<?php
/**
 * Functions Polyfill.
 *
 * This file provides polyfills for PHP 8 functions like `str_contains` and `str_ends_with`
 * to ensure compatibility with older PHP versions.
 *
 * @package Polyfills
 */

if ( ! function_exists( 'str_contains' ) ) {
	/**
	 * Polyfill for str_contains function (introduced in PHP 8).
	 *
	 * This function checks if a given string ($needle) is present within another string ($haystack).
	 * If the needle is an empty string, it returns true.
	 *
	 * @see https://www.php.net/manual/en/function.str-contains.php
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for.
	 *
	 * @return bool True if $needle is found in $haystack, false otherwise.
	 */
	function str_contains( $haystack, $needle ) {
		return 0 === strlen( $needle ) || false !== strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'str_ends_with' ) ) {
	/**
	 * Polyfill for str_ends_with function (introduced in PHP 8).
	 *
	 * This function checks if a given string ($haystack) ends with a specified substring ($needle).
	 * If the needle is an empty string, it returns true.
	 *
	 * @see https://www.php.net/manual/en/function.str-ends-with.php
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to check for at the end.
	 *
	 * @return bool True if $haystack ends with $needle, false otherwise.
	 */
	function str_ends_with( $haystack, $needle ) {
		return 0 === strlen( $needle ) || substr( $haystack, -strlen( $needle ) ) === $needle;
	}
}

if ( ! function_exists( 'str_starts_with' ) ) {
	/**
	 * Polyfill for str_starts_with() function
	 * Available natively in PHP 8.0+
	 *
	 * Checks if a string starts with a given substring
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle The substring to search for.
	 * @throws InvalidArgumentException If either argument is not a string.
	 * @return bool Returns true if haystack starts with needle, false otherwise
	 */
	function str_starts_with( $haystack, $needle ) {
		if ( ! is_string( $haystack ) || ! is_string( $needle ) ) {
			throw new InvalidArgumentException( 'str_starts_with(): Arguments must be strings' );
		}

		// Empty needle always returns true (matches PHP 8.0+ behavior).
		if ( '' === $needle ) {
			return true;
		}

		return 0 === strpos( $haystack, $needle );
	}
}
