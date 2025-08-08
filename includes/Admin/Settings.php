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
        
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'javascript';
        
        ?>
        <div class="wrap">
            <h1>LeadStream: Advanced Analytics Injector</h1>
            <?php settings_errors(); ?>
            
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
                    self::render_links_tab();
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
        $history = get_transient('ls_utm_history') ?: [];
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
}
