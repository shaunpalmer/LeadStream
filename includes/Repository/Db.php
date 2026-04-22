<?php
namespace LS\Repository;

/**
 * Minimal database helper for table name resolution.
 */
final class Db {
	/**
	 * Return the WordPress-prefixed table name.
	 *
	 * @param string $table Unprefixed table name (e.g. 'ls_clicks').
	 * @return string Fully qualified table name (e.g. 'wp_ls_clicks').
	 */
	public static function table( string $table ): string {
		global $wpdb;
		return $wpdb->prefix . $table;
	}
}
