<?php

namespace Doofinder\WC;

use Doofinder\WC\Settings\Settings;
use Doofinder\WC\Setup_Wizard;

defined( 'ABSPATH' ) or die;

class Config {

	/**
	 * Plugin configuration that Doofinder website will read.
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Register the feed with WordPress.
	 * Necessary for Doofinder website to access plugin configuration.
	 *
	 * @since 1.0.0
	 */
	public static function register() {
		$class = __CLASS__;
		add_feed( 'doofinder-config', function() use ( $class ) {
			$feed = new $class();
			$feed->generate();
		} );
	}

	/**
	 * Prepare the Doofinder configuration feed.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$multilanguage = Multilanguage::instance();

		if ( $multilanguage->is_active() ) {
			$configuration = $this->get_multilanguage_configuration();
		} else {
			$configuration = $this->get_single_language_configuration();
		}

		$this->config = array(
			'platform' => array(
				'name' => 'WooCommerce',
				'version' => \WC()->version,
			),

			'module' => array(
				'configuration' => $configuration,

				'options' => array(
					'language' => array_map( 'strtoupper', $this->get_languages() ),
				),

				'version' => Doofinder_For_WooCommerce::$version,

				'wizard' => $this->get_wizard_status(),
			),
		);
	}

	/**
	 * Output the feed.
	 */
	public function generate() {
		header( 'Content-Type: application/json' );
		echo json_encode( $this->config );
	}

	/**
	 * Get module.configuration part of the feed for the multilanguage installations.
	 *
	 * @return array
	 */
	private function get_multilanguage_configuration() {
		$multilanguage = Multilanguage::instance();
		$configuration = array();

		$languages = $multilanguage->get_languages();
		foreach ( $languages as $code => $language ) {
			$code_label = strtoupper( $code );
			$secured    = ( 'yes' === Settings::get( 'feed', 'password_protected', $code ) );

			$configuration[ $code_label ] = array(
				'currency' => get_woocommerce_currency(),
				'language' => $code_label,
				'feed'     => $multilanguage->get_feed_link( 'doofinder', $code ),
				'secured'  => $secured
			);
		}

		return $configuration;
	}

	/**
	 * Get module.configuration part of the feed for installations without internationalization.
	 *
	 * @return array
	 */
	private function get_single_language_configuration() {
		$language_code = $this->get_locale_language_code();

		$configuration = array();
		$secured       = ( 'yes' === Settings::get( 'feed', 'password_protected' ) );
		$configuration[ $language_code ] = array(
			'currency' => get_woocommerce_currency(),
			'language' => $language_code,
			'feed'     => get_feed_link( 'doofinder' ),
			'secured'  => $secured
		);

		return $configuration;
	}

	/**
	 * Get (capitalized) language code from WP locale.
	 */
	private function get_locale_language_code() {
		$locale = get_locale();
		$parts = preg_split( '/_/', $locale );

		return strtoupper( $parts[0] );
	}

	/**
	 * Get the list of languages.
	 * Comes from internationalization plugin if internationalization is available,
	 * and from WP locale otherwise.
	 */
	private function get_languages() {
		$multilanguage = Multilanguage::instance();
		if ( $multilanguage->is_active() ) {
			return array_keys( $multilanguage->get_languages() );
		}

		return array( $this->get_locale_language_code() );
	}

	/**
	 * Get wizard status for configuration
	 *
	 * @return string
	 */
	private function get_wizard_status() {
		$wizard = get_option(Setup_Wizard::$wizard_status);
		if ( $wizard ) {
			return $wizard;
		}

		return Settings::is_configuration_complete()
			? Setup_Wizard::$wizard_status_finished
			: Setup_Wizard::$wizard_status_pending;
	}
}
