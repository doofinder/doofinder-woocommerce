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
	abstract public function get_lang_code_by_locale( $locale );

	/**
	 * Get the language code of a post (e.g. a product).
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string Language code, or empty string if the post has no language.
	 */
	abstract public function get_post_language( $post_id );

	/**
	 * Switch the language of the current request, so queries return content in it.
	 *
	 * @param string $lang_code Language code.
	 *
	 * @return void
	 */
	abstract public function switch_language( $lang_code );

	/**
	 * Switch the current request to "all languages", so queries are not filtered by language.
	 *
	 * @return void
	 */
	abstract public function switch_to_all_languages();

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
