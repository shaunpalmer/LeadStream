<?php
namespace LS\Events;

defined( 'ABSPATH' ) || exit;

use LS\Repository\EventsRepository;

/**
 * LS_Form_Events
 * Capture successful form submissions from common plugins and
 * record a single row in wp_ls_events (event_type=form_submit).
 *
 * No PII is stored: we only keep form id/name, page url, referrer, UA.
 */
class LS_Form_Events {
	private EventsRepository $events;

	public static function init(): void {
		$o = new self();
		$o->hook();
	}

	public function __construct() {
		$this->events = new EventsRepository();
	}

	public function hook(): void {
		// Best-effort ensure schema exists (safe no-op if it does).
		add_action( 'admin_init', array( $this->events, 'ensure_table_schema' ) );

		// Contact Form 7
		add_action(
			'wpcf7_mail_sent',
			function ( $contact_form ) {
				$id   = method_exists( $contact_form, 'id' ) ? (string) $contact_form->id() : '';
				$name = method_exists( $contact_form, 'title' ) ? (string) $contact_form->title() : '';
				$this->record( 'CF7', $id, $name );
			},
			10,
			1
		);

		// WPForms
		add_action(
			'wpforms_process_complete',
			function ( $fields, $entry, $form_data ) {
				$id   = isset( $form_data['id'] ) ? (string) $form_data['id'] : '';
				$name = isset( $form_data['settings']['form_title'] ) ? (string) $form_data['settings']['form_title'] : '';
				$this->record( 'WPForms', $id, $name );
			},
			10,
			3
		);

		// Gravity Forms
		add_action(
			'gform_after_submission',
			function ( $entry, $form ) {
				$id   = isset( $form['id'] ) ? (string) $form['id'] : '';
				$name = isset( $form['title'] ) ? (string) $form['title'] : '';
				$this->record( 'Gravity', $id, $name );
			},
			10,
			2
		);

		// Ninja Forms
		add_action(
			'ninja_forms_after_submission',
			function ( $form_data ) {
				$id   = isset( $form_data['id'] ) ? (string) $form_data['id'] : '';
				$name = isset( $form_data['settings']['title'] ) ? (string) $form_data['settings']['title'] : '';
				$this->record( 'Ninja', $id, $name );
			},
			10,
			1
		);
	}

	/** Write one sanitized row */
	private function record( string $source, string $form_id, string $form_name ): void {
		// De-dupe: if identical form on same page within 2 seconds, skip
		$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			$path = '';
		}
		$key = md5( $source . '|' . $form_id . '|' . $path . '|' . (int) ( time() / 2 ) );
		if ( isset( $GLOBALS['__LS_FORM_DUPE__'][ $key ] ) ) {
			return;
		}
		$GLOBALS['__LS_FORM_DUPE__'][ $key ] = true;

		$params = array(
			'source'    => sanitize_text_field( $source ),
			'form_id'   => sanitize_text_field( $form_id ),
			'form_name' => sanitize_text_field( $form_name ),
			'path'      => $path,
			'page_url'  => esc_url_raw( home_url( add_query_arg( array() ) ) ),
			'referrer'  => esc_url_raw( is_string( wp_get_referer() ) ? wp_get_referer() : '' ),
			'ua'        => substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 512 ),
		);

		$this->events->insert_event(
			'form_submit',
			'form_submit',
			$params,
			get_current_user_id(),
			(string) ( $_SERVER['REMOTE_ADDR'] ?? '' )
		);

		// Also pass through the tracking pipeline (dedupe + strategies + integration hook).
		if ( class_exists( '\\LeadStream\\DTO\\ClickContext' ) && class_exists( '\\LeadStream\\DTO\\TrackingEnvelope' ) ) {
			$bucket   = (int) ( time() / 2 );
			$event_id = hash( 'sha256', 'form_submit|' . $source . '|' . $form_id . '|' . $path . '|' . (string) $bucket );

			$utm = array(
				'utm_source'   => '',
				'utm_medium'   => '',
				'utm_campaign' => '',
				'utm_term'     => '',
				'utm_content'  => '',
			);
			$click_ids = array(
				'gclid'   => '',
				'fbclid'  => '',
				'msclkid' => '',
				'ttclid'  => '',
			);
			$device = array(
				'client_language'  => '',
				'device_type'      => '',
				'viewport_w'       => 0,
				'viewport_h'       => 0,
				'time_to_click_ms' => 0,
				'landing_page'     => '',
			);
			$element = array(
				'element_type'  => 'form',
				'element_class' => '',
				'element_id'    => (string) $form_id,
				'original'      => (string) $form_name,
			);

			$ctx = new \LeadStream\DTO\ClickContext(
				new \DateTimeImmutable( 'now' ),
				'form',
				substr( (string) ( $source . ':' . $form_id ), 0, 255 ),
				$params['page_url'],
				$params['page_url'],
				'',
				(string) $params['referrer'],
				'form',
				(string) $source,
				(string) $params['ua'],
				substr( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ), 0, 45 ),
				(int) get_current_user_id(),
				is_user_logged_in(),
				$utm,
				$click_ids,
				$device,
				$element,
				$event_id,
				'',
				'',
				0,
				0
			);

			$envelope = new \LeadStream\DTO\TrackingEnvelope(
				$ctx,
				'form_submit',
				$event_id,
				0,
				array(
					'source'    => (string) $source,
					'form_id'   => (string) $form_id,
					'form_name' => (string) $form_name,
					'path'      => (string) $path,
				)
			);

			if ( class_exists( '\\LeadStream\\Pipeline\\TrackingPipelineRunner' ) ) {
				\LeadStream\Pipeline\TrackingPipelineRunner::run( $envelope, 2 );
			}
		}
	}
}
