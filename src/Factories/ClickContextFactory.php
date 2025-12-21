<?php

declare(strict_types=1);

namespace LeadStream\Factories;

use LeadStream\DTO\ClickContext;

final class ClickContextFactory {
	/**
	 * Hydrate a ClickContext from raw AJAX request data.
	 *
	 * @param array<string,mixed> $post
	 * @param array<string,mixed> $server
	 * @param array<string,mixed> $cookies
	 */
	public static function from_ajax_request( array $post, array $server, bool $is_authenticated, array $cookies = array() ): ClickContext {
		$raw_post = function_exists( 'wp_unslash' ) ? wp_unslash( $post ) : $post;
		$raw_srv  = function_exists( 'wp_unslash' ) ? wp_unslash( $server ) : $server;
		$raw_cookies = function_exists( 'wp_unslash' ) ? wp_unslash( $cookies ) : $cookies;

		$phone_raw = isset( $raw_post['phone'] ) ? sanitize_text_field( (string) $raw_post['phone'] ) : '';
		$norm      = \LeadStream\Utils\PhoneNumberNormalizer::normalize( $phone_raw, '+64' );
		$phone_digits = (string) ( $norm['digits'] ?? '' );
		$phone_e164   = (string) ( $norm['e164'] ?? '' );
		if ( '' === $phone_digits ) {
			throw new \InvalidArgumentException( 'Phone number required' );
		}

		$original_phone = isset( $raw_post['original_phone'] ) ? sanitize_text_field( (string) $raw_post['original_phone'] ) : '';
		$page_url       = isset( $raw_post['page_url'] ) ? esc_url_raw( (string) $raw_post['page_url'] ) : '';
		if ( '' === $page_url && isset( $raw_post['url'] ) ) {
			$page_url = esc_url_raw( (string) $raw_post['url'] );
		}
		$page_title = isset( $raw_post['page_title'] ) ? sanitize_text_field( (string) $raw_post['page_title'] ) : '';
		$origin     = isset( $raw_post['origin'] ) ? sanitize_text_field( (string) $raw_post['origin'] ) : '';
		$source     = isset( $raw_post['source'] ) ? sanitize_text_field( (string) $raw_post['source'] ) : '';

		$utm = array(
			'utm_source'   => isset( $raw_post['utm_source'] ) ? sanitize_text_field( (string) $raw_post['utm_source'] ) : '',
			'utm_medium'   => isset( $raw_post['utm_medium'] ) ? sanitize_text_field( (string) $raw_post['utm_medium'] ) : '',
			'utm_campaign' => isset( $raw_post['utm_campaign'] ) ? sanitize_text_field( (string) $raw_post['utm_campaign'] ) : '',
			'utm_term'     => isset( $raw_post['utm_term'] ) ? sanitize_text_field( (string) $raw_post['utm_term'] ) : '',
			'utm_content'  => isset( $raw_post['utm_content'] ) ? sanitize_text_field( (string) $raw_post['utm_content'] ) : '',
		);

		$click_ids = array(
			'gclid'   => isset( $raw_post['gclid'] ) ? sanitize_text_field( (string) $raw_post['gclid'] ) : '',
			'fbclid'  => isset( $raw_post['fbclid'] ) ? sanitize_text_field( (string) $raw_post['fbclid'] ) : '',
			'msclkid' => isset( $raw_post['msclkid'] ) ? sanitize_text_field( (string) $raw_post['msclkid'] ) : '',
			'ttclid'  => isset( $raw_post['ttclid'] ) ? sanitize_text_field( (string) $raw_post['ttclid'] ) : '',
		);

		$device = array(
			'client_language'  => isset( $raw_post['client_language'] ) ? sanitize_text_field( (string) $raw_post['client_language'] ) : '',
			'device_type'      => isset( $raw_post['device_type'] ) ? sanitize_text_field( (string) $raw_post['device_type'] ) : '',
			'viewport_w'       => isset( $raw_post['viewport_w'] ) ? (int) $raw_post['viewport_w'] : 0,
			'viewport_h'       => isset( $raw_post['viewport_h'] ) ? (int) $raw_post['viewport_h'] : 0,
			'time_to_click_ms' => isset( $raw_post['time_to_click_ms'] ) ? (int) $raw_post['time_to_click_ms'] : 0,
			'landing_page'     => isset( $raw_post['landing_page'] ) ? esc_url_raw( (string) $raw_post['landing_page'] ) : '',
		);

		$element = array(
			'element_type'  => isset( $raw_post['element_type'] ) ? sanitize_text_field( (string) $raw_post['element_type'] ) : '',
			'element_class' => isset( $raw_post['element_class'] ) ? sanitize_text_field( (string) $raw_post['element_class'] ) : '',
			'element_id'    => isset( $raw_post['element_id'] ) ? sanitize_text_field( (string) $raw_post['element_id'] ) : '',
			'original'      => $original_phone,
		);

		$ua = isset( $raw_srv['HTTP_USER_AGENT'] ) ? sanitize_text_field( (string) $raw_srv['HTTP_USER_AGENT'] ) : '';
		$ua = substr( $ua, 0, 512 );

		$raw_ip = '';
		if ( isset( $raw_srv['HTTP_X_FORWARDED_FOR'] ) ) {
			$raw_ip = (string) $raw_srv['HTTP_X_FORWARDED_FOR'];
		} elseif ( isset( $raw_srv['REMOTE_ADDR'] ) ) {
			$raw_ip = (string) $raw_srv['REMOTE_ADDR'];
		}
		if ( false !== strpos( $raw_ip, ',' ) ) {
			$ip_parts = explode( ',', $raw_ip );
			$raw_ip   = trim( (string) $ip_parts[0] );
		}
		$ip = sanitize_text_field( $raw_ip );

		$ref = isset( $raw_srv['HTTP_REFERER'] ) ? sanitize_text_field( (string) $raw_srv['HTTP_REFERER'] ) : '';

		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

		$ga_client_id      = isset( $raw_post['ga_client_id'] ) ? sanitize_text_field( (string) $raw_post['ga_client_id'] ) : '';
		$ga_session_id     = isset( $raw_post['ga_session_id'] ) ? (int) $raw_post['ga_session_id'] : 0;
		$ga_session_number = isset( $raw_post['ga_session_number'] ) ? (int) $raw_post['ga_session_number'] : 0;
		$event_id          = isset( $raw_post['event_id'] ) ? sanitize_text_field( (string) $raw_post['event_id'] ) : '';
		$event_id          = substr( $event_id, 0, 128 );

		// Cookie fallback (best-effort) if JS didn't provide golden keys.
		if ( '' === $ga_client_id && isset( $raw_cookies['_ga'] ) && is_scalar( $raw_cookies['_ga'] ) ) {
			$parts = explode( '.', (string) $raw_cookies['_ga'] );
			if ( count( $parts ) >= 4 ) {
				$ga_client_id = sanitize_text_field( $parts[2] . '.' . $parts[3] );
			}
		}

		if ( 0 === $ga_session_id ) {
			foreach ( $raw_cookies as $cookie_name => $cookie_value ) {
				if ( ! is_string( $cookie_name ) || ! is_scalar( $cookie_value ) ) {
					continue;
				}
				if ( 0 !== strpos( $cookie_name, '_ga_' ) ) {
					continue;
				}
				$val = (string) $cookie_value;
				if ( 0 !== strpos( $val, 'GS1.' ) ) {
					continue;
				}
				$parts = explode( '.', $val );
				// Expected: GS1.1.<session_id>.<session_number>....
				if ( count( $parts ) >= 4 ) {
					$maybe_session_id     = (int) $parts[2];
					$maybe_session_number = (int) $parts[3];
					if ( $maybe_session_id > 0 ) {
						$ga_session_id = $maybe_session_id;
					}
					if ( $maybe_session_number > 0 ) {
						$ga_session_number = $maybe_session_number;
					}
				}
				break;
			}
		}

		return new ClickContext(
			new \DateTimeImmutable( 'now' ),
			'phone',
			substr( (string) $phone_digits, 0, 255 ),
			'tel:' . (string) $phone_digits,
			$page_url,
			$page_title,
			$ref,
			$origin,
			$source,
			$ua,
			substr( $ip, 0, 45 ),
			$user_id,
			$is_authenticated,
			$utm,
			$click_ids,
			$device,
			$element,
			$event_id,
			substr( $phone_e164, 0, 32 ),
			$ga_client_id,
			$ga_session_id,
			$ga_session_number
		);
	}
}
