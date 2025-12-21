<?php

declare(strict_types=1);

namespace LeadStream\Integrations\GTM;

use LeadStream\DTO\ClickContext;

final class GTMDataLayerMapper {
	/**
	 * Maps the ClickContext to a GTM-friendly dataLayer event object.
	 *
	 * @return array<string,mixed>
	 */
	public static function map_phone_click( ClickContext $context ): array {
		$event_id = method_exists( $context, 'event_id' ) ? (string) $context->event_id() : '';
		return array(
			'event'        => 'ls_conversion',
			'event_id'     => $event_id,
			'ls_event_data' => array(
				'action'   => $context->link_type(),
				'label'    => $context->link_key(),
				'location' => $context->page_url(),
				'source'   => $context->get_utm( 'utm_source' ),
				'gclid'    => $context->get_click_id( 'gclid' ),
				'event_id' => $event_id,
			),
			'ls_attribution' => array(
				'utm_source'   => $context->get_utm( 'utm_source' ),
				'utm_medium'   => $context->get_utm( 'utm_medium' ),
				'utm_campaign' => $context->get_utm( 'utm_campaign' ),
				'utm_term'     => $context->get_utm( 'utm_term' ),
				'utm_content'  => $context->get_utm( 'utm_content' ),
				'gclid'        => $context->get_click_id( 'gclid' ),
				'fbclid'       => $context->get_click_id( 'fbclid' ),
				'msclkid'      => $context->get_click_id( 'msclkid' ),
				'ttclid'       => $context->get_click_id( 'ttclid' ),
			),
		);
	}
}
