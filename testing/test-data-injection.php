<?php
/**
 * TEMPORARY TEST DATA INJECTION FOR SPARKLINE TESTING
 * 
 * This script injects realistic test data to showcase sparkline functionality.
 * Run this once to populate test data, then delete when testing is complete.
 * 
 * LOCAL XAMPP USAGE: 
 * http://localhost/wordpress/wp-content/plugins/leadstream-analytics-injector/test-data-injection.php
 */

// Include WordPress to access database
require_once(__DIR__ . '/../../../wp-config.php');

// Security check - only run in development
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    die('This script only runs in development mode (WP_DEBUG must be true)');
}

// Only allow admin users
if (!current_user_can('manage_options')) {
    die('Access denied - admin privileges required');
}

global $wpdb;

echo "<h1>🧪 LeadStream Sparkline Test Data Injection</h1>";
echo "<p><strong>Warning:</strong> This will add test data to your database.</p>";

// Check if we're actually injecting data
if (!isset($_GET['inject']) || $_GET['inject'] !== 'yes') {
    echo '<p><a href="?inject=yes" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">🚀 Inject Test Data</a></p>';
    echo '<p><a href="?cleanup=yes" style="background: #d63638; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">🧹 Clean Up Test Data</a></p>';
    exit;
}

// Cleanup existing test data if requested
if (isset($_GET['cleanup']) && $_GET['cleanup'] === 'yes') {
    echo "<h2>🧹 Cleaning up test data...</h2>";
    
    // Delete test clicks
    $clicks_deleted = $wpdb->query("DELETE FROM {$wpdb->prefix}ls_clicks WHERE link_id IN (SELECT id FROM {$wpdb->prefix}ls_links WHERE slug LIKE 'test-%')");
    
    // Delete test links
    $links_deleted = $wpdb->query("DELETE FROM {$wpdb->prefix}ls_links WHERE slug LIKE 'test-%'");
    
    echo "<p>✅ Deleted {$links_deleted} test links and {$clicks_deleted} test clicks</p>";
    echo '<p><a href="?" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">← Back</a></p>';
    exit;
}

echo "<h2>🚀 Injecting test data...</h2>";

// Test links with different patterns
$test_links = [
    [
        'slug' => 'test-trending-up',
        'target_url' => 'https://example.com/trending-campaign?utm_source=social&utm_medium=facebook',
        'pattern' => 'trending_up', // Few clicks early, more clicks recently
        'description' => 'Trending Up - Social media campaign gaining momentum'
    ],
    [
        'slug' => 'test-trending-down',
        'target_url' => 'https://example.com/declining-offer?utm_source=email&utm_medium=newsletter',
        'pattern' => 'trending_down', // Many clicks early, fewer recently
        'description' => 'Trending Down - Email campaign losing steam'
    ],
    [
        'slug' => 'test-steady-performer',
        'target_url' => 'https://example.com/steady-content?utm_source=organic&utm_medium=search',
        'pattern' => 'steady', // Consistent clicks throughout
        'description' => 'Steady Performer - Consistent organic traffic'
    ],
    [
        'slug' => 'test-viral-spike',
        'target_url' => 'https://example.com/viral-post?utm_source=twitter&utm_medium=social',
        'pattern' => 'spike', // Big spike in the middle
        'description' => 'Viral Spike - Twitter post that went viral'
    ],
    [
        'slug' => 'test-no-activity',
        'target_url' => 'https://example.com/quiet-page?utm_source=direct&utm_medium=none',
        'pattern' => 'none', // No clicks
        'description' => 'No Activity - Link with zero clicks'
    ],
    [
        'slug' => 'test-weekend-warrior',
        'target_url' => 'https://example.com/weekend-deals?utm_source=social&utm_medium=instagram',
        'pattern' => 'weekend', // Higher clicks on weekends
        'description' => 'Weekend Warrior - Higher weekend engagement'
    ]
];

$link_ids = [];

// Insert test links
foreach ($test_links as $link) {
    $result = $wpdb->insert(
        $wpdb->prefix . 'ls_links',
        [
            'slug' => $link['slug'],
            'target_url' => $link['target_url'],
            'created_at' => date('Y-m-d H:i:s', strtotime('-30 days'))
        ],
        ['%s', '%s', '%s']
    );
    
    if ($result) {
        $link_ids[$link['slug']] = $wpdb->insert_id;
        echo "<p>✅ Created link: <strong>{$link['slug']}</strong> - {$link['description']}</p>";
    }
}

// Generate click patterns for the last 14 days
$patterns = [
    'trending_up' => [1, 2, 3, 4, 6, 8, 12, 15, 18, 22, 28, 35, 42, 50],
    'trending_down' => [45, 40, 35, 30, 25, 20, 16, 12, 9, 7, 5, 3, 2, 1],
    'steady' => [15, 14, 16, 15, 17, 14, 16, 15, 16, 14, 15, 17, 14, 16],
    'spike' => [5, 6, 8, 12, 25, 45, 80, 35, 15, 8, 6, 5, 4, 3],
    'none' => [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
    'weekend' => [8, 5, 6, 7, 6, 18, 22, 9, 6, 7, 8, 6, 19, 25] // Higher on weekends (days 6,7,13,14)
];

// Insert clicks for each pattern
foreach ($test_links as $link) {
    if (!isset($link_ids[$link['slug']]) || !isset($patterns[$link['pattern']])) {
        continue;
    }
    
    $link_id = $link_ids[$link['slug']];
    $pattern = $patterns[$link['pattern']];
    $total_clicks = 0;
    
    for ($day = 13; $day >= 0; $day--) {
        $date = date('Y-m-d', strtotime("-{$day} days"));
        $clicks_for_day = $pattern[13 - $day];
        
        // Insert individual clicks throughout the day
        for ($click = 0; $click < $clicks_for_day; $click++) {
            $hour = rand(8, 22); // Clicks between 8 AM and 10 PM
            $minute = rand(0, 59);
            $second = rand(0, 59);
            
            $click_time = $date . ' ' . sprintf('%02d:%02d:%02d', $hour, $minute, $second);
            
            $wpdb->insert(
                $wpdb->prefix . 'ls_clicks',
                [
                    'link_id' => $link_id,
                    'ip_address' => long2ip(rand(0, 4294967295)), // Random IP
                    'user_agent' => 'Mozilla/5.0 (Test Data) Chrome/91.0.4472.124',
                    'clicked_at' => $click_time
                ],
                ['%d', '%s', '%s', '%s']
            );
        }
        
        $total_clicks += $clicks_for_day;
    }
    
    echo "<p>📊 Generated <strong>{$total_clicks} clicks</strong> for {$link['slug']}</p>";
}

echo "<hr>";
echo "<h2>🎉 Test Data Injection Complete!</h2>";
echo "<p>You can now test the sparklines:</p>";
echo "<ul>";
echo "<li>📊 <strong>List Table:</strong> Go to LeadStream → Pretty Links to see mini sparklines</li>";
echo "<li>📈 <strong>Dashboard Widget:</strong> Check your WordPress Dashboard for the overall sparkline</li>";
echo "<li>🧪 <strong>Different Patterns:</strong> Each test link shows a different trend pattern</li>";
echo "</ul>";

echo "<h3>🌐 Access URLs for Local Testing:</h3>";
echo "<ul>";
echo "<li><strong>Pretty Links Dashboard:</strong><br>";
echo "<code>http://localhost/wordpress/wp-admin/admin.php?page=leadstream-analytics-injector&tab=links</code></li>";
echo "<li><strong>WordPress Dashboard (for widget):</strong><br>";
echo "<code>http://localhost/wordpress/wp-admin/</code></li>";
echo "</ul>";

echo "<h3>🔗 Test Links Created:</h3>";
echo "<ul>";
foreach ($test_links as $link) {
    $short_url = home_url("/l/{$link['slug']}");
    echo "<li><strong>{$link['slug']}</strong>: <a href='{$short_url}' target='_blank'>{$short_url}</a><br>";
    echo "<small>{$link['description']}</small></li>";
}
echo "</ul>";

echo "<hr>";
echo '<p style="background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;">';
echo '<strong>⚠️ Remember:</strong> When testing is complete, run the cleanup to remove test data:<br>';
echo '<a href="?cleanup=yes" style="background: #d63638; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">🧹 Clean Up Test Data</a>';
echo '</p>';

echo '<p><a href="' . admin_url('admin.php?page=leadstream-analytics-injector&tab=links') . '" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">📊 View Pretty Links Dashboard</a></p>';

echo "<hr>";
echo "<h3>📍 Quick Access Links for Local Testing:</h3>";
echo '<div style="background: #f0f6fc; padding: 15px; border-left: 4px solid #2271b1; margin: 10px 0;">';
echo '<p><strong>🚀 Ready to test? Use these direct links:</strong></p>';
echo '<p>📊 <a href="http://localhost/wordpress/wp-admin/admin.php?page=leadstream-analytics-injector&tab=links" target="_blank" style="text-decoration: none;">Pretty Links with Sparklines →</a></p>';
echo '<p>📈 <a href="http://localhost/wordpress/wp-admin/" target="_blank" style="text-decoration: none;">WordPress Dashboard (see widget) →</a></p>';
echo '</div>';
?>
