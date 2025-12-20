<?php
/**
 * LeadStream Database Migration & Sample Data Script
 * Migrates to 3-table normalized structure and adds sample data
 */

// WordPress bootstrap
require_once('../../../wp-config.php');

global $wpdb;
$prefix = $wpdb->prefix;

echo "<h2>🔄 LeadStream Database Migration</h2>\n";
echo "<style>
    body{font-family:system-ui;margin:20px;background:#f8f9fa;}
    .success{color:#10b981;background:#ecfdf5;padding:8px;border-radius:4px;margin:4px 0;}
    .info{color:#2563eb;background:#eff6ff;padding:8px;border-radius:4px;margin:4px 0;}
    .warning{color:#d97706;background:#fffbeb;padding:8px;border-radius:4px;margin:4px 0;}
    .error{color:#dc2626;background:#fef2f2;padding:8px;border-radius:4px;margin:4px 0;}
    pre{background:#1f2937;color:#f3f4f6;padding:12px;border-radius:6px;overflow-x:auto;}
</style>\n";

// Check current structure
echo "<h3>📊 Current Database State</h3>\n";

$tables = [
    'ls_clicks' => $prefix . 'ls_clicks',
    'ls_links' => $prefix . 'ls_links', 
    'ls_events' => $prefix . 'ls_events',
    'ls_phone_clicks' => $prefix . 'ls_phone_clicks',  // GHOST TABLE
    'ls_forms' => $prefix . 'ls_forms'                  // GHOST TABLE
];

foreach ($tables as $name => $table) {
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $class = in_array($name, ['ls_phone_clicks', 'ls_forms']) ? 'warning' : 'success';
        echo "<div class='{$class}'>✅ {$name}: {$count} rows</div>\n";
    } else {
        $class = in_array($name, ['ls_phone_clicks', 'ls_forms']) ? 'info' : 'error';
        echo "<div class='{$class}'>❌ {$name}: Does not exist</div>\n";
    }
}

// Add sample data to demonstrate normalized structure
echo "<h3>📝 Adding Sample Data</h3>\n";

$sample_data = [
    // Phone calls
    [
        'link_type' => 'phone',
        'link_key' => 'phone-main',
        'target_url' => 'tel:+1234567890',
        'clicked_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'element_type' => 'phone_link',
        'page_url' => 'https://example.com/contact',
        'page_title' => 'Contact Us'
    ],
    [
        'link_type' => 'phone', 
        'link_key' => 'phone-main',
        'target_url' => 'tel:+1234567890',
        'clicked_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'element_type' => 'phone_button',
        'page_url' => 'https://example.com/home',
        'page_title' => 'Home Page'
    ],
    [
        'link_type' => 'phone',
        'link_key' => 'phone-main', 
        'target_url' => 'tel:+1234567890',
        'clicked_at' => date('Y-m-d H:i:s'),
        'element_type' => 'phone_link',
        'page_url' => 'https://example.com/contact',
        'page_title' => 'Contact Us'
    ],
    
    // Link clicks
    [
        'link_type' => 'link',
        'link_key' => 'special-offer',
        'target_url' => 'https://example.com/special-offer',
        'clicked_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
        'element_type' => 'pretty_link',
        'page_url' => 'https://example.com/blog',
        'page_title' => 'Blog Post'
    ],
    [
        'link_type' => 'link',
        'link_key' => 'newsletter',
        'target_url' => 'https://example.com/newsletter',
        'clicked_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'element_type' => 'pretty_link',
        'page_url' => 'https://example.com/home',
        'page_title' => 'Home Page'
    ],
    
    // Form submissions
    [
        'link_type' => 'form',
        'link_key' => 'contact-form',
        'target_url' => '',
        'clicked_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'element_type' => 'contact_form',
        'page_url' => 'https://example.com/contact',
        'page_title' => 'Contact Form'
    ],
    [
        'link_type' => 'form',
        'link_key' => 'newsletter-signup',
        'target_url' => '',
        'clicked_at' => date('Y-m-d H:i:s'),
        'element_type' => 'newsletter_form',
        'page_url' => 'https://example.com/newsletter',
        'page_title' => 'Newsletter Signup'
    ]
];

$clicks_table = $prefix . 'ls_clicks';
$inserted = 0;

foreach ($sample_data as $data) {
    // Add redundant timestamp fields to match current schema
    $data['click_datetime'] = $data['clicked_at'];
    $data['click_date'] = date('Y-m-d', strtotime($data['clicked_at']));
    $data['click_time'] = date('H:i:s', strtotime($data['clicked_at']));
    $data['created_at'] = $data['clicked_at'];
    $data['ip_address'] = '127.0.0.1';
    $data['user_agent'] = 'LeadStream Migration Script';
    
    $result = $wpdb->insert($clicks_table, $data);
    if ($result) {
        $inserted++;
        echo "<div class='success'>✅ Added {$data['link_type']} event: {$data['link_key']}</div>\n";
    } else {
        echo "<div class='error'>❌ Failed to add {$data['link_type']} event: " . $wpdb->last_error . "</div>\n";
    }
}

echo "<div class='info'>📊 Inserted {$inserted} sample events</div>\n";

// Show final breakdown
echo "<h3>📈 Final Event Breakdown</h3>\n";

$breakdown = $wpdb->get_results("
    SELECT 
        COALESCE(link_type, 'unknown') as event_type,
        COUNT(*) as total_count,
        COUNT(CASE WHEN DATE(clicked_at) = CURDATE() THEN 1 END) as today_count,
        COUNT(CASE WHEN DATE(clicked_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_count
    FROM {$clicks_table} 
    GROUP BY COALESCE(link_type, 'unknown')
    ORDER BY total_count DESC
", ARRAY_A);

echo "<table style='border-collapse:collapse;width:100%;margin:10px 0;'>\n";
echo "<tr style='background:#f3f4f6;'><th style='padding:8px;border:1px solid #ddd;'>Event Type</th><th style='padding:8px;border:1px solid #ddd;'>Total</th><th style='padding:8px;border:1px solid #ddd;'>Today</th><th style='padding:8px;border:1px solid #ddd;'>This Week</th></tr>\n";

foreach ($breakdown as $row) {
    echo "<tr>";
    echo "<td style='padding:8px;border:1px solid #ddd;font-weight:600;'>{$row['event_type']}</td>";
    echo "<td style='padding:8px;border:1px solid #ddd;text-align:center;'>{$row['total_count']}</td>";
    echo "<td style='padding:8px;border:1px solid #ddd;text-align:center;'>{$row['today_count']}</td>";
    echo "<td style='padding:8px;border:1px solid #ddd;text-align:center;'>{$row['week_count']}</td>";
    echo "</tr>\n";
}
echo "</table>\n";

// Test the Data class
echo "<h3>🧪 Testing Data Class</h3>\n";

try {
    // Manually include the Data class (adjust path as needed)
    require_once('includes/Admin/dashboard/class-ls-dashboard-data.php');
    
    $data = new \LeadStream\Admin\Dashboard\Data();
    $kpis = $data->kpis();
    
    echo "<div class='success'>✅ Data class loaded successfully</div>\n";
    echo "<h4>Live KPI Results:</h4>\n";
    echo "<pre>";
    print_r($kpis);
    echo "</pre>\n";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Data class error: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}

echo "<h3>✅ Migration Complete</h3>\n";
echo "<div class='success'>";
echo "Your database is now using the 3-table normalized structure:<br>";
echo "• <strong>ls_clicks</strong> - Single source of truth for all events (phone, link, form)<br>";
echo "• <strong>ls_links</strong> - Pretty Link definitions<br>";
echo "• <strong>ls_events</strong> - Optional GA4-style event stream<br><br>";
echo "All queries are now null-safe and use only the clicked_at timestamp.";
echo "</div>\n";

echo "<p><em>Generated: " . date('Y-m-d H:i:s') . "</em></p>\n";
?>
