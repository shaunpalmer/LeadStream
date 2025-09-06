<?php
/**
 * LeadStream Frontend Injector
 * Handles JavaScript injection in header/footer and GTM integration
 */

namespace LS\Frontend;

defined('ABSPATH') || exit;

class Injector {
    
    public static function init() {
        add_action('wp_head', [__CLASS__, 'inject_header_js'], 999);
        add_action('wp_footer', [__CLASS__, 'inject_footer_js'], 999);
        add_action('wp_head', [__CLASS__, 'inject_gtm_head'], 998);
        add_action('wp_footer', [__CLASS__, 'inject_gtm_noscript'], 998);
    // Tiny LS badge dot for free users (safe, unobtrusive). Allow disable via filter.
    add_action('wp_footer', [__CLASS__, 'inject_ls_badge'], 1000);
    }
    
    /**
     * Inject header JavaScript
     */
    public static function inject_header_js() {
        $header_js = get_option('custom_header_js');
        $inject_header = get_option('leadstream_inject_header', 1);
        
        if (!empty($header_js) && $inject_header) {
            echo '<!-- LeadStream: Custom Header JS -->' . "\n";
            echo '<script type="text/javascript">' . "\n" . $header_js . "\n" . '</script>' . "\n";
        }
    }
    
    /**
     * Inject footer JavaScript
     */
    public static function inject_footer_js() {
        $footer_js = get_option('custom_footer_js');
        $inject_footer = get_option('leadstream_inject_footer', 1);
        
        if (!empty($footer_js) && $inject_footer) {
            echo '<!-- LeadStream: Custom Footer JS -->' . "\n";
            echo '<script type="text/javascript">' . "\n" . $footer_js . "\n" . '</script>' . "\n";
                        // Add a small listener that captures pushed dataLayer/gtag events and forwards phone-like events to our AJAX handler
                        $nonce = wp_create_nonce('leadstream_phone_click');
                        $ajax_url = admin_url('admin-ajax.php');
                        $listen_names = get_option('leadstream_listen_event_names', 'phone_click');
                        $events = array_map('trim', explode(',', $listen_names));
                        $events_json = wp_json_encode($events);
                        $inline = <<<JS
(function(){
    if (window.LS_injection_listener) return; window.LS_injection_listener = true;
    var cfg = {
        ajax: %s,
        nonce: %s,
        events: %s
    };

    function normalizePhoneFromPayload(o){
        if (!o) return '';
        return (o.phone || o.original_phone || o.event_label || o.label || o.phone_number || '').toString();
    }

    function sendToAjax(payload){
        try{
            var phone = normalizePhoneFromPayload(payload);
            if (!phone) return;
            var body = new URLSearchParams();
            body.append('action','leadstream_record_phone_click');
            body.append('nonce', cfg.nonce);
            body.append('phone', phone);
            body.append('original_phone', payload.original || payload.original_phone || payload.event_label || '');
            body.append('origin','injected_event');
            body.append('element_type','injected_event');
            body.append('page_url', window.location.href);
            body.append('page_title', document.title || '');

            // Try keepalive fetch first (works on modern browsers, good for unload)
            fetch(cfg.ajax, { method: 'POST', body: body, keepalive: true }).catch(function(){ /* swallow */ });
        }catch(e){/* swallow */}
    }

    function matchesEventName(name){
        if (!name) return false; var n = String(name).toLowerCase();
        for (var i=0;i<cfg.events.length;i++){ if (!cfg.events[i]) continue; if (n.indexOf(String(cfg.events[i]).toLowerCase()) !== -1) return true; }
        return false;
    }

    function handleEventObject(obj){
        try{
            if (!obj) return;
            var name = obj.event || obj.name || obj.action || '';
            if (matchesEventName(name)) { sendToAjax(obj); }
            // also allow flattened params: { event: 'phone_click', phone: '...' }
        }catch(e){ }
    }

    // Wrap dataLayer.push
    try{
        if (window.dataLayer && Array.isArray(window.dataLayer)){
            var origPush = window.dataLayer.push.bind(window.dataLayer);
            window.dataLayer.push = function(){
                try{ for (var i=0;i<arguments.length;i++){ handleEventObject(arguments[i]); } }catch(e){}
                return origPush.apply(null, arguments);
            };
            // Scan any existing entries
            for (var j=0;j<window.dataLayer.length;j++){ handleEventObject(window.dataLayer[j]); }
        }
    }catch(e){}

    // Wrap gtag if present
    try{
        if (typeof window.gtag === 'function'){
            var originalGtag = window.gtag.bind(window);
            window.gtag = function(){
                try{
                    if (arguments.length >= 2 && arguments[0] === 'event'){
                        var ename = arguments[1]; var params = arguments[2] || {};
                        handleEventObject(Object.assign({event: ename}, params));
                    }
                }catch(e){}
                return originalGtag.apply(null, arguments);
            };
        }
    }catch(e){}

})();
JS;
                        // Print with safe JSON-encoded pieces
                        echo '<script type="text/javascript">' . sprintf($inline, wp_json_encode($ajax_url), wp_json_encode($nonce), $events_json) . '</script>' . "\n";
        }
    }
    
    /**
     * Inject GTM loader in <head>
     */
    public static function inject_gtm_head() {
        $gtm_id = get_option('leadstream_gtm_id');
        
        if (!empty($gtm_id) && preg_match('/^GTM-[A-Z0-9]+$/i', $gtm_id)) {
            echo "<!-- Google Tag Manager -->\n";
            echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . esc_js($gtm_id) . "');</script>\n";
            echo "<!-- End Google Tag Manager -->\n";
        }
    }
    
    /**
     * Inject GTM <noscript> fallback in footer
     */
    public static function inject_gtm_noscript() {
        $gtm_id = get_option('leadstream_gtm_id');
        
        if (!empty($gtm_id) && preg_match('/^GTM-[A-Z0-9]+$/i', $gtm_id)) {
            echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr($gtm_id) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
        }
    }

    /**
     * Inject a 10px "LS" dot next to our pixel/injection in the footer.
     * - Minimal DOM/CSS, no layout thrash, tooltip for accessibility.
     * - Looks for #leadstream-pixel; falls back to <footer> then body.
     * - Enable/disable with filter: add_filter('leadstream_enable_badge', '__return_false');
     * - Override href/tip via filters: 'leadstream_badge_href', 'leadstream_badge_tip'.
     */
    public static function inject_ls_badge() {
    // Paid-version stubs: any of these will suppress the badge automatically
    if (defined('LEADSTREAM_PRO') && LEADSTREAM_PRO) { return; }
    if (apply_filters('leadstream_is_paid', false)) { return; }
    if ((bool) get_option('leadstream_paid_stub', false)) { return; }

    // Allow theme/site to opt-out explicitly (free builds can still override)
    $enabled = apply_filters('leadstream_enable_badge', true);
    if (!$enabled) { return; }

        $href = apply_filters('leadstream_badge_href', 'https://projectstudios.co.nz/');
        $tip  = apply_filters('leadstream_badge_tip', 'Powered by LeadStream • LS');

    // Build tiny JS via HEREDOC to avoid quoting issues
    $href_js = wp_json_encode($href);
    $tip_js  = wp_json_encode($tip);
    $js = <<<JS
(function LSBadge(){
  var HREF = {$href_js};
  var TIP  = {$tip_js};
    var css = ".ls-dot{display:inline-block;position:relative;width:10px;height:10px;border-radius:50%;background:#111;opacity:.6;vertical-align:middle;margin-left:6px;cursor:pointer}"
      + ".ls-dot:hover{opacity:.9}"
      + ".ls-dot[data-tip]:hover:after{content:attr(data-tip);position:absolute;bottom:140%;left:50%;transform:translateX(-50%);white-space:nowrap;background:#111;color:#fff;font:500 11px/1.2 system-ui,-apple-system,Segoe UI,Roboto,Arial;padding:4px 6px;border-radius:6px;pointer-events:none;opacity:.95}"
                    + ".ls-ns{position:absolute;left:-9999px}"
                    + ".ls-dot.ls-center{display:block;margin:8px auto 0 auto}";
  try { var style=document.createElement("style"); style.textContent=css; (document.head||document.documentElement).appendChild(style); } catch(e){}
    var a=document.createElement("a"); a.href=HREF; a.target="_blank"; a.rel="dofollow noopener"; a.className="ls-dot ls-center"; a.setAttribute("data-tip",TIP); a.setAttribute("aria-label",TIP);
  a.innerHTML = "<svg width=\"10\" height=\"10\" viewBox=\"0 0 10 10\" aria-hidden=\"true\" focusable=\"false\"><circle cx=\"5\" cy=\"5\" r=\"5\" fill=\"currentColor\"/></svg><span class=\"ls-ns\">LS</span>";
    var tgt = (document.querySelector("footer") || document.body);
    if (tgt) { tgt.appendChild(a); }
  var ns = document.createElement("noscript"); ns.innerHTML = "<a href=\"" + HREF.replace(/\"/g,"&quot;") + "\" rel=\"dofollow\">Powered by LeadStream</a>"; (document.querySelector("footer")||document.body).appendChild(ns);
})();
JS;

    echo "<script>{$js}</script>\n";
    }
}
