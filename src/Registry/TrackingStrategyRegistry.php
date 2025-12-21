<?php

declare(strict_types=1);

namespace LeadStream\Registry;

use LeadStream\Strategies\GA4Strategy;
use LeadStream\Strategies\TrackingStrategyInterface;

final class TrackingStrategyRegistry {
	/**
	 * Default strategies shipped with LeadStream.
	 *
	 * Extension point:
	 * - filter `leadstream_tracking_strategies`
	 * - receives array<int,TrackingStrategyInterface>
	 * - must return array<int,TrackingStrategyInterface>
	 *
	 * @return array<int,TrackingStrategyInterface>
	 */
	public static function default_strategies(): array {
		$strategies = array(
			new GA4Strategy(),
		);

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'leadstream_tracking_strategies', $strategies );
			if ( is_array( $filtered ) ) {
				return $filtered;
			}
		}

		return $strategies;
	}
}
