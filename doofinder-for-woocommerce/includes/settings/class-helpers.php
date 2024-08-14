<?php
/**
 * DooFinder Helpers methods.
 *
 * @package Doofinder\WP\Helpers
 */

namespace Doofinder\WP\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds settings helper methods.
 */
trait Helpers {

	/**
	 * Helper function to search two dimensional array.
	 *
	 * @param string $needle The value to search for in the two dimensional array.
	 * @param array  $haystack The two dimensional array in which to search for the value.
	 *
	 * @return bool
	 */
	private function in_2d_array( $needle, $haystack ) {
		foreach ( $haystack as $array ) {
			if ( in_array( $needle, $array, true ) ) {
				return true;
			}
		}

		return false;
	}
}
