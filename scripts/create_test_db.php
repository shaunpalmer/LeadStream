<?php
// scripts/create_test_db.php
try {
    $pdo = new PDO('mysql:host=localhost', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS leadstream_lic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("CREATE USER IF NOT EXISTS 'leadstream_user'@'localhost' IDENTIFIED BY 'secure-pass';");
    $pdo->exec("GRANT ALL PRIVILEGES ON leadstream_lic.* TO 'leadstream_user'@'localhost';");
    $pdo->exec("FLUSH PRIVILEGES;");
    echo "DB and user created (or already exist)\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
