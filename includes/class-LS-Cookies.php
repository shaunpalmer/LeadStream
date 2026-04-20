<?php
/**
 * LeadStream Cookie & Session Bootstrap (root copy)
 *
 * Source of truth: includes/Tracking/class-LS-Cookies.php
 * This file is the original root copy that was previously truncated.
 * It is now completed and guarded against redeclaration so that loading
 * either copy first is safe. On a case-sensitive filesystem the autoloader
 * cannot find either file (filename case mismatch), but an explicit
 * require_once of this file will no longer cause a parse or redeclaration error.
 *
 * Security: No PII stored in cookies. HttpOnly only for server-read cookies.
 */

// Guard: if Tracking copy was already loaded, do not redeclare.
if ( class_exists( 'LS_Cookies' ) ) {
    return;
}

class LS_Cookies {
    const COOKIE_ID   = 'lsid';
    const COOKIE_META = 'ls_meta';
    const COOKIE_TTL  = 7776000; // 90 days

    /**
     * Must run early, before output.
     */
    public static function bootstrap() : void {
        if ( empty( $_COOKIE[ self::COOKIE_ID ] ) ) {
            $uuid = self::uuidv4();
            self::set_cookie( self::COOKIE_ID, $uuid, true );
            $meta = [
                'rv'    => 0,
                'first' => time(),
                'last'  => time(),
            ];
            self::set_cookie( self::COOKIE_META, json_encode( $meta ), false );
        } else {
            $meta = self::get_meta();
            $now  = time();
            if ( isset( $meta['last'] ) && ( $now - (int) $meta['last'] ) >= 86400 ) {
                $meta['rv'] = (int) ( $meta['rv'] ?? 0 ) + 1;
            }
            $meta['last'] = $now;
            self::set_cookie( self::COOKIE_META, json_encode( $meta ), false );
        }
    }

    public static function get_lsid() : ?string {
        return $_COOKIE[ self::COOKIE_ID ] ?? null;
    }

    public static function get_meta() : array {
        if ( ! empty( $_COOKIE[ self::COOKIE_META ] ) ) {
            $decoded = json_decode( strval( $_COOKIE[ self::COOKIE_META ] ), true );
            return is_array( $decoded ) ? $decoded : [];
        }
        return [];
    }

    /** UUID v4 generator (requires PHP 7.0+ random_bytes) */
    public static function uuidv4() : string {
        $data    = random_bytes( 16 );
        $data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
        $data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );
        return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
    }

    private static function set_cookie( string $name, string $value, bool $httpOnly = false ) : void {
        $secure = is_ssl();
        setcookie( $name, $value, [
            'expires'  => time() + self::COOKIE_TTL,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => $httpOnly,
            'samesite' => 'Lax',
        ] );
    }
}
