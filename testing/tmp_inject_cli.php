<?php
// Temporary CLI-only injector for LeadStream testing.
if (php_sapi_name() !== 'cli') { die('Run this from CLI only'); }

// Bootstrap WordPress
$wp = dirname(__DIR__, 2) . '/wp-load.php';
if (!file_exists($wp)) { echo "WP not found at {$wp}\n"; exit(1); }
require_once $wp;

if (!defined('WP_DEBUG') || !WP_DEBUG) {
    echo "WP_DEBUG must be true to run this script\n"; exit(1);
}

global $wpdb;
$prefix = $wpdb->prefix;

echo "Inserting synthetic test rows...\n";

// Insert two phone clicks into ls_clicks
for ($i = 0; $i < 2; $i++) {
    $wpdb->insert($prefix . 'ls_clicks', [
        'link_id' => null,
        'link_type' => 'phone',
        'link_key' => 'tel-test',
        'target_url' => 'tel:+15551234' . rand(100,999),
        'clicked_at' => current_time('mysql'),
        'click_datetime' => current_time('mysql'),
        'click_date' => current_time('Y-m-d'),
        'click_time' => current_time('H:i:s'),
        'created_at' => current_time('mysql'),
        'ip_address' => '127.0.0.' . rand(1,254),
        'user_agent' => 'LeadStream-Test/1.0',
    ], ['%d','%s','%s','%s','%s','%s','%s','%s','%s','%s']);
}

// Insert two form_submit events into ls_events
for ($i = 0; $i < 2; $i++) {
    $wpdb->insert($prefix . 'ls_events', [
        'event_name' => 'form_submit',
        'event_type' => 'form_submit',
        'event_params' => json_encode(['source' => 'cli-test']),
        'user_id' => get_current_user_id() ?: null,
        'ip_address' => '127.0.0.' . rand(1,254),
        'created_at' => current_time('mysql'),
    ], ['%s','%s','%s','%d','%s','%s']);
}

echo "Done.\n";
