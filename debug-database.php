<?php
/**
 * LeadStream Database Diagnostic Script
 * Run this to see what data is actually in your database tables
 */

// WordPress bootstrap (adjust path if needed)
require_once('../../../wp-config.php');

global $wpdb;
$prefix = $wpdb->prefix;

echo "<h2>LeadStream Database Diagnostic</h2>\n";
echo "<style>table{border-collapse:collapse;width:100%;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background:#f2f2f2;}</style>\n";

// Tables to check
$tables = [
    'ls_clicks' => $prefix . 'ls_clicks',
    'ls_phone_clicks' => $prefix . 'ls_phone_clicks', 
    'ls_forms' => $prefix . 'ls_forms',
    'ls_events' => $prefix . 'ls_events',
    'ls_links' => $prefix . 'ls_links'
];

foreach ($tables as $name => $table) {
    echo "<h3>Table: {$name} ({$table})</h3>\n";
    
    // Check if table exists
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    
    if (!$exists) {
        echo "<p style='color:red;'>❌ Table does not exist</p>\n";
        continue;
    }
    
    echo "<p style='color:green;'>✅ Table exists</p>\n";
    
    // Get row count
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    echo "<p><strong>Total rows:</strong> {$count}</p>\n";
    
    if ($count == 0) {
        echo "<p style='color:orange;'>⚠️ Table is empty</p>\n";
        continue;
    }
    
    // Show table structure
    $columns = $wpdb->get_results("DESCRIBE {$table}", ARRAY_A);
    echo "<h4>Structure:</h4>\n";
    echo "<table>\n";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>\n";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>\n";
    }
    echo "</table>\n";
    
    // Show recent data (last 5 rows)
    $recent = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 5", ARRAY_A);
    if (!empty($recent)) {
        echo "<h4>Recent Data (last 5 rows):</h4>\n";
        echo "<table>\n";
        
        // Headers
        echo "<tr>";
        foreach (array_keys($recent[0]) as $header) {
            echo "<th>{$header}</th>";
        }
        echo "</tr>\n";
        
        // Data rows
        foreach ($recent as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                $value = htmlspecialchars(substr($value, 0, 50));
                echo "<td>{$value}</td>";
            }
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    echo "<hr>\n";
}

// Test the actual Data class queries
echo "<h3>Live Data Class Test</h3>\n";

try {
    if (class_exists('LeadStream\\Admin\\Dashboard\\Data')) {
        $data = new \LeadStream\Admin\Dashboard\Data();
        $kpis = $data->kpis();
        
        echo "<h4>Current KPI Values:</h4>\n";
        echo "<table>\n";
        echo "<tr><th>Metric</th><th>Value</th><th>Delta Abs</th><th>Delta %</th></tr>\n";
        
        foreach ($kpis as $key => $value) {
            if (is_array($value)) {
                echo "<tr><td>{$key}</td><td>{$value['value']}</td><td>{$value['delta_abs']}</td><td>{$value['delta_pct']}%</td></tr>\n";
            }
        }
        echo "</table>\n";
        
    } else {
        echo "<p style='color:red;'>❌ Data class not found</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<p><em>Generated: " . date('Y-m-d H:i:s') . "</em></p>\n";
?>
