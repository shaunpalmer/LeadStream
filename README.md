# LeadStream: Full Power Analytics Tracking Done Right.
Easy-to-use event tracking for GA4, WPForms, and more—no setup headaches.

A professional JavaScript injection plugin for WordPress designed for agencies and marketers who need precise conversion tracking. Custom event handling for Meta Pixel, Google Analytics (GA4), TikTok Pixel, Triple Whale, and any analytics platform.

## 🎯 Features

### **Universal JavaScript Injection**
- **Two injection points**: Header (`<head>`) and Footer (before `</body>`)
- **Any tracking platform**: Google Analytics, Facebook Pixel, TikTok Pixel, Triple Whale, Pinterest, LinkedIn, etc.
- **Clean output**: Minimal HTML comments, no clutter
- **SEO optimized**: Footer injection loads after page content

### **Developer-Friendly Interface**
- **Monospace font textareas** with syntax highlighting-friendly styling
- **Large text areas** (15 rows) for complex tracking code
- **Placeholder examples** to guide usage
- **No `<script>` tags needed** - just paste your JavaScript

### **Smart Implementation**
- **Priority 999**: Loads after all other plugins and themes
- **Custom sanitization**: Preserves JavaScript syntax without breaking code
- **WordPress Settings API**: Secure, standard WordPress implementation
- **No conflicts**: Works alongside Google Site Kit and other tracking plugins

## 🚀 Installation

1. Upload the `leadstream-analytics-injector.php` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **"LeadStream"** in your WordPress admin menu
4. Add your JavaScript code and save

## 💡 Use Cases

### **Google Analytics Event Tracking**
Perfect for custom event tracking that works alongside Google Site Kit:
```javascript
// Form submission tracking
document.addEventListener('wpformsSubmit', function(event) {
    gtag('event', 'form_submit', {
        'event_category': 'Lead',
        'event_label': 'Contact Form'
    });
});

// Button click tracking
document.getElementById('cta-button').addEventListener('click', function() {
    gtag('event', 'cta_click', {
        'event_category': 'CTA',
        'event_label': 'Get Quote Button'
    });
});
```

### **Facebook/Meta Pixel**
```javascript
// Lead tracking
fbq('track', 'Lead');
fbq('track', 'CompleteRegistration');

// Custom events
fbq('trackCustom', 'ContactFormSubmit');
```

### **TikTok Pixel**
```javascript
// Standard events
ttq.track('Contact');
ttq.track('SubmitForm');

// Custom events with parameters
ttq.track('CustomEvent', {
    'value': 25.00,
    'currency': 'USD'
});
```

### **Triple Whale & Other Platforms**
```javascript
// Triple Whale
triplewhale.track('purchase', {
    'value': 99.99,
    'currency': 'USD'
});

// Pinterest
pintrk('track', 'lead');

// LinkedIn
_linkedin_partner_id = "12345";
```

## 🎛️ Admin Interface

### **Location**
- WordPress Admin → **"LeadStream"**
- Top-level menu item (not under Settings)

### **Two Text Areas**
1. **Header JavaScript**: Code injected in `<head>` section
2. **Footer JavaScript**: Code injected before `</body>` tag

### **Styling Features**
- Monospace font (Consolas/Monaco) for code readability
- Light gray background with padding
- Wide text areas (800px max-width)
- Helpful placeholder examples

## ⚡ Performance & SEO Benefits

### **Optimal Loading Strategy**
- **Footer injection**: JavaScript loads after page content
- **Priority 999**: Ensures tracking code loads last
- **No render blocking**: Page content displays immediately
- **SEO friendly**: Search engines can crawl content without delay

### **Clean Output**
```html
<!-- GA tracking here -->
<script type="text/javascript">
// Your custom JavaScript here
</script>
```

## 🔧 Technical Details

### **WordPress Hooks Used**
- `wp_head` (priority 999) - Header injection
- `wp_footer` (priority 999) - Footer injection
- `admin_menu` - Settings page creation
- `admin_init` - Settings registration

### **Security Features**
- WordPress Settings API implementation
- Custom sanitization function preserves JavaScript syntax
- `ABSPATH` security check
- Proper capability checking (`manage_options`)

### **Compatibility**
- **WordPress**: 5.0+ (uses standard WordPress functions)
- **PHP**: 7.4+ recommended
- **Themes**: Universal - works with any theme
- **Plugins**: No conflicts - designed to work alongside other tracking plugins

## 🎯 Why Use This Plugin?

### **Universal Application**
- **Agency-friendly**: Drop into any WordPress project
- **Platform-agnostic**: Works with any JavaScript tracking code
- **Future-proof**: Add new tracking platforms as they emerge

### **No Conflicts**
- **Google Site Kit**: Works perfectly alongside existing GA tracking
- **Theme changes**: Survives theme updates and changes
- **Plugin updates**: Settings persist through WordPress updates

### **Developer Benefits**
- **Clean code**: Professional, maintainable implementation
- **Standard practices**: Uses WordPress coding standards
- **Easy deployment**: Single plugin folder, works everywhere

## 📝 Example Tracking Scenarios

### **E-commerce Lead Tracking**
```javascript
// WooCommerce add to cart
document.addEventListener('added_to_cart', function() {
    gtag('event', 'add_to_cart');
    fbq('track', 'AddToCart');
    ttq.track('AddToCart');
});
```

### **Form Submission Tracking**
```javascript
// Contact forms
document.querySelectorAll('form').forEach(function(form) {
    form.addEventListener('submit', function() {
        gtag('event', 'form_submit');
        fbq('track', 'Lead');
    });
});
```

### **Scroll Depth Tracking**
```javascript
// Engagement tracking
let scrollTracked = false;
window.addEventListener('scroll', function() {
    if (!scrollTracked && window.scrollY > document.body.scrollHeight * 0.75) {
        gtag('event', 'scroll_depth', {'event_label': '75%'});
        scrollTracked = true;
    }
});
```

## 🤝 Contributing

This plugin is designed to be simple and focused. If you have suggestions for improvements while maintaining its lightweight nature, please feel free to contribute.

## 📄 License

GPL v2 or later

## 🔗 Support

For support and questions, please use the WordPress plugin support forum or create an issue on GitHub.

---

**Perfect for agencies, developers, and anyone who needs reliable, universal JavaScript tracking across multiple WordPress projects!** 🚀
