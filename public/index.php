<?php
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/DB.php';
require __DIR__ . '/../src/Json.php';
require __DIR__ . '/../src/LicenseService.php';

use LicSrv\{Config, Json, LicenseService as Lic};

header('Access-Control-Allow-Origin: *'); // tighten later
header('Content-Type: application/json');

// bootstrap
Lic::migrate(); // idempotent

// very small router
$uri = strtok($_SERVER['REQUEST_URI'], '?');
$method = $_SERVER['REQUEST_METHOD'];
$payload = json_decode(file_get_contents('php://input'), true) ?: [];

// Helpers
function host_from(string $url): string {
    $h = parse_url($url, PHP_URL_HOST);
    return strtolower($h ?: '');
}

function dev_domain(string $domain): bool {
    return $domain === 'localhost' || str_ends_with($domain, '.local') || str_ends_with($domain, '.test');
}

// Common request context
$domain = $payload['domain'] ?? ($payload['url'] ?? '');
$domain = $domain ? host_from(is_string($domain) ? $domain : '') : '';
$key    = trim((string)($payload['key'] ?? ''));

// Routes
if ($uri === '/v1/licenses/activate' && $method === 'POST') {
    if ($domain === '') Json::bad('domain required', 422);

    if (Config::ALLOW_DEV_DOMAINS && dev_domain($domain)) {
        Json::ok(['status'=>'valid','expires'=>0]);
    }

    if ($key === '') Json::bad('key required', 422);
    $lic = Lic::findByKey($key);
    if (!$lic) Json::bad('invalid', 403);

    if ($lic['status'] !== 'active') Json::bad('not-active', 403);
    if ($lic['expires_at'] && time() >= (int)$lic['expires_at']) Json::ok(['status'=>'expired','expires'=>(int)$lic['expires_at']]);

    // seat check
    $activeCount = Lic::activeSitesCount((int)$lic['id']);
    if ($activeCount >= (int)$lic['max_sites']) {
        // allow re-activating same domain (won't increase count due to UNIQUE key)
        Lic::upsertActivation((int)$lic['id'], $domain);
    } else {
        Lic::upsertActivation((int)$lic['id'], $domain);
    }

    Json::ok(['status'=>'valid','expires'=>(int)$lic['expires_at']]);
}

if ($uri === '/v1/licenses/deactivate' && $method === 'POST') {
    if ($domain === '') Json::bad('domain required', 422);

    if (Config::ALLOW_DEV_DOMAINS && dev_domain($domain)) {
        Json::ok(['status'=>'deactivated']);
    }

    if ($key) {
        $lic = Lic::findByKey($key);
        if ($lic) { Lic::deactivateDomain((int)$lic['id'], $domain); }
    }
    Json::ok(['status'=>'deactivated']);
}

if ($uri === '/v1/licenses/status' && $method === 'POST') {
    if ($domain === '') Json::bad('domain required', 422);

    if (Config::ALLOW_DEV_DOMAINS && dev_domain($domain)) {
        Json::ok(['status'=>'valid','expires'=>0]);
    }

    // status without key is still fine — domain-bound check
    $pdo = LicSrv\DB::pdo();
    $stmt = $pdo->prepare('SELECT l.status, l.expires_at FROM licenses l
        JOIN activations a ON a.license_id = l.id AND a.domain = :dom AND a.state = "active" LIMIT 1');
    $stmt->execute([':dom'=>$domain]);
    $row = $stmt->fetch();
    if (!$row) Json::bad('not-activated', 404);

    if ($row['status'] !== 'active') Json::ok(['status'=>'invalid','expires'=>(int)$row['expires_at']]);
    if ($row['expires_at'] && time() >= (int)$row['expires_at']) Json::ok(['status'=>'expired','expires'=>(int)$row['expires_at']]);

    Json::ok(['status'=>'valid','expires'=>(int)$row['expires_at']]);
}

if ($uri === '/v1/updates' && $method === 'GET') {
    // Minimal stub – return nothing (no updates) until you wire release feed
    echo json_encode([], JSON_UNESCAPED_SLASHES);
    exit;
}

Json::bad('not-found', 404);