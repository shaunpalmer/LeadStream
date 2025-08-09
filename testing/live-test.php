<?php
/**
 * LeadStream Phone Tracking LIVE Test Page
 * 
 * This page loads the actual LeadStream phone tracking and sends real data to WordPress!
 * Access via: http://localhost/wordpress/wp-content/plugins/leadstream-analytics-injector/testing/live-test.php
 */

// Load WordPress
$wp_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (file_exists($wp_path)) {
    require_once($wp_path);
} else {
    die('WordPress not found. Please check the file path.');
}

// Force load our plugin assets
wp_enqueue_script('jquery');

// Get phone tracking settings
$phone_numbers = get_option('leadstream_phone_numbers', array());
$phone_enabled = get_option('leadstream_phone_enabled', 1);
$css_selectors = get_option('leadstream_phone_selectors', '');

// Get current stats from database
global $wpdb;
$total_phone_clicks = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE link_type = %s",
    'phone'
));
$phone_clicks_today = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}ls_clicks WHERE link_type = %s AND DATE(clicked_at) = %s",
    'phone',
    current_time('Y-m-d')
));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeadStream LIVE Phone Tracking Test</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
            color: #1d2327;
        }
        
        .live-test-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .live-header {
            background: linear-gradient(135deg, #00a32a 0%, #008a00 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 163, 42, 0.3);
        }

        .live-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.2em;
            font-weight: 700;
        }

        .current-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 25px 0;
            padding: 25px;
            background: linear-gradient(135deg, #e8f5e8 0%, #d4f1d4 100%);
            border-radius: 12px;
            border: 2px solid #00a32a;
        }

        .stat-card {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #00a32a;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-number {
            font-size: 2em;
            font-weight: 700;
            color: #00a32a;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 12px;
            color: #50575e;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .test-section {
            background: white;
            margin: 25px 0;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            border-left: 5px solid #2271b1;
        }

        .test-section h2 {
            margin-top: 0;
            color: #2271b1;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .phone-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .phone-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 25px;
            background: #2271b1;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 16px;
            min-height: 50px;
        }

        .phone-btn:hover {
            background: #135e96;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(34, 113, 177, 0.3);
            color: white;
            text-decoration: none;
        }

        .phone-btn.custom {
            background: #00a32a;
        }

        .phone-btn.custom:hover {
            background: #008a00;
            box-shadow: 0 6px 20px rgba(0, 163, 42, 0.3);
        }

        .status-info {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .status-info h3 {
            margin-top: 0;
            color: #1565c0;
        }

        .tracking-numbers {
            background: #f1f8e9;
            border: 1px solid #c5e1a5;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            font-family: monospace;
        }

        .admin-link {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }

        .admin-link a {
            display: inline-block;
            padding: 12px 30px;
            background: #00a32a;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 10px;
        }

        .admin-link a:hover {
            background: #008a00;
            color: white;
            text-decoration: none;
        }

        .live-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            background: #00a32a;
            border-radius: 50%;
            animation: pulse 2s infinite;
            margin-right: 8px;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="live-test-container">
        <div class="live-header">
            <h1><span class="live-indicator"></span>LIVE LeadStream Phone Tracking Test</h1>
            <p>This page uses the actual LeadStream plugin - clicks will appear in your WordPress admin!</p>
        </div>

        <!-- Current Database Stats -->
        <div class="current-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_phone_clicks); ?></div>
                <div class="stat-label">Total Phone Clicks in Database</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($phone_clicks_today); ?></div>
                <div class="stat-label">Phone Clicks Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $phone_enabled ? 'ENABLED' : 'DISABLED'; ?></div>
                <div class="stat-label">Tracking Status</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($phone_numbers); ?></div>
                <div class="stat-label">Configured Numbers</div>
            </div>
        </div>

        <!-- Plugin Status -->
        <div class="status-info">
            <h3>📊 LeadStream Plugin Status</h3>
            
            <?php if ($phone_enabled && !empty($phone_numbers)): ?>
                <p><strong>✅ Phone tracking is ACTIVE and ready!</strong></p>
                <div class="tracking-numbers">
                    <strong>Tracked Phone Numbers:</strong><br>
                    <?php foreach ($phone_numbers as $num): ?>
                        • <?php echo esc_html($num); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!$phone_enabled): ?>
                <p><strong>⚠️ Phone tracking is DISABLED</strong> - Enable it in WordPress admin first.</p>
            <?php elseif (empty($phone_numbers)): ?>
                <p><strong>⚠️ No phone numbers configured</strong> - Add numbers in WordPress admin first.</p>
            <?php endif; ?>
            
            <?php if (!empty($css_selectors)): ?>
                <p><strong>Custom Selectors:</strong> <code><?php echo esc_html($css_selectors); ?></code></p>
            <?php endif; ?>
        </div>

        <!-- Live Test Phone Numbers -->
        <div class="test-section">
            <h2>📱 LIVE Phone Number Test</h2>
            <p><strong>Click these numbers to see them appear in your WordPress admin dashboard!</strong></p>
            
            <div class="phone-buttons">
                <?php if (!empty($phone_numbers)): ?>
                    <?php foreach (array_slice($phone_numbers, 0, 4) as $index => $phone): ?>
                        <a href="tel:<?php echo esc_attr($phone); ?>" class="phone-btn">
                            📞 Call <?php echo esc_html($phone); ?>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a href="tel:+15551234567" class="phone-btn">📞 +1 (555) 123-4567</a>
                    <a href="tel:5551234567" class="phone-btn">📞 555-123-4567</a>
                    <a href="tel:+1-555-123-4568" class="phone-btn">📞 +1-555-123-4568</a>
                    <a href="tel:555.123.4569" class="phone-btn">📞 555.123.4569</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Custom Element Test -->
        <div class="test-section">
            <h2>🎯 Custom Element Test</h2>
            <p>Test custom CSS selectors (if configured):</p>
            
            <div class="phone-buttons">
                <button class="phone-btn custom phone-button" onclick="window.location.href='tel:5551234567'">
                    💼 Sales Team (.phone-button)
                </button>
                <button class="phone-btn custom contact-phone" onclick="window.location.href='tel:5551234568'">
                    🛠️ Support (.contact-phone)
                </button>
                <div class="phone-btn custom" data-phone="5551234569" onclick="window.location.href='tel:5551234569'">
                    🚨 Emergency ([data-phone])
                </div>
            </div>
        </div>

        <!-- Admin Dashboard Link -->
        <div class="admin-link">
            <h3>📈 View Your Analytics</h3>
            <p>After clicking phone numbers above, check your WordPress admin to see the data:</p>
            <a href="<?php echo admin_url('admin.php?page=lead-tracking&tab=phone'); ?>">
                📊 Open LeadStream Phone Tracking Dashboard
            </a>
            <a href="<?php echo admin_url('admin.php?page=lead-tracking&tab=links'); ?>">
                🔗 Open Pretty Links Dashboard
            </a>
        </div>
    </div>

    <!-- Load WordPress jQuery and LeadStream assets -->
    <?php wp_head(); ?>
    
    <!-- Load LeadStream Phone Tracking -->
    <script>
    // Ensure LeadStream phone tracking is loaded
    jQuery(document).ready(function($) {
        console.log('LeadStream Live Test Page Ready');
        
        // Check if phone tracking is available
        if (typeof LeadStreamPhone !== 'undefined') {
            console.log('✅ LeadStream Phone Tracking Loaded:', LeadStreamPhone);
        } else {
            console.warn('⚠️ LeadStream Phone Tracking not found - check plugin activation');
        }
        
        // Monitor all phone clicks
        $(document).on('click', 'a[href^="tel:"], .phone-button, .contact-phone, [data-phone]', function() {
            const element = this;
            const phone = element.href ? element.href.replace('tel:', '') : 
                         element.getAttribute('data-phone') || 'unknown';
            
            console.log('📞 Phone click detected:', phone, element);
            
            // Reload stats after click (with delay for processing)
            setTimeout(function() {
                console.log('🔄 Click processed - refresh page to see updated stats');
            }, 1000);
        });
    });
    </script>
    
    <!-- Manual LeadStream Assets (fallback) -->
    <script src="<?php echo plugin_dir_url(dirname(__FILE__)) . '../assets/js/phone-tracking.js'; ?>"></script>
    
    <!-- Localize phone tracking data -->
    <script>
    window.LeadStreamPhone = {
        numbers: <?php echo json_encode($phone_numbers); ?>,
        selectors: <?php echo json_encode($css_selectors); ?>,
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('leadstream_phone_click'); ?>',
        show_feedback: true
    };
    </script>
</body>
</html>
