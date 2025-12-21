<?php

declare(strict_types=1);

namespace LeadStream\Factories;

use LeadStream\Registry\TrackingStrategyRegistry;
use LeadStream\TrackingManager;

final class TrackingPipelineFactory {
	public static function create_manager(): TrackingManager {
		return new TrackingManager( TrackingStrategyRegistry::default_strategies() );
	}
}
