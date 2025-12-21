<?php
namespace LS\AJAX;

defined( 'ABSPATH' ) || exit;

class PhoneHandler {

	public static function init() {
		// Primary action used by frontend JS.
		add_action( 'wp_ajax_leadstream_record_phone_click', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_leadstream_record_phone_click', array( __CLASS__, 'handle' ) );
		// Back-compat action name.
		add_action( 'wp_ajax_leadstream_phone_click', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_leadstream_phone_click', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Handle phone click AJAX request
	 *
	 * Security:
	 * - Optional nonce verification for authenticated tracking
	 * - Public endpoint (nopriv) allows anonymous tracking
	 * - IP throttling + duplicate detection (2-sec debounce)
	 * - All user input sanitized
	 * - GA4 Measurement Protocol integration
	 */
	public static function handle() {
		try {
			// 1. GATEKEEPER: Method Check
			if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				wp_send_json_error( array( 'message' => 'Invalid method' ), 405 );
			}

			// 2. SECURITY: Nonce Verification (Optional)
			$nonce            = $_POST['nonce'] ?? ( $_POST['_ajax_nonce'] ?? '' );
			$is_authenticated = false;

			if ( ! empty( $nonce ) && function_exists( 'wp_verify_nonce' ) ) {
				if ( wp_verify_nonce( $nonce, 'leadstream_phone_click' ) ) {
					$is_authenticated = true;
				}
			}

			// 3. DATA: Sanitization & Collection
			try {
				$ctx = \LeadStream\Factories\ClickContextFactory::from_ajax_request( $_POST, $_SERVER, $is_authenticated, $_COOKIE );
			} catch ( \InvalidArgumentException $e ) {
				wp_send_json_error( array( 'message' => $e->getMessage() ), 400 );
			}

			$payload = $ctx->to_array();
			$phone   = (string) ( $payload['phone'] ?? '' );
			$page_url = (string) ( $payload['page_url'] ?? '' );
			$gclid   = (string) ( $payload['gclid'] ?? '' );

			// 4. OBSERVER GUARD (pre-DB): 2-second debounce to prevent duplicate clicks.
			$event_id_for_key = '';
			if ( method_exists( $ctx, 'event_id' ) ) {
				$event_id_for_key = (string) $ctx->event_id();
			}
			$debounce_hash = '' !== $event_id_for_key ? $event_id_for_key : ( $phone . '|' . $page_url );
			$debounce_key  = 'ls_click_debounce_' . hash( 'sha256', $debounce_hash );

			if ( get_transient( $debounce_key ) ) {
				$clicks_repo       = new \LS\Repository\ClicksRepository();
				$since_mysql       = gmdate( 'Y-m-d H:i:s', time() - 2 );
				$existing_click_id = $clicks_repo->find_recent_phone_click_id( preg_replace( '/\D+/', '', $phone ), $since_mysql );

				wp_send_json_success(
					array(
						'received' => true,
						'status'   => 'deduplicated',
						'click_id' => $existing_click_id,
						'event_id' => $event_id_for_key,
						'sent'     => false,
					)
				);
			}

			set_transient( $debounce_key, 1, 2 );

			// 5. IP DETECTION & RATE LIMITING
			$raw_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
			if ( strpos( $raw_ip, ',' ) !== false ) {
				$ip_parts = explode( ',', $raw_ip );
				$raw_ip   = trim( $ip_parts[0] );
			}
			$ip = sanitize_text_field( $raw_ip );

			// Basic rate limiting: max 10 clicks per IP per minute
			$rate_limit_key = 'ls_rate_limit_' . $ip;
			$click_count    = (int) get_transient( $rate_limit_key );
			if ( $click_count >= 10 ) {
				wp_send_json_error( array( 'message' => 'Rate limit exceeded' ), 429 );
			}
			set_transient( $rate_limit_key, $click_count + 1, 60 );

			// 6. DATABASE: Log click locally
			$clicks_repo = new \LS\Repository\ClicksRepository();
			$payload['ip_address'] = $ip;
			$click_id              = $clicks_repo->insert_phone_click( $payload );

			if ( 0 === $click_id ) {
				wp_send_json_error( array( 'message' => 'Database error' ), 500 );
			}

			// 7. Observer Guard: suppress duplicate downstream sends for 2 seconds.
			$event_id = '';
			if ( method_exists( $ctx, 'event_id' ) ) {
				$event_id = (string) $ctx->event_id();
			}
			if ( '' === $event_id ) {
				$event_id = \LeadStream\Observers\EventGuard::build_event_id( $ctx, 'phone_click', $click_id );
			}

			$envelope = new \LeadStream\DTO\TrackingEnvelope( $ctx, 'phone_click', $event_id, $click_id );
			$allow_send = \LeadStream\Pipeline\TrackingPipelineRunner::run( $envelope, 2 );

			wp_send_json_success(
				array(
					'received' => true,
					'click_id' => $click_id,
					'event_id' => $event_id,
					'sent'     => $allow_send,
				)
			);

		} catch ( \Throwable $e ) {
			set_transient( 'leadstream_phone_handler_crash', true, HOUR_IN_SECONDS );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'LeadStream PhoneHandler Error: ' . $e->getMessage() );
			}
			wp_send_json_error( array( 'message' => 'Internal Server Error' ), 500 );
		}
	}
}
