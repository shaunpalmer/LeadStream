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
        ?>
        <div class="wrap">
            <h1>LeadStream: Advanced Analytics Injector</h1>
            <?php settings_errors(); ?>
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
        <?php self::render_toggle_scripts(); ?>
        <?php self::render_starter_script(); ?>
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
        <?php self::render_faq_scripts(); ?>
        <?php self::render_faq_styles(); ?>
        <?php
    }
    
    /**
     * Callback for header JS field
     */
    public static function header_js_field_callback() {
        $header_js = get_option('custom_header_js');
        echo '<textarea id="custom_header_js" name="custom_header_js" class="large-text code" rows="15" placeholder="// Header JavaScript - typically for setup code or early-loading scripts

// Example: Initialize tracking pixel (replace with your actual ID)
// fbq(\'init\', \'YOUR_PIXEL_ID_HERE\');
// fbq(\'track\', \'PageView\');

// Example: Custom variable setup
// window.customTrackingEnabled = true;

// Add your header JavaScript here...">' . esc_textarea($header_js) . '</textarea>';
        echo '<p class="description">JavaScript code to inject in the &lt;head&gt; section. Best for setup code and early-loading scripts. No &lt;script&gt; tags needed.</p>';
    }
    
    /**
     * Callback for footer JS field
     */
    public static function footer_js_field_callback() {
        $footer_js = get_option('custom_footer_js');
        echo '<textarea id="custom_footer_js" name="custom_footer_js" class="large-text code" rows="15" placeholder="// Footer JavaScript - perfect for event tracking after page loads

// Example: Track form submissions
document.addEventListener(\'wpformsSubmit\', function(event) {
  gtag(\'event\', \'form_submit\', {
    \'event_category\': [Your Service/Product] Lead,
    \'event_label\': [Your Service/Product] Contact Form
  });
});

// Example: Track button clicks
document.getElementById(\'your-button-id\').addEventListener(\'click\', function() {
  gtag(\'event\', \'button_click\', {
    \'event_category\': [Your Service/Product] CTA,
    \'event_label\': [Your Service/Product] Get Quote Button
  });
});

// Click \'Load Starter Script\' above for more examples...">' . esc_textarea($footer_js) . '</textarea>';
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
     * Render toggle scripts
     */
    private static function render_toggle_scripts() {
        ?>
        <script>
        // Make toggles mutually exclusive
        document.addEventListener('DOMContentLoaded', function() {
          var headerToggle = document.getElementById('leadstream_inject_header');
          var footerToggle = document.getElementById('leadstream_inject_footer');
          if (headerToggle && footerToggle) {
            headerToggle.addEventListener('change', function() {
              if (headerToggle.checked) footerToggle.checked = false;
            });
            footerToggle.addEventListener('change', function() {
              if (footerToggle.checked) headerToggle.checked = false;
            });
          }
        });
        </script>
        <?php
    }
    
    /**
     * Render starter script
     */
    private static function render_starter_script() {
        ?>
        <script>
        document.getElementById('load-starter-script').addEventListener('click', function() {
            var blocks = [];
            // Platforms
            if(document.getElementById('ls-ga4').checked) {
                blocks.push(`// === GOOGLE ANALYTICS (GA4) ===\ndocument.addEventListener('form_submit', function(event) {\n  gtag('event', 'form_submit', {\n    'event_category': '[Your Service/Product] Lead',\n    'event_label': '[Your Service/Product] Form Submission'\n  });\n});`);
            }
            if(document.getElementById('ls-tiktok').checked) {
                blocks.push(`// === TIKTOK PIXEL ===\nif (typeof ttq !== 'undefined') {\n  ttq.track('Contact');\n}`);
            }
            if(document.getElementById('ls-meta').checked) {
                blocks.push(`// === META/FACEBOOK PIXEL ===\nif (typeof fbq !== 'undefined') {\n  fbq('track', 'Contact');\n}`);
            }
            if(document.getElementById('ls-triple').checked) {
                blocks.push(`// === TRIPLE WHALE TRACKING ===\nwindow.triplewhale && triplewhale.track && triplewhale.track('LeadStreamEvent');`);
            }
            // Form Builders
            if(document.getElementById('ls-wpforms').checked) {
                blocks.push(`// === WPForms ===\ndocument.addEventListener('wpformsSubmit', function (event) {\n  gtag('event', 'form_submit', {\n    'event_category': '[Your Service/Product] Lead',\n    'event_label': '[Your Service/Product] WPForms Contact Form'\n  });\n});`);
            }
            if(document.getElementById('ls-cf7').checked) {
                blocks.push(`// === CONTACT FORM 7 ===\ndocument.addEventListener('wpcf7mailsent', function(event) {\n  gtag('event', 'form_submit', {\n    'event_category': 'Lead Generation',\n    'event_label': 'Contact Form 7 - ' + event.detail.contactFormId,\n    'value': 1\n  });\n});`);
            }
            if(document.getElementById('ls-gravity').checked) {
                blocks.push(`// === GRAVITY FORMS ===\ndocument.addEventListener('gform_confirmation_loaded', function(event) {\n  gtag('event', 'form_submit', {\n    'event_category': 'Lead Generation',\n    'event_label': 'Gravity Form - ID ' + event.detail.formId,\n    'value': 1\n  });\n});`);
            }
            if(document.getElementById('ls-ninja').checked) {
                blocks.push(`// === NINJA FORMS ===\ndocument.addEventListener('nfFormSubmit', function(event) {\n  gtag('event', 'form_submit', {\n    'event_category': 'Lead Generation',\n    'event_label': 'Ninja Forms - ' + event.detail.formId,\n    'value': 1\n  });\n});`);
            }
            if(document.getElementById('ls-generic').checked) {
                blocks.push(`// === GENERIC FORM (Fallback) ===\ndocument.addEventListener('submit', function(event) {\n  if (event.target.tagName === 'FORM') {\n    gtag('event', 'form_submit', {\n      'event_category': 'Form Interaction',\n      'event_label': 'Generic Form Submit'\n    });\n  }\n});`);
            }
            document.getElementById('custom_footer_js').value = blocks.join('\n\n');
            alert('Starter script loaded! Scroll down to customize and save your code.');
        });
        </script>
        <?php
    }
    
    /**
     * Render FAQ scripts
     */
    private static function render_faq_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($){
            // Accordion toggle
            $('.ls-accordion-toggle').on('click', function(){
                $(this).toggleClass('active')
                    .next('.ls-accordion-panel').slideToggle(200);
            });
            // Copy to header/footer field
            $('.ls-copy-btn').on('click', function(){
                var codeId = $(this).data('copytarget');
                var fieldId = $(this).data('copyfield');
                var code = $('#' + codeId).text().trim();
                var $field = $('#' + fieldId);
                if ($field.length) {
                    $field.val(code);
                    $(this).text('Copied!').delay(1000).queue(function(next){
                        $(this).text($(this).data('copyfield') === 'custom_header_js' ? 'Copy to Header' : 'Copy to Footer');
                        next();
                    });
                } else {
                    navigator.clipboard.writeText(code);
                    $(this).text('Copied!').delay(1000).queue(function(next){
                        $(this).text('Copy Code');
                        next();
                    });
                }
            });
        });
        </script>
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
}
