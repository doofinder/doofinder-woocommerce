<?php
/**
 * DooFinder I18n_Handler methods.
 *
 * @package Doofinder\WP\Multilanguage
 */

namespace Doofinder\WP\Multilanguage;

/**
 * Interface with useful functions to handle the Multi-language.
 */
interface I18n_Handler {

	/**
	 * Retrieve the code of the default language of the site.
	 *
	 * @since 1.0.0
	 * @return array Default language information.
	 */
	public function get_default_language();

	/**
	 * Retrieve the code of the currently active language.
	 *
	 * @since 1.0.0
	 * @return array Current language information.
	 */
	public function get_current_language();

	/**
	 * Retrieve the home URL for the given language.
	 *
	 * @since 1.0.0
	 * @param string $language Language to retrieve home URL for.
	 * @return string Home URL.
	 */
	public function get_home_url( $language );

	/**
	 * Get the language code corresponding to a given locale.
	 *
	 * Iterates over the list of configured languages and returns the language code
	 * that matches the provided locale. If no match is found, the original locale is returned.
	 *
	 * @since 2.9.0
	 * @param string $locale The locale string with underscore to search for (e.g. 'en_US', 'es_ES', 'zh_CN').
	 * @return string The matching language code (e.g. 'en', 'es', 'zh-hans'), or an empty string if not found.
	 */
	public function get_lang_code_by_locale( $locale );

	/**
	 * Retrieve the list of available languages. That should be an array containing following:
	 * name    - Full name of the language.
	 * code    - A language code (typically two letter, e.g. "en").
	 * active  - True if the language is currently active, false otherwise.
	 * default - True if that's the default language, false otherwise.
	 * prefix  - Prefix for settings, links, etc. Typically language code, empty string for default language.
	 *
	 * @return array A list of available languages.
	 */
	public function get_languages();


	/**
	 * Get all formatted languages.
	 *
	 * @return array[string]string List of all languages.
	 */
	public function get_formatted_languages();

	/**
	 * Retrieve the name of the WordPress option
	 * for the current languages.
	 *
	 * Some fields in Doofinder settings will have different values,
	 * depending on language.
	 *
	 * @param string $base Country code or language.
	 *
	 * @return string
	 */
	public function get_option_name( $base );
}
