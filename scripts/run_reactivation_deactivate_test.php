<?php
// scripts/run_reactivation_deactivate_test.php
$key = $argv[1] ?? 'SMOKE-TEST-0005';
$base = 'http://127.0.0.1:8080';
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
    echo "POST $path -> HTTP $code\n";
    echo ($body ?: '') . "\n\n";
}

echo "Using key: $key\n\n";
$domain = 'http://react.test';

// Activate 1
post_json($base, '/v1/licenses/activate', ['key'=>$key, 'url'=>$domain]);
// Activate 2 (same domain)
post_json($base, '/v1/licenses/activate', ['key'=>$key, 'url'=>$domain]);
// Deactivate
post_json($base, '/v1/licenses/deactivate', ['key'=>$key, 'url'=>$domain]);
// Status
post_json($base, '/v1/licenses/status', ['url'=>$domain]);

echo "Done.\n";
