<?php
/**
 * DooFinder Script (formerly JS_Layer) methods.
 *
 * @package Doofinder\WP\Script
 */

namespace Doofinder\WP;

use Doofinder\WP\Helpers\Helpers;

use Doofinder\WP\Multilanguage\Multilanguage;

/**
 * JS_Layer Class.
 */
class JS_Layer {


	/**
	 * Singleton instance of this class.
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 *
	 * Log object.
	 *
	 * @var Log
	 */
	private $log;

	/**
	 * Retrieve (or create, if one does not exist) a singleton
	 * instance of this class.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * JS_Layer constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		if ( ! Settings::is_js_layer_enabled() || '' === Settings::get_js_layer() ) {
			return;
		}

		$this->insert_js_layer();
	}

	/**
	 * Insert the code of the JS Layer to HTML.
	 */
	private function insert_js_layer() {
		add_action(
			'wp_footer',
			function () {
				$this->insert_js_layer_from_options();
			}
		);
	}

	/**
	 * Output JS Layer script pasted by the user in the options.
	 */
	private function insert_js_layer_from_options() {

		$multilanguage = Multilanguage::instance();
		$lang          = Helpers::format_locale_to_underscore( $multilanguage->get_current_language() );
		$layer         = Settings::get_js_layer( $lang );

		/*
		TODO: When all the customer have migrated to the single script
		this one should be adapted too.
		*/
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'local' && defined( 'DF_SEARCH_HOST' ) && defined( 'DF_LAYER_HOST' ) ) {
			$local_constants = "<script>
    // FOR DEVELOPMENT PURPOSES ONLY!!!
    var __DF_DEBUG_MODE__ = true;
    var __DF_SEARCH_SERVER__ = '" . DF_SEARCH_HOST . "';
    var __DF_LAYER_SERVER__ = '" . DF_LAYER_HOST . "';
    var __DF_CDN_PREFIX__ =  '" . DF_LAYER_HOST . "/assets';";
			$layer           = str_replace( '<script>', $local_constants, $layer );
			$layer           = str_replace( 'https://cdn.doofinder.com/livelayer/1/js/loader.min.js', DF_LAYER_HOST . '/assets/js/loader.js', $layer );
		}

		echo $layer; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
