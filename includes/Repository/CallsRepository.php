<?php
namespace LS\Repository;

/**
 * Repository for the ls_calls table (provider webhook call outcomes).
 */
final class CallsRepository {
	private string $calls_table;

	public function __construct() {
		$this->calls_table = Db::table( 'ls_calls' );
	}

	/**
	 * Return the fully-qualified table name (with WordPress prefix).
	 */
	public function table_name(): string {
		return $this->calls_table;
	}

	/**
	 * Check whether the ls_calls table exists in the database.
	 */
	public function exists(): bool {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->calls_table ) ) === $this->calls_table;
	}

	/**
	 * Count rows matching an arbitrary WHERE clause.
	 *
	 * @param string  $where_sql Prepared WHERE fragment (no leading WHERE keyword).
	 * @param array   $params    Positional values for the prepared statement.
	 */
	public function count_filtered( string $where_sql, array $params ): int {
		global $wpdb;
		$sql        = 'SELECT COUNT(*) FROM ' . $this->calls_table . ' WHERE ' . $where_sql . ' AND %d = %d';
		$all_params = array_merge( array_values( $params ), array( 1, 1 ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$all_params ) );
	}

	/**
	 * Fetch a paginated result set matching an arbitrary WHERE clause.
	 *
	 * @return array<int,object>
	 */
	public function fetch_filtered( string $where_sql, array $params, int $limit, int $offset ): array {
		global $wpdb;
		$sql        = 'SELECT * FROM ' . $this->calls_table . ' WHERE ' . $where_sql . ' AND %d = %d ORDER BY start_time DESC LIMIT %d OFFSET %d';
		$all_params = array_merge( array_values( $params ), array( 1, 1, $limit, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$all_params ) );
	}

	/**
	 * Fetch rows for CSV export (returns associative arrays).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function fetch_filtered_csv( string $where_sql, array $params, int $limit ): array {
		global $wpdb;
		$sql        = 'SELECT start_time, end_time, duration, provider, status, from_number, to_number, recording_url FROM ' . $this->calls_table . ' WHERE ' . $where_sql . ' AND %d = %d ORDER BY start_time DESC LIMIT %d';
		$all_params = array_merge( array_values( $params ), array( 1, 1, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$all_params ), ARRAY_A );
	}

	/**
	 * Return the distinct non-empty provider values present in the table.
	 *
	 * @return array<int,string>
	 */
	public function distinct_providers(): array {
		global $wpdb;
		$sql = "SELECT DISTINCT provider FROM {$this->calls_table} WHERE provider != '' ORDER BY provider ASC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; no user values.
		return array_values( array_filter( (array) $wpdb->get_col( $sql ) ) );
	}

	/**
	 * Return the distinct non-empty status values present in the table.
	 *
	 * @return array<int,string>
	 */
	public function distinct_statuses(): array {
		global $wpdb;
		$sql = "SELECT DISTINCT status FROM {$this->calls_table} WHERE status != '' ORDER BY status ASC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; no user values.
		return array_values( array_filter( (array) $wpdb->get_col( $sql ) ) );
	}

	/**
	 * Fetch the most recent rows whose status is in the given list.
	 *
	 * @param array<int,string> $statuses Status values to match.
	 * @param int               $limit    Maximum rows to return.
	 * @return array<int,object>
	 */
	public function fetch_recent_by_statuses( array $statuses, int $limit ): array {
		global $wpdb;
		if ( empty( $statuses ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$sql          = 'SELECT * FROM ' . $this->calls_table . ' WHERE status IN (' . $placeholders . ') ORDER BY start_time DESC LIMIT %d';
		$all_params   = array_merge( array_values( $statuses ), array( $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$all_params ) );
	}

	/**
	 * Insert two sample call rows for the opt-in demo data feature.
	 *
	 * @return int Number of rows inserted.
	 */
	public function insert_demo_calls(): int {
		global $wpdb;
		if ( ! $this->exists() ) {
			return 0;
		}

		$now  = current_time( 'mysql' );
		$rows = array(
			array(
				'provider'         => 'ls-demo',
				'provider_call_id' => 'LS-DEMO-CALL-001',
				'from_number'      => '+15550100',
				'to_number'        => '+15550199',
				'status'           => 'completed',
				'start_time'       => $now,
				'end_time'         => $now,
				'duration'         => 60,
				'meta_data'        => 'ls_sample=1',
			),
			array(
				'provider'         => 'ls-demo',
				'provider_call_id' => 'LS-DEMO-CALL-002',
				'from_number'      => '+15550101',
				'to_number'        => '+15550199',
				'status'           => 'no-answer',
				'start_time'       => $now,
				'end_time'         => $now,
				'duration'         => 0,
				'meta_data'        => 'ls_sample=1',
			),
		);

		$inserted = 0;
		foreach ( $rows as $row ) {
			$ok = $wpdb->insert(
				$this->calls_table,
				$row,
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);
			if ( false !== $ok ) {
				++$inserted;
			}
		}

		return $inserted;
	}

	/**
	 * Delete all demo call rows inserted by insert_demo_calls().
	 *
	 * @return int Number of rows deleted.
	 */
	public function delete_demo_calls(): int {
		global $wpdb;
		if ( ! $this->exists() ) {
			return 0;
		}

		$sql = 'DELETE FROM ' . $this->calls_table . ' WHERE provider = %s AND provider_call_id LIKE %s';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (int) $wpdb->query( $wpdb->prepare( $sql, 'ls-demo', 'LS-DEMO-%' ) );
	}
}
