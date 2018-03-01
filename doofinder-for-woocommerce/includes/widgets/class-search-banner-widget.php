<?php

namespace Doofinder\WC\Widgets;

defined( 'ABSPATH' ) or die();

class Search_Banner_Widget extends \WP_Widget {

	public function __construct() {
		parent::__construct(
			'doofinder-for-woocommerce-search-banner',
			'Doofinder Search Banner',
			array(
				'description' => 'Displays banners from Doofinder search. Use it in the search results page if you don\'t like the default location.',
			)
		);
	}

	/**
	 * Output the widget on the frontend.
	 *
	 * This is done via action because banner data comes as a part of API response, which will
	 * be done only once. Therefore a class that has access to results can (and should) hook into this
	 * action to generate output.
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		// Banner is only displayed sometimes, and we don't want to add widget wrapper
		// in case there is no banner, because that adds additional padding / space
		// in the widget sidebar.
		ob_start();
		do_action( 'doofinder_for_woocommerce_search_banner_widget' );
		$banner_html = ob_get_clean();

		if ( empty( trim( $banner_html ) ) ) {
			return;
		}

		echo $args['before_widget'];
		echo $banner_html;
		echo $args['after_widget'];
	}
}
