<p align="center">
  <img
    src="https://raw.githubusercontent.com/shaunpalmer/LeadStream/main/assets/LeadStream-772x250.png"
    alt="LeadStream"
    width="1100"
  />
</p>

<h1 align="center">LeadStream</h1>
<p align="center"><strong>Universal Event & Pixel Injector for WordPress</strong></p>

---

## Description
LeadStream is a Streamlit web application designed for lead management and monitoring. It provides a front-end interface for users to manage, view, and monitor leads, and integrates with various AI and data services.
<p align="center">
  <img
    src="https://raw.githubusercontent.com/shaunpalmer/LeadStream/main/assets/Screenshot 2025-08-10 193233.png"
    alt="LeadStream"
    width="1100"
  />
</p>
## Features
- Lead Management: Easily add, edit, and manage leads.
- Dashboard: View and monitor lead activities and statuses.
- AI Integration: Utilize AI for lead scoring and recommendations.
- Data Integration: Connect to various data sources for comprehensive lead information.
LeadStream is a WordPress plugin that helps you **track real lead actions** on your site—starting with one of the most important ones:

- **Phone number clicks** (e.g. `tel:` links, call buttons, sticky call bars)

It logs those events **inside WordPress**, gives you **clear counts and trends** in the admin UI, and can also fire a matching **GA4 event** (`phone_click`) if you have GA4/gtag running.

This plugin is built to be **practical for site owners** (simple “How many calls? From where?” answers) while still being useful for analytics folks who want clean events.

---

## What it tracks (currently)

### ✅ Phone click tracking
LeadStream tracks clicks on phone numbers across your site:

- Automatically tracks **all** `tel:` links (`a[href^="tel:"]`) when phone tracking is enabled and the number is configured.
- Optionally tracks phone clicks from **custom selectors** (buttons, spans, widgets) and can extract numbers from:
  - `tel:` links
  - `data-phone=""`
  - visible text (fallback)

### ✅ Stores data in your WordPress database
Phone clicks are recorded via a WordPress AJAX endpoint and stored in a normalized click dataset (so reporting stays consistent).

### ✅ Admin reporting (owner-friendly)
In the WordPress admin you can view:
- counts (e.g. total phone clicks, today’s clicks)
- tables / lists of recorded interactions
- export tools (CSV/JSON) where enabled in the admin “Danger Zone” utilities

### ✅ GA4 event (optional)
If `gtag()` is present and a GA4 ID is configured, LeadStream also triggers:
- `phone_click` (event label includes normalized phone number)

> LeadStream does not require GA4 to function. GA4 is optional.

---

## Why it’s reliable (caching/mobile safe)

This is where LeadStream is unusually strong for a free phone tracking tool:

- **Smart delivery on mobile:** the tracker uses `fetch(..., { keepalive: true })` and falls back to `sendBeacon` on page hide/visibility changes—so clicks aren’t lost when the phone dialer steals focus.
- **Server-side anti-duplication:** short window de-dupe prevents double inserts (e.g. rapid taps or fetch+beacon).
- **Robust AJAX URL handling:** supports multiple naming conventions and fallbacks (`ajaxUrl`, `ajax_url`, `window.ajaxurl`, and `/wp-admin/admin-ajax.php`).
- **Admin-only debug/verification:** debug badge and enqueue comments are gated so production sites don’t get visual noise.

If you use caching/minification plugins (e.g. WP Rocket, Hummingbird), you’ll typically just need to **purge cache once** after enabling tracking or changing selectors.

---

## Quick start

1. Install and activate the plugin.
2. In WordPress admin, open **LeadStream → Phone**.
3. Add the phone numbers you want to track (one per line).
4. (Optional) Add custom selectors if your theme uses non-`tel:` click targets.
5. Test:
   - click a phone number on the frontend
   - refresh the LeadStream admin screen and confirm the click appears

---

## What LeadStream does NOT do (yet)

To avoid any mystery marketing, here are features discussed in notes but not shipped in the free plugin by default:

- Call outcome tracking (answered/missed/duration) via Twilio/Telnyx/CallRail webhooks
- “Lead Threads” (stitching clicks + forms + visits into one timeline)
- Automated weekly digests / alerts
- Dynamic Number Insertion (DNI) rule engines

These are on the roadmap and/or exist in more robust “pro” direction, but they are not promised as part of the current free version unless explicitly released.

---

## Roadmap (short + honest)

The direction of LeadStream is **owner-friendly attribution**:

- clearer KPI tiles + deltas (today/week/month)
- “Owner vs Analyst” views
- more automation (weekly digest, anomaly alerts)
- optional call outcomes via provider webhooks (Twilio/Telnyx/etc.)

See: `LeadStream.txt` for rough product thinking / future ideas.

---

## Privacy

LeadStream is designed to be first‑party and WordPress‑native.

It records click events you enable and stores them in your WordPress database.  
(Exact fields stored depend on enabled features and current version.)

If you need GDPR export/delete support for specific fields, that can be added as part of the roadmap.

---

## Contributing

PRs and issues are welcome. If you’re reporting a bug, please include:
- WordPress version
- caching/minify plugins in use (if any)
- steps to reproduce

---

## License

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/shaunpalmer/LeadStream.git
cd LeadStream
```

### 2. Set up the Python environment

Create a virtual environment and install dependencies:

```bash
python -m venv venv
source venv/bin/activate  # On Windows use `venv\Scripts\activate`
pip install -r requirements.txt
```

### 3. Run the application

```bash
streamlit run app.py
```

## Usage

After starting the app, open your browser and navigate to `http://localhost:8501` to access LeadStream.

## Contributing

Contributions are welcome! Please submit pull requests or open issues to help improve the project.

## License

This project is licensed under the MIT License.
