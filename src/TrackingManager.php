<?php

declare(strict_types=1);

namespace LeadStream;

use LeadStream\DTO\TrackingEnvelope;
use LeadStream\Strategies\TrackingStrategyInterface;

final class TrackingManager {
	/** @var array<int,TrackingStrategyInterface> */
	private array $strategies;

	/**
	 * @param array<int,TrackingStrategyInterface> $strategies
	 */
	public function __construct( array $strategies ) {
		$this->strategies = $strategies;
	}

	public function dispatch_envelope( TrackingEnvelope $envelope ): void {
		foreach ( $this->strategies as $strategy ) {
			if ( $strategy->should_skip( $envelope ) ) {
				continue;
			}
			$strategy->dispatch( $envelope );
		}
	}

}
