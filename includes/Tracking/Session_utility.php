<?php
namespace LeadStream\Tracking;

defined('ABSPATH') || exit;

/**
 * Class Session_utility
 *
 * Tracks a lightweight page-to-page "trail" for a visitor session.
 *
 * - Appends a step on each pageview (path, title, timestamp, session id)
 * - Back-fills duration on the previous step
 * - Marks CTA events and exit reasons via REST (sendBeacon)
 * - Stores the last N steps in ls_trail (cookie, JSON)
 *
 * Depends on LeadStream\Tracking\CookieManager (ls_* cookies already set).
 *
 * @package LeadStream\Tracking
 */
final class Session_utility
{
    /** Cookie that stores the rolling trail (JSON array) */
    public const COOKIE_TRAIL = 'ls_trail';

    /** Trail retention (client side). Keep it short; server has the source of truth. */
    public const TRAIL_TTL_SEC = 3 * 24 * 60 * 60; // 3 days

     /**
      * Boot the session utility: hooks actions and REST routes.
      *
      * @return void
      */
    public static function boot(): void
    {
        add_action('init', [__CLASS__, 'setup'], 1);
        add_action('template_redirect', [__CLASS__, 'log_pageview'], 5);
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

     /**
      * Ensure prerequisites (CookieManager already boots earlier).
      * Reserved for future toggles.
      *
      * @return void
      */
    public static function setup(): void
    {
        // noop for now; reserved for future toggles
    }

     /**
      * Register REST routes for exit and CTA beacons.
      *
      * @return void
      */
    public static function routes(): void
    {
        register_rest_route('leadstream/v1', '/exit', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_exit'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('leadstream/v1', '/cta', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_cta'],
            'permission_callback' => '__return_true',
        ]);
    }

     /**
      * Append a pageview step and back-fill previous step duration.
      *
      * @return void
      */
    public static function log_pageview(): void
    {
        if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
            return; // front-end only
        }

        $now     = time();
        $path    = esc_url_raw($_SERVER['REQUEST_URI'] ?? '/');
        $title   = wp_strip_all_tags(function_exists('wp_get_document_title') ? wp_get_document_title() : get_bloginfo('name'));
        $title   = mb_substr($title, 0, 120);
        $session = CookieManager::get(CookieManager::COOKIE_SESSION) ?? '';
        $vid     = CookieManager::get(CookieManager::COOKIE_ID) ?? '';

        $trail = self::read_trail();

        // Back-fill duration on previous step (ms)
        if (!empty($trail)) {
            $i = count($trail) - 1;
            if (!isset($trail[$i]['dur'])) {
                $prevTs = (int) ($trail[$i]['ts'] ?? $now);
                $trail[$i]['dur'] = max(0, ($now - $prevTs) * 1000);
            }
        }

        // Optional hint: mark if this page is a "policy/permissions" type
        $exitPages = (array) apply_filters('leadstream/exit_pages', ['/privacy-policy', '/user-permissions', '/terms']);
        $exitHint  = in_array(parse_url($path, PHP_URL_PATH), $exitPages, true) ? 'policy' : '';

        // Append current step
        $trail[] = [
            'ts'   => $now,
            'sid'  => $session,
            'vid'  => $vid ? substr($vid, 0, 8) : '', // tiny hint for debugging, not PII
            'path' => $path,
            'title'=> $title,
            'hint' => $exitHint, // may be ''
        ];

        self::write_trail($trail);
    }

    // -------------------------
     // REST callbacks
     // -------------------------

     /**
      * Record an exit event against the current (last) step.
      *
      * @param \WP_REST_Request $req
      * @return \WP_REST_Response
      */
    public static function rest_exit(\WP_REST_Request $req): \WP_REST_Response
    {
        $p      = $req->get_json_params() ?: [];
        $reason = sanitize_text_field($p['reason'] ?? 'unload'); // e.g. 'unload','external','timeout'
        $path   = esc_url_raw($p['path'] ?? ($_SERVER['REQUEST_URI'] ?? '/'));
        $dwell_ms   = isset($p['dwell_ms'])   ? (int) $p['dwell_ms']   : 0;
        $scroll_pct = isset($p['scroll_pct']) ? (int) $p['scroll_pct'] : 0;
        $form_touch = !empty($p['form_touched']) ? 1 : 0;
        $ts     = time();

        $trail = self::read_trail();
        if (!empty($trail)) {
            $i = count($trail) - 1;
            // If the beacon includes a different path (tab closed before nav), keep it
            if (!empty($path)) $trail[$i]['path'] = $path;
            $trail[$i]['exit'] = [
                'reason' => $reason,
                't' => $ts,
                'dwell_ms' => $dwell_ms,
                'scroll' => $scroll_pct,
                'form' => $form_touch
            ];
            // If no duration yet, estimate to now
            if (!isset($trail[$i]['dur']) && !empty($trail[$i]['ts'])) {
                $trail[$i]['dur'] = max(0, ($ts - (int)$trail[$i]['ts']) * 1000);
            }
            self::write_trail($trail);
        }

        // --- TODO: Persist to DB for analytics (commented, not active) ---
        /*
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ls_exits', [
            'vid' => $vid, // visitor id
            'session_id' => $session,
            'path' => $path,
            'exit_reason' => $reason,
            'dwell_ms' => $dwell_ms,
            'scroll_pct' => $scroll_pct,
            'form_touched' => $form_touch,
            'ts' => $ts
        ]);
        */

        // --- TODO: Example queries for admin tiles (commented) ---
        /*
        // Top Exit Pages (bad exits)
        // SELECT path, COUNT(*) as exits FROM wp_ls_exits WHERE exit_reason IN ('form_abandon','dead_end','wizard_exit','price_page_exit') OR (scroll_pct < 50 AND dwell_ms < 15000 AND form_touched=0) GROUP BY path ORDER BY exits DESC LIMIT 10;

        // Satisfied Exits (content complete)
        // SELECT path, COUNT(*) as exits FROM wp_ls_exits WHERE exit_reason='read_exit' OR (scroll_pct >= 75 AND dwell_ms >= 30000) GROUP BY path ORDER BY exits DESC LIMIT 10;

        // Worst Step in Quote Flow
        // SELECT path, COUNT(*) as exits FROM wp_ls_exits WHERE path LIKE '%quote%' AND exit_reason IN ('form_abandon','wizard_exit') GROUP BY path ORDER BY exits DESC LIMIT 10;
        */

        return new \WP_REST_Response(['ok' => true], 200);
    }

     /**
      * Mark a CTA on the current step.
      *
      * @param \WP_REST_Request $req
      * @return \WP_REST_Response
      */
    public static function rest_cta(\WP_REST_Request $req): \WP_REST_Response
    {
        $p     = $req->get_json_params() ?: [];
        $type  = sanitize_text_field($p['type'] ?? 'cta');   // e.g., 'phone','form','button'
        $label = sanitize_text_field($p['label'] ?? '');     // e.g., 'Call Now','Get Quote'
        $path  = esc_url_raw($p['path'] ?? ($_SERVER['REQUEST_URI'] ?? '/'));
        $ts    = time();

        $trail = self::read_trail();
        if (!empty($trail)) {
            $i = count($trail) - 1;
            $trail[$i]['cta'] = ['type' => $type, 'label' => $label, 'path' => $path, 't' => $ts];
            self::write_trail($trail);
        }

        // TODO: Optionally persist server-side.
        return new \WP_REST_Response(['ok' => true], 200);
    }

    // -------------------------
     // Public helpers
     // -------------------------

     /**
      * Read current trail (array of steps).
      *
      * @return array
      */
    public static function read_trail(): array
    {
        $raw = CookieManager::get(self::COOKIE_TRAIL);
        if (!$raw) return [];
        $data = json_decode(stripslashes($raw), true);
        return is_array($data) ? $data : [];
    }

     /**
      * Replace trail and write back to cookie (trim to max).
      *
      * @param array $trail
      * @return void
      */
    public static function write_trail(array $trail): void
    {
        $max = (int) apply_filters('leadstream/trail_max_steps', 12);
        $trail = array_slice($trail, -$max);
        CookieManager::set(
            self::COOKIE_TRAIL,
            wp_json_encode($trail, JSON_UNESCAPED_SLASHES),
            time() + self::TRAIL_TTL_SEC,
            ['secure' => is_ssl(), 'httponly' => false, 'samesite' => 'Lax']
        );
    }

     /**
      * Clear the trail cookie.
      *
      * @return void
      */
    public static function clear_trail(): void
    {
        CookieManager::expire(self::COOKIE_TRAIL);
    }
}

