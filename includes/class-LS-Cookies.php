<?php
/**
 * LeadStream Cookie & Session Bootstrap
 *
 * - Mints first-party anonymous ID cookie `lsid` (UUIDv4)
 * - Maintains lightweight `ls_meta` (return visits, first_seen, last_seen)
 * - Exposes helpers for reading IDs and updating meta safely
 *
 * Security: No PII stored in cookies. HttpOnly only for server-read cookies.
 */
class LS_Cookies {
    const COOKIE_ID   = 'lsid';
    const COOKIE_META = 'ls_meta';
    const COOKIE_TTL  = 7776000; // 90 days

    /**
     * Must run early, before output.
     */
    public static function bootstrap() : void {
        // Create ID if missing
        if ( empty($_COOKIE[self::COOKIE_ID]) ) {
            $uuid = self::uuidv4();
            self::set_cookie(self::COOKIE_ID, $uuid, true /*httpOnly*/);
            // New meta
            $meta = [
                'rv'    => 0, // return visits (increments when 24h+ since last_seen)
                'first' => time(),
                'last'  => time()
            ];
            self::set_cookie(self::COOKIE_META, json_encode($meta), false /*not httpOnly since JS may read*/);
        } else {
            // Update meta.last, and maybe rv if >24h gap
            $meta = self::get_meta();
            $now  = time();
            if (isset($meta['last']) && ($now - (int)$meta['last']) >= 86400) {
                $meta['rv'] = (int)($meta['rv'] ?? 0) + 1;
            }
            $meta['last'] = $now;
            self::set_cookie(self::COOKIE_META, json_encode($meta), false);
        }
    }

    public static function get_lsid() : ?string {
        return $_COOKIE[self::COOKIE_ID] ?? null;
    }

    public static function get_meta() : array {
        if (!empty($_COOKIE[self::COOKIE_META])) {
            $decoded = json_decode(strval($_COOKIE[self::COOKIE_META]), true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private static function set_cookie(string $name, string $value, bool $httpOnly = false) : void {
        // Requires PHP 7.3+ for options array
        $secure = is_ssl();
        setcookie($name, $value, [
            'expires'  =>
