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

		$this->output_page_context_variables();
		echo $layer; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Output global JS variables with page context for the Doofinder layer.
	 *
	 * Sets dfPageType, dfProductId and dfCategoryName so the layer script
	 * can adapt its behaviour to the current page.
	 */
	private function output_page_context_variables() {
		$page_type     = $this->get_page_type();
		$product_id    = $this->get_product_id();
		$category_name = $this->get_category_name();

		echo "<script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '  var dfPageType = ' . wp_json_encode( $page_type ) . ";\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '  var dfProductId = ' . wp_json_encode( $product_id ) . ";\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '  var dfCategoryName = ' . wp_json_encode( $category_name ) . ";\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		echo "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Resolve the page type for the current request.
	 *
	 * @return string
	 */
	private function get_page_type() {
		if ( is_front_page() || is_home() ) {
			return 'home';
		}
		if ( is_search() ) {
			return 'search';
		}
		if ( ! function_exists( 'is_product' ) ) {
			return 'other';
		}
		if ( is_product() ) {
			return 'product';
		}
		if ( is_product_category() ) {
			return 'category';
		}
		if ( is_shop() ) {
			return 'home';
		}
		if ( is_cart() ) {
			return 'cart';
		}
		if ( is_checkout() ) {
			return 'checkout';
		}
		return 'other';
	}

	/**
	 * Get the current product ID when on a product page.
	 *
	 * @return string
	 */
	private function get_product_id() {
		if ( function_exists( 'is_product' ) && is_product() ) {
			return (string) get_the_ID();
		}
		return '';
	}

	/**
	 * Get the category name for the current product or category page.
	 *
	 * @return string
	 */
	private function get_category_name() {
		if ( ! function_exists( 'is_product' ) ) {
			return '';
		}
		if ( is_product() ) {
			$terms = get_the_terms( get_the_ID(), 'product_cat' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				return $this->get_term_hierarchy_path( $terms[0] );
			}
		} elseif ( is_product_category() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				return $this->get_term_hierarchy_path( $term );
			}
		}
		return '';
	}

	/**
	 * Build the full category hierarchy path for a term (e.g. "Electronics > Phones > Smartphones").
	 *
	 * Uses the same " > " separator as the Doofinder product indexer.
	 *
	 * @param \WP_Term $term The category term.
	 * @return string The full hierarchy path.
	 */
	private function get_term_hierarchy_path( $term ) {
		$ancestors = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );
		$ancestors = array_reverse( $ancestors );

		$parts = array();
		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'product_cat' );
			if ( ! is_wp_error( $ancestor ) ) {
				$parts[] = $ancestor->name;
			}
		}
		$parts[] = $term->name;

		return implode( ' > ', $parts );
	}
}
