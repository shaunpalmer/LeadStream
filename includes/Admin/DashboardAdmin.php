<?php
namespace LS\Admin;

defined('ABSPATH') || exit;

class DashboardAdmin {
    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'localize_rest_for_dashboard']);
    }

    public static function register_routes(): void {
        register_rest_route('leadstream/v1', '/metrics', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'route_metrics'],
            'permission_callback' => function() { return current_user_can('manage_options'); }
        ]);
    }

    public static function route_metrics(
        \WP_REST_Request $req
    ) {
        try {
            if (!class_exists('\LeadStream\Admin\Dashboard\Data')) {
                return new \WP_REST_Response(['error' => 'data_unavailable'], 500);
            }
            $ds = new \LeadStream\Admin\Dashboard\Data();
            $kpis = $ds->kpis();
            $range = [ 'start' => date('Y-m-d', strtotime('-7 days')), 'end' => date('Y-m-d') ];
            $trend = $ds->get_timeseries($range, 'calls');
            return new \WP_REST_Response([ 'kpis' => $kpis, 'trend' => $trend ], 200);
        } catch (\Throwable $e) {
            return new \WP_REST_Response(['error' => 'exception', 'message' => $e->getMessage()], 500);
        }
    }

    public static function localize_rest_for_dashboard($hook) {
        // Only on our settings page dashboard tab
        if ($hook !== 'toplevel_page_leadstream-analytics-injector') {
            return;
        }
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
        if ($current_tab !== 'dashboard') { return; }

        if (wp_script_is('leadstream-dashboard', 'enqueued') || wp_script_is('leadstream-dashboard', 'registered')) {
            wp_localize_script('leadstream-dashboard', 'LS_DASH', [
                'rest_base' => rest_url('leadstream/v1/metrics'),
                'rest_nonce' => wp_create_nonce('wp_rest')
            ]);
        }
    }
}


