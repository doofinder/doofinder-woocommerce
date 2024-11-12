<?php
/**
 * DooFinder config. Allows to view relevant data like the Installation ID, Search Engines, etc. accessing the URL.
 *
 * @package Doofinder\WP\Config
 */

namespace Doofinder\WP;

use Doofinder\WP\Multilanguage;
use Doofinder\WP\Settings;
use Doofinder\WP\Setup_Wizard;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Config Class.
 */
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
		$class  = __CLASS__;
		$config = new $class();

		register_rest_route(
			'doofinder/v1',
			'/config',
			array(
				'methods'             => 'GET',
				'callback'            => array( $config, 'generate' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Prepare the Doofinder configuration feed.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wp_version;
		$multilanguage = Multilanguage::instance();

		if ( $multilanguage->is_active() ) {
			$configuration = $this->get_multilanguage_configuration();
		} else {
			$configuration = $this->get_single_language_configuration();
		}

		$config = array(
			'platform' => array(
				'name'    => 'WordPress',
				'version' => $wp_version,
			),
			'module'   => array(
				'configuration' => $configuration,
				'options'       => array(
					'language' => array_map( 'strtoupper', $this->get_languages() ),
				),
				'version'       => Doofinder_For_WordPress::$version,
				'wizard'        => $this->get_wizard_status(),
			),
		);

		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$config['platform'] = array(
				'name'              => 'WooCommerce',
				'version'           => \WC()->version,
				'wordpress_version' => $wp_version,
			);
		}

		$this->config = $config;
	}

	/**
	 * Generates the REST Response from the config data.
	 *
	 * @return \WP_REST_Response
	 */
	public function generate() {
		return new WP_REST_Response( $this->config );
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

			$configuration[ $code_label ]['language'] = $code_label;

			if ( function_exists( 'get_woocommerce_currency' ) ) {
				$configuration[ $code_label ]['currency'] = get_woocommerce_currency();
			}
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

		$currency                        = is_plugin_active( 'woocommerce/woocommerce.php' ) ? get_woocommerce_currency() : 'EUR';
		$configuration                   = array();
		$configuration[ $language_code ] = array(
			'currency' => $currency,
			'language' => $language_code,
		);

		return $configuration;
	}

	/**
	 * Get (capitalized) language code from WP locale.
	 */
	private function get_locale_language_code() {
		$locale = get_locale();
		$parts  = preg_split( '/_/', $locale );

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
		$wizard = get_option( Setup_Wizard::$wizard_status );
		if ( $wizard ) {
			return $wizard;
		}

		return Settings::is_configuration_complete()
			? Setup_Wizard::$wizard_status_finished
			: Setup_Wizard::$wizard_status_pending;
	}
}
