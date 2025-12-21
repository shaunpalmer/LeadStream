<?php

declare(strict_types=1);

namespace LeadStream\Strategies;

use LeadStream\DTO\TrackingEnvelope;

interface TrackingStrategyInterface {
	/**
	 * Allows a strategy to self-disable based on the envelope and environment.
	 */
	public function should_skip( TrackingEnvelope $envelope ): bool;

	/**
	 * Dispatch the envelope to the strategy's destination.
	 */
	public function dispatch( TrackingEnvelope $envelope ): void;
}
