<?php
namespace LS\Repository;


class LinksRepository {
	public static function fetch_by_id( int $id ): ?object {
		global $wpdb;
		$links_table = Db::table( 'ls_links' );
		$sql         = 'SELECT * FROM ' . $links_table . ' WHERE id = %d LIMIT 1';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $id ) );
		return $row ? $row : null;
	}

	/**
	 * Fetch raw ls_links rows for backups.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function fetch_backup_chunk( string $where_sql, array $params, int $limit, int $offset ): array {
		global $wpdb;
		$links_table = Db::table( 'ls_links' );
		$sql         = 'SELECT * FROM ' . $links_table . ' WHERE ' . $where_sql . ' AND %d = %d ORDER BY id ASC LIMIT %d OFFSET %d';
		$all_params  = array_merge( array_values( $params ), array( 1, 1, $limit, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$all_params ), ARRAY_A );
	}

	/**
	 * Dangerous: removes all Pretty Links definitions.
	 */
	public static function truncate_all(): int {
		global $wpdb;
		$links_table = Db::table( 'ls_links' );
		$sql         = 'TRUNCATE TABLE ' . $links_table;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name.
		return (int) $wpdb->query( $sql );
	}

	public static function exists(): bool {
		global $wpdb;
		$links_table = Db::table( 'ls_links' );
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $links_table ) ) === $links_table;
	}

	/**
	 * @return array<int,object>
	 */
	public static function fetch_recent_slugs_targets( int $limit ): array {
		global $wpdb;
		$links_table = Db::table( 'ls_links' );
		$sql         = 'SELECT slug, target_url FROM ' . $links_table . ' ORDER BY created_at DESC LIMIT %d';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $limit ) );
	}

	public static function fetch_most_popular_link(): ?object {
		global $wpdb;
		$links_table  = Db::table( 'ls_links' );
		$clicks_table = Db::table( 'ls_clicks' );
		$sql          = 'SELECT l.slug, COUNT(c.id) as click_count FROM ' . $links_table . " l LEFT JOIN {$clicks_table} c ON l.id = c.link_id AND c.link_type = %s GROUP BY l.id ORDER BY click_count DESC, l.created_at DESC LIMIT %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, 'link', 1 ) );
		return ! empty( $rows[0] ) ? $rows[0] : null;
	}

	/**
	 * @return array<int,object>
	 */
	public static function fetch_top_links_since( string $since_mysql_datetime, int $limit ): array {
		global $wpdb;
		$links_table  = Db::table( 'ls_links' );
		$clicks_table = Db::table( 'ls_clicks' );
		$sql          = 'SELECT l.slug, l.target_url, COUNT(c.id) as click_count FROM ' . $links_table . " l LEFT JOIN {$clicks_table} c ON l.id = c.link_id AND c.link_type = %s AND c.clicked_at >= %s GROUP BY l.id ORDER BY click_count DESC, l.created_at DESC LIMIT %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, 'link', $since_mysql_datetime, $limit ) );
	}

	public static function build_filters( array $args ): array {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		$q      = isset( $args['q'] ) ? sanitize_text_field( $args['q'] ) : '';
		$rt     = isset( $args['rt'] ) ? sanitize_text_field( $args['rt'] ) : '';
		$from   = isset( $args['from'] ) ? sanitize_text_field( $args['from'] ) : '';
		$to     = isset( $args['to'] ) ? sanitize_text_field( $args['to'] ) : '';
		if ( $q ) {
			$like     = '%' . $wpdb->esc_like( $q ) . '%';
			$where[]  = '(l.slug LIKE %s OR l.target_url LIKE %s)';
			$params[] = $like;
			$params[] = $like; }
		if ( in_array( $rt, array( '301', '302', '307', '308' ), true ) ) {
			$where[]  = 'l.redirect_type = %s';
			$params[] = $rt; }
		if ( $from ) {
			$where[]  = 'l.created_at >= %s';
			$params[] = $from . ' 00:00:00'; }
		if ( $to ) {
			$where[]  = 'l.created_at <= %s';
			$params[] = $to . ' 23:59:59'; }
		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * @return array<int,object>
	 */
	public static function fetch_with_counts( string $where_sql, array $params, int $limit, int $offset ): array {
		global $wpdb;
		$links_table  = Db::table( 'ls_links' );
		$clicks_table = Db::table( 'ls_clicks' );
		$sql          = 'SELECT l.*, COUNT(c.id) as clicks, MAX(c.clicked_at) as last_click FROM ' . $links_table . " l LEFT JOIN {$clicks_table} c ON l.id = c.link_id AND c.link_type = 'link' WHERE {$where_sql} AND %d = %d GROUP BY l.id ORDER BY l.created_at DESC LIMIT %d OFFSET %d";
		$all_params   = array_merge( array_values( $params ), array( 1, 1, $limit, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$all_params ) );
	}

	public static function count( string $where_sql, array $params ): int {
		global $wpdb;
		$links_table = Db::table( 'ls_links' );
		$sql         = 'SELECT COUNT(*) FROM ' . $links_table . " l WHERE {$where_sql} AND %d = %d";
		$all_params  = array_merge( array_values( $params ), array( 1, 1 ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$all_params ) );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function fetch_directory_csv( string $where_sql, array $params, int $limit ): array {
		global $wpdb;
		$links_table  = Db::table( 'ls_links' );
		$clicks_table = Db::table( 'ls_clicks' );
		$sql          = 'SELECT l.slug, l.target_url, l.redirect_type, l.created_at, COUNT(c.id) as total_clicks, MAX(c.clicked_at) as last_click FROM ' . $links_table . " l LEFT JOIN {$clicks_table} c ON l.id = c.link_id AND c.link_type = 'link' WHERE {$where_sql} AND %d = %d GROUP BY l.id ORDER BY l.created_at DESC LIMIT %d";
		$all_params   = array_merge( array_values( $params ), array( 1, 1, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$all_params ), ARRAY_A );
	}

	public static function count_link_clicks( string $where_sql, array $params ): int {
		global $wpdb;
		$links_table  = Db::table( 'ls_links' );
		$clicks_table = Db::table( 'ls_clicks' );
		$sql          = 'SELECT COUNT(*) FROM ' . $clicks_table . " c LEFT JOIN {$links_table} l ON c.link_id = l.id WHERE {$where_sql} AND %d = %d";
		$all_params   = array_merge( array_values( $params ), array( 1, 1 ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$all_params ) );
	}

	/**
	 * @return array<int,object>
	 */
	public static function fetch_link_clicks_page( string $where_sql, array $params, int $limit, int $offset ): array {
		global $wpdb;
		$links_table  = Db::table( 'ls_links' );
		$clicks_table = Db::table( 'ls_clicks' );
		$sql          = 'SELECT c.click_date, c.click_time, c.clicked_at, l.slug, l.target_url, c.page_title, c.page_url, c.ip_address, c.referrer FROM ' . $clicks_table . " c LEFT JOIN {$links_table} l ON c.link_id = l.id WHERE {$where_sql} AND %d = %d ORDER BY c.clicked_at DESC LIMIT %d OFFSET %d";
		$all_params   = array_merge( array_values( $params ), array( 1, 1, $limit, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$all_params ) );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function fetch_link_clicks_csv( string $where_sql, array $params, int $limit ): array {
		global $wpdb;
		$links_table  = Db::table( 'ls_links' );
		$clicks_table = Db::table( 'ls_clicks' );
		$sql          = 'SELECT c.click_date, c.click_time, l.slug, l.target_url, c.page_title, c.page_url, c.ip_address, c.referrer, c.clicked_at FROM ' . $clicks_table . " c LEFT JOIN {$links_table} l ON c.link_id = l.id WHERE {$where_sql} AND %d = %d ORDER BY c.clicked_at DESC LIMIT %d";
		$all_params   = array_merge( array_values( $params ), array( 1, 1, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$all_params ), ARRAY_A );
	}

	/**
	 * @return array<int,string>
	 */
	public static function distinct_slugs(): array {
		global $wpdb;
		$links_table = Db::table( 'ls_links' );
		$sql         = 'SELECT slug FROM ' . $links_table . ' ORDER BY created_at DESC';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name.
		return array_values( array_filter( (array) $wpdb->get_col( $sql ) ) );
	}
}
