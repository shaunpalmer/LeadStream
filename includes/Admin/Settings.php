<?php
namespace LS\Admin;
/**
 * LeadStream Admin Settings Handler
 * Handles admin menu, settings registration, field callbacks, and settings display
 */



defined('ABSPATH') || exit;

class Settings {
    
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_notices', [__CLASS__, 'show_admin_notices']);
        add_action('admin_footer', [__CLASS__, 'custom_admin_footer']);
        add_filter('plugin_action_links_' . plugin_basename(dirname(dirname(__DIR__)) . '/leadstream-analytics-injector.php'), [__CLASS__, 'add_settings_link']);
        
        // Dashboard widget for Pretty Links stats
        add_action('wp_dashboard_setup', [__CLASS__, 'add_dashboard_widget']);
        
        // Pretty Links form handlers (remove old admin_post handlers)
        // add_action('admin_post_add_pretty_link', [__CLASS__, 'handle_add_pretty_link']);
        // add_action('admin_post_edit_pretty_link', [__CLASS__, 'handle_edit_pretty_link']);
        
        // AJAX handlers
        add_action('wp_ajax_check_slug_availability', [__CLASS__, 'ajax_check_slug_availability']);
    add_action('wp_ajax_ls_generate_short_slug', [__CLASS__, 'ajax_generate_short_slug']);
    add_action('wp_ajax_ls_phone_table', [__CLASS__, 'ajax_phone_table']);
    add_action('wp_ajax_ls_calls_table', [__CLASS__, 'ajax_calls_table']);
    // Utility: soft-confirm counts for range deletions
    add_action('wp_ajax_ls_count_range', [__CLASS__, 'ajax_count_range']);
    }

    /**
     * AJAX: Count rows for range delete soft-confirm
     */
    public static function ajax_count_range() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }
        check_ajax_referer('ls-admin', 'nonce');
        $kind = isset($_POST['kind']) ? sanitize_text_field($_POST['kind']) : '';
        $from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : '';
        $to   = isset($_POST['to'])   ? sanitize_text_field($_POST['to'])   : '';
        if (!$kind || !$from || !$to) {
            wp_send_json_error(['message' => 'missing params'], 400);
        }
        global $wpdb; $table = $wpdb->prefix . 'ls_clicks';
        $lt = ($kind === 'link') ? 'link' : 'phone';
        $sql = "SELECT COUNT(*) FROM {$table} WHERE link_type = %s AND clicked_at BETWEEN %s AND %s";
        $count = (int) $wpdb->get_var($wpdb->prepare($sql, $lt, $from . ' 00:00:00', $to . ' 23:59:59'));
        wp_send_json_success(['count' => $count]);
    }
    
    /**
     * Add settings page to admin menu
     */
    public static function add_settings_page() {
        add_menu_page(
            'LeadStream Analytics', 
            'LeadStream', 
            'manage_options', 
            'leadstream-analytics-injector', 
            [__CLASS__, 'display_settings_page']
        );
    }
    
    /**
     * Sanitize and normalize phone numbers input with deduplication
     */
    public static function sanitize_phone_numbers($input) {
        if (empty($input)) {
            return array();
        }
        
        
        // Handle both string and array input
        if (is_array($input)) {
            $raw_numbers = $input;
        } else {
            // If it's a string, split by newlines
            $raw_numbers = explode("\n", $input);
        }
        
        $normalized_numbers = array();
        
        foreach ($raw_numbers as $raw_number) {
            $raw_number = trim(sanitize_text_field($raw_number));
            if (empty($raw_number)) {
                continue;
            }
            
            // Normalize the phone number (digits only)
            $normalized = self::normalize_phone_number($raw_number);
            
            // Only add if it's not already in our array (deduplication)
            if (!empty($normalized) && !in_array($normalized, $normalized_numbers)) {
                $normalized_numbers[] = $normalized;
            }
        }
        
        return $normalized_numbers;
    }
    
    /**
     * Normalize phone number to consistent format (digits only)
     */
    private static function normalize_phone_number($phone) {
        if (empty($phone)) {
            return '';
        }
        // Tracking normalization: digits only, no automatic country code or truncation.
        // Dialing/display is preserved elsewhere (e.g., Call Bar uses your exact input or '+').
        $digits_only = preg_replace('/\D+/', '', (string) $phone);
        // Allow custom strategies via filter if needed.
        return apply_filters('leadstream_normalize_phone_digits', $digits_only, $phone);
    }
    
    /**
     * Register settings and fields
     */
    public static function register_settings() {
        register_setting('lead-tracking-js-settings-group', 'custom_header_js', array(
            'sanitize_callback' => '\LS\Utils::sanitize_javascript'
        ));
        register_setting('lead-tracking-js-settings-group', 'custom_footer_js', array(
            'sanitize_callback' => '\LS\Utils::sanitize_javascript'
        ));
        register_setting('lead-tracking-js-settings-group', 'leadstream_inject_header', array(
            'type' => 'integer',
            'default' => 1
        ));
        register_setting('lead-tracking-js-settings-group', 'leadstream_inject_footer', array(
            'type' => 'integer',
            'default' => 1
        ));
        register_setting('lead-tracking-js-settings-group', 'leadstream_gtm_id', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        // Default calling country code used during phone normalization (digits only, e.g., "1" for US, "64" for NZ)
        register_setting('lead-tracking-js-settings-group', 'leadstream_default_country_code', array(
            'type' => 'string',
            'default' => '1',
            'sanitize_callback' => [__CLASS__, 'sanitize_country_code']
        ));
        // Stub setting: LS badge toggle (grayed out on free; auto-hidden on paid)
        register_setting('lead-tracking-js-settings-group', 'leadstream_enable_badge', array(
            'type'    => 'integer',
            'default' => 1,
        ));
        
        // Phone tracking settings - handled via custom form processing
        // register_setting('leadstream_phone_settings_group', 'leadstream_phone_numbers', array(
        //     'sanitize_callback' => [__CLASS__, 'sanitize_phone_numbers']
        // ));
        
        add_settings_section(
            'lead-tracking-js-settings-section',
            'Custom JavaScript Injection',
            null,
            'lead-tracking-js-settings-group'
        );
        
        add_settings_field(
            'custom_header_js_field',
            'Header JavaScript',
            [__CLASS__, 'header_js_field_callback'],
            'lead-tracking-js-settings-group',
            'lead-tracking-js-settings-section'
        );
        
        add_settings_field(
            'custom_footer_js_field',
            'Footer JavaScript',
            [__CLASS__, 'footer_js_field_callback'],
            'lead-tracking-js-settings-group',
            'lead-tracking-js-settings-section'
        );
        
        add_settings_field(
            'leadstream_gtm_id_field',
            'Google Tag Manager ID',
            [__CLASS__, 'gtm_id_field_callback'],
            'lead-tracking-js-settings-group',
            'lead-tracking-js-settings-section'
        );

        // Default calling country selector (shown in Injection Settings table)
        add_settings_field(
            'leadstream_default_country_code_field',
            'Default Calling Country',
            [__CLASS__, 'country_code_field_callback'],
            'lead-tracking-js-settings-group',
            'lead-tracking-js-settings-section'
        );

        // Badge toggle (UI stub): disabled until paid flag present
        add_settings_field(
            'leadstream_badge_toggle_field',
            'Tiny LS badge (free installs)',
            [__CLASS__, 'badge_toggle_field_callback'],
            'lead-tracking-js-settings-group',
            'lead-tracking-js-settings-section'
        );
    }

    /** Determine if paid version flags are present (stubs). */
    private static function is_paid() {
        if (defined('LEADSTREAM_PRO') && LEADSTREAM_PRO) return true;
        if (apply_filters('leadstream_is_paid', false)) return true;
        if ((bool) get_option('leadstream_paid_stub', false)) return true;
        return false;
    }
    
    /**
     * Show admin notices
     */
    public static function show_admin_notices() {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved! Please refresh this page to see changes.</p></div>';
        }
        
        // GTM notice if container is set
        $gtm_id = get_option('leadstream_gtm_id');
        if (!empty($gtm_id) && preg_match('/^GTM-[A-Z0-9]+$/i', $gtm_id)) {
            echo '<div class="notice notice-info is-dismissible"><p>Google Tag Manager container loaded (<strong>' . esc_html($gtm_id) . '</strong>). Configure triggers and tags in GTM dashboard.</p></div>';
        }
    }

    /** Render LS badge toggle field (disabled for free tier; auto-enabled when paid). */
    public static function badge_toggle_field_callback() {
        $paid = self::is_paid();
        $val  = (int) get_option('leadstream_enable_badge', 1);
        $disabled_attr = $paid ? '' : 'disabled="disabled"';
        $checked = $val ? 'checked' : '';
        echo '<label><input type="checkbox" name="leadstream_enable_badge" value="1" ' . $checked . ' ' . $disabled_attr . ' /> Show tiny LS badge in footer</label>';
        echo '<p class="description">';
        if ($paid) {
            echo 'You can toggle the badge for paid installs.';
        } else {
            echo 'This option is managed automatically for free installs and will be unlockable on paid builds.';
        }
        echo '</p>';
    }
    
    /**
     * Display settings page content
     */
    public static function display_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        
        // Handle form submissions FIRST (before any output)
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'javascript';

        // Handle Pretty Links Danger Zone exports (GET) before any output
        if ($current_tab === 'links' && isset($_GET['dz_export']) && in_array($_GET['dz_export'], ['link_clicks','links'], true)) {
            if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
            $nonce = isset($_GET['ls_dz_export']) ? sanitize_text_field(wp_unslash($_GET['ls_dz_export'])) : '';
            if (!wp_verify_nonce($nonce, 'ls_dz_export')) { wp_die('Invalid nonce'); }
            global $wpdb;
            $what = sanitize_text_field($_GET['dz_export']);
            $dz_from = isset($_GET['dz_from']) ? sanitize_text_field($_GET['dz_from']) : '';
            $dz_to   = isset($_GET['dz_to'])   ? sanitize_text_field($_GET['dz_to'])   : '';
            $fmt     = isset($_GET['fmt']) ? sanitize_text_field($_GET['fmt']) : 'csv';
            $excel   = isset($_GET['excel']) && $_GET['excel'] == '1';
            if (function_exists('ob_get_level')) { while (ob_get_level()) { ob_end_clean(); } }
            if ($what === 'link_clicks') {
                $table = $wpdb->prefix . 'ls_clicks';
                $cond = ["link_type = %s"]; $par = ['link'];
                if ($dz_from) { $cond[] = 'clicked_at >= %s'; $par[] = $dz_from . ' 00:00:00'; }
                if ($dz_to)   { $cond[] = 'clicked_at <= %s'; $par[] = $dz_to   . ' 23:59:59'; }
                $where_sql = implode(' AND ', $cond);
                if ($fmt === 'json') {
                    header('Content-Type: application/json; charset=utf-8');
                    header('Content-Disposition: attachment; filename=leadstream-pretty-link-clicks-backup.json');
                    echo '[';
                    $offset = 0; $limit = 2000; $first = true; do {
                        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id ASC LIMIT %d OFFSET %d";
                        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($par, [$limit, $offset])), ARRAY_A);
                        if (!$rows) break;
                        foreach ($rows as $r) { if (!$first) echo ','; $first = false; echo wp_json_encode($r); }
                        $offset += $limit; if (function_exists('flush')) { flush(); }
                    } while (count($rows) === $limit);
                    echo ']'; exit;
                } else {
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename=leadstream-pretty-link-clicks-backup.csv');
                    $out = fopen('php://output', 'w');
                    if ($excel) { echo "\xEF\xBB\xBF"; } // BOM for Excel on Windows
                    $offset = 0; $limit = 5000; $wroteHeader = false; do {
                        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id ASC LIMIT %d OFFSET %d";
                        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($par, [$limit, $offset])), ARRAY_A);
                        if (!$rows) break; if (!$wroteHeader) { fputcsv($out, array_keys($rows[0])); $wroteHeader = true; }
                        foreach ($rows as $r) { fputcsv($out, $r); }
                        $offset += $limit; if (function_exists('flush')) { flush(); }
                    } while (count($rows) === $limit);
                    fclose($out); exit;
                }
            } elseif ($what === 'links') {
                $table = $wpdb->prefix . 'ls_links';
                $cond = ['1=1']; $par = [];
                if ($dz_from) { $cond[] = 'created_at >= %s'; $par[] = $dz_from . ' 00:00:00'; }
                if ($dz_to)   { $cond[] = 'created_at <= %s'; $par[] = $dz_to   . ' 23:59:59'; }
                $where_sql = implode(' AND ', $cond);
                if ($fmt === 'json') {
                    header('Content-Type: application/json; charset=utf-8');
                    header('Content-Disposition: attachment; filename=leadstream-pretty-links-backup.json');
                    echo '['; $offset = 0; $limit = 2000; $first = true; do {
                        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id ASC LIMIT %d OFFSET %d";
                        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($par, [$limit, $offset])), ARRAY_A);
                        if (!$rows) break; foreach ($rows as $r) { if (!$first) echo ','; $first = false; echo wp_json_encode($r); }
                        $offset += $limit; if (function_exists('flush')) { flush(); }
                    } while (count($rows) === $limit); echo ']'; exit;
                } else {
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename=leadstream-pretty-links-backup.csv');
                    $out = fopen('php://output', 'w');
                    if ($excel) { echo "\xEF\xBB\xBF"; }
                    $offset = 0; $limit = 5000; $wroteHeader = false; do {
                        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id ASC LIMIT %d OFFSET %d";
                        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($par, [$limit, $offset])), ARRAY_A);
                        if (!$rows) break; if (!$wroteHeader) { fputcsv($out, array_keys($rows[0])); $wroteHeader = true; }
                        foreach ($rows as $r) { fputcsv($out, $r); }
                        $offset += $limit; if (function_exists('flush')) { flush(); }
                    } while (count($rows) === $limit); fclose($out); exit;
                }
            }
        }
        
        // Only process forms on Pretty Links tab and if it's a POST request
        if ($current_tab === 'links' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Danger Zone: Flushes
                if (isset($_POST['ls_flush_link_clicks'])) {
                    check_admin_referer('ls_flush_link_clicks','ls_flush_link_clicks_nonce');
                    global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}ls_clicks WHERE link_type = 'link'");
                    add_settings_error('leadstream_links', 'flushed_link_clicks', 'All Pretty Link click rows have been permanently deleted.', 'error');
                } elseif (isset($_POST['ls_flush_links'])) {
                    check_admin_referer('ls_flush_links','ls_flush_links_nonce');
                    global $wpdb; $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}ls_links");
                    // Also remove link-type clicks for cleanliness
                    $wpdb->query("DELETE FROM {$wpdb->prefix}ls_clicks WHERE link_type = 'link'");
                    add_settings_error('leadstream_links', 'flushed_links', 'All Pretty Links and their clicks have been permanently deleted.', 'error');
                } elseif (isset($_POST['ls_delete_link_clicks_range'])) {
                    check_admin_referer('ls_delete_link_clicks_range','ls_delete_link_clicks_range_nonce');
                    $dz_from = isset($_POST['dz_from']) ? sanitize_text_field($_POST['dz_from']) : '';
                    $dz_to   = isset($_POST['dz_to'])   ? sanitize_text_field($_POST['dz_to'])   : '';
                    if ($dz_from && $dz_to) {
                        global $wpdb; $table = $wpdb->prefix . 'ls_clicks';
                        $sql = "DELETE FROM {$table} WHERE link_type = %s AND clicked_at BETWEEN %s AND %s";
                        $from = $dz_from . ' 00:00:00'; $to = $dz_to . ' 23:59:59';
                        $wpdb->query($wpdb->prepare($sql, 'link', $from, $to));
                        add_settings_error('leadstream_links', 'deleted_range', 'Deleted link clicks in selected date range.', 'error');
                    } else {
                        add_settings_error('leadstream_links', 'deleted_range_err', 'Please provide a valid From and To date.', 'error');
                    }
                } else {
                    self::handle_pretty_links_form_submission_early();
                }
            } catch (Exception $e) {
                // Log error and show user-friendly message
                error_log('LeadStream form processing error: ' . $e->getMessage());
                add_settings_error('leadstream_links', 'form_error', 'An error occurred while processing the form. Please try again.');
            }
        }

        // Resolve plugin logo URLs (1x + 2x) reliably from plugin root
        if (defined('LS_FILE')) {
            $ls_logo_url   = plugins_url('assets/Lead-stream-logo-Small.png', LS_FILE);
            $ls_logo_2xurl = plugins_url('assets/icon-256x256.png', LS_FILE);
        } else {
            $base = dirname(dirname(__DIR__)) . '/leadstream-analytics-injector.php';
            $ls_logo_url   = plugins_url('assets/Lead-stream-logo-Small.png', $base);
            $ls_logo_2xurl = plugins_url('assets/icon-256x256.png', $base);
        }
        ?>
        <div class="wrap">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                <img src="<?php echo esc_url($ls_logo_url); ?>"
                     srcset="<?php echo esc_url($ls_logo_2xurl); ?> 2x"
                     alt="LeadStream Logo"
                     width="36" height="36"
                     style="width:36px; height:36px; border-radius:4px; object-fit:contain; flex-shrink:0;" />
                <h1 style="margin:0; padding:0;">LeadStream: Advanced Analytics Injector</h1>
            </div>
            
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper">
                <a href="<?php echo add_query_arg('tab', 'javascript', admin_url('admin.php?page=leadstream-analytics-injector')); ?>" 
                   class="nav-tab <?php echo $current_tab === 'javascript' ? 'nav-tab-active' : ''; ?>">
                    📝 JavaScript Injection
                </a>
                <a href="<?php echo add_query_arg('tab', 'utm', admin_url('admin.php?page=leadstream-analytics-injector')); ?>" 
                   class="nav-tab <?php echo $current_tab === 'utm' ? 'nav-tab-active' : ''; ?>">
                    🔗 UTM Builder
                </a>
                <a href="<?php echo add_query_arg('tab', 'links', admin_url('admin.php?page=leadstream-analytics-injector')); ?>" 
                   class="nav-tab <?php echo $current_tab === 'links' ? 'nav-tab-active' : ''; ?>">
                    🎯 Pretty Links
                </a>
                <a href="<?php echo add_query_arg('tab', 'phone', admin_url('admin.php?page=leadstream-analytics-injector')); ?>" 
                   class="nav-tab <?php echo $current_tab === 'phone' ? 'nav-tab-active' : ''; ?>">
                    📞 Phone Tracking
                </a>
                <!-- Future analytics tab placeholder -->
                <?php /* 
                <a href="<?php echo add_query_arg('tab', 'analytics', admin_url('admin.php?page=leadstream-analytics-injector')); ?>" 
                   class="nav-tab <?php echo $current_tab === 'analytics' ? 'nav-tab-active' : ''; ?>">
                    📊 Analytics Dashboard
                </a>
                */ ?>
            </nav>
            
            <?php
            // Display tab content
            switch ($current_tab) {
                case 'utm':
                    self::render_utm_tab();
                    break;
                case 'links':
                    $action = $_GET['action'] ?? 'list';
                    
                    switch ($action) {
                        case 'add':
                            self::render_add_link_form();
                            break;
                        case 'edit':
                            self::render_edit_link_form();
                            break;
                        default:
                            // Show admin notices for Pretty Links
                            self::show_pretty_links_notices();

                            // Handle Pretty Links CSV imports (before rendering forms)
                            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                                // Import Link Clicks
                                if (isset($_POST['ls_import_link_clicks'])) {
                                    check_admin_referer('ls_import_link_clicks', 'ls_import_link_clicks_nonce');
                                    if (!empty($_FILES['ls_import_file']['tmp_name'])) {
                                        global $wpdb; $table = $wpdb->prefix . 'ls_clicks'; $truncate = !empty($_POST['ls_import_truncate']);
                                        if ($truncate) { $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE link_type = %s", 'link')); }
                                        if (($fh = fopen($_FILES['ls_import_file']['tmp_name'], 'r'))) {
                                            $header = fgetcsv($fh); $count=0; $max=20000; if (is_array($header)) { $header = array_map('trim', $header); }
                                            $allowed = ['link_id','link_type','link_key','target_url','clicked_at','click_datetime','click_date','click_time','ip_address','user_agent','user_id','referrer','page_url','page_title','element_type','element_class','element_id','meta_data'];
                                            while (($row = fgetcsv($fh)) !== false && $count < $max) {
                                                $data = array_combine($header, $row); if (!$data) continue;
                                                $rec = array_intersect_key($data, array_flip($allowed));
                                                $rec = array_map('wp_kses_post', $rec);
                                                $rec['link_type'] = 'link';
                                                if (empty($rec['clicked_at'])) {
                                                    $cd = $rec['click_date'] ?? ''; $ct = $rec['click_time'] ?? '';
                                                    $rec['clicked_at'] = trim($cd . ' ' . $ct) ?: current_time('mysql');
                                                }
                                                $wpdb->insert($table, $rec);
                                                $count++;
                                            }
                                            fclose($fh);
                                            add_settings_error('leadstream_links', 'import_link_clicks_ok', sprintf('Imported %d link click rows.', intval($count)), 'updated');
                                        }
                                    }
                                }
                                // Import Pretty Links
                                if (isset($_POST['ls_import_links'])) {
                                    check_admin_referer('ls_import_links', 'ls_import_links_nonce');
                                    if (!empty($_FILES['ls_import_file_links']['tmp_name'])) {
                                        global $wpdb; $table = $wpdb->prefix . 'ls_links'; $truncate = !empty($_POST['ls_import_truncate_links']);
                                        if ($truncate) { $wpdb->query("TRUNCATE TABLE {$table}"); }
                                        if (($fh = fopen($_FILES['ls_import_file_links']['tmp_name'], 'r'))) {
                                            $header = fgetcsv($fh); $count=0; $max=10000; if (is_array($header)) { $header = array_map('trim', $header); }
                                            $allowed = ['slug','target_url','redirect_type','created_at'];
                                            while (($row = fgetcsv($fh)) !== false && $count < $max) {
                                                $data = array_combine($header, $row); if (!$data) continue;
                                                $rec = array_intersect_key($data, array_flip($allowed));
                                                $rec = array_map('wp_kses_post', $rec);
                                                if (empty($rec['redirect_type'])) { $rec['redirect_type'] = '301'; }
                                                $wpdb->insert($table, $rec);
                                                $count++;
                                            }
                                            fclose($fh);
                                            add_settings_error('leadstream_links', 'import_links_ok', sprintf('Imported %d pretty links.', intval($count)), 'updated');
                                        }
                                    }
                                }
                            }

                            // Capture Stats and Helper content so we can wrap in accordions conditionally
                            ob_start();
                            self::show_pretty_links_stats();
                            $stats_html = trim(ob_get_clean());

                            ob_start();
                            self::render_pretty_links_helper();
                            $helper_html = trim(ob_get_clean());

                            // Quick jump links + Add New
                            echo '<div class="ls-btn-row" style="display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin: 6px 0 10px 0;">';
                            echo '  <nav style="display:flex; gap:6px;">';
                            if ($stats_html !== '') {
                                echo '    <a href="#ls-pl-stats" class="button">Stats</a>';
                            }
                            if ($helper_html !== '') {
                                echo '    <a href="#ls-pl-helper" class="button">Quick Access</a>';
                            }
                            echo '    <a href="#ls-pl-table" class="button button-primary">All Links</a>';
                            echo '  </nav>';
                            echo '  <a href="' . esc_url(admin_url('admin.php?page=leadstream-analytics-injector&tab=links&action=add')) . '" class="button button-primary" style="margin-left:auto;">+ Add New</a>';
                            echo '</div>';

                            // Stats panel (optional)
                            if ($stats_html !== '') {
                                echo '<button type="button" class="button button-secondary button-small ls-acc-toggle" data-acc="ls-pl-stats" aria-controls="ls-pl-stats" aria-expanded="true">📊 Link Stats Summary</button>';
                                echo '<div id="ls-pl-stats" class="ls-acc-panel" style="margin-top:10px;">' . $stats_html . '</div>';
                            }

                            // Helper panel (optional)
                            if ($helper_html !== '') {
                                echo '<button type="button" class="button button-secondary button-small ls-acc-toggle" data-acc="ls-pl-helper" aria-controls="ls-pl-helper" aria-expanded="true">🧰 Quick Access Helper</button>';
                                echo '<div id="ls-pl-helper" class="ls-acc-panel" style="margin-top:10px;">' . $helper_html . '</div>';
                            }

                            // Instantiate and render our List Table inside a collapsible panel
                            $table = new \LS\Admin\LinksDashboard();
                            $table->prepare_items();
                            echo '<button type="button" class="button button-secondary button-small ls-acc-toggle" data-acc="ls-pl-table" aria-controls="ls-pl-table" aria-expanded="true">📋 All Pretty Links</button>';
                            echo '<div id="ls-pl-table" class="ls-acc-panel" style="margin-top:10px;">';
                            echo '  <div class="wrap">';
                            echo '    <h1 class="wp-heading-inline">Pretty Links Dashboard</h1>';
                            echo '    <a href="' . esc_url(admin_url('admin.php?page=leadstream-analytics-injector&tab=links&action=add')) . '" class="page-title-action">Add New</a>';
                            echo '    <hr class="wp-header-end">';
                            echo '    <div class="ls-links-table">';
                            $table->display();
                            echo '    </div>';
                            echo '  </div>';
                            echo '</div>';
                            
                            // ... (Import card lives near Danger Zone below)

                            // FAQ Accordion for Pretty Links
                            ?>
                            <div class="postbox" style="margin-top: 30px;">
                                <div class="postbox-header">
                                    <span class="dashicons dashicons-editor-help ls-faq-icon" style="vertical-align: middle; font-size: 20px !important; width: 20px !important; height: 20px !important; line-height: 20px !important;"></span>&nbsp;&nbsp;
                                    <style>.ls-faq-icon.dashicons { font-size: 20px !important; width: 20px !important; height: 20px !important; }</style>
                                    <h2 class="hndle">Frequently Asked Questions</h2>
                                </div>
                                <div class="inside">
                                    <div class="ls-accordion">
                                        
                                        <div class="ls-accordion-item">
                                            <div class="ls-accordion-header" data-target="faq-1">
                                                <span class="ls-accordion-icon">+</span>
                                                <strong>What's the difference between Pretty Links and regular WordPress permalinks?</strong>
                                            </div>
                                            <div class="ls-accordion-content" id="faq-1">
                                                <p>Pretty Links are completely separate from WordPress permalinks. They're custom short URLs (like <code>/l/summer-sale</code>) that redirect to any URL, internal or external. Perfect for tracking campaigns, affiliate links, or making long URLs shareable.</p>
                                            </div>
                                        </div>
                                        
                                        <div class="ls-accordion-item">
                                            <div class="ls-accordion-header" data-target="faq-2">
                                                <span class="ls-accordion-icon">+</span>
                                                <strong>Can I track clicks and analytics?</strong>
                                            </div>
                                            <div class="ls-accordion-content" id="faq-2">
                                                <p>Yes! Every click is automatically tracked and stored in your database. You can see click counts, timestamps, and detailed analytics right in your WordPress admin. Perfect for measuring campaign performance.</p>
                                            </div>
                                        </div>
                                        
                                        <div class="ls-accordion-item">
                                            <div class="ls-accordion-header" data-target="faq-3">
                                                <span class="ls-accordion-icon">+</span>
                                                <strong>Are these links SEO-friendly?</strong>
                                            </div>
                                            <div class="ls-accordion-content" id="faq-3">
                                                <p>Absolutely! All pretty links use proper 301 redirects, which pass SEO juice to the destination URL. Search engines treat them as permanent redirects, maintaining your link authority.</p>
                                            </div>
                                        </div>
                                        
                                        <div class="ls-accordion-item">
                                            <div class="ls-accordion-header" data-target="faq-4">
                                                <span class="ls-accordion-icon">+</span>
                                                <strong>Can I use UTM parameters and tracking codes?</strong>
                                            </div>
                                            <div class="ls-accordion-content" id="faq-4">
                                                <p>Yes! Paste any URL with UTM parameters, affiliate codes, or tracking parameters as your target URL. The pretty link will cleanly redirect while preserving all tracking information.</p>
                                            </div>
                                        </div>
                                        
                                        <div class="ls-accordion-item">
                                            <div class="ls-accordion-header" data-target="faq-5">
                                                <span class="ls-accordion-icon">+</span>
                                                <strong>How many links can I create?</strong>
                                            </div>
                                            <div class="ls-accordion-content" id="faq-5">
                                                <p>There's no built-in limit! The system is designed to handle thousands of links efficiently with direct database lookups. Performance scales well with your needs.</p>
                                            </div>
                                        </div>
                                        
                                        <div class="ls-accordion-item">
                                            <div class="ls-accordion-header" data-target="faq-6">
                                                <span class="ls-accordion-icon">+</span>
                                                <strong>What happens if I delete a pretty link?</strong>
                                            </div>
                                            <div class="ls-accordion-content" id="faq-6">
                                                <p>Once deleted, the pretty link will return a 404 error. However, all click history is preserved in your analytics. Consider editing the target URL instead of deleting if you need to change destinations.</p>
                                            </div>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                            
                            <style>
                            .ls-accordion-item {
                                border-bottom: 1px solid #dcdcde;
                                margin: 0;
                            }
                            .ls-accordion-item:last-child {
                                border-bottom: none;
                            }
                            .ls-accordion-header {
                                padding: 20px;
                                cursor: pointer;
                                display: flex;
                                align-items: flex-start;
                                background: #fff;
                                border: none;
                                width: 100%;
                                text-align: left;
                                font-family: inherit;
                                transition: background-color 0.15s ease-in-out;
                                gap: 12px;
                            }
                            .ls-accordion-header:hover {
                                background: #f6f7f7;
                            }
                            .ls-accordion-header.active {
                                background: #f0f6fc;
                            }
                            .ls-accordion-icon {
                                flex-shrink: 0;
                                width: 20px;
                                height: 20px;
                                border-radius: 50%;
                                background: #2271b1;
                                color: white;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 14px;
                                font-weight: 600;
                                transition: transform 0.2s ease, background-color 0.15s ease;
                                margin-top: 2px;
                            }
                            .ls-accordion-header:hover .ls-accordion-icon {
                                background: #135e96;
                            }
                            .ls-accordion-header.active .ls-accordion-icon {
                                transform: rotate(45deg);
                                background: #0073aa;
                            }
                            .ls-accordion-content {
                                display: none;
                                background: #f9f9f9;
                                border-top: 1px solid #dcdcde;
                            }
                            .ls-accordion-content.active {
                                display: block;
                                padding: 20px 20px 24px 52px;
                            }
                            .ls-accordion-content p {
                                margin: 0 0 16px 0;
                                line-height: 1.6;
                                color: #50575e;
                                font-size: 14px;
                            }
                            .ls-accordion-content p:last-child {
                                margin-bottom: 0;
                            }
                            .ls-accordion-content code {
                                background: #fff;
                                padding: 3px 6px;
                                border-radius: 3px;
                                font-size: 13px;
                                color: #0073aa;
                                border: 1px solid #dcdcde;
                                font-family: Consolas, Monaco, monospace;
                            }
                            .ls-accordion-header strong {
                                font-weight: 600;
                                color: #1d2327;
                                font-size: 14px;
                                line-height: 1.4;
                                flex: 1;
                            }
                            </style>
                            
                            <script>
                            jQuery(document).ready(function($) {
                                // Accordion functionality
                                $('.ls-accordion-header').click(function() {
                                    var target = $(this).data('target');
                                    var content = $('#' + target);
                                    var icon = $(this).find('.ls-accordion-icon');
                                    
                                    if (content.hasClass('active')) {
                                        // Close this accordion
                                        content.removeClass('active').slideUp(200);
                                        $(this).removeClass('active');
                                    } else {
                                        // Close all other accordions
                                        $('.ls-accordion-content.active').removeClass('active').slideUp(200);
                                        $('.ls-accordion-header.active').removeClass('active');
                                        
                                        // Open this accordion
                                        content.addClass('active').slideDown(200);
                                        $(this).addClass('active');
                                    }
                                });
                            });
                            </script>
                            <?php
                            
                            // Admin-only: Import cards and Danger Zone for Pretty Links (independent CRUD)
                            if (current_user_can('manage_options')): ?>
        
        <!-- Import: Pretty Links (separate card) -->
    <details style="margin-top: 18px; padding:16px; border:1px solid #96d636ff; background:#fff; border-radius:6px;">
        <summary style="padding:12px 16px;  background:#fff5f5; color:#000000; font-weight:600; cursor:pointer;">Import Zone: Import Pretty Links Data</summary>     
        <div style="margin-top: 18px; padding:16px;color: #000;background:#fff5f5;">
            <h3 style="margin:0 0 6px 0;">Import: Pretty Links</h3>
            <p style="margin:6px 0 12px 0; color:#50575e;">Upload CSV backups exported from LeadStream. Use the Links form for slugs/targets and the Clicks form for click history.</p>
            <form method="post" enctype="multipart/form-data" style="display:flex; gap:10px;   align-items:end; flex-wrap:wrap;">
                <?php wp_nonce_field('ls_import_link_clicks','ls_import_link_clicks_nonce'); ?>
                <div><label>Link Clicks CSV<br><input type="file" name="ls_import_file" accept=".csv" required></label></div>
                <label style="display:flex; align-items:center; gap:6px;"><input type="checkbox" name="ls_import_truncate" value="1"> Truncate existing link clicks before import</label>
                <button type="submit" name="ls_import_link_clicks" value="1" class="button">Import Link Clicks CSV</button>
            </form>
        </div>
      </details>

        <!-- Danger Zone: Pretty Links -->
        <details style="margin-top: 18px;" class="ls-acc" id="ls-dz-links">
            <summary style="padding:12px 16px; border:1px solid #d63638; border-radius:6px; background:#fff5f5; color:#b32d2e; font-weight:600; cursor:pointer;">Danger Zone: Flush Pretty Links Data</summary>
            <div style="padding:16px; border:1px solid #d63638; border-top:none; background:#fff5f5; border-radius:0 0 6px 6px;">
            <p style="margin:6px 0 12px 0; color:#8a1f1f;">
                These actions permanently delete data from your database. Use only to resolve data issues or reduce database size. This cannot be undone.
            </p>
            <div style="margin:6px 0 12px 0; display:grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap:8px; align-items:end;">
                <form method="get" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
                    <input type="hidden" name="page" value="leadstream-analytics-injector" />
                    <input type="hidden" name="tab" value="links" />
                    <input type="hidden" name="dz_export" value="link_clicks" />
                    <input type="hidden" name="ls_dz_export" value="<?php echo esc_attr(wp_create_nonce('ls_dz_export')); ?>" />
                    <label>From<br><input type="date" name="dz_from" /></label>
                    <label>To<br><input type="date" name="dz_to" /></label>
                    <label style="display:flex; align-items:center; gap:6px;">
                        <input type="checkbox" name="excel" value="1" /> Open in Excel
                    </label>
                    <button class="button button-secondary" type="submit" name="fmt" value="csv">Export Link Clicks CSV</button>
                    <button class="button" type="submit" name="fmt" value="json">Export Link Clicks JSON</button>
                </form>
                <form method="get" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
                    <input type="hidden" name="page" value="leadstream-analytics-injector" />
                    <input type="hidden" name="tab" value="links" />
                    <input type="hidden" name="dz_export" value="links" />
                    <input type="hidden" name="ls_dz_export" value="<?php echo esc_attr(wp_create_nonce('ls_dz_export')); ?>" />
                    <label>From<br><input type="date" name="dz_from" /></label>
                    <label>To<br><input type="date" name="dz_to" /></label>
                    <label style="display:flex; align-items:center; gap:6px;">
                        <input type="checkbox" name="excel" value="1" /> Open in Excel
                    </label>
                    <button class="button button-secondary" type="submit" name="fmt" value="csv">Export Pretty Links CSV</button>
                    <button class="button" type="submit" name="fmt" value="json">Export Pretty Links JSON</button>
                </form>
                <div style="align-self:center; color:#8a1f1f;">Download filtered backups before deleting.</div>
            </div>

            <div style="margin:6px 0 12px 0;">
                <form method="post" onsubmit="return window.LSConfirmRange && LSConfirmRange(this, 'link')" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
                    <?php wp_nonce_field('ls_delete_link_clicks_range','ls_delete_link_clicks_range_nonce'); ?>
                    <label>Delete Link Clicks From<br><input type="date" name="dz_from" required /></label>
                    <label>To<br><input type="date" name="dz_to" required /></label>
                    <button type="submit" name="ls_delete_link_clicks_range" value="1" class="button" style="background:#f0b429; border-color:#f0b429; color:#1d2327;">Delete Link Clicks in Range</button>
                </form>
            </div>
            <label style="display:flex; align-items:center; gap:8px; margin:8px 0; color:#8a1f1f;">
                <input type="checkbox" id="ls-confirm-links-flush" />
                <span>I understand this will permanently remove Pretty Links and/or their click history from the database.</span>
            </label>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <form method="post" onsubmit="return (document.getElementById('ls-confirm-links-flush')?.checked && confirm('This will permanently delete ALL Pretty Link click rows. Continue?'));">
                    <?php wp_nonce_field('ls_flush_link_clicks','ls_flush_link_clicks_nonce'); ?>
                    <button type="submit" name="ls_flush_link_clicks" value="1" class="button" style="background:#d63638; border-color:#d63638; color:#fff;" id="ls-btn-links-flush-clicks" disabled>Delete All Link Clicks</button>
                </form>
                <form method="post" onsubmit="return (document.getElementById('ls-confirm-links-flush')?.checked && confirm('This will permanently delete ALL Pretty Links (slugs). Clicks tied to links will be deleted via cascade. Continue?'));">
                    <?php wp_nonce_field('ls_flush_links','ls_flush_links_nonce'); ?>
                    <button type="submit" name="ls_flush_links" value="1" class="button" style="background:#8a1f1f; border-color:#8a1f1f; color:#fff;" id="ls-btn-links-flush-links" disabled>Delete All Pretty Links</button>
                </form>
            </div>
            </div>
        </details>

        <?php endif; // admin only ?>
        <?php
                            
                            echo '</div>'; // close outer wrap for Pretty Links default view
                            break;
                    }
                    break;
                case 'phone':
                    self::render_phone_tab();
                    break;
                /* Future analytics tab
                case 'analytics':
                    self::render_analytics_tab();
                    break;
                */
                case 'javascript':
                default:
                    self::render_javascript_tab();
                    break;
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Render Quick Start section
     */
    private static function render_quick_start_section() {
        ?>
        <div class="leadstream-quick-start" style="margin:20px 0; padding:15px; background:#f0f8ff; border-left:4px solid #0073aa; border-radius:4px;">
            <h3 style="margin-top:0;">🚀 Quick Start</h3>
            <p>New to event tracking? Select which samples to load, then click the button below to inject them into the Footer JavaScript box.</p>
            <div id="ls-starter-checkboxes" style="margin-bottom:12px;">
                <strong>Platforms:</strong><br>
                <label><input type="checkbox" id="ls-ga4" checked> Google Analytics (GA4)</label>
                <label style="margin-left:18px;"><input type="checkbox" id="ls-tiktok"> TikTok Pixel</label>
                <label style="margin-left:18px;"><input type="checkbox" id="ls-meta"> Meta Pixel (Facebook)</label>
                <label style="margin-left:18px;"><input type="checkbox" id="ls-triple"> Triple Whale</label>
                <br><br>
                <strong>Form Builders:</strong><br>
                <label><input type="checkbox" id="ls-wpforms" checked> WPForms</label>
                <label style="margin-left:18px;"><input type="checkbox" id="ls-cf7"> Contact Form 7</label>
                <label style="margin-left:18px;"><input type="checkbox" id="ls-gravity"> Gravity Forms</label>
                <label style="margin-left:18px;"><input type="checkbox" id="ls-ninja"> Ninja Forms</label>
                <label style="margin-left:18px;"><input type="checkbox" id="ls-generic"> Generic HTML Form</label>
            </div>
            <button type="button" id="load-starter-script" class="button button-secondary">Load Starter Script</button>
            <small style="color:#666;">Only the checked samples will be loaded below. Customize as needed.</small>
            <div style="margin-top:12px; padding:10px; background:#f9f9f9; border-radius:4px; border-left:3px solid #ffb900;">
                <small style="color:#b07503;"><strong>⚠️ Already using Google Analytics?</strong> If you have Google Site Kit, MonsterInsights, or another GA plugin active, <strong>don't duplicate the same tracking code here</strong>. Use LeadStream for <em>custom events only</em> (form submissions, button clicks, etc.).</small>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Security Notice
     */
    private static function render_security_notice() {
        ?>
        <div class="leadstream-security-notice" style="margin:20px 0; padding:15px; background:#fff3cd; border-left:4px solid #ffc107; border-radius:4px;">
            <h3 style="margin-top:0;">⚠️ Security & Privacy Notice</h3>
            <ul style="margin:10px 0;">
                <li><strong>Admin Only:</strong> Only trusted administrators should add JavaScript code.</li>
                <li><strong>GDPR Compliance:</strong> Ensure your tracking complies with local privacy laws. Avoid collecting personal data without consent.</li>
                <li><strong>Code Safety:</strong> Only paste JavaScript from trusted sources. All code runs on your website frontend.</li>
                <li><strong>No Default Tracking:</strong> This plugin does not track users by default - only your custom code will run.</li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Render conflict detection
     */
    private static function render_conflict_detection() {
        // Conflict detection for popular analytics plugins
        $conflicting_plugins = array(
            'google-site-kit/google-site-kit.php' => 'Google Site Kit',
            'monsterinsights/monsterinsights.php' => 'MonsterInsights',
            'ga-google-analytics/ga-google-analytics.php' => 'GA Google Analytics',
            'analytify/analytify.php' => 'Analytify',
            'wp-analytify/wp-analytify.php' => 'WP Analytify',
        );
        $active_plugins = get_option('active_plugins', array());
        $conflicts = array();
        foreach ($conflicting_plugins as $plugin_file => $plugin_name) {
            if (in_array($plugin_file, $active_plugins)) {
                $conflicts[] = $plugin_name;
            }
        }
        if (!empty($conflicts)) {
            add_settings_error(
                'leadstream_conflict',
                'leadstream_conflict_warning',
                'Warning: The following analytics plugins are active: <strong>' . esc_html(implode(', ', $conflicts)) . '</strong>. This may cause double tracking or conflicts. Consider disabling other analytics plugins if you use LeadStream for all tracking.',
                'warning'
            );
        }
    }
    
    /**
     * Render JavaScript section
     */
    private static function render_javascript_section() {
        ?>
        <h2>Custom JavaScript Injection</h2>
        <table class="form-table leadstream-admin">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="custom_header_js">Header JavaScript</label>
                    </th>
                    <td>
                        <?php self::header_js_field_callback(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="custom_footer_js">Footer JavaScript</label>
                    </th>
                    <td>
                        <?php self::footer_js_field_callback(); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render GTM section
     */
    private static function render_gtm_section() {
        ?>
        <h2>Google Tag Manager</h2>
        <table class="form-table leadstream-admin">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="leadstream_gtm_id">GTM Container ID</label>
                    </th>
                    <td>
                        <?php self::gtm_id_field_callback(); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render injection settings
     */
    private static function render_injection_settings() {
        ?>
        <h2>Injection Settings</h2>
        <table class="form-table leadstream-admin">
            <tbody>
                <tr>
                    <th scope="row">
                        <label>JavaScript Location</label>
                    </th>
                    <td>
                        <div class="ls-toggle-group">
                            <label class="ls-toggle-switch">
                                <input type="hidden" name="leadstream_inject_header" value="0">
                                <input type="checkbox" name="leadstream_inject_header" id="leadstream_inject_header" value="1" <?php checked(1, get_option('leadstream_inject_header', 1)); ?>>
                                <span class="ls-slider"></span>
                                <span class="ls-label">in Header</span>
                            </label>
                            <label class="ls-toggle-switch">
                                <input type="hidden" name="leadstream_inject_footer" value="0">
                                <input type="checkbox" name="leadstream_inject_footer" id="leadstream_inject_footer" value="1" <?php checked(1, get_option('leadstream_inject_footer', 1)); ?>>
                                <span class="ls-slider"></span>
                                <span class="ls-label">in Footer</span>
                            </label>
                        </div>
                        <p class="description">Choose where your JavaScript code should be injected. Header is better for setup scripts, Footer for event tracking.</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php self::render_toggle_styles(); ?>
        <?php
    }

    /** Sanitize numeric country calling code (digits only). */
    public static function sanitize_country_code($val) {
        $val = is_scalar($val) ? (string) $val : '';
        $val = preg_replace('/\D+/', '', $val ?? '');
        return $val !== '' ? $val : '1';
    }

    /** Render the Default Calling Country dropdown (curated list; filterable). */
    public static function country_code_field_callback() {
        $current = (string) get_option('leadstream_default_country_code', '1');
        $options = self::get_supported_calling_codes();
        echo '<select id="leadstream_default_country_code" name="leadstream_default_country_code">';
        foreach ($options as $opt) {
            $code = esc_attr($opt['code']);
            $label = esc_html($opt['label']);
            $sel = ($current === (string) $opt['code']) ? ' selected' : '';
            echo '<option value="' . $code . '"' . $sel . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Used to normalize local phone numbers to international format. List covers OECD members and select high‑income regions. Filter: <code>leadstream_supported_calling_codes</code>.</p>';
    }

    /**
     * Curated calling codes for common markets (OECD members + select high-income regions).
     * Return value: array of [ 'code' => '64', 'label' => 'New Zealand (+64)' ]
     */
    private static function get_supported_calling_codes() {
        $list = [
            // North America
            ['code' => '1',   'label' => 'United States (+1)'],
            ['code' => '1',   'label' => 'Canada (+1)'],
            ['code' => '52',  'label' => 'Mexico (+52)'],
            // Europe (OECD)
            ['code' => '43',  'label' => 'Austria (+43)'],
            ['code' => '32',  'label' => 'Belgium (+32)'],
            ['code' => '420', 'label' => 'Czech Republic (+420)'],
            ['code' => '45',  'label' => 'Denmark (+45)'],
            ['code' => '372', 'label' => 'Estonia (+372)'],
            ['code' => '358', 'label' => 'Finland (+358)'],
            ['code' => '33',  'label' => 'France (+33)'],
            ['code' => '49',  'label' => 'Germany (+49)'],
            ['code' => '30',  'label' => 'Greece (+30)'],
            ['code' => '36',  'label' => 'Hungary (+36)'],
            ['code' => '354', 'label' => 'Iceland (+354)'],
            ['code' => '353', 'label' => 'Ireland (+353)'],
            ['code' => '39',  'label' => 'Italy (+39)'],
            ['code' => '371', 'label' => 'Latvia (+371)'],
            ['code' => '370', 'label' => 'Lithuania (+370)'],
            ['code' => '352', 'label' => 'Luxembourg (+352)'],
            ['code' => '31',  'label' => 'Netherlands (+31)'],
            ['code' => '47',  'label' => 'Norway (+47)'],
            ['code' => '48',  'label' => 'Poland (+48)'],
            ['code' => '351', 'label' => 'Portugal (+351)'],
            ['code' => '421', 'label' => 'Slovakia (+421)'],
            ['code' => '386', 'label' => 'Slovenia (+386)'],
            ['code' => '34',  'label' => 'Spain (+34)'],
            ['code' => '46',  'label' => 'Sweden (+46)'],
            ['code' => '41',  'label' => 'Switzerland (+41)'],
            ['code' => '44',  'label' => 'United Kingdom (+44)'],
            // Asia-Pacific
            ['code' => '61',  'label' => 'Australia (+61)'],
            ['code' => '64',  'label' => 'New Zealand (+64)'],
            ['code' => '81',  'label' => 'Japan (+81)'],
            ['code' => '82',  'label' => 'South Korea (+82)'],
            ['code' => '65',  'label' => 'Singapore (+65)'],
            ['code' => '852', 'label' => 'Hong Kong (+852)'],
            // Middle East
            ['code' => '972', 'label' => 'Israel (+972)'],
            ['code' => '971', 'label' => 'United Arab Emirates (+971)'],
            ['code' => '90',  'label' => 'Turkey (+90)'],
            // Latin America (OECD members)
            ['code' => '56',  'label' => 'Chile (+56)'],
            ['code' => '57',  'label' => 'Colombia (+57)'],
            ['code' => '506', 'label' => 'Costa Rica (+506)'],
        ];

        /**
         * Filter the supported calling codes list.
         * @param array $list Array of [ 'code' => string, 'label' => string ]
         */
        $list = apply_filters('leadstream_supported_calling_codes', $list);

        // Ensure unique and sanitized
        $seen = [];
        $out = [];
        foreach ($list as $it) {
            if (!is_array($it) || empty($it['code']) || empty($it['label'])) { continue; }
            $key = $it['code'] . '|' . $it['label'];
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $out[] = [ 'code' => (string) preg_replace('/\D+/', '', (string) $it['code']), 'label' => (string) $it['label'] ];
        }
        return $out;
    }
    
    /**
     * Render FAQ section
     */
    private static function render_faq_section() {
        ?>
        <!-- Accordion FAQ for advanced usage -->
        <div class="ls-accordion-faq" style="margin-top: 36px;">
            <h2>📚 Advanced Examples & FAQ</h2>
            <div class="ls-accordion">
                <div class="ls-accordion-item">
                    <button class="ls-accordion-toggle">How do I track WPForms with form ID, name, and page URL?</button>
                    <div class="ls-accordion-panel">
                        <pre><code id="faq-wpforms-example">
// Enhanced WPForms tracking for Google Analytics
document.addEventListener('wpformsSubmit', function (event) {
    const formId = event.detail.formId;
    const formName = event.detail.formName || 'Unnamed Form ' + formId;
    const pageUrl = window.location.href;
    const eventLabel = `Form ID: ${formId} | Form Name: ${formName} | Page: ${pageUrl}`;
    gtag('event', 'form_submit', {
        'event_category': 'Lead',
        'event_label': eventLabel,
        'value': 1,
        'form_id': formId,
        'page_url': pageUrl
    });
    console.log('WPForms Submission Tracked:', { formId, formName, pageUrl });
}, false);
                        </code></pre>
                        <button class="ls-copy-btn" data-copytarget="faq-wpforms-example" data-copyfield="custom_header_js">Copy to Header</button>
                        <button class="ls-copy-btn" data-copytarget="faq-wpforms-example" data-copyfield="custom_footer_js">Copy to Footer</button>
                    </div>
                </div>
                <!-- Add more items here for Gravity Forms, Ninja Forms, custom events, etc. -->
            </div>
        </div>
        <?php self::render_faq_styles(); ?>
        <?php
    }
    
    /**
     * Callback for header JS field
     */
    public static function header_js_field_callback() {
        $header_js = get_option('custom_header_js');
        echo '<textarea id="custom_header_js" name="custom_header_js" class="large-text code" rows="15" placeholder="// Header JavaScript - typically for setup code or early-loading scripts

// Click \'Load Starter Script\' above for pre-built examples
// that work with your selected form builders and analytics platforms

// Add your header JavaScript here...">' . esc_textarea($header_js) . '</textarea>';
        echo '<p class="description">JavaScript code to inject in the &lt;head&gt; section. Best for setup code and early-loading scripts. No &lt;script&gt; tags needed.</p>';
    }
    
    /**
     * Callback for footer JS field
     */
    public static function footer_js_field_callback() {
        $footer_js = get_option('custom_footer_js');
        echo '<textarea id="custom_footer_js" name="custom_footer_js" class="large-text code" rows="15" placeholder="// Footer JavaScript - perfect for event tracking after page loads

// Click \'Load Starter Script\' above for pre-built examples
// that work with your selected form builders and analytics platforms

// Add your custom footer JavaScript here...">' . esc_textarea($footer_js) . '</textarea>';
        echo '<p class="description">JavaScript code to inject before closing &lt;/body&gt; tag. Perfect for event tracking and user interaction. No &lt;script&gt; tags needed.</p>';
    }
    
    /**
     * Callback for GTM ID field
     */
    public static function gtm_id_field_callback() {
        $gtm_id = get_option('leadstream_gtm_id');
        echo '<input name="leadstream_gtm_id" id="leadstream_gtm_id" type="text" value="' . esc_attr($gtm_id) . '" placeholder="GTM-XXXXXXX" size="20" />';
        echo '<p class="description">Paste your GTM container ID (e.g. GTM-ABCDE12). No script tags—just the ID.</p>';
    }
    
    /**
     * Add Settings link beside Deactivate on plugins page
     */
    public static function add_settings_link($links) {
        $settings_url = admin_url('admin.php?page=leadstream-analytics-injector');
        $settings_link = '<a href="' . esc_url($settings_url) . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Custom admin footer
     */
    public static function custom_admin_footer() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (is_object($screen) && $screen->id === 'toplevel_page_leadstream-analytics-injector') {
            echo '
            <div id="leadstream-footer-replacement">
                Made with <span class="emoji">❤️</span> by LeadStream
            </div>
            <style>
                #wpfooter { display: none !important; }
                #leadstream-footer-replacement {
                    position: fixed;
                    bottom: 0;
                    left: 160px; /* Matches admin menu width */
                    width: calc(100% - 160px);
                    background: #fff;
                    border-top: 1px solid #ccc;
                    padding: 10px;
                    font-size: 13px;
                    color: #2271b1;
                    text-align: center;
                    z-index: 9999;
                    box-shadow: 0 -1px 3px rgba(0,0,0,0.05);
                }
                .emoji { margin: 0 4px; }
            </style>';
        }
    }
    
    /**
     * Render toggle styles
     */
    private static function render_toggle_styles() {
        ?>
        <style>
        .ls-toggle-group {
          display: flex;
          gap: 32px;
          margin-bottom: 24px;
          align-items: center;
        }
        .ls-toggle-switch {
          display: flex;
          align-items: center;
          gap: 10px;
          font-size: 1.1em;
          margin-bottom: 0;
          padding-bottom: 0;
        }
        .ls-slider {
          position: relative;
          display: inline-block;
          width: 56px;
          height: 28px;
          background-color: #ccc;
          border-radius: 34px;
          transition: background 0.3s;
        }
        .ls-toggle-switch input:checked + .ls-slider {
          background-color: #27ae60;
        }
        .ls-slider:before {
          content: "";
          position: absolute;
          left: 4px;
          bottom: 4px;
          width: 20px;
          height: 20px;
          background: white;
          border-radius: 50%;
          transition: transform 0.3s;
        }
        .ls-toggle-switch input:checked + .ls-slider:before {
          transform: translateX(28px);
        }
        .ls-toggle-switch input {
          display: none;
        }
        .ls-label {
          margin-left: 12px;
          font-weight: 500;
          letter-spacing: 0.03em;
        }
        </style>
        <?php
    }
    
    /**
     * Render FAQ styles
     */
    private static function render_faq_styles() {
        ?>
        <style>
        .ls-accordion { margin: 0; padding: 0; }
        .ls-accordion-item { margin-bottom: 14px; border: 1px solid #e0e0e0; border-radius: 8px; }
        .ls-accordion-toggle {
            width: 100%; background: #fafafa; border: none; text-align: left;
            padding: 12px 16px; font-weight: 600; cursor: pointer; border-radius: 8px 8px 0 0;
        }
        .ls-accordion-panel { display: none; padding: 14px 18px; background: #f7f7f7; }
        .ls-copy-btn { margin-top: 10px; margin-right: 8px; background: #27ae60; color: #fff; border: none; padding: 7px 16px; border-radius: 4px; cursor: pointer; }
        </style>
        <?php
    }

    /**
     * Render the JavaScript injection tab
     */
    private static function render_javascript_tab() {
        ?>
        <?php self::render_quick_start_section(); ?>
        
        <?php self::render_security_notice(); ?>
        <p>Professional JavaScript injection for advanced lead tracking. Add your custom code below - no &lt;script&gt; tags needed.</p>
        <?php self::render_conflict_detection(); ?>
        <form action='options.php' method='post'>
            <?php settings_fields('lead-tracking-js-settings-group'); ?>
            <div class="leadstream-admin">
                <?php self::render_javascript_section(); ?>
                <?php self::render_gtm_section(); ?>
                <?php self::render_injection_settings(); ?>
            </div>
            <?php submit_button('Save JavaScript'); ?>
        </form>
        <?php self::render_faq_section(); ?>
        <?php
    }

    /**
     * Render the UTM builder tab
     */
    private static function render_utm_tab() {
        ?>
        <div class="leadstream-utm-builder" style="max-width: 800px;">
            <h2>UTM Builder</h2>
            <p>Generate UTM-tagged URLs for tracking campaign performance across <strong>any marketing platform</strong> - social media, email, paid ads, content marketing, and more.</p>
            
            <form id="utm-builder-form" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="utm-url">Website URL *</label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="utm-url" 
                                   name="base_url" 
                                   class="regular-text" 
                                   placeholder="https://example.com/landing-page" 
                                   required />
                            <p class="description">The destination URL you want to track</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="utm-source">Campaign Source *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="utm-source" 
                                   name="utm_source" 
                                   class="regular-text" 
                                   placeholder="facebook, google, linkedin, newsletter, website" 
                                   required />
                            <p class="description">Where the traffic comes from (platform, website, or referrer)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="utm-medium">Campaign Medium *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="utm-medium" 
                                   name="utm_medium" 
                                   class="regular-text" 
                                   placeholder="paid-social, email, ppc, display, organic" 
                                   required />
                            <p class="description">How users reached you (paid-social, email, ppc, display, organic, etc.)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="utm-campaign">Campaign Name *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="utm-campaign" 
                                   name="utm_campaign" 
                                   class="regular-text" 
                                   placeholder="holiday-sale, webinar-series, brand-awareness" 
                                   required />
                            <p class="description">Your campaign name (keep consistent across channels)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="utm-term">Campaign Term</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="utm-term" 
                                   name="utm_term" 
                                   class="regular-text" 
                                   placeholder="business software, digital marketing, online tools" />
                            <p class="description">Optional: Target keywords for paid search campaigns</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="utm-content">Campaign Content</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="utm-content" 
                                   name="utm_content" 
                                   class="regular-text" 
                                   placeholder="video-ad, carousel-post, story-highlight, banner-top" />
                            <p class="description">Optional: Differentiate ad variations, content types, or placements</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="utm-button">Button/CTA Tracking</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="utm-button" 
                                   name="utm_button" 
                                   class="regular-text" 
                                   placeholder="learn-more, get-started, watch-demo, contact-sales" />
                            <p class="description">Optional: Track specific call-to-action buttons or links</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" id="generate-utm" class="button-primary">Generate UTM URL</button>
                    <button type="button" id="clear-utm" class="button">Clear Form</button>
                </p>
            </form>
            
            <div id="utm-result" style="display: none; margin-top: 20px;">
                <h3>✅ Generated UTM URL</h3>
                <div style="background: #f7f7f7; padding: 20px; border-radius: 6px; border: 1px solid #ddd;">
                    
                    <!-- Full URL Display -->
                    <div style="margin-bottom: 15px;">
                        <label for="utm-generated-url" style="font-weight: 600; margin-bottom: 5px; display: block;">
                            📋 Complete UTM URL:
                        </label>
                        <textarea id="utm-generated-url" 
                                  readonly 
                                  style="width: 100%; height: 100px; padding: 10px; font-family: 'Courier New', monospace; 
                                         background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; 
                                         font-size: 12px; line-height: 1.4; resize: vertical;"
                                  placeholder="Generated UTM URL will appear here..."></textarea>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="margin-bottom: 15px;">
                        <button type="button" id="copy-utm-url" class="button button-primary" style="margin-right: 10px;">
                            📋 Copy URL
                        </button>
                        <button type="button" id="open-utm-url" class="button" style="margin-right: 10px;">
                            🌐 Test URL
                        </button>
                        <span id="utm-copy-feedback" style="color: #46b450; font-weight: 600; display: none;">
                            ✅ Copied to clipboard!
                        </span>
                    </div>
                    
                    <!-- UTM Parameters Breakdown -->
                    <div id="utm-breakdown" style="background: #fff; padding: 15px; border-radius: 4px; border: 1px solid #e0e0e0;">
                        <h4 style="margin-top: 0; margin-bottom: 10px; color: #333;">🔍 UTM Parameters Breakdown:</h4>
                        <div id="utm-params-list" style="font-family: monospace; font-size: 13px; line-height: 1.6;">
                            <!-- Parameters will be populated by JavaScript -->
                        </div>
                        <p style="margin-top: 15px; margin-bottom: 0; font-size: 12px; color: #666;">
                            <strong>💡 Universal Usage Guide:</strong><br>
                            • <strong>Social Platforms:</strong> Facebook, Instagram, LinkedIn, Twitter, TikTok, YouTube ads & posts<br>
                            • <strong>Paid Advertising:</strong> Google Ads, Microsoft Ads, display networks, native advertising<br>
                            • <strong>Email Marketing:</strong> Newsletters, automated sequences, promotional campaigns<br>
                            • <strong>Content Marketing:</strong> Blog posts, guest articles, influencer partnerships<br>
                            • <strong>Analytics:</strong> View results in Google Analytics, Adobe Analytics, or your preferred platform<br>
                            • <strong>Best Practice:</strong> Keep campaign names consistent across all marketing channels
                        </p>
                    </div>
                </div>
            </div>
            
            <?php self::render_utm_history(); ?>
        </div>
        <?php
    }

    /**
     * Render UTM history table
     */
    private static function render_utm_history() {
        // Get UTM history from persistent user meta instead of transient
        $user_id = get_current_user_id();
        $history = get_user_meta($user_id, 'ls_utm_history', true);
        if (!is_array($history)) {
            $history = [];
        }
        if (empty($history)) {
            return;
        }
        ?>
        <div style="margin-top: 40px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;">📋 Recent UTM URLs</h2>
                <button type="button" id="clear-utm-history" class="button" style="color: #d63638;">
                    🗑️ Clear History
                </button>
            </div>
            
            <table class="widefat fixed striped" style="margin-top: 0;">
                <thead>
                    <tr>
                        <th width="3%">#</th>
                        <th width="30%">Campaign Details</th>
                        <th>Generated URL</th>
                        <th width="12%">When</th>
                        <th width="15%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $i => $entry): 
                        $num = $i + 1;
                        $url = esc_url($entry['url']);
                        $when = human_time_diff($entry['time'], current_time('timestamp')) . ' ago';
                        $full_date = date_i18n('Y-m-d H:i:s', $entry['time']);
                    ?>
                    <tr>
                        <td><?php echo $num; ?></td>
                        <td>
                            <strong><?php echo esc_html($entry['campaign']); ?></strong><br>
                            <small style="color: #666;">
                                <?php echo esc_html($entry['source']); ?> • <?php echo esc_html($entry['medium']); ?>
                                <?php if (!empty($entry['content'])): ?>
                                    • <?php echo esc_html($entry['content']); ?>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <code style="word-break: break-all; font-size: 11px; line-height: 1.3;">
                                <?php echo esc_html($url); ?>
                            </code>
                        </td>
                        <td title="<?php echo esc_attr($full_date); ?>">
                            <?php echo esc_html($when); ?>
                        </td>
                        <td>
                            <button class="button button-small copy-history" 
                                    data-url="<?php echo esc_attr($url); ?>" 
                                    style="margin-right: 5px;">
                                📋 Copy
                            </button>
                            <button class="button button-small test-history" 
                                    data-url="<?php echo esc_attr($url); ?>">
                                🌐 Test
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <script>
            jQuery(function($) {
                // Copy history URL
                $('.copy-history').on('click', function() {
                    const $btn = $(this);
                    const url = $btn.data('url');
                    
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(url).then(function() {
                            $btn.text('✅ Copied!');
                            setTimeout(() => $btn.text('📋 Copy'), 2000);
                        });
                    } else {
                        // Fallback
                        const textArea = document.createElement('textarea');
                        textArea.value = url;
                        document.body.appendChild(textArea);
                        textArea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textArea);
                        $btn.text('✅ Copied!');
                        setTimeout(() => $btn.text('📋 Copy'), 2000);
                    }
                });
                
                // Test history URL
                $('.test-history').on('click', function() {
                    const url = $(this).data('url');
                    window.open(url, '_blank');
                });
                
                // Clear history
                $('#clear-utm-history').on('click', function() {
                    if (!confirm('Are you sure you want to clear all UTM history? This cannot be undone.')) {
                        return;
                    }
                    
                    const $btn = $(this);
                    $btn.prop('disabled', true).text('Clearing...');
                    
                    $.post(leadstream_utm_ajax.ajax_url, {
                        action: 'clear_utm_history',
                        nonce: leadstream_utm_ajax.nonce
                    })
                    .done(function(response) {
                        if (response.success) {
                            location.reload(); // Refresh to hide the table
                        } else {
                            alert('Error clearing history: ' + (response.data || 'Unknown error'));
                        }
                    })
                    .fail(function() {
                        alert('Error: Could not connect to server.');
                    })
                    .always(function() {
                        $btn.prop('disabled', false).text('🗑️ Clear History');
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Get phone tracking summary for live counters
     */
    private static function get_phone_tracking_summary() {
        global $wpdb;
        $table = $wpdb->prefix . 'ls_clicks';
        
        // Check if table exists and has required columns
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
        if (!$table_exists) {
            return ['total' => 0, 'phone' => 0, 'custom' => 0, 'today' => 0];
        }
        
        // Total clicks recorded for testing/demo purposes
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE link_type IN (%s, %s)",
            'phone', 'test'
        ));
        
        // Phone clicks (link_type = 'phone')
        $phone = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE link_type = %s",
            'phone'
        ));
        
        // Today's phone clicks
        $today = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE link_type = %s AND DATE(clicked_at) = %s",
            'phone',
            current_time('Y-m-d')
        ));
        
        // Custom element clicks (approximate - phone clicks from custom selectors)
        $custom = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE link_type = %s AND element_class IS NOT NULL AND element_class != ''",
            'phone'
        ));
        
        return compact('total', 'phone', 'custom', 'today');
    }

    /**
     * Render Phone Tracking tab
     */
    private static function render_phone_tab() {
        // Danger Zone export for Phone Clicks with date-range + JSON/CSV
        if (isset($_GET['dz_export']) && $_GET['dz_export'] === 'phone_clicks') {
            if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
            $nonce = isset($_GET['ls_dz_export']) ? sanitize_text_field(wp_unslash($_GET['ls_dz_export'])) : '';
            if (!wp_verify_nonce($nonce, 'ls_dz_export')) { wp_die('Invalid nonce'); }
            global $wpdb;
            $table = $wpdb->prefix . 'ls_clicks';
            $dz_from = isset($_GET['dz_from']) ? sanitize_text_field($_GET['dz_from']) : '';
            $dz_to   = isset($_GET['dz_to'])   ? sanitize_text_field($_GET['dz_to'])   : '';
            $fmt     = isset($_GET['fmt']) ? sanitize_text_field($_GET['fmt']) : 'csv';
            $excel   = isset($_GET['excel']) && $_GET['excel'] == '1';
            $cond = ["link_type = %s"]; $par = ['phone'];
            if ($dz_from) { $cond[] = 'clicked_at >= %s'; $par[] = $dz_from . ' 00:00:00'; }
            if ($dz_to)   { $cond[] = 'clicked_at <= %s'; $par[] = $dz_to   . ' 23:59:59'; }
            $where_sql = implode(' AND ', $cond);
            if (function_exists('ob_get_level')) { while (ob_get_level()) { ob_end_clean(); } }
            if ($fmt === 'json') {
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename=leadstream-phone-clicks-backup.json');
                echo '[';
                $offset = 0; $limit = 2000; $first = true; do {
                    $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id ASC LIMIT %d OFFSET %d";
                    $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($par, [$limit, $offset])), ARRAY_A);
                    if (!$rows) break; foreach ($rows as $r) { if (!$first) echo ','; $first = false; echo wp_json_encode($r); }
                    $offset += $limit; if (function_exists('flush')) { flush(); }
                } while (count($rows) === $limit);
                echo ']'; exit;
            } else {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=leadstream-phone-clicks-backup.csv');
                $out = fopen('php://output', 'w'); if ($excel) { echo "\xEF\xBB\xBF"; } $offset = 0; $limit = 5000; $wroteHeader = false; do {
                    $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id ASC LIMIT %d OFFSET %d";
                    $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($par, [$limit, $offset])), ARRAY_A);
                    if (!$rows) break; if (!$wroteHeader) { fputcsv($out, array_keys($rows[0])); $wroteHeader = true; }
                    foreach ($rows as $r) { fputcsv($out, $r); }
                    $offset += $limit; if (function_exists('flush')) { flush(); }
                } while (count($rows) === $limit); fclose($out); exit;
            }
        }
        // CSV Import for Phone Clicks
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ls_import_phone_clicks'])) {
            if (!current_user_can('manage_options')) { wp_die('Permission denied'); }
            check_admin_referer('ls_import_phone_clicks', 'ls_import_phone_clicks_nonce');
            $truncate = !empty($_POST['ls_import_truncate']);
            if (!empty($_FILES['ls_import_file']['tmp_name'])) {
                global $wpdb; $table = $wpdb->prefix . 'ls_clicks';
                if ($truncate) { $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE link_type = %s", 'phone')); }
                if (($fh = fopen($_FILES['ls_import_file']['tmp_name'], 'r'))) {
                    $header = fgetcsv($fh); $count = 0; $max = 20000; if (is_array($header)) { $header = array_map('trim', $header); }
                    $allowed = ['link_id','link_type','link_key','target_url','clicked_at','click_datetime','click_date','click_time','ip_address','user_agent','user_id','referrer','page_url','page_title','element_type','element_class','element_id','meta_data'];
                    while (($row = fgetcsv($fh)) !== false && $count < $max) {
                        $data = array_combine($header, $row); if (!$data) continue;
                        $rec = array_intersect_key($data, array_flip($allowed));
                        $rec = array_map('wp_kses_post', $rec);
                        $rec['link_type'] = 'phone';
                        if (empty($rec['clicked_at'])) {
                            $cd = $rec['click_date'] ?? ''; $ct = $rec['click_time'] ?? '';
                            $rec['clicked_at'] = trim($cd . ' ' . $ct) ?: current_time('mysql');
                        }
                        $wpdb->insert($table, $rec);
                        $count++;
                    }
                    fclose($fh);
                    echo '<div class="notice notice-success is-dismissible"><p>Imported ' . intval($count) . ' phone click rows.</p></div>';
                }
            }
        }

        // Date-range delete for phone clicks
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ls_delete_phone_clicks_range'])) {
            if (!current_user_can('manage_options')) { wp_die('Permission denied'); }
            check_admin_referer('ls_delete_phone_clicks_range', 'ls_delete_phone_clicks_range_nonce');
            $dz_from = isset($_POST['dz_from']) ? sanitize_text_field($_POST['dz_from']) : '';
            $dz_to   = isset($_POST['dz_to'])   ? sanitize_text_field($_POST['dz_to'])   : '';
            if ($dz_from && $dz_to) {
                global $wpdb; $table = $wpdb->prefix . 'ls_clicks';
                $from = $dz_from . ' 00:00:00'; $to = $dz_to . ' 23:59:59';
                $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE link_type = %s AND clicked_at BETWEEN %s AND %s", 'phone', $from, $to));
                echo '<div class="notice notice-warning is-dismissible"><p>Deleted phone clicks between ' . esc_html($dz_from) . ' and ' . esc_html($dz_to) . '.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Please provide a valid From and To date.</p></div>';
            }
        }

        // Dangerous: Flush phone clicks
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ls_flush_phone_clicks'])) {
            if (!current_user_can('manage_options')) { wp_die('Permission denied'); }
            check_admin_referer('ls_flush_phone', 'ls_flush_phone_nonce');
            global $wpdb;
            $table = $wpdb->prefix . 'ls_clicks';
            // Only remove phone click rows
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE link_type = %s", 'phone'));
            echo '<div class="notice notice-error" style="border-left-color:#d63638"><p><strong>Flushed:</strong> All Phone Clicks have been permanently deleted from the database.</p></div>';
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leadstream_phone_submit'])) {
            check_admin_referer('leadstream_phone_settings', 'leadstream_phone_nonce');
            
            // Count original vs normalized numbers for feedback
            $original_input = $_POST['leadstream_phone_numbers'] ?? '';
            $original_count = 0;
            if (!empty($original_input)) {
                $original_lines = explode("\n", $original_input);
                $original_count = count(array_filter(array_map('trim', $original_lines)));
            }
            
            // Sanitize and save phone numbers (with normalization and deduplication)
            $phone_numbers = self::sanitize_phone_numbers($original_input);
            update_option('leadstream_phone_numbers', $phone_numbers);
            
            // Sanitize and save CSS selectors
            $css_selectors = sanitize_textarea_field($_POST['leadstream_phone_selectors'] ?? '');
            update_option('leadstream_phone_selectors', $css_selectors);
            
            // Save enable/disable setting
            $phone_enabled = isset($_POST['leadstream_phone_enabled']) ? 1 : 0;
            update_option('leadstream_phone_enabled', $phone_enabled);

            // Save optional recording URL
            $recording_url = isset($_POST['leadstream_phone_recording_url']) ? esc_url_raw(trim($_POST['leadstream_phone_recording_url'])) : '';
            update_option('leadstream_phone_recording_url', $recording_url);

            // Sticky Call Bar settings
            $callbar_enabled = isset($_POST['leadstream_callbar_enabled']) ? 1 : 0;
            update_option('leadstream_callbar_enabled', $callbar_enabled);
            $callbar_default = sanitize_text_field($_POST['leadstream_callbar_default'] ?? '');
            // Trim to 24 chars server-side
            if (strlen($callbar_default) > 24) { $callbar_default = substr($callbar_default, 0, 24); }
            update_option('leadstream_callbar_default', $callbar_default);
            $callbar_mobile_only = isset($_POST['leadstream_callbar_mobile_only']) ? 1 : 0;
            update_option('leadstream_callbar_mobile_only', $callbar_mobile_only);
            $callbar_position = in_array(($_POST['leadstream_callbar_position'] ?? 'bottom'), ['top','bottom'], true) ? $_POST['leadstream_callbar_position'] : 'bottom';
            update_option('leadstream_callbar_position', $callbar_position);
            $callbar_align = in_array(($_POST['leadstream_callbar_align'] ?? 'center'), ['left','center','right'], true) ? $_POST['leadstream_callbar_align'] : 'center';
            update_option('leadstream_callbar_align', $callbar_align);
            $callbar_cta = sanitize_text_field($_POST['leadstream_callbar_cta'] ?? 'Call Now');
            if (strlen($callbar_cta) > 60) { $callbar_cta = substr($callbar_cta, 0, 60); }
            update_option('leadstream_callbar_cta', $callbar_cta);

            // Save Default Calling Country (from phone tab)
            if (isset($_POST['leadstream_default_country_code'])) {
                $cc = self::sanitize_country_code($_POST['leadstream_default_country_code']);
                update_option('leadstream_default_country_code', $cc);
            }

            // Call Bar appearance options
            if (function_exists('sanitize_hex_color')) {
                $cb_bg         = sanitize_hex_color($_POST['leadstream_callbar_bg'] ?? '#000000');
                $cb_btn_bg     = sanitize_hex_color($_POST['leadstream_callbar_btn_bg'] ?? '#ffce00');
                $cb_btn_text   = sanitize_hex_color($_POST['leadstream_callbar_btn_text'] ?? '#000000');
                $cb_hover_bg   = sanitize_hex_color($_POST['leadstream_callbar_hover_bg'] ?? '#fff200');
                $cb_hover_text = sanitize_hex_color($_POST['leadstream_callbar_hover_text'] ?? '#111111');
                // Pick a complementary default border color (dark goldenrod)
                $cb_border     = sanitize_hex_color($_POST['leadstream_callbar_border'] ?? '#b8860b');
            } else {
                $cb_bg = $cb_btn_bg = $cb_btn_text = $cb_hover_bg = $cb_hover_text = $cb_border = '';
            }
            update_option('leadstream_callbar_bg', $cb_bg ?: '#000000');
            update_option('leadstream_callbar_btn_bg', $cb_btn_bg ?: '#ffce00');
            update_option('leadstream_callbar_btn_text', $cb_btn_text ?: '#000000');
            update_option('leadstream_callbar_hover_bg', $cb_hover_bg ?: '#fff200');
            update_option('leadstream_callbar_hover_text', $cb_hover_text ?: '#111111');
            update_option('leadstream_callbar_border', $cb_border ?: '#b8860b');

            // Font size (accept numeric => rem)
            $cb_font_size = sanitize_text_field($_POST['leadstream_callbar_font_size'] ?? '1rem');
            update_option('leadstream_callbar_font_size', $cb_font_size);

            // Border width and radius
            $bw_raw = sanitize_text_field($_POST['leadstream_callbar_border_width'] ?? '1');
            $bw_val = preg_match('/^\d+$/', $bw_raw) ? ($bw_raw . 'px') : $bw_raw;
            update_option('leadstream_callbar_border_width', $bw_val);
            $cb_radius = sanitize_text_field($_POST['leadstream_callbar_radius'] ?? '999px');
            update_option('leadstream_callbar_radius', $cb_radius);
            $dni_rules_text = sanitize_textarea_field($_POST['leadstream_dni_rules'] ?? '');
            update_option('leadstream_dni_rules', $dni_rules_text);

            // Ensure callbar numbers are also tracked: merge default + rules into phone_numbers
            $extra_numbers = [];
            if (!empty($callbar_default)) {
                $extra_numbers = array_merge($extra_numbers, self::sanitize_phone_numbers([$callbar_default]));
            }
            if (!empty($dni_rules_text)) {
                $lines = preg_split('/\r?\n/', $dni_rules_text);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '#') === 0) { continue; }
                    // Accept formats like: key=value, key -> value, key: value
                    if (preg_match('/^.+?[=:>\-]{1,2}\s*(.+)$/', $line, $m)) {
                        $num = trim($m[1]);
                        $extra_numbers = array_merge($extra_numbers, self::sanitize_phone_numbers([$num]));
                    }
                }
            }
            if (!empty($extra_numbers)) {
                $merged = array_values(array_unique(array_filter(array_merge($phone_numbers, $extra_numbers))));
                $phone_numbers = $merged;
                update_option('leadstream_phone_numbers', $merged);
            }
            
            // Show success message with normalization feedback
            $normalized_count = count($phone_numbers);
            $message = 'Phone tracking settings saved successfully!';
            
            if ($original_count > $normalized_count && $normalized_count > 0) {
                $duplicates_removed = $original_count - $normalized_count;
                $message .= " <strong>Optimization:</strong> {$duplicates_removed} duplicate/invalid numbers were automatically removed.";
            }
            
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        }
        
    // Get current settings
    $phone_numbers = get_option('leadstream_phone_numbers', array());
    $css_selectors = get_option('leadstream_phone_selectors', '');
    $phone_enabled = get_option('leadstream_phone_enabled', 1);
    $recording_url = get_option('leadstream_phone_recording_url', '');
    // Call bar & DNI
    $callbar_enabled = (int) get_option('leadstream_callbar_enabled', 0);
    $callbar_default = (string) get_option('leadstream_callbar_default', '');
    $callbar_mobile_only = (int) get_option('leadstream_callbar_mobile_only', 1);
    $callbar_position = (string) get_option('leadstream_callbar_position', 'bottom');
    $callbar_align   = (string) get_option('leadstream_callbar_align', 'center');
    $callbar_cta = (string) get_option('leadstream_callbar_cta', 'Call Now');
    // Appearance values
    $callbar_bg         = (string) get_option('leadstream_callbar_bg', '#000000');
    $callbar_btn_bg     = (string) get_option('leadstream_callbar_btn_bg', '#ffce00');
    $callbar_btn_text   = (string) get_option('leadstream_callbar_btn_text', '#000000');
    $callbar_hover_bg   = (string) get_option('leadstream_callbar_hover_bg', '#fff200');
    $callbar_hover_text = (string) get_option('leadstream_callbar_hover_text', '#111111');
    $callbar_border     = (string) get_option('leadstream_callbar_border', '#b8860b');
    $callbar_font_size  = (string) get_option('leadstream_callbar_font_size', '1rem');
    $callbar_border_w   = (string) get_option('leadstream_callbar_border_width', '1px');
    $callbar_radius     = (string) get_option('leadstream_callbar_radius', '999px');
    $dni_rules_text = (string) get_option('leadstream_dni_rules', "");
        
        // Get phone click stats with proper wpdb->prepare() usage
        global $wpdb;
    $total_phone_clicks = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE link_type = %s",
            'phone'
    ));
    $phone_clicks_today = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE link_type = %s AND DATE(clicked_at) = %s",
            'phone',
            current_time('Y-m-d')
    ));
    $phone_clicks_this_week = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE link_type = %s AND clicked_at >= %s",
            'phone',
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
        // This month (from first day of current month)
        $month_start = date('Y-m-01 00:00:00');
        $phone_clicks_this_month = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE link_type = %s AND clicked_at >= %s",
            'phone',
            $month_start
        ));
        
        // Sparkline data for phone clicks (last 14 days)
        $phone_sparkline_data = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $clicks = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE link_type = 'phone' AND DATE(clicked_at) = %s",
                $date
            ));
            $phone_sparkline_data[] = intval($clicks);
        }
        
        ?>
        <div class="leadstream-phone-tracking" style="max-width: 900px;">
            <h2>📞 Phone Click Tracking</h2>
            <p>Track clicks on phone numbers across your website. Monitor which numbers get the most calls and analyze user engagement patterns.</p>
            
            <!-- Unified Stats (always visible) -->
            
            <!-- Current Phone Numbers Info -->
            <?php if (!empty($phone_numbers)): ?>
            <div style="background: #f0f8f0; border: 1px solid #c6e1c6; color: #155724; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 10px 0; color: #155724;">✅ Currently Tracking <?php echo count($phone_numbers); ?> Phone Number<?php echo count($phone_numbers) === 1 ? '' : 's'; ?></h4>
                <div style="font-family: 'Courier New', monospace; background: #fff; padding: 10px; border-radius: 3px; border: 1px solid #ddd;">
                    <?php foreach ($phone_numbers as $num): ?>
                        <div style="padding: 2px 0;"><strong><?php echo esc_html($num); ?></strong> <span style="color: #666;">(normalized)</span></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php
            // Calls Outcomes (provider webhooks) with filters + CSV + pagination
            $calls_table = $wpdb->prefix . 'ls_calls';
            $calls_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $calls_table)) === $calls_table;
            if ($calls_exists):
                $c_from = isset($_GET['c_from']) ? sanitize_text_field($_GET['c_from']) : '';
                $c_to   = isset($_GET['c_to'])   ? sanitize_text_field($_GET['c_to'])   : '';
                $c_status = isset($_GET['c_status']) ? sanitize_text_field($_GET['c_status']) : '';
                $c_provider = isset($_GET['c_provider']) ? sanitize_text_field($_GET['c_provider']) : '';
                $c_fromnum = isset($_GET['c_fromnum']) ? sanitize_text_field($_GET['c_fromnum']) : '';
                $c_tonum   = isset($_GET['c_tonum'])   ? sanitize_text_field($_GET['c_tonum'])   : '';
                $c_group = isset($_GET['c_group']) ? sanitize_text_field($_GET['c_group']) : '';
                $c_pp = isset($_GET['c_pp']) ? max(10, min(200, intval($_GET['c_pp']))) : 25;
                $c_p  = isset($_GET['c_p'])  ? max(1, intval($_GET['c_p'])) : 1;
                $c_export = isset($_GET['c_export']) && $_GET['c_export'] === 'csv';

                $cw = ['1=1']; $cp = [];
                if ($c_from) { $cw[] = 'start_time >= %s'; $cp[] = $c_from . ' 00:00:00'; }
                if ($c_to)   { $cw[] = 'start_time <= %s'; $cp[] = $c_to   . ' 23:59:59'; }
                // Grouped status shortcuts
                $missed_group = ['no-answer','busy','failed','canceled','missed','no_answer'];
                $answered_group = ['completed','answered','ok','success'];
                if ($c_group === 'missed') {
                    $placeholders = implode(',', array_fill(0, count($missed_group), '%s'));
                    $cw[] = "status IN ($placeholders)"; $cp = array_merge($cp, $missed_group);
                } elseif ($c_group === 'answered') {
                    $placeholders = implode(',', array_fill(0, count($answered_group), '%s'));
                    $cw[] = "status IN ($placeholders)"; $cp = array_merge($cp, $answered_group);
                } elseif ($c_status) {
                    $cw[] = 'status = %s'; $cp[] = $c_status;
                }
                if ($c_provider) { $cw[] = 'provider = %s'; $cp[] = $c_provider; }
                if ($c_fromnum) { $cw[] = 'from_number = %s'; $cp[] = $c_fromnum; }
                if ($c_tonum)   { $cw[] = 'to_number = %s'; $cp[] = $c_tonum; }
                $cw_sql = implode(' AND ', $cw);

                if ($c_export && current_user_can('manage_options')) {
                    if (function_exists('ob_get_level')) { while (ob_get_level()) { ob_end_clean(); } }
                    $csv_sql = "SELECT start_time, end_time, duration, provider, status, from_number, to_number, recording_url FROM {$calls_table} WHERE {$cw_sql} ORDER BY start_time DESC LIMIT %d";
                    $rows = $wpdb->get_results($wpdb->prepare($csv_sql, array_merge($cp, [10000])), ARRAY_A);
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename=leadstream-call-outcomes.csv');
                    $out = fopen('php://output', 'w');
                    fputcsv($out, array_keys(reset($rows) ?: ['start_time','end_time','duration','provider','status','from_number','to_number','recording_url']));
                    foreach ($rows as $r) { fputcsv($out, $r); }
                    fclose($out); exit;
                }

                $c_count_sql = "SELECT COUNT(*) FROM {$calls_table} WHERE {$cw_sql}";
                $c_total = (int) $wpdb->get_var($wpdb->prepare($c_count_sql, $cp));
                $c_offset = ($c_p - 1) * $c_pp;
                $c_data_sql = "SELECT id, start_time, end_time, duration, provider, status, from_number, to_number, recording_url FROM {$calls_table} WHERE {$cw_sql} ORDER BY start_time DESC LIMIT %d OFFSET %d";
                $c_rows = $wpdb->get_results($wpdb->prepare($c_data_sql, array_merge($cp, [$c_pp, $c_offset])));

                $providers = $wpdb->get_col("SELECT DISTINCT provider FROM {$calls_table} ORDER BY provider");
                $statuses  = $wpdb->get_col("SELECT DISTINCT status FROM {$calls_table} ORDER BY status");
            ?>
            <button type="button" class="button button-secondary button-small ls-acc-toggle" data-acc="ls-call-outcomes" aria-controls="ls-call-outcomes" aria-expanded="true">📞 Call Outcomes</button>
            <div id="ls-call-outcomes" class="ls-acc-panel ls-call-outcomes" style="margin-top: 10px;">
                <h3 class="screen-reader-text">📞 Call Outcomes</h3>
                <form class="js-calls-filters" method="get" style="margin-bottom:12px; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:10px; align-items:end;">
                    <input type="hidden" name="page" value="leadstream-analytics-injector" />
                    <input type="hidden" name="tab" value="phone" />
                    <input type="hidden" name="c_group" value="<?php echo esc_attr($c_group); ?>" />
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">From</label>
                        <input type="date" name="c_from" value="<?php echo esc_attr($c_from); ?>" />
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">To</label>
                        <input type="date" name="c_to" value="<?php echo esc_attr($c_to); ?>" />
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">Provider</label>
                        <select name="c_provider">
                            <option value="">All</option>
                            <?php foreach ($providers as $pr): ?>
                                <option value="<?php echo esc_attr($pr); ?>" <?php selected($c_provider, $pr); ?>><?php echo esc_html($pr); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">Status</label>
                        <select name="c_status">
                            <option value="">All</option>
                            <?php foreach ($statuses as $st): ?>
                                <option value="<?php echo esc_attr($st); ?>" <?php selected($c_status, $st); ?>><?php echo esc_html($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">From number</label>
                        <input type="text" name="c_fromnum" value="<?php echo esc_attr($c_fromnum); ?>" placeholder="digits" />
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">To number</label>
                        <input type="text" name="c_tonum" value="<?php echo esc_attr($c_tonum); ?>" placeholder="digits" />
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">Per page</label>
                        <input type="number" name="c_pp" value="<?php echo esc_attr($c_pp); ?>" min="10" max="200" />
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="button button-primary" type="submit">Filter</button>
                        <a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'leadstream-analytics-injector','tab'=>'phone'], admin_url('admin.php'))); ?>">Reset</a>
                        <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['c_export'=>'csv']))); ?>">Export CSV</a>
                    </div>
                    <div class="ls-quick-chips" style="grid-column:1/-1; display:flex; gap:6px; flex-wrap:wrap; align-items:center; margin-top:4px;">
                        <span style="font-size:12px; color:#646970;">Quick:</span>
                        <button type="button" class="button ls-chip" data-chip="c_missed">Missed</button>
                        <button type="button" class="button ls-chip" data-chip="c_answered">Answered</button>
                        <button type="button" class="button ls-chip" data-chip="c_last7">Last 7 Days</button>
                    </div>
                </form>

                <div style="margin-bottom:8px; color:#646970; font-size:12px;">Showing <?php echo number_format(min($c_pp, max(0, $c_total - $c_offset))); ?> of <?php echo number_format($c_total); ?> result<?php echo $c_total==1?'':'s'; ?>.</div>

                <?php echo self::render_calls_fragment($c_rows, $c_total, $c_pp, $c_p); ?>
            </div>
            <?php endif; ?>
            <!-- Phone Tracking Stats (always visible, default 0s) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div style="text-align: center; padding: 15px; background: #f0f6fc; border-radius: 6px; border-left: 4px solid #2271b1;">
                    <div style="font-size: 24px; font-weight: 600; color: #1d2327; margin-bottom: 4px;">
                        <?php echo number_format($total_phone_clicks); ?>
                    </div>
                    <div style="font-size: 12px; color: #50575e; text-transform: uppercase; letter-spacing: 0.5px;">
                        Total Phone Clicks
                    </div>
                </div>
                
                <div style="text-align: center; padding: 15px; background: #f0f8f0; border-radius: 6px; border-left: 4px solid #00a32a;">
                    <div style="font-size: 24px; font-weight: 600; color: #1d2327; margin-bottom: 4px;">
                        <?php echo number_format($phone_clicks_this_week); ?>
                    </div>
                    <div style="font-size: 12px; color: #50575e; text-transform: uppercase; letter-spacing: 0.5px;">
                        This Week
                    </div>
                </div>
                
                <div style="text-align: center; padding: 15px; background: #fff8e1; border-radius: 6px; border-left: 4px solid #dba617;">
                    <div style="font-size: 24px; font-weight: 600; color: #1d2327; margin-bottom: 4px;">
                        <?php echo number_format($phone_clicks_today); ?>
                    </div>
                    <div style="font-size: 12px; color: #50575e; text-transform: uppercase; letter-spacing: 0.5px;">
                        Today
                    </div>
                </div>
                
                <div style="text-align: center; padding: 15px; background: #f3e8ff; border-radius: 6px; border-left: 4px solid #9333ea;">
                    <div style="font-size: 24px; font-weight: 600; color: #1d2327; margin-bottom: 4px;">
                        <?php echo number_format($phone_clicks_this_month); ?>
                    </div>
                    <div style="font-size: 12px; color: #50575e; text-transform: uppercase; letter-spacing: 0.5px;">
                        This Month
                    </div>
                </div>
            </div>
            
            <!-- Phone Activity (collapsible) -->
            <details open style="margin-bottom: 30px;">
                <summary style="cursor: pointer; list-style: none;">
                    <div style="display:flex; align-items:center; gap:8px; background:#fff; border:1px solid #dcdcde; border-radius:6px; padding:12px 15px;">
                        <span style="font-size:14px; color:#1d2327;">📊 Phone Activity Trend (14 Days)</span>
                        <?php 
                        $total_p = array_sum($phone_sparkline_data);
                        if ($total_p > 0) {
                            $first_week_p = array_sum(array_slice($phone_sparkline_data, 0, 7));
                            $second_week_p = array_sum(array_slice($phone_sparkline_data, 7, 7));
                            if ($second_week_p > $first_week_p) {
                                echo '<span style="color:#00a32a; font-size:12px;">📈 Trending Up</span>';
                            } elseif ($second_week_p < $first_week_p) {
                                echo '<span style="color:#d63638; font-size:12px;">📉 Trending Down</span>';
                            } else {
                                echo '<span style="color:#646970; font-size:12px;">➡️ Steady</span>';
                            }
                        } else {
                            echo '<span style="color:#646970; font-size:12px;">No recent data</span>';
                        }
                        ?>
                    </div>
                </summary>
                <div style="padding-top:10px;">
                    <?php echo self::render_widget_sparkline($phone_sparkline_data); ?>
                </div>
            </details>
            
            <form method="post" style="background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 6px;">
                <?php wp_nonce_field('leadstream_phone_settings', 'leadstream_phone_nonce'); ?>
                
                <!-- Enable/Disable Toggle -->
                <div style="margin-bottom: 25px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
                    <label style="display: flex; align-items: center; gap: 10px; font-weight: 600;">
                        <input type="checkbox" name="leadstream_phone_enabled" value="1" <?php checked($phone_enabled, 1); ?> />
                        Enable Phone Click Tracking
                    </label>
                    <p style="margin: 8px 0 0 0; color: #50575e; font-size: 13px;">
                        When enabled, all clicks on phone numbers will be tracked and sent to your analytics platforms.
                    </p>
                </div>
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="leadstream_default_country_code">Default Calling Country</label></th>
                            <td>
                                <?php self::country_code_field_callback(); ?>
                                <p class="description" style="margin-top:4px;">Used only for matching clicks. Your visible/dialed number stays exactly as you enter it (including any leading 0). This setting helps the tracker recognise both national and international forms.</p>
                            </td>
                        </tr>
                        <tr>
                            <th colspan="2"><h3 style="margin:8px 0;">📱 Mobile Sticky Call Bar</h3></th>
                        </tr>
                        <tr>
                            <th scope="row"><label>Call bar settings</label></th>
                            <td>
                                <details class="postbox" style="margin-top: 18px; padding:0; border:1px solid #96d636; background:#fff; border-radius:6px;">
                                    <summary class="hndle" style="padding:12px 16px;  background:#f6f7f7; color:#1d2327; font-weight:600; cursor:pointer; border-radius:6px 6px 0 0; display:flex; align-items:center; gap:8px;">
                                        <span class="dashicons dashicons-phone"></span>
                                        <span>Call bar settings Zone</span>
                                    </summary>
                                    <div class="inside" style="padding:16px;">
                                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;align-items:end;">
                                            <label style="display:flex;align-items:center;gap:10px;font-weight:600;">
                                                <input type="checkbox" id="leadstream_callbar_enabled" name="leadstream_callbar_enabled" value="1" <?php checked($callbar_enabled, 1); ?> />
                                                Enable Call Bar
                                            </label>
                                            <label style="display:flex;align-items:center;gap:10px;">
                                                <input type="checkbox" name="leadstream_callbar_mobile_only" value="1" <?php checked($callbar_mobile_only, 1); ?> />
                                                Mobile only
                                            </label>
                                            <label>Position<br/>
                                                <span style="display:flex;gap:12px;align-items:center;">
                                                    <label><input type="radio" name="leadstream_callbar_position" value="bottom" <?php checked($callbar_position, 'bottom'); ?> /> Bottom</label>
                                                    <label><input type="radio" name="leadstream_callbar_position" value="top" <?php checked($callbar_position, 'top'); ?> /> Top</label>
                                                </span>
                                            </label>
                                            <label>Alignment<br/>
                                                <span style="display:flex;gap:12px;align-items:center;">
                                                    <label title="Left aligns the button near the screen edge"><input type="radio" name="leadstream_callbar_align" value="left" <?php checked($callbar_align, 'left'); ?> /> Left</label>
                                                    <label title="Center keeps the button centered (default)"><input type="radio" name="leadstream_callbar_align" value="center" <?php checked($callbar_align, 'center'); ?> /> Center</label>
                                                    <label title="Right aligns the button near the screen edge"><input type="radio" name="leadstream_callbar_align" value="right" <?php checked($callbar_align, 'right'); ?> /> Right</label>
                                                </span>
                                            </label>
                                            <div style="grid-column:1 / -1; margin:4px 0 8px 0; color:#50575e; font-size:13px;">
                                                <p style="margin:0 0 6px 0;">You can place the Call Bar anywhere using the shortcode and choose the alignment (left, center, or right). Examples:</p>
                                                <div><code>[leadstream_callbar align="left"]</code></div>
                                                <div><code>[leadstream_callbar align="center"]</code></div>
                                                <div><code>[leadstream_callbar align="right"]</code></div>
                                            </div>
                                            <label>Default Phone Number<br/>
                                                <input id="leadstream_callbar_default" name="leadstream_callbar_default" type="tel" class="regular-text" style="width:260px" maxlength="24" value="<?php echo esc_attr($callbar_default); ?>" placeholder="(555) 123-4567" />
                                            </label>
                                            <label>CTA Text<br/>
                                                <input id="leadstream_callbar_cta" name="leadstream_callbar_cta" type="text" class="regular-text" style="width:260px" maxlength="60" value="<?php echo esc_attr($callbar_cta); ?>" placeholder="Call Now" />
                                            </label>
                                        </div>
                                        <hr style="margin:16px 0; border:none; border-top:1px solid #e5e5e5;" />
                                        <div style="margin-bottom:8px; display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                                            <style>
                                                .ls-swatch { display:inline-block; width:12px; height:12px; border:1px solid #c3c4c7; border-radius:2px; vertical-align:middle; margin-right:6px; }
                                                .ls-leg { display:inline-flex; align-items:center; gap:6px; padding:2px 8px; border:1px solid #dcdcde; border-radius:999px; background:#fff; font-size:12px; color:#1d2327; }
                                            </style>
                                            <span class="ls-leg"><span class="ls-swatch" style="background:<?php echo esc_attr($callbar_bg); ?>"></span>Bar BG</span>
                                            <span class="ls-leg"><span class="ls-swatch" style="background:<?php echo esc_attr($callbar_btn_bg); ?>"></span>Button BG</span>
                                            <span class="ls-leg"><span class="ls-swatch" style="background:<?php echo esc_attr($callbar_btn_text); ?>"></span>Button Text</span>
                                            <span class="ls-leg"><span class="ls-swatch" style="background:<?php echo esc_attr($callbar_hover_bg); ?>"></span>Hover BG</span>
                                            <span class="ls-leg"><span class="ls-swatch" style="background:<?php echo esc_attr($callbar_hover_text); ?>"></span>Hover Text</span>
                                            <span class="ls-leg"><span class="ls-swatch" style="background:<?php echo esc_attr($callbar_border); ?>"></span>Border</span>
                                        </div>
                                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                                            <label><span class="ls-swatch" style="background:<?php echo esc_attr($callbar_bg); ?>"></span> Bar Background <span class="ls-help" title="Background color of the sticky bar behind the button.">?</span><br/>
                                                <input type="text" class="small-text ls-color" style="width:96px" name="leadstream_callbar_bg" value="<?php echo esc_attr($callbar_bg); ?>" title="Bar background" />
                                            </label>
                                            <label><span class="ls-swatch" style="background:<?php echo esc_attr($callbar_btn_bg); ?>"></span> Button Background <span class="ls-help" title="Main background color of the call button.">?</span><br/>
                                                <input type="text" class="small-text ls-color" style="width:96px" name="leadstream_callbar_btn_bg" value="<?php echo esc_attr($callbar_btn_bg); ?>" title="Button background" />
                                            </label>
                                            <label><span class="ls-swatch" style="background:<?php echo esc_attr($callbar_btn_text); ?>"></span> Button Text <span class="ls-help" title="Text color on the call button.">?</span><br/>
                                                <input type="text" class="small-text ls-color" style="width:96px" name="leadstream_callbar_btn_text" value="<?php echo esc_attr($callbar_btn_text); ?>" title="Button text color" />
                                            </label>
                                            <label><span class="ls-swatch" style="background:<?php echo esc_attr($callbar_hover_bg); ?>"></span> Hover Background <span class="ls-help" title="Button background color when hovered.">?</span><br/>
                                                <input type="text" class="small-text ls-color" style="width:96px" name="leadstream_callbar_hover_bg" value="<?php echo esc_attr($callbar_hover_bg); ?>" title="Hover background" />
                                            </label>
                                            <label><span class="ls-swatch" style="background:<?php echo esc_attr($callbar_hover_text); ?>"></span> Hover Text <span class="ls-help" title="Button text color when hovered.">?</span><br/>
            									<input type="text" class="small-text ls-color" style="width:96px" name="leadstream_callbar_hover_text" value="<?php echo esc_attr($callbar_hover_text); ?>" title="Hover text color" />
                                            </label>
                                            <label><span class="ls-swatch" style="background:<?php echo esc_attr($callbar_border); ?>"></span> Border Color <span class="ls-help" title="Outline color of the call button.">?</span><br/>
                                                <input type="text" class="small-text ls-color" style="width:96px" name="leadstream_callbar_border" value="<?php echo esc_attr($callbar_border); ?>" title="Button border color" />
                                            </label>
                                            <label>Border Width (px)<br/>
                                                <input type="number" min="0" max="4" step="1" class="small-text" name="leadstream_callbar_border_width" value="<?php echo esc_attr(preg_replace('/[^0-9]/', '', $callbar_border_w)); ?>" />
                                                <span class="description">0–4px</span>
                                            </label>
                                            <label>Border Radius<br/>
                                                <input type="text" class="regular-text" style="width:120px" name="leadstream_callbar_radius" value="<?php echo esc_attr($callbar_radius); ?>" placeholder="e.g. 999px" />
                                            </label>
                                            <label>Font Size<br/>
                                                <input type="text" class="regular-text" style="width:96px" name="leadstream_callbar_font_size" value="<?php echo esc_attr($callbar_font_size); ?>" placeholder="1rem" />
                                            </label>
                                        </div>
                                        <p class="description" style="margin-top:8px;">Colors accept hex (e.g., #ffce00). Font size clamps to 1.0–1.6rem (or 16–26px). Border width clamps 0–4px.</p>
                                        <div style="margin-top:14px">
                                            <div class="ls-callbar ls-callbar--bottom ls-callbar--align-<?php echo esc_attr(in_array($callbar_align,['left','center','right'],true)?$callbar_align:'center'); ?>" style="position:static;display:flex;justify-content:<?php echo $callbar_align==='left'?'flex-start':($callbar_align==='right'?'flex-end':'center'); ?>;background:<?php echo esc_attr($callbar_bg); ?>;padding:10px 12px">
                                                <a class="ls-callbar__btn" style="background:<?php echo esc_attr($callbar_btn_bg); ?>;color:<?php echo esc_attr($callbar_btn_text); ?>;border:<?php echo esc_attr($callbar_border_w); ?> solid <?php echo esc_attr($callbar_border); ?>;font-size:<?php echo esc_attr($callbar_font_size); ?>;padding:10px 16px;border-radius:<?php echo esc_attr($callbar_radius); ?>;text-decoration:none;display:inline-flex;gap:10px;align-items:center;cursor:pointer">
                                                    <span class="ls-callbar__cta"><?php echo esc_html($callbar_cta ?: 'Call Now'); ?></span>
                                                    <span class="ls-callbar__num"><?php echo esc_html($callbar_default ?: '(555) 123-4567'); ?></span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </details>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="leadstream_dni_rules">Dynamic Number Insertion (DNI)</label></th>
                            <td>
                                <textarea id="leadstream_dni_rules" name="leadstream_dni_rules" rows="4" class="large-text" placeholder="utm_source=google => (555) 111-2222&#10;ref=facebook.com => +1 555-333-4444&#10;path=/landing/california => 555-777-8888&#10;campaign=spring-sale => 555-999-0000"><?php echo esc_textarea($dni_rules_text); ?></textarea>
                                <p class="description">
                                    One rule per line. Supported patterns: <code>utm_source=VALUE</code>, <code>ref=DOMAIN</code>, <code>path=/partial</code>, or any URL/referrer substring. Use <code>=> NUMBER</code> to assign the phone.<br>
                                    Example: <code>utm_source=google =&gt; 1-800-555-1234</code>. All numbers here are auto-added to your tracked list on save.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="leadstream_phone_numbers">Phone Numbers to Track</label>
                            </th>
                            <td>
                                <textarea id="leadstream_phone_numbers" 
                                          name="leadstream_phone_numbers" 
                                          rows="4" 
                                          class="large-text" 
                                          placeholder="(555) 123-4567&#10;+1-555-123-4568&#10;555.123.4569"><?php echo esc_textarea(implode("\n", $phone_numbers)); ?></textarea>
                                <p class="description">
                                    <strong>Enter your main phone numbers, one per line.</strong><br>
                                    Any format works: (555) 123-4567, +1-555-123-4567, 555.123.4567, etc.<br>
                                    <em>Numbers are automatically normalized and deduplicated when saved.</em><br>
                                    These numbers will be automatically detected in <code>tel:</code> links across your site.
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="leadstream_phone_selectors">Custom CSS Selectors <em>(Optional)</em></label>
                            </th>
                            <td>
                                <textarea id="leadstream_phone_selectors" 
                                          name="leadstream_phone_selectors" 
                                          rows="4" 
                                          class="large-text" 
                                          placeholder=".phone-button&#10;#call-now-btn&#10;.contact-phone a&#10;[data-phone]"><?php echo esc_textarea($css_selectors); ?></textarea>
                                <p class="description">
                                    <strong>Advanced:</strong> Track clicks on custom phone elements (one CSS selector per line).<br>
                                    Examples: <code>.phone-button</code>, <code>#call-now-btn</code>, <code>.contact-phone a</code><br>
                                    Useful for tracking custom phone buttons, click-to-call widgets, or styled phone elements.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="leadstream_phone_recording_url">Recording URL <em>(Optional)</em></label>
                            </th>
                            <td>
                                <input type="url" id="leadstream_phone_recording_url" name="leadstream_phone_recording_url" value="<?php echo esc_attr($recording_url); ?>" class="regular-text" placeholder="https://youtu.be/... or https://example.com/demo.mp4" />
                                <p class="description">
                                    Paste a link to a short screen recording explaining how Phone Tracking works. YouTube, Vimeo, or direct MP4 supported.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #dcdcde;">
                    <button type="submit" name="leadstream_phone_submit" class="button button-primary button-large">
                        💾 Save Phone Tracking Settings
                    </button>
                </div>
            </form>

            <?php
            // Missed calls (from provider webhooks)
            global $wpdb;
            $calls_table = $wpdb->prefix . 'ls_calls';
            $calls_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $calls_table)) === $calls_table;
            if ($calls_exists):
                $missed_statuses = ['no-answer','busy','failed','canceled','missed'];
                $placeholders = implode(',', array_fill(0, count($missed_statuses), '%s'));
                $recent_missed = $wpdb->get_results($wpdb->prepare(
                    "SELECT start_time, from_number, to_number, status, recording_url FROM {$calls_table} WHERE status IN ($placeholders) ORDER BY start_time DESC LIMIT 20",
                    $missed_statuses
                ));
            ?>
            <button type="button" class="button button-secondary button-small ls-acc-toggle" data-acc="ls-missed-calls" aria-controls="ls-missed-calls" aria-expanded="true">🚫 Missed Calls (Webhook - requires provider integration)</button>
            <div id="ls-missed-calls" class="ls-acc-panel" style="margin-top: 10px;">
                <h3 class="screen-reader-text">🚫 Missed Calls</h3>
                <?php if (empty($recent_missed)): ?>
                    <div style="padding:12px; color:#646970;">No data. This section requires a phone provider integration (e.g., Twilio, RingCentral) posting to the webhook endpoint. Planned for a future phase.</div>
                <?php else: ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="18%">When</th>
                            <th width="22%">From</th>
                            <th width="22%">To</th>
                            <th width="16%">Status</th>
                            <th>Recording</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_missed as $row): ?>
                        <tr>
                            <td><?php echo $row->start_time ? esc_html(date_i18n('M j, Y g:i A', strtotime($row->start_time))) : '<span style="color:#787c82;">—</span>'; ?></td>
                            <td><code><?php echo esc_html($row->from_number ?: ''); ?></code></td>
                            <td><code><?php echo esc_html($row->to_number ?: ''); ?></code></td>
                            <td><?php echo esc_html($row->status ?: ''); ?></td>
                            <td>
                                <?php if (!empty($row->recording_url)): ?>
                                    <a class="button button-small" href="<?php echo esc_url($row->recording_url); ?>" target="_blank" rel="noopener">Listen</a>
                                <?php else: ?>
                                    <span style="color:#787c82;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                <div style="margin-top:10px; color:#50575e;">
                    <strong>Webhook Endpoint:</strong>
                    <?php $endpoint = rest_url('leadstream/v1/calls'); ?>
                    <code><?php echo esc_html($endpoint); ?></code>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- How It Works Section -->
            <button type="button" class="button button-secondary button-small ls-acc-toggle" data-acc="ls-how-it-works" aria-controls="ls-how-it-works" aria-expanded="true">🔧 How Phone Tracking Works</button>
            <div id="ls-how-it-works" class="ls-acc-panel" style="margin-top: 10px; background: #f9f9f9; padding: 20px; border-radius: 6px; border-left: 4px solid #72aee6;">
                <h3 class="screen-reader-text">🔧 How Phone Tracking Works</h3>

                <?php if (!empty($recording_url)):
                    $embed_html = '';
                    $url = esc_url($recording_url);
                    $host = wp_parse_url($recording_url, PHP_URL_HOST);
                    if (is_string($host)) { $host = strtolower($host); }
                    // YouTube
                    if ($host && (strpos($host, 'youtube.com') !== false || strpos($host, 'youtu.be') !== false)) {
                        $vid = '';
                        if (strpos($host, 'youtu.be') !== false) {
                            // https://youtu.be/VIDEOID
                            $path = trim((string) wp_parse_url($recording_url, PHP_URL_PATH), '/');
                            $vid = $path;
                        } else {
                            // https://www.youtube.com/watch?v=VIDEOID
                            parse_str((string) wp_parse_url($recording_url, PHP_URL_QUERY), $qs);
                            $vid = isset($qs['v']) ? $qs['v'] : '';
                        }
                        if ($vid) {
                            $embed_html = '<div class="ls-video-wrapper"><iframe width="560" height="315" src="https://www.youtube.com/embed/' . esc_attr($vid) . '" title="How Phone Tracking Works" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture;" allowfullscreen></iframe></div>';
                        }
                    }
                    // Vimeo
                    if (!$embed_html && $host && strpos($host, 'vimeo.com') !== false) {
                        $path = trim((string) wp_parse_url($recording_url, PHP_URL_PATH), '/');
                        if ($path) {
                            $embed_html = '<div class="ls-video-wrapper"><iframe src="https://player.vimeo.com/video/' . esc_attr($path) . '" width="640" height="360" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>';
                        }
                    }
                    // Direct MP4
                    if (!$embed_html && preg_match('/\.mp4($|\?)/i', $recording_url)) {
                        $embed_html = '<div class="ls-video-wrapper"><video controls preload="metadata"><source src="' . $url . '" type="video/mp4" />Your browser does not support the video tag.</video></div>';
                    }
                    if ($embed_html) {
                        echo $embed_html; 
                    } else {
                        echo '<p style="margin-top:0;">Watch the quick recording: <a class="button" href="' . $url . '" target="_blank" rel="noopener">Open Recording</a></p>';
                    }
                else: ?>
                    <p style="margin-top:0;">Add a short recording URL above to embed a quick walkthrough here.</p>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 16px;">
                    <div>
                        <h4 style="color: #2271b1; margin-bottom: 8px;">📱 Automatic Detection</h4>
                        <ul style="margin: 0; color: #50575e; font-size: 14px; line-height: 1.5;">
                            <li>Scans all <code>&lt;a href="tel:..."&gt;</code> links</li>
                            <li>Matches against your configured phone numbers</li>
                            <li>Works with any phone number format</li>
                            <li>No code changes required</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 style="color: #2271b1; margin-bottom: 8px;">📊 Analytics Integration</h4>
                        <ul style="margin: 0; color: #50575e; font-size: 14px; line-height: 1.5;">
                            <li>Sends events to Google Analytics (GA4)</li>
                            <li>Stores click data in WordPress database</li>
                            <li>Shows stats in your LeadStream dashboard</li>
                            <li>Tracks timestamps and user context</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 style="color: #2271b1; margin-bottom: 8px;">🎯 Custom Elements</h4>
                        <ul style="margin: 0; color: #50575e; font-size: 14px; line-height: 1.5;">
                            <li>Track custom phone buttons and widgets</li>
                            <li>Use CSS selectors for precise targeting</li>
                            <li>Perfect for styled call-to-action buttons</li>
                            <li>Works with page builders and themes</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Per-page quick control -->
            <div class="ls-btn-row" style="display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin: 10px 0 0 0;">
                <form method="get" style="margin-left:auto; display:flex; gap:8px; align-items:center;">
                    <input type="hidden" name="page" value="leadstream-analytics-injector" />
                    <input type="hidden" name="tab" value="phone" />
                    <label style="font-size:12px; color:#646970;">Per page
                        <select name="pp" onchange="this.form.submit()">
                            <?php $__pp_cur = isset($_GET['pp']) ? intval($_GET['pp']) : 25; ?>
                            <option value="10" <?php selected($__pp_cur,10); ?>>10</option>
                            <option value="25" <?php selected($__pp_cur,25); ?>>25</option>
                            <option value="50" <?php selected($__pp_cur,50); ?>>50</option>
                            <option value="100" <?php selected($__pp_cur,100); ?>>100</option>
                        </select>
                    </label>
                </form>
            </div>

            <!-- Phone Click History -->
            <?php
            // Respect global per-page setting for Recent as well
            $pp_recent = isset($_GET['pp']) ? max(10, min(200, intval($_GET['pp']))) : 25;
            $recent_phone_clicks = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    link_key as phone_number,
                    clicked_at,
                    click_date,
                    click_time,
                    ip_address,
                    referrer,
                    page_url,
                    page_title,
                    element_type,
                    element_class,
                    element_id,
                    user_agent
                 FROM {$wpdb->prefix}ls_clicks 
                 WHERE link_type = %s 
                 ORDER BY clicked_at DESC 
                 LIMIT %d",
                'phone',
                $pp_recent
            ));
            
            if (!empty($recent_phone_clicks)): ?>
            <button type="button" class="button button-secondary button-small ls-acc-toggle" data-acc="ls-recent" aria-controls="ls-recent" aria-expanded="true">📞 Recent Phone Clicks</button>
            <div id="ls-recent" class="ls-acc-panel" style="margin-top: 10px;">
                <h3 class="screen-reader-text">📞 Recent Phone Clicks</h3>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="12%">Date</th>
                            <th width="10%">Time</th>
                            <th width="16%">Phone</th>
                            <th>Page</th>
                            <th width="14%">Source</th>
                            <th width="12%">IP</th>
                            <th width="12%">Referrer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_phone_clicks as $click): ?>
                        <?php 
                            // Prefer split date/time if present, else derive from clicked_at
                            $when_ts = strtotime($click->clicked_at);
                            $date_str = !empty($click->click_date) ? esc_html(date_i18n('M j, Y', strtotime($click->click_date))) : esc_html(date_i18n('M j, Y', $when_ts));
                            $time_str = !empty($click->click_time) ? esc_html(date_i18n('g:i A', strtotime($click->click_time))) : esc_html(date_i18n('g:i A', $when_ts));
                            $source_bits = [];
                            if (!empty($click->element_type)) { $source_bits[] = $click->element_type; }
                            if (!empty($click->element_id)) { $source_bits[] = '#' . $click->element_id; }
                            if (!empty($click->element_class)) { $source_bits[] = '.' . preg_replace('/\s+/', '.', $click->element_class); }
                            $source = !empty($source_bits) ? implode(' ', $source_bits) : 'unknown';
                            $ref_host = '';
                            if (!empty($click->referrer)) { $p = wp_parse_url($click->referrer); $ref_host = $p['host'] ?? $click->referrer; }
                        ?>
                        <tr>
                            <td><?php echo $date_str; ?></td>
                            <td><?php echo $time_str; ?></td>
                            <td><strong><?php echo esc_html($click->phone_number); ?></strong></td>
                            <td>
                                <?php if (!empty($click->page_url)): ?>
                                    <a href="<?php echo esc_url($click->page_url); ?>" target="_blank" title="<?php echo esc_attr($click->page_title ?: $click->page_url); ?>">
                                        <?php echo esc_html($click->page_title ?: $click->page_url); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:#646970;">(no page)</span>
                                <?php endif; ?>
                                <br><small style="color:#787c82;"><?php echo esc_html(human_time_diff($when_ts, current_time('timestamp')) . ' ago'); ?></small>
                            </td>
                            <td><code><?php echo esc_html($source); ?></code></td>
                            <td><code><?php echo esc_html($click->ip_address ?: ''); ?></code></td>
                            <td><?php echo esc_html($ref_host ?: ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Phone Number Analytics Table -->
            <?php if ($total_phone_clicks > 0): 
                // Get phone number stats grouped by number
                $phone_analytics = $wpdb->get_results($wpdb->prepare(
                    "SELECT 
                        link_key as phone_number,
                        COUNT(*) as total_calls,
                        COUNT(CASE WHEN DATE(clicked_at) = %s THEN 1 END) as today_calls,
                        MAX(clicked_at) as last_click
                     FROM {$wpdb->prefix}ls_clicks 
                     WHERE link_type = %s 
                     GROUP BY link_key 
                     ORDER BY total_calls DESC",
                    current_time('Y-m-d'),
                    'phone'
                ));
            ?>
            <button type="button" class="button button-secondary button-small ls-acc-toggle" data-acc="ls-performance" aria-controls="ls-performance" aria-expanded="true">📊 Phone Number Performance</button>
            <div id="ls-performance" class="ls-acc-panel" style="margin-top: 10px;">
                <h3 class="screen-reader-text">📊 Phone Number Performance</h3>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="25%">Phone Number</th>
                            <th width="15%">Total Calls</th>
                            <th width="15%">Today's Calls</th>
                            <th width="25%">Last Click</th>
                            <th width="20%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($phone_analytics as $phone_stat): ?>
                        <tr>
                            <td><strong><?php echo esc_html($phone_stat->phone_number); ?></strong></td>
                            <td>
                                <span style="background: #0073aa; color: white; padding: 3px 8px; border-radius: 3px; font-weight: 600;">
                                    <?php echo number_format($phone_stat->total_calls); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($phone_stat->today_calls > 0): ?>
                                <span style="background: #00a32a; color: white; padding: 3px 8px; border-radius: 3px; font-weight: 600;">
                                    <?php echo number_format($phone_stat->today_calls); ?>
                                </span>
                                <?php else: ?>
                                <span style="color: #787c82;">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($phone_stat->last_click): ?>
                                    <?php echo esc_html(date_i18n('M j, Y g:i A', strtotime($phone_stat->last_click))); ?>
                                    <br><small style="color: #787c82;">
                                        <?php echo esc_html(human_time_diff(strtotime($phone_stat->last_click), current_time('timestamp')) . ' ago'); ?>
                                    </small>
                                <?php else: ?>
                                    <span style="color: #787c82;">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="tel:<?php echo esc_attr($phone_stat->phone_number); ?>" 
                                   class="button button-small" 
                                   style="text-decoration: none; margin-right: 5px;">
                                    📞 Test Call
                                </a>
                                <button type="button" 
                                        class="button button-small" 
                                        onclick="prompt('Google Analytics Phone Event:', 'gtag(\'event\', \'phone_click\', {\'phone_number\': \'<?php echo esc_js($phone_stat->phone_number); ?>\'})')">
                                    📊 GA4 Event
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- All Phone Calls (Filters + CSV) -->
            <?php
            // Only render if table exists
            $table = $wpdb->prefix . 'ls_clicks';
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
            if ($table_exists):
                // Gather filters (GET so it doesn't conflict with settings POST)
                $from_date = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
                $to_date = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : '';
                $phone_filter = isset($_GET['phone']) ? sanitize_text_field($_GET['phone']) : '';
                $page_q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
                $elem_q = isset($_GET['elem']) ? sanitize_text_field($_GET['elem']) : '';
                $per_page = isset($_GET['pp']) ? max(10, min(200, intval($_GET['pp']))) : 25;
                $paged = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
                $do_export = isset($_GET['export']) && $_GET['export'] === 'csv';

                // Build WHERE conditions safely
                $where = ["link_type = %s"]; $params = ['phone'];
                if ($phone_filter !== '') { $where[] = "link_key = %s"; $params[] = $phone_filter; }
                if ($from_date !== '') { $where[] = "clicked_at >= %s"; $params[] = $from_date . ' 00:00:00'; }
                if ($to_date !== '') { $where[] = "clicked_at <= %s"; $params[] = $to_date . ' 23:59:59'; }
                if ($page_q !== '') {
                    $like = '%' . $wpdb->esc_like($page_q) . '%';
                    $where[] = "(page_title LIKE %s OR page_url LIKE %s)"; $params[] = $like; $params[] = $like;
                }
                if ($elem_q !== '') {
                    $elike = '%' . $wpdb->esc_like($elem_q) . '%';
                    $where[] = "(element_type LIKE %s OR element_id LIKE %s OR element_class LIKE %s)";
                    $params[] = $elike; $params[] = $elike; $params[] = $elike;
                }
                $where_sql = implode(' AND ', $where);

                // CSV Export
                if ($do_export && current_user_can('manage_options')) {
                    $csv_sql = "SELECT click_date, click_time, link_key as phone, page_title, page_url, element_type, element_id, element_class, ip_address, referrer, clicked_at FROM {$table} WHERE {$where_sql} ORDER BY clicked_at DESC LIMIT %d";
                    $csv_params = array_merge($params, [5000]);
                    $rows = $wpdb->get_results($wpdb->prepare($csv_sql, $csv_params), ARRAY_A);
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename=leadstream-phone-calls.csv');
                    $out = fopen('php://output', 'w');
                    fputcsv($out, array_keys(reset($rows) ?: [
                        'click_date','click_time','phone','page_title','page_url','element_type','element_id','element_class','ip_address','referrer','clicked_at'
                    ]));
                    foreach ($rows as $r) { fputcsv($out, $r); }
                    fclose($out);
                    exit;
                }

                // Total count for pagination
                $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
                $total_count = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
                $offset = ($paged - 1) * $per_page;

                // Fetch page results
                $data_sql = "SELECT 
                        link_key as phone_number,
                        clicked_at, click_date, click_time,
                        page_title, page_url,
                        element_type, element_id, element_class,
                        ip_address, referrer
                    FROM {$table}
                    WHERE {$where_sql}
                    ORDER BY clicked_at DESC
                    LIMIT %d OFFSET %d";
                $data_params = array_merge($params, [$per_page, $offset]);
                $all_calls = $wpdb->get_results($wpdb->prepare($data_sql, $data_params));

                // Distinct numbers for dropdown
                $numbers = $wpdb->get_col("SELECT DISTINCT link_key FROM {$table} WHERE link_type = 'phone' ORDER BY link_key ASC");
            ?>
            <button type="button" class="button button-secondary button-small ls-acc-toggle" data-acc="ls-all-calls" aria-controls="ls-all-calls" aria-expanded="true">📒 All Phone Calls</button>
            <div id="ls-all-calls" class="ls-acc-panel ls-phone-calls" style="margin-top: 10px;">
                <h3 class="screen-reader-text">📒 All Phone Calls</h3>
                <form class="js-phone-filters" method="get" style="margin-bottom: 12px; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:10px; align-items:end;">
                    <input type="hidden" name="page" value="leadstream-analytics-injector" />
                    <input type="hidden" name="tab" value="phone" />
                    <div>
                        <label for="ls_from" style="display:block; font-size:12px; color:#646970;">From</label>
                        <input id="ls_from" type="date" name="from" value="<?php echo esc_attr($from_date); ?>" class="regular-text" />
                    </div>
                    <div>
                        <label for="ls_to" style="display:block; font-size:12px; color:#646970;">To</label>
                        <input id="ls_to" type="date" name="to" value="<?php echo esc_attr($to_date); ?>" class="regular-text" />
                    </div>
                    <div>
                        <label for="ls_phone" style="display:block; font-size:12px; color:#646970;">Phone</label>
                        <select id="ls_phone" name="phone" class="regular-text">
                            <option value="">All</option>
                            <?php foreach ($numbers as $num): ?>
                                <option value="<?php echo esc_attr($num); ?>" <?php selected($phone_filter, $num); ?>><?php echo esc_html($num); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="ls_q" style="display:block; font-size:12px; color:#646970;">Page contains</label>
                        <input id="ls_q" type="text" name="q" value="<?php echo esc_attr($page_q); ?>" placeholder="/contact, Title..." class="regular-text" />
                    </div>
                    <div>
                        <label for="ls_elem" style="display:block; font-size:12px; color:#646970;">Element contains</label>
                        <input id="ls_elem" type="text" name="elem" value="<?php echo esc_attr($elem_q); ?>" placeholder="a, #call-now, .btn" class="regular-text" />
                    </div>
                    <div>
                        <label for="ls_pp" style="display:block; font-size:12px; color:#646970;">Per page</label>
                        <input id="ls_pp" type="number" name="pp" value="<?php echo esc_attr($per_page); ?>" min="10" max="200" />
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="button button-primary" type="submit">Filter</button>
                        <a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'leadstream-analytics-injector','tab'=>'phone'], admin_url('admin.php'))); ?>">Reset</a>
                        <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['export'=>'csv']))); ?>">Export CSV</a>
                    </div>
                    <div class="ls-quick-chips" style="grid-column:1/-1; display:flex; gap:6px; flex-wrap:wrap; align-items:center; margin-top:4px;">
                        <span style="font-size:12px; color:#646970;">Quick:</span>
                        <button type="button" class="button ls-chip" data-chip="p_today">Today</button>
                        <button type="button" class="button ls-chip" data-chip="p_last7">Last 7 Days</button>
                        <button type="button" class="button ls-chip" data-chip="p_month">This Month</button>
                    </div>
                </form>

                <div style="margin-bottom:8px; color:#646970; font-size:12px;">Showing <?php echo number_format(min($per_page, max(0, $total_count - $offset))); ?> of <?php echo number_format($total_count); ?> result<?php echo $total_count==1?'':'s'; ?>.</div>

                <?php echo self::render_phone_calls_fragment($all_calls, $total_count, $per_page, $paged); ?>
            </div>
            <?php endif; // table_exists ?>
        </div>
        
        <!-- FAQ & Tips (collapsible) -->
        <details class="ls-acc" id="ls-phone-faq" style="margin-top: 24px;">
            <summary style="padding:12px 16px; border:1px solid #dcdcde; border-radius:6px; background:#f6f7f7; color:#1d2327; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-editor-help" style="font-size:18px;"></span>
                <span>Phone Tracking FAQ & Tips</span>
            </summary>
            <div style="padding:14px 16px; border:1px solid #dcdcde; border-top:none; border-radius:0 0 6px 6px; background:#fff;">
                <details open style="margin-bottom:10px;">
                    <summary style="font-weight:600; cursor:pointer;">How do I add phone numbers to track?</summary>
                    <div style="margin-top:8px; color:#50575e;">
                        Enter one phone number per line in the “Phone Numbers to Track” box. Any format is fine (e.g. (555) 123-4567, +1-555-123-4567). We normalize and deduplicate automatically when you save.
                    </div>
                </details>
                <details style="margin-bottom:10px;">
                    <summary style="font-weight:600; cursor:pointer;">What if a number isn’t being tracked?</summary>
                    <div style="margin-top:8px; color:#50575e;">
                        Make sure the number appears exactly on the page (or in a tel: link). We match by digits after normalization, so “(555) 123-4567” and “5551234567” are equivalent.
                        Also confirm the feature is enabled and clear any caching that might block updated scripts.
                    </div>
                </details>
                <details style="margin-bottom:10px;">
                    <summary style="font-weight:600; cursor:pointer;">How do custom selectors work?</summary>
                    <div style="margin-top:8px; color:#50575e;">
                        If your site uses stylized buttons or widgets instead of tel: links, add CSS selectors (one per line). We’ll bind click tracking to those elements as well.
                    </div>
                </details>
                <details style="margin-bottom:10px;">
                    <summary style="font-weight:600; cursor:pointer;">Why do I see no data?</summary>
                    <div style="margin-top:8px; color:#50575e;">
                        New installs start at zero. Use your site to click the phone links, or for local demos go to the plugin’s Test Data injector to generate sample calls. The stat cards always render and will display zeros until clicks occur.
                    </div>
                </details>
                <details>
                    <summary style="font-weight:600; cursor:pointer;">How do I use the filters and export?</summary>
                    <div style="margin-top:8px; color:#50575e;">
                        Use From/To for date ranges, select a specific phone number, or search by page title/URL or element (like a, #call-now, .btn). Adjust Per Page to control list size. Click Export CSV to download the current view.
                    </div>
                </details>

                <hr style="margin:14px 0; border:none; border-top:1px solid #f0f0f1;" />
                <details style="margin-bottom:10px;">
                    <summary style="font-weight:600; cursor:pointer;">How do I enable and customize the Mobile Sticky Call Bar?</summary>
                    <div style="margin-top:8px; color:#50575e;">
                        In Phone Click Tracking, toggle “Enable Call Bar”, choose Mobile only, pick Position (Top/Bottom) and Alignment (Left/Center/Right), and set CTA text and Default Phone. The live preview reflects your colors, font size, border, and radius.
                    </div>
                </details>
                <details style="margin-bottom:10px;">
                    <summary style="font-weight:600; cursor:pointer;">Shortcode vs auto‑inject — which one shows?</summary>
                    <div style="margin-top:8px; color:#50575e;">
                        When enabled, the bar is auto‑injected at the top or bottom site‑wide based on your Position setting. If you place the shortcode <code>[leadstream_callbar]</code> on a page, auto‑inject is suppressed on that page to avoid duplicates. Shortcode attributes: <code>align="left|center|right"</code>, <code>position="top|bottom"</code>, <code>cta="Tap to Call"</code>, <code>class="extra-classes"</code>. Example: <code>[leadstream_callbar align="right" position="top" cta="Tap to Call"]</code>.
                    </div>
                </details>
                <details style="margin-bottom:10px;">
                    <summary style="font-weight:600; cursor:pointer;">What data is captured on each phone click?</summary>
                    <div style="margin-top:8px; color:#50575e;">
                        We log to the <code>ls_clicks</code> table with <em>link_type=phone</em>, normalized digits, original formatted number, timestamps (date/time), IP, user agent, referrer, WordPress user ID (if logged in), and context like element type/class/id and page title in metadata. If GA4 is present, we also emit a <code>phone_click</code> event.
                    </div>
                </details>
                <details style="margin-bottom:10px;">
                    <summary style="font-weight:600; cursor:pointer;">Does Dynamic Number Insertion (DNI) change the Call Bar number?</summary>
                    <div style="margin-top:8px; color:#50575e;">
                        No. DNI rules help match and track numbers shown in your content and tel: links. The Call Bar uses the Default Phone you configure in settings (and respects your CTA/position/alignment). Use the shortcode if you need page‑level placement control.
                    </div>
                </details>
                <details style="margin-bottom:10px;">
                    <summary style="font-weight:600; cursor:pointer;">How do I write DNI rules?</summary>
                    <div style="margin-top:8px; color:#50575e;">
                        One rule per line using patterns like <code>utm_source=google =&gt; 1-800-555-1234</code>, <code>ref=facebook.com =&gt; (555) 222-3333</code>, or <code>path=/landing/california =&gt; 555-777-8888</code>. We support <code>utm_*</code>, <code>ref</code>, <code>path</code>, and free‑text URL/referrer substrings. Numbers in rules are auto‑added to your tracked list on save.
                    </div>
                </details>
                <details style="margin-bottom:10px;">
                    <summary style="font-weight:600; cursor:pointer;">Can I make QR codes for my short links?</summary>
                    <div style="margin-top:8px; color:#50575e;">
                        Yes. In the Pretty Links dashboard, click the QR button on any row to open a modal with pro‑grade controls: 512/1024 px output, error correction (L/M/Q/H), transparent background, brand colors, quiet‑zone padding, and logo overlay (use site icon or upload). Download PNG, pop out, or copy the URL.
                    </div>
                </details>
                <details>
                    <summary style="font-weight:600; cursor:pointer;">Can I record missed/ended calls from providers?</summary>
                    <div style="margin-top:8px; color:#50575e;">
                        Not by default. Today, LeadStream records call clicks (tap-to-call) for analytics. Missed/ended call logs require a provider integration (e.g., Twilio, RingCentral) to post to a prepared endpoint: <code>/wp-json/leadstream/v1/calls</code>. This integration is planned for a future phase; until then, the “Missed Calls (Webhook)” panel will remain empty.
                    </div>
                </details>
            </div>
        </details>

        <?php if (current_user_can('manage_options')): ?>
        <!-- Import: Phone Clicks (separate card) -->
        <div style="margin-top: 18px; padding:16px; border:1px solid #ccd0d4; background:#fff; border-radius:6px;">
            <h3 style="margin:0 0 6px 0;">Import: Phone Clicks</h3>
            <p style="margin:6px 0 12px 0; color:#50575e;">Upload a CSV backup of phone clicks to restore. Headers should match the ls_clicks columns. Only rows with link_type=phone will be imported.</p>
            <form method="post" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
                <?php wp_nonce_field('ls_import_phone_clicks','ls_import_phone_clicks_nonce'); ?>
                <div>
                    <label>CSV File<br><input type="file" name="ls_import_file" accept=".csv" required></label>
                </div>
                <label style="display:flex; align-items:center; gap:6px;">
                    <input type="checkbox" name="ls_import_truncate" value="1"> Truncate existing phone clicks before import
                </label>
                <button type="submit" name="ls_import_phone_clicks" value="1" class="button">Import CSV</button>
            </form>
        </div>

        <!-- Danger Zone: Flush Phone Clicks -->
        <details class="ls-acc" id="ls-dz-phone" style="margin-top:18px;">
            <summary style="padding:12px 16px; border:1px solid #d63638; border-radius:6px; background:#fff5f5; color:#b32d2e; font-weight:600; cursor:pointer;">Danger Zone: Flush Phone Clicks</summary>
            <div style="padding:16px; border:1px solid #d63638; border-top:none; background:#fff5f5; border-radius:0 0 6px 6px;">
            <p style="margin:6px 0 12px 0; color:#8a1f1f;">
                This permanently deletes all tracked Phone Clicks from your database. Use only to resolve data issues or reduce database size. This cannot be undone.
            </p>
            <form method="get" style="margin:6px 0 12px 0; display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
                <input type="hidden" name="page" value="leadstream-analytics-injector" />
                <input type="hidden" name="tab" value="phone" />
                <input type="hidden" name="dz_export" value="phone_clicks" />
                <input type="hidden" name="ls_dz_export" value="<?php echo esc_attr(wp_create_nonce('ls_dz_export')); ?>" />
                <label>From<br><input type="date" name="dz_from" /></label>
                <label>To<br><input type="date" name="dz_to" /></label>
                <label style="display:flex; align-items:center; gap:6px;">
                    <input type="checkbox" name="excel" value="1" /> Open in Excel
                </label>
                <button class="button button-secondary" type="submit" name="fmt" value="csv">Export CSV</button>
                <button class="button" type="submit" name="fmt" value="json">Export JSON</button>
                <span style="margin-left:8px; color:#8a1f1f;">Download a filtered backup before deleting.</span>
            </form>
            <label style="display:flex; align-items:center; gap:8px; margin:8px 0; color:#8a1f1f;">
                <input type="checkbox" id="ls-confirm-phone-flush" />
                <span>I understand this will permanently remove all phone click records from the database.</span>
            </label>
            <form method="post" onsubmit="return (document.getElementById('ls-confirm-phone-flush')?.checked && confirm('This will permanently delete all Phone Click rows. Are you sure?'));">
                <?php wp_nonce_field('ls_flush_phone','ls_flush_phone_nonce'); ?>
                <button type="submit" name="ls_flush_phone_clicks" value="1" class="button" style="background:#d63638; border-color:#d63638; color:#fff;" disabled id="ls-btn-phone-flush">
                    Delete All Phone Clicks
                </button>
            </form>
            <div style="margin-top:10px;">
                <form method="post" onsubmit="return window.LSConfirmRange && LSConfirmRange(this, 'phone')" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
                    <?php wp_nonce_field('ls_delete_phone_clicks_range','ls_delete_phone_clicks_range_nonce'); ?>
                    <label>Delete Phone Clicks From<br><input type="date" name="dz_from" required /></label>
                    <label>To<br><input type="date" name="dz_to" required /></label>
                    <button type="submit" name="ls_delete_phone_clicks_range" value="1" class="button" style="background:#f0b429; border-color:#f0b429; color:#1d2327;">Delete Phone Clicks in Range</button>
                </form>
            </div>
            </div>
        </details>
        <?php endif; ?>
        <?php
    }

    // Reusable fragment renderer for All Phone Calls table and pagination
    private static function render_phone_calls_fragment($rows, $total_count, $per_page, $paged) {
        ob_start();
        $offset = ($paged - 1) * $per_page;
        ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th width="12%">Date</th>
                    <th width="10%">Time</th>
                    <th width="16%">Phone</th>
                    <th>Page</th>
                    <th width="14%">Source</th>
                    <th width="12%">IP</th>
                    <th width="12%">Referrer</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" style="text-align:center; color:#646970;">No calls found for the selected filters.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                <?php 
                    $ts = strtotime($row->clicked_at);
                    $date_str = !empty($row->click_date) ? esc_html(date_i18n('M j, Y', strtotime($row->click_date))) : esc_html(date_i18n('M j, Y', $ts));
                    $time_str = !empty($row->click_time) ? esc_html(date_i18n('g:i A', strtotime($row->click_time))) : esc_html(date_i18n('g:i A', $ts));
                    $bits = [];
                    if (!empty($row->element_type)) $bits[] = $row->element_type;
                    if (!empty($row->element_id)) $bits[] = '#' . $row->element_id;
                    if (!empty($row->element_class)) $bits[] = '.' . preg_replace('/\s+/', '.', $row->element_class);
                    $src = !empty($bits) ? implode(' ', $bits) : 'unknown';
                    $ref_host = '';
                    if (!empty($row->referrer)) { $p = wp_parse_url($row->referrer); $ref_host = $p['host'] ?? $row->referrer; }
                ?>
                <tr>
                    <td><?php echo $date_str; ?></td>
                    <td><?php echo $time_str; ?></td>
                    <td><strong><?php echo esc_html($row->phone_number); ?></strong></td>
                    <td>
                        <?php if (!empty($row->page_url)): ?>
                            <a href="<?php echo esc_url($row->page_url); ?>" target="_blank" title="<?php echo esc_attr($row->page_title ?: $row->page_url); ?>">
                                <?php echo esc_html($row->page_title ?: $row->page_url); ?>
                            </a>
                        <?php else: ?>
                            <span style="color:#646970;">(no page)</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?php echo esc_html($src); ?></code></td>
                    <td><code><?php echo esc_html($row->ip_address ?: ''); ?></code></td>
                    <td><?php echo esc_html($ref_host ?: ''); ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php 
        // Pagination with First/Prev/Next/Last and numeric pages
        $total_pages = max(1, ceil($total_count / $per_page));
        if ($total_pages > 1):
            $start_num = $offset + 1;
            $end_num = min($offset + $per_page, $total_count);
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo '<span class="displaying-num">' . intval($start_num) . '–' . intval($end_num) . ' of ' . intval($total_count) . '</span>';
            echo '<span class="pagination-links">';
            $keep = array_intersect_key($_GET, array_flip(['from','to','phone','q','elem','pp']));
            $base_args = array_merge(['page'=>'leadstream-analytics-injector','tab'=>'phone'], $keep);
            $first_url = add_query_arg(array_merge($base_args, ['p'=>1]), admin_url('admin.php'));
            $prev_page = max(1, $paged - 1);
            $prev_url  = add_query_arg(array_merge($base_args, ['p'=>$prev_page]), admin_url('admin.php'));
            $next_page = min($total_pages, $paged + 1);
            $next_url  = add_query_arg(array_merge($base_args, ['p'=>$next_page]), admin_url('admin.php'));
            $last_url  = add_query_arg(array_merge($base_args, ['p'=>$total_pages]), admin_url('admin.php'));

            if ($paged > 1) {
                echo '<a class="page-numbers first-page js-paginate" data-args=' . esc_attr(wp_json_encode(array_merge($keep, ['p'=>1]))) . ' href="' . esc_url($first_url) . '"><span class="screen-reader-text">First page</span></a>';
                echo '<a class="page-numbers prev-page js-paginate" data-args=' . esc_attr(wp_json_encode(array_merge($keep, ['p'=>$prev_page]))) . ' href="' . esc_url($prev_url) . '"><span class="screen-reader-text">Previous page</span></a>';
            } else {
                echo '<span class="tablenav-pages-navspan button disabled first-page" aria-hidden="true">&nbsp;</span>';
                echo '<span class="tablenav-pages-navspan button disabled prev-page" aria-hidden="true">&nbsp;</span>';
            }

            // Numeric pages
            for ($i=1; $i<=$total_pages; $i++) {
                $url = add_query_arg(array_merge($base_args, ['p'=>$i]), admin_url('admin.php'));
                if ($i == $paged) {
                    echo '<span class="page-numbers current" aria-current="page">' . intval($i) . '</span>';
                } else {
                    echo '<a class="page-numbers js-paginate" data-args=' . esc_attr(wp_json_encode(array_merge($keep, ['p'=>$i]))) . ' href="' . esc_url($url) . '">' . intval($i) . '</a>';
                }
            }

            if ($paged < $total_pages) {
                echo '<a class="page-numbers next-page js-paginate" data-args=' . esc_attr(wp_json_encode(array_merge($keep, ['p'=>$next_page]))) . ' href="' . esc_url($next_url) . '"><span class="screen-reader-text">Next page</span></a>';
                echo '<a class="page-numbers last-page js-paginate" data-args=' . esc_attr(wp_json_encode(array_merge($keep, ['p'=>$total_pages]))) . ' href="' . esc_url($last_url) . '"><span class="screen-reader-text">Last page</span></a>';
            } else {
                echo '<span class="tablenav-pages-navspan button disabled next-page" aria-hidden="true">&nbsp;</span>';
                echo '<span class="tablenav-pages-navspan button disabled last-page" aria-hidden="true">&nbsp;</span>';
            }
            echo '</span></div></div>';
        endif; 
        ?>
        <?php
        return ob_get_clean();
    }

    // Reusable fragment renderer for Calls Outcomes (wp_ls_calls)
    private static function render_calls_fragment($rows, $total_count, $per_page, $paged) {
        ob_start();
        ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th width="18%">Start</th>
                    <th width="10%">Duration</th>
                    <th width="12%">Provider</th>
                    <th width="12%">Status</th>
                    <th width="18%">From</th>
                    <th width="18%">To</th>
                    <th>Recording</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr><td colspan="7" style="text-align:center; color:#646970;">No calls found for the selected filters.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo $r->start_time ? esc_html(date_i18n('M j, Y g:i A', strtotime($r->start_time))) : '<span style="color:#787c82;">—</span>'; ?></td>
                    <td><?php echo $r->duration ? esc_html($r->duration . 's') : '<span style="color:#787c82;">—</span>'; ?></td>
                    <td><?php echo esc_html($r->provider ?: ''); ?></td>
                    <td><?php echo esc_html($r->status ?: ''); ?></td>
                    <td><code><?php echo esc_html($r->from_number ?: ''); ?></code></td>
                    <td><code><?php echo esc_html($r->to_number ?: ''); ?></code></td>
                    <td>
                        <?php if (!empty($r->recording_url)): ?>
                            <a class="button button-small" href="<?php echo esc_url($r->recording_url); ?>" target="_blank" rel="noopener">Listen</a>
                        <?php else: ?>
                            <span style="color:#787c82;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php $total_pages = max(1, ceil($total_count / $per_page));
        if ($total_pages > 1):
            $start_num = ($paged - 1) * $per_page + 1;
            $end_num = min($paged * $per_page, $total_count);
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo '<span class="displaying-num">' . intval($start_num) . '–' . intval($end_num) . ' of ' . intval($total_count) . '</span>';
            echo '<span class="pagination-links">';
            $ckeep = array_intersect_key($_GET, array_flip(['c_from','c_to','c_status','c_provider','c_fromnum','c_tonum','c_group','c_pp']));
            $cbase_args = array_merge(['page'=>'leadstream-analytics-injector','tab'=>'phone'], $ckeep);
            $first_url = add_query_arg(array_merge($cbase_args, ['c_p'=>1]), admin_url('admin.php'));
            $prev_page = max(1, $paged - 1);
            $prev_url  = add_query_arg(array_merge($cbase_args, ['c_p'=>$prev_page]), admin_url('admin.php'));
            $next_page = min($total_pages, $paged + 1);
            $next_url  = add_query_arg(array_merge($cbase_args, ['c_p'=>$next_page]), admin_url('admin.php'));
            $last_url  = add_query_arg(array_merge($cbase_args, ['c_p'=>$total_pages]), admin_url('admin.php'));

            if ($paged > 1) {
                echo '<a class="page-numbers first-page js-paginate" data-args=' . esc_attr(wp_json_encode(array_merge($ckeep, ['c_p'=>1]))) . ' href="' . esc_url($first_url) . '"><span class="screen-reader-text">First page</span></a>';
                echo '<a class="page-numbers prev-page js-paginate" data-args=' . esc_attr(wp_json_encode(array_merge($ckeep, ['c_p'=>$prev_page]))) . ' href="' . esc_url($prev_url) . '"><span class="screen-reader-text">Previous page</span></a>';
            } else {
                echo '<span class="tablenav-pages-navspan button disabled first-page" aria-hidden="true">&nbsp;</span>';
                echo '<span class="tablenav-pages-navspan button disabled prev-page" aria-hidden="true">&nbsp;</span>';
            }

            for ($i=1; $i<=$total_pages; $i++) {
                $url = add_query_arg(array_merge($cbase_args, ['c_p'=>$i]), admin_url('admin.php'));
                if ($i == $paged) {
                    echo '<span class="page-numbers current" aria-current="page">' . intval($i) . '</span>';
                } else {
                    echo '<a class="page-numbers js-paginate" data-args=' . esc_attr(wp_json_encode(array_merge($ckeep, ['c_p'=>$i]))) . ' href="' . esc_url($url) . '">' . intval($i) . '</a>';
                }
            }

            if ($paged < $total_pages) {
                echo '<a class="page-numbers next-page js-paginate" data-args=' . esc_attr(wp_json_encode(array_merge($ckeep, ['c_p'=>$next_page]))) . ' href="' . esc_url($next_url) . '"><span class="screen-reader-text">Next page</span></a>';
                echo '<a class="page-numbers last-page js-paginate" data-args=' . esc_attr(wp_json_encode(array_merge($ckeep, ['c_p'=>$total_pages]))) . ' href="' . esc_url($last_url) . '"><span class="screen-reader-text">Last page</span></a>';
            } else {
                echo '<span class="tablenav-pages-navspan button disabled next-page" aria-hidden="true">&nbsp;</span>';
                echo '<span class="tablenav-pages-navspan button disabled last-page" aria-hidden="true">&nbsp;</span>';
            }
            echo '</span></div></div>';
        endif; ?>
        <?php
        return ob_get_clean();
    }

    // AJAX: return Calls Outcomes fragment
    public static function ajax_calls_table() {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message'=>'forbidden'], 403); }
        check_ajax_referer('ls-admin','nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'ls_calls';
        $from = isset($_REQUEST['c_from']) ? sanitize_text_field(wp_unslash($_REQUEST['c_from'])) : '';
        $to   = isset($_REQUEST['c_to'])   ? sanitize_text_field(wp_unslash($_REQUEST['c_to']))   : '';
        $status = isset($_REQUEST['c_status']) ? sanitize_text_field(wp_unslash($_REQUEST['c_status'])) : '';
        $provider = isset($_REQUEST['c_provider']) ? sanitize_text_field(wp_unslash($_REQUEST['c_provider'])) : '';
        $fromnum = isset($_REQUEST['c_fromnum']) ? sanitize_text_field(wp_unslash($_REQUEST['c_fromnum'])) : '';
        $tonum   = isset($_REQUEST['c_tonum'])   ? sanitize_text_field(wp_unslash($_REQUEST['c_tonum']))   : '';
    $per_page = isset($_REQUEST['c_pp']) ? max(10, min(200, intval($_REQUEST['c_pp']))) : 25;
        $paged = isset($_REQUEST['c_p']) ? max(1, intval($_REQUEST['c_p'])) : 1;
    $group = isset($_REQUEST['c_group']) ? sanitize_text_field(wp_unslash($_REQUEST['c_group'])) : '';

        $w = ['1=1']; $p = [];
        if ($from)    { $w[] = 'start_time >= %s'; $p[] = $from . ' 00:00:00'; }
        if ($to)      { $w[] = 'start_time <= %s'; $p[] = $to   . ' 23:59:59'; }
        $missed_group = ['no-answer','busy','failed','canceled','missed','no_answer'];
        $answered_group = ['completed','answered','ok','success'];
        if ($group === 'missed') {
            $ph = implode(',', array_fill(0, count($missed_group), '%s'));
            $w[] = "status IN ($ph)"; $p = array_merge($p, $missed_group);
        } elseif ($group === 'answered') {
            $ph = implode(',', array_fill(0, count($answered_group), '%s'));
            $w[] = "status IN ($ph)"; $p = array_merge($p, $answered_group);
        } elseif ($status)  { $w[] = 'status = %s'; $p[] = $status; }
        if ($provider){ $w[] = 'provider = %s'; $p[] = $provider; }
        if ($fromnum) { $w[] = 'from_number = %s'; $p[] = $fromnum; }
        if ($tonum)   { $w[] = 'to_number = %s'; $p[] = $tonum; }
        $where_sql = implode(' AND ', $w);

        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $p));
        $offset = ($paged - 1) * $per_page;
        $data_sql = "SELECT id, start_time, end_time, duration, provider, status, from_number, to_number, recording_url FROM {$table} WHERE {$where_sql} ORDER BY start_time DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($data_sql, array_merge($p, [$per_page, $offset])));

        $html = self::render_calls_fragment($rows, $total, $per_page, $paged);
    $url = add_query_arg(array_merge([
            'page' => 'leadstream-analytics-injector', 'tab' => 'phone'
    ], array_intersect_key($_REQUEST, array_flip(['c_from','c_to','c_status','c_provider','c_fromnum','c_tonum','c_group','c_pp','c_p']))), admin_url('admin.php'));
        wp_send_json_success(['html'=>$html, 'url'=>$url]);
    }
    // AJAX: return All Phone Calls fragment
    public static function ajax_phone_table() {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message'=>'forbidden'], 403); }
        check_ajax_referer('ls-admin','nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'ls_clicks';
        $from_date = isset($_REQUEST['from']) ? sanitize_text_field(wp_unslash($_REQUEST['from'])) : '';
        $to_date = isset($_REQUEST['to']) ? sanitize_text_field(wp_unslash($_REQUEST['to'])) : '';
        $phone_filter = isset($_REQUEST['phone']) ? sanitize_text_field(wp_unslash($_REQUEST['phone'])) : '';
        $page_q = isset($_REQUEST['q']) ? sanitize_text_field(wp_unslash($_REQUEST['q'])) : '';
        $elem_q = isset($_REQUEST['elem']) ? sanitize_text_field(wp_unslash($_REQUEST['elem'])) : '';
        $per_page = isset($_REQUEST['pp']) ? max(10, min(200, intval($_REQUEST['pp']))) : 25;
        $paged = isset($_REQUEST['p']) ? max(1, intval($_REQUEST['p'])) : 1;

        $where = ["link_type = %s"]; $params = ['phone'];
        if ($phone_filter !== '') { $where[] = "link_key = %s"; $params[] = $phone_filter; }
        if ($from_date !== '') { $where[] = "clicked_at >= %s"; $params[] = $from_date . ' 00:00:00'; }
        if ($to_date !== '') { $where[] = "clicked_at <= %s"; $params[] = $to_date . ' 23:59:59'; }
        if ($page_q !== '') { $like = '%' . $wpdb->esc_like($page_q) . '%'; $where[] = "(page_title LIKE %s OR page_url LIKE %s)"; $params[] = $like; $params[] = $like; }
        if ($elem_q !== '') { $elike = '%' . $wpdb->esc_like($elem_q) . '%'; $where[] = "(element_type LIKE %s OR element_id LIKE %s OR element_class LIKE %s)"; $params[] = $elike; $params[] = $elike; $params[] = $elike; }
        $where_sql = implode(' AND ', $where);

        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total_count = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
        $offset = ($paged - 1) * $per_page;

        $data_sql = "SELECT link_key as phone_number, clicked_at, click_date, click_time, page_title, page_url, element_type, element_id, element_class, ip_address, referrer FROM {$table} WHERE {$where_sql} ORDER BY clicked_at DESC LIMIT %d OFFSET %d";
        $data_params = array_merge($params, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($data_sql, $data_params));

        $html = self::render_phone_calls_fragment($rows, $total_count, $per_page, $paged);
        $url = add_query_arg(array_merge([
            'page' => 'leadstream-analytics-injector', 'tab' => 'phone'
        ], array_intersect_key($_REQUEST, array_flip(['from','to','phone','q','elem','pp','p']))), admin_url('admin.php'));
        wp_send_json_success(['html'=>$html, 'url'=>$url]);
    }

    /**
     * Render Pretty Links tab
     */
    private static function render_links_tab() {
    // Export handled earlier in display_settings_page() to avoid accidental output before headers
        ?>
        <div class="leadstream-pretty-links">
            <h2>🎯 Pretty Links Dashboard</h2>
            <p>Create, manage, and track short links with detailed click analytics.</p>
            
            <div style="background: #f0f8ff; padding: 20px; border-left: 4px solid #0073aa; margin: 20px 0;">
                <h3 style="margin-top: 0;">� Feature Status: Active!</h3>
                <p>The Pretty Links feature is <strong>fully functional</strong> and includes:</p>
                <ul>
                    <li><strong>✅ Database Tables:</strong> Custom <code>ls_links</code> and <code>ls_clicks</code> tables created</li>
                    <li><strong>✅ URL Rewriting:</strong> Clean <code>/l/slug</code> URLs with 301 redirects</li>
                    <li><strong>✅ Click Tracking:</strong> Full analytics with IP, user agent, and timestamps</li>
                    <li><strong>✅ Management Interface:</strong> WordPress-native admin dashboard</li>
                </ul>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="<?php echo admin_url('admin.php?page=leadstream-links'); ?>" class="button button-primary button-large">
                    🔗 Open Links Manager
                </a>
                <a href="<?php echo admin_url('admin.php?page=leadstream-links&action=add'); ?>" class="button button-secondary button-large">
                    ➕ Add New Link
                </a>
            </div>

            <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">
                <h4 style="margin-top: 0;">� How to Use</h4>
                <ol>
                    <li><strong>Create a Link:</strong> Click "Add New Link" and enter your slug and target URL</li>
                    <li><strong>Share:</strong> Use the generated <code>/l/your-slug</code> URL in campaigns</li>
                    <li><strong>Track:</strong> View click analytics and performance in the Links Manager</li>
                    <li><strong>Optimize:</strong> Use click data to improve your marketing campaigns</li>
                </ol>
            </div>

            <div style="background: #d1ecf1; padding: 15px; border-left: 4px solid #bee5eb; margin: 20px 0;">
                <h4 style="margin-top: 0;">⚡ Performance Benefits</h4>
                <ul>
                    <li><strong>Lightning Fast:</strong> Direct database lookups, no CPT overhead</li>
                    <li><strong>Scalable:</strong> Handles thousands of clicks without performance issues</li>
                    <li><strong>SEO Friendly:</strong> Proper 301 redirects maintain link juice</li>
                    <li><strong>Analytics Ready:</strong> Detailed click tracking for campaign optimization</li>
                </ul>
            </div>

            <?php
            // All Link Clicks reporting (filters + CSV)
            global $wpdb;
            $table_c = $wpdb->prefix . 'ls_clicks';
            $table_l = $wpdb->prefix . 'ls_links';
            $table_exists_c = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_c)) === $table_c;
            $table_exists_l = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_l)) === $table_l;
            if ($table_exists_c && $table_exists_l):
                // Filters (GET)
                $from = isset($_GET['l_from']) ? sanitize_text_field($_GET['l_from']) : '';
                // Links Directory (searchable + filterable)
                $ld_from = isset($_GET['ld_from']) ? sanitize_text_field($_GET['ld_from']) : '';
                $ld_to   = isset($_GET['ld_to'])   ? sanitize_text_field($_GET['ld_to'])   : '';
                $ld_rt   = isset($_GET['ld_rt'])   ? sanitize_text_field($_GET['ld_rt'])   : '';
                $ld_q    = isset($_GET['ld_q'])    ? sanitize_text_field($_GET['ld_q'])    : '';
                $ld_pp   = isset($_GET['ld_pp'])   ? max(10, min(200, intval($_GET['ld_pp']))) : 25;
                $ld_p    = isset($_GET['ld_p'])    ? max(1, intval($_GET['ld_p'])) : 1;
                $ld_export = isset($_GET['ld_export']) && $_GET['ld_export'] === 'csv';

                $ld_where = ['1=1']; $ld_params = [];
                if ($ld_from) { $ld_where[] = 'l.created_at >= %s'; $ld_params[] = $ld_from . ' 00:00:00'; }
                if ($ld_to)   { $ld_where[] = 'l.created_at <= %s'; $ld_params[] = $ld_to   . ' 23:59:59'; }
                if (in_array($ld_rt, ['301','302','307','308'], true)) { $ld_where[] = 'l.redirect_type = %s'; $ld_params[] = $ld_rt; }
                if ($ld_q) {
                    $like = '%' . $wpdb->esc_like($ld_q) . '%';
                    $ld_where[] = '(l.slug LIKE %s OR l.target_url LIKE %s)';
                    $ld_params[] = $like; $ld_params[] = $like;
                }
                $ld_where_sql = implode(' AND ', $ld_where);

                if ($ld_export && current_user_can('manage_options')) {
                    // Ensure no prior output breaks CSV headers.
                    if (function_exists('ob_get_level')) { while (ob_get_level()) { ob_end_clean(); } }
                    $csv_sql = "SELECT l.slug, l.target_url, l.redirect_type, l.created_at,
                                    (SELECT COUNT(*) FROM {$table_c} c2 WHERE c2.link_id = l.id AND c2.link_type='link') as total_clicks,
                                    (SELECT MAX(clicked_at) FROM {$table_c} c3 WHERE c3.link_id = l.id AND c3.link_type='link') as last_click
                                 FROM {$table_l} l
                                 WHERE {$ld_where_sql}
                                 ORDER BY l.created_at DESC
                                 LIMIT %d";
                    $rows = $wpdb->get_results($wpdb->prepare($csv_sql, array_merge($ld_params, [10000])), ARRAY_A);
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename=leadstream-links-directory.csv');
                    $out = fopen('php://output', 'w');
                    fputcsv($out, array_keys(reset($rows) ?: ['slug','target_url','redirect_type','created_at','total_clicks','last_click']));
                    foreach ($rows as $r) { fputcsv($out, $r); }
                    fclose($out); exit;
                }

                $ld_count_sql = "SELECT COUNT(*) FROM {$table_l} l WHERE {$ld_where_sql}";
                $ld_total = (int) $wpdb->get_var($wpdb->prepare($ld_count_sql, $ld_params));
                $ld_offset = ($ld_p - 1) * $ld_pp;

                $ld_data_sql = "SELECT l.id, l.slug, l.target_url, l.redirect_type, l.created_at,
                                    (SELECT COUNT(*) FROM {$table_c} c2 WHERE c2.link_id = l.id AND c2.link_type='link') as total_clicks,
                                    (SELECT MAX(clicked_at) FROM {$table_c} c3 WHERE c3.link_id = l.id AND c3.link_type='link') as last_click
                                 FROM {$table_l} l
                                 WHERE {$ld_where_sql}
                                 ORDER BY l.created_at DESC
                                 LIMIT %d OFFSET %d";
                $ld_rows = $wpdb->get_results($wpdb->prepare($ld_data_sql, array_merge($ld_params, [$ld_pp, $ld_offset])));
                $ld_total_pages = max(1, ceil($ld_total / $ld_pp));

            ?>
            <div id="ls-links-dir" style="margin-top: 20px;">
                <h3>📚 Links Directory</h3>
                <form method="get" style="margin-bottom: 12px; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:10px; align-items:end;">
                    <input type="hidden" name="page" value="leadstream-analytics-injector" />
                    <input type="hidden" name="tab" value="links" />
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">Created from</label>
                        <input type="date" name="ld_from" value="<?php echo esc_attr($ld_from); ?>" />
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">Created to</label>
                        <input type="date" name="ld_to" value="<?php echo esc_attr($ld_to); ?>" />
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">Redirect type</label>
                        <select name="ld_rt">
                            <?php $rts = ['', '301','302','307','308']; $labels = ['All','301','302','307','308'];
                                  foreach ($rts as $i => $rt): $val = $rt; $text = $labels[$i]; ?>
                                <option value="<?php echo esc_attr($val); ?>" <?php selected($ld_rt, $val); ?>><?php echo esc_html($text ?: 'All'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">Search</label>
                        <input type="text" name="ld_q" value="<?php echo esc_attr($ld_q); ?>" placeholder="slug or target URL" />
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">Per page</label>
                        <input type="number" name="ld_pp" value="<?php echo esc_attr($ld_pp); ?>" min="10" max="200" />
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="button button-primary" type="submit">Filter</button>
                        <a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'leadstream-analytics-injector','tab'=>'links'], admin_url('admin.php'))); ?>">Reset</a>
                        <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['ld_export'=>'csv']))); ?>">Export CSV</a>
                    </div>
                </form>

                <div style="margin-bottom:8px; color:#646970; font-size:12px;">Showing <?php echo number_format(min($ld_pp, max(0, $ld_total - $ld_offset))); ?> of <?php echo number_format($ld_total); ?> link<?php echo $ld_total==1?'':'s'; ?>.</div>

                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="18%">Slug</th>
                            <th>Target URL</th>
                            <th width="10%">Redirect</th>
                            <th width="14%">Created</th>
                            <th width="10%">Clicks</th>
                            <th width="16%">Last Click</th>
                            <th width="16%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ld_rows)): ?>
                            <tr><td colspan="7" style="text-align:center; color:#646970;">No links found for the selected filters.</td></tr>
                        <?php else: foreach ($ld_rows as $row): ?>
                        <?php $short = home_url('/l/' . $row->slug); $ts = $row->last_click ? strtotime($row->last_click) : 0; ?>
                        <tr>
                            <td><a href="<?php echo esc_url($short); ?>" target="_blank">/l/<?php echo esc_html($row->slug); ?></a></td>
                            <td><a href="<?php echo esc_url($row->target_url); ?>" target="_blank" title="<?php echo esc_attr($row->target_url); ?>"><?php echo esc_html(wp_trim_words($row->target_url, 10, '…')); ?></a></td>
                            <td><code><?php echo esc_html($row->redirect_type ?: '301'); ?></code></td>
                            <td><?php echo esc_html(date_i18n('M j, Y', strtotime($row->created_at))); ?></td>
                            <td><strong><?php echo number_format((int)$row->total_clicks); ?></strong></td>
                            <td><?php echo $ts ? esc_html(date_i18n('M j, Y g:i A', $ts)) : '<span style="color:#646970;">—</span>'; ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=leadstream-analytics-injector&tab=links&action=edit&id=' . intval($row->id))); ?>" class="button button-small">Edit</a>
                                <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($short); ?>'); this.innerText='Copied'; setTimeout(()=>this.innerText='Copy',1200);">Copy</button>
                                <a href="<?php echo esc_url($short); ?>" target="_blank" class="button button-small">Test</a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <?php if ($ld_total_pages > 1):
                    echo '<div class="tablenav"><div class="tablenav-pages">';
                    for ($i=1; $i<=$ld_total_pages; $i++) {
                        $url = add_query_arg(array_merge($_GET, ['ld_p'=>$i]));
                        $style = $i==$ld_p ? 'font-weight:600;' : '';
                        echo '<a class="page-numbers" style="margin-right:6px; ' . esc_attr($style) . '" href="' . esc_url($url) . '">' . intval($i) . '</a>';
                    }
                    echo '</div></div>';
                endif; ?>
            </div>
            <?php
                $to = isset($_GET['l_to']) ? sanitize_text_field($_GET['l_to']) : '';
                $slug = isset($_GET['l_slug']) ? sanitize_title($_GET['l_slug']) : '';
                $page_q = isset($_GET['l_q']) ? sanitize_text_field($_GET['l_q']) : '';
                $per_page = isset($_GET['l_pp']) ? max(10, min(200, intval($_GET['l_pp']))) : 50;
                $paged = isset($_GET['l_p']) ? max(1, intval($_GET['l_p'])) : 1;
                $export = isset($_GET['l_export']) && $_GET['l_export'] === 'csv';

                $where = ["c.link_type = 'link'"]; $params = [];
                if ($from) { $where[] = 'c.clicked_at >= %s'; $params[] = $from . ' 00:00:00'; }
                if ($to) { $where[] = 'c.clicked_at <= %s'; $params[] = $to . ' 23:59:59'; }
                if ($slug) { $where[] = 'l.slug = %s'; $params[] = $slug; }
                if ($page_q) { $like = '%' . $wpdb->esc_like($page_q) . '%'; $where[] = '(c.page_title LIKE %s OR c.page_url LIKE %s)'; $params[] = $like; $params[] = $like; }
                $where_sql = implode(' AND ', $where);

                if ($export && current_user_can('manage_options')) {
                    // Ensure no prior output breaks CSV headers.
                    if (function_exists('ob_get_level')) { while (ob_get_level()) { ob_end_clean(); } }
                    $csv_sql = "SELECT c.click_date, c.click_time, l.slug, l.target_url, c.page_title, c.page_url, c.ip_address, c.referrer, c.clicked_at
                                FROM {$table_c} c LEFT JOIN {$table_l} l ON c.link_id = l.id
                                WHERE {$where_sql} ORDER BY c.clicked_at DESC LIMIT %d";
                    $rows = $wpdb->get_results($wpdb->prepare($csv_sql, array_merge($params, [10000])), ARRAY_A);
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename=leadstream-link-clicks.csv');
                    $out = fopen('php://output', 'w');
                    fputcsv($out, array_keys(reset($rows) ?: ['click_date','click_time','slug','target_url','page_title','page_url','ip_address','referrer','clicked_at']));
                    foreach ($rows as $r) { fputcsv($out, $r); }
                    fclose($out); exit;
                }

                $count_sql = "SELECT COUNT(*) FROM {$table_c} c LEFT JOIN {$table_l} l ON c.link_id = l.id WHERE {$where_sql}";
                $total_count = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
                $offset = ($paged - 1) * $per_page;

                $data_sql = "SELECT c.click_date, c.click_time, c.clicked_at, l.slug, l.target_url, c.page_title, c.page_url, c.ip_address, c.referrer
                             FROM {$table_c} c LEFT JOIN {$table_l} l ON c.link_id = l.id
                             WHERE {$where_sql}
                             ORDER BY c.clicked_at DESC
                             LIMIT %d OFFSET %d";
                $rows = $wpdb->get_results($wpdb->prepare($data_sql, array_merge($params, [$per_page, $offset])));
                $slugs = $wpdb->get_col("SELECT slug FROM {$table_l} ORDER BY created_at DESC");
            ?>
            <div style="margin-top: 20px;">
                <h3>📒 All Link Clicks</h3>
                <form method="get" style="margin-bottom: 12px; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:10px; align-items:end;">
                    <input type="hidden" name="page" value="leadstream-analytics-injector" />
                    <input type="hidden" name="tab" value="links" />
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">From</label>
                        <input type="date" name="l_from" value="<?php echo esc_attr($from); ?>" />
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">To</label>
                        <input type="date" name="l_to" value="<?php echo esc_attr($to); ?>" />
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">Slug</label>
                        <select name="l_slug">
                            <option value="">All</option>
                            <?php foreach ($slugs as $s): ?>
                                <option value="<?php echo esc_attr($s); ?>" <?php selected($slug, $s); ?>>/l/<?php echo esc_html($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">Page contains</label>
                        <input type="text" name="l_q" value="<?php echo esc_attr($page_q); ?>" placeholder="/landing, Title..." />
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; color:#646970;">Per page</label>
                        <input type="number" name="l_pp" value="<?php echo esc_attr($per_page); ?>" min="10" max="200" />
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button class="button button-primary" type="submit">Filter</button>
                        <a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'leadstream-analytics-injector','tab'=>'links'], admin_url('admin.php'))); ?>">Reset</a>
                        <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['l_export'=>'csv']))); ?>">Export CSV</a>
                    </div>
                </form>

                <div style="margin-bottom:8px; color:#646970; font-size:12px;">Showing <?php echo number_format(min($per_page, max(0, $total_count - $offset))); ?> of <?php echo number_format($total_count); ?> result<?php echo $total_count==1?'':'s'; ?>.</div>

                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="12%">Date</th>
                            <th width="10%">Time</th>
                            <th width="18%">Pretty Link</th>
                            <th>Page</th>
                            <th width="12%">IP</th>
                            <th width="16%">Referrer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                        <tr><td colspan="6" style="text-align:center; color:#646970;">No clicks found for the selected filters.</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                        <?php $ts = strtotime($r->clicked_at);
                            $date_str = !empty($r->click_date) ? esc_html(date_i18n('M j, Y', strtotime($r->click_date))) : esc_html(date_i18n('M j, Y', $ts));
                            $time_str = !empty($r->click_time) ? esc_html(date_i18n('g:i A', strtotime($r->click_time))) : esc_html(date_i18n('g:i A', $ts));
                        ?>
                        <tr>
                            <td><?php echo $date_str; ?></td>
                            <td><?php echo $time_str; ?></td>
                            <td>
                                <?php if (!empty($r->slug)): ?>
                                    <a href="<?php echo esc_url(home_url('/l/' . $r->slug)); ?>" target="_blank">/l/<?php echo esc_html($r->slug); ?></a>
                                <?php else: ?>
                                    <span style="color:#646970;">(deleted)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r->page_url)): ?>
                                    <a href="<?php echo esc_url($r->page_url); ?>" target="_blank" title="<?php echo esc_attr($r->page_title ?: $r->page_url); ?>">
                                        <?php echo esc_html($r->page_title ?: $r->page_url); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:#646970;">(no page)</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html($r->ip_address ?: ''); ?></code></td>
                            <td><?php echo esc_html(wp_parse_url($r->referrer)['host'] ?? $r->referrer ?? ''); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <?php $total_pages = max(1, ceil($total_count / $per_page));
                if ($total_pages > 1):
                    echo '<div class="tablenav"><div class="tablenav-pages">';
                    for ($i=1; $i<=$total_pages; $i++) {
                        $url = add_query_arg(array_merge($_GET, ['l_p'=>$i]));
                        $style = $i==$paged ? 'font-weight:600;' : '';
                        echo '<a class="page-numbers" style="margin-right:6px; ' . esc_attr($style) . '" href="' . esc_url($url) . '">' . intval($i) . '</a>';
                    }
                    echo '</div></div>';
                endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (current_user_can('manage_options')): ?>
        <!-- Import: Pretty Links (separate card) -->
        <div style="margin-top: 18px; padding:16px; border:1px solid #ccd0d4; background:#fff; border-radius:6px;">
            <h3 style="margin:0 0 6px 0;">Import: Pretty Links</h3>
            <p style="margin:6px 0 12px 0; color:#50575e;">Upload CSV backups exported from LeadStream. Use the Links form for slugs/targets and the Clicks form for click history.</p>
            <form method="post" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap; margin-bottom:10px;">
                <?php wp_nonce_field('ls_import_links','ls_import_links_nonce'); ?>
                <div><label>Pretty Links CSV<br><input type="file" name="ls_import_file_links" accept=".csv" required></label></div>
                <label style="display:flex; align-items:center; gap:6px;"><input type="checkbox" name="ls_import_truncate_links" value="1"> Truncate existing pretty links before import</label>
                <button type="submit" name="ls_import_links" value="1" class="button">Import Pretty Links CSV</button>
            </form>
            <form method="post" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
                <?php wp_nonce_field('ls_import_link_clicks','ls_import_link_clicks_nonce'); ?>
                <div><label>Link Clicks CSV<br><input type="file" name="ls_import_file" accept=".csv" required></label></div>
                <label style="display:flex; align-items:center; gap:6px;"><input type="checkbox" name="ls_import_truncate" value="1"> Truncate existing link clicks before import</label>
                <button type="submit" name="ls_import_link_clicks" value="1" class="button">Import Link Clicks CSV</button>
            </form>
        </div>

        <!-- Danger Zone: Pretty Links -->
        <div style="margin-top: 18px; padding:16px; border:1px solid #d63638; background:#fff5f5; border-radius:6px;">
            <h3 style="margin:0 0 6px 0; color:#b32d2e;">Danger Zone: Flush Pretty Links Data</h3>
            <p style="margin:6px 0 12px 0; color:#8a1f1f;">
                These actions permanently delete data from your database. Use only to resolve data issues or reduce database size. This cannot be undone.
            </p>
            <div style="margin:6px 0 12px 0; display:grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap:8px; align-items:end;">
                <form method="get" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
                    <input type="hidden" name="page" value="leadstream-analytics-injector" />
                    <input type="hidden" name="tab" value="links" />
                    <input type="hidden" name="dz_export" value="link_clicks" />
                    <input type="hidden" name="ls_dz_export" value="<?php echo esc_attr(wp_create_nonce('ls_dz_export')); ?>" />
                    <label>From<br><input type="date" name="dz_from" /></label>
                    <label>To<br><input type="date" name="dz_to" /></label>
                    <button class="button button-secondary" type="submit" name="fmt" value="csv">Export Link Clicks CSV</button>
                    <button class="button" type="submit" name="fmt" value="json">Export Link Clicks JSON</button>
                </form>
                <form method="get" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
                    <input type="hidden" name="page" value="leadstream-analytics-injector" />
                    <input type="hidden" name="tab" value="links" />
                    <input type="hidden" name="dz_export" value="links" />
                    <input type="hidden" name="ls_dz_export" value="<?php echo esc_attr(wp_create_nonce('ls_dz_export')); ?>" />
                    <label>From<br><input type="date" name="dz_from" /></label>
                    <label>To<br><input type="date" name="dz_to" /></label>
                    <button class="button button-secondary" type="submit" name="fmt" value="csv">Export Pretty Links CSV</button>
                    <button class="button" type="submit" name="fmt" value="json">Export Pretty Links JSON</button>
                </form>
                <div style="align-self:center; color:#8a1f1f;">Download filtered backups before deleting.</div>
            </div>

            <div style="margin:6px 0 12px 0;">
                <form method="post" onsubmit="return (confirm('Delete link clicks in the selected date range? This cannot be undone.'))" style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
                    <?php wp_nonce_field('ls_delete_link_clicks_range','ls_delete_link_clicks_range_nonce'); ?>
                    <label>Delete Link Clicks From<br><input type="date" name="dz_from" required /></label>
                    <label>To<br><input type="date" name="dz_to" required /></label>
                    <button type="submit" name="ls_delete_link_clicks_range" value="1" class="button" style="background:#f0b429; border-color:#f0b429; color:#1d2327;">Delete Link Clicks in Range</button>
                </form>
            </div>
            <label style="display:flex; align-items:center; gap:8px; margin:8px 0; color:#8a1f1f;">
                <input type="checkbox" id="ls-confirm-links-flush" />
                <span>I understand this will permanently remove Pretty Links and/or their click history from the database.</span>
            </label>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <form method="post" onsubmit="return (document.getElementById('ls-confirm-links-flush')?.checked && confirm('This will permanently delete ALL Pretty Link click rows. Continue?'));">
                    <?php wp_nonce_field('ls_flush_link_clicks','ls_flush_link_clicks_nonce'); ?>
                    <button type="submit" name="ls_flush_link_clicks" value="1" class="button" style="background:#d63638; border-color:#d63638; color:#fff;" id="ls-btn-links-flush-clicks" disabled>Delete All Link Clicks</button>
                </form>
                <form method="post" onsubmit="return (document.getElementById('ls-confirm-links-flush')?.checked && confirm('This will permanently delete ALL Pretty Links (slugs). Clicks tied to links will be deleted via cascade. Continue?'));">
                    <?php wp_nonce_field('ls_flush_links','ls_flush_links_nonce'); ?>
                    <button type="submit" name="ls_flush_links" value="1" class="button" style="background:#8a1f1f; border-color:#8a1f1f; color:#fff;" id="ls-btn-links-flush-links" disabled>Delete All Pretty Links</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render add new link form
     */
    private static function render_add_link_form() {
        // Get form values to preserve on validation errors
        $slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
    $target_url = isset($_POST['target_url']) ? esc_url_raw($_POST['target_url']) : '';
    $redirect_type = isset($_POST['redirect_type']) ? sanitize_text_field($_POST['redirect_type']) : '301';
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Add New Pretty Link</h1>
            <a href="<?php echo admin_url('admin.php?page=leadstream-analytics-injector&tab=links'); ?>" class="page-title-action">Back to Links</a>
            <hr class="wp-header-end">
            
            <?php 
            // Show validation errors using WordPress settings errors
            settings_errors('leadstream_links'); 
            ?>
            
            <div class="postbox" style="margin-top: 20px;">
                <div class="postbox-header">
                <h2 class="hndle">🎯 Create Your Pretty Link</h2>
                </div>
                <div class="inside">
                    
                    <!-- Introduction -->
                    <div style="background: #e8f4fd; padding: 20px; border-left: 4px solid #0073aa; margin-bottom: 20px; border-radius: 4px;">
                        <h3 style="margin-top: 0; color: #0073aa;">Transform Messy URLs into Clean, Trackable Links</h3>
                        <p style="margin-bottom: 0; font-size: 14px; line-height: 1.5;">
                            Perfect for social media, email campaigns, and affiliate marketing. Paste any long URL with tracking parameters and we'll create a beautiful, shareable link that's easy to remember and track.
                        </p>
                    </div>
                    
                    <!-- Real-time Example Box -->
                    <div id="link-example" style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 20px; border-radius: 4px; display: none;">
                        <h4 style="margin-top: 0; color: #856404;">📋 What You're Creating:</h4>
                        <div style="font-family: monospace; font-size: 12px; line-height: 1.6;">
                            <div style="margin-bottom: 8px;">
                                <strong style="color: #721c24;">Before (Messy):</strong><br>
                                <span id="example-messy" style="color: #721c24; word-break: break-all;">Enter your target URL to see preview...</span>
                            </div>
                            <div>
                                <strong style="color: #155724;">After (Clean):</strong><br>
                                <span id="example-clean" style="color: #155724;"><?php echo esc_url(home_url('/l/')); ?><span id="example-slug">your-slug</span></span> ← Perfect for sharing!
                            </div>
                        </div>
                    </div>
                    
                    <form method="post" class="leadstream-admin" id="add-link-form">
                        <?php wp_nonce_field('ls_add_link', 'ls_add_link_nonce'); ?>
                        
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="slug">Slug <span class="description">(required)</span></label>
                                    </th>
                                    <td>
                                        <input type="text" id="slug" name="slug" class="regular-text" required 
                                               pattern="[a-z0-9\-]+" 
                                               title="Only lowercase letters, numbers, and dashes allowed"
                                               value="<?php echo esc_attr($slug); ?>" 
                                               placeholder="summer-sale"
                                               autocomplete="off">
                                        <button type="button" class="button" id="btn-generate-slug" style="margin-left:8px;">Generate Short Slug</button>
                                        
                                        <!-- Live Preview -->
                                        <div id="slug-preview" style="margin-top: 8px; padding: 8px 12px; background: #f6f7f7; border-left: 4px solid #00a0d2; border-radius: 3px; display: none;">
                                            <strong>Preview:</strong> <span id="preview-url"><?php echo esc_url(home_url('/l/')); ?></span><strong id="preview-slug"></strong>
                                        </div>
                                        
                                        <!-- Validation feedback -->
                                        <div id="slug-feedback" style="margin-top: 5px;"></div>
                                        
                                        <p class="description">Choose a memorable, SEO-friendly name for your link. Keep it short and descriptive (e.g., 'summer-sale', 'free-guide', 'product-demo')</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="target_url">Target URL <span class="description">(required)</span></label>
                                    </th>
                                    <td>
                                        <input type="url" id="target_url" name="target_url" class="regular-text" required
                                               value="<?php echo esc_attr($target_url); ?>" 
                                               placeholder="https://partner.com/product?utm_source=email&utm_campaign=summer&ref=123">
                                        <p class="description">Paste your long, complex URL here (with UTM parameters, tracking codes, affiliate links, etc.). We'll turn it into a clean, shareable link that's perfect for social media and email campaigns.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="redirect_type">Redirect Type</label></th>
                                    <td>
                                        <select id="redirect_type" name="redirect_type">
                                            <?php $rt = in_array($redirect_type, ['301','302','307','308'], true) ? $redirect_type : '301'; ?>
                                            <option value="301" <?php selected($rt,'301'); ?>>301 (Moved Permanently)</option>
                                            <option value="302" <?php selected($rt,'302'); ?>>302 (Found/Temporary)</option>
                                            <option value="307" <?php selected($rt,'307'); ?>>307 (Temporary, method preserved)</option>
                                            <option value="308" <?php selected($rt,'308'); ?>>308 (Permanent, method preserved)</option>
                                        </select>
                                        <p class="description">Choose the HTTP status code used for redirects. 301 is typical for permanent short links.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <p class="submit">
                            <?php submit_button('Add Pretty Link', 'primary', 'submit', false); ?>
                            <a href="<?php echo admin_url('admin.php?page=leadstream-analytics-injector&tab=links'); ?>" class="button button-secondary" style="margin-left: 10px;">Cancel</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            const slugInput = $('#slug');
            const previewDiv = $('#slug-preview');
            const previewSlug = $('#preview-slug');
            const feedbackDiv = $('#slug-feedback');
            let checkTimeout;
            
            // Live preview as user types
            slugInput.on('input', function() {
                const value = $(this).val().toLowerCase().replace(/[^a-z0-9\-]/g, '');
                $(this).val(value); // Auto-clean the input
                
                if (value.length > 0) {
                    previewSlug.text(value);
                    previewDiv.show();
                    
                    // Clear previous timeout
                    clearTimeout(checkTimeout);
                    
                    // Check availability after user stops typing
                    checkTimeout = setTimeout(function() {
                        checkSlugAvailability(value);
                    }, 500);
                } else {
                    previewDiv.hide();
                    feedbackDiv.html('');
                }
            });
            
            // Check slug availability via AJAX
            function checkSlugAvailability(slug) {
                if (slug.length < 2) return;
                
                feedbackDiv.html('<span style="color: #666;">⏳ Checking availability...</span>');
                
                $.post(ajaxurl, {
                    action: 'check_slug_availability',
                    slug: slug,
                    nonce: '<?php echo wp_create_nonce('ls_check_slug'); ?>'
                })
                .done(function(response) {
                    if (response.success) {
                        if (response.data.available) {
                            feedbackDiv.html('<span style="color: #00a32a;">✓ Available</span>');
                        } else {
                            feedbackDiv.html('<span style="color: #d63638;">✗ Already taken</span>');
                        }
                    }
                })
                .fail(function() {
                    feedbackDiv.html('<span style="color: #666;">Could not check availability</span>');
                });
            }

            // Generate short slug
            $('#btn-generate-slug').on('click', function() {
                feedbackDiv.html('<span style="color: #666;">⏳ Generating...</span>');
                $.post(ajaxurl, {
                    action: 'ls_generate_short_slug',
                    nonce: '<?php echo wp_create_nonce('ls_generate_slug'); ?>'
                }).done(function(res) {
                    if (res && res.success && res.data.slug) {
                        slugInput.val(res.data.slug).trigger('input');
                        feedbackDiv.html('<span style="color: #00a32a;">✓ Short slug generated</span>');
                    } else {
                        feedbackDiv.html('<span style="color: #d63638;">Could not generate slug</span>');
                    }
                }).fail(function(){
                    feedbackDiv.html('<span style="color: #d63638;">Error generating slug</span>');
                });
            });
            
            // Pre-populate if there's an existing value
            if (slugInput.val()) {
                slugInput.trigger('input');
            }
        });
        </script>
        <?php
    }

    /**
     * Render edit link form
     */
    private static function render_edit_link_form() {
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

    $redirect_type = $link->redirect_type ?? '301';
    ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Edit Pretty Link</h1>
            <a href="<?php echo admin_url('admin.php?page=leadstream-analytics-injector&tab=links'); ?>" class="page-title-action">Back to Links</a>
            <hr class="wp-header-end">
            
            <?php 
            // Show validation errors using WordPress settings errors
            settings_errors('leadstream_links'); 
            ?>
            
            <div class="postbox" style="margin-top: 20px;">
                <div class="postbox-header">
                    <h2 class="hndle">Link Settings</h2>
                </div>
                <div class="inside">
                    <form method="post" class="leadstream-admin" id="edit-link-form">
                        <input type="hidden" name="id" value="<?php echo esc_attr($link->id); ?>">
                        <?php wp_nonce_field('ls_edit_link', 'ls_edit_link_nonce'); ?>
                        
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="slug">Slug <span class="description">(required)</span></label>
                                    </th>
                                    <td>
                                        <input type="text" id="slug" name="slug" class="regular-text" required 
                                               pattern="[a-z0-9\-]+" 
                                               title="Only lowercase letters, numbers, and dashes allowed"
                                               value="<?php echo esc_attr($link->slug); ?>"
                                               autocomplete="off">
                                        <button type="button" class="button" id="btn-generate-slug" style="margin-left:8px;">Generate Short Slug</button>
                                        
                                        <!-- Live Preview -->
                                        <div id="slug-preview" style="margin-top: 8px; padding: 8px 12px; background: #f6f7f7; border-left: 4px solid #00a0d2; border-radius: 3px;">
                                            <strong>Current:</strong> <span id="preview-url"><?php echo esc_url(home_url('/l/')); ?></span><strong id="preview-slug"><?php echo esc_html($link->slug); ?></strong>
                                        </div>
                                        
                                        <!-- Validation feedback -->
                                        <div id="slug-feedback" style="margin-top: 5px;"></div>
                                        
                                        <p class="description">Only lowercase letters, numbers, and dashes. Currently: <code>/l/<?php echo esc_html($link->slug); ?></code></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="target_url">Target URL <span class="description">(required)</span></label>
                                    </th>
                                    <td>
                                        <input type="url" id="target_url" name="target_url" class="regular-text" required
                                               value="<?php echo esc_attr($link->target_url); ?>">
                                        <p class="description">The full URL to redirect to when someone visits your pretty link.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="redirect_type">Redirect Type</label></th>
                                    <td>
                                        <select id="redirect_type" name="redirect_type">
                                            <?php $rt = in_array($redirect_type, ['301','302','307','308'], true) ? $redirect_type : '301'; ?>
                                            <option value="301" <?php selected($rt,'301'); ?>>301 (Moved Permanently)</option>
                                            <option value="302" <?php selected($rt,'302'); ?>>302 (Found/Temporary)</option>
                                            <option value="307" <?php selected($rt,'307'); ?>>307 (Temporary, method preserved)</option>
                                            <option value="308" <?php selected($rt,'308'); ?>>308 (Permanent, method preserved)</option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <p class="submit">
                            <?php submit_button('Update Pretty Link', 'primary', 'submit', false); ?>
                            <a href="<?php echo admin_url('admin.php?page=leadstream-analytics-injector&tab=links'); ?>" class="button button-secondary" style="margin-left: 10px;">Cancel</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            const slugInput = $('#slug');
            const previewSlug = $('#preview-slug');
            const feedbackDiv = $('#slug-feedback');
            const originalSlug = '<?php echo esc_js($link->slug); ?>';
            let checkTimeout;
            
            // Live preview as user types
            slugInput.on('input', function() {
                const value = $(this).val().toLowerCase().replace(/[^a-z0-9\-]/g, '');
                $(this).val(value); // Auto-clean the input
                
                if (value.length > 0) {
                    previewSlug.text(value);
                    
                    // Clear previous timeout
                    clearTimeout(checkTimeout);
                    
                    // Check availability after user stops typing (only if different from original)
                    if (value !== originalSlug) {
                        checkTimeout = setTimeout(function() {
                            checkSlugAvailability(value, originalSlug);
                        }, 500);
                    } else {
                        feedbackDiv.html('<span style="color: #666;">Current slug</span>');
                    }
                } else {
                    feedbackDiv.html('');
                }
            });
            
            // Check slug availability via AJAX
            function checkSlugAvailability(slug, excludeSlug) {
                if (slug.length < 2) return;
                
                feedbackDiv.html('<span style="color: #666;">⏳ Checking availability...</span>');
                
                $.post(ajaxurl, {
                    action: 'check_slug_availability',
                    slug: slug,
                    exclude: excludeSlug,
                    nonce: '<?php echo wp_create_nonce('ls_check_slug'); ?>'
                })
                .done(function(response) {
                    if (response.success) {
                        if (response.data.available) {
                            feedbackDiv.html('<span style="color: #00a32a;">✓ Available</span>');
                        } else {
                            feedbackDiv.html('<span style="color: #d63638;">✗ Already taken</span>');
                        }
                    }
                })
                .fail(function() {
                    feedbackDiv.html('<span style="color: #666;">Could not check availability</span>');
                });
            }

            // Generate short slug (edit)
            $('#btn-generate-slug').on('click', function() {
                feedbackDiv.html('<span style="color: #666;">⏳ Generating...</span>');
                $.post(ajaxurl, {
                    action: 'ls_generate_short_slug',
                    nonce: '<?php echo wp_create_nonce('ls_generate_slug'); ?>'
                }).done(function(res) {
                    if (res && res.success && res.data.slug) {
                        slugInput.val(res.data.slug).trigger('input');
                        feedbackDiv.html('<span style="color: #00a32a;">✓ Short slug generated</span>');
                    } else {
                        feedbackDiv.html('<span style="color: #d63638;">Could not generate slug</span>');
                    }
                }).fail(function(){
                    feedbackDiv.html('<span style="color: #d63638;">Error generating slug</span>');
                });
            });
            
            // Update real-time example when fields change
            $('#slug, #target_url').on('input', function() {
                updateLinkExample();
            });
            
            function updateLinkExample() {
                var slug = $('#slug').val();
                var targetUrl = $('#target_url').val();
                
                if (slug && targetUrl) {
                    $('#link-example').show();
                    $('#example-messy').text(targetUrl);
                    $('#example-slug').text(slug);
                } else {
                    $('#link-example').hide();
                }
            }
        });
        </script>
        <?php
    }

    /**
     * Handle Pretty Links form submissions early (before any output)
     */
    private static function handle_pretty_links_form_submission_early() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        // Handle Add New Link
        if (isset($_POST['ls_add_link_nonce']) && wp_verify_nonce($_POST['ls_add_link_nonce'], 'ls_add_link')) {
            $slug = sanitize_title($_POST['slug'] ?? '');
            $target_url = esc_url_raw($_POST['target_url'] ?? '');
            $redirect_type = isset($_POST['redirect_type']) && in_array($_POST['redirect_type'], ['301','302','307','308'], true)
                ? $_POST['redirect_type']
                : '301';
            
            // Use WP_Error for better error handling
            $errors = new \WP_Error();
            
            // Validate slug
            if (empty($slug)) {
                $errors->add('slug_empty', 'Slug is required.');
            } elseif (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
                $errors->add('slug_invalid', 'Slug can only contain lowercase letters, numbers, and dashes.');
            }
            
            // Validate URL
            if (empty($target_url)) {
                $errors->add('url_empty', 'Target URL is required.');
            } elseif (!filter_var($target_url, FILTER_VALIDATE_URL)) {
                $errors->add('url_invalid', 'Please enter a valid URL (including http:// or https://).');
            }
            
            // Check for duplicate slug
            if (!$errors->has_errors()) {
                global $wpdb;
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}ls_links WHERE slug = %s",
                    $slug
                ));
                
                if ($existing > 0) {
                    $errors->add('slug_exists', 'That slug is already in use. Please try another.');
                }
            }
            
            // Process if no errors
            if (!$errors->has_errors()) {
                try {
                    global $wpdb;
                    // Insert the new link
                    $result = $wpdb->insert(
                        $wpdb->prefix . 'ls_links',
                        [
                            'slug' => $slug,
                            'target_url' => $target_url,
                            'redirect_type' => $redirect_type,
                            'created_at' => current_time('mysql')
                        ],
                        ['%s', '%s', '%s', '%s']
                    );
                    
                    if ($result !== false) {
                        // Store last used slug in user meta for persistence
                        $user_id = get_current_user_id();
                        update_user_meta($user_id, 'ls_last_pretty_link', $slug);
                        
                        // Use nocache_headers to prevent caching issues
                        nocache_headers();
                        wp_redirect(admin_url('admin.php?page=leadstream-analytics-injector&tab=links&added=' . urlencode($slug)));
                        exit;
                    } else {
                        $errors->add('db_error', 'Database error: Could not create the link.');
                    }
                } catch (Exception $e) {
                    error_log('LeadStream DB Insert Error: ' . $e->getMessage());
                    $errors->add('db_exception', 'An unexpected error occurred. Please try again.');
                }
            }
            
            // Store errors for display using WordPress settings errors
            if ($errors->has_errors()) {
                foreach ($errors->get_error_messages() as $message) {
                    add_settings_error('leadstream_links', '', $message, 'error');
                }
            }
        }
        
        // Handle Edit Link
        if (isset($_POST['ls_edit_link_nonce']) && wp_verify_nonce($_POST['ls_edit_link_nonce'], 'ls_edit_link')) {
            $id = intval($_POST['id'] ?? 0);
            $slug = sanitize_title($_POST['slug'] ?? '');
            $target_url = esc_url_raw($_POST['target_url'] ?? '');
            $redirect_type = isset($_POST['redirect_type']) && in_array($_POST['redirect_type'], ['301','302','307','308'], true)
                ? $_POST['redirect_type']
                : '301';
            
            // Use WP_Error for better error handling
            $errors = new \WP_Error();
            
            // Validate ID
            if (!$id) {
                $errors->add('id_invalid', 'Invalid link ID.');
            }
            
            // Validate slug
            if (empty($slug)) {
                $errors->add('slug_empty', 'Slug is required.');
            } elseif (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
                $errors->add('slug_invalid', 'Slug can only contain lowercase letters, numbers, and dashes.');
            }
            
            // Validate URL
            if (empty($target_url)) {
                $errors->add('url_empty', 'Target URL is required.');
            } elseif (!filter_var($target_url, FILTER_VALIDATE_URL)) {
                $errors->add('url_invalid', 'Please enter a valid URL (including http:// or https://).');
            }
            
            // Check for duplicate slug (excluding current link)
            if (!$errors->has_errors()) {
                global $wpdb;
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}ls_links WHERE slug = %s AND id != %d",
                    $slug,
                    $id
                ));
                
                if ($existing > 0) {
                    $errors->add('slug_exists', 'That slug is already in use. Please try another.');
                }
            }
            
            // Process if no errors
            if (!$errors->has_errors()) {
                try {
                    global $wpdb;
                    // Update the link
                    $result = $wpdb->update(
                        $wpdb->prefix . 'ls_links',
                        [
                            'slug' => $slug,
                            'target_url' => $target_url,
                            'redirect_type' => $redirect_type,
                        ],
                        ['id' => $id],
                        ['%s', '%s', '%s'],
                        ['%d']
                    );
                    
                    if ($result !== false) {
                        // Store last used slug in user meta for persistence
                        $user_id = get_current_user_id();
                        update_user_meta($user_id, 'ls_last_pretty_link', $slug);
                        
                        // Use nocache_headers to prevent caching issues
                        nocache_headers();
                        wp_redirect(admin_url('admin.php?page=leadstream-analytics-injector&tab=links&updated=' . urlencode($slug)));
                        exit;
                    } else {
                        $errors->add('db_error', 'Database error: Could not update the link.');
                    }
                } catch (Exception $e) {
                    error_log('LeadStream DB Update Error: ' . $e->getMessage());
                    $errors->add('db_exception', 'An unexpected error occurred. Please try again.');
                }
            }
            
            // Store errors for display using WordPress settings errors
            if ($errors->has_errors()) {
                foreach ($errors->get_error_messages() as $message) {
                    add_settings_error('leadstream_links', '', $message, 'error');
                }
            }
        }
    }

    /**
     * Show Pretty Links admin notices
     */
    private static function show_pretty_links_notices() {
        // Show settings errors (validation errors)
        settings_errors('leadstream_links');
        
        // Success messages with test links
        if (isset($_GET['added']) && !empty($_GET['added'])) {
            $slug = sanitize_text_field($_GET['added']);
            printf(
                '<div class="notice notice-success is-dismissible">
                    <p><strong>Success!</strong> Pretty Link <strong>%s</strong> added successfully! 
                       <a href="%s" target="_blank" class="button button-small">Test it →</a>
                    </p>
                 </div>',
                esc_html($slug),
                esc_url(home_url("/l/{$slug}"))
            );
        }
        
        if (isset($_GET['updated']) && !empty($_GET['updated'])) {
            $slug = sanitize_text_field($_GET['updated']);
            printf(
                '<div class="notice notice-success is-dismissible">
                    <p><strong>Success!</strong> Pretty Link <strong>%s</strong> updated successfully! 
                       <a href="%s" target="_blank" class="button button-small">Test it →</a>
                    </p>
                 </div>',
                esc_html($slug),
                esc_url(home_url("/l/{$slug}"))
            );
        }
        
        if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
            echo '<div class="notice notice-success is-dismissible">
                    <p><strong>Success!</strong> Pretty Link deleted successfully!</p>
                  </div>';
        }
    }

    /**
     * Handle adding new pretty link (DEPRECATED - kept for backward compatibility)
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
     * Handle editing pretty link (DEPRECATED - kept for backward compatibility)
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

    /**
     * Show Pretty Links statistics summary
     */
    private static function show_pretty_links_stats() {
        global $wpdb;
        
        // Get basic stats
        $total_links = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ls_links");
        $total_clicks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE link_type = 'link'");
        $clicks_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE link_type = 'link' AND DATE(clicked_at) = %s",
            current_time('Y-m-d')
        ));
        $clicks_this_week = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE link_type = 'link' AND clicked_at >= %s",
            date('Y-m-d', strtotime('-7 days'))
        ));
        
        // Get most popular link
        $popular_link = $wpdb->get_row(
            "SELECT l.slug, COUNT(c.id) as click_count 
             FROM {$wpdb->prefix}ls_links l 
             LEFT JOIN {$wpdb->prefix}ls_clicks c ON l.id = c.link_id 
             GROUP BY l.id 
             ORDER BY click_count DESC 
             LIMIT 1"
        );
        
        if ($total_links == 0) {
            return; // Don't show stats if no links exist
        }
        
        ?>
        <div class="leadstream-stats-summary" style="margin: 20px 0; display: flex; gap: 15px; flex-wrap: wrap;">
            <div class="stat-box" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; min-width: 140px; text-align: center; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                <div style="font-size: 24px; font-weight: 600; color: #2271b1; line-height: 1;"><?php echo number_format((int)$total_links); ?></div>
                <div style="font-size: 13px; color: #646970; margin-top: 4px;">Total Links</div>
            </div>
            
            <div class="stat-box" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; min-width: 140px; text-align: center; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                <div style="font-size: 24px; font-weight: 600; color: #00a32a; line-height: 1;"><?php echo number_format((int)$total_clicks); ?></div>
                <div style="font-size: 13px; color: #646970; margin-top: 4px;">Total Clicks</div>
            </div>
            
            <div class="stat-box" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; min-width: 140px; text-align: center; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                <div style="font-size: 24px; font-weight: 600; color: #dba617; line-height: 1;"><?php echo number_format((int)$clicks_today); ?></div>
                <div style="font-size: 13px; color: #646970; margin-top: 4px;">Today</div>
            </div>
            
            <div class="stat-box" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; min-width: 140px; text-align: center; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                <div style="font-size: 24px; font-weight: 600; color: #72aee6; line-height: 1;"><?php echo number_format((int)$clicks_this_week); ?></div>
                <div style="font-size: 13px; color: #646970; margin-top: 4px;">This Week</div>
            </div>
            
            <?php if ($popular_link && $popular_link->click_count > 0): ?>
            <div class="stat-box" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; min-width: 180px; text-align: center; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                <div style="font-size: 16px; font-weight: 600; color: #1d2327; line-height: 1; font-family: Consolas, Monaco, monospace;">/l/<?php echo esc_html($popular_link->slug); ?></div>
                <div style="font-size: 13px; color: #646970; margin-top: 4px;">Most Popular (<?php echo number_format($popular_link->click_count); ?> clicks)</div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX handler for checking slug availability
     */
    public static function ajax_check_slug_availability() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'ls_check_slug')) {
            wp_send_json_error('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $slug = sanitize_title($_POST['slug'] ?? '');
        $exclude = sanitize_title($_POST['exclude'] ?? '');
        
        if (empty($slug)) {
            wp_send_json_error('Invalid slug');
        }
        
        global $wpdb;
        
        // For edit forms, exclude the current slug from the check
        if (!empty($exclude)) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ls_links WHERE slug = %s AND slug != %s",
                $slug,
                $exclude
            ));
        } else {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ls_links WHERE slug = %s",
                $slug
            ));
        }
        
        wp_send_json_success([
            'available' => ($existing == 0),
            'slug' => $slug
        ]);
    }

    /**
     * AJAX: Generate a unique short slug (6-8 chars)
     */
    public static function ajax_generate_short_slug() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ls_generate_slug')) {
            wp_send_json_error('Invalid nonce');
        }

        global $wpdb;
        $max_tries = 10;
        $slug = '';
        for ($i=0; $i<$max_tries; $i++) {
            // Base32-like alphabet without confusing chars 0,O,1,l
            $alphabet = '23456789abcdefghijkmnpqrstuvwxyz';
            $len = rand(6, 8);
            $candidate = '';
            for ($j=0; $j<$len; $j++) {
                $candidate .= $alphabet[random_int(0, strlen($alphabet)-1)];
            }

            // Ensure uniqueness
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ls_links WHERE slug = %s",
                $candidate
            ));
            if (!$exists) { $slug = $candidate; break; }
        }

        if (empty($slug)) {
            wp_send_json_error('Could not generate unique slug');
        }
        wp_send_json_success(['slug' => $slug]);
    }

    /**
     * Render Pretty Links helper section for JavaScript injection
     */
    private static function render_pretty_links_helper() {
        global $wpdb;
        
        // Get user's last used pretty link
        $user_id = get_current_user_id();
        $last_slug = get_user_meta($user_id, 'ls_last_pretty_link', true);
        
        // Get all available pretty links
        $all_links = $wpdb->get_results(
            "SELECT slug, target_url FROM {$wpdb->prefix}ls_links ORDER BY created_at DESC LIMIT 20"
        );
        
        if (empty($all_links)) {
            return; // Don't show if no links exist
        }
        
        ?>
        <div class="leadstream-pretty-links-helper" style="margin:20px 0; padding:15px; background:#f8f9fa; border-left:4px solid #00a0d2; border-radius:4px;">
            <h3 style="margin-top:0;">🎯 Quick Access: Your Pretty Links</h3>
            <p>Use these short links in your tracking code, social media, or anywhere you need clean URLs:</p>
            
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin: 15px 0;">
                <?php foreach ($all_links as $link): ?>
                    <div class="pretty-link-item" style="background: white; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;" 
                         onclick="copyToClipboard('<?php echo esc_js(home_url("/l/{$link->slug}")); ?>')">
                        <code>/l/<?php echo esc_html($link->slug); ?></code>
                        <span style="font-size: 12px; color: #666; margin-left: 8px;">→ <?php echo esc_html(wp_trim_words($link->target_url, 6, '...')); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <p style="margin-bottom: 0;">
                <small style="color: #666;">💡 Click any link to copy to clipboard. 
                <a href="<?php echo admin_url('admin.php?page=leadstream-analytics-injector&tab=links'); ?>">Manage all links →</a></small>
            </p>
        </div>
        
        <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show success feedback
                const temp = event.target.style.background;
                event.target.style.background = '#00a32a';
                event.target.style.color = 'white';
                setTimeout(function() {
                    event.target.style.background = temp;
                    event.target.style.color = '';
                }, 500);
            });
        }
        </script>
        <?php
    }

    /**
     * Add dashboard widget for Pretty Links stats
     */
    public static function add_dashboard_widget() {
        // Only show to users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if Pretty Links tables exist
        global $wpdb;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ls_links'");
        
        if (!$table_exists) {
            return; // Don't show widget if tables don't exist
        }

        wp_add_dashboard_widget(
            'leadstream_pretty_links_widget',
            '🎯 LeadStream: Pretty Links Stats',
            [__CLASS__, 'render_dashboard_widget'],
            [__CLASS__, 'render_dashboard_widget_config'],
            null,
            'normal',
            'high'
        );
    }

    /**
     * Render the dashboard widget content
     */
    public static function render_dashboard_widget() {
        global $wpdb;
        
        // Get total stats
        $total_links = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ls_links");
        $total_clicks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE link_type = 'link'");
        
        // Get clicks this week
        $week_start = date('Y-m-d H:i:s', strtotime('monday this week'));
        $clicks_this_week = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE link_type = 'link' AND clicked_at >= %s",
            $week_start
        ));
        
        // Get clicks today
        $today_start = date('Y-m-d 00:00:00');
        $clicks_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE link_type = 'link' AND clicked_at >= %s",
            $today_start
        ));
        
        // Get top 5 links this week
        $top_links = $wpdb->get_results($wpdb->prepare(
            "SELECT l.slug, l.target_url, COUNT(c.id) as click_count
             FROM {$wpdb->prefix}ls_links l
             LEFT JOIN {$wpdb->prefix}ls_clicks c ON l.id = c.link_id AND c.link_type = 'link' AND c.clicked_at >= %s
             GROUP BY l.id
             ORDER BY click_count DESC, l.created_at DESC
             LIMIT 5",
            $week_start
        ));
        
        // Get overall sparkline data (last 14 days for better trend visualization)
        $sparkline_data = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $clicks = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$wpdb->prefix}ls_clicks 
                WHERE link_type = 'link' AND DATE(clicked_at) = %s
            ", $date));
            $sparkline_data[] = intval($clicks);
        }
        
        ?>
        <div class="leadstream-dashboard-widget">
            
            <!-- Custom Widget Header with Logo -->
            <div style="display: flex; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f1;">
                <img src="<?php echo plugins_url('assets/Lead-stream-logo-Small.png', dirname(dirname(__FILE__))); ?>" 
                     alt="LeadStream Logo" 
                     style="width: 36px; height: 40px; margin-right: 12px; border-radius: 4px; object-fit: contain; vertical-align: bottom;">
                <div>
                    <h3 style="margin: 0; font-size: 16px; color: #1d2327; font-weight: 600;">
                        Pretty Links Dashboard
                    </h3>
                    <div style="font-size: 12px; color: #646970; margin-top: 2px;">
                        Track your link performance
                    </div>
                </div>
            </div>
            
            <!-- Summary Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div style="text-align: center; padding: 15px; background: #f0f6fc; border-radius: 6px; border-left: 4px solid #2271b1;">
                    <div style="font-size: 24px; font-weight: 600; color: #1d2327; margin-bottom: 4px;">
                        <?php echo number_format($total_links); ?>
                    </div>
                    <div style="font-size: 12px; color: #50575e; text-transform: uppercase; letter-spacing: 0.5px;">
                        Total Links
                    </div>
                </div>
                
                <div style="text-align: center; padding: 15px; background: #f0f8f0; border-radius: 6px; border-left: 4px solid #00a32a;">
                    <div style="font-size: 24px; font-weight: 600; color: #1d2327; margin-bottom: 4px;">
                        <?php echo number_format($total_clicks); ?>
                    </div>
                    <div style="font-size: 12px; color: #50575e; text-transform: uppercase; letter-spacing: 0.5px;">
                        All-Time Clicks
                    </div>
                </div>
                
                <div style="text-align: center; padding: 15px; background: #fff8e1; border-radius: 6px; border-left: 4px solid #dba617;">
                    <div style="font-size: 24px; font-weight: 600; color: #1d2327; margin-bottom: 4px;">
                        <?php echo number_format($clicks_this_week); ?>
                    </div>
                    <div style="font-size: 12px; color: #50575e; text-transform: uppercase; letter-spacing: 0.5px;">
                        This Week
                    </div>
                </div>
                
                <div style="text-align: center; padding: 15px; background: #fdf2f2; border-radius: 6px; border-left: 4px solid #d63638;">
                    <div style="font-size: 24px; font-weight: 600; color: #1d2327; margin-bottom: 4px;">
                        <?php echo number_format($clicks_today); ?>
                    </div>
                    <div style="font-size: 12px; color: #50575e; text-transform: uppercase; letter-spacing: 0.5px;">
                        Today
                    </div>
                </div>
            </div>
            
            <!-- Activity Sparkline -->
            <?php if (array_sum($sparkline_data) > 0): ?>
            <div style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #dcdcde; border-radius: 6px;">
                <h4 style="margin: 0 0 12px 0; font-size: 14px; color: #1d2327; display: flex; align-items: center; gap: 6px;">
                    📊 Activity Trend (14 Days)
                    <?php 
                    // Calculate overall trend
                    $first_week = array_sum(array_slice($sparkline_data, 0, 7));
                    $second_week = array_sum(array_slice($sparkline_data, 7, 7));
                    if ($second_week > $first_week) {
                        echo '<span style="color: #00a32a; font-size: 12px;">📈 Trending Up</span>';
                    } elseif ($second_week < $first_week) {
                        echo '<span style="color: #d63638; font-size: 12px;">📉 Trending Down</span>';
                    } else {
                        echo '<span style="color: #646970; font-size: 12px;">➡️ Steady</span>';
                    }
                    ?>
                </h4>
                <?php echo self::render_widget_sparkline($sparkline_data); ?>
            </div>
            <?php endif; ?>
            
            <!-- Top Links This Week -->
            <?php if (!empty($top_links)): ?>
            <div style="margin-bottom: 15px;">
                <h4 style="margin: 0 0 12px 0; font-size: 14px; color: #1d2327;">📈 Top Links This Week</h4>
                <div style="background: #fff; border: 1px solid #dcdcde; border-radius: 4px;">
                    <?php foreach ($top_links as $i => $link): ?>
                    <div style="padding: 12px 15px; border-bottom: 1px solid #f0f0f1; display: flex; justify-content: space-between; align-items: center; <?php echo ($i === count($top_links) - 1) ? 'border-bottom: none;' : ''; ?>">
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 600; font-size: 13px; color: #0073aa; margin-bottom: 2px;">
                                /l/<?php echo esc_html($link->slug); ?>
                            </div>
                            <div style="font-size: 11px; color: #646970; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                → <?php echo esc_html(wp_trim_words($link->target_url, 8, '...')); ?>
                            </div>
                        </div>
                        <div style="margin-left: 10px; text-align: right;">
                            <div style="font-weight: 600; font-size: 14px; color: #1d2327;">
                                <?php echo number_format($link->click_count); ?>
                            </div>
                            <div style="font-size: 11px; color: #646970;">
                                click<?php echo $link->click_count == 1 ? '' : 's'; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Quick Actions - Full Navigation -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; padding-top: 15px; border-top: 1px solid #f0f0f1;">
                <a href="<?php echo admin_url('admin.php?page=leadstream-analytics-injector&tab=links'); ?>" 
                   class="button button-primary button-small ls-widget-btn">
                    📊 Dashboard
                </a>
                <a href="<?php echo admin_url('admin.php?page=leadstream-analytics-injector&tab=links&action=add'); ?>" 
                   class="button button-secondary button-small ls-widget-btn">
                    ➕ Add Link
                </a>
                <a href="<?php echo admin_url('admin.php?page=leadstream-analytics-injector&tab=utm'); ?>" 
                   class="button button-secondary button-small ls-widget-btn">
                    🔗 UTM Builder
                </a>
                <a href="<?php echo admin_url('admin.php?page=leadstream-analytics-injector&tab=javascript'); ?>" 
                   class="button button-secondary button-small ls-widget-btn">
                    📝 Code Inject
                </a>
            </div>
            
            <?php if ($total_links == 0): ?>
            <div style="text-align: center; padding: 20px; color: #646970;">
                <div style="font-size: 14px; margin-bottom: 10px;">🚀 Ready to start tracking?</div>
                <div style="font-size: 12px; line-height: 1.4;">
                    Create your first Pretty Link to see stats here!
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .leadstream-dashboard-widget .button-small {
            font-size: 11px;
            padding: 4px 8px;
            height: auto;
            line-height: 1.4;
        }
        .leadstream-dashboard-widget .ls-widget-btn {
            text-align: center;
            font-size: 10px;
            padding: 6px 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
        }
        .leadstream-dashboard-widget .ls-widget-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        </style>
        <?php
    }

    /**
     * Dashboard widget configuration
     */
    public static function render_dashboard_widget_config() {
        // Empty function - required for widget registration but we don't need config options
    }
    
    /**
     * Render a larger sparkline for the dashboard widget
     */
    private static function render_widget_sparkline($data) {
        if (empty($data) || array_sum($data) == 0) {
            return '<div style="text-align: center; color: #646970; font-size: 12px; padding: 20px;">No click data available</div>';
        }
        
        $max = max($data);
        $svg_height = 60;
        $svg_width = 280;
        $points = [];
        $bars = [];
        
        // Create points for line chart
        foreach ($data as $i => $value) {
            $x = ($i / (count($data) - 1)) * $svg_width;
            $y = $svg_height - (($value / $max) * ($svg_height - 10)) - 5;
            $points[] = "$x,$y";
            
            // Create bars for bar chart overlay
            $bar_width = ($svg_width / count($data)) * 0.6;
            $bar_x = ($i * ($svg_width / count($data))) + (($svg_width / count($data)) - $bar_width) / 2;
            $bar_height = ($value / $max) * ($svg_height - 10);
            $bar_y = $svg_height - $bar_height - 5;
            
            if ($value > 0) {
                $bars[] = sprintf(
                    '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="rgba(34, 113, 177, 0.2)" stroke="rgba(34, 113, 177, 0.4)" stroke-width="0.5"/>',
                    $bar_x, $bar_y, $bar_width, $bar_height
                );
            }
        }
        
        $path = 'M' . implode(' L', $points);
        $total_clicks = array_sum($data);
        $avg_clicks = round($total_clicks / count($data), 1);
        
        return sprintf(
            '<div style="text-align: center;">
                <svg width="%d" height="%d" style="border: 1px solid #dcdcde; background: linear-gradient(to bottom, #fafafa, #f0f0f1); border-radius: 4px; margin-bottom: 10px;">
                    <!-- Grid lines -->
                    <defs>
                        <pattern id="grid" width="20" height="10" patternUnits="userSpaceOnUse">
                            <path d="M 20 0 L 0 0 0 10" fill="none" stroke="#e0e0e0" stroke-width="0.5" opacity="0.3"/>
                        </pattern>
                    </defs>
                    <rect width="100%%" height="100%%" fill="url(#grid)" />
                    
                    <!-- Bars -->
                    %s
                    
                    <!-- Line -->
                    <polyline points="%s" fill="none" stroke="#2271b1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    
                    <!-- Data points -->
                    %s
                </svg>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #646970;">
                    <span>14 days ago</span>
                    <span><strong>%d total clicks</strong> • avg %.1f/day</span>
                    <span>Today</span>
                </div>
            </div>',
            $svg_width,
            $svg_height,
            implode('', $bars),
            implode(' ', $points),
            implode('', array_map(function($point, $value) {
                list($x, $y) = explode(',', $point);
                return $value > 0 ? sprintf('<circle cx="%.1f" cy="%.1f" r="2" fill="#2271b1"/>', $x, $y) : '';
            }, $points, $data)),
            $total_clicks,
            $avg_clicks
        );
    }
}
