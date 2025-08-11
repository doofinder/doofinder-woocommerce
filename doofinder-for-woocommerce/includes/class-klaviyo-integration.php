<?php
/**
 * DooFinder Klaviyo Integration methods.
 *
 * @package Doofinder\WP\Klaviyo_Integration
 */

namespace Doofinder\WP;

/**
 * Handles the Klaviyo workflow.
 */
class Klaviyo_Integration {

	/**
	 * Singleton of this class.
	 *
	 * @var Klaviyo
	 */
	private static $instance;

	/**
	 * Returns the only instance of Klaviyo.
	 *
	 * @return Klaviyo
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Klaviyo constructor.
	 */
	public function __construct() {
		$this->maybe_enqueue_script();
	}

	/**
	 * Enqueue plugin styles and scripts.
	 *
	 * @since 2.7.1
	 */
	public function maybe_enqueue_script() {
		if ( ! class_exists( 'WooCommerceKlaviyo' ) ) {
			return;
		}

		add_action(
			'wp_enqueue_scripts',
			function () {
				if ( Settings::is_js_layer_enabled() ) {
					wp_enqueue_script(
						'doofinder-integration-klaviyo',
						Doofinder_For_WordPress::plugin_url() . 'assets/js/doofinder-integration-klaviyo.js',
						array( 'kl-identify-browser' ), // To prevent this to be loaded before Klaviyo plugin script.
						Doofinder_For_WordPress::$version,
						true
					);
				}
			}
		);
	}
}
