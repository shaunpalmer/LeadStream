<?php
namespace LS\License;

defined('ABSPATH') || exit;

// DOCK: TEST-ONLY-START — Remove before shipping

/**
 * Small “Test Tools” card shown under the License tab for admins.
 * Lets you: check now, clear cache, view raw status, and test-activate arbitrary URLs.
 */
final class AdminTools {

    public static function boot(): void {
        add_action('admin_init', [__CLASS__, 'handle_post']);
        add_action('leadstream/admin/tab/license', [__CLASS__, 'render_tools_card']);
  add_action('admin_notices', [__CLASS__, 'maybe_show_notice']);
    }

    /** Handle admin-post actions for the tools */
    public static function handle_post(): void {
        if (($_POST['action'] ?? '') !== 'ls_license_tools') return;
        if (!current_user_can('manage_options')) wp_die('Not allowed.');
        check_admin_referer('ls_license_action','ls_license_nonce');

        $mgr    = Manager::instance();
        $client = new ApiClient();
        $out    = [];

        if (isset($_POST['ls_tool_check_now'])) {
            $mgr->maybe_check();
            $out['message'] = 'Status checked.';
        }

        if (isset($_POST['ls_tool_clear_cache'])) {
            delete_option('ls_license_status');
            delete_option('ls_license_expires');
            delete_option('ls_license_last_check');
            $out['message'] = 'Local license cache cleared.';
        }

        if (isset($_POST['ls_tool_status_raw'])) {
            $out['status_raw'] = $client->post('/v1/licenses/status', []);
        }

        if (isset($_POST['ls_tool_test_activate'])) {
            $url = sanitize_text_field($_POST['ls_tool_test_url'] ?? '');
            $key = sanitize_text_field($_POST['ls_tool_test_key'] ?? '');
            $payload = [];
            if ($key !== '') $payload['key'] = $key;
            if ($url !== '') $payload['url'] = $url;
            $out['activate_raw'] = $client->post('/v1/licenses/activate', $payload);
        }

  // Generate a simple smoke license (test-only) — LOCAL ONLY: do not insert to DB or save in Manager
  if (isset($_POST['ls_tool_generate_key'])) {
    $prefix = 'SMOKE-';
    $key = $prefix . strtoupper(bin2hex(random_bytes(4)));
    $out['generated_key'] = $key;
    $out['message'] = 'Generated test license (local only): ' . $key;
  }

    // stash one-time result to show in UI and to surface an admin notice
    set_transient('ls_license_tools_flash', $out, 60);
    set_transient('ls_license_tools_notice', $out, 30);
        wp_safe_redirect(add_query_arg(['page'=>'leadstream-analytics-injector','tab'=>'license'], admin_url('admin.php')));
        exit;
    }

  /** Show a top-level admin notice summarizing the last tools action */
  public static function maybe_show_notice(): void {
    if (!current_user_can('manage_options')) return;
    $data = get_transient('ls_license_tools_notice');
    if (!$data) return;
    delete_transient('ls_license_tools_notice');

    if (!empty($data['error'])) {
      echo '<div class="notice notice-error"><p>' . esc_html($data['error']) . '</p></div>';
      return;
    }
    if (!empty($data['message'])) {
      echo '<div class="notice notice-success"><p>' . esc_html($data['message']) . '</p></div>';
    }
  }

    /** Renders the card UI under the main license form */
    public static function render_tools_card(): void {
        if (!current_user_can('manage_options')) return;

        $flash = get_transient('ls_license_tools_flash') ?: [];
        if ($flash) delete_transient('ls_license_tools_flash');

  $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        ?>
        <div class="card" style="max-width:920px;margin-top:18px;padding:16px;">
          <h2 style="margin-top:0;">Test Tools</h2>
          <p class="description">For local testing & support. Dev domains (localhost/.local/.test) auto‑pass and do not consume seats.</p>

          <?php if (!empty($flash['message'])): ?>
            <div class="notice notice-success"><p><?php echo esc_html($flash['message']); ?></p></div>
          <?php endif; ?>

          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:12px;">
            <?php wp_nonce_field('ls_license_action','ls_license_nonce'); ?>
            <input type="hidden" name="action" value="ls_license_tools" />
            <p><strong>Current domain WP will send:</strong> <?php echo esc_html($host); ?></p>
            <p class="submit">
              <button class="button button-primary" name="ls_tool_check_now" value="1">Check status now</button>
              <button class="button" name="ls_tool_status_raw" value="1">Show raw status</button>
              <button class="button" name="ls_tool_clear_cache" value="1" onclick="return confirm('Clear local license cache?')">Clear local cache</button>
              <button class="button" name="ls_tool_generate_key" value="1">Generate test license</button>
            </p>
          </form>

          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ls_license_action','ls_license_nonce'); ?>
            <input type="hidden" name="action" value="ls_license_tools" />
            <table class="form-table" role="presentation">
              <tr>
                <th scope="row"><label for="ls_tool_test_url">Test activate URL</label></th>
                <td>
                  <input id="ls_tool_test_url" name="ls_tool_test_url" type="text" class="regular-text" placeholder="http://site1.test" />
                  <p class="description">POST /v1/licenses/activate with this URL (simulates different domains).</p>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="ls_tool_test_key">Override key (optional)</label></th>
                <td>
                  <input id="ls_tool_test_key" name="ls_tool_test_key" type="text" class="regular-text" placeholder="XXXX-XXXX-XXXX-XXXX" />
                  <p class="description">Leave empty to use dev bypass on local domains.</p>
                </td>
              </tr>
            </table>
            <?php submit_button('Test activate'); ?>
          </form>

          <?php if (!empty($flash['status_raw'])): ?>
            <h3>Raw status response</h3>
            <pre style="white-space:pre-wrap;background:#f6f7f7;padding:12px;"><?php echo esc_html(wp_json_encode($flash['status_raw'], JSON_PRETTY_PRINT)); ?></pre>
          <?php endif; ?>
          <?php if (!empty($flash['activate_raw'])): ?>
            <h3>Raw activate response</h3>
            <pre style="white-space:pre-wrap;background:#f6f7f7;padding:12px;"><?php echo esc_html(wp_json_encode($flash['activate_raw'], JSON_PRETTY_PRINT)); ?></pre>
          <?php endif; ?>
          <?php if (!empty($flash['generated_key'])): ?>
            <h3>Generated license</h3>
            <p><input id="ls_tools_generated_key" type="text" class="regular-text" value="<?php echo esc_attr($flash['generated_key']); ?>" readonly />
            <button class="button" id="ls_tools_copy_key">Copy</button>
            <button class="button" id="ls_tools_insert_key">Insert into License field</button>
            </p>
            <script>
            (function(){
              document.getElementById('ls_tools_copy_key').addEventListener('click', function(e){
                e.preventDefault(); navigator.clipboard.writeText(document.getElementById('ls_tools_generated_key').value);
              });
              document.getElementById('ls_tools_insert_key').addEventListener('click', function(e){
                e.preventDefault();
                var k = document.getElementById('ls_tools_generated_key').value;
                var target = document.getElementById('ls_license_key');
                if (target) { target.value = k; target.scrollIntoView(); target.focus(); }
              });
            })();
            </script>
          <?php endif; ?>
        </div>
        <?php
    }
}

// DOCK: TEST-ONLY-END — Remove before shipping

