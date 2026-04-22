<?php
/**
 * WordPress Test Page for LeadStream Auto-Injection
 * 
 * This simulates a real WordPress page with phone numbers.
 * Access via: http://localhost/wordpress/wp-content/plugins/leadstream-analytics-injector/testing/wordpress-page-test.php
 * 
 * The LeadStream plugin should automatically inject phone tracking JavaScript.
 */

// Load WordPress
$wp_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (file_exists($wp_path)) {
    require_once($wp_path);
} else {
    die('<h1>WordPress Not Found</h1><p>Cannot load WordPress. This test requires WordPress to demonstrate auto-injection.</p>');
}

// Start WordPress head
get_header();
?>

<style>
    .phone-test-content {
        max-width: 800px;
        margin: 50px auto;
        padding: 30px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .phone-test-content h1 {
        color: #2271b1;
        text-align: center;
        margin-bottom: 30px;
    }
    
    .phone-section {
        margin: 30px 0;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 6px;
        border-left: 4px solid #2271b1;
    }
    
    .phone-link {
        display: inline-block;
        padding: 12px 20px;
        margin: 8px;
        background: #2271b1;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .phone-link:hover {
        background: #135e96;
        transform: translateY(-2px);
        color: white;
        text-decoration: none;
    }
    
    .custom-phone {
        display: inline-block;
        padding: 12px 20px;
        margin: 8px;
        background: #00a32a;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .custom-phone:hover {
        background: #008a00;
        transform: translateY(-2px);
    }
    
    .status-box {
        background: #e7f3ff;
        border: 1px solid #b3d9ff;
        padding: 20px;
        border-radius: 6px;
        margin: 20px 0;
    }
    
    .debug-info {
        background: #1e1e1e;
        color: #00ff00;
        padding: 15px;
        border-radius: 6px;
        font-family: monospace;
        font-size: 13px;
        margin: 20px 0;
        min-height: 100px;
        overflow-y: auto;
    }
</style>

<div class="phone-test-content">
    <h1>📞 WordPress Page with Auto-Injection</h1>
    
    <div class="status-box">
        <h3>🎯 What This Tests</h3>
        <p>This is a <strong>real WordPress page</strong> that should automatically have LeadStream phone tracking injected.</p>
        <p>Unlike static HTML files, this page loads through WordPress and gets the LeadStream JavaScript automatically.</p>
    </div>

    <div class="phone-section">
        <h3>📱 Standard Phone Links</h3>
        <p>These should be automatically tracked by LeadStream:</p>
        
        <a href="tel:+15551234567" class="phone-link">📞 +1 (555) 123-4567</a>
        <a href="tel:5551234567" class="phone-link">📞 555-123-4567</a>
        <a href="tel:+1-555-123-4568" class="phone-link">📞 +1-555-123-4568</a>
        <a href="tel:555.123.4569" class="phone-link">📞 555.123.4569</a>
    </div>

    <div class="phone-section">
        <h3>🎯 Custom Phone Elements</h3>
        <p>These use custom CSS classes for selector-based tracking:</p>
        
        <button class="custom-phone phone-button" onclick="window.location.href='tel:5551234567'">
            💼 Sales (.phone-button)
        </button>
        <button class="custom-phone contact-phone" onclick="window.location.href='tel:5551234568'">
            🛠️ Support (.contact-phone)
        </button>
        <div class="custom-phone" data-phone="5551234569" onclick="window.location.href='tel:5551234569'">
            🚨 Emergency ([data-phone])
        </div>
    </div>

    <div class="status-box" style="background: #f0f8f0; border-color: #c3e6c3;">
        <h3>✅ Auto-Injection Status</h3>
        <p id="injection-status">Checking LeadStream auto-injection...</p>
        <div class="debug-info" id="debug-log">
            [LOADING] Checking for LeadStream phone tracking...<br>
        </div>
    </div>

    <div class="status-box" style="background: #fff3cd; border-color: #ffeaa7;">
        <h3>📊 After Testing</h3>
        <p>After clicking phone numbers above, check your WordPress admin:</p>
        <a href="<?php echo admin_url('admin.php?page=lead-tracking&tab=phone'); ?>" 
           style="display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px;">
            📊 Phone Tracking Dashboard
        </a>
        <a href="<?php echo admin_url('admin.php?page=lead-tracking'); ?>" 
           style="display: inline-block; padding: 10px 20px; background: #00a32a; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px;">
            🎯 Main Dashboard
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusEl = document.getElementById('injection-status');
    const debugLog = document.getElementById('debug-log');
    
    function log(message, type = 'INFO') {
        const timestamp = new Date().toLocaleTimeString();
        const colors = {
            'SUCCESS': '#00ff00',
            'ERROR': '#ff4444', 
            'WARNING': '#ffaa00',
            'INFO': '#00dddd'
        };
        const color = colors[type] || '#cccccc';
        debugLog.innerHTML += `<span style="color: ${color}">[${timestamp}] ${message}</span><br>`;
        debugLog.scrollTop = debugLog.scrollHeight;
    }
    
    log('WordPress page loaded', 'INFO');
    log('Checking for LeadStream auto-injection...', 'INFO');
    
    // Check after WordPress scripts have loaded
    setTimeout(function() {
        if (window.LeadStreamPhone) {
            statusEl.innerHTML = '✅ <strong>SUCCESS!</strong> LeadStream phone tracking is auto-injected and active.';
            statusEl.style.color = '#155724';
            log('LeadStream phone tracking detected!', 'SUCCESS');
            log('Configured numbers: ' + JSON.stringify(window.LeadStreamPhone.numbers), 'INFO');
            log('Custom selectors: ' + window.LeadStreamPhone.selectors, 'INFO');
            log('AJAX URL: ' + window.LeadStreamPhone.ajax_url, 'INFO');
        } else {
            statusEl.innerHTML = '⚠️ <strong>NOT DETECTED:</strong> LeadStream phone tracking not found. Check plugin activation and configuration.';
            statusEl.style.color = '#721c24';
            log('LeadStream phone tracking NOT detected', 'ERROR');
            log('Possible issues: Plugin not activated, phone tracking disabled, or no numbers configured', 'WARNING');
        }
    }, 2000);
    
    // Monitor phone clicks
    document.addEventListener('click', function(e) {
        const target = e.target;
        if (target.tagName === 'A' && target.href && target.href.startsWith('tel:')) {
            const phone = target.href.replace('tel:', '');
            log('Tel: link clicked: ' + phone, 'SUCCESS');
            log('LeadStream should automatically track this click', 'INFO');
        } else if (target.classList.contains('phone-button') || target.classList.contains('contact-phone') || target.hasAttribute('data-phone')) {
            log('Custom phone element clicked', 'SUCCESS');
            log('Element classes: ' + target.className, 'INFO');
        }
    });
    
    log('Phone click monitoring active', 'SUCCESS');
});
</script>

<?php get_footer(); ?>
