<?php

namespace Doofinder\WC\Multilanguage;

abstract class Language_Plugin {

	/**
	 * Get all languages.
	 *
	 * @return array[string]string List of all languages.
	 */
	abstract public function get_languages();

	/**
	 * Get all formatted languages.
	 *
	 * @return array[string]string List of all languages.
	 */
	abstract public function get_formatted_languages();

	/**
	 * Get active language code.
	 *
	 * @return string Lang code of current selected language.
	 */
	abstract public function get_active_language();

	/**
	 * Get active language code.
	 *
	 * @return string Lang code of current selected language.
	 */
	abstract public function get_current_language();

	/**
	 * Retrieve the base language of the site.
	 *
	 * This is important because the behavior of the site (e.g. language-specific
	 * option names) should be the same as if there was no multilanguage plugin
	 * installed.
	 *
	 * @return string Lang code of the base (primary) language of the site.
	 */
	abstract public function get_default_language();

	/**
	 * Retrieve the base language of the site.
	 *
	 * This is important because the behavior of the site (e.g. language-specific
	 * option names) should be the same as if there was no multilanguage plugin
	 * installed.
	 *
	 * @return string Lang code of the base (primary) language of the site.
	 */
	abstract public function get_base_language();

	/**
	 * Get all posts ids of a given language.
	 *
	 * @param string $language_code
	 * @param int    $ids_greater_than
	 * @param string $post_type
	 * @param int    $number_of_posts
	 *
	 * @return int[] List of ids.
	 */
	abstract public function get_posts_ids( $language_code, $post_type, $ids_greater_than, $number_of_posts);

	/**
	 * Retrieve the name of the wordpress option
	 * for the current languages.
	 *
	 * Some fields in Doofinder settings will have different values,
	 * depending on language.
	 *
	 * @param string $base
	 *
	 * @return string
	 */
	// public function get_option_name( $base ) {
	// 	$language_code = $this->get_active_language();
	// 	if ( ! $language_code ) {
	// 		return $base;
	// 	}

	// 	$base_language = $this->get_base_language();
	// 	if ( $language_code === $base_language ) {
	// 		return $base;
	// 	}

	// 	return "{$base}_{$language_code}";
	// }

	/**
	 * Retrieve the name of the wordpress option
	 * for the current languages.
	 *
	 * Some fields in Doofinder settings will have different values,
	 * depending on language.
	 *
	 * @param string $base
	 *
	 * @return string
	 */
	//abstract public function get_option_name( $base );
}
