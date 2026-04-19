<?php
namespace LeadStream\Admin\Dashboard;

use LeadStream\Admin\Dashboard\Data;
use LeadStream\Admin\Dashboard\Status;

class Render {
    private Data $data;
    private Status $status;

    public function __construct(Data $data, Status $status) {
        $this->data = $data;
        $this->status = $status;
    }

    public function output(): void {
        $k = $this->data->kpis();
        $s = $this->status->flags();
        
        // Get trend data for chart
        $from = sanitize_text_field($_GET['from'] ?? '');
        $to = sanitize_text_field($_GET['to'] ?? '');
        $metric = sanitize_text_field($_GET['metric'] ?? 'calls');
        
        // Default to last 14 days if no range specified
        if (empty($from) || empty($to)) {
            $end = new \DateTimeImmutable('today', wp_timezone());
            $start = $end->modify('-13 days'); // 14 days total
            $from = $start->format('Y-m-d');
            $to = $end->format('Y-m-d');
        }
        
        $range = ['start' => $from, 'end' => $to];
        $trend = $this->data->get_timeseries($range, $metric);
        
    // Structured dashboard output matching frontend JS selectors
    echo '<div class="ls-dash">';

    // Filters / controls
    echo '<div class="ls-filters">';
    echo '<div style="display:flex;gap:8px;align-items:center">';
    echo '<label for="ls-from">From</label><input id="ls-from" type="date" />';
    echo '<label for="ls-to">To</label><input id="ls-to" type="date" />';
    echo '<label for="ls-metric">Metric</label><select id="ls-metric"><option value="calls">Calls</option><option value="forms">Forms</option><option value="leads">Total Leads</option></select>'; 
    echo '<button class="button button-primary" id="ls-apply">Apply</button>';
    echo '</div>';
    echo '</div>';

    // KPI tiles (top-level unified view)
    $tiles = [];
    // Phone sensor: calls today / week / month
    $tiles[] = [ 'id' => 'LS-DASH-P-001', 'label' => 'Calls Today', 'value' => $k['calls_today'] ?? 0 ];
    $tiles[] = [ 'id' => 'LS-DASH-P-002', 'label' => 'Calls This Week', 'value' => $k['calls_week'] ?? 0 ];
    $tiles[] = [ 'id' => 'LS-DASH-P-003', 'label' => 'Calls This Month', 'value' => $k['calls_month'] ?? 0 ];

    // Links sensor: from Data class
    $tiles[] = [ 'id' => 'LS-DASH-L-001', 'label' => 'Links Today', 'value' => $k['links_today'] ?? 0 ];
    $tiles[] = [ 'id' => 'LS-DASH-L-002', 'label' => 'Links This Week', 'value' => $k['links_week'] ?? 0 ];
    $tiles[] = [ 'id' => 'LS-DASH-L-003', 'label' => 'Links This Month', 'value' => $k['links_month'] ?? 0 ];

    echo '<div id="ls-tiles" class="ls-kpis">';
    foreach ($tiles as $t) {
        $this->tile($t['id'], $t['label'], $t['value']);
    }
    echo '</div>';

    $this->promo_card();

    // Detailed sections for drill-down
    echo '<div class="ls-details" style="margin-top:24px;">';
    
    // Phone tracking section
    echo '<div class="phone-section" style="margin:20px 0;padding:20px;border-left:4px solid #00a32a;background:#f0f8f0;border-radius:6px;">';
    echo '<h2 style="margin:0 0 12px 0;color:#2271b1;">📞 Phone Tracking Details</h2>';
    echo '<div class="stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;">';
    echo '<div class="stat-card" style="padding:15px;background:#f0f6fc;border-radius:6px;text-align:center;border-left:4px solid #2271b1;">';
    echo '<div class="stat-number" style="font-size:24px;font-weight:600;color:#1d2327;">' . ($k['calls_today']['value'] ?? 0) . '</div>';
    echo '<div class="stat-label" style="font-size:12px;color:#50575e;text-transform:uppercase;letter-spacing:0.5px;">Calls Today</div>';
    echo '</div>';
    echo '<div class="stat-card" style="padding:15px;background:#f0f6fc;border-radius:6px;text-align:center;border-left:4px solid #2271b1;">';
    echo '<div class="stat-number" style="font-size:24px;font-weight:600;color:#1d2327;">' . ($k['calls_week']['value'] ?? 0) . '</div>';
    echo '<div class="stat-label" style="font-size:12px;color:#50575e;text-transform:uppercase;letter-spacing:0.5px;">Calls This Week</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Links tracking section
    echo '<div class="link-section" style="margin:20px 0;padding:20px;border-left:4px solid #dba617;background:#fff8ee;border-radius:6px;">';
    echo '<h2 style="margin:0 0 12px 0;color:#2271b1;">🔗 Links Tracking Details</h2>';
    echo '<div class="stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;">';
    echo '<div class="stat-card" style="padding:15px;background:#f0f6fc;border-radius:6px;text-align:center;border-left:4px solid #dba617;">';
    echo '<div class="stat-number" style="font-size:24px;font-weight:600;color:#1d2327;">' . ($k['links_today']['value'] ?? 0) . '</div>';
    echo '<div class="stat-label" style="font-size:12px;color:#50575e;text-transform:uppercase;letter-spacing:0.5px;">Links Today</div>';
    echo '</div>';
    echo '<div class="stat-card" style="padding:15px;background:#f0f6fc;border-radius:6px;text-align:center;border-left:4px solid #dba617;">';
    echo '<div class="stat-number" style="font-size:24px;font-weight:600;color:#1d2327;">' . ($k['links_week']['value'] ?? 0) . '</div>';
    echo '<div class="stat-label" style="font-size:12px;color:#50575e;text-transform:uppercase;letter-spacing:0.5px;">Links This Week</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';

    // Status badges
    echo '<div id="ls-status" class="ls-status">';
    $this->badge('JavaScript Injection', $s['js']);
    $this->badge('Phone Tracking', $s['phone']);
    $this->badge('Pretty Links', $s['pretty']);
    echo '</div>';

    // Trend chart
    echo '<div class="ls-card" style="margin-top:16px;padding:12px;">';
    echo '<h3 style="margin:0 0 8px 0">14-day trend</h3>';
    echo '<div style="height:280px"><canvas id="ls-trend" aria-label="Trend chart"></canvas></div>';
    echo '</div>';

        // enqueue minimal CSS/JS if needed
        wp_enqueue_style('leadstream-dashboard', LS_PLUGIN_URL . 'assets/css/ls-dashboard.css', [], LS_VERSION);

        // Ensure Chart.js is present for the trend chart. This mirrors the
        // registration in Assets::enqueue_dashboard so Render::output() is
        // safe when used in alternative contexts (widgets, inline embeds).
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1',
            true
        );

        wp_enqueue_script('leadstream-dashboard', LS_PLUGIN_URL . 'assets/js/ls-dashboard.js', ['jquery', 'chartjs'], LS_VERSION, true);

        // Pass data to JavaScript
        $formatted_kpis = [];
        foreach ($k as $key => $kpi_data) {
            $formatted_kpis[] = [
                'id' => $key,
                'label' => $this->get_kpi_label($key),
                'value' => is_array($kpi_data) ? (int)($kpi_data['value'] ?? 0) : (int)$kpi_data,
                'delta_abs' => is_array($kpi_data) ? (int)($kpi_data['delta_abs'] ?? 0) : 0,
                'delta_pct' => is_array($kpi_data) ? (float)($kpi_data['delta_pct'] ?? 0) : 0,
                'state' => is_array($kpi_data) ? ($kpi_data['state'] ?? 'green') : 'green'
            ];
        }
        
        wp_localize_script('leadstream-dashboard', 'LeadStreamDashboard', [
            'kpis' => $formatted_kpis,
            'trend' => $trend,
            'status' => $s,
            'range' => [
                'start' => $from,
                'end' => $to
            ],
            'metric' => $metric
        ]);

        // Admin diagnostic: quick provenance panel to help debug why tiles might be zero.
        // Visible only to users who can manage options.
        if (current_user_can('manage_options')) {
            $this->diagnostic_panel();
            // UX hint: if forms installed but no submissions today
            $forms_today_val = is_array($k['forms_today'] ?? null) ? (int)($k['forms_today']['value'] ?? 0) : (int)($k['forms_today'] ?? 0);
            if ($forms_today_val === 0 && (defined('WPCF7_VERSION') || function_exists('wpforms') || function_exists('is_gf_entry') || function_exists('ninja_forms'))) {
                echo '<div style="margin-top:12px; padding:10px; border-left:4px solid #ffc107; background:#fff8e1;">';
                echo '<strong>Forms installed — no submissions recorded today yet.</strong> Submit a test form to light this up.';
                echo '</div>';
            }

            // Recent events panel (last 10)
            $this->recent_events_panel();
        }
    }

    private function get_kpi_label(string $key): string {
        $labels = [
            'calls_today' => 'Calls Today',
            'calls_week' => 'Calls This Week', 
            'calls_month' => 'Calls This Month',
            'forms_today' => 'Forms Today',
            'links_today' => 'Links Today',
            'links_week' => 'Links This Week',
            'links_month' => 'Links This Month'
        ];
        return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * $value may be an int or an array with ['value'=>int,'delta_abs'=>int,'delta_pct'=>float]
     */
    private function tile(string $id, string $label, $value): void {
        $val = 0; $delta_html = '';
        if (is_array($value)) {
            $val = (int) ($value['value'] ?? 0);
            $dabs = (int) ($value['delta_abs'] ?? 0);
            $dpct = (float) ($value['delta_pct'] ?? 0.0);
            if ($dabs !== 0) {
                $arrow = $dabs > 0 ? '▲' : '▼';
                $sign = $dabs > 0 ? '+' : '';
                $col = $dabs > 0 ? 'color:#10b981;' : 'color:#ef4444;';
                $delta_class = $dabs > 0 ? 'positive' : 'negative';
                $delta_html = sprintf('<div class="ls-tile-delta %s" style="font-size:12px;margin-top:6px;%s">%s %s%d (%s%%)</div>', 
                    $delta_class, $col, $arrow, $sign, $dabs, esc_html((string)$dpct));
            }
        } else {
            $val = (int) $value;
        }

        printf('<div id="%s" class="ls-tile" data-tile="%s"><div class="ls-tile-label">%s</div><div class="ls-tile-value">%d</div>%s</div>', 
            esc_attr($id), esc_attr($id), esc_html($label), $val, $delta_html);
    }

    private function badge(string $label, string $state): void {
    $class = 'off';
    $text = 'Off';
    if ($state === 'ok') { $class = 'state-green'; $text = 'On'; }
    elseif ($state === 'warn') { $class = 'state-orange'; $text = 'Needs Setup'; }
    else { $class = 'state-red'; }
    printf('<div class="ls-badge %s"><span class="dot"></span>%s — %s</div>', esc_attr($class), esc_html($label), esc_html($text));
    }

    private function promo_card(): void {
        $logo = trailingslashit(LS_PLUGIN_URL) . 'assets/Lead-stream-logo-Small.png';
        $href = apply_filters('leadstream_dashboard_promo_href', 'https://projectstudios.co.nz/product-leadstream/');
        $title = apply_filters('leadstream_dashboard_promo_title', 'LeadStream Pro');
        $headline = apply_filters('leadstream_dashboard_promo_headline', 'See what LeadStream Pro can unlock for your pipeline.');
        $summary = apply_filters('leadstream_dashboard_promo_summary', 'Explore the features and capabilities included in LeadStream Pro.');
        $detail = apply_filters('leadstream_dashboard_promo_detail', 'Review what is included, how it works, and whether it fits your tracking and reporting workflow.');
        $link_text = apply_filters('leadstream_dashboard_promo_link_text', 'View LeadStream Pro');

        echo '<section class="ls-promo-card" aria-label="LeadStream Pro product card">';
        echo '<div class="ls-promo-card__summary">';
        echo '<img class="ls-promo-card__logo" src="' . esc_url($logo) . '" alt="LeadStream logo" width="40" height="40" />';
        echo '<div>';
        echo '<h3 class="ls-promo-card__title"><a class="ls-promo-card__title-link" href="' . esc_url($href) . '" target="_blank" rel="noopener noreferrer">' . esc_html($title) . '</a></h3>';
        echo '<p class="ls-promo-card__headline">' . esc_html($headline) . '</p>';
        echo '</div>';
        echo '</div>';
        echo '<details class="ls-promo-card__details">';
        echo '<summary>' . esc_html($summary) . '</summary>';
        echo '<p>' . esc_html($detail) . '</p>';
        echo '<a class="button button-secondary" href="' . esc_url($href) . '" target="_blank" rel="noopener noreferrer">' . esc_html($link_text) . '</a>';
        echo '</details>';
        echo '</section>';
    }

    /**
     * Diagnostic panel: simplified and professional display
     */
    private function diagnostic_panel(): void {
        global $wpdb;
        $p = $wpdb->prefix;

        // NORMALIZED: Only 3 tables needed
        $key_tables = [
            'Click Events' => $p . 'ls_clicks',     // ALL events (phone, link, form)
            'Link Definitions' => $p . 'ls_links',   // Pretty Links definitions
            'Event Stream' => $p . 'ls_events'       // Optional GA4-style log
        ];

        echo '<div class="ls-diagnostic" style="margin-top:18px;padding:16px;background:#ffffff;border:1px solid #e1e5e9;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.04);">';
        echo '<h4 style="margin:0 0 12px 0;color:#374151;font-size:14px;font-weight:600;">System Status (3-Table Normalized)</h4>';
        
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">';
        
        foreach ($key_tables as $label => $table) {
            $exists = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($exists) {
                $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
                
                // Use null-safe date checking
                $date_col = ($table === $p . 'ls_clicks') ? 'clicked_at' : 'created_at';
                $today = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE DATE(COALESCE({$date_col}, '1970-01-01')) = %s", 
                    date('Y-m-d')
                ));
                
                $status_color = $count > 0 ? '#10b981' : '#6b7280';
                $status_text = $count > 0 ? 'Active' : 'Empty';
            } else {
                $count = 0; $today = 0;
                $status_color = '#ef4444';
                $status_text = 'Missing';
            }
            
            echo '<div style="padding:12px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;">';
            echo '<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">';
            echo '<div style="width:8px;height:8px;border-radius:50%;background:' . $status_color . ';"></div>';
            echo '<span style="font-weight:500;font-size:13px;color:#374151;">' . esc_html($label) . '</span>';
            echo '</div>';
            echo '<div style="font-size:18px;font-weight:700;color:#1f2937;">' . number_format($count) . '</div>';
            echo '<div style="font-size:11px;color:#6b7280;">' . $today . ' today</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Show event breakdown for ls_clicks if it exists
        $clicks_table = $p . 'ls_clicks';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $clicks_table))) {
            echo '<div style="margin-top:12px;padding:12px;background:#f8fafc;border-radius:6px;">';
            echo '<h5 style="margin:0 0 8px 0;font-size:12px;color:#6b7280;text-transform:uppercase;">Event Breakdown</h5>';
            
            $breakdown = $wpdb->get_results("
                SELECT COALESCE(link_type, 'unknown') as type, COUNT(*) as count 
                FROM {$clicks_table} 
                GROUP BY COALESCE(link_type, 'unknown')
            ", ARRAY_A);
            
            echo '<div style="display:flex;gap:16px;flex-wrap:wrap;">';
            foreach ($breakdown as $row) {
                echo '<div style="text-align:center;">';
                echo '<div style="font-weight:600;color:#1f2937;">' . number_format($row['count']) . '</div>';
                echo '<div style="font-size:11px;color:#6b7280;">' . esc_html($row['type']) . '</div>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /** Recent events read-only panel */
    private function recent_events_panel(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ls_events';
        if (! $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) return;

        $rows = $wpdb->get_results("SELECT id, event_type, action, label, page_url, created_at FROM {$table} ORDER BY created_at DESC LIMIT 10", ARRAY_A);

        echo '<div class="ls-recent-events" style="margin-top:14px;padding:12px;background:#fff;border:1px solid #e6e6e6">';
        echo '<h4 style="margin:0 0 8px 0">Recent Events — last 10</h4>';
        if (empty($rows)) {
            echo '<p style="color:#666;margin:0">No recent events found.</p>';
            echo '</div>';
            return;
        }

        echo '<table style="width:100%;border-collapse:collapse;font-size:13px">';
        echo '<thead><tr><th style="text-align:left;padding:6px">When</th><th style="text-align:left;padding:6px">Type</th><th style="text-align:left;padding:6px">Action</th><th style="text-align:left;padding:6px">Label / Page</th></tr></thead>';
        echo '<tbody>';
        foreach ($rows as $r) {
            printf('<tr><td style="padding:6px;white-space:nowrap">%s</td><td style="padding:6px">%s</td><td style="padding:6px">%s</td><td style="padding:6px">%s</td></tr>', esc_html($r['created_at']), esc_html($r['event_type']), esc_html($r['action']), esc_html($r['label'] . ' — ' . wp_parse_url($r['page_url'], PHP_URL_PATH)));
        }
        echo '</tbody></table>';
        echo '</div>';
    }
}
