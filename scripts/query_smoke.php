<?php
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/DB.php';
require __DIR__ . '/../src/LicenseService.php';
use LicSrv\DB;
$pdo = DB::pdo();
$key = $argv[1] ?? 'SMOKE-4c1c6f2e';
$stmt = $pdo->prepare('SELECT id,lic_key,max_sites,created_at FROM licenses WHERE lic_key=?');
$stmt->execute([$key]);
$row = $stmt->fetch();
echo "LICENSE:\n"; print_r($row);
$stmt2 = $pdo->prepare('SELECT domain,state,first_seen,last_seen FROM activations WHERE license_id=?');
$stmt2->execute([(int)$row['id']]);
echo "ACTIVATIONS:\n"; print_r($stmt2->fetchAll());
