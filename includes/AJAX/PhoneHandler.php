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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $dbg_origin = isset($_REQUEST['origin']) ? sanitize_text_field($_REQUEST['origin']) : '';
            $dbg_orig_phone = isset($_REQUEST['original_phone']) ? sanitize_text_field($_REQUEST['original_phone']) : '';
            $dbg_digits = isset($_REQUEST['phone']) ? preg_replace('/\D/', '', (string) $_REQUEST['phone']) : '';
            error_log("LeadStream[PhoneHandler]: start origin={$dbg_origin} original_phone={$dbg_orig_phone} digits={$dbg_digits}");
        }
        $nonce = $_POST['nonce'] ?? '';
        $valid_nonce = wp_verify_nonce($nonce, 'leadstream_phone_click') ||
                       wp_verify_nonce($nonce, 'leadstream_phone_nonce'); // legacy
        if (!$valid_nonce) {
            wp_send_json_error('Invalid security token');
            return;
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LeadStream[PhoneHandler]: nonce OK');
        }
        
    // Get data from improved frontend format
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $original_phone = sanitize_text_field($_POST['original_phone'] ?? '');
    $origin = sanitize_text_field($_POST['origin'] ?? '');
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
        
        // Normalize once
        $phone_normalized = preg_replace('/\D/', '', $phone);

        // --- Belt & braces: lightweight server-side anti-duplication ---
        try {
            $vid    = isset($_COOKIE['ls_vid']) ? sanitize_text_field($_COOKIE['ls_vid']) : '';
            $origin_key = $origin ?: (($element_type === 'callbar') ? 'callbar' : 'web');
            $bucket = (string) floor(time() / 2); // 2-second bucket
            $fp     = sha1($vid . '|' . $phone_normalized . '|' . $origin_key . '|' . $bucket);
            $tkey   = 'ls_click_dupe_' . $fp;
            if (get_transient($tkey)) {
                wp_send_json_success(['ok' => true, 'dupe' => 1]);
            }
            set_transient($tkey, 1, 5); // hold briefly to collapse doubles
        } catch (\Throwable $e) {
            // no-op: never break tracking on anti-dupe failure
        }

        // Verify this phone number is in our tracked list, unless it comes from the Call Bar
        $tracked_numbers = get_option('leadstream_phone_numbers', array());
        $is_tracked = false;
        foreach ($tracked_numbers as $tracked_number) {
            $tracked_normalized = preg_replace('/\D/', '', $tracked_number);
            if ($tracked_normalized === '') { continue; }
            if (strpos($phone_normalized, $tracked_normalized) !== false || strpos($tracked_normalized, $phone_normalized) !== false) {
                $is_tracked = true;
                break;
            }
        }
        // Treat origin values from Call Bar as trusted: 'callbar', 'auto', 'shortcode', or element_type === 'callbar'
        $is_callbar = ($element_type === 'callbar') || in_array(strtolower($origin), array('callbar','auto','shortcode'), true);
        if (!$is_callbar && !$is_tracked) {
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
            'normalized_phone' => $phone_normalized,
            'element_type' => $element_type,
            'element_class' => $element_class,
            'element_id' => $element_id,
            'page_title' => $page_title,
            'origin' => ($origin ?: ($element_type === 'callbar' ? 'callbar' : '')),
            'tracking_method' => 'leadstream_phone_v2', // Version tracking
            'click_timestamp' => time()
        ];
        
        // Insert with improved structure
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("LeadStream[PhoneHandler]: inserting origin={$meta_data['origin']} digits={$phone_normalized} original_phone={$original_phone}");
        }
        $result = $wpdb->insert(
            $wpdb->prefix . 'ls_clicks',
            [
                'link_type' => 'phone',
                'link_key' => $phone_normalized, // normalized digits
                // Store the posted original as-is (visible/dialed), no tel: prefix
                'target_url' => $original_phone,
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LeadStream[PhoneHandler]: insert OK id=' . (int) $wpdb->insert_id);
        }
        
        // Optional: Server-side GA4 Measurement Protocol event
        $ga4_id     = get_option('leadstream_ga4_id');
        $ga4_secret = get_option('leadstream_ga4_secret');
        if (!empty($ga4_id) && !empty($ga4_secret)) {
            $ga4_url = 'https://www.google-analytics.com/mp/collect?measurement_id=' . rawurlencode($ga4_id) . '&api_secret=' . rawurlencode($ga4_secret);
            $ga4_payload = array(
                'client_id' => (string) (isset($_COOKIE['_ga']) ? $_COOKIE['_ga'] : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')) . '.' . (string) time(),
                'events' => array(array(
                    'name' => 'phone_click',
                    'params' => array(
                        'event_category' => 'Phone',
                        'event_label'    => $phone_normalized,
                        'value'          => 1,
                        'origin'         => $is_callbar ? ($origin ?: 'callbar') : ($origin ?: 'web'),
                    ),
                )),
            );
            wp_remote_post($ga4_url, array(
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => wp_json_encode($ga4_payload),
                'timeout' => 0.5,
            ));
        }

        // Log successful tracking for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("LeadStream: Phone click recorded - {$original_phone} -> {$phone_normalized} on {$page_url} (origin={$origin}|etype={$element_type})");
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
