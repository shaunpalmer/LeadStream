<?php

declare(strict_types=1);

namespace LeadStream\Utils;

final class PhoneNumberNormalizer {
	/**
	 * Best-effort phone normalization.
	 * - Always returns digits-only.
	 * - Returns E.164 when confidently derivable (defaults to NZ +64 for local 0-prefixed numbers).
	 *
	 * @return array{digits:string,e164:string}
	 */
	public static function normalize( string $raw, string $default_country_code = '+64' ): array {
		$raw_trim = trim( $raw );
		$has_plus = '' !== $raw_trim && '+' === $raw_trim[0];

		$digits = preg_replace( '/\D+/', '', $raw_trim );
		$digits = is_string( $digits ) ? $digits : '';
		if ( '' === $digits ) {
			return array( 'digits' => '', 'e164' => '' );
		}

		$default_country_code = '' !== $default_country_code ? $default_country_code : '+64';
		$default_cc_digits    = ltrim( $default_country_code, '+' );

		// If user already provided +, trust it.
		if ( $has_plus ) {
			return array( 'digits' => $digits, 'e164' => '+' . $digits );
		}

		// International dialing prefix.
		if ( 0 === strpos( $digits, '00' ) && strlen( $digits ) > 2 ) {
			$rest = substr( $digits, 2 );
			return array( 'digits' => $rest, 'e164' => '+' . $rest );
		}

		// Already country-code prefixed.
		if ( '' !== $default_cc_digits && 0 === strpos( $digits, $default_cc_digits ) ) {
			return array( 'digits' => $digits, 'e164' => '+' . $digits );
		}

		// NZ local convention: leading 0 trunk prefix.
		if ( '+64' === $default_country_code && '0' === $digits[0] && strlen( $digits ) > 1 ) {
			return array( 'digits' => $digits, 'e164' => '+64' . substr( $digits, 1 ) );
		}

		// Unknown; keep digits-only and leave e164 blank to avoid incorrect normalization.
		return array( 'digits' => $digits, 'e164' => '' );
	}
}
