<?php
// scripts/http_test_with_counts.php
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/DB.php';
require __DIR__ . '/../src/LicenseService.php';
use LicSrv\DB;

$key = $argv[1] ?? 'SMOKE-TEST-0002';
$base = 'http://127.0.0.1:8080';
$pdo = DB::pdo();

function post_json($base, $path, $data) {
    $url = $base . $path;
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($data),
            'ignore_errors' => true,
            'timeout' => 5,
        ]
    ];
    $ctx = stream_context_create($opts);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (!empty($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#HTTP/\d\.\d\s+(\d+)#', $h, $m)) { $code = (int)$m[1]; break; }
        }
    }
    echo "POST $path → HTTP $code\n";
    echo ($body ?: '') . "\n\n";
}

function get_active_count($pdo, $key) {
    $stmt = $pdo->prepare('SELECT id FROM licenses WHERE lic_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $id = $stmt->fetchColumn();
    if (!$id) return 0;
    $c = $pdo->prepare('SELECT COUNT(*) FROM activations WHERE license_id = ? AND state = "active"');
    $c->execute([(int)$id]);
    return (int)$c->fetchColumn();
}

echo "Using key: $key\n\n";

// clear activations first
$pdo->prepare('DELETE FROM activations WHERE license_id = (SELECT id FROM licenses WHERE lic_key = ? LIMIT 1)')->execute([$key]);

post_json($base, '/v1/licenses/activate', ['key'=>$key, 'url'=>'http://site1.test']);
echo "active_count=" . get_active_count($pdo, $key) . "\n\n";
post_json($base, '/v1/licenses/activate', ['key'=>$key, 'url'=>'http://site2.test']);
echo "active_count=" . get_active_count($pdo, $key) . "\n\n";
post_json($base, '/v1/licenses/activate', ['key'=>$key, 'url'=>'http://site3.test']);
echo "active_count=" . get_active_count($pdo, $key) . "\n\n";
post_json($base, '/v1/licenses/status', ['url'=>'http://site1.test']);

