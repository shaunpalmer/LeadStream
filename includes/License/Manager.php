<?php
namespace LS\License;

defined('ABSPATH') || exit;

/**
 * LeadStream License Manager
 * - Stores key hash (not raw)
 * - Activates/deactivates with remote
 * - Caches status & expiry; daily check
 */
final class Manager {
    private static ?Manager $instance = null;

    private string $opt_key         = 'ls_license_key';        // sha256
    private string $opt_status      = 'ls_license_status';     // valid|invalid|expired|deactivated
    private string $opt_expires     = 'ls_license_expires';    // unix ts (0 = never)
    private string $opt_last_check  = 'ls_license_last_check'; // unix ts

    private function __construct() {}

    public static function instance(): Manager {
        return self::$instance ??= new self();
    }

    /** True if license is currently valid */
    public function is_pro(): bool {
        $status = get_option($this->opt_status, 'invalid');
        if ($status !== 'valid') return false;
        $exp = (int) get_option($this->opt_expires, 0);
        return $exp === 0 || time() < $exp;
    }

    public static function pro(): bool { return self::instance()->is_pro(); }

    /** Masked key (for UI only) */
    public function masked_key(): string {
        $hash = (string) get_option($this->opt_key, '');
        if ($hash === '') return '';
        return substr($hash, 0, 6) . '••••••••' . substr($hash, -4);
    }

    /** Save a raw key as hash */
    private function save_hash(string $raw): void {
        update_option($this->opt_key, hash('sha256', $raw), false);
    }

    /** Activate */
    public function activate(string $raw_key): array {
        $client = new ApiClient();
        $resp   = $client->post('/v1/licenses/activate', [ 'key' => trim($raw_key) ]);

        if ($resp['ok'] && ($resp['data']['status'] ?? '') === 'valid') {
            $this->save_hash($raw_key);
            update_option($this->opt_status, 'valid', false);
            update_option($this->opt_expires, (int)($resp['data']['expires'] ?? 0), false);
        } else {
            update_option($this->opt_status, 'invalid', false);
        }
        update_option($this->opt_last_check, time(), false);
        do_action('ls_license_status_changed', get_option($this->opt_status));
        return $resp;
    }

    /** Deactivate */
    public function deactivate(): array {
        $client = new ApiClient();
        $resp   = $client->post('/v1/licenses/deactivate', []);
        update_option($this->opt_status, 'deactivated', false);
        update_option($this->opt_expires, 0, false);
        update_option($this->opt_last_check, time(), false);
        do_action('ls_license_status_changed', 'deactivated');
        return $resp;
    }

    /** Daily status check */
    public function maybe_check(): void {
        $last = (int) get_option($this->opt_last_check, 0);
        if ($last && (time() - $last) < DAY_IN_SECONDS) return;
        $client = new ApiClient();
        $resp   = $client->post('/v1/licenses/status', []);

        if ($resp['ok']) {
            $status = (string) ($resp['data']['status'] ?? 'invalid');
            $exp    = (int) ($resp['data']['expires'] ?? 0);
            update_option($this->opt_status, $status, false);
            update_option($this->opt_expires, $exp, false);
        }
        update_option($this->opt_last_check, time(), false);
    }
}