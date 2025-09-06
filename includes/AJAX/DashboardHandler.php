<?php
namespace LS\AJAX;

defined('ABSPATH') || exit;

/**
 * Dashboard data AJAX handler
 */
class DashboardHandler {
    public static function init() {
        add_action('wp_ajax_leadstream_dashboard_data', [__CLASS__, 'handle_dashboard_data']);
        // admin-only endpoint (no nopriv)
    }

    public static function handle_dashboard_data() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('forbidden', 403);
            return;
        }

        $nonce = $_REQUEST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'leadstream_dashboard')) {
            wp_send_json_error('invalid_nonce', 400);
            return;
        }

        $from = sanitize_text_field($_REQUEST['from'] ?? '');
        $to = sanitize_text_field($_REQUEST['to'] ?? '');
        $metric = sanitize_text_field($_REQUEST['metric'] ?? 'calls');

        try {
            if (!class_exists('\LeadStream\Admin\Dashboard\Data')) {
                wp_send_json_error('no_data_class');
                return;
            }

            $ds = new \LeadStream\Admin\Dashboard\Data();

            $kpis = $ds->kpis();
            $series = $ds->series_events(7);

            wp_send_json_success([
                'kpis' => $kpis,
                'trend' => $series,
                'status' => [
                    'phone' => [ 'state' => (get_option('leadstream_phone_enabled', 1) ? 'green' : 'red'), 'msg' => get_option('leadstream_phone_enabled', 1) ? 'Enabled' : 'Disabled' ],
                ]
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error('exception', 500);
        }
    }
}
