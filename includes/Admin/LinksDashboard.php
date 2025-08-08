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
     * Initialize hooks and menu
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_post_add_link', [__CLASS__, 'handle_add_link']);
        add_action('admin_post_delete_link', [__CLASS__, 'handle_delete_link']);
        add_action('wp_ajax_test_link', [__CLASS__, 'ajax_test_link']);
    }

    /**
     * Add submenu page under LeadStream
     */
    public static function add_menu_page() {
        add_submenu_page(
            'leadstream-analytics-injector',
            'Pretty Links Manager',
            'Pretty Links',
            'manage_options',
            'leadstream-links',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Render the main page
     */
    public static function render_page() {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'add':
                self::render_add_form();
                break;
            case 'edit':
                self::render_edit_form();
                break;
            default:
                self::render_list_table();
                break;
        }
    }

    /**
     * Render the list table
     */
    private static function render_list_table() {
        $table = new self();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Pretty Links Manager</h1>
            <a href="<?php echo admin_url('admin.php?page=leadstream-links&action=add'); ?>" class="page-title-action">Add New</a>
            <hr class="wp-header-end">
            
            <?php $table->display(); ?>
        </div>
        <?php
    }

    /**
     * Render add new form
     */
    private static function render_add_form() {
        ?>
        <div class="wrap">
            <h1>Add New Pretty Link</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="add_link">
                <?php wp_nonce_field('add_link', 'add_link_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="slug">Slug</label></th>
                        <td>
                            <input type="text" id="slug" name="slug" class="regular-text" required>
                            <p class="description">The short identifier (e.g., "my-link" becomes /l/my-link)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="target_url">Target URL</label></th>
                        <td>
                            <input type="url" id="target_url" name="target_url" class="regular-text" required>
                            <p class="description">The full URL to redirect to</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Add Pretty Link'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render edit form
     */
    private static function render_edit_form() {
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            wp_die('Invalid link ID');
        }

        global $wpdb;
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ls_links WHERE id = %d",
            $id
        ));

        if (!$link) {
            wp_die('Link not found');
        }

        ?>
        <div class="wrap">
            <h1>Edit Pretty Link</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="edit_link">
                <input type="hidden" name="id" value="<?php echo $link->id; ?>">
                <?php wp_nonce_field('edit_link', 'edit_link_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="slug">Slug</label></th>
                        <td>
                            <input type="text" id="slug" name="slug" value="<?php echo esc_attr($link->slug); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="target_url">Target URL</label></th>
                        <td>
                            <input type="url" id="target_url" name="target_url" value="<?php echo esc_attr($link->target_url); ?>" class="regular-text" required>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Update Pretty Link'); ?>
            </form>
        </div>
        <?php
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
                    '<strong><a href="%s">%s</a></strong><br><small>%s</small>',
                    admin_url("admin.php?page=leadstream-links&action=edit&id={$item->id}"),
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
                return sprintf(
                    '<a href="%s" class="button button-small">Edit</a> 
                     <a href="%s" class="button button-small" onclick="navigator.clipboard.writeText(\'%s\'); alert(\'Copied!\'); return false;">Copy</a>
                     <a href="%s" class="button button-small" target="_blank">Test</a>',
                    admin_url("admin.php?page=leadstream-links&action=edit&id={$item->id}"),
                    '#',
                    esc_js($short_url),
                    esc_url($short_url)
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
     * Handle adding new link
     */
    public static function handle_add_link() {
        if (!wp_verify_nonce($_POST['add_link_nonce'], 'add_link')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        $slug = sanitize_text_field($_POST['slug']);
        $target_url = esc_url_raw($_POST['target_url']);

        if (empty($slug) || empty($target_url)) {
            wp_die('Both slug and target URL are required');
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
            wp_die('Failed to create link. Slug might already exist.');
        }

        wp_redirect(admin_url('admin.php?page=leadstream-links&message=added'));
        exit;
    }

    /**
     * Handle deleting link
     */
    public static function handle_delete_link() {
        $id = intval($_GET['id'] ?? 0);
        if (!$id || !wp_verify_nonce($_GET['_wpnonce'], 'delete_link_' . $id)) {
            wp_die('Security check failed');
        }

        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'ls_links', ['id' => $id], ['%d']);

        wp_redirect(admin_url('admin.php?page=leadstream-links&message=deleted'));
        exit;
    }
}
