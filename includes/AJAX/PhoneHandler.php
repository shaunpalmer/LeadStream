<?php
namespace LS\AJAX;

defined('ABSPATH') || exit;

/**
 * Phone Click Handler - Enhanced for robust tracking
 * Handles AJAX requests for phone click tracking with improved data structure
 */
class PhoneHandler {
    
    public static function init() {
        // AJAX handlers for both logged in and logged out users
        add_action('wp_ajax_leadstream_record_phone_click', [__CLASS__, 'record_phone_click']);
        add_action('wp_ajax_nopriv_leadstream_record_phone_click', [__CLASS__, 'record_phone_click']);
    }
    
    /**
     * Record phone click in database with enhanced tracking
     */
    public static function record_phone_click() {
        // Verify nonce (accept current and legacy tokens for compatibility)
        $nonce = $_POST['nonce'] ?? '';
        $valid_nonce = wp_verify_nonce($nonce, 'leadstream_phone_click') ||
                       wp_verify_nonce($nonce, 'leadstream_phone_nonce'); // legacy
        if (!$valid_nonce) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Get data from improved frontend format
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $original_phone = sanitize_text_field($_POST['original_phone'] ?? '');
        $element_type = sanitize_text_field($_POST['element_type'] ?? 'unknown');
        $element_class = sanitize_text_field($_POST['element_class'] ?? '');
        $element_id = sanitize_text_field($_POST['element_id'] ?? '');
        $page_url = esc_url_raw($_POST['page_url'] ?? '');
        $page_title = sanitize_text_field($_POST['page_title'] ?? '');
        
        // Validate required data
        if (empty($phone)) {
            wp_send_json_error('Phone number is required');
            return;
        }
        
        // Verify this phone number is in our tracked list
        $tracked_numbers = get_option('leadstream_phone_numbers', array());
        $is_tracked = false;
        
        foreach ($tracked_numbers as $tracked_number) {
            $phone_normalized = preg_replace('/\D/', '', $phone);
            $tracked_normalized = preg_replace('/\D/', '', $tracked_number);
            
            if (strpos($phone_normalized, $tracked_normalized) !== false || 
                strpos($tracked_normalized, $phone_normalized) !== false) {
                $is_tracked = true;
                break;
            }
        }
        
        if (!$is_tracked) {
            wp_send_json_error('Phone number not in tracking list');
            return;
        }
        
        // Get additional context
        $user_id = get_current_user_id();
        $ip_address = self::get_client_ip();
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        $referrer = esc_url_raw($_SERVER['HTTP_REFERER'] ?? '');
        
        // Prepare enhanced data with date/time split for better reporting
        $now = current_time('mysql');
        $date_obj = new \DateTime($now);
        
        global $wpdb;
        
        // Enhanced phone tracking metadata
        $meta_data = [
            'original_phone' => $original_phone,
            'normalized_phone' => $phone,
            'element_type' => $element_type,
            'element_class' => $element_class,
            'element_id' => $element_id,
            'page_title' => $page_title,
            'tracking_method' => 'leadstream_phone_v2', // Version tracking
            'click_timestamp' => time()
        ];
        
        // Insert with improved structure
        $result = $wpdb->insert(
            $wpdb->prefix . 'ls_clicks',
            [
                'link_type' => 'phone',
                'link_key' => $phone, // Already normalized digits from frontend
                'target_url' => 'tel:' . $original_phone,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'user_id' => $user_id ?: null,
                'referrer' => $referrer,
                'clicked_at' => $now, // Backward compatibility
                'click_datetime' => $now,
                'click_date' => $date_obj->format('Y-m-d'),
                'click_time' => $date_obj->format('H:i:s'),
                'created_at' => $now,
                'meta_data' => wp_json_encode($meta_data)
            ],
            [
                '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            ]
        );
        
        if ($result === false) {
            error_log('LeadStream: Failed to insert phone click - ' . $wpdb->last_error);
            wp_send_json_error('Database error');
            return;
        }
        
        // Log successful tracking for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("LeadStream: Phone click recorded - {$original_phone} -> {$phone} on {$page_url}");
        }
        
        // Success response with enhanced data
        wp_send_json_success([
            'message' => 'Phone click recorded successfully',
            'phone' => $phone,
            'original_phone' => $original_phone,
            'timestamp' => $now,
            'click_id' => $wpdb->insert_id,
            'tracking_method' => 'v2'
        ]);
    }
    
    /**
     * Get client IP address with enhanced proxy support
     */
    private static function get_client_ip() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_X_REAL_IP',           // Nginx real IP
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field($_SERVER[$header]);
                
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip_list = explode(',', $ip);
                    $ip = trim($ip_list[0]);
                }
                
                // Validate IP (exclude private and reserved ranges)
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }
}
