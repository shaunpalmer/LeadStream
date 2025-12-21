<?php
/**
 * LeadStream Frontend Injector
 * Handles JavaScript injection in header/footer and GTM integration
 */

namespace LS\Frontend;

defined( 'ABSPATH' ) || exit;

class Injector {

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'inject_header_js' ), 999 );
		add_action( 'wp_footer', array( __CLASS__, 'inject_footer_js' ), 999 );
		add_action( 'wp_head', array( __CLASS__, 'inject_gtm_head' ), 998 );
		add_action( 'wp_footer', array( __CLASS__, 'inject_gtm_noscript' ), 998 );
		// Tiny LS badge dot for free users (safe, unobtrusive). Allow disable via filter.
		add_action( 'wp_footer', array( __CLASS__, 'inject_ls_badge' ), 1000 );
	}

	/**
	 * Inject header JavaScript
	 */
	public static function inject_header_js() {
		$header_js     = get_option( 'custom_header_js' );
		$inject_header = get_option( 'leadstream_inject_header', 1 );

		if ( ! empty( $header_js ) && $inject_header ) {
			echo '<!-- LeadStream: Custom Header JS -->' . "\n";
			echo '<script type="text/javascript">' . "\n" . $header_js . "\n" . '</script>' . "\n";
		}
	}

	/**
	 * Inject footer JavaScript
	 */
	public static function inject_footer_js() {
		$footer_js     = get_option( 'custom_footer_js' );
		$inject_footer = get_option( 'leadstream_inject_footer', 1 );

		if ( ! empty( $footer_js ) && $inject_footer ) {
			echo '<!-- LeadStream: Custom Footer JS -->' . "\n";
			echo '<script type="text/javascript">' . "\n" . $footer_js . "\n" . '</script>' . "\n";
		}
	}

	/**
	 * Inject GTM loader in <head>
	 */
	public static function inject_gtm_head() {
		$gtm_id = get_option( 'leadstream_gtm_id' );

		if ( ! empty( $gtm_id ) && preg_match( '/^GTM-[A-Z0-9]+$/i', $gtm_id ) ) {
			echo "<!-- Google Tag Manager -->\n";
			echo "<script>(function(w,d,s,l,i){try{if(w.google_tag_manager&&w.google_tag_manager[i]){return;}}catch(e){};w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . esc_js( $gtm_id ) . "');</script>\n";
			echo "<!-- End Google Tag Manager -->\n";
		}
	}

	/**
	 * Inject GTM <noscript> fallback in footer
	 */
	public static function inject_gtm_noscript() {
		$gtm_id = get_option( 'leadstream_gtm_id' );

		if ( ! empty( $gtm_id ) && preg_match( '/^GTM-[A-Z0-9]+$/i', $gtm_id ) ) {
			echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr( $gtm_id ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
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
		if ( defined( 'LEADSTREAM_PRO' ) && LEADSTREAM_PRO ) {
			return; }
		if ( apply_filters( 'leadstream_is_paid', false ) ) {
			return; }
		if ( (bool) get_option( 'leadstream_paid_stub', false ) ) {
			return; }

		// Allow theme/site to opt-out explicitly (free builds can still override)
		$enabled = apply_filters( 'leadstream_enable_badge', true );
		if ( ! $enabled ) {
			return; }

		$href = apply_filters( 'leadstream_badge_href', 'https://projectstudios.co.nz/' );
		$tip  = apply_filters( 'leadstream_badge_tip', 'Powered by LeadStream • LS' );

				// Badge rendering is now handled by the enqueued frontend script via localized config
				return;
	}
}
