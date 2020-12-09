<?php

namespace Doofinder\WC;

use Doofinder\WC\Multilanguage\I18n_Handler;
use Doofinder\WC\Multilanguage\WPML;

class Multilanguage {

	/**
	 * Singleton of this class.
	 *
	 * @var Multilanguage
	 */
	private static $_instance;

	/**
	 * Class handling the internationalization of the plugin. Handler classes represent
	 * internationalization plugins.
	 *
	 * @var I18n_Handler
	 */
	private $handler;

	/* Class operations ***********************************************************/

	/**
	 * Returns the only instance of Multilanguage.
	 *
	 * @since 1.0.0
	 * @return Multilanguage
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Check if any internationalization plugin is active.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $sitepress;

		if ( function_exists( 'icl_object_id' ) && isset( $sitepress ) && $sitepress ) {
			$this->handler = new WPML();
		}
	}

	/**
	 * Transfers function calls to $handler, so we don't have to chain it every time.
	 *
	 * @since 1.0.0
	 * @param string $name      Name of the method called.
	 * @param array  $arguments Parameters.
	 * @return mixed Method return value.
	 */
	public function __call( $name, $arguments ) {
		if ( ! method_exists( $this->handler, $name ) ) {
			throw new \BadMethodCallException( "Method $name doesn't exist in $this->handler or " . __CLASS__ );
		}

		return call_user_func_array( array( $this->handler, $name ), $arguments );
	}

	/* Methods higher level than handler ******************************************/

	/**
	 * Check if internationalization is active.
	 *
	 * @since 1.0.0
	 * @return bool True if internationalization is active, false otherwise.
	 */
	public function is_active() {
		return $this->handler !== null;
	}

	/**
	 * Retrieve the code of the current language.
	 *
	 * @return string Code of the current language, empty string if no internationalization.
	 */
	public function get_language_code() {
		if ( ! $this->is_active() ) {
			return '';
		}

		$language = $this->handler->get_current_language();

		return $language['code'] ?? '';
	}

	/**
	 * Retrieve the prefix of the current language.
	 *
	 * @return string Prefix of the current language, empty string if no internationalization.
	 */
	public function get_language_prefix() {
		if ( ! $this->is_active() ) {
			return '';
		}

		$language = $this->handler->get_current_language();
		return $language['prefix'] ?? '';
	}

	/**
	 * Retrieve html for notice when is mulilang site and no language is selected
	 *
	 * @return string
	 */
	public function get_choose_language_notice($hide_button = true) {
		if ( ! $this->is_active() ) {
			return '';
		}

		if($hide_button) {

			$GLOBALS['hide_save_button']  = true;
		}

		$notice_html = 
			'<div class="notice notice-error"><p>' 
			. __( 'You have a multi-language site. Please choose a language first to configure Doofinder.', 'woocommerce-doofinder' ) 
			. '</p></div>';
		
		return $notice_html;
	}

	/**
	 * Perform a given action for each of the existing languages. If internationalization
	 * is not active, then the action will be performed only once, and with empty language code.
	 * This can be used to register settings for each language, etc.
	 *
	 * @since 1.0.0
	 * @param callable $action Action to perform (a function).
	 */
	public static function for_each( $action ) {
		$multilanguage = self::instance();

		if ( ! $multilanguage->is_active() ) {
			call_user_func_array( $action, array( 'code' => '' ) );
		} else {
			foreach ( $multilanguage->handler->get_languages() as $language ) {
				call_user_func_array( $action, array( 'code' => $language['prefix'] ) );
			}
		}
	}

	/**
	 * Retrieve home URL in the given language. Retrieves basic WP home URL if empty
	 * language is passed (that's the case when for example internationalization is not active).
	 *
	 * @since 1.0.0
	 * @param string $language Language code to retrieve the home URL for.
	 * @return string Home URL.
	 */
	public static function get_home_url( $language = '' ) {
		$multilanguage = self::instance();
		if ( ! $multilanguage->is_active() || empty( $language ) ) {
			return get_bloginfo( 'url' );
		}

		return $multilanguage->handler->get_home_url( $language );
	}

	/* Utilities ******************************************************************/

	/**
	 * Adds a language code as a suffix to the given string, like so:
	 * some_string_en
	 *
	 * @since 1.0.0
	 * @param string $text      Text to add language code to.
	 * @param string $code      Language code to add.
	 * @param string $separator How to separate text from the suffix.
	 * @return string Transformed text.
	 */
	public static function code_suffix( $text, $code = '', $separator = '_' ) {
		if ( empty( $code ) ) {
			return $text;
		}

		return $text . $separator . $code;
	}
}
