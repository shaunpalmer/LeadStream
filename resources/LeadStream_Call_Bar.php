<?php
/**
 * Plugin Name: LeadStream Call Bar
 * Description: Sticky mobile call bar + shortcode for LeadStream. Shortcode: [leadstream_callbar cta="Call Now" position="bottom"].
 * Version: 1.0.0
 * Author: Shaun Palmer
 */

if (!defined('ABSPATH')) exit;

final class LS_Callbar {
  const OPT_AUTO      = 'ls_callbar_auto';       // '1' or '0'
  const OPT_POSITION  = 'ls_callbar_position';   // 'top' or 'bottom'
  const OPT_CTA       = 'ls_callbar_cta';        // default CTA text
  const NONCE_ACTION  = 'ls_callbar_click';

  public function __construct() {
    add_shortcode('leadstream_callbar', [$this, 'shortcode']);
    add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    add_action('wp_footer', [$this, 'maybe_auto_render']);           // bottom
    add_action('wp_body_open', [$this, 'maybe_auto_render_top']);    // top
    add_action('wp_ajax_leadstream_record_phone_click', [$this,'ajax_record']);
    add_action('wp_ajax_nopriv_leadstream_record_phone_click', [$this,'ajax_record']);
  }

  /** Try to fetch your “one true” number from existing LeadStream options. */
  private function get_number() : string {
    // Adjust keys to match your settings page — we try a few common ones.
    $candidates = [
      'leadstream_main_phone',
      'ls_phone',
      'leadstream_phone',
    ];
    foreach ($candidates as $k) {
      $v = trim((string) get_option($k, ''));
      if ($v) return $v;
    }
    return ''; // nothing configured
  }

  /** Normalize number to a tel: (leave formatting of the visible text alone) */
  private function tel_href(string $number) : string {
    return 'tel:' . preg_replace('/\D+/', '', $number);
  }

  /** Enqueue assets & expose runtime data */
  public function enqueue() {
    $css = plugin_dir_url(__FILE__) . 'assets/ls-callbar.css';
    $js  = plugin_dir_url(__FILE__) . 'assets/ls-callbar.js';

    wp_enqueue_style('ls-callbar', $css, [], '1.0.0');
    wp_enqueue_script('ls-callbar', $js, [], '1.0.0', true);

    $data = [
      'ajaxUrl'   => admin_url('admin-ajax.php'),
      'nonce'     => wp_create_nonce(self::NONCE_ACTION),
      'enabled'   => true,
    ];
    wp_add_inline_script('ls-callbar', 'window.LeadStreamCallBarData=' . wp_json_encode($data) . ';', 'before');
  }

  /** Shortcode: [leadstream_callbar cta="Call Now" position="bottom"] */
  public function shortcode($atts = []) {
    $atts = shortcode_atts([
      'cta'      => get_option(self::OPT_CTA, 'Call Now'),
      'position' => get_option(self::OPT_POSITION, 'bottom'), // top|bottom
      'class'    => '',
    ], $atts, 'leadstream_callbar');

    return $this->render($atts['cta'], $atts['position'], $atts['class'], false);
  }

  /** Auto inject at bottom via wp_footer if toggle on & position=bottom */
  public function maybe_auto_render() {
    if (!wp_is_mobile()) return; // default: mobile only; change if you like
    if (get_option(self::OPT_AUTO) !== '1') return;
    if (get_option(self::OPT_POSITION, 'bottom') !== 'bottom') return;
    echo $this->render(get_option(self::OPT_CTA, 'Call Now'), 'bottom', '', true);
  }

  /** Auto inject at top via wp_body_open if toggle on & position=top */
  public function maybe_auto_render_top() {
    if (!wp_is_mobile()) return;
    if (get_option(self::OPT_AUTO) !== '1') return;
    if (get_option(self::OPT_POSITION, 'bottom') !== 'top') return;
    echo $this->render(get_option(self::OPT_CTA, 'Call Now'), 'top', '', true);
  }

  /** Core HTML builder */
  private function render(string $cta, string $position, string $extraClass, bool $isAuto) : string {
    $number = $this->get_number();
    if (!$number) return ''; // silently bail if not configured

    $telHref = esc_url($this->tel_href($number));
    $digits  = preg_replace('/\D+/', '', $number);
    $posMod  = $position === 'top' ? 'top' : 'bottom';

    ob_start(); ?>
    <div class="ls-callbar ls-callbar--<?php echo esc_attr($posMod); ?> <?php echo esc_attr($extraClass); ?>" role="region" aria-label="Call bar">
      <a class="ls-callbar__btn" href="<?php echo $telHref; ?>"
         data-ls-phone="<?php echo esc_attr($digits); ?>"
         data-ls-origin="<?php echo $isAuto ? 'auto' : 'shortcode'; ?>">
        <span class="ls-callbar__cta"><?php echo esc_html($cta); ?></span>
        <span class="ls-callbar__num"><?php echo esc_html($number); ?></span>
      </a>
    </div>
    <?php
    return ob_get_clean();
  }

  /** Minimal click logger endpoint (mirrors your earlier phone-tracking) */
  public function ajax_record() {
    check_ajax_referer(self::NONCE_ACTION, 'nonce');
    // Collect a few fields (expand as needed)
    $phone   = sanitize_text_field($_POST['phone'] ?? '');
    $href    = esc_url_raw($_POST['page_url'] ?? '');
    $origin  = sanitize_text_field($_POST['origin'] ?? 'callbar');

    // TODO: store to your plugin table. For now, just OK.
    wp_send_json_success(['ok' => true]);
  }
}
new LS_Callbar();

