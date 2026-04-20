<?php
namespace LS\AJAX;

defined('ABSPATH') || exit;

/**
 * Dashboard data AJAX handler
 *
 * TODO [BOOT-003]: This class is NEVER loaded or initialized.
 * It is not listed in Bootstrap::$admin_components and there is no
 * explicit require_once for it anywhere in the codebase. As a result,
 * init() is never called, the wp_ajax_leadstream_dashboard_data action is
 * never registered, and any JavaScript that calls this AJAX endpoint will
 * receive WordPress's default "0" (no handler) response.
 *
 * Fix: add 'AJAX/DashboardHandler' to Bootstrap::$admin_components and add
 * a corresponding class_exists + ::init() call in initialize_components().
 *
 * Note: handle_dashboard_data() calls $ds->series_events(7) — verify that
 * Dashboard\Data::series_events() exists before wiring this up.
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
