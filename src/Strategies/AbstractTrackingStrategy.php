<?php

declare(strict_types=1);

namespace LeadStream\Strategies;

use LeadStream\DTO\TrackingEnvelope;

abstract class AbstractTrackingStrategy implements TrackingStrategyInterface {
	public function should_skip( TrackingEnvelope $envelope ): bool {
		return false;
	}

	abstract public function dispatch( TrackingEnvelope $envelope ): void;
}
