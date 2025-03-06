<?php
/**
 * DooFinder Klaviyo Integration methods.
 *
 * @package Doofinder\WP\Klaviyo_Integration
 */

namespace Doofinder\WP;

use WP_Http;

/**
 * Handles the add to cart workflow.
 */
class Klaviyo_Integration {

	const ACTION_NAME = 'doofinder_ajax_add_to_cart';

	/**
	 * Singleton of this class.
	 *
	 * @var Add_To_Cart
	 */
	private static $instance;

	/**
	 * Returns the only instance of Add_To_Cart.
	 *
	 * @return Add_To_Cart
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add_To_Cart constructor.
	 */
	public function __construct() {
		$this->enqueue_script();
	}

	/**
	 * Enqueue plugin styles and scripts.
	 *
	 * @since 1.5.23
	 */
	public function enqueue_script() {
		add_action(
			'wp_enqueue_scripts',
			function () {
				if ( Settings::is_js_layer_enabled() ) {
					wp_enqueue_script(
						'doofinder-integration-klaviyo',
						Doofinder_For_WordPress::plugin_url() . 'assets/js/doofinder-integration-klaviyo.js',
						array( 'jquery' ),
						Doofinder_For_WordPress::$version,
						true
					);
				}
			}
		);
	}
}
