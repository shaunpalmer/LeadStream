<?php
namespace LS\REST;

defined('ABSPATH') || exit;

class CallsWebhook {
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('leadstream/v1', '/calls', [
            'methods'  => ['POST'],
            'permission_callback' => '__return_true', // Providers post publicly; authenticate at network edge if needed
            'callback' => [__CLASS__, 'handle_webhook']
        ]);
    }

    public static function handle_webhook(\WP_REST_Request $req) {
        global $wpdb;
        $table = $wpdb->prefix . 'ls_calls';

        // Accept JSON or form data
        $b = $req->get_json_params();
        if (!is_array($b) || empty($b)) { $b = $req->get_params(); }

        // Sanitize
        $provider  = isset($b['provider']) ? sanitize_text_field($b['provider']) : '';
        $sid       = isset($b['provider_call_id']) ? sanitize_text_field($b['provider_call_id']) : '';
        $from      = isset($b['from']) ? sanitize_text_field($b['from']) : '';
        $to        = isset($b['to']) ? sanitize_text_field($b['to']) : '';
        $status    = isset($b['status']) ? sanitize_text_field($b['status']) : '';
        $start     = isset($b['start_time']) ? sanitize_text_field($b['start_time']) : '';
        $end       = isset($b['end_time']) ? sanitize_text_field($b['end_time']) : '';
        $duration  = isset($b['duration']) ? intval($b['duration']) : 0;
        $recording = isset($b['recording_url']) ? esc_url_raw($b['recording_url']) : '';

        // Optional correlation keys
        $click_id  = isset($b['click_id']) ? intval($b['click_id']) : null;
        $meta      = isset($b['meta']) ? wp_json_encode($b['meta']) : '';

        // Normalize ISO8601 -> mysql datetime if provided
        $to_mysql = function($v) {
            if (empty($v)) return null;
            $t = strtotime($v);
            return $t ? gmdate('Y-m-d H:i:s', $t) : null;
        };

        $data = [
            'provider' => $provider,
            'provider_call_id' => $sid,
            'from_number' => $from,
            'to_number' => $to,
            'status' => $status,
            'start_time' => $to_mysql($start),
            'end_time' => $to_mysql($end),
            'duration' => $duration,
            'recording_url' => $recording,
            'click_id' => $click_id,
            'meta_data' => $meta,
            'created_at' => current_time('mysql'),
        ];

        // Upsert by provider_call_id if provided
        if (!empty($sid)) {
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE provider_call_id = %s", $sid));
            if ($existing) {
                $wpdb->update($table, $data, ['id' => $existing]);
                return new \WP_REST_Response(['ok' => true, 'updated' => (int)$existing], 200);
            }
        }

        $wpdb->insert($table, $data);
        $id = $wpdb->insert_id;
        return new \WP_REST_Response(['ok' => true, 'id' => (int)$id], 201);
    }
}
