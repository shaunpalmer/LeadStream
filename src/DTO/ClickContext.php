<?php

declare(strict_types=1);

namespace LeadStream\DTO;

final class ClickContext {
	private \DateTimeImmutable $timestamp;
	private string $linkType;
	private string $linkKey;
	private string $targetUrl;
	private string $pageUrl;
	private string $pageTitle;
	private string $referrer;
	private string $origin;
	private string $source;
	private string $userAgent;
	private string $ipAddress;
	private int $userId;
	private bool $isAuthenticated;
	private string $eventId;
	private string $phoneE164;
	private string $gaClientId;
	private int $gaSessionId;
	private int $gaSessionNumber;
	/** @var array{utm_source:string,utm_medium:string,utm_campaign:string,utm_term:string,utm_content:string} */
	private array $utm;
	/** @var array{gclid:string,fbclid:string,msclkid:string,ttclid:string} */
	private array $clickIds;
	/** @var array{client_language:string,device_type:string,viewport_w:int,viewport_h:int,time_to_click_ms:int,landing_page:string} */
	private array $device;
	/** @var array{element_type:string,element_class:string,element_id:string,original:string} */
	private array $element;

	/**
	 * @param array{utm_source:string,utm_medium:string,utm_campaign:string,utm_term:string,utm_content:string} $utm
	 * @param array{gclid:string,fbclid:string,msclkid:string,ttclid:string} $clickIds
	 * @param array{client_language:string,device_type:string,viewport_w:int,viewport_h:int,time_to_click_ms:int,landing_page:string} $device
	 * @param array{element_type:string,element_class:string,element_id:string,original:string} $element
	 */
	public function __construct(
		\DateTimeImmutable $timestamp,
		string $linkType,
		string $linkKey,
		string $targetUrl,
		string $pageUrl,
		string $pageTitle,
		string $referrer,
		string $origin,
		string $source,
		string $userAgent,
		string $ipAddress,
		int $userId,
		bool $isAuthenticated,
		array $utm,
		array $clickIds,
		array $device,
		array $element,
		string $eventId = '',
		string $phoneE164 = '',
		string $gaClientId = '',
		int $gaSessionId = 0,
		int $gaSessionNumber = 0
	) {
		$this->timestamp       = $timestamp;
		$this->linkType         = $linkType;
		$this->linkKey          = $linkKey;
		$this->targetUrl        = $targetUrl;
		$this->pageUrl          = $pageUrl;
		$this->pageTitle        = $pageTitle;
		$this->referrer         = $referrer;
		$this->origin           = $origin;
		$this->source           = $source;
		$this->userAgent        = $userAgent;
		$this->ipAddress        = $ipAddress;
		$this->userId           = $userId;
		$this->isAuthenticated  = $isAuthenticated;
		$this->eventId          = $eventId;
		$this->phoneE164        = $phoneE164;
		$this->gaClientId       = $gaClientId;
		$this->gaSessionId      = $gaSessionId;
		$this->gaSessionNumber  = $gaSessionNumber;
		$this->utm              = $utm;
		$this->clickIds         = $clickIds;
		$this->device           = $device;
		$this->element          = $element;
	}

	public function event_id(): string {
		return $this->eventId;
	}

	public function phone_e164(): string {
		return $this->phoneE164;
	}

	public function ga_client_id(): string {
		return $this->gaClientId;
	}

	public function ga_session_id(): int {
		return $this->gaSessionId;
	}

	public function ga_session_number(): int {
		return $this->gaSessionNumber;
	}

	public function timestamp(): \DateTimeImmutable {
		return $this->timestamp;
	}

	public function link_type(): string {
		return $this->linkType;
	}

	public function link_key(): string {
		return $this->linkKey;
	}

	public function target_url(): string {
		return $this->targetUrl;
	}

	public function page_url(): string {
		return $this->pageUrl;
	}

	public function page_title(): string {
		return $this->pageTitle;
	}

	public function referrer(): string {
		return $this->referrer;
	}

	public function origin(): string {
		return $this->origin;
	}

	public function source(): string {
		return $this->source;
	}

	public function user_agent(): string {
		return $this->userAgent;
	}

	public function ip_address(): string {
		return $this->ipAddress;
	}

	public function user_id(): int {
		return $this->userId;
	}

	public function is_authenticated(): bool {
		return $this->isAuthenticated;
	}

	public function get_utm( string $key ): string {
		return isset( $this->utm[ $key ] ) ? $this->utm[ $key ] : '';
	}

	public function get_click_id( string $key ): string {
		return isset( $this->clickIds[ $key ] ) ? $this->clickIds[ $key ] : '';
	}

	public function is_bot(): bool {
		$ua = strtolower( $this->userAgent );
		if ( '' === $ua ) {
			return false;
		}
		$needles = array( 'bot', 'spider', 'crawl', 'slurp', 'bingpreview', 'facebookexternalhit', 'whatsapp', 'telegrambot' );
		foreach ( $needles as $n ) {
			if ( false !== strpos( $ua, $n ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Convert to the payload expected by LS\Repository\ClicksRepository::insert_phone_click().
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'event_id'         => $this->eventId,
			'phone'            => $this->linkKey,
			'phone_e164'       => $this->phoneE164,
			'original_phone'   => $this->element['original'],
			'origin'           => $this->origin,
			'source'           => $this->source,
			'ga_client_id'     => $this->gaClientId,
			'ga_session_id'    => $this->gaSessionId,
			'ga_session_number' => $this->gaSessionNumber,
			'utm_source'       => $this->utm['utm_source'],
			'utm_medium'       => $this->utm['utm_medium'],
			'utm_campaign'     => $this->utm['utm_campaign'],
			'utm_term'         => $this->utm['utm_term'],
			'utm_content'      => $this->utm['utm_content'],
			'gclid'            => $this->clickIds['gclid'],
			'fbclid'           => $this->clickIds['fbclid'],
			'msclkid'          => $this->clickIds['msclkid'],
			'ttclid'           => $this->clickIds['ttclid'],
			'client_language'  => $this->device['client_language'],
			'device_type'      => $this->device['device_type'],
			'viewport_w'       => $this->device['viewport_w'],
			'viewport_h'       => $this->device['viewport_h'],
			'time_to_click_ms' => $this->device['time_to_click_ms'],
			'landing_page'     => $this->device['landing_page'],
			'ip_address'       => $this->ipAddress,
			'user_agent'       => $this->userAgent,
			'referrer'         => $this->referrer,
			'user_id'          => $this->userId,
			'is_authenticated' => $this->isAuthenticated ? 1 : 0,
			'page_url'         => $this->pageUrl,
			'page_title'       => $this->pageTitle,
			'element_type'     => $this->element['element_type'],
			'element_class'    => $this->element['element_class'],
			'element_id'       => $this->element['element_id'],
		);
	}
}
