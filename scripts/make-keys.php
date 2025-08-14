<?php
// scripts/make-keys.php
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/DB.php';
require __DIR__ . '/../src/LicenseService.php';

use LicSrv\{DB, LicenseService as Lic};

Lic::migrate();
$pdo = DB::pdo();

$opts = getopt('', ['count::', 'seats::', 'days::', 'plan::', 'prefix::']);
$count  = max(1, (int)($opts['count']  ?? 10));
$seats  = max(1, (int)($opts['seats']  ?? 1));
$days   = max(0, (int)($opts['days']   ?? 0));   // 0 = lifetime
$plan   = (string)($opts['plan']       ?? 'pro');
$prefix = strtoupper((string)($opts['prefix']    ?? 'LS'));

function make_key(string $prefix): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/I/1
    $blk = function() use ($chars) {
        $s = '';
        for ($i=0; $i<4; $i++) $s .= $chars[random_int(0, strlen($chars)-1)];
        return $s;
    };
    return $prefix . '-' . $blk() . '-' . $blk() . '-' . $blk() . '-' . $blk();
}

$expires = $days > 0 ? (time() + $days*86400) : 0;
$now = time();

$csv = [];
for ($i=0; $i<$count; $i++) {
    $key = make_key($prefix);
    $pdo->prepare('INSERT INTO licenses
        (lic_key, plan, max_sites, status, expires_at, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?)')
        ->execute([$key, $plan, $seats, 'active', $expires, $now, $now]);
    $csv[] = [$key, $plan, $seats, $expires];
    echo "Created key: $key  seats=$seats  expires=" . ($expires ?: 'never') . PHP_EOL;
}

$fname = __DIR__ . '/keys_' . date('Ymd_His') . '.csv';
$fh = fopen($fname, 'w');
fputcsv($fh, ['key','plan','seats','expires_unix']);
foreach ($csv as $row) fputcsv($fh, $row);
fclose($fh);
echo "Saved CSV: $fname\n";
