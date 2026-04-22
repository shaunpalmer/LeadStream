<?php
// scripts/clear_activations.php
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/DB.php';
require __DIR__ . '/../src/LicenseService.php';
use LicSrv\DB;
$pdo = DB::pdo();
$key = $argv[1] ?? null;
if (!$key) {
    echo "Usage: php clear_activations.php <license_key>\n";
    exit(1);
}
$stmt = $pdo->prepare('SELECT id FROM licenses WHERE lic_key = ? LIMIT 1');
$stmt->execute([$key]);
$id = $stmt->fetchColumn();
if (!$id) {
    echo "No license with key $key\n";
    exit(1);
}
$del = $pdo->prepare('DELETE FROM activations WHERE license_id = ?');
$del->execute([(int)$id]);
echo "Deleted activations for $key\n";
