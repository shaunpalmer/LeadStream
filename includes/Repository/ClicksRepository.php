<?php
namespace LS\Repository;

final class ClicksRepository implements ClicksRepositoryInterface {
	private string $clicks_table;

	public function __construct() {
		$this->clicks_table = Db::table( 'ls_clicks' );
	}

	/**
	 * Extract common tracking parameters from a URL.
	 *
	 * @return array{utm_source:string,utm_medium:string,utm_campaign:string,utm_term:string,utm_content:string,gclid:string,fbclid:string,msclkid:string,ttclid:string}
	 */
	private function extract_tracking_from_url( string $url ): array {
		$out = array(
			'utm_source'   => '',
			'utm_medium'   => '',
			'utm_campaign' => '',
			'utm_term'     => '',
			'utm_content'  => '',
			'gclid'        => '',
			'fbclid'       => '',
			'msclkid'      => '',
			'ttclid'       => '',
		);

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['query'] ) ) {
			return $out;
		}

		$query = array();
		wp_parse_str( (string) $parts['query'], $query );
		if ( ! is_array( $query ) ) {
			return $out;
		}

		foreach ( array_keys( $out ) as $key ) {
			if ( isset( $query[ $key ] ) && is_scalar( $query[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( (string) $query[ $key ] );
			}
		}

		return $out;
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
	 */
	public function insert_demo_phone_clicks(): int {
		global $wpdb;
		if ( ! $this->exists() ) {
			return 0;
		}

		$now  = current_time( 'mysql' );
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
			$ok = $wpdb->insert( $this->clicks_table, $row, null );
			if ( false !== $ok ) {
				++$inserted;
			}
		}

		return $inserted;
	}

	public function delete_demo_phone_clicks(): int {
		global $wpdb;
		if ( ! $this->exists() ) {
			return 0;
		}

		$sql = 'DELETE FROM ' . $this->clicks_table . ' WHERE link_type = %s AND link_key LIKE %s';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		return (int) $wpdb->query( $wpdb->prepare( $sql, 'phone', 'LS-DEMO-%' ) );
	}

	public function insert_link_click( int $link_id, string $link_key, string $target_url, string $ip_address, string $user_agent ): bool {
		global $wpdb;
		$clicked_at = current_time( 'mysql' );
		$click_date = current_time( 'Y-m-d' );
		$click_time = current_time( 'H:i:s' );
		$user_agent = substr( $user_agent, 0, 512 );
		$link_key   = substr( $link_key, 0, 255 );
		$ip_address = substr( $ip_address, 0, 45 );

		$referrer = '';
		$referer  = wp_get_referer();
		if ( is_string( $referer ) && '' !== $referer ) {
			$referrer = $referer;
		} elseif ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referrer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		$page_url = $referrer;
		$tracking = '' !== $referrer ? $this->extract_tracking_from_url( $referrer ) : $this->extract_tracking_from_url( $target_url );

		$meta      = array(
			'origin'    => 'redirect',
			'referrer'  => $referrer,
			'page_url'  => $page_url,
			'target'    => $target_url,
			'utm'       => array(
				'utm_source'   => $tracking['utm_source'],
				'utm_medium'   => $tracking['utm_medium'],
				'utm_campaign' => $tracking['utm_campaign'],
				'utm_term'     => $tracking['utm_term'],
				'utm_content'  => $tracking['utm_content'],
			),
			'click_ids' => array(
				'gclid'   => $tracking['gclid'],
				'fbclid'  => $tracking['fbclid'],
				'msclkid' => $tracking['msclkid'],
				'ttclid'  => $tracking['ttclid'],
			),
		);
		$meta_json = wp_json_encode( $meta );

		$result = $wpdb->insert(
			$this->clicks_table,
			array(
				'link_id'        => $link_id,
				'link_type'      => 'link',
				'link_key'       => $link_key,
				'target_url'     => $target_url,
				'clicked_at'     => $clicked_at,
				'click_datetime' => $clicked_at,
				'click_date'     => $click_date,
				'click_time'     => $click_time,
				'ip_address'     => $ip_address,
				'user_agent'     => $user_agent,
				'referrer'       => $referrer,
				'page_url'       => $page_url,
				'origin'         => 'redirect',
				'utm_source'     => $tracking['utm_source'],
				'utm_medium'     => $tracking['utm_medium'],
				'utm_campaign'   => $tracking['utm_campaign'],
				'utm_term'       => $tracking['utm_term'],
				'utm_content'    => $tracking['utm_content'],
				'gclid'          => $tracking['gclid'],
				'fbclid'         => $tracking['fbclid'],
				'msclkid'        => $tracking['msclkid'],
				'ttclid'         => $tracking['ttclid'],
				'meta_data'      => is_string( $meta_json ) ? $meta_json : '',
			),
			null
		);

		return false !== $result;
	}

	public function find_recent_phone_click_id( string $phone_key, string $since_mysql ): int {
		global $wpdb;
		$sql = 'SELECT id FROM ' . $this->clicks_table . ' WHERE link_type = %s AND link_key = %s AND clicked_at >= %s ORDER BY clicked_at DESC LIMIT 1';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name; values are prepared.
		$id = (int) $wpdb->get_var( $wpdb->prepare( $sql, 'phone', $phone_key, $since_mysql ) );
		return $id > 0 ? $id : 0;
	}

	public function insert_phone_click( array $payload ): int {
		global $wpdb;
		$phone = isset( $payload['phone'] ) ? preg_replace( '/\D+/', '', (string) $payload['phone'] ) : '';
		if ( '' === $phone ) {
			return 0;
		}

		$clicked_at = current_time( 'mysql' );
		$click_date = current_time( 'Y-m-d' );
		$click_time = current_time( 'H:i:s' );

		$original_phone   = isset( $payload['original_phone'] ) ? sanitize_text_field( (string) $payload['original_phone'] ) : '';
		$origin           = isset( $payload['origin'] ) ? sanitize_text_field( (string) $payload['origin'] ) : '';
		$source           = isset( $payload['source'] ) ? sanitize_text_field( (string) $payload['source'] ) : '';
		$utm_source       = isset( $payload['utm_source'] ) ? sanitize_text_field( (string) $payload['utm_source'] ) : '';
		$utm_medium       = isset( $payload['utm_medium'] ) ? sanitize_text_field( (string) $payload['utm_medium'] ) : '';
		$utm_campaign     = isset( $payload['utm_campaign'] ) ? sanitize_text_field( (string) $payload['utm_campaign'] ) : '';
		$utm_term         = isset( $payload['utm_term'] ) ? sanitize_text_field( (string) $payload['utm_term'] ) : '';
		$utm_content      = isset( $payload['utm_content'] ) ? sanitize_text_field( (string) $payload['utm_content'] ) : '';
		$gclid            = isset( $payload['gclid'] ) ? sanitize_text_field( (string) $payload['gclid'] ) : '';
		$fbclid           = isset( $payload['fbclid'] ) ? sanitize_text_field( (string) $payload['fbclid'] ) : '';
		$msclkid          = isset( $payload['msclkid'] ) ? sanitize_text_field( (string) $payload['msclkid'] ) : '';
		$ttclid           = isset( $payload['ttclid'] ) ? sanitize_text_field( (string) $payload['ttclid'] ) : '';
		$event_id         = isset( $payload['event_id'] ) ? sanitize_text_field( (string) $payload['event_id'] ) : '';
		$event_id         = substr( $event_id, 0, 128 );
		$phone_e164       = isset( $payload['phone_e164'] ) ? sanitize_text_field( (string) $payload['phone_e164'] ) : '';
		$phone_e164       = substr( $phone_e164, 0, 32 );
		$ga_client_id     = isset( $payload['ga_client_id'] ) ? sanitize_text_field( (string) $payload['ga_client_id'] ) : '';
		$ga_session_id    = isset( $payload['ga_session_id'] ) ? (int) $payload['ga_session_id'] : 0;
		$ga_session_number = isset( $payload['ga_session_number'] ) ? (int) $payload['ga_session_number'] : 0;
		$client_language  = isset( $payload['client_language'] ) ? sanitize_text_field( (string) $payload['client_language'] ) : '';
		$device_type      = isset( $payload['device_type'] ) ? sanitize_text_field( (string) $payload['device_type'] ) : '';
		$viewport_w       = isset( $payload['viewport_w'] ) ? (int) $payload['viewport_w'] : 0;
		$viewport_h       = isset( $payload['viewport_h'] ) ? (int) $payload['viewport_h'] : 0;
		$time_to_click_ms = isset( $payload['time_to_click_ms'] ) ? (int) $payload['time_to_click_ms'] : 0;
		$landing_page     = isset( $payload['landing_page'] ) ? esc_url_raw( (string) $payload['landing_page'] ) : '';

		$is_auth = ! empty( $payload['is_authenticated'] ) ? 1 : 0;
		$user_id = isset( $payload['user_id'] ) ? (int) $payload['user_id'] : 0;

		$meta      = array(
			'event_id'         => $event_id,
			'phone_e164'       => $phone_e164,
			'original_phone'   => $original_phone,
			'origin'           => $origin,
			'source'           => $source,
			'is_authenticated' => $is_auth,
			'ga'               => array(
				'ga_client_id'      => $ga_client_id,
				'ga_session_id'     => $ga_session_id,
				'ga_session_number' => $ga_session_number,
			),
			'utm'              => array(
				'utm_source'   => $utm_source,
				'utm_medium'   => $utm_medium,
				'utm_campaign' => $utm_campaign,
				'utm_term'     => $utm_term,
				'utm_content'  => $utm_content,
			),
			'click_ids'        => array(
				'gclid'   => $gclid,
				'fbclid'  => $fbclid,
				'msclkid' => $msclkid,
				'ttclid'  => $ttclid,
			),
			'client'           => array(
				'language'         => $client_language,
				'device_type'      => $device_type,
				'viewport_w'       => $viewport_w,
				'viewport_h'       => $viewport_h,
				'time_to_click_ms' => $time_to_click_ms,
				'landing_page'     => $landing_page,
			),
		);
		$meta_json = wp_json_encode( $meta );

		$result = $wpdb->insert(
			$this->clicks_table,
			array(
				'link_type'      => 'phone',
				'link_key'       => substr( $phone, 0, 255 ),
				'target_url'     => 'tel:' . $phone,
				'clicked_at'     => $clicked_at,
				'click_datetime' => $clicked_at,
				'click_date'     => $click_date,
				'click_time'     => $click_time,
				'ip_address'     => isset( $payload['ip_address'] ) ? substr( (string) $payload['ip_address'], 0, 45 ) : '',
				'user_agent'     => isset( $payload['user_agent'] ) ? substr( (string) $payload['user_agent'], 0, 512 ) : '',
				'user_id'        => $user_id > 0 ? $user_id : null,
				'referrer'       => isset( $payload['referrer'] ) ? esc_url_raw( (string) $payload['referrer'] ) : '',
				'page_url'       => isset( $payload['page_url'] ) ? esc_url_raw( (string) $payload['page_url'] ) : '',
				'page_title'     => isset( $payload['page_title'] ) ? sanitize_text_field( (string) $payload['page_title'] ) : '',
				'element_type'   => isset( $payload['element_type'] ) ? sanitize_text_field( (string) $payload['element_type'] ) : '',
				'element_class'  => isset( $payload['element_class'] ) ? sanitize_text_field( (string) $payload['element_class'] ) : '',
				'element_id'     => isset( $payload['element_id'] ) ? sanitize_text_field( (string) $payload['element_id'] ) : '',
				'origin'         => $origin,
				'source'         => $source,
				'ga_client_id'   => '' !== $ga_client_id ? substr( $ga_client_id, 0, 64 ) : null,
				'ga_session_id'  => $ga_session_id > 0 ? $ga_session_id : null,
				'ga_session_number' => $ga_session_number > 0 ? $ga_session_number : null,
				'utm_source'     => $utm_source,
				'utm_medium'     => $utm_medium,
				'utm_campaign'   => $utm_campaign,
				'utm_term'       => $utm_term,
				'utm_content'    => $utm_content,
				'gclid'          => $gclid,
				'fbclid'         => $fbclid,
				'msclkid'        => $msclkid,
				'ttclid'         => $ttclid,
				'meta_data'      => is_string( $meta_json ) ? $meta_json : '',
			),
			null
		);

		if ( false === $result ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Bulk insert click rows (e.g., CSV imports).
	 *
	 * @param array<int,array<string,mixed>> $rows
	 * @return array{inserted:int, failed:int}
	 */
	public function insert_rows_bulk( array $rows ): array {
		global $wpdb;
		if ( empty( $rows ) ) {
			return array(
				'inserted' => 0,
				'failed'   => 0,
			);
		}

		$allowed = array_flip(
			array(
				'link_id',
				'link_type',
				'link_key',
				'target_url',
				'clicked_at',
				'click_datetime',
				'click_date',
				'click_time',
				'ip_address',
				'user_agent',
				'user_id',
				'referrer',
				'page_url',
				'page_title',
				'element_type',
				'element_class',
				'element_id',
				'origin',
				'source',
				'utm_source',
				'utm_medium',
				'utm_campaign',
				'utm_term',
				'utm_content',
				'gclid',
				'fbclid',
				'msclkid',
				'ttclid',
				'meta_data',
			)
		);

		$inserted = 0;
		$failed   = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row ) ) {
				++$failed;
				continue;
			}

			$rec = array_intersect_key( $row, $allowed );
			if ( empty( $rec ) ) {
				++$failed;
				continue;
			}

			$formats = array_fill( 0, count( $rec ), '%s' );
			$ok      = $wpdb->insert( $this->clicks_table, $rec, $formats );
			if ( false === $ok ) {
				++$failed;
				continue;
			}
			++$inserted;
		}

		return array(
			'inserted' => $inserted,
			'failed'   => $failed,
		);
	}
}
