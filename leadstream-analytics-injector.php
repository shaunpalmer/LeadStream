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

// Display settings page content
function leadstream_analytics_settings_display() {
    ?>
    <div class="wrap">
        <h1>LeadStream: Advanced Analytics Injector</h1>
        <p>Professional JavaScript injection for advanced lead tracking. Add your custom code below - no &lt;script&gt; tags needed.</p>
        
        <div style="margin: 20px 0; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa; border-radius: 4px;">
            <h3 style="margin-top: 0;">🚀 Quick Start</h3>
            <p>New to event tracking? Click the button below to load common tracking examples into the Footer JavaScript box.</p>
            <button type="button" id="load-starter-script" class="button button-secondary" style="margin-right: 10px;">Load Starter Script</button>
            <small style="color: #666;">This will populate the footer box with Google Analytics event tracking examples you can customize.</small>
        </div>
        
        <div style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
            <h3 style="margin-top: 0;">⚠️ Security & Privacy Notice</h3>
            <ul style="margin: 10px 0;">
                <li><strong>Admin Only:</strong> Only trusted administrators should add JavaScript code.</li>
                <li><strong>GDPR Compliance:</strong> Ensure your tracking complies with local privacy laws. Avoid collecting personal data without consent.</li>
                <li><strong>Code Safety:</strong> Only paste JavaScript from trusted sources. All code runs on your website frontend.</li>
                <li><strong>No Default Tracking:</strong> This plugin does not track users by default - only your custom code will run.</li>
            </ul>
        </div>
        
        <form action='options.php' method='post'>
            <?php
                settings_fields('lead-tracking-js-settings-group');
                do_settings_sections('lead-tracking-js-settings-group');
                submit_button('Save JavaScript');
            ?>
        </form>
        
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
    if (!empty($header_js)) {
        echo '<!-- LeadStream: Custom Header JS -->' . "\n";
        echo '<script type="text/javascript">' . "\n" . $header_js . "\n" . '</script>' . "\n";
    }
}
add_action('wp_head', 'leadstream_inject_header_js', 999);

// Inject footer JavaScript
function leadstream_inject_footer_js() {
    $footer_js = get_option('custom_footer_js');
    if (!empty($footer_js)) {
        echo '<!-- LeadStream: Custom Footer JS -->' . "\n";
        echo '<script type="text/javascript">' . "\n" . $footer_js . "\n" . '</script>' . "\n";
    }
}
add_action('wp_footer', 'leadstream_inject_footer_js', 999);
