<?php

declare(strict_types=1);

namespace LeadStream\Observers;

final class TransientEventGuard extends EventGuard {
	/**
	 * Guard against double-sending the same event.
	 *
	 * @param string $event_id Stable event id.
	 * @param int $ttl_seconds How long to suppress duplicates.
	 */
	public static function should_allow_send( string $event_id, int $ttl_seconds = 2 ): bool {
		if ( '' === $event_id ) {
			return true;
		}

		$ttl_seconds = max( 1, (int) $ttl_seconds );
		$key         = 'ls_evt_guard_' . substr( $event_id, 0, 40 );

		if ( function_exists( 'get_transient' ) && function_exists( 'set_transient' ) ) {
			$existing = get_transient( $key );
			if ( $existing ) {
				return false;
			}
			set_transient( $key, 1, $ttl_seconds );
			return true;
		}

		return true;
	}
}
