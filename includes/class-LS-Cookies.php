<?php
/**
 * LeadStream Cookie & Session Bootstrap
 *
 * - Mints first-party anonymous ID cookie `lsid` (UUIDv4)
 * - Maintains lightweight `ls_meta` (return visits, first_seen, last_seen)
 * - Exposes helpers for reading IDs and updating meta safely
 *
 * Security: No PII stored in cookies. HttpOnly only for server-read cookies.
 *
 * TODO [COOKIE-001] CRITICAL — THIS FILE IS TRUNCATED / INCOMPLETE.
 * It ends mid-statement at line 59 with an unclosed array literal:
 *     setcookie($name, $value, [
 *         'expires'  =>
 * PHP will throw a Parse error (Unclosed '[') when this file is loaded, which
 * will cause a fatal error and take the entire plugin down.
 *
 * Additionally, the uuidv4() method is missing entirely. bootstrap() calls
 * self::uuidv4() on line 22, which would throw an Error: Call to undefined method.
 *
 * A complete copy of this class (with set_cookie() and uuidv4() intact) exists at
 * includes/Tracking/class-LS-Cookies.php, but that copy also has its own issues —
 * see TODO [COOKIE-002] in that file.
 *
 * Resolution options:
 *   A) Complete this file (add uuidv4() method and close set_cookie() properly).
 *   B) Delete this file and rely on includes/Tracking/class-LS-Cookies.php after
 *      fixing it.
 *   C) Replace both with includes/Tracking/CookieManager.php which is fully
 *      functional and already contains uuidv4().
 * Either way, having TWO files defining the same global class 'LS_Cookies' must be
 * resolved — only one can be loaded or PHP will fatal with a class redeclaration error.
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
            // TODO [COOKIE-001]: self::uuidv4() is called here but the method is
            // missing from this file — fatal Error if this code ever runs.
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

    // TODO [COOKIE-001]: set_cookie() is incomplete — the array literal and method
    // body were cut off. The remainder of this method and the uuidv4() method are
    // missing. Do not load this file until both methods are restored.
    private static function set_cookie(string $name, string $value, bool $httpOnly = false) : void {
        // Requires PHP 7.3+ for options array
        $secure = is_ssl();
        setcookie($name, $value, [
            'expires'  =>
