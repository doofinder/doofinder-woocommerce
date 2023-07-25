<?php

namespace Doofinder\WP\Settings;

defined( 'ABSPATH' ) or die();

trait Helpers {

	/**
	 * Helper function to search two dimensional array.
	 *
	 * @param string $needle
	 * @param array  $haystack
	 *
	 * @return bool
	 */
	private function in_2d_array( $needle, array $haystack ) {
		foreach ( $haystack as $array ) {
			if ( in_array( $needle, $array ) ) {
				return true;
			}
		}

		return false;
	}
}