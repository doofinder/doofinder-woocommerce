<?php
/**
 * DooFinder Platform_Confirmation methods.
 *
 * @package Doofinder\WP\Platform
 */

namespace Doofinder\WP;

use Doofinder\WP\Multilanguage\Language_Plugin;
use Doofinder\WP\Multilanguage\Multilanguage;

/**
 * Handles the platform decision process.
 */
class Platform_Confirmation {


	/**
	 * Instance of the class handling currently active
	 * multilanguage plugin.
	 *
	 * @var Language_Plugin
	 */
	private $language;

	/**
	 * Version of current WordPress install.
	 *
	 * @var string
	 */
	private $wp_version;

	/**
	 * List of language codes that are available on the site.
	 *
	 * @var string[]
	 */
	private $languages = array();

	/**
	 * Register the feed printing the JSON with site configuration.
	 * It will be available under:
	 * /feed/doofinder-for-wp-confirmation
	 */
	public static function register() {
		add_feed(
			'doofinder-for-wp-confirmation',
			function () {
				$confirmation = new self();
				$confirmation->generate();
			}
		);
	}

	/**
	 * Platform_Confirmation constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->language   = Multilanguage::instance();
		$this->wp_version = get_bloginfo( 'version' );
		$this->prepare_languages();
	}

	/**
	 * Generate (print out) the configuration.
	 */
	private function generate() {
		header( 'Content-Type: application/json' );
		$config = array(
			'platform' => array(
				'name'    => 'WordPress',
				'version' => $this->wp_version,
			),
			'module'   => array(
				'options' => array(
					'language' => $this->languages,
				),

				'version' => Doofinder_For_WordPress::$version,
			),
		);
		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$config['platform'] = array(
				'name'    => 'WooCommerce',
				'version' => \WC()->version,
			);
		}
		echo wp_json_encode( $config );
	}

	/**
	 * Generate the list of languages the blog supports.
	 */
	private function prepare_languages() {
		if ( $this->language->get_languages() ) {
			$this->languages_from_plugins();

			return;
		}

		$this->language_from_locale();
	}

	/**
	 * Figure out what the current language is based on locale.
	 *
	 * This will be used if there's no multilanguage plugin installed.
	 */
	private function language_from_locale() {
		$locale = get_locale();

		$is_locale_correct = preg_match( '/([a-z]{2})_.*/', $locale, $matches );
		if ( ! $is_locale_correct ) {
			return;
		}

		$this->languages[] = strtoupper( $matches[1] );
	}

	/**
	 * Get the list of languages from currently active multilanguage plugin.
	 */
	private function languages_from_plugins() {
		foreach ( $this->language->get_languages() as $language_code => $language_name ) {
			$this->languages[] = strtoupper( $language_code );
		}
	}
}
