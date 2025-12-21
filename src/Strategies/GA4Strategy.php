<?php

declare(strict_types=1);

namespace LeadStream\Strategies;

use LeadStream\DTO\TrackingEnvelope;

final class GA4Strategy extends AbstractTrackingStrategy {
	public function should_skip( TrackingEnvelope $envelope ): bool {
		// If not configured, skip.
		\LeadStream\GA4Service::init();
		if ( ! \LeadStream\GA4Service::is_configured() ) {
			return true;
		}

		$event_name = $envelope->event_name();
		if ( ! in_array( $event_name, array( 'phone_click', 'call_answered', 'call_missed' ), true ) ) {
			return true;
		}

		// If frontend indicates GTM is the driver for GA4, we still keep server-side GA4 as a backup,
		// but the EventGuard will prevent duplicates.
		return false;
	}

	public function dispatch( TrackingEnvelope $envelope ): void {
		\LeadStream\GA4Service::init();
		$event_name = $envelope->event_name();
		$click_id   = $envelope->click_id();
		$ctx        = $envelope->context();

		if ( 'phone_click' === $event_name ) {
			\LeadStream\GA4Service::send_phone_click_event_from_context( $ctx, $click_id );
			return;
		}

		if ( 'call_answered' === $event_name || 'call_missed' === $event_name ) {
			$meta            = $envelope->meta();
			$provider_call_id = isset( $meta['provider_call_id'] ) ? (string) $meta['provider_call_id'] : '';
			$duration         = isset( $meta['duration'] ) ? (string) $meta['duration'] : '';
			$status           = 'call_answered' === $event_name ? 'answered' : 'missed';
			\LeadStream\GA4Service::send_call_outcome_event( $status, $duration, $click_id, $provider_call_id );
		}
	}
}
