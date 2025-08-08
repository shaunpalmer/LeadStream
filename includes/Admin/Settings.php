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
        
        // Pretty Links form handlers (remove old admin_post handlers)
        // add_action('admin_post_add_pretty_link', [__CLASS__, 'handle_add_pretty_link']);
        // add_action('admin_post_edit_pretty_link', [__CLASS__, 'handle_edit_pretty_link']);
        
        // AJAX handlers
        add_action('wp_ajax_check_slug_availability', [__CLASS__, 'ajax_check_slug_availability']);
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
    
    /**
     * Display settings page content
     */
    public static function display_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        
        // Handle form submissions FIRST (before any output)
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'javascript';
        
        // Only process forms on Pretty Links tab and if it's a POST request
        if ($current_tab === 'links' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                self::handle_pretty_links_form_submission_early();
            } catch (Exception $e) {
                // Log error and show user-friendly message
                error_log('LeadStream form processing error: ' . $e->getMessage());
                add_settings_error('leadstream_links', 'form_error', 'An error occurred while processing the form. Please try again.');
            }
        }
        
        ?>
        <div class="wrap">
            <h1>LeadStream: Advanced Analytics Injector</h1>
            
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
                            
                            // Show quick stats
                            self::show_pretty_links_stats();
                            
                            // Show quick access helper
                            self::render_pretty_links_helper();
                            
                            // Instantiate and render our List Table
                            $table = new \LS\Admin\LinksDashboard();
                            $table->prepare_items();
                            echo '<div class="wrap">';
                            echo '<h1 class="wp-heading-inline">Pretty Links Dashboard</h1>';
                            echo '<a href="' . admin_url('admin.php?page=leadstream-analytics-injector&tab=links&action=add') . '" class="page-title-action">Add New</a>';
                            echo '<hr class="wp-header-end">';
                            $table->display();
                            
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
                            
                            echo '</div>';
                            break;
                    }
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
     * Render Pretty Links tab
     */
    private static function render_links_tab() {
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
        </div>
        <?php
    }

    /**
     * Render add new link form
     */
    private static function render_add_link_form() {
        // Get form values to preserve on validation errors
        $slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
        $target_url = isset($_POST['target_url']) ? esc_url_raw($_POST['target_url']) : '';
        
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
                            'created_at' => current_time('mysql')
                        ],
                        ['%s', '%s', '%s']
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
                        ],
                        ['id' => $id],
                        ['%s', '%s'],
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
        $total_clicks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks");
        $clicks_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE DATE(clicked_at) = %s",
            current_time('Y-m-d')
        ));
        $clicks_this_week = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE clicked_at >= %s",
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
                <div style="font-size: 24px; font-weight: 600; color: #2271b1; line-height: 1;"><?php echo number_format($total_links); ?></div>
                <div style="font-size: 13px; color: #646970; margin-top: 4px;">Total Links</div>
            </div>
            
            <div class="stat-box" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; min-width: 140px; text-align: center; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                <div style="font-size: 24px; font-weight: 600; color: #00a32a; line-height: 1;"><?php echo number_format($total_clicks); ?></div>
                <div style="font-size: 13px; color: #646970; margin-top: 4px;">Total Clicks</div>
            </div>
            
            <div class="stat-box" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; min-width: 140px; text-align: center; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                <div style="font-size: 24px; font-weight: 600; color: #dba617; line-height: 1;"><?php echo number_format($clicks_today); ?></div>
                <div style="font-size: 13px; color: #646970; margin-top: 4px;">Today</div>
            </div>
            
            <div class="stat-box" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; min-width: 140px; text-align: center; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
                <div style="font-size: 24px; font-weight: 600; color: #72aee6; line-height: 1;"><?php echo number_format($clicks_this_week); ?></div>
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
}
