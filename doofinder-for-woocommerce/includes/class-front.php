<?php

namespace Doofinder\WC;

use Doofinder\WC\Settings\Settings;

defined( 'ABSPATH' ) or die;

class Front {

	/**
	 * Singleton of this class.
	 *
	 * @var Front
	 */
	private static $_instance;

	/**
	 * Returns the only instance of Front.
	 *
	 * @since 1.0.0
	 * @return Front
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
		$this->add_doofinder_layer_code();
		$this->add_internal_search();
	}

	/**
	 * Print the Doofinder Layer code in the page footer.
	 *
	 * @since 1.0.0
	 */
	private function add_doofinder_layer_code() {
		add_action( 'wp_footer', function() {
			$script = Settings::get( 'layer', 'code' );

			if ( 'yes' === Settings::get( 'layer', 'enabled' ) && ! empty( $script ) ) {
				echo stripslashes( $script );
			}
		} );
	}

	/**
	 * Hook into WooCommerce search, call Doofinder and modify WC search
	 * with Doofinder results.
	 *
	 * @since 1.0.0
	 */
	private function add_internal_search() {
		add_filter( 'posts_pre_query', function( $posts, $query ) {
			global $wp_query, $skip_internal_search;

			/*
			 * Only use Internal Search on WooCommerce shop pages.
			 * Only use it for the main product query.git
			 * $skip_internal_search is a flag that prevents firing this filter in nested
			 * queries.
			 */
			if ( ! is_shop() || 'product' !== $query->query['post_type'] || true === $skip_internal_search ) {
				return null;
			}

			$search = new Internal_Search();

			// Only use Internal Search if it's enabled and keys are present
			if ( ! $search->is_enabled() ) {
				return null;
			}

			$results = $search->search( $wp_query->query_vars );
			if ( ! $results ) {
				return null;
			}

			/*
			 * Returning custom array of post IDs prevents WP_Query from performing its own
			 * database query, therefore it is unable to figure out pagination parameters on its own,
			 * and we need to set them up manually.
			 */
			$wp_query->found_posts = $results['found_posts'];
			$wp_query->max_num_pages = $results['max_num_pages'];

			return $results['ids'];
		}, 10, 2 );
	}
}
