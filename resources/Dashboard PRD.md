We’re in a good place to draft a PRD for Copilot — we’ve got your notes, the phase plan, and now the chart direction nailed. Here’s how I’d frame it cleanly:

📋 LeadStream Dashboard PRD (Phase 1)

Purpose
Provide an admin-only dashboard as the first tab in LeadStream. This is the landing view. It should give owners instant visual cues about tracking health and show key performance metrics at a glance, with trends over time.

🖼️ Layout

Header: “LeadStream Dashboard”

KPI Tiles (row of cards, color-coded):

Phone Calls (green/orange/red)

Form Submits (green/orange/red)

CTR %

Top Sources (list top 3 by volume)

Trend Chart (below tiles):

Line chart (like GA trendline, not sparkline)

X-axis: time (controlled by date pickers)

Y-axis: metric (calls, forms, leads — selectable via dropdown)

Status Badges (under chart):

Phone Tracking status

JavaScript Injection status

Pretty Links status

QR status
Each shows Green = OK, Orange = configured but partial, Red = off/missing.

🎨 Color Scheme (badge/tile states)

Green → Configured + data flowing.

Orange → Config enabled but missing requirements (e.g., phone tracking on but no numbers).

Red → Disabled or broken.

🛠️ Functional Requirements

Date Range Pickers

Start + End date (default: last 7 days).

Controls data scope for trend chart + tiles.

Metric Selector

Dropdown: “Calls”, “Forms”, “Total Leads”.

Updates Y-axis and chart line.

Delta Calculation

KPI tiles show ▲ / ▼ vs prior period (% and absolute).

Data Sources

Pull from existing tables: calls, forms, links, QR events.

Localization

All dashboard data exposed via PHP → wp_localize_script → JS chart.

Responsive

Works on desktop and mobile (tiles stack vertically).

🚦 Phase 1 Deliverables

Dashboard tab (first in nav).

KPI tiles (with delta calc + color states).

Trend line chart with date pickers + metric selector.

Status badges (phone, JS, links, QR).

PHP functions to aggregate counts by day for the chart.

🚧 Future Phases

Phase 2: Lead Threads timeline view.

Phase 3: Alerts + weekly digest.

✅ With this PRD, Copilot can implement a first cut of the dashboard without overthinking. We keep the scope tight: tiles + chart + statuses. That gets you the “sunshine” effect you’re aiming for.

1. 📚 Libraries for PHP

For the backend (data aggregation, exposure to JS):

Native WordPress APIs

$wpdb for pulling event data (already your style).

wp_localize_script or REST routes to pass JSON to JS.

Optional add-ons (if you want polish down the road):

WP_List_Table for tabular data in admin (already built into WP core).

DateTime + DateInterval (native PHP) for delta calculations.

No need for heavy external libs — keep it lean so no Composer bloat (matches your preference).

2. 🧩 OOP Patterns

We can make this dashboard modular and SOLID-friendly:

Single Responsibility (SRP):

Dashboard_Data → handles queries & aggregation (counts, deltas).

Dashboard_Render → enqueues scripts + outputs placeholders.

Dashboard_Status → runs health checks (phone tracking, links, etc).

Open/Closed (OCP):

Add new KPI sources (Forms, QR, Pretty Links) by extending an abstract KPIProvider.

No need to rewrite dashboard core.

Strategy Pattern:

Each KPI tile can use a Strategy to calculate values (e.g., PhoneCallsStrategy, FormsStrategy).

Swap strategies easily without touching main loop.

Factory Pattern:

KPIProviderFactory builds KPI objects depending on what’s enabled.

Keeps creation clean.

Dependency Inversion:

Dashboard shouldn’t depend on $wpdb directly. Inject a DataRepositoryInterface so you can swap raw SQL for cached JSON or REST later.

3. 🎨 Making It Look Professional

Think premium SaaS polish:

Use a charting library like Chart.js (lightweight, responsive, works perfectly in WP admin).

KPI Tiles styled with WordPress admin CSS + a tiny custom CSS layer (rounded corners, drop shadows, consistent spacing).

Color-coded badges (green/orange/red).

Smooth hover tooltips for deltas.

Icons (FontAwesome or Dashicons) for visual cues — e.g., 📞 for calls, ✉️ for forms.

4. 📈 Useful Information (not clutter)

Top 3 Sources (what channel is working right now).

Call-to-booking ratio (conversion, not just clicks).

Delta vs previous period (shows momentum).

Health states (phone tracking active, selectors configured, GA ID present).

5. 🧲 How to Make It Sticky

Sticky dashboards make people want to check them daily:

Default to last 7 days (fast trend recognition).

Highlight anomalies (e.g., “calls dropped 35% vs last week”).

Make it glanceable — no heavy tables upfront, just KPIs + one good chart.

Provide context (▲ / ▼ with period comparison).

Drill-down ready — click a tile → jumps you into the relevant tab (Phone, Forms, etc).

6. 🌞 My Contribution (where I can push it further)

Draft the class structure with SOLID baked in (Data / Render / Status separation).

Write a Copilot brief with ready-to-use Chart.js integration (PHP localize → JS chart).

Add polish ideas like:

Tooltip explanations on tiles.

Small “last updated X mins ago” note.

Smooth CSS transitions on badges (red → orange → green).

Plan the data repository contract so you can easily add caching or external APIs later without rewriting.

⚡ In short:

PHP → keep it native (wpdb + DateTime).

Patterns → SRP + Factory + Strategy, all SOLID.

Frontend → Chart.js for trendlines, admin CSS + small polish layer.

Stickiness → actionable metrics, delta comparisons, status colors, drill-down links. 

I’ve got enough context from your notes, the existing plugin structure, and what we’ve been shaping up with the badge + tabs to sketch this out cleanly in class form. Procedural would get messy fast here — classes will keep it SOLID and extensible as you grow the dashboard.

Here’s a class map proposal for Phase 1 of the Dashboard:

🗂️ Class Map
1. AYS_Dashboard

🔑 Coordinator / Entry point

Registers the “Dashboard” tab in your plugin’s admin.

Calls into other dashboard classes to assemble data + render view.

Enqueues Chart.js + dashboard.css.

2. AYS_Dashboard_Render

🔑 Presentation layer

Outputs the markup for KPI tiles, trend chart container, status badges.

Reads from AYS_Dashboard_Data (aggregated stats).

Keeps zero logic — only formatting/echoing.

3. AYS_Dashboard_Data

🔑 Aggregation layer

Queries $wpdb for calls, forms, links, QR events.

Groups by day (for trendlines).

Exposes totals + deltas (e.g., calls this week vs last).

Implements DataRepositoryInterface (so later you can swap raw SQL with cached JSON).

4. AYS_Dashboard_Status

🔑 Health checker

Runs feature checks:

Phone tracking enabled + numbers exist?

Pretty Links active?

JS injection working?

QR module available?

Returns state objects with status (green/orange/red) + message.

Used by renderer for status badges.

5. KPIProvider (Abstract)

🔑 Strategy base class for KPI tiles

Methods:

get_label()

get_value(DateRange $range)

get_delta(DateRange $current, DateRange $previous)

Concrete implementations:

KPI_PhoneCalls

KPI_FormSubmits

KPI_CTR

Each provider encapsulates its logic (SRP).

6. AYS_Dashboard_Factory

🔑 Factory to build KPI providers

Reads enabled features → instantiates the right KPIProvider classes.

Example: if Forms disabled, don’t include FormSubmits tile.

7. AYS_Dashboard_Controller (Optional but nice)

🔑 Future REST/AJAX bridge

Provides JSON for chart data (calls/forms grouped by day).

Decouples PHP render from JS → easier for future React/SPA extension.

⚙️ Patterns in Play

SRP → Each class has a single job.

Factory → Instantiates KPI providers.

Strategy → Different KPIs implement their own calculation logic.

Dependency Inversion → lS_Dashboard_Data behind an interface, so it can swap data sources.

🌈 Professional Polish

Chart.js for trend lines (time on X-axis, counts on Y).

Tiles with ▲/▼ deltas, color-coded (green/orange/red).

Badges for feature states, aligned under chart.

CSS polish → shadowed cards, consistent spacing, responsive grid.

⚡ With this structure, Phase 1 (tiles + chart + status) slots in neatly, and you can keep expanding (Lead Threads, Alerts, Digests) without touching the foundation.