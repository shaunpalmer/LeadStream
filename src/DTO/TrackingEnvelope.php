<?php

declare(strict_types=1);

namespace LeadStream\DTO;

final class TrackingEnvelope {
	private ClickContext $context;
	private string $eventName;
	private string $eventId;
	private int $clickId;
	/** @var array<string,mixed> */
	private array $meta;

	/**
	 * @param array<string,mixed> $meta
	 */
	public function __construct( ClickContext $context, string $eventName, string $eventId = '', int $clickId = 0, array $meta = array() ) {
		$this->context   = $context;
		$this->eventName = $eventName;
		$this->eventId   = $eventId;
		$this->clickId   = $clickId;
		$this->meta      = $meta;
	}

	public function context(): ClickContext {
		return $this->context;
	}

	public function event_name(): string {
		return $this->eventName;
	}

	public function event_id(): string {
		return $this->eventId;
	}

	public function click_id(): int {
		return $this->clickId;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function meta(): array {
		return $this->meta;
	}

	public function with_click_id( int $clickId ): self {
		return new self( $this->context, $this->eventName, $this->eventId, $clickId, $this->meta );
	}

	public function with_event_id( string $eventId ): self {
		return new self( $this->context, $this->eventName, $eventId, $this->clickId, $this->meta );
	}

	/**
	 * @param array<string,mixed> $meta
	 */
	public function with_meta( array $meta ): self {
		return new self( $this->context, $this->eventName, $this->eventId, $this->clickId, $meta );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'event_name' => $this->eventName,
			'event_id'   => $this->eventId,
			'click_id'   => $this->clickId,
			'context'    => $this->context->to_array(),
			'meta'       => $this->meta,
		);
	}

	public function to_json(): string {
		$payload = $this->to_array();
		if ( function_exists( 'wp_json_encode' ) ) {
			$encoded = wp_json_encode( $payload );
			return is_string( $encoded ) ? $encoded : '';
		}
		$encoded = json_encode( $payload );
		return is_string( $encoded ) ? $encoded : '';
	}
}
