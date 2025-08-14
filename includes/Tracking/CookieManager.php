<?php
namespace LeadStream\Tracking;

defined('ABSPATH') || exit;

/**
 * LeadStream CookieManager
 *
 * - First-party cookie CRUD with Secure/HttpOnly/SameSite options
 * - Visitor/session management (UUID, first/last seen, per-visit session)
 * - Safe IP hashing for server analytics
 * - One-shot migration from legacy prefixes (e.g., ays_)
 *
 * PHP 7.3+ (array options for setcookie)
 */
final class CookieManager
{
    // Cookie keys (LeadStream)
    public const COOKIE_ID       = 'ls_vid';         // HttpOnly (canonical)
    public const COOKIE_ID_PUB   = 'ls_vid_pub';     // JS-readable mirror
    public const COOKIE_FIRST    = 'ls_first_seen';  // ISO8601
    public const COOKIE_LAST     = 'ls_last_seen';   // ISO8601
    public const COOKIE_SESSION  = 'ls_session_id';  // per-visit token
    public const COOKIE_PREFIX   = 'ls_';

    // Tunables
    public const LONG_TTL_SEC    = 400 * 24 * 60 * 60; // ~400 days (Chrome cap)
    public const SESSION_TTL_SEC = 30 * 60;            // 30 min inactivity = new "visit"

    /** Boot early so headers are free */
    public static function boot(): void
    {
        // Run earlier than init to avoid headers already sent; priority 0
        add_action('plugins_loaded', [__CLASS__, 'ensure_core'], 0);
    }

    /** Ensure core cookies exist / are updated */
    public static function ensure_core(): void
    { // TODO: Fix early-exit when headers are already sent: this prevents core cookies from being created/migrated on pages where a theme/plugin outputs too early. Hook earlier (e.g., plugins_loaded at priority 0), add a JS fallback for public cookies, or conditionally buffer output to avoid silent analytics gaps.
        if (headers_sent()) return; // Cookies can't be set after output; see TODO above for mitigation strategy.

        // One-shot migration from any old prefix (e.g., ays_) — should be idempotent
        self::migrate_from_prefix('ays_');

        $now      = time();
        $is_https = is_ssl(); // Note: respects proxies; ensures we only set Secure cookies over HTTPS

        // Canonical visitor ID (HttpOnly) — server-only identifier, separate JS mirror below
        $vid = self::get(self::COOKIE_ID);
        if (!$vid) {
            $vid = self::uuidv4();
            self::set(self::COOKIE_ID, $vid, $now + self::LONG_TTL_SEC, [
                'secure'   => $is_https,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        // Public mirror for JS — not HttpOnly so front-end code can read it
        if (!self::has(self::COOKIE_ID_PUB)) {
            self::set(self::COOKIE_ID_PUB, $vid, $now + self::LONG_TTL_SEC, [
                'secure'   => $is_https,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }

        // First seen (one-shot) — ISO8601 UTC for analytics consistency
        if (!self::has(self::COOKIE_FIRST)) {
            self::set(self::COOKIE_FIRST, gmdate('c', $now), $now + self::LONG_TTL_SEC, [
                'secure'   => $is_https,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }

        // Last seen (touch each request) — updates per request to mark activity window
        self::touch_last_seen();

        // Session id (rotate after idle) — new token when inactivity > SESSION_TTL_SEC
        self::rotate_session_if_idle();
    }

    /** True if cookie exists (non-empty string) */
    public static function has(string $name): bool
    {
        return isset($_COOKIE[$name]) && $_COOKIE[$name] !== '';
    }

    /** Get cookie value or default */
    public static function get(string $name, $default = null)
    {
        return $_COOKIE[$name] ?? $default;
    }

    /**
     * Set cookie with sane defaults.
     * $expires: unix timestamp; use 0 for session cookie.
     * $opts: ['secure'=>bool,'httponly'=>bool,'samesite'=>'Lax|Strict|None','path'=>string,'domain'=>string]
     */
    public static function set(string $name, string $value, int $expires = 0, array $opts = []): bool
    {
        if (headers_sent()) {
            return false;
        }

        $defaults = [
            'path'     => '/',
            'domain'   => defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? COOKIE_DOMAIN : parse_url(home_url('/'), PHP_URL_HOST),
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ];
        $o = array_merge($defaults, $opts);

        // PHP 7.3+ array options syntax
        return setcookie($name, $value, [
            'expires'  => $expires,
            'path'     => $o['path'],
            'domain'   => $o['domain'],
            'secure'   => (bool) $o['secure'],
            'httponly' => (bool) $o['httponly'],
            'samesite' => $o['samesite'],
        ]);
    }

    /** Delete/expire a cookie by setting it with a past expiration */
    public static function delete(string $name, array $opts = []): bool
    {
        if (!self::has($name)) return true; // already gone
        $defaults = [
            'path'     => '/',
            'domain'   => defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? COOKIE_DOMAIN : parse_url(home_url('/'), PHP_URL_HOST),
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ];
        $o = array_merge($defaults, $opts);
        if (headers_sent()) {
            return false;
        }
        return setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => $o['path'],
            'domain'   => $o['domain'],
            'secure'   => (bool) $o['secure'],
            'httponly' => (bool) $o['httponly'],
            'samesite' => $o['samesite'],
        ]);
    }

    /** Update last_seen cookie to now */
    public static function touch_last_seen(): void
    {
        $now = time();
        self::set(self::COOKIE_LAST, gmdate('c', $now), $now + self::LONG_TTL_SEC, [
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    /** Rotate session id when idle > SESSION_TTL_SEC; always extend its TTL on activity */
    public static function rotate_session_if_idle(): void
    {
        $now = time();
        $last = self::get(self::COOKIE_LAST);
        $lastTs = $last ? strtotime($last) : 0;

        $sid = self::get(self::COOKIE_SESSION);
        $idleTooLong = !$lastTs || ($now - $lastTs) > self::SESSION_TTL_SEC;
        if (!$sid || $idleTooLong) {
            $sid = self::uuidv4();
        }

        // Always extend TTL so the visit stays alive during activity
        self::set(self::COOKIE_SESSION, $sid, $now + self::SESSION_TTL_SEC, [
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    /** One-shot migration from legacy prefix (e.g., ays_) to ls_ */
    public static function migrate_from_prefix(string $legacyPrefix): void
    {
        if (!$legacyPrefix || $legacyPrefix === self::COOKIE_PREFIX) return;
        if (headers_sent()) return;

        foreach ($_COOKIE as $key => $val) {
            if (strpos($key, $legacyPrefix) !== 0) continue;
            $newKey = self::COOKIE_PREFIX . substr($key, strlen($legacyPrefix));
            // Preserve value but normalize TTL to long-lived window
            self::set($newKey, (string) $val, time() + self::LONG_TTL_SEC, [
                'secure'   => is_ssl(),
                'httponly' => ($newKey === self::COOKIE_ID),
                'samesite' => 'Lax',
            ]);
            // Best-effort expire old
            self::delete($key, ['httponly' => ($key === $legacyPrefix . 'vid')]);
        }
    }

    /** UUID v4 generator */
    public static function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /** Stable hash of client IP using WP salt; returns hex */
    public static function hashed_ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $salt = function_exists('wp_salt') ? wp_salt('auth') : (defined('NONCE_SALT') ? NONCE_SALT : '');
        return hash('sha256', $ip . '|' . $salt);
    }

    /** Snapshot current tracking values for analytics inserts */
    public static function snapshot(): array
    {
        return [
            'vid'         => self::get(self::COOKIE_ID),
            'vid_pub'     => self::get(self::COOKIE_ID_PUB),
            'first_seen'  => self::get(self::COOKIE_FIRST),
            'last_seen'   => self::get(self::COOKIE_LAST),
            'session_id'  => self::get(self::COOKIE_SESSION),
        ];
    }

}

// -----------------
#add_action('template_redirect', function () {
#    $c = CookieManager::snapshot();
    // $c['vid'] is the canonical server ID; $c['session_id'] for visit; $c['first_seen']/['last_seen'] are ISO
    // Example: stitch a pageview
    /*
    global $wpdb;
    $wpdb->insert($wpdb->prefix.'ls_analytics', [
        'vid'        => $c['vid'],
        'session_id' => $c['session_id'],
        'path'       => esc_url_raw($_SERVER['REQUEST_URI'] ?? '/'),
        'ref'        => esc_url_raw($_SERVER['HTTP_REFERER'] ?? ''),
        'ip_hash'    => CookieManager::hashed_ip(),
        'ua'         => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 255),
        'ts'         => current_time('mysql', true),
    ], ['%s','%s','%s','%s','%s','%s','%s']);
    */
#});


// TODO
#add_action('wp_enqueue_scripts', function () {
#    wp_enqueue_script(
#        'ls-cookies',
#       plugins_url('assets/js/ls-cookies.js', __FILE__),
#        [],
#        '1.0.0',
#        true
#    );
#});
