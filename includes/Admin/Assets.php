<?php
/**
 * LeadStream Admin Assets Handler
 * Handles CodeMirror integration, admin styles, and asset enqueuing
 */

namespace LS\Admin;

defined('ABSPATH') || exit;

class Assets {
    
    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_code_editor']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_styles']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_utm_builder']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_pretty_links']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_ls_admin']);
        
        // Frontend phone tracking
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_phone_tracking']);
    }
    
    /**
     * Smart CodeMirror with switch - CDN default, WordPress fallback
     */
    public static function enqueue_code_editor($hook_suffix) {
        // Only load on our plugin's settings page
        if ('toplevel_page_leadstream-analytics-injector' !== $hook_suffix) {
            return;
        }

        // CDN mode: reliable CodeMirror that actually works (WordPress fails silently)
        $codemirror_source = get_option('leadstream_codemirror_source', 'cdn');
        
        switch ($codemirror_source) {
            case 'auto':
                // Try WordPress native first (more WordPress-y)
                $editor_settings = wp_enqueue_code_editor([
                    'type' => 'text/javascript',
                    'codemirror' => [
                        'mode' => 'javascript',
                        'lineNumbers' => true,
                        'indentUnit' => 2,
                        'lineWrapping' => true,
                        'matchBrackets' => true,
                        'autoCloseBrackets' => true,
                    ]
                ]);
                
                if (false !== $editor_settings) {
                    wp_enqueue_script('wp-code-editor');
                    wp_enqueue_style('wp-codemirror');
                    $init_script = sprintf(
                        'jQuery(function($){ 
                            console.log("LeadStream: Using WordPress CodeMirror (auto mode)");
                            if ($("#custom_header_js").length) {
                                wp.codeEditor.initialize($("#custom_header_js"), %s);
                            }
                            if ($("#custom_footer_js").length) {
                                wp.codeEditor.initialize($("#custom_footer_js"), %s);
                            }
                        });',
                        wp_json_encode($editor_settings),
                        wp_json_encode($editor_settings)
                    );
                    wp_add_inline_script('wp-code-editor', $init_script);
                    break; // Success with WordPress
                }
                // WordPress failed, fallback to CDN
                
            case 'cdn':
            default:
                // CloudFlare CDN - reliable and fast (bulletproof default)
                wp_enqueue_style('leadstream-codemirror-css',
                    'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css',
                    [], '5.65.5'
                );
                wp_enqueue_script('leadstream-codemirror-js',
                    'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js',
                    [], '5.65.5', true
                );
                wp_enqueue_script('leadstream-codemirror-mode-js',
                    'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/javascript/javascript.min.js',
                    ['leadstream-codemirror-js'], '5.65.5', true
                );

                // Initialize CodeMirror on both textareas
                $inline = <<<'JS'
document.addEventListener('DOMContentLoaded', function() {
  console.log("LeadStream: Using CDN CodeMirror (reliable)");
  
  if (typeof CodeMirror !== 'undefined') {
    // Header editor
    var headerTextarea = document.getElementById('custom_header_js');
    if (headerTextarea) {
      console.log("LeadStream: Creating header editor");
      CodeMirror.fromTextArea(headerTextarea, {
        mode: 'javascript',
        lineNumbers: true,
        indentUnit: 2,
        lineWrapping: true,
        matchBrackets: true,
        autoCloseBrackets: true,
        theme: 'default'
      });
    }
    
    // Footer editor
    var footerTextarea = document.getElementById('custom_footer_js');
    if (footerTextarea) {
      console.log("LeadStream: Creating footer editor");
      CodeMirror.fromTextArea(footerTextarea, {
        mode: 'javascript',
        lineNumbers: true,
        indentUnit: 2,
        lineWrapping: true,
        matchBrackets: true,
        autoCloseBrackets: true,
        theme: 'default'
      });
    }
    
    console.log("LeadStream: CodeMirror setup complete");
  } else {
    console.error("LeadStream: CodeMirror failed to load");
  }
});
JS;
                wp_add_inline_script('leadstream-codemirror-mode-js', $inline);
                break;
                
            case 'wordpress':
                // Force WordPress only (no CDN fallback)
                $editor_settings = wp_enqueue_code_editor([
                    'type' => 'text/javascript',
                    'codemirror' => [
                        'mode' => 'javascript',
                        'lineNumbers' => true,
                        'indentUnit' => 2,
                        'lineWrapping' => true,
                        'matchBrackets' => true,
                        'autoCloseBrackets' => true,
                    ]
                ]);
                
                if (false !== $editor_settings) {
                    wp_enqueue_script('wp-code-editor');
                    wp_enqueue_style('wp-codemirror');
                    $init_script = sprintf(
                        'jQuery(function($){ 
                            console.log("LeadStream: Using WordPress CodeMirror (forced)");
                            if ($("#custom_header_js").length) {
                                wp.codeEditor.initialize($("#custom_header_js"), %s);
                            }
                            if ($("#custom_footer_js").length) {
                                wp.codeEditor.initialize($("#custom_footer_js"), %s);
                            }
                        });',
                        wp_json_encode($editor_settings),
                        wp_json_encode($editor_settings)
                    );
                    wp_add_inline_script('wp-code-editor', $init_script);
                }
                break;
        }
    }
    
    /**
     * Enqueue admin styles for proper WordPress form layout
     */
    public static function enqueue_admin_styles($hook) {
        if ('toplevel_page_leadstream-analytics-injector' !== $hook) {
            return;
        }
        
        // Ensure Dashicons are available for core-like pagination icons
        wp_enqueue_style('dashicons');

        wp_enqueue_style(
            'leadstream-admin-css',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/leadstream-admin.css',
            ['wp-admin'],
            '2.5.7'
        );
        
        // Add CodeMirror styling that works with both WP and CDN versions
        $codemirror_css = '
        .CodeMirror {
            border: 1px solid #ddd !important;
            font-family: Consolas, Monaco, "Courier New", monospace !important;
            font-size: 14px !important;
            line-height: 1.4 !important;
            max-width: 800px !important;
            width: 100% !important;
        }
        .CodeMirror-scroll {
            min-height: 400px !important;
        }
        .CodeMirror-linenumber {
            color: #999 !important;
            padding: 0 8px 0 5px !important;
        }
        .CodeMirror-gutters {
            border-right: 1px solid #ddd !important;
            background-color: #f7f7f7 !important;
        }
        ';
        wp_add_inline_style('leadstream-admin-css', $codemirror_css);
    }

    /** Enqueue small admin-enhancement script and sticky styles on our pages */
    public static function enqueue_ls_admin($hook) {
        if ('toplevel_page_leadstream-analytics-injector' !== $hook) {
            return;
        }
    // Media library for logo selection in QR modal
    if (function_exists('wp_enqueue_media')) { wp_enqueue_media(); }
        // JS
        wp_enqueue_script(
            'leadstream-admin-lite',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/ls-admin.js',
            ['jquery'],
            '1.0.0',
            true
        );
        wp_localize_script('leadstream-admin-lite', 'LSAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ls-admin')
        ]);

    // WordPress native color picker for appearance fields
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    $initColorPicker = 'jQuery(function($){ try { $(".ls-color").wpColorPicker && $(".ls-color").wpColorPicker(); } catch(e){} });';
    wp_add_inline_script('wp-color-picker', $initColorPicker);

                // Datepicker: Flatpickr (reliable, small). Applies to inputs on Pretty Links & Phone filters
                wp_enqueue_style(
                        'flatpickr-css',
                        'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css',
                        [],
                        '4.6.13'
                );
                wp_enqueue_script(
                        'flatpickr-js',
                        'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js',
                        [],
                        '4.6.13',
                        true
                );
                $initFlatpickr = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    function initLSDatepickers(root) {
        if (!window.flatpickr) return;
        var sel = [
            '#ls_from',
            '#ls_to',
            'form#ls-links-filters input[name="from"]',
            'form#ls-links-filters input[name="to"]'
        ].join(',');
        var scope = root || document;
        scope.querySelectorAll(sel).forEach(function (el) {
            try {
                if (el._flatpickr) { el._flatpickr.destroy(); }
                flatpickr(el, {
                    allowInput: true,
                    dateFormat: 'Y-m-d',   // submitted format
                    altInput: true,
                    altFormat: 'd/m/Y',    // displayed format
                    disableMobile: true
                });
                if (el._flatpickr && el._flatpickr.altInput) {
                    el._flatpickr.altInput.setAttribute('placeholder', 'dd/mm/yyyy');
                } else {
                    el.setAttribute('placeholder', 'dd/mm/yyyy');
                }
            } catch (e) {}
        });
    }
    // Initial
    initLSDatepickers(document);
    // Re-init after our AJAX table swaps
    document.addEventListener('ls:links:loaded', function (e) {
        initLSDatepickers(e.detail && e.detail.root ? e.detail.root : document);
    });
        document.addEventListener('ls:phone:loaded', function (e) {
            initLSDatepickers(e.detail && e.detail.root ? e.detail.root : document);
        });
});
JS;
                wp_add_inline_script('flatpickr-js', $initFlatpickr);
        // QRCode lib for generating high-res QR in modal
        wp_enqueue_script(
            'qrcodejs',
            'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
            [],
            '1.0.0',
            true
        );

        // Inline CSS: sticky tabs/header, loading shimmer, and QR modal
    $css = '
    /* Sticky table headers */
    .ls-links-table .wp-list-table thead th { position: sticky; top: 64px; background: #fff; z-index: 5; }
    body.admin-bar .ls-links-table .wp-list-table thead th { top: 96px; }
    /* Loading shimmer */
    .ls-links-table.is-loading, .ls-phone-calls.is-loading { position: relative; }
    .ls-links-table.is-loading::after, .ls-phone-calls.is-loading::after {
            content: ""; position: absolute; left:0; right:0; top:0; height:3px;
            background: linear-gradient(90deg, rgba(0,115,170,0) 0%, rgba(0,115,170,.5) 50%, rgba(0,115,170,0) 100%);
            animation: ls-shimmer 1s linear infinite;
        }
        @keyframes ls-shimmer { 0% { transform: translateX(-100%);} 100% { transform: translateX(100%);} }
        .ls-toast { position: fixed; bottom: 24px; right: 24px; background:#1d2327; color:#fff; padding:8px 12px; border-radius:4px; box-shadow:0 2px 10px rgba(0,0,0,.2); z-index:9999; }
        /* QR Modal */
        .ls-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); display:none; align-items:center; justify-content:center; z-index: 10000; }
        .ls-modal { background:#fff; border-radius:8px; max-width: 720px; width: min(92vw, 720px); padding: 16px; box-shadow: 0 10px 40px rgba(0,0,0,.3); }
        .ls-modal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom: 12px; }
        .ls-modal-title { font-size: 18px; font-weight: 600; margin:0; }
        .ls-modal-close { background:none; border:none; font-size:20px; cursor:pointer; }
        .ls-qr-wrap { display:flex; gap:16px; align-items:center; flex-wrap:wrap; }
        .ls-qr-canvas { background:#fff; padding:8px; border:1px solid #dcdcde; border-radius:6px; }
        .ls-qr-meta { flex:1; min-width: 240px; }
        .ls-qr-meta code { word-break: break-all; }
        .ls-qr-actions { margin-top:12px; display:flex; gap:8px; }
    /* QR Controls */
    .ls-qr-controls { margin-top:10px; display:flex; flex-wrap:wrap; gap:12px 16px; align-items:center; }
    .ls-qr-row { display:flex; align-items:center; gap:8px; }
    .ls-qr-row label { font-weight: 500; color:#1d2327; }
    .ls-qr-row select { min-width: 90px; }
    .ls-qr-row input[type="range"] { width: 180px; }
    .ls-qr-row input[type="color"] { width: 36px; height: 24px; padding: 0; border: 1px solid #c3c4c7; border-radius: 3px; }
    .ls-qr-hint { font-size: 12px; color:#646970; margin-top: 2px; }
    .ls-qr-hint strong { color:#1d2327; }
    .ls-qr-hint.warn { color:#b32d2e; }
    .ls-help { display:inline-flex; width:16px; height:16px; align-items:center; justify-content:center; border-radius:50%; background:#eef4ff; color:#1d2327; font-weight:600; font-size:11px; border:1px solid #c3c4c7; cursor:help; margin-left:6px; }
    .ls-help:hover { background:#e7f0fa; }
    /* Toggle switch (compact) */
    .ls-switch { position: relative; display: inline-flex; align-items:center; cursor: pointer; user-select:none; gap:8px; }
    .ls-switch input { position:absolute; opacity:0; pointer-events:none; }
    .ls-switch .ls-switch-slider { width:38px; height:20px; background:#c3c4c7; border-radius:999px; position:relative; transition:background .2s ease; box-shadow: inset 0 0 0 1px rgba(0,0,0,.06); }
    .ls-switch .ls-switch-slider::after { content:""; position:absolute; top:2px; left:2px; width:16px; height:16px; background:#fff; border-radius:50%; box-shadow:0 1px 2px rgba(0,0,0,.2); transition: transform .2s ease; }
    .ls-switch input:checked + .ls-switch-slider { background:#2271b1; }
    .ls-switch input:checked + .ls-switch-slider::after { transform: translateX(18px); }
        ';
        wp_add_inline_style('leadstream-admin-css', $css);

        // Inject modal container into footer of our admin page
        add_action('admin_footer', function() {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen && $screen->id === 'toplevel_page_leadstream-analytics-injector') {
                // Resolve a default logo from theme custom logo or site icon
                $default_logo_url = '';
                $logo_id = function_exists('get_theme_mod') ? (int) get_theme_mod('custom_logo') : 0;
                if ($logo_id) {
                    $img = wp_get_attachment_image_src($logo_id, 'full');
                    if ($img && is_array($img) && !empty($img[0])) { $default_logo_url = $img[0]; }
                } elseif (function_exists('has_site_icon') && has_site_icon()) {
                    $default_logo_url = get_site_icon_url(512);
                }
                echo '<div class="ls-modal-overlay" id="ls-qr-overlay" role="dialog" aria-modal="true" aria-hidden="true">'
                    . '<div class="ls-modal" role="document">'
                    .   '<div class="ls-modal-header">'
                    .     '<h3 class="ls-modal-title">Short Link QR Code</h3>'
                    .     '<button class="ls-modal-close" aria-label="Close" id="ls-qr-close">×</button>'
                    .   '</div>'
                    .   '<div class="ls-qr-wrap" data-default-logo="' . esc_attr($default_logo_url) . '">'
                    .     '<div id="ls-qr-canvas" class="ls-qr-canvas" aria-label="QR code image" role="img"></div>'
                    .     '<div class="ls-qr-meta">'
                    .       '<div><strong>URL:</strong> <code id="ls-qr-url"></code></div>'
                    .       '<div class="ls-qr-controls">'
                    .         '<div class="ls-qr-row">'
                    .           '<label class="ls-switch" for="ls-qr-print-size">'
                    .             '<input id="ls-qr-print-size" type="checkbox" />'
                    .             '<span class="ls-switch-slider" aria-hidden="true"></span>'
                    .             '<span>Print size 1024px</span>'
                    .           '</label>'
                    .         '</div>'
                    .         '<div class="ls-qr-hint" id="ls-qr-size-hint">&nbsp;</div>'
                    .         '<div class="ls-qr-row">'
                    .           '<label for="ls-qr-ecc">Error correction:</label><span class="ls-help" title="L low, M medium, Q quartile, H high (auto-bumps to H for large logo or transparent bg)">?</span>'
                    .           '<select id="ls-qr-ecc" aria-label="QR error correction">'
                    .             '<option value="M" selected>M (default)</option>'
                    .             '<option value="L">L</option>'
                    .             '<option value="Q">Q</option>'
                    .             '<option value="H">H</option>'
                    .           '</select>'
                    .         '</div>'
                    .         '<div class="ls-qr-row">'
                    .           '<label><input type="checkbox" id="ls-qr-transparent" /> Transparent bg</label>'
                    .         '</div>'
                    .         '<div class="ls-qr-row">'
                    .           '<label><input type="checkbox" id="ls-qr-logo-on" /> Logo overlay</label>'
                    .           '<label><input type="checkbox" id="ls-qr-use-site-logo" checked /> Use site logo</label>'
                    .           '<button class="button" id="ls-qr-logo-choose" type="button">Choose file</button>'
                    .           '<span id="ls-qr-logo-name" class="ls-qr-hint"></span>'
                    .         '</div>'
                    .         '<div class="ls-qr-row">'
                    .           '<label for="ls-qr-logo-range">Logo size:</label><span class="ls-help" title="8–20% of QR width (default 16%)">?</span>'
                    .           '<input type="range" id="ls-qr-logo-range" min="8" max="20" step="0.5" value="16" />'
                    .           '<span id="ls-qr-logo-range-val">16%</span>'
                    .           '<label for="ls-qr-pad-scale">Padding:</label>'
                    .           '<select id="ls-qr-pad-scale">'
                    .             '<option value="1.15">Small</option>'
                    .             '<option value="1.25" selected>Medium</option>'
                    .             '<option value="1.35">Large</option>'
                    .           '</select>'
                    .         '</div>'
                    .         '<div class="ls-qr-row">'
                    .           '<label for="ls-qr-fg">Foreground:</label>'
                    .           '<input type="color" id="ls-qr-fg" value="#000000" />'
                    .           '<label for="ls-qr-bg">Background:</label>'
                    .           '<input type="color" id="ls-qr-bg" value="#ffffff" />'
                    .         '</div>'
                    .         '<div class="ls-qr-hint" id="ls-qr-contrast-hint">Aim for high contrast (≥ 7:1).</div>'
                    .         '<div class="ls-qr-hint" id="ls-qr-quiet-hint">Ensures quiet zone ≥ 4 modules for print/download.</div>'
                    .       '</div>'
                    .       '<div class="ls-qr-actions">'
                    .         '<button class="button button-primary" id="ls-qr-download">Download PNG</button>'
                    .         '<button class="button" id="ls-qr-popout">Pop out</button>'
                    .         '<button class="button" id="ls-qr-copy">Copy URL</button>'
                    .       '</div>'
                    .     '</div>'
                    .   '</div>'
                    . '</div>'
                    . '</div>';
                $script = <<<'JS'
<script>(function($){
    var QR = { url: "", size: 512, ecc: "M", transparent: false, fg: "#000000", bg: "#ffffff", instance: null, moduleCount: null, logoOn: false, logoUrl: "", logoImg: null, logoPct: 0.16, prevEcc: "M", manualECC: false, padScale: 1.25, pixelRatio: (window.devicePixelRatio||1), useSiteLogo: true };
    function eccMap(v){ var L = (window.QRCode && QRCode.CorrectLevel) || {}; return {L:L.L||1, M:L.M||2, Q:L.Q||3, H:L.H||4}[v] || (L.M||2); }
    function fmtSizeHint(px){ var inches = px/300; var cm = inches*2.54; return '\u2248 ' + (Math.round(cm*10)/10) + ' cm @ 300 DPI'; }
    function updateSizeHint(){ $("#ls-qr-size-hint").text(fmtSizeHint(QR.size)); }
    function contrastRatio(hex1, hex2){ function c2rgb(h){ h=h.replace('#',''); if(h.length===3){h=h.split('').map(function(c){return c+c;}).join('');} var r=parseInt(h.substr(0,2),16)/255, g=parseInt(h.substr(2,2),16)/255, b=parseInt(h.substr(4,2),16)/255; return [r,g,b]; } function lum(c){ var a=c.map(function(v){ return v<=0.03928? v/12.92 : Math.pow((v+0.055)/1.055,2.4); }); return 0.2126*a[0]+0.7152*a[1]+0.0722*a[2]; } var L1=lum(c2rgb(hex1)), L2=lum(c2rgb(hex2)); var hi=Math.max(L1,L2), lo=Math.min(L1,L2); return (hi+0.05)/(lo+0.05); }
    function updateContrastHint(){ var hint = $("#ls-qr-contrast-hint"); if(QR.transparent){ hint.text('Transparent background — ensure placement on a high-contrast background.'); hint.addClass('warn'); return; } var ratio = contrastRatio(QR.fg, QR.bg); if(ratio < 7){ hint.text('⚠ Low contrast ('+ (Math.round(ratio*10)/10) +':1); scanning may fail.'); hint.addClass('warn'); } else { hint.text('Contrast OK ('+ (Math.round(ratio*10)/10) +':1).'); hint.removeClass('warn'); } }
    function roundRect(ctx, x, y, w, h, r){ ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r); ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath(); }
    function drawLogoWithKnockout(ctx, qrSizePx, modules, logoImg){ try { if(!QR.logoOn || !logoImg) return; var moduleSize = qrSizePx / modules; var pct = Math.min(Math.max(QR.logoPct, 0.08), 0.20); var logoW = Math.min(qrSizePx * pct, qrSizePx * 0.20); var logoH = logoW; var padPx = Math.max(0, (QR.padScale||1.25) - 1.0) * logoW; var boxW = logoW + padPx * 2; var boxH = logoH + padPx * 2; var cx = qrSizePx / 2, cy = qrSizePx / 2; var x = cx - boxW / 2, y = cy - boxH / 2; var r = Math.max(2 * moduleSize, 6); ctx.save(); if(!QR.transparent){ ctx.fillStyle = '#fff'; ctx.beginPath(); roundRect(ctx, x, y, boxW, boxH, r); ctx.fill(); } ctx.restore(); ctx.drawImage(logoImg, cx - logoW / 2, cy - logoH / 2, logoW, logoH); } catch(e){} }
    function maybeAutoECC(){ if(QR.manualECC) return; var needH = (QR.logoPct > 0.16) || QR.transparent; if(needH && QR.ecc !== 'H'){ QR.ecc = 'H'; $('#ls-qr-ecc').val('H'); } }
    function renderQR(){
        var $wrap = $("#ls-qr-canvas");
        $wrap.empty();
        if (!window.QRCode) return;
        var pixelRatio = QR.pixelRatio = (window.devicePixelRatio||1);
        var renderPx = Math.round(QR.size * pixelRatio);
        var opts = { text: QR.url, width: renderPx, height: renderPx, correctLevel: eccMap(QR.ecc), colorDark: QR.fg, colorLight: QR.transparent ? "rgba(0,0,0,0)" : QR.bg };
        QR.instance = new QRCode($wrap[0], opts);
        // Capture module count if available for quiet-zone calculations
        try { QR.moduleCount = (QR.instance && QR.instance._oQRCode && QR.instance._oQRCode.getModuleCount) ? QR.instance._oQRCode.getModuleCount() : null; } catch(e){ QR.moduleCount = null; }
        // If print size and modules too small, re-render at a larger pixel size to keep module >= 4px
        if(QR.moduleCount){ var modPxTry = (renderPx / QR.moduleCount); if(QR.size >= 1024 && modPxTry < 4){ var renderPx2 = Math.ceil(QR.moduleCount * 4); $wrap.empty(); opts.width = renderPx2; opts.height = renderPx2; QR.instance = new QRCode($wrap[0], opts); renderPx = renderPx2; }
            try { QR.moduleCount = (QR.instance && QR.instance._oQRCode && QR.instance._oQRCode.getModuleCount) ? QR.instance._oQRCode.getModuleCount() : QR.moduleCount; } catch(e){}
        }
        // Downscale canvas for crisp on-screen preview
        var $c = $("#ls-qr-canvas canvas").first(); if($c.length){ $c[0].style.width = QR.size + 'px'; $c[0].style.height = QR.size + 'px'; }
        updateSizeHint();
        updateContrastHint();
        // If logo selected, draw it atop the QR canvas with knockout
        var $c2 = $("#ls-qr-canvas canvas").first(); if($c2.length){ if(QR.logoOn && QR.logoImg){ if(QR.logoImg.complete){ drawLogoWithKnockout($c2[0].getContext('2d'), $c2[0].width, QR.moduleCount||1, QR.logoImg); } else { QR.logoImg.onload = function(){ drawLogoWithKnockout($c2[0].getContext('2d'), $c2[0].width, QR.moduleCount||1, QR.logoImg); }; } } }
        // Module size advisory for print size
        if(QR.moduleCount){ var modPx = (renderPx / QR.moduleCount); var hint = document.getElementById('ls-qr-quiet-hint'); if(hint){ var msg = 'Ensures quiet zone ≥ 4 modules for print/download.'; if(QR.size >= 1024 && modPx < 4){ msg += ' ⚠ Module size ~' + (Math.round(modPx*10)/10) + 'px; consider larger size for print.'; hint.classList.add('warn'); } else { hint.classList.remove('warn'); } hint.textContent = msg; }
        }
    }
    function openQR(url, filename){
        var $ov = $("#ls-qr-overlay");
        QR.url = url; QR.size = 512; QR.ecc = "M"; QR.transparent = false; QR.fg = "#000000"; QR.bg = "#ffffff";
    QR.logoOn = false; QR.logoUrl = ""; QR.logoImg = null; QR.prevEcc = "M"; QR.logoPct = 0.16; QR.padScale = 1.25; QR.manualECC = false; QR.useSiteLogo = true;
        $("#ls-qr-logo-on").prop('checked', false); $("#ls-qr-logo-name").text('');
    $("#ls-qr-logo-range").val('16').prop('disabled', true); $("#ls-qr-logo-range-val").text('16%');
        $("#ls-qr-pad-scale").val('1.25').prop('disabled', true);
        $("#ls-qr-use-site-logo").prop('checked', true);
        $("#ls-qr-url").text(url);
        $("#ls-qr-print-size").prop("checked", false);
        $("#ls-qr-transparent").prop("checked", false);
        $("#ls-qr-ecc").val("M");
        $("#ls-qr-fg").val("#000000");
        $("#ls-qr-bg").val("#ffffff");
        // Offer default logo if present
        var dlogo = $(".ls-qr-wrap").data('default-logo'); if(dlogo){ QR.logoUrl = String(dlogo); QR.logoImg = new Image(); QR.logoImg.crossOrigin = 'anonymous'; QR.logoImg.src = QR.logoUrl; $("#ls-qr-logo-name").text('Using site logo'); }
        renderQR();
        $ov.css("display","flex").hide().fadeIn(120).attr("aria-hidden","false").data("url", url).data("filename", filename);
    }
    function closeQR(){ $("#ls-qr-overlay").fadeOut(120, function(){ $(this).hide(); }).attr("aria-hidden","true"); }
    $(document).on("click", "button[data-url][data-filename]", function(){
        var url = $(this).data("url"); var fn = $(this).data("filename") || "qr.png";
        openQR(url, fn);
    });
    $(document).on("click", "#ls-qr-close, #ls-qr-overlay", function(e){ if(e.target === this) closeQR(); });
    $(document).on("change", "#ls-qr-print-size", function(){ QR.size = this.checked ? 1024 : 512; renderQR(); });
    $(document).on("change", "#ls-qr-ecc", function(){ QR.ecc = this.value || "M"; QR.manualECC = true; renderQR(); });
    $(document).on("change", "#ls-qr-transparent", function(){ QR.transparent = !!this.checked; maybeAutoECC(); renderQR(); });
    $(document).on("change", "#ls-qr-fg", function(){ QR.fg = this.value || "#000000"; renderQR(); });
    $(document).on("change", "#ls-qr-bg", function(){ QR.bg = this.value || "#ffffff"; renderQR(); });
    $(document).on('change', '#ls-qr-logo-on', function(){ var on = !!this.checked; QR.logoOn = on; $('#ls-qr-logo-range, #ls-qr-pad-scale, #ls-qr-use-site-logo').prop('disabled', !on); maybeAutoECC(); renderQR(); });
    $(document).on('input change', '#ls-qr-logo-range', function(){ var v = parseFloat(this.value)||14; if(v < 8) v = 8; if(v > 20) v = 20; QR.logoPct = v/100; $('#ls-qr-logo-range-val').text(v+'%'); maybeAutoECC(); renderQR(); });
    $(document).on('change', '#ls-qr-pad-scale', function(){ var v = parseFloat(this.value)||1.25; QR.padScale = v; renderQR(); });
    $(document).on('change', '#ls-qr-use-site-logo', function(){ QR.useSiteLogo = !!this.checked; if(QR.useSiteLogo){ var dlogo = $(".ls-qr-wrap").data('default-logo'); if(dlogo){ QR.logoUrl = String(dlogo); QR.logoImg = new Image(); QR.logoImg.crossOrigin = 'anonymous'; QR.logoImg.onload = function(){ $("#ls-qr-logo-name").text('Using site logo'); renderQR(); }; QR.logoImg.src = QR.logoUrl; } } });
    $(document).on('click', '#ls-qr-logo-choose', function(e){ e.preventDefault(); if(!window.wp || !wp.media){ alert('Media library unavailable.'); return; } var frame = wp.media({ title: 'Select Logo', library: { type: 'image' }, multiple: false, button: { text: 'Use this logo' } }); frame.on('select', function(){ var sel = frame.state().get('selection').first(); if(!sel) return; var data = sel.toJSON(); var url = (data.sizes && data.sizes.medium && data.sizes.medium.url) ? data.sizes.medium.url : data.url; QR.logoUrl = url; QR.logoImg = new Image(); QR.logoImg.crossOrigin = 'anonymous'; QR.logoImg.onload = function(){ $("#ls-qr-logo-name").text((data.title||data.filename||'Logo')); renderQR(); }; QR.logoImg.src = url; }); frame.open(); });
    function buildFileName(base, size){ var today = new Date(); function pad(n){return (n<10?'0':'')+n;} var y=today.getFullYear(); var m=pad(today.getMonth()+1); var d=pad(today.getDate()); var name = (base||'qr').replace(/\.[a-zA-Z0-9]+$/,''); return name + '-' + size + 'px-' + y + m + d + '.png'; }
    function canvasWithQuietZone(srcCanvas){ var mCount = QR.moduleCount || null; if(!mCount){ return srcCanvas; } var renderPx = srcCanvas.width; var modulePx = renderPx / mCount; var quiet = Math.max(1, Math.ceil(4 * modulePx)); var out = document.createElement('canvas'); out.width = renderPx + quiet*2; out.height = renderPx + quiet*2; var ctx = out.getContext('2d'); if(!QR.transparent){ ctx.fillStyle = QR.bg; ctx.fillRect(0,0,out.width,out.height); } ctx.drawImage(srcCanvas, quiet, quiet); // Update hint text
        var hint = document.getElementById('ls-qr-quiet-hint'); if(hint){ hint.textContent = 'Quiet zone: 4 modules (' + quiet + ' px) will be included in PNG.'; }
        return out; }
    $(document).on("click", "#ls-qr-download", function(){
        var $c = $("#ls-qr-canvas canvas").first(); if(!$c.length){return;}
        var dlCanvas = canvasWithQuietZone($c[0]);
        var link = document.createElement("a"); link.href = dlCanvas.toDataURL("image/png");
        var base = $("#ls-qr-overlay").data("filename") || "qr.png"; link.download = buildFileName(base, QR.size); link.click();
    });
    $(document).on("click", "#ls-qr-popout", function(){
        var $c = $("#ls-qr-canvas canvas").first(); if(!$c.length){return;}
    var dlCanvas = canvasWithQuietZone($c[0]);
    var data = dlCanvas.toDataURL("image/png");
    var w = window.open(); if (w) { w.document.write("<title>QR</title><img src=\"" + data + "\" style=\"max-width:100%;height:auto;display:block;margin:0 auto\" alt=\"QR code\" />"); }
    });
    $(document).on("click", "#ls-qr-copy", function(){
        var url = $("#ls-qr-overlay").data("url") || $("#ls-qr-url").text();
        if(navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(url); }
    });
})(jQuery);</script>
JS;
                echo $script;
            }
        });
    }
    
    /**
     * Enqueue admin JavaScript for settings functionality
     */
    public static function enqueue_admin_scripts($hook) {
        // Only load on our plugin's settings page
        if ('toplevel_page_leadstream-analytics-injector' !== $hook) {
            return;
        }
        
        // Enqueue our admin settings JavaScript
        wp_enqueue_script(
            'leadstream-admin-settings',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/leadstream-admin-settings.js',
            ['jquery'], // Dependencies: jQuery for FAQ functionality
            '2.6.4-' . time(), // Force cache refresh with timestamp
            true // Load in footer
        );
    }

    /**
     * Enqueue UTM builder scripts on the UTM tab
     */
    public static function enqueue_utm_builder($hook) {
        // Only on the LeadStream settings page
        if ($hook !== 'toplevel_page_leadstream-analytics-injector') {
            return;
        }

        // Only if we're on the UTM tab
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'utm') {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
        $plugin_dir = plugin_dir_path(dirname(dirname(__FILE__)));
        
        wp_enqueue_script(
            'leadstream-utm-builder',
            $plugin_url . 'assets/js/utm-builder.js',
            ['jquery'],
            filemtime($plugin_dir . 'assets/js/utm-builder.js'),
            true
        );

        // Add localized data for AJAX
        wp_localize_script('leadstream-utm-builder', 'leadstream_utm_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('leadstream_utm_nonce')
        ]);
    }

    /**
     * Enqueue Pretty Links dashboard assets
     */
    public static function enqueue_pretty_links($hook) {
        // Only on the LeadStream settings page
        if ($hook !== 'toplevel_page_leadstream-analytics-injector') {
            return;
        }

        // Only if we're on the links tab
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'links') {
            return;
        }

        // Enqueue WordPress list table styles (ensures proper styling)
        wp_enqueue_style('list-tables');
        
        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
        $plugin_dir = plugin_dir_path(dirname(dirname(__FILE__)));
        
        // Create the JS file if it doesn't exist
        $js_file = $plugin_dir . 'assets/js/pretty-links.js';
        if (!file_exists($js_file)) {
            self::create_pretty_links_js($js_file);
        }
        
        wp_enqueue_script(
            'leadstream-pretty-links',
            $plugin_url . 'assets/js/pretty-links.js',
            ['jquery'],
            filemtime($js_file),
            true
        );

        // Add localized data for AJAX
        wp_localize_script('leadstream-pretty-links', 'leadstream_links_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('leadstream_links_nonce')
        ]);
    }

    /**
     * Create Pretty Links JavaScript file
     */
    private static function create_pretty_links_js($file_path) {
        $js_content = <<<'JS'
jQuery(document).ready(function($) {
    'use strict';

    // Copy link functionality
    $('.copy-link-btn').on('click', function(e) {
        e.preventDefault();
        const url = $(this).data('url');
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
                alert('Link copied to clipboard!');
            }).catch(function() {
                // Fallback
                copyToClipboardFallback(url);
            });
        } else {
            copyToClipboardFallback(url);
        }
    });

    // Fallback copy method
    function copyToClipboardFallback(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            alert('Link copied to clipboard!');
        } catch (err) {
            console.error('Could not copy text: ', err);
            alert('Could not copy link. Please copy manually: ' + text);
        }
        
        document.body.removeChild(textArea);
    }

    // Test link functionality
    $('.test-link-btn').on('click', function(e) {
        e.preventDefault();
        const url = $(this).data('url');
        window.open(url, '_blank');
    });

    // Delete confirmation
    $('.delete-link-btn').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this link? This action cannot be undone.')) {
            e.preventDefault();
        }
    });
});
JS;

        // Ensure the directory exists
        $dir = dirname($file_path);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        file_put_contents($file_path, $js_content);
    }
    
    /**
     * Enqueue phone tracking script on frontend
     */
    public static function enqueue_phone_tracking() {
        // Only enqueue if phone tracking is enabled and numbers are configured
        $phone_enabled = get_option('leadstream_phone_enabled', 1);
        $phone_numbers = get_option('leadstream_phone_numbers', array());
        
        if (!$phone_enabled || empty($phone_numbers)) {
            return;
        }
        
        wp_enqueue_script(
            'leadstream-phone-tracking',
            plugin_dir_url(dirname(__DIR__)) . 'assets/js/phone-tracking.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        // Get phone selectors
        $phone_selectors = get_option('leadstream_phone_selectors', '');
        
        // Get GA4 ID if available (using GTM container id option for now)
        $ga_id = get_option('leadstream_gtm_id', '');

        // Build alternate dialing variants for matching (national/E.164).
        // We do NOT change what visitors dial; this is only to improve tracking matches.
        $default_cc = (string) apply_filters('leadstream_default_country_code', (string) get_option('leadstream_default_country_code', '1'));
        $alt_numbers = [];
        foreach ((array) $phone_numbers as $n) {
            $digits = preg_replace('/\D+/', '', (string) $n);
            if ($digits === '') { continue; }
            // E.164-ish with country code
            $alt_numbers[] = $default_cc . ltrim($digits, '0');
            // National form (keep a single leading 0 if present in source)
            $alt_numbers[] = (strpos((string) $n, '0') === 0) ? ('0' . ltrim($digits, '0')) : $digits;
        }
        $alt_numbers = array_values(array_unique(array_filter($alt_numbers)));
        
                wp_localize_script('leadstream-phone-tracking', 'LeadStreamPhone', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'numbers' => $phone_numbers,
            'altNumbers' => $alt_numbers,
            'selectors' => $phone_selectors,
            'nonce' => wp_create_nonce('leadstream_phone_click'),
            'ga_id' => $ga_id,
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'show_feedback' => true
        ]);

                // TEMP: callbar assets are enqueued by LS_Callbar::enqueue to avoid double-binding.
                // See hotfix/phone-tracking-baseline.
                // The previous duplicate enqueue/localize for leadstream-callbar.js has been commented out intentionally.

                // Admin-only smoke test helper (dev-only)
                if (current_user_can('manage_options')) {
                    $smoke = <<<'JS'
window.__LS_SMOKE__ = function(){
    try {
        var n = (window.LeadStreamPhone && LeadStreamPhone.nonce) || (window.LeadStreamCallBarData && LeadStreamCallBarData.nonce) || '';
        var fd = new FormData();
        fd.append('action','leadstream_record_phone_click');
        fd.append('nonce', n);
        fd.append('phone','0220601100');
        fd.append('original_phone','022 060 11 00');
        fd.append('origin','callbar');
        fd.append('page_url', location.href);
        fd.append('page_title', document.title);
        return fetch((window.ajaxurl||'')|| (LeadStreamPhone && LeadStreamPhone.ajax_url) || (window.LeadStreamCallBar && LeadStreamCallBar.ajaxUrl) || '/wp-admin/admin-ajax.php', { method:'POST', body: fd }).then(function(r){ return r.json(); }).then(function(j){ console.log('LS smoke:', j); return j; });
    } catch(e){ console.warn('LS smoke error', e); return Promise.reject(e); }
};
JS;
                    wp_add_inline_script('leadstream-phone-tracking', $smoke);
                }
            }
}
