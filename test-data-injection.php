<?php
/**
 * LeadStream Test Data Injection (Pretty Links + Phone Clicks)
 *
 * Dev-only helper to generate realistic data for dashboards/sparklines.
 * URL: http(s)://your-site/wp-content/plugins/leadstream-analytics-injector/test-data-injection.php
 */

require_once __DIR__ . '/../../../wp-config.php';

if (!defined('WP_DEBUG') || !WP_DEBUG) { die('Dev only: enable WP_DEBUG to run.'); }
if (!current_user_can('manage_options')) { die('Access denied'); }

global $wpdb;

function ls_td_link($label, $query) {
    $base = add_query_arg([], basename(__FILE__));
    return '<a href="' . esc_url($base . '?' . http_build_query($query)) . '" class="button" style="margin-right:8px;">' . esc_html($label) . '</a>';
}

echo '<div style="font-family:system-ui,Segoe UI,Roboto,Arial; max-width:900px; margin:20px auto;">';
echo '<h1>🧪 LeadStream Test Data</h1>';
echo '<p>This tool seeds test data for Pretty Links and Phone clicks to demo analytics and sparklines.</p>';

$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';

// Actions menu
if (!$action) {
    echo '<div style="display:flex; gap:8px; flex-wrap:wrap; margin:16px 0;">';
    echo ls_td_link('Inject Pretty Links', ['action'=>'inject_links']);
    echo ls_td_link('Inject Phone Clicks', ['action'=>'inject_phone']);
    echo ls_td_link('Cleanup All Test Data', ['action'=>'cleanup']);
    echo '</div>';
    echo '<p style="background:#fff3cd; padding:10px; border-left:4px solid #ffc107;">Tip: Re-run injections to refresh randomized timestamps.</p>';
    echo '</div>';
    exit;
}

// Cleanup common helper
function ls_td_cleanup() {
    global $wpdb;
    $deleted_clicks = $wpdb->query("DELETE FROM {$wpdb->prefix}ls_clicks WHERE target_url LIKE 'tel:test-%' OR page_title LIKE 'Test %' OR user_agent LIKE 'Mozilla/5.0 (Test Data)%' OR referrer LIKE 'https://example.com/test/%'");
    $deleted_clicks += $wpdb->query("DELETE FROM {$wpdb->prefix}ls_clicks WHERE link_id IN (SELECT id FROM {$wpdb->prefix}ls_links WHERE slug LIKE 'test-%')");
    $deleted_links = $wpdb->query("DELETE FROM {$wpdb->prefix}ls_links WHERE slug LIKE 'test-%'");
    return [$deleted_links, $deleted_clicks];
}

if ($action === 'cleanup') {
    list($l, $c) = ls_td_cleanup();
    echo '<p>🧹 Removed ' . intval($l) . ' test links and ' . intval($c) . ' test clicks.</p>';
    echo ls_td_link('Back', []);
    echo '</div>'; exit;
}

if ($action === 'inject_links') {
    // Create test links
    $tests = [
        ['slug'=>'test-trending-up','url'=>'https://example.com/trending?utm_source=social','desc'=>'Trending Up','p'=>'up'],
        ['slug'=>'test-trending-down','url'=>'https://example.com/down?utm_source=email','desc'=>'Trending Down','p'=>'down'],
        ['slug'=>'test-steady','url'=>'https://example.com/steady?utm_source=search','desc'=>'Steady','p'=>'steady'],
        ['slug'=>'test-spike','url'=>'https://example.com/spike?utm_source=twitter','desc'=>'Spike','p'=>'spike'],
        ['slug'=>'test-no-activity','url'=>'https://example.com/quiet','desc'=>'No Activity','p'=>'none'],
    ];

    foreach ($tests as $t) {
        $wpdb->insert($wpdb->prefix.'ls_links', [
            'slug'=>$t['slug'], 'target_url'=>$t['url'], 'created_at'=>current_time('mysql', true)
        ], ['%s','%s','%s']);
        $ids[$t['slug']] = $wpdb->insert_id;
    }

    $patterns = [
        'up'    => [1,2,3,4,6,8,12,15,18,22,28,35,42,50],
        'down'  => [45,40,35,30,25,20,16,12,9,7,5,3,2,1],
        'steady'=> [15,14,16,15,17,14,16,15,16,14,15,17,14,16],
        'spike' => [5,6,8,12,25,45,80,35,15,8,6,5,4,3],
        'none'  => array_fill(0,14,0),
    ];

    foreach ($tests as $t) {
        $id = $ids[$t['slug']] ?? 0; if (!$id) continue;
        $arr = $patterns[$t['p']]; $total = 0;
        for ($d=13; $d>=0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));
            $n = $arr[13-$d];
            for ($i=0; $i<$n; $i++) {
                $ts = $date . ' ' . sprintf('%02d:%02d:%02d', rand(8,22), rand(0,59), rand(0,59));
                $wpdb->insert($wpdb->prefix.'ls_clicks', [
                    'link_id'=>$id,
                    'link_type'=>'link',
                    'ip_address'=> long2ip(rand(0,4294967295)),
                    'user_agent'=>'Mozilla/5.0 (Test Data) Chrome',
                    'referrer'=>'https://example.com/test/links',
                    'page_title'=>'Test Landing Page',
                    'page_url'=> home_url('/test/landing'),
                    'clicked_at'=>$ts,
                    'click_datetime'=>$ts,
                    'click_date'=>substr($ts,0,10),
                    'click_time'=>substr($ts,11,8),
                    'created_at'=>$ts,
                ], ['%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']);
            }
            $total += $n;
        }
        echo '<p>📊 ' . esc_html($t['desc']) . ': ' . intval($total) . ' clicks</p>';
    }

    echo ls_td_link('Back', []);
    echo '</div>'; exit;
}

if ($action === 'inject_phone') {
    // Ensure some tracked phone numbers exist
    $tracked = get_option('leadstream_phone_numbers', []);
    if (empty($tracked)) {
        $tracked = ['+1 (555) 123-4567', '555.987.6543'];
        update_option('leadstream_phone_numbers', $tracked);
        echo '<p>Added default tracked numbers.</p>';
    }

    // Normalize to digits for link_key
    $digits = array_map(function($n){ return preg_replace('/\D/','',$n); }, $tracked);

    // Patterns per number (14 days)
    $per_number_patterns = [
        0 => [2,3,1,4,3,6,8,5,4,3,4,5,6,7], // first number mild up
        1 => [7,6,5,4,3,2,2,2,1,1,1,1,0,0], // second number decline
    ];

    $total_inserted = 0;
    foreach ($digits as $idx => $link_key) {
        $series = $per_number_patterns[$idx] ?? array_fill(0,14,3);
        for ($d=13; $d>=0; $d--) {
            $date = date('Y-m-d', strtotime("-{$d} days"));
            $n = $series[13-$d];
            for ($i=0; $i<$n; $i++) {
                $ts = $date . ' ' . sprintf('%02d:%02d:%02d', rand(8,21), rand(0,59), rand(0,59));
                $meta = wp_json_encode([
                    'original_phone' => $tracked[$idx],
                    'normalized_phone' => $link_key,
                    'element_type' => 'a',
                    'element_class' => 'btn btn-call',
                    'element_id' => 'call-now',
                    'page_title' => 'Test Contact Page',
                    'tracking_method' => 'leadstream_phone_v2',
                    'click_timestamp' => strtotime($ts),
                ]);

                $wpdb->insert($wpdb->prefix.'ls_clicks', [
                    'link_type' => 'phone',
                    'link_key' => $link_key,
                    'target_url' => 'tel:' . $tracked[$idx],
                    'ip_address' => long2ip(rand(0,4294967295)),
                    'user_agent' => 'Mozilla/5.0 (Test Data) Chrome',
                    'referrer' => 'https://example.com/test/phone',
                    'page_title' => 'Test Contact Page',
                    'page_url' => home_url('/contact'),
                    'clicked_at' => $ts,
                    'click_datetime' => $ts,
                    'click_date' => substr($ts,0,10),
                    'click_time' => substr($ts,11,8),
                    'created_at' => $ts,
                    'meta_data' => $meta,
                ], ['%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']);
                $total_inserted++;
            }
        }
    }

    echo '<p>📞 Inserted ' . intval($total_inserted) . ' phone click rows for ' . count($digits) . ' numbers.</p>';
    echo ls_td_link('Back', []);
    echo '</div>'; exit;
}

echo '</div>';
?>
