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
 * TODO [COOKIE-002] DUPLICATE CLASS — this file and includes/class-LS-Cookies.php
 * both declare the global class 'LS_Cookies'. Only ONE can be loaded per request.
 * If the autoloader (or any require_once) loads the root copy first, this file is
 * irrelevant; if both are somehow loaded, PHP will throw a fatal class-redeclaration
 * error. The two files must be consolidated into a single authoritative source.
 * The root copy (includes/class-LS-Cookies.php) is truncated and broken; this copy
 * is syntactically valid but is missing the uuidv4() method — see TODO [COOKIE-003].
 *
 * TODO [COOKIE-003] MISSING METHOD — bootstrap() calls self::uuidv4() but this file
 * does not define uuidv4(). A working implementation exists in
 * includes/Tracking/CookieManager::uuidv4() and can be copied here, or this entire
 * class can be replaced by CookieManager which is more complete.
 *
 * TODO [COOKIE-004] INDENTATION / BRACE ALIGNMENT in set_cookie() below is
 * misaligned (extra indent levels). The method body is technically valid PHP but
 * the visual structure is misleading — it looks like nested blocks but they are not.
 * Clean up the indentation when the above TODOs are resolved.
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
            // TODO [COOKIE-003]: self::uuidv4() is called here but uuidv4() is not
            // defined in this class. This will throw an Error at runtime.
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

    // TODO [COOKIE-004]: Indentation is misaligned inside this method — looks like
    // nested scopes but the braces at lines 65-66 simply close the method and class.
    private static function set_cookie(string $name, string $value, bool $httpOnly = false) : void {
        // Requires PHP 7.3+ for options array
        $secure = is_ssl();
                setcookie($name, $value, [
                    'expires'  => time() + self::COOKIE_TTL,
                    'path'     => '/',
                    'secure'   => $secure,
                    'httponly' => $httpOnly,
                    'samesite' => 'Lax'
                ]);
            }
        }
