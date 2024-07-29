<?php
/**
 * DooFinder main helper methods.
 *
 * @package Doofinder\WP\Helpers
 */

namespace Doofinder\WP;

/**
 * Helpers Class.
 */
class Helpers {

	/**
	 * Returns `true` if we are currently in debug mode, `false` otherwise.
	 *
	 * @return bool
	 */
	public static function is_debug_mode() {
		return WP_DEBUG && ! Settings::get_disable_debug_mode();
	}

	/**
	 * This function extracts the language code from a language country code.
	 * Example: 'en-US' or 'en_US' is converted to to ISO 639-1 language code
	 * en.
	 *
	 * @param string $language_code The language code separated with a hyphen.
	 *
	 * @return string The language code
	 */
	public static function get_language_from_locale( $language_code ) {
		$language_code = str_replace( '-', '_', $language_code );
		return explode( '_', $language_code )[0];
	}

	/**
	 * Recursive in_array() for multidimensional arrays.
	 *
	 * @param string $needle String to find.
	 * @param array  $haystack The array where we will look for the $needle.
	 * @param bool   $strict (optional) Enforce an strict comparison instead of a loose one. `false` by default.
	 *
	 * @return bool
	 */
	public static function in_array_r( $needle, $haystack, $strict = false ) {

		foreach ( $haystack as $item ) {
			// phpcs:ignore Universal.Operators.StrictComparisons
			if ( ( $strict ? $item === $needle : $item == $needle ) || ( is_array( $item ) && self::in_array_r( $needle, $item, $strict ) ) ) {

				return true;
			}
		}
		return false;
	}

	/**
	 * This function converts a locale code (language and country code) from
	 * 'en-US' to 'en_US' format.
	 *
	 * @param string $locale_code Locale with a hyphen.
	 *
	 * @return string The formatted locale code, with an underscore.
	 */
	public static function format_locale_to_underscore( $locale_code ) {
		return str_replace( '-', '_', $locale_code );
	}

	/**
	 * This function converts a locale code (language and country code) from
	 * 'en_US' to 'en-US' format used by Live Layer.
	 *
	 * @param string $locale_code Locale with an underscore.
	 *
	 * @return string The formatted locale code with a hyphen.
	 */
	public static function format_locale_to_hyphen( $locale_code ) {
		return str_replace( '_', '-', $locale_code );
	}

	/**
	 * Obtains the region from a given DooFinder host.
	 *
	 * @param string $host WordPress website host.
	 *
	 * @return string The region identifier (eu1 or us1).
	 */
	public static function get_region_from_host( $host ) {
		$re = '/:\/\/(?<region>[a-z]{2}[0-9])-.*/m';
		preg_match_all( $re, $host, $matches, PREG_SET_ORDER, 0 );

		if ( ! empty( $matches ) && array_key_exists( 'region', $matches[0] ) ) {
			return $matches[0]['region'];
		}
		return false;
	}
}
