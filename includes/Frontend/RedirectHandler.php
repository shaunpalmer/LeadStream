<?php
namespace LS\Frontend;

/**
 * Handles pretty-link redirects and click logging.
 * Follows WordPress best practices with proper OOP structure.
 */
class RedirectHandler {

	/**
	 * Hook our rewrite rule, query var, and redirect logic.
	 */
	public static function init() {
		// 1. Add custom query var
		add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );

		// 2. Register rewrite rule: /l/{slug}
		add_action( 'init', array( __CLASS__, 'add_rewrite_rule' ) );

		// 3. Perform redirect & logging as early as possible
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 0 );
	}

	/**
	 * Allow 'ls_link' as a valid query var.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public static function add_query_var( $vars ) {
		$vars[] = 'ls_link';
		$vars[] = 'ls_s';
		return $vars;
	}

	/**
	 * Register the rewrite rule for slug-based links.
	 * Matches URLs like /l/my-slug/ and maps to index.php?ls_link=my-slug
	 */
	public static function add_rewrite_rule() {
		add_rewrite_rule(
			'^l/([^/]+)/?$',
			'index.php?ls_link=$matches[1]',
			'top'
		);
		// Short code route: /s/{code}
		add_rewrite_rule(
			'^s/([^/]+)/?$',
			'index.php?ls_s=$matches[1]',
			'top'
		);
	}

	/**
	 * If 'ls_link' is present, look up the target, log the click, and redirect.
	 */
	public static function maybe_redirect() {
		$slug  = get_query_var( 'ls_link' );
		$short = get_query_var( 'ls_s' );

		$slug  = is_string( $slug ) ? sanitize_title( $slug ) : '';
		$short = is_string( $short ) ? preg_replace( '/[^0-9A-Za-z]/', '', $short ) : '';

		// Cheap hardening: refuse unreasonably long path inputs (UTMs live in the query string, not the slug).
		if ( strlen( $slug ) > 512 || strlen( $short ) > 32 ) {
			return;
		}
		if ( empty( $slug ) && empty( $short ) ) {
			return; }

		global $wpdb;

		// Fetch link record by slug or by short code -> id
		$table = $wpdb->prefix . 'ls_links';
		if ( ! empty( $short ) ) {
			$id = \LS\Utils::base62_decode( $short );
			if ( $id > 0 ) {
				$link = $wpdb->get_row(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from $wpdb->prefix.
						"SELECT id, target_url, COALESCE(redirect_type, '301') as redirect_type FROM {$table} WHERE id = %d LIMIT 1",
						$id
					)
				);
			} else {
				$link = null;
			}
		} else {
			$link = $wpdb->get_row(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from $wpdb->prefix.
					"SELECT id, target_url, COALESCE(redirect_type, '301') as redirect_type FROM {$table} WHERE slug = %s LIMIT 1",
					$slug
				)
			);
		}

		if ( ! $link ) {
			// No matching slug—let WP handle 404 normally
			return;
		}

		// Log the click
		$clicks_table = $wpdb->prefix . 'ls_clicks';
		$wpdb->insert(
			$clicks_table,
			array(
				'link_id'    => $link->id,
				'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
				'user_agent' => substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255 ),
			),
			array( '%d', '%s', '%s' )
		);

		// Perform redirect honoring configured type
		$code = in_array( $link->redirect_type, array( '301', '302', '307', '308' ), true ) ? intval( $link->redirect_type ) : 301;
		wp_redirect( $link->target_url, $code );
		exit;
	}
}
