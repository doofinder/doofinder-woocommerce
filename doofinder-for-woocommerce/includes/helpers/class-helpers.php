<?php
/**
 * DooFinder Helpers methods.
 *
 * @package Doofinder\WP\Helpers
 */

namespace Doofinder\WP\Helpers;

use Doofinder\WP\Multilanguage;

/**
 * Adds general helper methods.
 */
class Helpers {

	/**
	 * Checks if string contains https:// if not then add it.
	 *
	 * @param string $host Name of the host (e.g. wordpress.org).
	 */
	public static function prepare_host( $host ) {
		if ( $host ) {
			$has_http = preg_match( '@^http[s]?:\/\/@', $host );
			$host     = ! $has_http ? 'https://' . $host : $host;
		}
		return $host;
	}

	/**
	 * Retrieves the current memory usage or the peak memory usage.
	 *
	 * This function allows you to get the memory usage in bytes or megabytes, and can also
	 * return the peak memory usage instead of the current usage.
	 *
	 * @param bool $real         (Optional) If set to `true`, gets the real memory usage allocated by the operating system.
	 *                            If set to `false`, gets the memory usage reported by PHP's emalloc.
	 *                            Default is `false`.
	 * @param bool $peak         (Optional) If set to `true`, gets the peak memory usage.
	 *                            If set to `false`, gets the current memory usage.
	 *                            Default is `false`.
	 * @param bool $in_megabytes (Optional) If set to `true`, the function will return the value in megabytes (MB).
	 *                            If set to `false`, it will return the value in bytes.
	 *                            Default is `false`.
	 *
	 * @return string The amount of memory used, followed by the appropriate unit (bytes or MB), and the value in MB in parentheses.
	 */
	public static function get_memory_usage( $real = false, $peak = false, $in_megabytes = false ) {

		$unit     = $in_megabytes ? ' MB' : ' bytes';
		$megabyte = 1048576;

		if ( $peak ) {
			$amount       = $in_megabytes ? round( memory_get_peak_usage( $real ) / $megabyte, 2 ) : number_format( memory_get_peak_usage( $real ), 0, ',', ' ' );
			$amount_in_mb = round( memory_get_peak_usage( $real ) / $megabyte, 2 );
		} else {
			$amount       = $in_megabytes ? round( memory_get_usage( $real ) / $megabyte, 2 ) : number_format( memory_get_usage( $real ), 0, ',', ' ' );
			$amount_in_mb = round( memory_get_usage( $real ) / $megabyte, 2 );
		}

		return $amount . $unit . '  (' . $amount_in_mb . ' MB)';
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
	 * Applies the given locale or language code to the REST API context.
	 *
	 * If the input is a valid locale string (e.g. 'en-US'), it converts it to an underscore format
	 * (e.g. 'en_US'), fetches the corresponding language code using WPML, and applies it by:
	 * - Adding a filter to override the `locale`.
	 * - Triggering the WPML language switch via `do_action( 'wpml_switch_language', $lang_code )`.
	 *
	 * This ensures that REST API requests are handled in the appropriate language context.
	 *
	 * @param string $locale_or_lang_code A locale (e.g. 'en-US' or 'zh-CN') or a language code (e.g. 'en' or 'zh-hans').
	 *
	 * @return string The language code (e.g. 'en') after applying the locale context if needed.
	 */
	public static function apply_locale_to_rest_context( $locale_or_lang_code ) {
		$lang_code     = $locale_or_lang_code;
		$multilanguage = Multilanguage::instance();
		if ( $multilanguage->is_active() && preg_match( '/^[a-z]{2}-[A-Z]{2}$/', $locale_or_lang_code ) ) {
			$locale_underscore = self::format_locale_to_underscore( $locale_or_lang_code );
			$lang_code         = self::format_locale_to_hyphen( $multilanguage->get_lang_code_by_locale( $locale_underscore ) );
			add_filter(
				'locale',
				function () use ( $locale_underscore ) {
					return $locale_underscore;
				}
			);
			/**
			 * Sets the internal WPML language for this request to the specified one if WPML plugin is enabled.
			 *
			 * @since 2.9.0
			 */
			do_action( 'wpml_switch_language', $lang_code );
		}

		return $lang_code;
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
	 * Obtains the region from a given DooFinder host.
	 *
	 * @param string $host WordPress website host.
	 *
	 * @return string The region identifier (eu1, us1 or ap1).
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
