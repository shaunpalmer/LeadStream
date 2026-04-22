<?php
namespace LicSrv;
use PDO;

final class LicenseService {
    public static function migrate(): void {
        $pdo = DB::pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS licenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lic_key VARCHAR(64) NOT NULL UNIQUE,
            plan VARCHAR(32) NOT NULL DEFAULT "pro",
            max_sites INT NOT NULL DEFAULT 1,
            status ENUM("active","revoked","expired") NOT NULL DEFAULT "active",
            expires_at INT NOT NULL DEFAULT 0,
            meta JSON NULL,
            created_at INT NOT NULL,
            updated_at INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

        $pdo->exec('CREATE TABLE IF NOT EXISTS activations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            license_id INT NOT NULL,
            domain VARCHAR(191) NOT NULL,
            state ENUM("active","deactivated") NOT NULL DEFAULT "active",
            first_seen INT NOT NULL,
            last_seen INT NOT NULL,
            UNIQUE KEY uniq_lic_domain (license_id, domain),
            FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
    }

    public static function findByKey(string $key): ?array {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT * FROM licenses WHERE lic_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function upsertActivation(int $licenseId, string $domain): void {
        $now = time();
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO activations (license_id, domain, state, first_seen, last_seen)
            VALUES (:lid, :dom, "active", :now, :now)
            ON DUPLICATE KEY UPDATE state = "active", last_seen = :now2');
        $stmt->execute([':lid'=>$licenseId, ':dom'=>$domain, ':now'=>$now, ':now2'=>$now]);
    }

    public static function deactivateDomain(int $licenseId, string $domain): void {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE activations SET state="deactivated", last_seen=:now WHERE license_id=:lid AND domain=:dom');
        $stmt->execute([':now'=>time(), ':lid'=>$licenseId, ':dom'=>$domain]);
    }

    public static function activeSitesCount(int $licenseId): int {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) c FROM activations WHERE license_id=? AND state="active"');
        $stmt->execute([$licenseId]);
        return (int) $stmt->fetchColumn();
    }
}