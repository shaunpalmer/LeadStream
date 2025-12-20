<?php
namespace LS\Repository;

interface ClicksRepositoryInterface {
	public function count_by_type( string $link_type ): int;

	public function count_by_type_on_date( string $link_type, string $ymd ): int;

	public function count_by_type_since( string $link_type, string $mysql_datetime ): int;
	public function count_by_type_in_range( string $link_type, string $from_mysql_datetime, string $to_mysql_datetime ): int;

	public function count_filtered( string $where_sql, array $params ): int;

	public function fetch_backup_chunk( string $where_sql, array $params, int $limit, int $offset ): array;

	public function fetch_phone_calls_csv( string $where_sql, array $params, int $limit ): array;

	public function fetch_phone_calls_page( string $where_sql, array $params, int $limit, int $offset ): array;

	public function distinct_link_keys_by_type( string $link_type ): array;

	/**
	 * Fetch the most recent clicks of a given type.
	 *
	 * @return array<int,object> Rows as objects (wpdb default) for easy templating.
	 */
	public function fetch_recent_by_type( string $link_type, int $limit ): array;

	/**
	 * Aggregates phone clicks per phone number.
	 *
	 * @return array<int,object> Rows as objects (wpdb default) with fields:
	 *                    phone_number, total_calls, today_calls, last_click.
	 */
	public function fetch_phone_number_analytics( string $ymd_today ): array;

	public function delete_by_type( string $link_type ): int;

	public function delete_by_type_in_range( string $link_type, string $from_mysql_datetime, string $to_mysql_datetime ): int;
}
