<?php
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/DB.php';
require __DIR__ . '/../src/LicenseService.php';

use LicSrv\{DB, LicenseService as Lic};

Lic::migrate();
$pdo = DB::pdo();

$key       = $argv[1] ?? 'ABCD-1234-EFGH-5678';
$expires   = 0;            // 0 = never
$maxSites  = 3;            // seats
$now       = time();

$pdo->prepare('INSERT INTO licenses (lic_key, plan, max_sites, status, expires_at, created_at, updated_at)
VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE updated_at=VALUES(updated_at)')
    ->execute([$key, 'pro', $maxSites, 'active', $expires, $now, $now]);

echo "Seeded key: $key\n";