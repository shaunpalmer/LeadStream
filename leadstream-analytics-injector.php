<?php
/*
Plugin Name: LeadStream: Advanced Analytics Injector
Description: Professional JavaScript injection for advanced lead tracking. Custom event handling for Meta Pixel, Google Analytics (GA4), TikTok Pixel, Triple Whale, and any analytics platform. Built for agencies and marketers who need precise conversion tracking.
Version: 1.0
Author: shaun palmer
Text Domain: leadstream-analytics
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add settings page to admin menu
function leadstream_analytics_settings_page() {
    add_menu_page(
        'LeadStream Analytics', 
        'LeadStream', 
        'manage_options', 
        'leadstream-analytics-injector', 
        'leadstream_analytics_settings_display'
    );
}
add_action('admin_menu', 'leadstream_analytics_settings_page');

// Show 'Settings saved!' notice after saving
add_action('admin_notices', function() {
    if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved! Please refresh this page to see changes.</p></div>';
    }
    // GTM notice if container is set
    $gtm_id = get_option('leadstream_gtm_id');
    if (!empty($gtm_id) && preg_match('/^GTM-[A-Z0-9]+$/i', $gtm_id)) {
        echo '<div class="notice notice-info is-dismissible"><p>Google Tag Manager container loaded (<strong>' . esc_html($gtm_id) . '</strong>). Configure triggers and tags in GTM dashboard.</p></div>';
    }
});

// Inject GTM loader in <head>
add_action('wp_head', function() {
    $gtm_id = get_option('leadstream_gtm_id');
    if (!empty($gtm_id) && preg_match('/^GTM-[A-Z0-9]+$/i', $gtm_id)) {
        echo "<!-- Google Tag Manager -->\n";
        echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . esc_js($gtm_id) . "');</script>\n";
        echo "<!-- End Google Tag Manager -->\n";
    }
}, 998);

// Inject GTM <noscript> fallback in footer
add_action('wp_footer', function() {
    $gtm_id = get_option('leadstream_gtm_id');
    if (!empty($gtm_id) && preg_match('/^GTM-[A-Z0-9]+$/i', $gtm_id)) {
        echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr($gtm_id) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
    }
}, 998);

// Display settings page content
function leadstream_analytics_settings_display() {
    ?>
    <div class="wrap">
        <h1>LeadStream: Advanced Analytics Injector</h1>
        <p>Professional JavaScript injection for advanced lead tracking. Add your custom code below - no &lt;script&gt; tags needed.</p>
        <!-- GTM Field -->
        <form action='options.php' method='post'>
            <?php
                settings_fields('lead-tracking-js-settings-group');
                do_settings_sections('lead-tracking-js-settings-group');
            ?>
            <table class="form-table" style="margin-bottom: 24px;">
                <tr>
                    <th scope="row"><label for="leadstream_gtm_id">Google Tag Manager ID</label></th>
                    <td>
                        <input name="leadstream_gtm_id" id="leadstream_gtm_id" type="text" value="<?php echo esc_attr(get_option('leadstream_gtm_id', '')); ?>" placeholder="GTM-XXXXXXX" size="20" />
                        <p class="description">Paste your GTM container ID (e.g. GTM-ABCDE12). No script tags—just the ID.</p>
                    </td>
                </tr>
            </table>
            <!-- ...existing toggle switches and submit button... -->
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
            <?php submit_button('Save JavaScript'); ?>
        </form>
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
        
        <script>
        document.getElementById('load-starter-script').addEventListener('click', function() {
            var starterScript = `// LeadStream Starter Script - Common Event Tracking Examples
// Customize the event labels and categories for your site

// Example 1: Track WPForms submission (fires on any WPForms form)
document.addEventListener('wpformsSubmit', function (event) {
  gtag('event', 'form_submit', {
    'event_category': 'Lead',
    'event_label': 'WPForms Contact Form'
  });
}, false);

// Example 2: Track Contact Form 7 submission
document.addEventListener('wpcf7mailsent', function(event) {
  gtag('event', 'form_submit', {
    'event_category': 'Lead', 
    'event_label': 'Contact Form 7'
  });
});

// Example 3: Track a CTA button click by ID (edit ID as needed)
var cateringBtn = document.getElementById('order-catering-btn');
if (cateringBtn) {
  cateringBtn.addEventListener('click', function () {
    gtag('event', 'cta_quote_click', {
      'event_category': 'CTA',
      'event_label': 'Order [Your Service/Product] - Main Hero'
    });
  });
}

// Example 4: Track phone number clicks (all tel: links)
document.querySelectorAll('a[href^="tel:"]').forEach(function(link) {
  link.addEventListener('click', function() {
    gtag('event', 'phone_click', {
      'event_category': 'Lead',
      'event_label': 'Phone Number Click'
    });
  });
});

// Example 5: Track email clicks (all mailto: links)
document.querySelectorAll('a[href^="mailto:"]').forEach(function(link) {
  link.addEventListener('click', function() {
    gtag('event', 'email_click', {
      'event_category': 'Lead', 
      'event_label': 'Email Click'
    });
  });
});

// Example 6: Track scroll depth (75% page scroll)
let scrollTracked = false;
window.addEventListener('scroll', function() {
  if (!scrollTracked && window.scrollY > document.body.scrollHeight * 0.75) {
    gtag('event', 'scroll_depth', {
      'event_category': 'Engagement',
      'event_label': '75% Page Scroll'
    });
    scrollTracked = true;
  }
});

// Replace 'event_label' and 'event_category' with your own descriptions.
// Add more listeners for other forms, buttons, or actions as needed.`;
            
            document.getElementById('custom_footer_js').value = starterScript;
            alert('Starter script loaded! Scroll down to see the examples in the Footer JavaScript box. Customize the labels and add your own tracking events.');
        });
        </script>
    </div>
    <?php
}

// Register settings and fields
function leadstream_analytics_settings_init() {
    register_setting('lead-tracking-js-settings-group', 'custom_header_js', array(
        'sanitize_callback' => 'leadstream_sanitize_javascript'
    ));
    register_setting('lead-tracking-js-settings-group', 'custom_footer_js', array(
        'sanitize_callback' => 'leadstream_sanitize_javascript'
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
        'leadstream_header_js_field_callback',
        'lead-tracking-js-settings-group',
        'lead-tracking-js-settings-section'
    );
    
    add_settings_field(
        'custom_footer_js_field',
        'Footer JavaScript',
        'leadstream_footer_js_field_callback',
        'lead-tracking-js-settings-group',
        'lead-tracking-js-settings-section'
    );
    
    add_settings_field(
        'leadstream_gtm_id_field',
        'Google Tag Manager ID',
        'leadstream_gtm_id_field_callback',
        'lead-tracking-js-settings-group',
        'lead-tracking-js-settings-section'
    );
}
add_action('admin_init', 'leadstream_analytics_settings_init');

// Callback for header JS field
function leadstream_header_js_field_callback() {
    $header_js = get_option('custom_header_js');
    echo '<textarea id="custom_header_js" name="custom_header_js" rows="15" cols="80" style="width: 100%; max-width: 800px; font-family: Consolas, Monaco, monospace; font-size: 14px; background: #f1f1f1; border: 1px solid #ddd; padding: 10px;" placeholder="// Header JavaScript - typically for setup code or early-loading scripts

// Example: Initialize tracking pixel (replace with your actual ID)
// fbq(\'init\', \'YOUR_PIXEL_ID_HERE\');
// fbq(\'track\', \'PageView\');

// Example: Custom variable setup
// window.customTrackingEnabled = true;

// Add your header JavaScript here...">' . esc_textarea($header_js) . '</textarea>';
    echo '<p class="description">JavaScript code to inject in the &lt;head&gt; section. Best for setup code and early-loading scripts. No &lt;script&gt; tags needed.</p>';
}

// Callback for footer JS field
function leadstream_footer_js_field_callback() {
    $footer_js = get_option('custom_footer_js');
    echo '<textarea id="custom_footer_js" name="custom_footer_js" rows="15" cols="80" style="width: 100%; max-width: 800px; font-family: Consolas, Monaco, monospace; font-size: 14px; background: #f1f1f1; border: 1px solid #ddd; padding: 10px;" placeholder="// Footer JavaScript - perfect for event tracking after page loads

// Example: Track form submissions
document.addEventListener(\'wpformsSubmit\', function(event) {
  gtag(\'event\', \'form_submit\', {
    \'event_category\': \'Lead\',
    \'event_label\': \'Contact Form\'
  });
});

// Example: Track button clicks
document.getElementById(\'your-button-id\').addEventListener(\'click\', function() {
  gtag(\'event\', \'button_click\', {
    \'event_category\': \'CTA\',
    \'event_label\': \'Get Quote Button\'
  });
});

// Click \'Load Starter Script\' above for more examples...">' . esc_textarea($footer_js) . '</textarea>';
    echo '<p class="description">JavaScript code to inject before closing &lt;/body&gt; tag. Perfect for event tracking and user interaction. No &lt;script&gt; tags needed.</p>';
}

// Callback for GTM ID field
function leadstream_gtm_id_field_callback() {
    $gtm_id = get_option('leadstream_gtm_id');
    echo '<input name="leadstream_gtm_id" id="leadstream_gtm_id" type="text" value="' . esc_attr($gtm_id) . '" placeholder="GTM-XXXXXXX" size="20" />';
    echo '<p class="description">Paste your GTM container ID (e.g. GTM-ABCDE12). No script tags—just the ID.</p>';
}

// Custom sanitization for JavaScript - preserves code integrity while ensuring security
// DRY placeholder check for both header and footer JS
function leadstream_check_placeholder($code, $field) {
    $pattern = '/\[Your Service\/Product\]/i';
    if (preg_match($pattern, $code)) {
        switch ($field) {
            case 'header':
                add_settings_error(
                    'custom_header_js',
                    'leadstream_placeholder_header',
                    'Warning: Please replace [Your Service/Product] with your actual service or product name before using this code in the header.',
                    'error'
                );
                break;
            case 'footer':
                add_settings_error(
                    'custom_footer_js',
                    'leadstream_placeholder_footer',
                    'Warning: Please replace [Your Service/Product] with your actual service or product name before using this code in the footer.',
                    'error'
                );
                break;
        }
    }
}

function leadstream_sanitize_javascript($input) {
    // Only allow if user has proper capabilities
    if (!current_user_can('manage_options')) {
        return '';
    }
    $sanitized = trim($input);
    // Security check: Block potentially dangerous PHP tags (shouldn't be in JS anyway)
    if (strpos($sanitized, '<?') !== false) {
        return '';
    }
    // Determine which field is being sanitized
    $field = '';
    if (isset($_POST['option_page'])) {
        if (isset($_POST['custom_header_js']) && $_POST['custom_header_js'] === $input) {
            $field = 'header';
        } elseif (isset($_POST['custom_footer_js']) && $_POST['custom_footer_js'] === $input) {
            $field = 'footer';
        }
    }
    if ($field) {
        leadstream_check_placeholder($sanitized, $field);
        // Double-injection warning: if header and footer JS are identical
        if ($field === 'header' || $field === 'footer') {
            $header_js = isset($_POST['custom_header_js']) ? trim($_POST['custom_header_js']) : get_option('custom_header_js', '');
            $footer_js = isset($_POST['custom_footer_js']) ? trim($_POST['custom_footer_js']) : get_option('custom_footer_js', '');
            if ($header_js !== '' && $footer_js !== '' && $header_js === $footer_js) {
                add_settings_error(
                    'leadstream_double_injection',
                    'leadstream_double_injection_warning',
                    'Warning: You are injecting the same code in both header and footer. This may cause double tracking or conflicts. Please use different scripts for each section.',
                    'warning'
                );
            }
        }
    }
    return $sanitized;
}

// Inject header JavaScript
function leadstream_inject_header_js() {
    $header_js = get_option('custom_header_js');
    $inject_header = get_option('leadstream_inject_header', 1);
    if (!empty($header_js) && $inject_header) {
        echo '<!-- LeadStream: Custom Header JS -->' . "\n";
        echo '<script type="text/javascript">' . "\n" . $header_js . "\n" . '</script>' . "\n";
    }
}
add_action('wp_head', 'leadstream_inject_header_js', 999);

// Inject footer JavaScript
function leadstream_inject_footer_js() {
    $footer_js = get_option('custom_footer_js');
    $inject_footer = get_option('leadstream_inject_footer', 1);
    if (!empty($footer_js) && $inject_footer) {
        echo '<!-- LeadStream: Custom Footer JS -->' . "\n";
        echo '<script type="text/javascript">' . "\n" . $footer_js . "\n" . '</script>' . "\n";
    }
}
add_action('wp_footer', 'leadstream_inject_footer_js', 999);
