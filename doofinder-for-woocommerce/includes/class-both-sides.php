<?php

namespace Doofinder\WC;

use Doofinder\WC\Widgets\Search_Banner_Widget;

defined( 'ABSPATH' ) or die();

class Both_Sides {

	/**
	 * Singleton of this class.
	 *
	 * @var Admin
	 */
	private static $_instance;

	/**
	 * Returns the only instance of Admin.
	 *
	 * @since 1.3.0
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
	 * @since 1.3.0
	 */
	public function __construct() {
		$this->register_widgets();
	}

	/**
	 * Register all widgets.
	 *
	 * @since 1.3.0
	 */
	private function register_widgets() {
		add_action( 'widgets_init', function () {
			register_widget( Search_Banner_Widget::class );
		} );
	}
}
