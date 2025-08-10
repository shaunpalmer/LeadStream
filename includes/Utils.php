<?php
/**
 * LeadStream Utilities
 * Shared helper functions and sanitizers
 */

namespace LS;

defined('ABSPATH') || exit;

class Utils {
    
    /**
     * Custom sanitization for JavaScript - preserves code integrity while ensuring security
     */
    public static function sanitize_javascript($input) {
        // Only allow if user has proper capabilities
        if (!current_user_can('manage_options')) {
            return '';
        }
        
        $sanitized = trim($input);
        
        // Security check: Block potentially dangerous PHP tags (shouldn't be in JS anyway)
        if (strpos($sanitized, '<?') !== false) {
            return '';
        }
        
        // Determine which field is being sanitized
        $field = '';
        if (isset($_POST['option_page'])) {
            if (isset($_POST['custom_header_js']) && $_POST['custom_header_js'] === $input) {
                $field = 'header';
            } elseif (isset($_POST['custom_footer_js']) && $_POST['custom_footer_js'] === $input) {
                $field = 'footer';
            }
        }
        
        if ($field) {
            self::check_placeholder($sanitized, $field);
            
            // Double-injection warning: if header and footer JS are identical
            if ($field === 'header' || $field === 'footer') {
                $header_js = isset($_POST['custom_header_js']) ? trim($_POST['custom_header_js']) : get_option('custom_header_js', '');
                $footer_js = isset($_POST['custom_footer_js']) ? trim($_POST['custom_footer_js']) : get_option('custom_footer_js', '');
                
                if ($header_js !== '' && $footer_js !== '' && $header_js === $footer_js) {
                    add_settings_error(
                        'leadstream_double_injection',
                        'leadstream_double_injection_warning',
                        'Warning: You are injecting the same code in both header and footer. This may cause double tracking or conflicts. Please use different scripts for each section.',
                        'warning'
                    );
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * DRY placeholder check for both header and footer JS
     */
    public static function check_placeholder($code, $field) {
        // Match any bracketed text containing 'service' or 'product' (case-insensitive, any extra words, slashes, spaces)
        $pattern = '/\[[^\]]*(service|product)[^\]]*\]/i';
        
        if (preg_match($pattern, $code)) {
            switch ($field) {
                case 'header':
                    add_settings_error(
                        'custom_header_js',
                        'leadstream_placeholder_header',
                        'Warning: Please replace [Your Service/Product] with your actual service or product name before using this code in the header.',
                        'error'
                    );
                    break;
                case 'footer':
                    add_settings_error(
                        'custom_footer_js',
                        'leadstream_placeholder_footer',
                        'Warning: Please replace [Your Service/Product] with your actual service or product name before using this code in the footer.',
                        'error'
                    );
                    break;
            }
        }
    }
    
    /**
     * Get plugin version
     */
    public static function get_version() {
        return '2.5.5';
    }
    
    /**
     * Get plugin path constants
     */
    public static function get_plugin_url() {
        return plugin_dir_url(dirname(__DIR__));
    }
    
    public static function get_plugin_path() {
        return dirname(dirname(__DIR__));
    }

    /**
     * Base62 encode/decode helpers for compact IDs (for short URLs)
     */
    public static function base62_encode($number) {
        $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $base = strlen($alphabet);
        $n = intval($number);
        if ($n <= 0) { return '0'; }
        $out = '';
        while ($n > 0) {
            $out = $alphabet[$n % $base] . $out;
            $n = intdiv($n, $base);
        }
        return $out;
    }

    public static function base62_decode($str) {
        $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $base = strlen($alphabet);
        $s = (string)$str; $len = strlen($s); $n = 0;
        for ($i = 0; $i < $len; $i++) {
            $pos = strpos($alphabet, $s[$i]);
            if ($pos === false) { return 0; }
            $n = $n * $base + $pos;
        }
        return $n;
    }
}
