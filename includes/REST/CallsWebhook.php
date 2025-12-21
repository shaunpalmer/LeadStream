<?php
namespace LS\REST;

defined( 'ABSPATH' ) || exit;

class CallsWebhook {
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			'leadstream/v1',
			'/calls',
			array(
				'methods'             => array( 'POST' ),
				'permission_callback' => '__return_true', // Providers post publicly; authenticate at network edge if needed
				'callback'            => array( __CLASS__, 'handle_webhook' ),
			)
		);
	}

	public static function handle_webhook( \WP_REST_Request $req ) {
		// Accept JSON or form data
		$b = $req->get_json_params();
		if ( ! is_array( $b ) || empty( $b ) ) {
			$b = $req->get_params();
		}

		// Sanitize
		$provider  = isset( $b['provider'] ) ? sanitize_text_field( $b['provider'] ) : '';
		$sid       = isset( $b['provider_call_id'] ) ? sanitize_text_field( $b['provider_call_id'] ) : '';
		$from      = isset( $b['from'] ) ? sanitize_text_field( $b['from'] ) : '';
		$to        = isset( $b['to'] ) ? sanitize_text_field( $b['to'] ) : '';
		$status    = isset( $b['status'] ) ? sanitize_text_field( $b['status'] ) : '';
		$start     = isset( $b['start_time'] ) ? sanitize_text_field( $b['start_time'] ) : '';
		$end       = isset( $b['end_time'] ) ? sanitize_text_field( $b['end_time'] ) : '';
		$duration  = isset( $b['duration'] ) ? intval( $b['duration'] ) : 0;
		$recording = isset( $b['recording_url'] ) ? esc_url_raw( $b['recording_url'] ) : '';

		// Optional correlation keys
		$click_id = isset( $b['click_id'] ) ? intval( $b['click_id'] ) : null;
		$event_id = isset( $b['event_id'] ) ? sanitize_text_field( (string) $b['event_id'] ) : '';
		$event_id = substr( $event_id, 0, 128 );
		$ga_client_id = isset( $b['ga_client_id'] ) ? sanitize_text_field( (string) $b['ga_client_id'] ) : '';
		$ga_session_id = isset( $b['ga_session_id'] ) ? intval( $b['ga_session_id'] ) : 0;
		$ga_session_number = isset( $b['ga_session_number'] ) ? intval( $b['ga_session_number'] ) : 0;
		$meta     = isset( $b['meta'] ) ? $b['meta'] : array();

		// Normalize ISO8601 -> mysql datetime if provided
		$to_mysql = function ( $v ) {
			if ( empty( $v ) ) {
				return null;
			}
			$t = strtotime( $v );
			return $t ? gmdate( 'Y-m-d H:i:s', $t ) : null;
		};

		// Validate minimal required fields
		if ( empty( $provider ) || empty( $sid ) || empty( $to ) ) {
			return new \WP_REST_Response( array( 'error' => 'Missing required fields' ), 400 );
		}

		// Normalize status to our ls_calls schema expectations.
		$outcome_status = 'unknown';
		if ( in_array( $status, array( 'answered', 'completed' ), true ) ) {
			$outcome_status = 'completed';
		} elseif ( in_array( $status, array( 'missed', 'no-answer' ), true ) ) {
			$outcome_status = 'missed';
		} elseif ( in_array( $status, array( 'failed', 'busy' ), true ) ) {
			$outcome_status = 'failed';
		}

		// Attempt to correlate with click if not provided
		// Attempt to correlate with click if not provided (recent, within last hour).
		if ( empty( $click_id ) ) {
			$phone_key   = preg_replace( '/\D+/', '', (string) $to );
			$since_mysql = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
			$clicks_repo = new \LS\Repository\ClicksRepository();
			$found_id    = $clicks_repo->find_recent_phone_click_id( (string) $phone_key, $since_mysql );
			$click_id    = $found_id > 0 ? $found_id : null;
		}

		$meta_payload = is_array( $meta ) ? $meta : array();
		$meta_payload = array_merge(
			$meta_payload,
			array(
				'raw_status' => $status,
				'event_id'   => $event_id,
				'ga'         => array(
					'ga_client_id'      => $ga_client_id,
					'ga_session_id'     => $ga_session_id,
					'ga_session_number' => $ga_session_number,
				),
			)
		);

		$calls_repo = new \LS\Repository\CallsRepository();
		$call_id    = $calls_repo->upsert_webhook_call(
			array(
				'provider'         => $provider,
				'provider_call_id' => $sid,
				'from_number'      => $from,
				'to_number'        => $to,
				'status'           => $outcome_status,
				'start_time'       => $to_mysql( $start ),
				'end_time'         => $to_mysql( $end ),
				'duration'         => $duration,
				'recording_url'    => $recording,
				'click_id'         => $click_id,
				'meta_data'        => wp_json_encode( $meta_payload ),
			)
		);

		if ( 0 === $call_id ) {
			return new \WP_REST_Response( array( 'error' => 'Database insert failed' ), 500 );
		}

		// Run through the tracking pipeline (guard + strategies + integration hook).
		if ( class_exists( '\\LeadStream\\DTO\\ClickContext' ) && class_exists( '\\LeadStream\\DTO\\TrackingEnvelope' ) && class_exists( '\\LeadStream\\Pipeline\\TrackingPipelineRunner' ) ) {
			$event_name = 'call_unknown';
			if ( 'completed' === $outcome_status ) {
				$event_name = 'call_answered';
			} elseif ( 'missed' === $outcome_status ) {
				$event_name = 'call_missed';
			} elseif ( 'failed' === $outcome_status ) {
				$event_name = 'call_failed';
			}

				if ( '' === $event_id ) {
					$event_id = hash( 'sha256', 'call|' . $provider . '|' . $sid . '|' . $outcome_status );
				}
			$to_digits = preg_replace( '/\D+/', '', (string) $to );
			if ( ! is_string( $to_digits ) ) {
				$to_digits = '';
			}

			$utm = array(
				'utm_source'   => '',
				'utm_medium'   => '',
				'utm_campaign' => '',
				'utm_term'     => '',
				'utm_content'  => '',
			);
			$click_ids = array(
				'gclid'   => '',
				'fbclid'  => '',
				'msclkid' => '',
				'ttclid'  => '',
			);
			$device = array(
				'client_language'  => '',
				'device_type'      => '',
				'viewport_w'       => 0,
				'viewport_h'       => 0,
				'time_to_click_ms' => 0,
				'landing_page'     => '',
			);
			$element = array(
				'element_type'  => 'call',
				'element_class' => '',
				'element_id'    => (string) $sid,
				'original'      => (string) $to,
			);

			$ctx = new \LeadStream\DTO\ClickContext(
				new \DateTimeImmutable( 'now' ),
				'call',
				substr( (string) $to_digits, 0, 255 ),
				'tel:' . (string) $to_digits,
				'',
				'',
				'',
				'call_webhook',
				(string) $provider,
				substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 512 ),
				substr( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ), 0, 45 ),
				0,
				false,
				$utm,
				$click_ids,
				$device,
				$element,
				$event_id,
				'',
					$ga_client_id,
					$ga_session_id,
					$ga_session_number
			);

			$envelope = new \LeadStream\DTO\TrackingEnvelope(
				$ctx,
				$event_name,
				$event_id,
				(int) ( $click_id ?? 0 ),
				array(
					'provider'         => (string) $provider,
					'provider_call_id' => (string) $sid,
					'status'           => (string) $outcome_status,
					'duration'         => (int) $duration,
					'from'             => (string) $from,
					'to'               => (string) $to,
					'call_id'          => (int) $call_id,
						'ga_client_id'     => (string) $ga_client_id,
						'ga_session_id'    => (int) $ga_session_id,
						'ga_session_number' => (int) $ga_session_number,
				)
			);

			\LeadStream\Pipeline\TrackingPipelineRunner::run( $envelope, 30 );
		}

		return new \WP_REST_Response(
			array(
				'success'          => true,
				'call_id'          => $call_id,
				'provider_call_id' => $sid,
			),
			200
		);
	}
}
