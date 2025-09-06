<?php
// scripts/create_smoke_key.php
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/DB.php';
require __DIR__ . '/../src/LicenseService.php';
use LicSrv\DB;
$pdo = DB::pdo();
$now = time();
$key = $argv[1] ?? 'SMOKE-TEST-0002';
$pdo->prepare('INSERT INTO licenses (lic_key, plan, max_sites, status, expires_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?)')
    ->execute([$key, 'pro', 2, 'active', 0, $now, $now]);
echo "Inserted license: $key\n";
