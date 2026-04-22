<?php
namespace LS\Repository;

final class ClicksRepository implements ClicksRepositoryInterface {
	private string $clicks_table;

	public function __construct() {
		$this->clicks_table = Db::table( 'ls_clicks' );
	}

	public function exists(): bool {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->clicks_table ) ) === $this->clicks_table;
	}

	public function count_by_type( string $link_type ): int {
		global $wpdb;
		$sql = 'SELECT COUNT(*) FROM ' . $this->clicks_table . ' WHERE link_type = %s';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $link_type ) );
	}

	public function count_by_type_on_date( string $link_type, string $ymd ): int {
		global $wpdb;
		$sql = 'SELECT COUNT(*) FROM ' . $this->clicks_table . ' WHERE link_type = %s AND DATE(clicked_at) = %s';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $link_type, $ymd ) );
	}

	public function count_by_type_since( string $link_type, string $mysql_datetime ): int {
		global $wpdb;
		$sql = 'SELECT COUNT(*) FROM ' . $this->clicks_table . ' WHERE link_type = %s AND clicked_at >= %s';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $link_type, $mysql_datetime ) );
	}

	public function count_by_type_in_range( string $link_type, string $from_mysql_datetime, string $to_mysql_datetime ): int {
		global $wpdb;
		$sql = 'SELECT COUNT(*) FROM ' . $this->clicks_table . ' WHERE link_type = %s AND clicked_at BETWEEN %s AND %s';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $link_type, $from_mysql_datetime, $to_mysql_datetime ) );
	}

	public function count_filtered( string $where_sql, array $params ): int {
		global $wpdb;
		$sql        = 'SELECT COUNT(*) FROM ' . $this->clicks_table . ' WHERE ' . $where_sql . ' AND %d = %d';
		$all_params = array_merge( array_values( $params ), array( 1, 1 ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$all_params ) );
	}

	public function fetch_backup_chunk( string $where_sql, array $params, int $limit, int $offset ): array {
		global $wpdb;
		$sql        = 'SELECT * FROM ' . $this->clicks_table . ' WHERE ' . $where_sql . ' AND %d = %d ORDER BY id ASC LIMIT %d OFFSET %d';
		$all_params = array_merge( array_values( $params ), array( 1, 1, $limit, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$all_params ), ARRAY_A );
	}

	public function fetch_phone_calls_csv( string $where_sql, array $params, int $limit ): array {
		global $wpdb;
		$sql        = 'SELECT click_date, click_time, link_key as phone, page_title, page_url, element_type, element_id, element_class, ip_address, referrer, clicked_at FROM ' . $this->clicks_table . ' WHERE ' . $where_sql . ' AND %d = %d ORDER BY clicked_at DESC LIMIT %d';
		$all_params = array_merge( array_values( $params ), array( 1, 1, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$all_params ), ARRAY_A );
	}

	public function fetch_phone_calls_page( string $where_sql, array $params, int $limit, int $offset ): array {
		global $wpdb;
		$sql        = 'SELECT link_key as phone_number, clicked_at, click_date, click_time, page_title, page_url, element_type, element_id, element_class, ip_address, referrer FROM ' . $this->clicks_table . ' WHERE ' . $where_sql . ' AND %d = %d ORDER BY clicked_at DESC LIMIT %d OFFSET %d';
		$all_params = array_merge( array_values( $params ), array( 1, 1, $limit, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$all_params ) );
	}

	public function distinct_link_keys_by_type( string $link_type ): array {
		global $wpdb;
		$sql = 'SELECT DISTINCT link_key FROM ' . $this->clicks_table . ' WHERE link_type = %s ORDER BY link_key ASC';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return array_values( array_filter( (array) $wpdb->get_col( $wpdb->prepare( $sql, $link_type ) ) ) );
	}

	public function fetch_recent_by_type( string $link_type, int $limit ): array {
		global $wpdb;
		$sql = 'SELECT link_key as phone_number, clicked_at, click_date, click_time, ip_address, referrer, page_url, page_title, element_type, element_class, element_id, user_agent'
			. ' FROM ' . $this->clicks_table
			. ' WHERE link_type = %s'
			. ' ORDER BY clicked_at DESC'
			. ' LIMIT %d';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $link_type, $limit ) );
	}

	public function fetch_phone_number_analytics( string $ymd_today ): array {
		global $wpdb;
		$sql = 'SELECT link_key as phone_number,'
			. ' COUNT(*) as total_calls,'
			. ' SUM(CASE WHEN DATE(clicked_at) = %s THEN 1 ELSE 0 END) as today_calls,'
			. ' MAX(clicked_at) as last_click'
			. ' FROM ' . $this->clicks_table
			. ' WHERE link_type = %s'
			. ' GROUP BY link_key'
			. ' ORDER BY total_calls DESC';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $ymd_today, 'phone' ) );
	}

	public function delete_by_type( string $link_type ): int {
		global $wpdb;
		$sql = 'DELETE FROM ' . $this->clicks_table . ' WHERE link_type = %s';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (int) $wpdb->query( $wpdb->prepare( $sql, $link_type ) );
	}

	public function delete_by_type_in_range( string $link_type, string $from_mysql_datetime, string $to_mysql_datetime ): int {
		global $wpdb;
		$sql = 'DELETE FROM ' . $this->clicks_table . ' WHERE link_type = %s AND clicked_at BETWEEN %s AND %s';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (int) $wpdb->query( $wpdb->prepare( $sql, $link_type, $from_mysql_datetime, $to_mysql_datetime ) );
	}

	/**
	 * Inserts a small set of demo phone clicks (opt-in).
	 *
	 * @return int Number of rows inserted.
	 */
	public function insert_demo_phone_clicks(): int {
		global $wpdb;
		if ( ! $this->exists() ) {
			return 0;
		}

		$now = current_time( 'mysql' );
		// Use clearly fake numbers (555-01xx is reserved for fiction in NANP).
		$rows = array(
			array(
				'link_type'    => 'phone',
				'link_key'     => 'LS-DEMO-555-0100',
				'target_url'   => 'tel:+15550100',
				'clicked_at'   => $now,
				'click_date'   => gmdate( 'Y-m-d' ),
				'click_time'   => gmdate( 'H:i:s' ),
				'page_url'     => home_url( '/leadstream-demo' ),
				'page_title'   => 'LeadStream Demo',
				'element_type' => 'demo',
				'meta_data'    => 'ls_sample=1',
			),
			array(
				'link_type'    => 'phone',
				'link_key'     => 'LS-DEMO-555-0101',
				'target_url'   => 'tel:+15550101',
				'clicked_at'   => $now,
				'click_date'   => gmdate( 'Y-m-d' ),
				'click_time'   => gmdate( 'H:i:s' ),
				'page_url'     => home_url( '/leadstream-demo' ),
				'page_title'   => 'LeadStream Demo',
				'element_type' => 'demo',
				'meta_data'    => 'ls_sample=1',
			),
		);

		$inserted = 0;
		foreach ( $rows as $row ) {
			$ok = $wpdb->insert(
				$this->clicks_table,
				$row,
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( false !== $ok ) {
				++$inserted;
			}
		}

		return $inserted;
	}

	/**
	 * Deletes demo phone clicks inserted via insert_demo_phone_clicks().
	 *
	 * @return int Number of rows deleted.
	 */
	public function delete_demo_phone_clicks(): int {
		global $wpdb;
		if ( ! $this->exists() ) {
			return 0;
		}

		$sql = 'DELETE FROM ' . $this->clicks_table . ' WHERE link_type = %s AND link_key LIKE %s';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (int) $wpdb->query( $wpdb->prepare( $sql, 'phone', 'LS-DEMO-%' ) );
	}
}
