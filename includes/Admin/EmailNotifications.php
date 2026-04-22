<?php
/**
 * LeadStream Email Notifications
 * Handles sending email notifications for lead submissions
 */

namespace LS\Admin;

defined( 'ABSPATH' ) || exit;

class EmailNotifications {

	/**
	 * Initialize email notifications
	 */
	public static function init() {
		$instance = new self();
		$instance->setup_hooks();
	}

	/**
	 * Setup WordPress hooks
	 */
	private function setup_hooks() {
		// Hook into form submission events with priority 20 (after recording)
		add_action( 'wpforms_process_complete', array( $this, 'handle_wpforms_submission' ), 20, 3 );
		add_action( 'wpcf7_mail_sent', array( $this, 'handle_cf7_submission' ), 20, 1 );
		add_action( 'gform_after_submission', array( $this, 'handle_gravity_submission' ), 20, 2 );
		add_action( 'ninja_forms_after_submission', array( $this, 'handle_ninja_submission' ), 20, 1 );
	}

	/**
	 * Handle WPForms submission
	 *
	 * @param array $fields Form fields
	 * @param array $entry Entry data
	 * @param array $form_data Form data
	 */
	public function handle_wpforms_submission( $fields, $entry, $form_data ) {
		$lead_email = $this->extract_email_from_wpforms( $fields );
		$form_name  = isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : 'Form';
		$this->send_notifications( $lead_email, $form_name, 'WPForms' );
	}

	/**
	 * Handle Contact Form 7 submission
	 *
	 * @param object $contact_form CF7 form object
	 */
	public function handle_cf7_submission( $contact_form ) {
		$submission = \WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$posted_data = $submission->get_posted_data();
		$lead_email  = $this->extract_email_from_cf7( $posted_data );
		$form_name   = method_exists( $contact_form, 'title' ) ? $contact_form->title() : 'Form';
		$this->send_notifications( $lead_email, $form_name, 'Contact Form 7' );
	}

	/**
	 * Handle Gravity Forms submission
	 *
	 * @param array $entry Entry data
	 * @param array $form Form data
	 */
	public function handle_gravity_submission( $entry, $form ) {
		$lead_email = $this->extract_email_from_gravity( $entry, $form );
		$form_name  = isset( $form['title'] ) ? $form['title'] : 'Form';
		$this->send_notifications( $lead_email, $form_name, 'Gravity Forms' );
	}

	/**
	 * Handle Ninja Forms submission
	 *
	 * @param array $form_data Form data
	 */
	public function handle_ninja_submission( $form_data ) {
		$lead_email = $this->extract_email_from_ninja( $form_data );
		$form_name  = isset( $form_data['settings']['title'] ) ? $form_data['settings']['title'] : 'Form';
		$this->send_notifications( $lead_email, $form_name, 'Ninja Forms' );
	}

	/**
	 * Extract email from WPForms fields
	 *
	 * @param array $fields Form fields
	 * @return string Email address or empty string
	 */
	private function extract_email_from_wpforms( $fields ) {
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return '';
		}

		foreach ( $fields as $field ) {
			if ( isset( $field['type'] ) && 'email' === $field['type'] && ! empty( $field['value'] ) ) {
				return sanitize_email( $field['value'] );
			}
		}

		return '';
	}

	/**
	 * Extract email from Contact Form 7 posted data
	 *
	 * @param array $posted_data Posted form data
	 * @return string Email address or empty string
	 */
	private function extract_email_from_cf7( $posted_data ) {
		if ( empty( $posted_data ) || ! is_array( $posted_data ) ) {
			return '';
		}

		// Check common email field names
		$email_fields = array( 'your-email', 'email', 'Email', 'user-email', 'contact-email' );
		foreach ( $email_fields as $field_name ) {
			if ( isset( $posted_data[ $field_name ] ) && is_email( $posted_data[ $field_name ] ) ) {
				return sanitize_email( $posted_data[ $field_name ] );
			}
		}

		// Fallback: search all fields for email
		foreach ( $posted_data as $value ) {
			if ( is_string( $value ) && is_email( $value ) ) {
				return sanitize_email( $value );
			}
		}

		return '';
	}

	/**
	 * Extract email from Gravity Forms entry
	 *
	 * @param array $entry Entry data
	 * @param array $form Form data
	 * @return string Email address or empty string
	 */
	private function extract_email_from_gravity( $entry, $form ) {
		if ( empty( $entry ) || ! is_array( $entry ) || empty( $form['fields'] ) ) {
			return '';
		}

		foreach ( $form['fields'] as $field ) {
			if ( isset( $field['type'] ) && 'email' === $field['type'] && isset( $field['id'] ) ) {
				$field_id = (string) $field['id'];
				if ( ! empty( $entry[ $field_id ] ) && is_email( $entry[ $field_id ] ) ) {
					return sanitize_email( $entry[ $field_id ] );
				}
			}
		}

		return '';
	}

	/**
	 * Extract email from Ninja Forms data
	 *
	 * @param array $form_data Form data
	 * @return string Email address or empty string
	 */
	private function extract_email_from_ninja( $form_data ) {
		if ( empty( $form_data['fields'] ) || ! is_array( $form_data['fields'] ) ) {
			return '';
		}

		foreach ( $form_data['fields'] as $field ) {
			if ( isset( $field['type'] ) && 'email' === $field['type'] && ! empty( $field['value'] ) ) {
				return sanitize_email( $field['value'] );
			}
		}

		return '';
	}

	/**
	 * Send notification emails
	 *
	 * @param string $lead_email Lead email address
	 * @param string $form_name Form name
	 * @param string $form_source Form source (WPForms, CF7, etc)
	 */
	private function send_notifications( $lead_email, $form_name, $form_source ) {
		// Generate unique key for idempotency (prevent duplicate sends within 5 seconds)
		$idempotency_key = md5( $lead_email . '|' . $form_name . '|' . floor( time() / 5 ) );
		if ( isset( $GLOBALS['__LS_EMAIL_SENT__'][ $idempotency_key ] ) ) {
			return; // Already sent
		}
		$GLOBALS['__LS_EMAIL_SENT__'][ $idempotency_key ] = true;

		// Send admin notification
		$this->send_admin_notification( $lead_email, $form_name, $form_source );

		// Send lead auto-reply (only if we have their email)
		if ( ! empty( $lead_email ) && is_email( $lead_email ) ) {
			$this->send_lead_autoreply( $lead_email, $form_name );
		}
	}

	/**
	 * Send admin notification email
	 *
	 * @param string $lead_email Lead email address
	 * @param string $form_name Form name
	 * @param string $form_source Form source
	 */
	private function send_admin_notification( $lead_email, $form_name, $form_source ) {
		// Check if admin notifications are enabled
		if ( ! get_option( 'leadstream_enable_admin_notification', false ) ) {
			return;
		}

		// Get recipient email (default to admin email if not set)
		$to = get_option( 'leadstream_admin_notification_email', get_option( 'admin_email' ) );
		if ( empty( $to ) || ! is_email( $to ) ) {
			return;
		}

		// Get from name and email
		$from_name  = get_option( 'leadstream_email_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'leadstream_email_from_email', get_option( 'admin_email' ) );

		// Build subject
		$subject = sprintf( '[%s] New Lead Submission from %s', get_bloginfo( 'name' ), $form_name );

		// Build message
		$message  = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
		$message .= '<h2 style="color: #0073aa;">New Lead Notification</h2>';
		$message .= '<p>A new lead has been submitted via <strong>' . esc_html( $form_name ) . '</strong> (' . esc_html( $form_source ) . ').</p>';

		if ( ! empty( $lead_email ) ) {
			$message .= '<p><strong>Lead Email:</strong> <a href="mailto:' . esc_attr( $lead_email ) . '">' . esc_html( $lead_email ) . '</a></p>';
		} else {
			$message .= '<p><em>No email address provided by the lead.</em></p>';
		}

		$message .= '<p><strong>Form:</strong> ' . esc_html( $form_name ) . '<br>';
		$message .= '<strong>Source:</strong> ' . esc_html( $form_source ) . '<br>';
		$message .= '<strong>Time:</strong> ' . current_time( 'mysql' ) . '</p>';
		$message .= '<hr style="border: 1px solid #ddd; margin: 20px 0;">';
		$message .= '<p style="font-size: 12px; color: #666;">This notification was sent by LeadStream on ' . get_bloginfo( 'name' ) . '</p>';
		$message .= '</body></html>';

		// Set headers
		$headers = array();
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		if ( ! empty( $from_name ) && ! empty( $from_email ) && is_email( $from_email ) ) {
			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
		}

		// Send email
		wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Send auto-reply email to lead
	 *
	 * @param string $lead_email Lead email address
	 * @param string $form_name Form name
	 */
	private function send_lead_autoreply( $lead_email, $form_name ) {
		// Check if auto-reply is enabled
		if ( ! get_option( 'leadstream_enable_lead_autoreply', false ) ) {
			return;
		}

		if ( empty( $lead_email ) || ! is_email( $lead_email ) ) {
			return;
		}

		// Get from name and email
		$from_name  = get_option( 'leadstream_email_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'leadstream_email_from_email', get_option( 'admin_email' ) );

		// Get subject and message
		$subject = get_option( 'leadstream_autoreply_subject', 'Thank you for your submission' );
		$html_message = get_option(
			'leadstream_autoreply_message',
			'<p>Thank you for contacting us. We have received your submission and will get back to you soon.</p>'
		);

		// Build full HTML message
		$message  = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
		$message .= $html_message;
		$message .= '<hr style="border: 1px solid #ddd; margin: 20px 0;">';
		$message .= '<p style="font-size: 12px; color: #666;">This is an automated message from ' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
		$message .= '</body></html>';

		// Set headers
		$headers = array();
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		if ( ! empty( $from_name ) && ! empty( $from_email ) && is_email( $from_email ) ) {
			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
		}

		// Send email
		wp_mail( $lead_email, $subject, $message, $headers );
	}
}
