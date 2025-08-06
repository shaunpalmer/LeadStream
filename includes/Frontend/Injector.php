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
}
