<?php
namespace LS\Admin;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
use LS\Repository\LinksRepository;
use LS\Export\CsvExporter;
use LS\Export\JsonExporter;

/**
 * Pretty Links Dashboard using WP_List_Table
 * Implements the Observer pattern for real-time updates
 */
class LinksDashboard extends \WP_List_Table {
    
    /**
     * Initialize hooks (only for form processing, not menu)
     */
    public static function init() {
        // Only handle form submissions - no separate menu needed
        add_action('admin_post_add_pretty_link', [__CLASS__, 'handle_add_pretty_link']);
        add_action('admin_post_edit_pretty_link', [__CLASS__, 'handle_edit_pretty_link']);
        add_action('admin_post_ls_export_links', [__CLASS__, 'handle_export_links']);
    // AJAX fragment for list table (Pretty Links)
    add_action('wp_ajax_ls_links_table', [__CLASS__, 'ajax_links_table']);
    }

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'link',
            'plural'   => 'links',
            'ajax'     => false
        ]);
    }

    /**
     * Define columns
     */
    public function get_columns() {
        return [
            'cb'          => '<input type="checkbox" />',
            'slug'        => 'Slug',
            'target_url'  => 'Target URL',
            'redirect_type' => 'Redirect',
            'clicks'      => 'Clicks',
            'trend'       => 'Trend (7d)',
            'last_click'  => 'Last Click',
            'created_at'  => 'Created',
            'actions'     => 'Actions'
        ];
    }

    /**
     * Prepare items for display
     */
    public function prepare_items() {
        global $wpdb;

        // Filters
        $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $rt = isset($_GET['rt']) ? sanitize_text_field($_GET['rt']) : '';
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
        $to   = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : '';
        $per_page = isset($_GET['pp']) ? max(10, min(200, intval($_GET['pp']))) : 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

    [$where_sql, $params] = \LS\Repository\LinksRepository::build_filters(compact('q','rt','from','to'));

        // Get links with click counts
    $this->items = \LS\Repository\LinksRepository::fetch_with_counts($where_sql, $params, $per_page, $offset);

        // Get sparkline data for each link (last 7 days)
        foreach ($this->items as &$item) {
            $item->sparkline_data = $this->get_sparkline_data($item->id);
        }

        // Set pagination
    $total_items = \LS\Repository\LinksRepository::count($where_sql, $params);
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        $this->_column_headers = [$this->get_columns(), [], []];
    }

    /**
     * Get sparkline data for a link (last 7 days)
     */
    private function get_sparkline_data($link_id) {
        global $wpdb;
        
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $clicks = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$wpdb->prefix}ls_clicks 
                WHERE link_id = %d AND link_type = 'link' AND DATE(clicked_at) = %s
            ", $link_id, $date));
            $data[] = intval($clicks);
        }
        
        return $data;
    }

    /**
     * Default column display
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'slug':
                $pretty_url = home_url("/l/{$item->slug}");
                // Deterministic auto shortener: /s/{base62(id)}
                $code = \LS\Utils::base62_encode($item->id);
                $shortener_url = home_url("/s/{$code}");
                return sprintf(
                    '<strong>%s</strong><br>' .
                    '<small>%s</small> <button type="button" class="button button-small ls-copy-btn" data-copy="%s" aria-label="Copy pretty link">Copy</button><br>' .
                    '<small title="Auto-generated shortener">%s</small> ' .
                    '<button type="button" class="button button-small ls-copy-btn" data-copy="%s" aria-label="Copy shortener">Copy</button> ' .
                    '<button type="button" class="button button-small ls-qr-btn" data-url="%s" data-filename="%s.png" aria-label="Show QR code" title="Show QR" style="padding:2px 4px; line-height:0;">'
                    .'<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
                    .'<rect x="2" y="2" width="6" height="6" fill="#1d2327"/><rect x="12" y="2" width="6" height="6" fill="#1d2327"/>'
                    .'<rect x="2" y="12" width="6" height="6" fill="#1d2327"/>'
                    .'<rect x="10" y="10" width="2" height="2" fill="#1d2327"/><rect x="14" y="10" width="4" height="2" fill="#1d2327"/>'
                    .'<rect x="10" y="14" width="2" height="4" fill="#1d2327"/><rect x="14" y="14" width="2" height="2" fill="#1d2327"/><rect x="16" y="16" width="2" height="2" fill="#1d2327"/>'
                    .'</svg>'
                    .'</button>',
                    esc_html($item->slug),
                    esc_html($pretty_url), esc_attr($pretty_url),
                    esc_html($shortener_url), esc_attr($shortener_url),
                    esc_attr($shortener_url), esc_attr($item->slug)
                );
            
            case 'target_url':
                $trimmed = esc_html(wp_trim_words($item->target_url, 8));
                $copy = esc_attr($item->target_url);
                return sprintf(
                    '<a href="%s" target="_blank">%s</a> <button type="button" class="button button-small ls-copy-btn" data-copy="%s" aria-label="Copy target URL">Copy</button>',
                    esc_url($item->target_url),
                    $trimmed,
                    $copy
                );

            case 'redirect_type':
                $rt = isset($item->redirect_type) && in_array($item->redirect_type, ['301','302','307','308'], true)
                    ? $item->redirect_type
                    : '301';
                return sprintf('<code>%s</code>', esc_html($rt));
            
            case 'clicks':
                return intval($item->clicks);
            
            case 'trend':
                return $this->render_sparkline($item->sparkline_data);
            
            case 'last_click':
                return $item->last_click ? date_i18n('M j, Y g:i A', strtotime($item->last_click)) : '—';
        }
    }

    public function no_items() {
        $add_url = admin_url('admin.php?page=leadstream-analytics-injector&tab=links&action=add');
        echo '<div class="ls-empty">No pretty links yet. Create your first one.';
        echo ' <a class="button button-primary" href="' . esc_url($add_url) . '">Add New</a></div>';
    }

    /**
     * Filters and actions above/below table
     */
    protected function extra_tablenav($which) {
        $base_url = admin_url('admin.php');
        $page = 'leadstream-analytics-injector';
        $tab = 'links';
        $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $rt = isset($_GET['rt']) ? sanitize_text_field($_GET['rt']) : '';
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
        $to   = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : '';
        $pp   = isset($_GET['pp']) ? max(10, min(200, intval($_GET['pp']))) : 20;
        $export_url = wp_nonce_url(
            add_query_arg([
                'action' => 'ls_export_links',
                'q' => $q, 'rt' => $rt, 'from' => $from, 'to' => $to
            ], admin_url('admin-post.php')),
            'ls_export_links'
        );

    echo '<div class="alignleft actions" style="display:flex; gap:8px; flex-wrap:wrap; align-items:end;">';
    echo '<form id="ls-links-filters" class="js-links-filters" method="get" action="' . esc_url($base_url) . '" style="display:flex; gap:8px; flex-wrap:wrap; align-items:end;">';
        echo '<input type="hidden" name="page" value="' . esc_attr($page) . '" />';
        echo '<input type="hidden" name="tab" value="' . esc_attr($tab) . '" />';
    echo '<label>From<br><input type="date" name="from" value="' . esc_attr($from) . '" placeholder="dd/mm/yyyy" /></label>';
    echo '<label>To<br><input type="date" name="to" value="' . esc_attr($to) . '" placeholder="dd/mm/yyyy" /></label>';
        echo '<label>Redirect<br><select name="rt">';
        $rts = ['', '301','302','307','308']; $labels = ['All','301','302','307','308'];
        foreach ($rts as $i => $val) {
            printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($rt, $val, false), esc_html($labels[$i]));
        }
        echo '</select></label>';
        echo '<label>Search<br><input type="text" name="q" value="' . esc_attr($q) . '" placeholder="slug or target URL" /></label>';
        echo '<label>Per page<br><input type="number" name="pp" min="10" max="200" value="' . esc_attr($pp) . '" /></label>';
        echo '<button class="button button-primary" type="submit">Filter</button>';
        $reset_url = add_query_arg(['page'=>$page,'tab'=>$tab], $base_url);
        echo '<a class="button" href="' . esc_url($reset_url) . '">Reset</a>';
        echo '</form>';
        echo '<a class="button button-secondary" href="' . esc_url($export_url) . '">Export CSV</a>';
        if ($which === 'top') {
            echo '<span style="margin-left:8px; color:#646970;">Use filters and click Export to download the current view.</span>';
        } else {
            $total_items = $this->_pagination_args['total_items'] ?? 0;
            $per_page = $this->_pagination_args['per_page'] ?? 20;
            $total_pages = max(1, (int) ceil($total_items / $per_page));
            $current = (int) $this->get_pagenum();
            echo '<span style="margin-left:8px; color:#646970;">Page ' . intval($current) . ' of ' . intval($total_pages) . '</span>';
        }
        echo '</div>';
    }

    /**
     * Handle CSV export via admin-post.php
     */
    public static function handle_export_links() {
        if (!current_user_can('manage_options')) { wp_die('Permission denied'); }
        check_admin_referer('ls_export_links');

        global $wpdb;
        $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $rt = isset($_GET['rt']) ? sanitize_text_field($_GET['rt']) : '';
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
        $to   = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : '';

    [$where_sql, $params] = \LS\Repository\LinksRepository::build_filters(compact('q','rt','from','to'));

        $sql = "SELECT l.slug, l.target_url, l.redirect_type, l.created_at,
                        (SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks c2 WHERE c2.link_id=l.id AND c2.link_type='link') AS total_clicks,
                        (SELECT MAX(clicked_at) FROM {$wpdb->prefix}ls_clicks c3 WHERE c3.link_id=l.id AND c3.link_type='link') AS last_click
                FROM {$wpdb->prefix}ls_links l
                WHERE {$where_sql}
                ORDER BY l.created_at DESC
                LIMIT %d";
        $exp_params = array_merge($params, [10000]);
        if (!empty($exp_params)) {
            $rows = $wpdb->get_results($wpdb->prepare($sql, $exp_params), ARRAY_A);
        } else {
            $rows = $wpdb->get_results(str_replace('%d', '10000', $sql), ARRAY_A);
        }

    $format = isset($_GET['format']) && strtolower($_GET['format']) === 'json' ? 'json' : 'csv';
    $exporter = $format === 'json' ? new \LS\Export\JsonExporter('leadstream-links-directory.json') : new \LS\Export\CsvExporter('leadstream-links-directory.csv');
    nocache_headers();
    header('Content-Type: ' . $exporter->contentType());
    header('Content-Disposition: attachment; filename=' . $exporter->filename());
    $rows = array_values($rows ?: []);
    $exporter->output($rows);
        exit;
    }

    /**
     * Render a mini sparkline chart
     */
    private function render_sparkline($data) {
        if (empty($data) || array_sum($data) == 0) {
            return '<span style="color: #646970; font-size: 11px;">No activity</span>';
        }
        
        $max = max($data);
        $svg_height = 20;
        $svg_width = 80;
        $points = [];
        
        foreach ($data as $i => $value) {
            $x = ($i / (count($data) - 1)) * $svg_width;
            $y = $svg_height - (($value / $max) * $svg_height);
            $points[] = "$x,$y";
        }
        
        $path = 'M' . implode(' L', $points);
        $total_clicks = array_sum($data);
        
        // Calculate trend direction
        $first_half = array_sum(array_slice($data, 0, 3));
        $second_half = array_sum(array_slice($data, -3));
        $trend_icon = '';
        $trend_class = '';
        
        if ($second_half > $first_half) {
            $trend_icon = '📈';
            $trend_class = 'trend-up';
        } elseif ($second_half < $first_half) {
            $trend_icon = '📉';
            $trend_class = 'trend-down';
        } else {
            $trend_icon = '➡️';
            $trend_class = 'trend-flat';
        }
        
        return sprintf(
            '<div class="ls-sparkline-container">
                <svg width="%d" height="%d" aria-label="7-day trend sparkline">
                    <polyline points="%s" fill="none" stroke="#0073aa" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <div class="ls-sparkline-trend ls-trend-indicator %s">
                    %s <span>%d</span>
                </div>
            </div>',
            $svg_width,
            $svg_height,
            implode(' ', $points),
            $trend_class,
            $trend_icon,
            $total_clicks
        );
    }

    /**
     * AJAX endpoint: return list table HTML fragment based on args
     */
    public static function ajax_links_table() {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message' => 'forbidden'], 403); }
        check_ajax_referer('ls-admin','nonce');

        // Map args
        $args = [];
        $keys = ['q','rt','from','to','pp','paged'];
        foreach ($keys as $k) {
            if (isset($_REQUEST[$k])) { $args[$k] = sanitize_text_field(wp_unslash($_REQUEST[$k])); }
        }
        // Apply to $_GET so WP_List_Table pagination works
        foreach ($args as $k => $v) { $_GET[$k] = $v; }

        // Render table into buffer
        $table = new self();
        $table->prepare_items();
        ob_start();
        $table->display();
        $html = ob_get_clean();

        // Build URL reflecting current args for history
        $url = add_query_arg(array_merge([
            'page' => 'leadstream-analytics-injector',
            'tab' => 'links'
        ], $args), admin_url('admin.php'));

        wp_send_json_success(['html' => $html, 'url' => $url]);
    }

    /**
     * Checkbox column
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="links[]" value="%s" />', $item->id);
    }

    /**
     * Handle adding new pretty link
     */
    public static function handle_add_pretty_link() {
        if (!wp_verify_nonce($_POST['add_pretty_link_nonce'], 'add_pretty_link')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        $slug = sanitize_text_field($_POST['slug']);
        $target_url = esc_url_raw($_POST['target_url']);

        if (empty($slug) || empty($target_url)) {
            wp_redirect(admin_url('admin.php?page=leadstream-analytics-injector&tab=links&action=add&error=missing_fields'));
            exit;
        }

        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'ls_links',
            [
                'slug' => $slug,
                'target_url' => $target_url,
            ],
            ['%s', '%s']
        );

        if ($result === false) {
            wp_redirect(admin_url('admin.php?page=leadstream-analytics-injector&tab=links&action=add&error=duplicate_slug'));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=leadstream-analytics-injector&tab=links&message=added'));
        exit;
    }

    /**
     * Handle editing pretty link
     */
    public static function handle_edit_pretty_link() {
        if (!wp_verify_nonce($_POST['edit_pretty_link_nonce'], 'edit_pretty_link')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        $id = intval($_POST['id']);
        $slug = sanitize_text_field($_POST['slug']);
        $target_url = esc_url_raw($_POST['target_url']);

        if (!$id || empty($slug) || empty($target_url)) {
            wp_redirect(admin_url('admin.php?page=leadstream-analytics-injector&tab=links&error=missing_fields'));
            exit;
        }

        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'ls_links',
            [
                'slug' => $slug,
                'target_url' => $target_url,
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        wp_redirect(admin_url('admin.php?page=leadstream-analytics-injector&tab=links&message=updated'));
        exit;
    }
}
