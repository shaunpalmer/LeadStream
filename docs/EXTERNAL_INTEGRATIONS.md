# LeadStream Pro — External Integrations Guide

This document is for developers building external plugins, providers, or “bridge” plugins that integrate with LeadStream Pro.

Scope:
- Public endpoints (REST + AJAX) that can be called externally
- The conversion pipeline hook you can subscribe to
- How to register additional tracking “strategies” (Meta/TikTok/etc.) without changing core
- The data tables that store LeadStream’s source-of-truth

Non-goals:
- Admin UI documentation
- UI automation / GTM container configuration

---

## 1) Mental Model

LeadStream records user actions (phone clicks, form submits, call outcomes) and turns them into **conversion envelopes** that run through a centralized pipeline:

1. Event source (AJAX, REST webhook, or server hook)
2. Build `ClickContext` (sanitized context) + `TrackingEnvelope` (event wrapper)
3. `TrackingPipelineRunner`:
   - ensures a stable `event_id`
   - runs anti-double-send guard (transient-based)
   - dispatches to registered strategies
   - emits a WordPress action for extensions

This design allows:
- “broadcast first / ship second” behavior (GTM-first + server-side fallback)
- consistent dedupe across channels using `event_id`
- extension strategies registered by filter

---

## 2) Primary Extension Hook (Subscribe to Conversions)

### Action: `leadstream_conversion_recorded`

Fired by the pipeline for every envelope.

Signature:
```php
/**
 * @param \LeadStream\DTO\TrackingEnvelope $envelope
 * @param int $click_id
 * @param string $event_id
 * @param bool $allow_send True when strategies were allowed to dispatch.
 */
do_action('leadstream_conversion_recorded', $envelope, $click_id, $event_id, $allow_send);
```

Recommended behavior for extension plugins:
- If you will dispatch to external systems (CRM, offline conversions, webhooks), gate on `$allow_send === true`.
- If you only want to mirror LeadStream’s DB truth (logging/analytics), you may accept `$allow_send === false` too.

Envelope contents:
- `$envelope->event_name()` (e.g. `phone_click`, `form_submit`, `call_answered`, `call_missed`)
- `$envelope->event_id()` stable dedupe id
- `$envelope->click_id()` numeric click correlation when available
- `$envelope->context()` sanitized `ClickContext`
- `$envelope->meta()` event-specific metadata (e.g. `provider_call_id`, `duration`)

---

## 3) Registering Custom Tracking Strategies

### Filter: `leadstream_tracking_strategies`

LeadStream dispatches conversions through a `TrackingManager` which iterates strategies.

Filter contract:
- receives `array<int, TrackingStrategyInterface>`
- must return `array<int, TrackingStrategyInterface>`

Example (in your plugin):
```php
add_filter('leadstream_tracking_strategies', function(array $strategies) {
    $strategies[] = new MyVendor\\MyStrategy();
    return $strategies;
});
```

Strategy interface uses the envelope (event-aware dispatch):
```php
interface TrackingStrategyInterface {
    public function should_skip(\LeadStream\DTO\TrackingEnvelope $envelope): bool;
    public function dispatch(\LeadStream\DTO\TrackingEnvelope $envelope): void;
}
```

Guidelines:
- Use `$envelope->event_name()` to route only the events you support.
- Use `$envelope->event_id()` as your dedupe key downstream.
- Treat the database as the truth; strategies are “shipping adapters.”

---

## 4) Public REST Endpoints

All REST endpoints live under the `leadstream/v1` namespace.

### 4.1) POST `/wp-json/leadstream/v1/calls`

Purpose:
- Provider webhook endpoint for call outcomes (Twilio/Telnyx/etc.)
- Writes/updates a row in `wp_ls_calls`
- Runs the conversion pipeline (dedupe + strategies + hook)

Auth:
- `permission_callback` is `__return_true` (intended for provider posts).
- You should authenticate at the edge (IP allowlist, secret header, reverse proxy rule), or wrap via your own bridge plugin.

Required fields:
- `provider` (string)
- `provider_call_id` (string)
- `to` (string)

Optional fields:
- `from` (string)
- `status` (string; examples: `answered`, `completed`, `missed`, `no-answer`, `failed`, `busy`)
- `start_time` / `end_time` (ISO8601 recommended)
- `duration` (int seconds)
- `recording_url` (string)
- `click_id` (int) correlation to `wp_ls_clicks.id` when known
- `event_id` (string, max 128) for cross-channel dedupe (recommended if you already have one)
- `ga_client_id` (string) optional GA4 client id
- `ga_session_id` (int) optional GA4 session id
- `ga_session_number` (int) optional GA4 session number
- `meta` (object/array) any extra provider payload

Response (200):
- `success` boolean
- `call_id` numeric
- `provider_call_id` echoed

Notes:
- LeadStream computes a stable `event_id` if not provided.
- Call outcomes map to envelope event names:
  - `completed` -> `call_answered`
  - `missed` -> `call_missed`
  - `failed` -> `call_failed` (pipeline runs; core GA4 strategy currently ignores it)

### 4.2) GET `/wp-json/leadstream/v1/metrics` (admin)

Purpose:
- Admin dashboard KPI + trend data

Auth:
- requires `manage_options`

---

## 5) Public AJAX Endpoints

AJAX endpoints are WordPress `admin-ajax.php` actions. Some are public (`nopriv`) and some are admin-only.

### 5.1) Phone click capture (public)

Action:
- `leadstream_record_phone_click` (primary)
- `leadstream_phone_click` (back-compat)

Method:
- POST

Behavior:
- Builds `ClickContext` from request
- Pre-DB debounce for 2 seconds
- Inserts into `wp_ls_clicks`
- Runs pipeline for `phone_click`

Important fields (subset; LeadStream JS sends more):
- `phone` (required)
- `page_url`
- `page_title`
- `origin`, `source`
- `event_id` (recommended)
- attribution keys: `gclid`, `fbclid`, `msclkid`, `ttclid`, `utm_*`
- GA “golden keys”: `ga_client_id`, `ga_session_id`, `ga_session_number`

Response:
- `received` boolean
- `click_id` numeric
- `event_id` string
- `sent` boolean (pipeline allow_send)

### 5.2) Generic event logger (public)

Action:
- `leadstream_log_event`

Purpose:
- Writes arbitrary client-side events to `wp_ls_events`.

Security:
- Accepts nonce if present (`leadstream_event`) but does not require it for public events.

Payload:
- `payload` JSON string or array.

### 5.3) Heartbeat (public) + crash notice dismiss (admin)

Actions:
- `leadstream_frontend_ping` (public)
- `leadstream_dismiss_crash_notice` (admin)

### 5.4) UTM builder (admin)

Actions:
- `generate_utm`
- `clear_utm_history`

Security:
- requires nonce `leadstream_utm_nonce` and `manage_options`.

---

## 6) Data Tables (Source of Truth)

These tables are created by the installer (prefix may vary):

### 6.1) `wp_ls_clicks`

Purpose:
- Primary conversion log for click-driven events (phone clicks, link redirects, etc.).

Notes:
- Phone click inserts store enriched metadata in `meta_data` JSON.
- Lookups exist for recent phone click correlation.

### 6.2) `wp_ls_calls`

Purpose:
- Stores provider webhook call records.

Key columns:
- `provider`, `provider_call_id` (unique)
- `from_number`, `to_number`
- `status`, `duration`, `recording_url`
- `click_id` nullable correlation to `wp_ls_clicks.id`
- `meta_data` LONGTEXT JSON

### 6.3) `wp_ls_events`

Purpose:
- Lightweight generic event log (e.g. form submits, client events).

Key columns:
- `event_name`, `event_type`
- `event_params` LONGTEXT (JSON)

---

## 7) Related Filters (Convenience)

These filters exist in core and may be useful for integrations:
- `leadstream_tel_href` (modify `tel:` link)
- `leadstream_default_country_code` (used by phone normalization)
- `leadstream_normalize_phone_digits`
- `leadstream_supported_calling_codes`

---

## 8) Versioning + Compatibility Notes

- Prefer using the pipeline hook (`leadstream_conversion_recorded`) instead of calling internals directly.
- Use `event_id` for dedupe wherever possible (store it in your system).
- Treat LeadStream tables as the source of truth; your integration should be tolerant of retries and duplicates.
