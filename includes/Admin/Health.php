<?php
namespace LS\Admin;

defined('ABSPATH') || exit;

class Health {
    /**
     * Compute phone tracking enqueue status and related diagnostics.
     * Returns array with: enabled, numbers_count, selectors_count, default_cc, would_enqueue, script_src, issues[]
     */
    public static function phone_status() {
        $enabled   = (int) get_option('leadstream_phone_enabled', 1) === 1;
        $numbers   = get_option('leadstream_phone_numbers', []);
        $selectors = (string) get_option('leadstream_phone_selectors', '');
        $default_cc = (string) get_option('leadstream_default_country_code', '1');

        $numbers_count   = is_array($numbers) ? count(array_filter($numbers)) : 0;
        $selectors_count = 0;
        if ($selectors !== '') {
            $selectors_count = count(array_filter(array_map('trim', preg_split('/\r?\n/', $selectors))));
        }

        // Determine if frontend would enqueue the script
        $would_enqueue = ($enabled && $numbers_count > 0);

        // Resolve asset URL from plugin root reliably
        if (defined('LS_FILE')) {
            $base = LS_FILE;
        } else {
            $base = dirname(dirname(__DIR__)) . '/leadstream-analytics-injector.php';
        }
        $script_src = plugins_url('assets/js/phone-tracking.js', $base);

        $issues = [];
        if (!$enabled) { $issues[] = 'Phone tracking is disabled.'; }
        if ($numbers_count === 0) { $issues[] = 'No phone numbers configured.'; }
        // Soft check: if LS_FILE not defined, we’re using fallback resolution
        if (!defined('LS_FILE')) { $issues[] = 'LS_FILE not defined; using fallback plugin path.'; }

        return compact('enabled','numbers_count','selectors_count','default_cc','would_enqueue','script_src','issues');
    }

    /**
     * Render a compact admin panel in the Phone tab showing enqueue status and quick links.
     */
    public static function render_phone_panel() {
        if (!current_user_can('manage_options')) return;
        $st = self::phone_status();
        $badge_color = $st['would_enqueue'] ? '#00a32a' : '#d63638';
        $badge_text  = $st['would_enqueue'] ? 'Ready to enqueue' : 'Blocked';
        $home_url    = home_url('/');
        $debug_url   = add_query_arg('ls_phone_debug', '1', $home_url);
        ?>
    <details class="ls-acc" style="margin-top: 14px; border:1px solid <?php echo esc_attr($badge_color); ?>; border-radius:6px; background:#fff;">
            <summary style="padding:12px 16px; background:#f6f7f7; color:#1d2327; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:8px; justify-content:space-between;">
                <span style="display:flex; align-items:center; gap:8px;"><span class="dashicons dashicons-yes"></span> Phone Tracking Enqueue Status</span>
                <span style="font-weight:600; color:#fff; background:<?php echo esc_attr($badge_color); ?>; padding:2px 8px; border-radius:999px; font-size:12px;"><?php echo esc_html($badge_text); ?></span>
            </summary>
            <div class="inside" style="padding:16px; display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:10px;">
                <div>
                    <div style="color:#646970; font-size:12px;">Enabled</div>
                        <div style="font-weight:600;"><?php echo $st['enabled'] ? 'Yes' : 'No'; ?></div>
                </div>
                <div>
                    <div style="color:#646970; font-size:12px;">Tracked numbers</div>
                        <div style="font-weight:600;"><?php echo intval($st['numbers_count']); ?></div>
                </div>
                <div>
                    <div style="color:#646970; font-size:12px;">Custom selectors</div>
                        <div style="font-weight:600;"><?php echo intval($st['selectors_count']); ?></div>
                </div>
                <div>
                    <div style="color:#646970; font-size:12px;">Default calling country</div>
                    <div style="font-weight:600;">+<?php echo esc_html($st['default_cc'] ?: '1'); ?></div>
                </div>
                <div style="grid-column: 1 / -1; margin-top:6px;">
                    <div style="color:#646970; font-size:12px;">Script URL</div>
                        <code style="display:block; word-break:break-all;"><?php echo esc_html($st['script_src']); ?></code>
                </div>
                <div style="grid-column: 1 / -1; display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:8px;">
                    <a href="<?php echo esc_url($home_url); ?>" class="button">Open site</a>
                    <a href="<?php echo esc_url($debug_url); ?>" class="button button-primary">Open with debug badge</a>
                    <span style="color:#646970; font-size:12px;">(admin-only badge appears when <code>?ls_phone_debug=1</code> is present)</span>
                </div>
                <?php if (!empty($st['issues'])): ?>
                <div style="grid-column: 1 / -1; margin-top:8px; background:#fff5f5; border:1px solid #d63638; border-radius:4px; padding:10px;">
                    <strong style="color:#b32d2e;">Issues:</strong>
                    <ul style="margin:6px 0 0 16px; color:#b32d2e;">
                        <?php foreach ($st['issues'] as $issue): ?>
                            <li><?php echo esc_html($issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </details>
        <?php
    }
}
?>
