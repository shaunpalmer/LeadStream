<?php
namespace LS\License;

defined('ABSPATH') || exit;

/**
 * LeadStream License Manager
 *
 * - Stores & masks key (hash at rest)
 * - Activates/deactivates with remote
 * - Caches status; re-checks daily or on demand
 */
final class Manager {
    private static ?Manager $instance = null;

    private string $opt_key         = 'ls_license_key';
    private string $opt_status      = 'ls_license_status';
    private string $opt_expires     = 'ls_license_expires';
    private string $opt_domain      = 'ls_license_domain';
    private string $opt_last_check  = 'ls_license_last_check';

    private function __construct() {}

    public static function instance(): Manager {
        return self::$instance ??= new self();
    }

    /** Public: is Pro enabled right now? */
    public function is_pro(): bool {
        $status = get_option($this->opt_status, 'invalid');
        if ($status !== 'valid') {
            return false;
        }
        $exp = (int) get_option($this->opt_expires, 0);
        return $exp === 0 || time() < $exp;
    }

    /** One-line helper for other code */
    public static function pro(): bool {
        return self::instance()->is_pro();
    }

    /** Masked key for UI */
    public function get_masked_key(): string {
        $hash = (string) get_option($this->opt_key, '');
        if ($hash === '') return '';
        return substr($hash, 0, 6) . '••••••••' . substr($hash, -4);
    }

    /** Save a raw key securely (hash at rest) */
    public function save_raw_key(string $raw): void {
        $raw = trim($raw);
        if ($raw === '') return;
        // store SHA256 hash only; server sees the raw in the request below
        update_option($this->opt_key, hash('sha256', $raw), false);
    }

    /** Return current bound domain (host only) */
    public function domain(): string {
        $stored = (string) get_option($this->opt_domain, '');
        if ($stored) return $stored;
        $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: '';
        $host = strtolower($host);
        update_option($this->opt_domain, $host, false);
        return $host;
    }

    /** Activate against remote server */
    public function activate(string $raw_key): array {
        $client = new ApiClient();
        $resp   = $client->post('/v1/licenses/activate', [
            'key'    => $raw_key,
            'domain' => $this->domain(),
            'site'   => get_bloginfo('name'),
            'url'    => home_url('/'),
            'version'=> $this->plugin_version(),
        ]);

        if ($resp['ok']) {
            $this->save_raw_key($raw_key);
            update_option($this->opt_status,  'valid', false);
            update_option($this->opt_expires, (int)($resp['data']['expires'] ?? 0), false);
            update_option($this->opt_last_check, time(), false);
            do_action('ls_license_status_changed', 'valid', $resp['data']);
        } else {
            update_option($this->opt_status, 'invalid', false);
        }
        return $resp;
    }

    /** Deactivate on remote + local */
    public function deactivate(): array {
        $client = new ApiClient();
        $resp   = $client->post('/v1/licenses/deactivate', [
            'domain' => $this->domain(),
        ]);
        update_option($this->opt_status, 'deactivated', false);
        update_option($this->opt_expires, 0, false);
        update_option($this->opt_last_check, time(), false);
        do_action('ls_license_status_changed', 'deactivated', []);
        return $resp;
    }

    /** Daily status check (cron or admin_init) */
    public function maybe_check(): void {
        $last = (int) get_option($this->opt_last_check, 0);
        if ($last && (time() - $last) < DAY_IN_SECONDS) return;

        $client = new ApiClient();
        $resp   = $client->post('/v1/licenses/status', [
            'domain' => $this->domain(),
        ]);
        if ($resp['ok']) {
            $status = (string) ($resp['data']['status'] ?? 'invalid');
            $expires= (int) ($resp['data']['expires'] ?? 0);
            update_option($this->opt_status, $status, false);
            update_option($this->opt_expires, $expires, false);
        }
        update_option($this->opt_last_check, time(), false);
    }

    /** Clear local cached license options (for testing) */
    public function clear_local_cache(): void {
        update_option($this->opt_status, 'invalid', false);
        update_option($this->opt_expires, 0, false);
        update_option($this->opt_domain, '', false);
        update_option($this->opt_last_check, 0, false);
    }

    private function plugin_version(): string {
        if (!function_exists('get_plugin_data')) require_once ABSPATH.'wp-admin/includes/plugin.php';
        $data = get_plugin_data(WP_PLUGIN_DIR.'/leadstream/leadstream.php', false, false);
        return $data['Version'] ?? '0.0.0';
    }
}
