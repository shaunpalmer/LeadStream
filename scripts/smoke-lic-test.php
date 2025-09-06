<?php
// scripts/smoke-lic-test.php
// Quick smoke test for license server.
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/DB.php';
require __DIR__ . '/../src/LicenseService.php';

use LicSrv\{DB, LicenseService as Lic};

echo "Running license smoke test...\n";
Lic::migrate();
$pdo = DB::pdo();

// Create a test key
$key = 'SMOKE-' . bin2hex(random_bytes(4));
$now = time();
$expires = $now + 3600; // 1 hour
$maxSites = 2;
$pdo->prepare('INSERT INTO licenses (lic_key, plan, max_sites, status, expires_at, created_at, updated_at)
    VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE updated_at=VALUES(updated_at)')
    ->execute([$key, 'pro', $maxSites, 'active', $expires, $now, $now]);

echo "Created test key: $key (seats=$maxSites, expires=" . date('c', $expires) . ")\n";

$domains = ['alpha.test','beta.test','gamma.test'];
foreach ($domains as $d) {
    echo "Activating $d... ";
    try {
        Lic::upsertActivation((int)$pdo->lastInsertId() ?: (int)$pdo->query("SELECT id FROM licenses WHERE lic_key='{$key}' LIMIT 1")->fetchColumn(), $d);
        $count = Lic::activeSitesCount((int)$pdo->query("SELECT id FROM licenses WHERE lic_key='{$key}' LIMIT 1")->fetchColumn());
        echo "OK (active sites now: $count)\n";
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

// Show activations
$stmt = $pdo->prepare('SELECT a.domain, a.state FROM activations a JOIN licenses l ON a.license_id = l.id WHERE l.lic_key = ?');
$stmt->execute([$key]);
$rows = $stmt->fetchAll();

echo "Activations for $key:\n";
foreach ($rows as $r) {
    echo " - {$r['domain']} ({$r['state']})\n";
}

echo "Smoke test complete.\n";
