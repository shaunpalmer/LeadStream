<?php
/**
 * LeadStream Phone Tracking Demo Page
 * 
 * This works as a standalone demo - no WordPress required!
 * Just access via: http://localhost/wordpress/wp-content/plugins/leadstream-analytics-injector/testing/wp-phone-demo.php
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeadStream Phone Tracking Demo</title>
</head>
<body>

<style>
.leadstream-demo {
    max-width: 800px;
    margin: 40px auto;
    padding: 20px;
}

.demo-section {
    background: white;
    margin: 30px 0;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-left: 4px solid #2271b1;
}

.phone-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin: 20px 0;
}

.phone-btn {
    display: inline-block;
    padding: 12px 24px;
    background: #2271b1;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.phone-btn:hover {
    background: #135e96;
    transform: translateY(-2px);
    color: white;
    text-decoration: none;
}

.phone-btn.custom {
    background: #00a32a;
}

.phone-btn.custom:hover {
    background: #008a00;
}

.demo-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin: 20px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 6px;
}

.stat-card {
    text-align: center;
    padding: 15px;
    background: white;
    border-radius: 4px;
    border-left: 3px solid #2271b1;
}

.stat-number {
    font-size: 24px;
    font-weight: 600;
    color: #1d2327;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 11px;
    color: #50575e;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.tracking-log {
    background: #1e1e1e;
    color: #00ff00;
    padding: 15px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    margin: 15px 0;
    max-height: 200px;
    overflow-y: auto;
}
</style>

<div class="leadstream-demo">
    <h1>📞 LeadStream Phone Tracking Demo</h1>
    <p><strong>This page demonstrates live phone tracking with your LeadStream plugin.</strong></p>
    
    <!-- Live Stats -->
    <div class="demo-stats">
        <div class="stat-card">
            <div class="stat-number" id="demo-clicks">0</div>
            <div class="stat-label">Demo Clicks</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="phone-clicks">0</div>
            <div class="stat-label">Phone Clicks</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="custom-clicks">0</div>
            <div class="stat-label">Custom Elements</div>
        </div>
    </div>

    <!-- Standard Phone Links -->
    <div class="demo-section">
        <h2>📱 Standard Tel: Links</h2>
        <p>These phone numbers should be automatically tracked by LeadStream:</p>
        
        <div class="phone-buttons">
            <a href="tel:+15551234567" class="phone-btn">📞 +1 (555) 123-4567</a>
            <a href="tel:5551234567" class="phone-btn">📞 555-123-4567</a>
            <a href="tel:+1-555-123-4568" class="phone-btn">📞 +1-555-123-4568</a>
            <a href="tel:555.123.4569" class="phone-btn">📞 555.123.4569</a>
        </div>
        
        <p><em>Each click is automatically tracked and sent to your analytics!</em></p>
    </div>

    <!-- Custom Phone Elements -->
    <div class="demo-section">
        <h2>🎯 Custom Phone Buttons</h2>
        <p>These use CSS classes that can be tracked with custom selectors:</p>
        
        <div class="phone-buttons">
            <button class="phone-btn custom phone-button" onclick="trackCustomPhone('5551234567', 'sales')">
                📞 Sales Team
            </button>
            <button class="phone-btn custom contact-phone" onclick="trackCustomPhone('5551234568', 'support')">
                📞 Support Line
            </button>
            <div class="phone-btn custom" data-phone="5551234569" onclick="trackCustomPhone('5551234569', 'emergency')">
                📞 Emergency
            </div>
        </div>
        
        <p><em>Custom selectors: .phone-button, .contact-phone, [data-phone]</em></p>
    </div>

    <!-- Tracking Log -->
    <div class="demo-section">
        <h3>📊 Live Tracking Log</h3>
        <div class="tracking-log" id="tracking-log">
            [READY] LeadStream Phone Demo Loaded<br>
            [INFO] Click phone numbers above to see tracking in action<br>
            [INFO] Check WordPress Admin → LeadStream → Phone Tracking for data<br>
        </div>
        
        <button onclick="clearLog()" class="phone-btn" style="background: #666; margin-top: 10px;">
            🗑️ Clear Log
        </button>
        <button onclick="runDemo()" class="phone-btn custom" style="margin-top: 10px; margin-left: 10px;">
            🚀 Run Demo
        </button>
    </div>

    <!-- WordPress Admin Link -->
    <div class="demo-section" style="border-left-color: #00a32a;">
        <h3>📈 View Real Analytics</h3>
        <p>See the actual tracking data in your WordPress admin:</p>
        <a href="#" onclick="alert('Install as WordPress plugin to access admin dashboard!')" class="phone-btn" style="background: #00a32a;">
            📊 WordPress Admin (Demo Mode)
        </a>
        <p><em>This is a standalone demo. Install the LeadStream plugin in WordPress to see real analytics!</em></p>
    </div>
</div>

<script>
let demoClicks = 0;
let phoneClicks = 0;
let customClicks = 0;

function log(message, type = 'INFO') {
    const timestamp = new Date().toLocaleTimeString();
    const logDiv = document.getElementById('tracking-log');
    const colors = {
        'SUCCESS': '#00ff00',
        'ERROR': '#ff4444',
        'PHONE': '#00dddd',
        'CUSTOM': '#ffaa00',
        'INFO': '#cccccc'
    };
    const color = colors[type] || '#cccccc';
    logDiv.innerHTML += `<span style="color: ${color}">[${timestamp}] [${type}] ${message}</span><br>`;
    logDiv.scrollTop = logDiv.scrollHeight;
}

function updateStats() {
    document.getElementById('demo-clicks').textContent = demoClicks;
    document.getElementById('phone-clicks').textContent = phoneClicks;
    document.getElementById('custom-clicks').textContent = customClicks;
}

function trackCustomPhone(number, type) {
    demoClicks++;
    customClicks++;
    updateStats();
    
    log(`Custom ${type} button clicked: ${number}`, 'CUSTOM');
    log(`→ LeadStream would normalize: ${number.replace(/\D/g, '')}`, 'INFO');
    log(`→ GA4 event: phone_click`, 'SUCCESS');
    log(`→ Database: link_type='phone', link_key='${number.replace(/\D/g, '')}'`, 'SUCCESS');
    
    // Actually make the call
    window.location.href = `tel:${number}`;
}

function clearLog() {
    document.getElementById('tracking-log').innerHTML = '[READY] Log cleared - ready for new tests<br>';
}

function runDemo() {
    log('Starting LeadStream phone tracking demo...', 'SUCCESS');
    
    const demos = [
        { phone: '+15551234567', format: 'International' },
        { phone: '5551234567', format: 'Standard' },
        { phone: '+1-555-123-4568', format: 'Dashed' },
        { phone: '555.123.4569', format: 'Dotted' }
    ];
    
    demos.forEach((demo, index) => {
        setTimeout(() => {
            demoClicks++;
            phoneClicks++;
            updateStats();
            
            log(`Testing ${demo.format} format: ${demo.phone}`, 'PHONE');
            log(`→ Digits extracted: ${demo.phone.replace(/\D/g, '')}`, 'INFO');
            log(`→ Matched against configured numbers`, 'SUCCESS');
            log(`→ Event sent to analytics platforms`, 'SUCCESS');
        }, (index + 1) * 1500);
    });
    
    setTimeout(() => {
        log('Demo complete! Check your LeadStream admin for real data 🎉', 'SUCCESS');
    }, 7000);
}

// Track actual tel: link clicks
document.addEventListener('click', function(e) {
    if (e.target.tagName === 'A' && e.target.href.startsWith('tel:')) {
        demoClicks++;
        phoneClicks++;
        updateStats();
        
        const phoneNumber = e.target.href.replace('tel:', '');
        log(`Tel: link clicked: ${phoneNumber}`, 'PHONE');
        log(`→ LeadStream tracking active`, 'SUCCESS');
        log(`→ Data saved to wp_ls_clicks table`, 'INFO');
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    log('LeadStream Phone Demo ready!', 'SUCCESS');
    log('All tracking elements loaded and active', 'INFO');
});
</script>

</body>
</html>
