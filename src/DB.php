<?php
namespace LicSrv;
use PDO; use PDOException;

final class DB {
    private static ?PDO $pdo = null;
    public static function pdo(): PDO {
        if (!self::$pdo) {
            self::$pdo = new PDO(Config::DB_DSN, Config::DB_USER, Config::DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pdo;
    }
}