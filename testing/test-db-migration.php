<?php
/**
 * LeadStream Database Migration Test Script
 * 
 * Run this to test database migration and phone tracking functionality
 * Place in plugin root and access via: /wp-content/plugins/leadstream-analytics-injector/test-db-migration.php
 */

// WordPress security check
if (!defined('ABSPATH')) {
    // Load WordPress if not already loaded
    require_once('../../../wp-load.php');
}

// Security check - only admins can run this
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

echo "<h1>🔧 LeadStream Database Migration Test</h1>";

global $wpdb;
$table_name = $wpdb->prefix . 'ls_clicks';

echo "<h2>📊 Current Table Structure</h2>";

// Show current table structure
$columns = $wpdb->get_results("DESCRIBE {$table_name}");
if ($columns) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td><strong>{$column->Field}</strong></td>";
        echo "<td>{$column->Type}</td>";
        echo "<td>{$column->Null}</td>";
        echo "<td>{$column->Key}</td>";
        echo "<td>{$column->Default}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Table {$table_name} does not exist!</p>";
}

echo "<h2>📈 Sample Data</h2>";

// Check for sample data by link_type
$link_types = $wpdb->get_results("SELECT link_type, COUNT(*) as count FROM {$table_name} GROUP BY link_type");
if ($link_types) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Link Type</th><th>Count</th></tr>";
    foreach ($link_types as $type) {
        echo "<tr><td><strong>{$type->link_type}</strong></td><td>{$type->count}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No data found in clicks table.</p>";
}

echo "<h2>📞 Phone Click Test</h2>";

// Show recent phone clicks
$phone_clicks = $wpdb->get_results($wpdb->prepare(
    "SELECT link_key, clicked_at, ip_address 
     FROM {$table_name} 
     WHERE link_type = %s 
     ORDER BY clicked_at DESC 
     LIMIT 5",
    'phone'
));

if ($phone_clicks) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Phone Number</th><th>Clicked At</th><th>IP Address</th></tr>";
    foreach ($phone_clicks as $click) {
        echo "<tr>";
        echo "<td><strong>{$click->link_key}</strong></td>";
        echo "<td>{$click->clicked_at}</td>";
        echo "<td>{$click->ip_address}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No phone clicks found. Try clicking a phone number on your site!</p>";
}

echo "<h2>🔗 Pretty Link Test</h2>";

// Show recent link clicks
$link_clicks = $wpdb->get_results($wpdb->prepare(
    "SELECT link_key, clicked_at, target_url 
     FROM {$table_name} 
     WHERE link_type = %s 
     ORDER BY clicked_at DESC 
     LIMIT 5",
    'link'
));

if ($link_clicks) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Link Key</th><th>Target URL</th><th>Clicked At</th></tr>";
    foreach ($link_clicks as $click) {
        echo "<tr>";
        echo "<td><strong>{$click->link_key}</strong></td>";
        echo "<td>" . esc_html(substr($click->target_url, 0, 50)) . "...</td>";
        echo "<td>{$click->clicked_at}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No link clicks found. Try using a pretty link!</p>";
}

echo "<h2>✅ Migration Status</h2>";

// Check if key columns exist
$has_link_type = $wpdb->get_var("SHOW COLUMNS FROM {$table_name} LIKE 'link_type'");
$has_link_key = $wpdb->get_var("SHOW COLUMNS FROM {$table_name} LIKE 'link_key'");

if ($has_link_type && $has_link_key) {
    echo "<p style='color: green; font-weight: bold;'>✅ Migration completed successfully!</p>";
    echo "<p>Both <code>link_type</code> and <code>link_key</code> columns are present.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ Migration incomplete!</p>";
    echo "<p>Missing columns:</p>";
    echo "<ul>";
    if (!$has_link_type) echo "<li><code>link_type</code></li>";
    if (!$has_link_key) echo "<li><code>link_key</code></li>";
    echo "</ul>";
    echo "<p><strong>Action needed:</strong> Deactivate and reactivate the LeadStream plugin to trigger migration.</p>";
}

echo "<hr>";
echo "<p><em>Generated: " . date('Y-m-d H:i:s') . "</em></p>";
?>

<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; }
table { margin: 10px 0; }
th { background: #f1f1f1; text-align: left; }
</style>
