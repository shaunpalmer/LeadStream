<?php

declare(strict_types=1);

namespace LeadStream\Pipeline;

use LeadStream\DTO\TrackingEnvelope;

final class TrackingPipelineRunner {
	/**
	 * Runs the tracking pipeline for a conversion envelope.
	 *
	 * Responsibilities:
	 * - Ensure a stable event_id (best-effort)
	 * - Apply anti-double-send guard
	 * - Dispatch through TrackingManager (strategies)
	 * - Emit integration hook for external plugins
	 */
	public static function run( TrackingEnvelope $envelope, int $ttl_seconds = 2 ): bool {
		$event_id = (string) $envelope->event_id();
		if ( '' === $event_id ) {
			$event_id = \LeadStream\Observers\EventGuard::build_event_id(
				$envelope->context(),
				$envelope->event_name(),
				$envelope->click_id()
			);
			$envelope = $envelope->with_event_id( $event_id );
		}

		$allow_send = \LeadStream\Observers\TransientEventGuard::should_allow_send( $event_id, $ttl_seconds );
		if ( $allow_send ) {
			$manager = \LeadStream\Factories\TrackingPipelineFactory::create_manager();
			$manager->dispatch_envelope( $envelope );
		}

		if ( function_exists( 'do_action' ) ) {
			do_action( 'leadstream_conversion_recorded', $envelope, $envelope->click_id(), $event_id, $allow_send );
		}

		return $allow_send;
	}
}
