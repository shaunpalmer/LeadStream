<?php
namespace LicSrv;

final class Config {
    // Local test DB (created by smoke test):
    public const DB_DSN  = 'mysql:host=127.0.0.1;dbname=leadstream_lic;charset=utf8mb4';
    public const DB_USER = 'leadstream_user';
    public const DB_PASS = 'secure-pass';
    public const ALLOW_DEV_DOMAINS = true; // localhost/.local/.test

    // Safety rails
    // NOTE: OWNER_MASTER_KEY is an emergency bypass for the owner only. Remove or rotate this
    // constant before publishing the license server to production or include it via an env var.
    public const OWNER_MASTER_KEY = 'LS-OWNER-0000-0000-0000'; // your bypass key (owner only)
    // Domains listed here will always be considered valid. Keep empty for normal operation.
    public const OWNER_WHITELIST  = ['your-live-domain.tld'];  // domains you own; optional
}