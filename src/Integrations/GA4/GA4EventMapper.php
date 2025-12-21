<?php

declare(strict_types=1);

namespace LeadStream\Integrations\GA4;

use LeadStream\DTO\ClickContext;

final class GA4EventMapper {
	/**
	 * @return array{name:string,params:array<string,mixed>}
	 */
	public static function map_phone_click( ClickContext $ctx, int $click_id, string $fallback_session_id = '' ): array {
		$params = array(
			'phone_number'         => $ctx->link_key(),
			'phone_e164'           => method_exists( $ctx, 'phone_e164' ) ? (string) $ctx->phone_e164() : '',
			'page_url'             => $ctx->page_url(),
			'page_title'           => $ctx->page_title(),
			'referrer'             => $ctx->referrer(),
			'origin'               => $ctx->origin(),
			'source'               => $ctx->source(),
			'click_id'             => $click_id,
			'event_id'             => method_exists( $ctx, 'event_id' ) ? (string) $ctx->event_id() : '',
			'ga_session_id'        => $ctx->ga_session_id() > 0 ? $ctx->ga_session_id() : ( '' !== $fallback_session_id ? $fallback_session_id : '' ),
			'ga_session_number'    => $ctx->ga_session_number() > 0 ? $ctx->ga_session_number() : 0,
			'engagement_time_msec' => '100',
		);

		if ( 0 === (int) $params['ga_session_number'] ) {
			unset( $params['ga_session_number'] );
		}
		if ( '' === (string) $params['ga_session_id'] ) {
			unset( $params['ga_session_id'] );
		}
		if ( '' === (string) $params['event_id'] ) {
			unset( $params['event_id'] );
		}
		if ( '' === (string) $params['phone_e164'] ) {
			unset( $params['phone_e164'] );
		}

		$utm_keys = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
		foreach ( $utm_keys as $k ) {
			$v = $ctx->get_utm( $k );
			if ( '' !== $v ) {
				$params[ $k ] = $v;
			}
		}

		$cid_keys = array( 'gclid', 'fbclid', 'msclkid', 'ttclid' );
		foreach ( $cid_keys as $k ) {
			$v = $ctx->get_click_id( $k );
			if ( '' !== $v ) {
				$params[ $k ] = $v;
			}
		}

		$payload = $ctx->to_array();
		$extra   = array(
			'landing_page'     => isset( $payload['landing_page'] ) ? (string) $payload['landing_page'] : '',
			'time_to_click_ms' => isset( $payload['time_to_click_ms'] ) ? (int) $payload['time_to_click_ms'] : 0,
			'device_type'      => isset( $payload['device_type'] ) ? (string) $payload['device_type'] : '',
			'client_language'  => isset( $payload['client_language'] ) ? (string) $payload['client_language'] : '',
			'viewport_w'       => isset( $payload['viewport_w'] ) ? (int) $payload['viewport_w'] : 0,
			'viewport_h'       => isset( $payload['viewport_h'] ) ? (int) $payload['viewport_h'] : 0,
		);

		foreach ( $extra as $k => $v ) {
			if ( ( is_int( $v ) && 0 !== $v ) || ( is_string( $v ) && '' !== $v ) ) {
				$params[ $k ] = $v;
			}
		}

		return array(
			'name'   => 'phone_click',
			'params' => $params,
		);
	}
}
