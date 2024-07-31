<?php
/**
 * DooFinder Language_Plugin methods.
 *
 * @package Doofinder\WP\Multilanguage
 */

namespace Doofinder\WP\Multilanguage;

/**
 * Include abstracts functions to be used by the different language plugins.
 */
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
	 * Retrieve the home URL for the given language.
	 *
	 * @since 1.0.0
	 * @param string $language Language to retrieve home URL for.
	 * @return string Home URL.
	 */
	abstract public function get_home_url( $language );

	// phpcs:disable
	/**
	 * Retrieve the name of the WordPress option
	 * for the current languages.
	 *
	 * Some fields in Doofinder settings will have different values,
	 * depending on language.
	 *
	 * @param string $base
	 *
	 * @return string
	 */
	// abstract public function get_option_name( $base );
	// phpcs:enable
}
