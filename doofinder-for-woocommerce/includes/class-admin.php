<?php

namespace Doofinder\WC;

defined( 'ABSPATH' ) or die;

class Admin {

	/**
	 * Singleton of this class.
	 *
	 * @var Admin
	 */
	private static $_instance;

	/**
	 * Returns the only instance of Admin.
	 *
	 * @since 1.0.0
	 * @return Admin
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Admin constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->enqueue_styles();
		$this->add_doofinder_settings();
	}

	/**
	 * Enqueue custom admin panel styles.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_styles() {
		add_action( 'admin_enqueue_scripts', function( $hook ) {
			if ( 'woocommerce_page_wc-settings' === $hook ) {
				wp_enqueue_style(
					'doofinder-for-wc-styles',
					Doofinder_For_WooCommerce::plugin_url() . 'assets/css/admin.css'
				);
			}
		} );
	}

	/**
	 * Add Doofinder settings to the WooCommerce settings page.
	 *
	 * @since 1.0.0
	 */
	private function add_doofinder_settings() {
		add_filter( 'woocommerce_get_settings_pages', function( $settings ) {
			$settings[] = new Settings_Page();
			return $settings;
		} );
	}
}
