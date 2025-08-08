<?php
/**
 * LeadStream UTM Handler
 * Handles AJAX requests for UTM parameter generation
 */

namespace LS\AJAX;

defined('ABSPATH') || exit;

class UTMHandler {
    
    public static function init() {
        add_action('wp_ajax_generate_utm', [__CLASS__, 'handle']);
        add_action('wp_ajax_clear_utm_history', [__CLASS__, 'clear_history']);
    }

    public static function handle() {
        // Security check
        if (!check_ajax_referer('leadstream_utm_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid security token', 400);
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        // Define allowed UTM fields with defaults
        $fields = [
            'base_url'      => home_url(),
            'utm_source'    => '',
            'utm_medium'    => '',
            'utm_campaign'  => '',
            'utm_term'      => '',
            'utm_content'   => '',
            'utm_button'    => '',  
            // Track specific button/CTA clicks
        ];

        // Sanitize and collect input data
        $utm_params = [];
        foreach ($fields as $key => $default) {
            if ($key === 'base_url') {
                // Special handling for base URL
                $value = isset($_POST[$key]) && !empty($_POST[$key]) 
                    ? sanitize_url($_POST[$key]) 
                    : $default;
                $fields[$key] = $value;
            } else {
                // UTM parameters
                if (isset($_POST[$key]) && $_POST[$key] !== '') {
                    $utm_params[$key] = sanitize_text_field($_POST[$key]);
                }
            }
        }

        // Validate base URL with multiple checks
        $base_url = $fields['base_url'];
        
        // Check if URL is valid format
        if (!filter_var($base_url, FILTER_VALIDATE_URL)) {
            wp_send_json_error('Invalid base URL format. Please include http:// or https://');
        }
        
        // Additional URL validation
        $parsed_url = parse_url($base_url);
        if (!$parsed_url || !isset($parsed_url['scheme']) || !isset($parsed_url['host'])) {
            wp_send_json_error('Invalid URL structure. Please provide a complete URL.');
        }
        
        // Ensure scheme is http or https
        if (!in_array($parsed_url['scheme'], ['http', 'https'])) {
            wp_send_json_error('URL must use http:// or https:// protocol');
        }
        
        // Validate UTM parameter format (no special characters that break URLs)
        foreach ($utm_params as $key => $value) {
            if (preg_match('/[<>"\']/', $value)) {
                wp_send_json_error("Invalid character in {$key}. Avoid quotes and HTML characters.");
            }
        }

        // Ensure at least one UTM parameter is provided
        if (empty($utm_params)) {
            wp_send_json_error('At least one UTM parameter is required');
        }

        // Build the query string
        $query_string = http_build_query($utm_params);
        
        // Construct final UTM URL
        $separator = parse_url($fields['base_url'], PHP_URL_QUERY) ? '&' : '?';
        $utm_url = $fields['base_url'] . $separator . $query_string;

        // Validate final URL
        $utm_url = esc_url_raw($utm_url);
        
        if (!$utm_url) {
            wp_send_json_error('Failed to generate valid UTM URL');
        }

        // Store in history (persistent user meta instead of transient)
        $user_id = get_current_user_id();
        $history = get_user_meta($user_id, 'ls_utm_history', true);
        if (!is_array($history)) {
            $history = [];
        }
        
        array_unshift($history, [
            'url' => $utm_url,
            'time' => time(),
            'source' => $utm_params['utm_source'] ?? '',
            'medium' => $utm_params['utm_medium'] ?? '',
            'campaign' => $utm_params['utm_campaign'] ?? '',
            'term' => $utm_params['utm_term'] ?? '',
            'content' => $utm_params['utm_content'] ?? '',
            'button' => $utm_params['utm_button'] ?? '',
        ]);
        
        // Keep only the latest 15 (configurable)
        $max_entries = apply_filters('leadstream_utm_history_max', 15);
        if (count($history) > $max_entries) {
            $history = array_slice($history, 0, $max_entries);
        }
        update_user_meta($user_id, 'ls_utm_history', $history);

        // Return success with the generated UTM URL
        wp_send_json_success($utm_url);
    }

    /**
     * Clear UTM history
     */
    public static function clear_history() {
        // Security check
        if (!check_ajax_referer('leadstream_utm_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid security token', 400);
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        // Clear the user meta (persistent storage)
        $user_id = get_current_user_id();
        delete_user_meta($user_id, 'ls_utm_history');

        wp_send_json_success('History cleared successfully');
    }
}
