<?php
if (!defined('ABSPATH')) exit;

final class LS_Callbar {
  // Use existing plugin option keys from Admin\Settings.php
  const OPT_AUTO      = 'leadstream_callbar_enabled';   // 1|0
  const OPT_POSITION  = 'leadstream_callbar_position';  // 'top'|'bottom'
  const OPT_ALIGN     = 'leadstream_callbar_align';     // 'left'|'center'|'right'
  const OPT_CTA       = 'leadstream_callbar_cta';       // CTA text
  const OPT_MOBILE    = 'leadstream_callbar_mobile_only'; // 1|0
  const OPT_DEFAULT   = 'leadstream_callbar_default';   // default phone number
  const NONCE_ACTION  = 'ls_callbar_click';

  private static $shortcode_rendered = false; // prevent duplicates

  public function __construct() {
    add_shortcode('leadstream_callbar', [$this, 'shortcode']);
    add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    add_action('wp_footer', [$this, 'maybe_auto_render']);           // bottom
    add_action('wp_body_open', [$this, 'maybe_auto_render_top']);    // top
  }

  // Static initializer used by plugin bootstrap
  public static function init() : void {
    if (!defined('LS_CALLBAR_BOOTSTRAPPED')) {
      define('LS_CALLBAR_BOOTSTRAPPED', true);
      new self();
    }
  }

  /** Try to fetch your “one true” number from existing LeadStream options. */
  private function get_number() : string {
    // Adjust keys to match your settings page — we try a few common ones.
    $candidates = [
      self::OPT_DEFAULT,
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
    $raw = trim($number);
    // Preserve leading '+' when provided (international E.164), otherwise keep national digits including any leading 0
    if (strpos($raw, '+') === 0) {
      $digits = '+' . preg_replace('/\D+/', '', ltrim($raw, '+'));
    } else {
      $digits = preg_replace('/\D+/', '', $raw);
    }
    /**
     * Filter the final tel: href value.
     * @param string $href  The computed tel: href (e.g., 'tel:+6421234567' or 'tel:021234567').
     * @param string $digits The normalized dialable portion including optional leading '+'.
     * @param string $original The original number provided in settings/shortcode.
     */
    $href = 'tel:' . $digits;
    return apply_filters('leadstream_tel_href', $href, $digits, $number);
  }

  /** Enqueue assets & expose runtime data */
  public function enqueue() {
    $css = plugin_dir_url(__FILE__) . '../assets/css/leadstream-callbar.css';
    $js  = plugin_dir_url(__FILE__) . '../assets/js/leadstream-callbar.js';

    wp_enqueue_style('ls-callbar', $css, [], '1.0.0');
    wp_enqueue_script('ls-callbar', $js, [], '1.0.0', true);

    $data = [
      'ajaxUrl'   => admin_url('admin-ajax.php'),
      // Use PhoneHandler nonce so clicks are recorded in DB
      'nonce'     => wp_create_nonce('leadstream_phone_click'),
      'enabled'   => true,
      'mobileOnly'=> (int) get_option(self::OPT_MOBILE, 1) === 1,
    ];
  // Provide a disabled creation config so JS early-exits its auto-create path (we render via PHP)
  $pre = 'window.LeadStreamCallBar = Object.assign({ enabled: false }, window.LeadStreamCallBar || {});\n';
  $pre .= 'window.LeadStreamCallBarData=' . wp_json_encode($data) . ';';
  wp_add_inline_script('ls-callbar', $pre, 'before');

    // Inline theme overrides based on saved options
  $bg          = $this->get_color('leadstream_callbar_bg', '#000000');
  $btn_bg      = $this->get_color('leadstream_callbar_btn_bg', '#ffce00');
  $btn_text    = $this->get_color('leadstream_callbar_btn_text', '#000000');
  $hover_bg    = $this->get_color('leadstream_callbar_hover_bg', '#fff200');
  $hover_text  = $this->get_color('leadstream_callbar_hover_text', '#111111');
  // Slightly warmer default border to complement the default button yellow
  $border      = $this->get_color('leadstream_callbar_border', '#b8860b');
  $font_size   = $this->get_size('leadstream_callbar_font_size', '1rem');
  $radius      = $this->get_radius('leadstream_callbar_radius', '50%');
  $border_w    = $this->get_border_width('leadstream_callbar_border_width', '1px');

  $inline = ".ls-callbar{background:{$bg}}\n" .
        ".ls-callbar__btn{background:{$btn_bg};color:{$btn_text};border-color:{$border};border-width:{$border_w};border-style:solid;font-size:{$font_size};border-radius:{$radius}}\n" .
        ".ls-callbar__btn:hover{background:{$hover_bg};color:{$hover_text}}\n";
    wp_add_inline_style('ls-callbar', $inline);
  }

  private function get_color(string $key, string $default): string {
    $val = (string) get_option($key, $default);
    // Basic hex color validation; fallback on invalid
    if (function_exists('sanitize_hex_color')) {
      $san = sanitize_hex_color($val);
      return $san ? $san : $default;
    }
    return preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', $val) ? $val : $default;
  }

  private function get_size(string $key, string $default): string {
    $val = trim((string) get_option($key, $default));
    if ($val === '') return $default;
    $minRem = 1.0;   // at least 1rem for readability
    $maxRem = 1.6;   // cap to avoid oversized UI
    $minPx  = 16;    // ~1rem baseline
    $maxPx  = 26;    // ~1.625rem cap

    // If numeric only, treat as rem and clamp
    if (is_numeric($val)) {
      $n = (float) $val;
      $n = min($maxRem, max($minRem, $n));
      return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') . 'rem';
    }
    // With unit
    if (preg_match('/^(\d+(?:\.\d+)?)(rem|px|em)$/', $val, $m)) {
      $num  = (float) $m[1];
      $unit = $m[2];
      if ($unit === 'rem' || $unit === 'em') {
        $num = min($maxRem, max($minRem, $num));
        return rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.') . $unit;
      }
      if ($unit === 'px') {
        $num = min($maxPx, max($minPx, $num));
        return (string) (int) round($num) . 'px';
      }
    }
    return $default;
  }

  /** Border radius validator: accepts px|rem|em|%; clamps to sane values */
  private function get_radius(string $key, string $default): string {
    $val = trim((string) get_option($key, $default));
    if ($val === '') return $default;
    // Sensible design bounds:
    // - px: 3–50px
    // - rem/em: ~3px–50px equivalent (0.1875rem–3.125rem assuming 16px base)
    // - percent: 0–50%
    $minRem = 0.1875; $maxRem = 3.125;
    $minPx  = 3;      $maxPx  = 50;
    if (is_numeric($val)) {
      // Treat bare number as px for convenience
      $n = (float) $val; $n = min($maxPx, max($minPx, $n));
      return (string) (int) round($n) . 'px';
    }
    if (preg_match('/^(\d+(?:\.\d+)?)(rem|px|em|%)$/', $val, $m)) {
      $num = (float) $m[1]; $unit = $m[2];
      if ($unit === '%') {
        $num = min(50, max(0, $num));
        return rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.') . '%';
      }
      if ($unit === 'px') { $num = min($maxPx, max($minPx, $num)); return (string) (int) round($num) . 'px'; }
      // rem|em
      $num = min($maxRem, max($minRem, $num));
      return rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.') . $unit;
    }
    return $default;
  }

  /** Border width validator: px only; clamps 0–4px */
  private function get_border_width(string $key, string $default): string {
    $val = trim((string) get_option($key, $default));
    if ($val === '') return $default;
    if (is_numeric($val)) {
      $n = (float) $val; $n = min(4, max(0, $n));
      return (string) (int) round($n) . 'px';
    }
    if (preg_match('/^(\d+(?:\.\d+)?)px$/', $val, $m)) {
      $n = (float) $m[1]; $n = min(4, max(0, $n));
      return (string) (int) round($n) . 'px';
    }
    return $default;
  }

  /** Shortcode: [leadstream_callbar cta="Call Now" position="bottom"] */
  public function shortcode($atts = []) {
    if (self::$shortcode_rendered) {
      return '';
    }
    $atts = shortcode_atts([
      'cta'      => get_option(self::OPT_CTA, 'Call Now'),
      'position' => get_option(self::OPT_POSITION, 'bottom'), // top|bottom
      'align'    => get_option(self::OPT_ALIGN, 'center'),    // left|center|right
      'class'    => '',
    ], $atts, 'leadstream_callbar');

    // Allow shortcode to override alignment via extra class
    $extraClass = trim($atts['class'] . ' ' . 'ls-callbar--align-' . sanitize_key($atts['align'] ?: 'center'));

  $html = $this->render($atts['cta'], $atts['position'], $extraClass, false);
  self::$shortcode_rendered = ($html !== '');
  return $html;
  }

  /** Auto inject at bottom via wp_footer if toggle on & position=bottom */
  public function maybe_auto_render() {
    // Always render when enabled and position matches; CSS controls visibility on desktop.
  if ((int) get_option(self::OPT_AUTO, 0) !== 1) return;
  if ((string) get_option(self::OPT_POSITION, 'bottom') !== 'bottom') return;
  if ($this->page_has_shortcode()) return; // suppress auto-inject if shortcode present
    echo $this->render(get_option(self::OPT_CTA, 'Call Now'), 'bottom', '', true);
  }

  /** Auto inject at top via wp_body_open if toggle on & position=top */
  public function maybe_auto_render_top() {
  if ((int) get_option(self::OPT_AUTO, 0) !== 1) return;
  if ((string) get_option(self::OPT_POSITION, 'bottom') !== 'top') return;
  if ($this->page_has_shortcode()) return; // suppress auto-inject if shortcode present
    echo $this->render(get_option(self::OPT_CTA, 'Call Now'), 'top', '', true);
  }

  /** Core HTML builder */
  private function render(string $cta, string $position, string $extraClass, bool $isAuto) : string {
    $number = $this->get_number();
    if (!$number) return ''; // silently bail if not configured

    $telHref = esc_url($this->tel_href($number));
    $digits  = preg_replace('/\D+/', '', $number);
    $posMod  = $position === 'top' ? 'top' : 'bottom';
  $align   = get_option(self::OPT_ALIGN, 'center');
  $alignMod = in_array($align, ['left','center','right'], true) ? 'align-' . $align : 'align-center';

    ob_start(); ?>
    <div class="ls-callbar ls-callbar--<?php echo esc_attr($posMod); ?> ls-callbar--<?php echo esc_attr($alignMod); ?> <?php echo esc_attr($extraClass); ?>" role="region" aria-label="Call bar">
      <a id="ls-callbar__btn" class="ls-callbar__btn" href="<?php echo $telHref; ?>"
         data-ls-phone="<?php echo esc_attr($digits); ?>"
         data-ls-original="<?php echo esc_attr($number); ?>"
         data-ls-origin="<?php echo $isAuto ? 'auto' : 'shortcode'; ?>">
        <span class="ls-callbar__cta"><?php echo esc_html($cta); ?></span>
        <span class="ls-callbar__num"><?php echo esc_html($number); ?></span>
      </a>
    </div>
    <?php
    return ob_get_clean();
  }

  // Phone clicks are handled by LS\AJAX\PhoneHandler::record_phone_click

  /** Check if current singular content uses the call bar shortcode */
  private function page_has_shortcode(): bool {
    if (!function_exists('has_shortcode')) return false;
    if (!is_singular()) return false;
    global $post; if (!$post || empty($post->post_content)) return false;
    return has_shortcode($post->post_content, 'leadstream_callbar');
  }
}

// Provide namespaced alias so class_exists('\\LS\\LS_Callbar') succeeds
if (!class_exists('LS\\LS_Callbar')) {
  class_alias('LS_Callbar', 'LS\\LS_Callbar');
}

// Do not auto-instantiate here; plugin bootstrap calls \\LS\\LS_Callbar::init()
