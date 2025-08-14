<?php
namespace LS\License;

defined('ABSPATH') || exit;

/** Admin tab to manage license */
final class AdminTab {
    public static function boot(): void {
        add_action('admin_init', [__CLASS__, 'handle_post']);
        add_action('admin_init', [Manager::instance(), 'maybe_check']);
        // Your existing tab system should call this filter/action.
        add_filter('leadstream/admin/tabs', [__CLASS__, 'register_tab']);
        add_action('leadstream/admin/tab/license', [__CLASS__, 'render']);
    }

    /** Add a License tab label */
    public static function register_tab(array $tabs): array {
        $tabs['license'] = 'License';
        return $tabs;
    }

    /** Render tab content */
    public static function render(): void {
        if (!current_user_can('manage_options')) return;
        $mgr    = Manager::instance();
        $valid  = $mgr->is_pro();
        $masked = esc_html($mgr->masked_key());
        $status = esc_html(get_option('ls_license_status', 'invalid'));
        $exp    = (int) get_option('ls_license_expires', 0);
        $expStr = $exp ? date_i18n(get_option('date_format'), $exp) : 'Never';
        ?>
        <div class="wrap">
          <h2>License</h2>
          <div class="notice notice-<?php echo $valid ? 'success' : 'warning'; ?>"><p>
            <strong>Status:</strong> <?php echo ucfirst($status); ?><?php echo $valid ? " (expires: $expStr)" : ''; ?>
          </p></div>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ls_license_action','ls_license_nonce'); ?>
            <input type="hidden" name="action" value="ls_license_action" />
            <table class="form-table" role="presentation">
              <tr>
                <th scope="row"><label for="ls_license_key">License key</label></th>
                <td>
                  <input type="text" id="ls_license_key" name="ls_license_key" class="regular-text"
                         placeholder="XXXX-XXXX-XXXX-XXXX" value="" />
                  <?php if ($masked): ?><p class="description">Current: <?php echo $masked; ?></p><?php endif; ?>
                </td>
              </tr>
            </table>
            <?php submit_button($valid ? 'Re-activate' : 'Activate', 'primary', 'ls_do', false); ?>
            <?php submit_button('Deactivate', 'secondary', 'ls_deactivate', false); ?>
            <?php submit_button('Check status', 'secondary', 'ls_check', false); ?>
          </form>
          <hr>
          <p><em>Note:</em> localhost/.local/.test auto‑pass and do not consume seats.</p>
        </div>
        <?php
    }

    /** Handle POST */
    public static function handle_post(): void {
        if (($_POST['action'] ?? '') !== 'ls_license_action') return;
        if (!current_user_can('manage_options')) wp_die('Not allowed.');
        check_admin_referer('ls_license_action','ls_license_nonce');

        $mgr = Manager::instance();
        if (isset($_POST['ls_deactivate'])) {
            $mgr->deactivate();
        } elseif (isset($_POST['ls_check'])) {
            $mgr->maybe_check();
        } else {
            $raw = sanitize_text_field($_POST['ls_license_key'] ?? '');
            if ($raw !== '') $mgr->activate($raw);
        }

        wp_safe_redirect(add_query_arg(['page'=>'leadstream-analytics-injector','tab'=>'license'], admin_url('admin.php')));
        exit;
    }
}