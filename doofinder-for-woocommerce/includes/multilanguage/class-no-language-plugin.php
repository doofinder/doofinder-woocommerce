<?php
/**
 * DooFinder WPML methods.
 *
 * @package Doofinder\WP\Multilanguage
 */

namespace Doofinder\WP\Multilanguage;

/**
 * Handles the case where no supported language plugins are active.
 */
class No_Language_Plugin extends Language_Plugin {


	/**
	 * It will always return null since there is only one language, the default one.
	 *
	 * @inheritdoc
	 */
	public function get_languages() {
		return null;
	}

	/**
	 * It will always return null since there is only one language, the default one.
	 *
	 * @inheritdoc
	 */
	public function get_formatted_languages() {
		return null;
	}

	/**
	 * It will always return null since there is only one language, the default one.
	 *
	 * @inheritdoc
	 */
	public function get_active_language() {
		return null;
	}

	/**
	 * It will always return '' since there is only one language, the default one.
	 *
	 * @inheritdoc
	 */
	public function get_current_language() {
		return '';
	}

	/**
	 * It will always return '' since there is only one language, the default one.
	 *
	 * @inheritdoc
	 */
	public function get_default_language() {
		return '';
	}

	/**
	 * It will always return '' since there is only one language, the default one.
	 *
	 * @inheritdoc
	 */
	public function get_base_language() {
		return '';
	}

	/**
	 * It will always return the default get_locale function value.
	 * See https://developer.wordpress.org/reference/functions/get_locale/.
	 *
	 * @inheritdoc
	 */
	public function get_base_locale() {
		return get_locale();
	}

	/**
	 * It will always return the `$locale` received without any transformation.
	 *
	 * @param string $locale The locale string with underscore to search for (e.g. 'en_US', 'es_ES', 'zh_CN').
	 * @inheritdoc
	 */
	public function get_lang_code_by_locale( $locale ) {
		return $locale;
	}

	/**
	 * It will always return the URL of the site, regardless of the language param.
	 * See https://developer.wordpress.org/reference/functions/get_bloginfo/.
	 *
	 * @param string $language Language.
	 *
	 * @inheritdoc
	 */
	public function get_home_url( $language ) {
		return get_bloginfo( 'url' );
	}

	/**
	 * Retrieve the name of the WordPress option
	 * for the current languages.
	 *
	 * Some fields in DooFinder settings will have different values,
	 * depending on language.
	 *
	 * @param string $base Country code or language.
	 *
	 * @return string
	 */
	public function get_option_name( $base ) {
		$language_code = $this->get_active_language();
		if ( ! $language_code ) {
			return $base;
		}

		$base_language = $this->get_base_language();
		if ( $language_code === $base_language ) {
			return $base;
		}

		return "{$base}_{$language_code}";
	}
}
