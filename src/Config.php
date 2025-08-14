<?php
namespace LicSrv;

final class Config {
    public const DB_DSN  = 'mysql:host=127.0.0.1;dbname=leadstream_lic;charset=utf8mb4';
    public const DB_USER = 'leadstream_user';
    public const DB_PASS = 'change-me';
    public const ALLOW_DEV_DOMAINS = true; // localhost/.local/.test
}