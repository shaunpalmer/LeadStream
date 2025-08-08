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
            'clicks'      => 'Clicks',
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

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Get links with click counts
        $query = "
            SELECT l.*, COUNT(c.id) as clicks, MAX(c.clicked_at) as last_click
            FROM {$wpdb->prefix}ls_links l
            LEFT JOIN {$wpdb->prefix}ls_clicks c ON l.id = c.link_id
            GROUP BY l.id
            ORDER BY l.created_at DESC
            LIMIT %d OFFSET %d
        ";

        $this->items = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset));

        // Set pagination
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ls_links");
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page
        ]);

        $this->_column_headers = [$this->get_columns(), [], []];
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
            
            case 'clicks':
                return intval($item->clicks);
            
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
