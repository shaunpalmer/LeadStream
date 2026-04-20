<?php
namespace LS\Repository;

/**
 * Thin database helper for the Repository layer.
 *
 * Provides a single static method that returns the full (prefixed) table
 * name for a given base name, matching the pattern used throughout
 * ClicksRepository and LinksRepository.
 */
final class Db {
	/**
	 * Return the WordPress-prefixed table name.
	 *
	 * @param string $base_name Unprefixed table name (e.g. 'ls_links').
	 * @return string Full table name (e.g. 'wp_ls_links').
	 */
	public static function table( string $base_name ): string {
		global $wpdb;
		// Restrict to valid table name characters (alphanumeric and underscores only).
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $base_name ) ) {
			_doing_it_wrong( __METHOD__, 'Invalid table base name.', '1.0' );
			return '';
		}
		return $wpdb->prefix . $base_name;
	}
}
