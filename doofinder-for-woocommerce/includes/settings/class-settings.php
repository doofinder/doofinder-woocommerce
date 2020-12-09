<?php

namespace Doofinder\WC\Settings;

use Doofinder\WC\Settings\Accessors;

use Doofinder\WC\Multilanguage;

defined( 'ABSPATH' ) or die;

/**
 * Standardizes options naming, and allows easy access to saved options.
 *
 * @package Doofinder\WC\Settings
 */
class Settings {
	use Accessors;

	/**
	 * Doofinder options prefix.
	 *
	 * @var string
	 */
	private static $prefix = 'woocommerce_doofinder';

	/**
	 * Generate id for the option. The id will be prefixed with the predefined prefix.
	 * The id can be constructed of few parts (for example part 1 can be a section name, and part 2
	 * can be the name of the option itself, or only the name of the option can be passed, and
	 * part 2 can be ignored).
	 *
	 * @since 1.0.0
	 * @param string $part1  First part of the id (e.g. section name).
	 * @param string $part2  Second part of the id (e.g. option name).
	 * @param string $suffix Suffix to add to option name, typically language code.
	 * @return string Full prefixed option name to store in DB.
	 */
	public static function option_id( $part1, $part2 = '', $suffix = '' ) {
		$id = array( self::$prefix );
		$id[] = $part1;

		if ( $part2 ) {
			$id[] = $part2;
		}

		if ( $suffix ) {
			$id[] = $suffix;
		}

		return implode( '_', $id );
	}

	/**
	 * Shorthand for retrieving Doofinder options. Handles the predefined Doofinder prefix,
	 * and organizing options into sections for readability.
	 *
	 * @since 1.0.0
	 * @param string $part1    First part of the id (e.g. section name).
	 * @param string $part2    Second part of the id (e.g. option name).
	 * @param string $language Language to retrieve option for, defaults to default language.
	 * @return mixed Setting value.
	 */
	public static function get( $part1, $part2 = '', $language = '' ) {
		$multilanguage = Multilanguage::instance();

		// Will get options for a given language only if internationalization is active
		if ( $multilanguage->is_active() ) {
			if ( empty( $language ) ) {
				$language = $multilanguage->get_language_prefix();
			} elseif ($language == 'all') {
				$language = '';
			} else {
				$languages = $multilanguage->get_languages();
				$language  = $languages[ $language ]['prefix'];
			}
		}

		$option = self::option_id( $part1, $part2, $language );
		return self::get_wc_option( $option );
	}

	/**
	 * Retrieve WooCommerce option.
	 * This is essentially a copy of WC_Admin_Settings::get_option, because earlier versions
	 * of WooCommerce don't include the class everywhere, and sometimes it's not present
	 * when reading the options.
	 *
	 * @param string $name Name of the option to retrieve.
	 * @return mixed Option value.
	 */
	public static function get_wc_option( $name ) {
		// Array value
		if ( strstr( $name, '[' ) ) {
			parse_str( $name, $option_array );

			// Option name is first key
			$name = current( array_keys( $option_array ) );

			// Get value
			$option_values = get_option( $name, '' );

			$key = key( $option_array[ $name ] );

			if ( isset( $option_values[ $key ] ) ) {
				$option_value = $option_values[ $key ];
			} else {
				$option_value = null;
			}
		}

		// Single value
		else {
			$option_value = get_option( $name, null );
		}

		return $option_value;
	}
}
