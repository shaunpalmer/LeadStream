<?php
namespace LS\Updates;

use LS\License\Manager;

defined('ABSPATH') || exit;

/** Gated update feed */
final class Updater {
    public static function boot(): void {
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'inject']);
    }

    public static function inject($transient) {
        if (!is_object($transient) || !Manager::pro()) return $transient;
        $slug    = 'leadstream';
        $plugin  = 'leadstream/leadstream.php';
        $current = self::version();

        $url = 'https://license.yourdomain.tld/v1/updates?slug=' . rawurlencode($slug) . '&version=' . rawurlencode($current);
        $res = wp_remote_get($url, ['timeout'=>10]);
        if (is_wp_error($res)) return $transient;

        $data = json_decode((string) wp_remote_retrieve_body($res), true);
        if (!is_array($data) || empty($data['new_version'])) return $transient;

        $obj = (object) [
            'slug'        => $slug,
            'plugin'      => $plugin,
            'new_version' => $data['new_version'],
            'package'     => $data['package'] ?? '',
            'url'         => $data['url'] ?? '',
        ];
        $transient->response[$plugin] = $obj;
        return $transient;
    }

    private static function version(): string {
        if (!function_exists('get_plugin_data')) require_once ABSPATH.'wp-admin/includes/plugin.php';
        $data = get_plugin_data(WP_PLUGIN_DIR.'/leadstream/leadstream.php', false, false);
        return $data['Version'] ?? '0.0.0';
    }
}