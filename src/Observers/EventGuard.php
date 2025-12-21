<?php

declare(strict_types=1);

namespace LeadStream\Observers;

use LeadStream\DTO\ClickContext;

abstract class EventGuard {
	/**
	 * Build a deterministic-ish event_id that stays stable across:
	 * - JS dataLayer push
	 * - AJAX logging
	 * - server-side Measurement Protocol
	 */
	public static function build_event_id( ClickContext $ctx, string $event_name, int $click_id = 0 ): string {
		$parts = array(
			$event_name,
			(string) $click_id,
			$ctx->link_type(),
			$ctx->link_key(),
			$ctx->page_url(),
			$ctx->ga_client_id(),
			(string) $ctx->ga_session_id(),
			(string) $ctx->timestamp()->getTimestamp(),
		);

		$raw = implode( '|', $parts );
		return hash( 'sha256', $raw );
	}

}
