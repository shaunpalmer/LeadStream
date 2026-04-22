<?php
namespace LS\Events;

defined('ABSPATH') || exit;

/**
 * LS_Events
 * Handles AJAX event logging from client-side listeners
 */
class LS_Events {
    public static function init() {
        add_action('wp_ajax_leadstream_log_event', [__CLASS__, 'handle_log_event']);
        add_action('wp_ajax_nopriv_leadstream_log_event', [__CLASS__, 'handle_log_event']);
    }

    public static function handle_log_event() {
        // Minimal security: accept nonce if present but don't require for public events
        $nonce = $_POST['nonce'] ?? '';
        if (!empty($nonce) && !wp_verify_nonce($nonce, 'leadstream_event')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        $payload = $_POST['payload'] ?? null;
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        if (empty($payload) || !is_array($payload)) {
            wp_send_json_error('Invalid payload');
            return;
        }

        global $wpdb;

        $now = current_time('mysql');


        $event_type = sanitize_text_field($payload['type'] ?? ($payload['event'] ?? ($payload['name'] ?? 'unknown')));

        $data = array(
            'event_name' => sanitize_text_field($payload['name'] ?? ($payload['event'] ?? 'unknown')),
            'event_type' => $event_type,
            'event_params' => wp_json_encode($payload['params'] ?? $payload),
            'user_id' => get_current_user_id() ?: null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at' => $now,
        );

        $format = ['%s','%s','%s','%d','%s','%s'];

        $table = $wpdb->prefix . 'ls_events';

        // Ensure table exists (best-effort: if not, attempt to create simple table)
        try {
            $wpdb->query("CREATE TABLE IF NOT EXISTS {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event_name varchar(191) NOT NULL,
                event_type varchar(191) NOT NULL,
                event_params longtext NOT NULL,
                user_id bigint(20) unsigned DEFAULT NULL,
                ip_address varchar(45) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY event_type_idx (event_type),
                KEY created_at_idx (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {
            // ignore failures - logging should not break page
        }

        $inserted = $wpdb->insert($table, $data, $format);

        if ($inserted === false) {
            wp_send_json_error('db_error');
            return;
        }

        wp_send_json_success(['ok' => true, 'id' => $wpdb->insert_id]);
    }
}
