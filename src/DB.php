<?php
namespace LicSrv;
use PDO; use PDOException;

final class DB {
    private static ?PDO $pdo = null;
    public static function pdo(): PDO {
        if (!self::$pdo) {
            // Allow overriding DB connection via environment variables for local testing.
            $dsn  = getenv('LS_DB_DSN')  ?: Config::DB_DSN;
            $user = getenv('LS_DB_USER') ?: Config::DB_USER;
            $pass = getenv('LS_DB_PASS') ?: Config::DB_PASS;

            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pdo;
    }
}