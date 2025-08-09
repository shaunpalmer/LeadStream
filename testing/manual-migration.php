<?php
/**
 * Manual Database Migration for LeadStream Plugin
 * 
 * Run this ONCE to add missing columns to existing installations
 * Access via: /wp-content/plugins/leadstream-analytics-injector/manual-migration.php
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

echo "<h1>🔧 LeadStream Manual Database Migration</h1>";

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
    exit;
}

echo "<h2>🚀 Running Migration</h2>";

// Check if the table exists
$table_exists = $wpdb->get_var($wpdb->prepare(
    "SHOW TABLES LIKE %s",
    $table_name
));

if (!$table_exists) {
    echo "<p style='color: red;'>❌ Table {$table_name} does not exist! Please activate the plugin first.</p>";
    exit;
}

// Check if link_type column exists
$link_type_exists = $wpdb->get_var($wpdb->prepare(
    "SHOW COLUMNS FROM {$table_name} LIKE %s",
    'link_type'
));

// Check if link_key column exists  
$link_key_exists = $wpdb->get_var($wpdb->prepare(
    "SHOW COLUMNS FROM {$table_name} LIKE %s",
    'link_key'
));

$changes_made = false;

// Add missing columns
if (!$link_type_exists) {
    echo "<p>🔧 Adding link_type column...</p>";
    $result = $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN link_type VARCHAR(32) NOT NULL DEFAULT 'utm' AFTER link_id");
    if ($result !== false) {
        echo "<p style='color: green;'>✅ Added link_type column successfully</p>";
        $changes_made = true;
    } else {
        echo "<p style='color: red;'>❌ Failed to add link_type column: " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ link_type column already exists</p>";
}

if (!$link_key_exists) {
    echo "<p>🔧 Adding link_key column...</p>";
    $result = $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN link_key VARCHAR(255) NULL AFTER link_type");
    if ($result !== false) {
        echo "<p style='color: green;'>✅ Added link_key column successfully</p>";
        $changes_made = true;
    } else {
        echo "<p style='color: red;'>❌ Failed to add link_key column: " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ link_key column already exists</p>";
}

// Add indexes for better performance
if ($changes_made) {
    echo "<p>🔧 Adding database indexes...</p>";
    
    $wpdb->query("ALTER TABLE {$table_name} ADD INDEX link_type_idx (link_type)");
    $wpdb->query("ALTER TABLE {$table_name} ADD INDEX link_key_idx (link_key)");
    
    echo "<p style='color: green;'>✅ Added indexes for better performance</p>";
    
    // Migrate existing data to use proper link_type
    echo "<p>🔧 Migrating existing data...</p>";
    $updated = $wpdb->query("UPDATE {$table_name} SET link_type = 'link' WHERE link_id IS NOT NULL AND link_type = 'utm'");
    echo "<p style='color: green;'>✅ Updated {$updated} existing records to use 'link' type</p>";
}

echo "<h2>📊 Updated Table Structure</h2>";

// Show updated table structure
$columns_after = $wpdb->get_results("DESCRIBE {$table_name}");
if ($columns_after) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns_after as $column) {
        $highlight = '';
        if ($column->Field === 'link_type' || $column->Field === 'link_key') {
            $highlight = " style='background: #d4edda;'";
        }
        echo "<tr{$highlight}>";
        echo "<td><strong>{$column->Field}</strong></td>";
        echo "<td>{$column->Type}</td>";
        echo "<td>{$column->Null}</td>";
        echo "<td>{$column->Key}</td>";
        echo "<td>{$column->Default}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>✅ Migration Summary</h2>";

if ($changes_made) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h3 style='margin-top: 0;'>🎉 Migration Completed Successfully!</h3>";
    echo "<p>Your database has been updated with the required columns for phone tracking.</p>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Go back to your LeadStream admin page</li>";
    echo "<li>Refresh the page to see the phone tracking working</li>";
    echo "<li>The SQL errors should now be resolved</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #cce5ff; border: 1px solid #b3d9ff; color: #0066cc; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h3 style='margin-top: 0;'>ℹ️ No Migration Needed</h3>";
    echo "<p>Your database already has all the required columns.</p>";
    echo "<p>If you're still seeing SQL errors, there may be a different issue.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Generated: " . date('Y-m-d H:i:s') . "</em></p>";
echo "<p><a href='../../..wp-admin/admin.php?page=leadstream-analytics-injector'>← Back to LeadStream Admin</a></p>";
?>

<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; }
table { margin: 10px 0; }
th { background: #f1f1f1; text-align: left; }
</style>
