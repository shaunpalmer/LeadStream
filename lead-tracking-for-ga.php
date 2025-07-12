<?php
/*
Plugin Name: Lead Tracking for Google Analytics
Description: A simple plugin to inject custom JavaScript for lead tracking.
Version: 1.0
Author: shaun palmer
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add settings page to admin menu
function nikkis_lead_tracking_settings_page() {
    add_menu_page(
        'JavaScript Injection', 
        'JS Injection', 
        'manage_options', 
        'lead-tracking-for-ga', 
        'nikkis_lead_tracking_settings_display'
    );
}
add_action('admin_menu', 'nikkis_lead_tracking_settings_page');

// Display settings page content
function nikkis_lead_tracking_settings_display() {
    ?>
    <div class="wrap">
        <h1>JavaScript Injection for Lead Tracking</h1>
        <p>Add your custom JavaScript code below. No need for &lt;script&gt; tags.</p>
        <form action='options.php' method='post'>
            <?php
                settings_fields('lead-tracking-js-settings-group');
                do_settings_sections('lead-tracking-js-settings-group');
                submit_button('Save JavaScript');
            ?>
        </form>
    </div>
    <?php
}

// Register settings and fields
function nikkis_lead_tracking_settings_init() {
    register_setting('lead-tracking-js-settings-group', 'custom_header_js', array(
        'sanitize_callback' => 'nikkis_sanitize_javascript'
    ));
    register_setting('lead-tracking-js-settings-group', 'custom_footer_js', array(
        'sanitize_callback' => 'nikkis_sanitize_javascript'
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
        'nikkis_header_js_field_callback',
        'lead-tracking-js-settings-group',
        'lead-tracking-js-settings-section'
    );
    
    add_settings_field(
        'custom_footer_js_field',
        'Footer JavaScript',
        'nikkis_footer_js_field_callback',
        'lead-tracking-js-settings-group',
        'lead-tracking-js-settings-section'
    );
}
add_action('admin_init', 'nikkis_lead_tracking_settings_init');

// Callback for header JS field
function nikkis_header_js_field_callback() {
    $header_js = get_option('custom_header_js');
    echo '<textarea id="custom_header_js" name="custom_header_js" rows="15" cols="80" style="width: 100%; max-width: 800px; font-family: Consolas, Monaco, monospace; font-size: 14px; background: #f1f1f1; border: 1px solid #ddd; padding: 10px;" placeholder="// Add your JavaScript code here
console.log(\'Header JS loaded\');">' . esc_textarea($header_js) . '</textarea>';
    echo '<p class="description">JavaScript code to inject in the &lt;head&gt; section. No &lt;script&gt; tags needed.</p>';
}

// Callback for footer JS field
function nikkis_footer_js_field_callback() {
    $footer_js = get_option('custom_footer_js');
    echo '<textarea id="custom_footer_js" name="custom_footer_js" rows="15" cols="80" style="width: 100%; max-width: 800px; font-family: Consolas, Monaco, monospace; font-size: 14px; background: #f1f1f1; border: 1px solid #ddd; padding: 10px;" placeholder="// Add your JavaScript code here
document.addEventListener(\'DOMContentLoaded\', function() {
    console.log(\'Footer JS loaded\');
});">' . esc_textarea($footer_js) . '</textarea>';
    echo '<p class="description">JavaScript code to inject before closing &lt;/body&gt; tag. No &lt;script&gt; tags needed.</p>';
}

// Custom sanitization for JavaScript - preserves code integrity
function nikkis_sanitize_javascript($input) {
    // Only basic sanitization - preserve JavaScript syntax
    return trim($input);
}

// Inject header JavaScript
function nikkis_inject_header_js() {
    $header_js = get_option('custom_header_js');
    if (!empty($header_js)) {
        echo '<!-- GA tracking here -->';
        echo '<script type="text/javascript">' . $header_js . '</script>';
    }
}
add_action('wp_head', 'nikkis_inject_header_js', 999);

// Inject footer JavaScript
function nikkis_inject_footer_js() {
    $footer_js = get_option('custom_footer_js');
    if (!empty($footer_js)) {
        echo '<!-- GA tracking here -->';
        echo '<script type="text/javascript">' . $footer_js . '</script>';
    }
}
add_action('wp_footer', 'nikkis_inject_footer_js', 999);