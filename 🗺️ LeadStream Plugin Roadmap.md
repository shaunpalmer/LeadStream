✅ Current Features (v0.9.x)
Pixel Injectors

Google Analytics 4

Meta Pixel (Facebook)

TikTok Pixel

Triple Whale (optional)

Form Tracker Integration

WPForms (with enhanced event labels)

Support for CF7, Gravity, Ninja, Generic HTML forms

Auto-event bindings on submit

Starter Script Loader

JS snippets tailored to selected pixel + form combo

One-click injection to header or footer

Pre-structured gtag events with labels

GTM Integration

Input container ID only—no script tags needed

Header/footer toggle

UI Safety Notices

Admin-only safeguard

GDPR and Code Safety warnings

No tracking by default

🧩 Next Version Goals (v1.0)
🎯 1. Attribution Logic (Core Upgrade)
UTM Tracking + First-Touch Persistence

Capture utm_source, utm_campaign, etc.

Store in localStorage or cookie (e.g. leadstream_first_touch)

Auto-append to form submissions as hidden fields

Pixel Watcher

Detect Meta/TikTok/LinkedIn pixel fires on page load

Log which platform first triggered

Store alongside UTM if no UTM is present

Priority Model

Customizable "First-touch wins" vs "Last-click override"

Admin toggle for priority logic

🧪 2. Debug Dashboard (Developer Tab)
Attribution Console:

Shows last 10 attribution events

UTM + pixel fire + page visited

Visual status: “source locked,” “session reset,” etc.

Cookie + LocalStorage Inspector

What’s currently saved per user

🛠️ 3. UTM Builder Tool (New Tab)
Input fields: base URL + UTM fields

“Generate URL” button

“Copy to Clipboard”

Optional presets (save/load common campaigns)

📦 4. Webhook Integration (Form Hook Pass-Through)
Allow passing enriched attribution data to:

Zapier / Make

CRMs via hidden fields or REST API

Optional: native Webhook URL field in settings

📈 5. Analytics Summary (Future)
Basic chart: Top 5 lead sources (first-touch only)

Date filtering (last 7 / 30 / 90 days)

Form submit count by source

🧪 Experimental (v1.1+ Ideas)
Heatmap or click zone tracking toggle

Tag injection conditions (e.g., “only fire Meta on mobile”)

Offline conversion tracking (passback to Meta API)

Pageview session maps with origin trail

💡 Release Positioning
“LeadStream is your lightweight, no-bloat analytics injector for WordPress—custom-tailored for forms, events, and UTM tracking. Privacy-safe, performance-focused, and plugin-conflict-free.”