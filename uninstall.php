<?php
// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove all plugin options from the database
delete_option('custom_header_js');
delete_option('custom_footer_js');

// For multisite installations, remove options from all sites
if (is_multisite()) {
    global $wpdb;
    
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
    $original_blog_id = get_current_blog_id();
    
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        delete_option('custom_header_js');
        delete_option('custom_footer_js');
    }
    
    switch_to_blog($original_blog_id);
}

// Clear any cached data
wp_cache_flush();
