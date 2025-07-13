<?php
/*
Plugin Name: LeadStream: Advanced Analytics Injector
Description: Professional JavaScript injection for advanced lead tracking. Custom event handling for Meta Pixel, Google Analytics (GA4), TikTok Pixel, Triple Whale, Manager, and any analytics platform. Built for agencies and marketers who need precise conversion tracking.
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

// Display validation messages
function leadstream_analytics_admin_validation_notices() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_leadstream-analytics-injector') {
        settings_errors('leadstream_analytics_messages');
    }
}
add_action('admin_notices', 'leadstream_analytics_admin_validation_notices');

// Add admin styles for better UX
function leadstream_analytics_admin_styles() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_leadstream-analytics-injector') {
        ?>
        <style>
        .leadstream-notice {
            position: relative;
        }
        .leadstream-notice p {
            font-size: 14px;
            margin: 10px 0;
        }
        .leadstream-notice strong {
            color: #2c5f2d;
        }
        .leadstream-quick-start {
            margin: 20px 0; 
            padding: 15px; 
            background: #f0f8ff; 
            border-left: 4px solid #0073aa; 
            border-radius: 4px;
        }
        .leadstream-security-notice {
            margin: 20px 0; 
            padding: 15px; 
            background: #fff3cd; 
            border-left: 4px solid #ffc107; 
            border-radius: 4px;
        }
        #load-starter-script {
            margin-right: 10px;
            transition: background-color 0.3s ease;
        }
        #load-starter-script:hover {
            background-color: #0073aa;
            color: white;
            border-color: #0073aa;
        }
        .notice.notice-info {
            border-left-color: #00a0d2;
            background-color: #f0f8ff;
        }
        .notice.notice-warning {
            border-left-color: #ffb900;
            background-color: #fff8e5;
        }
        .notice.notice-error {
            border-left-color: #dc3232;
            background-color: #ffeaea;
        }
        .notice.notice-error strong {
            color: #dc3232;
        }
        .notice.notice-warning strong {
            color: #b07503;
        }
        .notice.updated.notice-success {
            border-left-color: #46b450;
        }
        /* Special styling for conflict warnings */
        .notice.notice-warning.leadstream-notice {
            border-left-width: 5px;
            padding: 15px;
        }
        .notice.notice-warning.leadstream-notice p {
            margin: 8px 0;
        }
        .notice.notice-warning.leadstream-notice small {
            font-style: italic;
            color: #666;
        }
        /* Make error notices more prominent */
        .notice.notice-error {
            border-left-width: 5px;
            padding: 15px;
        }
        .notice.notice-error code {
            background: rgba(220, 50, 50, 0.1);
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }
        </style>
        <?php
    }
}
add_action('admin_head', 'leadstream_analytics_admin_styles');

// Check for Google Analytics plugin conflicts
function leadstream_check_ga_plugin_conflicts() {
    // Include plugin functions if not already loaded
    if (!function_exists('is_plugin_active')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    
    $conflicting_plugins = array();
    
    // Check for Google Site Kit
    if (is_plugin_active('google-site-kit/google-site-kit.php')) {
        $conflicting_plugins[] = 'Google Site Kit';
    }
    
    // Check for other popular GA plugins
    if (is_plugin_active('googleanalytics/googleanalytics.php')) {
        $conflicting_plugins[] = 'Google Analytics for WordPress by MonsterInsights';
    }
    
    if (is_plugin_active('ga-google-analytics/ga-google-analytics.php')) {
        $conflicting_plugins[] = 'GA Google Analytics';
    }
    
    if (is_plugin_active('google-analytics-dashboard-for-wp/gadwp.php')) {
        $conflicting_plugins[] = 'Google Analytics Dashboard Plugin';
    }
    
    return $conflicting_plugins;
}

// Display admin notices
function leadstream_analytics_admin_notices() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_leadstream-analytics-injector') {
        
        // Check for Google Analytics plugin conflicts
        $conflicting_plugins = leadstream_check_ga_plugin_conflicts();
        if (!empty($conflicting_plugins)) {
            $plugin_list = implode(', ', $conflicting_plugins);
            echo '<div class="notice notice-warning is-dismissible leadstream-notice">
                    <p><strong>⚠️ Double-Tracking Warning:</strong> ' . esc_html($plugin_list) . ' is active. If you inject Google Analytics tracking code here, you may send <strong>duplicate events</strong> to Google Analytics. Avoid pasting the same GA4 Measurement ID or event tracking already handled by your existing plugin.</p>
                    <p><small><strong>Tip:</strong> Use LeadStream for <em>custom events only</em> (form submissions, button clicks, etc.) and let your existing plugin handle basic pageviews and setup.</small></p>
                  </div>';
        }
        
        // Check for starter script loaded notice
        if (isset($_GET['starter_loaded']) && $_GET['starter_loaded'] == '1') {
            echo '<div class="notice notice-success is-dismissible leadstream-notice">
                    <p><strong>🚀 Starter tracking script loaded!</strong> The Footer JavaScript box now contains common tracking examples. Review, customize the event labels, and click "Save JavaScript" to activate your events.</p>
                  </div>';
        }
        
        // Check for settings saved notice - WordPress automatically adds settings-updated=true
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
            echo '<div class="notice notice-success is-dismissible leadstream-notice">
                    <p><strong>✅ Your JavaScript settings have been saved successfully!</strong> Your tracking code is now active on your website and will start capturing events.</p>
                  </div>';
        }
    }
}
add_action('admin_notices', 'leadstream_analytics_admin_notices');

// Display settings page content
function leadstream_analytics_settings_display() {
    ?>
    <div class="wrap">
        <h1>LeadStream: Advanced Analytics Injector</h1>
        <p>Professional JavaScript injection for advanced lead tracking. Add your custom code below - no &lt;script&gt; tags needed.</p>
        
        <div class="leadstream-quick-start">
            <h3 style="margin-top: 0;">🚀 Quick Start</h3>
            <p>New to event tracking? Click the button below to load common tracking examples into the Footer JavaScript box.</p>
            <button type="button" id="load-starter-script" class="button button-secondary">Load Starter Script</button>
            <small style="color: #666;">This will populate the footer box with Google Analytics, Google Tag Manager, TikTok, and Triple Whale event examples you can customize.</small>
            
            <div style="margin-top: 12px; padding: 10px; background: #f9f9f9; border-radius: 4px; border-left: 3px solid #ffb900;">
                <small style="color: #b07503;"><strong>⚠️ Already using Google Analytics?</strong> If you have Google Site Kit, MonsterInsights, or another GA plugin active, <strong>don't duplicate the same tracking code here</strong>. Use LeadStream for <em>custom events only</em> (form submissions, button clicks, etc.).</small>
            </div>
        </div>
        
        <div class="leadstream-security-notice">
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
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔍 LeadStream: DOM Content Loaded');
            
            var starterButton = document.getElementById('load-starter-script');
            if (!starterButton) {
                console.error('🔴 LeadStream: Cannot find load-starter-script button');
                return;
            }
            
            starterButton.addEventListener('click', function() {
                console.log('🔥 LeadStream: Button clicked!');
                
                var button = this;
                var originalText = button.textContent;
                button.textContent = 'Loading...';
                button.disabled = true;
                
                var footerTextarea = document.getElementById('custom_footer_js');
                if (!footerTextarea) {
                    console.error('🔴 LeadStream: Could not find custom_footer_js textarea');
                    alert('Error: Could not find the Footer JavaScript textarea. Please refresh the page and try again.');
                    button.textContent = originalText;
                    button.disabled = false;
                    return;
                }
                
                // Load comprehensive starter script
                var starterScript = `// 🚀 LEADSTREAM STARTER SCRIPT - COMPREHENSIVE EVENT TRACKING
// This script provides ready-to-use tracking for common website events
// Customize the event names and labels for your specific needs

// ==========================================
// 🎯 FORM SUBMISSION TRACKING
// ==========================================

// Contact Form 7 (WordPress Plugin)
document.addEventListener('wpcf7mailsent', function(event) {
    // Track successful form submissions
    gtag('event', 'form_submit', {
        'event_category': 'Lead Generation',
        'event_label': 'Contact Form 7 - ' + event.detail.contactFormId,
        'value': 1
    });
    
    // TikTok Pixel tracking
    if (typeof ttq !== 'undefined') {
        ttq.track('Contact');
    }
    
    // Facebook Pixel tracking
    if (typeof fbq !== 'undefined') {
        fbq('track', 'Contact');
    }
    
    console.log('📧 Form submission tracked');
});

// Gravity Forms
document.addEventListener('gform_confirmation_loaded', function(event) {
    gtag('event', 'form_submit', {
        'event_category': 'Lead Generation',
        'event_label': 'Gravity Form - ID ' + event.detail.formId,
        'value': 1
    });
    console.log('📧 Gravity Form submission tracked');
});

// WPForms
document.addEventListener('wpformsFormSubmitButtonClicked', function(event) {
    gtag('event', 'form_start', {
        'event_category': 'Form Interaction',
        'event_label': 'WPForms Start'
    });
});

// Generic form submission (fallback)
document.addEventListener('submit', function(event) {
    if (event.target.tagName === 'FORM') {
        gtag('event', 'form_submit', {
            'event_category': 'Form Interaction',
            'event_label': 'Generic Form Submit'
        });
    }
});

// ==========================================
// 🖱️ BUTTON & LINK TRACKING
// ==========================================

// Track CTA button clicks (customize selectors as needed)
document.addEventListener('click', function(event) {
    var element = event.target;
    
    // Track buttons with specific classes or IDs
    if (element.classList.contains('cta-button') || 
        element.classList.contains('btn-primary') ||
        element.id.includes('get-quote') ||
        element.id.includes('contact-us')) {
        
        gtag('event', 'cta_click', {
            'event_category': 'Call to Action',
            'event_label': element.textContent.trim() || element.id || 'CTA Button',
            'value': 1
        });
        
        // TikTok Pixel
        if (typeof ttq !== 'undefined') {
            ttq.track('ClickButton', {
                content_type: 'button',
                content_name: element.textContent.trim()
            });
        }
        
        console.log('🖱️ CTA button tracked:', element.textContent.trim());
    }
    
    // Track phone number clicks
    if (element.tagName === 'A' && element.href.startsWith('tel:')) {
        gtag('event', 'phone_call', {
            'event_category': 'Contact',
            'event_label': 'Phone Click',
            'value': 1
        });
        console.log('📞 Phone click tracked');
    }
    
    // Track email clicks
    if (element.tagName === 'A' && element.href.startsWith('mailto:')) {
        gtag('event', 'email_click', {
            'event_category': 'Contact',
            'event_label': 'Email Click',
            'value': 1
        });
        console.log('📧 Email click tracked');
    }
});

// ==========================================
// 📏 SCROLL DEPTH TRACKING
// ==========================================

var scrollDepths = [25, 50, 75, 100];
var scrollTracked = [];

window.addEventListener('scroll', function() {
    var scrollPercentage = Math.round((window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100);
    
    scrollDepths.forEach(function(depth) {
        if (scrollPercentage >= depth && scrollTracked.indexOf(depth) === -1) {
            scrollTracked.push(depth);
            
            gtag('event', 'scroll_depth', {
                'event_category': 'Engagement',
                'event_label': depth + '% Scrolled',
                'value': depth
            });
            
            console.log('📜 Scroll depth tracked:', depth + '%');
        }
    });
});

// ==========================================
// ⏱️ TIME ON PAGE TRACKING
// ==========================================

var timeThresholds = [30, 60, 120, 300]; // seconds
var timeTracked = [];
var startTime = Date.now();

setInterval(function() {
    var timeOnPage = Math.floor((Date.now() - startTime) / 1000);
    
    timeThresholds.forEach(function(threshold) {
        if (timeOnPage >= threshold && timeTracked.indexOf(threshold) === -1) {
            timeTracked.push(threshold);
            
            gtag('event', 'time_on_page', {
                'event_category': 'Engagement',
                'event_label': threshold + ' seconds',
                'value': threshold
            });
            
            console.log('⏱️ Time on page tracked:', threshold + ' seconds');
        }
    });
}, 10000); // Check every 10 seconds

// ==========================================
// 🎬 VIDEO TRACKING
// ==========================================

// YouTube video tracking (if embedded)
function onYouTubeIframeAPIReady() {
    var videos = document.querySelectorAll('iframe[src*="youtube.com"]');
    videos.forEach(function(video, index) {
        new YT.Player(video, {
            events: {
                'onStateChange': function(event) {
                    var eventAction = '';
                    switch(event.data) {
                        case YT.PlayerState.PLAYING:
                            eventAction = 'play';
                            break;
                        case YT.PlayerState.PAUSED:
                            eventAction = 'pause';
                            break;
                        case YT.PlayerState.ENDED:
                            eventAction = 'complete';
                            break;
                    }
                    
                    if (eventAction) {
                        gtag('event', 'video_' + eventAction, {
                            'event_category': 'Video',
                            'event_label': 'YouTube Video ' + (index + 1)
                        });
                        console.log('🎬 Video tracked:', eventAction);
                    }
                }
            }
        });
    });
}

// ==========================================
// 📱 CUSTOM EVENT EXAMPLES
// ==========================================

// Track clicks on specific elements (customize as needed)
function trackCustomElement(selector, eventName, eventLabel) {
    document.addEventListener('click', function(event) {
        if (event.target.matches(selector)) {
            gtag('event', eventName, {
                'event_category': 'Custom',
                'event_label': eventLabel
            });
            console.log('✨ Custom event tracked:', eventLabel);
        }
    });
}

// Usage examples (uncomment and customize):
// trackCustomElement('.pricing-button', 'pricing_click', 'Pricing Button');
// trackCustomElement('.social-link', 'social_click', 'Social Media Link');
// trackCustomElement('.download-link', 'file_download', 'File Download');

// ==========================================
// 🚀 INITIALIZATION
// ==========================================

console.log('🎯 LeadStream Analytics: All tracking events loaded successfully!');
console.log('📊 Available tracking:');
console.log('  ✅ Form submissions (Contact Form 7, Gravity Forms, WPForms)');
console.log('  ✅ Button & CTA clicks');
console.log('  ✅ Phone & email clicks');
console.log('  ✅ Scroll depth (25%, 50%, 75%, 100%)');
console.log('  ✅ Time on page (30s, 60s, 2min, 5min)');
console.log('  ✅ Video interactions');
console.log('📝 Customize event names and labels as needed for your website');

// Test if Google Analytics is available
if (typeof gtag === 'undefined') {
    console.warn('⚠️ Google Analytics not detected. Make sure GA4 is properly installed.');
}`;
                
                console.log('📝 LeadStream: Injecting comprehensive script');
                footerTextarea.value = starterScript;
                
                setTimeout(function() {
                    button.textContent = 'Script Loaded! ✅';
                    button.disabled = false;
                    console.log('✅ LeadStream: Comprehensive script loaded successfully');
                }, 1000);
            });
        });
        </script>
    </div>
    <?php
}

// Register settings and fields
function leadstream_analytics_settings_init() {
    register_setting('lead-tracking-js-settings-group', 'custom_header_js', array(
        'sanitize_callback' => 'leadstream_sanitize_javascript',
        'type' => 'string'
    ));
    register_setting('lead-tracking-js-settings-group', 'custom_footer_js', array(
        'sanitize_callback' => 'leadstream_sanitize_javascript',
        'type' => 'string'
    ));
    register_setting('lead-tracking-js-settings-group', 'gtm_container_id', array(
        'sanitize_callback' => 'leadstream_sanitize_gtm_id',
        'type' => 'string'
    ));
    
    add_settings_section(
        'lead-tracking-js-settings-section',
        'Custom JavaScript Injection',
        null,
        'lead-tracking-js-settings-group'
    );
    
    add_settings_field(
        'gtm_container_id_field',
        'Google Tag Manager',
        'leadstream_gtm_field_callback',
        'lead-tracking-js-settings-group',
        'lead-tracking-js-settings-section'
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

// Example: Initialize Facebook Pixel (replace with your actual ID)
// fbq(\'init\', \'YOUR_PIXEL_ID_HERE\');
// fbq(\'track\', \'PageView\');

// Example: Initialize TikTok Pixel (replace with your actual ID)
// !function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=[\"page\",\"track\",\"identify\",\"instances\",\"debug\",\"on\",\"off\",\"once\",\"ready\",\"alias\",\"group\",\"enableCookie\",\"disableCookie\"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i=\"https://analytics.tiktok.com/i18n/pixel/events.js\";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement(\"script\");o.type=\"text/javascript\",o.async=!0,o.src=i+\"?sdkid=\"+e+\"&lib=\"+t;var a=document.getElementsByTagName(\"script\")[0];a.parentNode.insertBefore(o,a)};ttq.load(\'YOUR_PIXEL_ID\');ttq.page();}(window,document,\'ttq\');

// Example: Initialize Triple Whale Pixel (get from your dashboard)
// Paste your Triple Whale base pixel code here

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

// Sanitization for GTM Container ID
function leadstream_sanitize_gtm_id($input) {
    if (empty($input)) {
        return '';
    }
    
    $input = sanitize_text_field($input);
    
    // Validate GTM-XXXXXXX format
    if (!preg_match('/^GTM-[A-Z0-9]{7,}$/', $input)) {
        add_settings_error('gtm_container_id', 'invalid_gtm_id', 
            'Google Tag Manager Container ID must be in format GTM-XXXXXXX');
        return get_option('gtm_container_id', '');
    }
    
    return $input;
}

// Display GTM field
function leadstream_gtm_field_callback() {
    $gtm_id = get_option('gtm_container_id', '');
    echo '<input type="text" id="gtm_container_id" name="gtm_container_id" value="' . esc_attr($gtm_id) . '" placeholder="GTM-XXXXXXX" style="width: 300px;" />';
    echo '<p class="description">Enter your Google Tag Manager Container ID (e.g., GTM-XXXXXXX). This will inject GTM into your site header.</p>';
}

// Custom sanitization for JavaScript - preserves code integrity while ensuring security
function leadstream_sanitize_javascript($input) {
    // Only allow if user has proper capabilities
    if (!current_user_can('manage_options')) {
        return '';
    }
    
    return $input;
}

// Inject header JavaScript
function leadstream_inject_header_js() {
    // Inject Google Tag Manager first (if configured)
    $gtm_id = get_option('gtm_container_id');
    if (!empty($gtm_id)) {
        echo '<!-- Google Tag Manager -->' . "\n";
        echo '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':' . "\n";
        echo 'new Date().getTime(),event:\'gtm.js\'});var f=d.getElementsByTagName(s)[0],' . "\n";
        echo 'j=d.createElement(s),dl=l!=\'dataLayer\'?\'&l=\'+l:\'\';j.async=true;j.src=' . "\n";
        echo '\'https://www.googletagmanager.com/gtm.js?id=\'+i+dl;f.parentNode.insertBefore(j,f);' . "\n";
        echo '})(window,document,\'script\',\'dataLayer\',\'' . esc_js($gtm_id) . '\');</script>' . "\n";
        echo '<!-- End Google Tag Manager -->' . "\n";
    }
    
    // Inject custom header JavaScript
    $header_js = get_option('custom_header_js');
    if (!empty($header_js)) {
        echo '<!-- LeadStream: Custom Header JS -->' . "\n";
        echo '<script type="text/javascript">' . "\n" . $header_js . "\n" . '</script>' . "\n";
    }
}
add_action('wp_head', 'leadstream_inject_header_js', 999);

// Inject footer JavaScript
function leadstream_inject_footer_js() {
    // Inject Google Tag Manager noscript fallback (if configured)
    $gtm_id = get_option('gtm_container_id');
    if (!empty($gtm_id)) {
        echo '<!-- Google Tag Manager (noscript) -->' . "\n";
        echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr($gtm_id) . '"' . "\n";
        echo 'height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
        echo '<!-- End Google Tag Manager (noscript) -->' . "\n";
    }
    
    // Inject custom footer JavaScript
    $footer_js = get_option('custom_footer_js');
    if (!empty($footer_js)) {
        echo '<!-- LeadStream: Custom Footer JS -->' . "\n";
        echo '<script type="text/javascript">' . "\n" . $footer_js . "\n" . '</script>' . "\n";
    }
}
add_action('wp_footer', 'leadstream_inject_footer_js', 999);
