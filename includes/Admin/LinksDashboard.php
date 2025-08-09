<?php
namespace LS\Admin;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

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
    } // are we sure this price is in the right place

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

        $where = ['1=1'];
        $params = [];
        if ($q) {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where[] = '(l.slug LIKE %s OR l.target_url LIKE %s)';
            $params[] = $like; $params[] = $like;
        }
        if (in_array($rt, ['301','302','307','308'], true)) {
            $where[] = 'l.redirect_type = %s';
            $params[] = $rt;
        }
        if ($from) { $where[] = 'l.created_at >= %s'; $params[] = $from . ' 00:00:00'; }
        if ($to)   { $where[] = 'l.created_at <= %s'; $params[] = $to   . ' 23:59:59'; }
        $where_sql = implode(' AND ', $where);

        // Get links with click counts
        $query = "
            SELECT l.*, COUNT(c.id) as clicks, MAX(c.clicked_at) as last_click
            FROM {$wpdb->prefix}ls_links l
            LEFT JOIN {$wpdb->prefix}ls_clicks c ON l.id = c.link_id AND c.link_type = 'link'
            WHERE {$where_sql}
            GROUP BY l.id
            ORDER BY l.created_at DESC
            LIMIT %d OFFSET %d
        ";

        $query_params = array_merge($params, [$per_page, $offset]);
        if (!empty($query_params)) {
            $this->items = $wpdb->get_results($wpdb->prepare($query, $query_params));
        } else {
            // Fallback (should not happen since LIMIT/OFFSET exist) but safe-guard anyway
            $this->items = $wpdb->get_results(str_replace(['%d','%d'], [$per_page, $offset], $query));
        }

        // Get sparkline data for each link (last 7 days)
        foreach ($this->items as &$item) {
            $item->sparkline_data = $this->get_sparkline_data($item->id);
        }

        // Set pagination
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}ls_links l WHERE {$where_sql}";
        if (!empty($params)) {
            $total_items = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
        } else {
            $total_items = (int) $wpdb->get_var($count_sql);
        }
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
                $short_url = home_url("/l/{$item->slug}");
                return sprintf(
                    '<strong>%s</strong><br><small>%s</small>',
                    esc_html($item->slug),
                    esc_html($short_url)
                );
            
            case 'target_url':
                return sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    esc_url($item->target_url),
                    esc_html(wp_trim_words($item->target_url, 8))
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
                return $item->last_click ? 
                    date_i18n('M j, Y g:i A', strtotime($item->last_click)) : 
                    '—';
            
            case 'created_at':
                return date_i18n('M j, Y', strtotime($item->created_at));
            
            case 'actions':
                $short_url = home_url("/l/{$item->slug}");
                $edit_url = admin_url("admin.php?page=leadstream-analytics-injector&tab=links&action=edit&id={$item->id}");
                return sprintf(
                    '<a href="%s" class="button button-small">Edit</a> 
                     <a href="#" class="button button-small copy-link-btn" data-url="%s">Copy</a>
                     <a href="#" class="button button-small test-link-btn" data-url="%s">Test</a>',
                    esc_url($edit_url),
                    esc_attr($short_url),
                    esc_attr($short_url)
                );
            
            default:
                return '';
        }
    }

    /**
     * Render filters above/below the table
     */
    protected function extra_tablenav($which) {
        if ($which !== 'top') { return; }
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
        echo '<form method="get" action="' . esc_url($base_url) . '" style="display:flex; gap:8px; flex-wrap:wrap; align-items:end;">';
        echo '<input type="hidden" name="page" value="' . esc_attr($page) . '" />';
        echo '<input type="hidden" name="tab" value="' . esc_attr($tab) . '" />';
        echo '<label>From<br><input type="date" name="from" value="' . esc_attr($from) . '" /></label>';
        echo '<label>To<br><input type="date" name="to" value="' . esc_attr($to) . '" /></label>';
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

        $where = ['1=1']; $params = [];
        if ($q) {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where[] = '(l.slug LIKE %s OR l.target_url LIKE %s)';
            $params[] = $like; $params[] = $like;
        }
        if (in_array($rt, ['301','302','307','308'], true)) { $where[] = 'l.redirect_type = %s'; $params[] = $rt; }
        if ($from) { $where[] = 'l.created_at >= %s'; $params[] = $from . ' 00:00:00'; }
        if ($to)   { $where[] = 'l.created_at <= %s'; $params[] = $to   . ' 23:59:59'; }
        $where_sql = implode(' AND ', $where);

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

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=leadstream-links-directory.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys(reset($rows) ?: ['slug','target_url','redirect_type','created_at','total_clicks','last_click']));
        foreach ($rows as $r) { fputcsv($out, $r); }
        fclose($out);
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
                <svg width="%d" height="%d">
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
