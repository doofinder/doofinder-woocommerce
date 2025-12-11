<?php
/**
 * DooFinder Add_To_Cart methods.
 *
 * @package Doofinder\WP\Add_To_Cart
 */

namespace Doofinder\WP;

use WP_Http;

/**
 * Handles the add to cart workflow.
 */
class Add_To_Cart {

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

		add_action( 'wp_ajax_' . self::ACTION_NAME, array( __CLASS__, 'doofinder_ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION_NAME, array( __CLASS__, 'doofinder_ajax_add_to_cart' ) );
		add_action( 'wp_ajax_doofinder_get_product_info', array( __CLASS__, 'product_info' ) );
		add_action( 'wp_ajax_nopriv_doofinder_get_product_info', array( __CLASS__, 'product_info' ) );
	}

	/**
	 * Returns the product info for a given id.
	 */
	public static function product_info() {
		$post_id = isset( $_REQUEST['id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) ) : null;
		if ( empty( $post_id ) ) {
			return '';
		}

		$product = wc_get_product( $post_id );
		if ( empty( $product ) ) {
			return '';
		}

		$variation_id = 0;

		if ( is_a( $product, 'WC_Product_Variation' ) ) {
			$post_id      = $product->get_parent_id();
			$variation_id = $product->get_id();
		}

		$data = array(
			'product'     => $post_id,
			'product_url' => get_the_permalink( $post_id ),
			'variation'   => $variation_id,
			'add_to_cart' => true,
		);

		if ( $data['add_to_cart'] ) {
			switch ( get_class( $product ) ) {
				case 'WC_Product_External':
				case 'WC_Product_Variable':
				case 'WC_Product_Grouped':
					$data['add_to_cart'] = false;
					break;
			}
		}

		return wp_send_json( $data );
	}

	/**
	 * Adds item to the WooCommerce cart from an AJAX request by using WooCommerce add to cart method.
	 *
	 * @return void
	 */
	public static function doofinder_ajax_add_to_cart() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), self::ACTION_NAME ) ) {
			wp_send_json_error( __( 'Nonce verification failed.', 'wordpress-doofinder' ), WP_Http::BAD_REQUEST );
		}

		if ( ! isset( $_POST['product_id'] ) || ! isset( $_POST['variation_id'] ) ) {
			wp_send_json_error( __( 'Required params are missing.', 'wordpress-doofinder' ), WP_Http::BAD_REQUEST );
		}

		/**
		 * Add to cart Product ID.
		 *
		 * Allows to modify the Product ID (as integer) to be added to the cart.
		 *
		 * @param $product_id ID of the product.
		 *
		 * @since 1.0
		 */
		$product_id   = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_POST['product_id'] ) );
		$quantity     = empty( $_POST['quantity'] ) ? 1 : wc_stock_amount( wp_unslash( $_POST['quantity'] ) );
		$variation_id = absint( $_POST['variation_id'] );

		/**
		 * Add to cart validation.
		 *
		 * Filters if an item being added to the cart passed validation checks.
		 *
		 * @param boolean $passed_validation True if the item passed validation.
		 * @param integer $product_id        Product ID being validated.
		 * @param integer $quantity          Quantity added to the cart.
		 *
		 * @since 1.0
		 */
		$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
		$product_status    = get_post_status( $product_id );

		if ( $passed_validation && WC()->cart->add_to_cart( $product_id, $quantity, $variation_id ) && 'publish' === $product_status ) {
			/**
			 * Added to cart Product ID.
			 *
			 * Function for `woocommerce_ajax_added_to_cart` action-hook.
			 *
			 * @param $product_id ID of the product.
			 *
			 * @since 1.0
			 */
			do_action( 'woocommerce_ajax_added_to_cart', $product_id );

			if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
				wc_add_to_cart_message( array( $product_id => $quantity ), true );
			}

			\WC_AJAX::get_refreshed_fragments();
		} else {
			$data = array(
				'error'       => true,
				/**
				 * Function for `woocommerce_cart_redirect_after_error` filter-hook.
				 *
				 * @param $permalink Product permalink.
				 * @param $product_id ID of the product.
				 *
				 * @since 1.0
				 */
				'product_url' => apply_filters( 'woocommerce_cart_redirect_after_error', get_permalink( $product_id ), $product_id ),
			);

			/*
			TODO (@davidmolinacano): This should be `wp_send_json_error()`
			instead, but it requires to modify the AJAX structure carefully.
			*/
			wp_send_json( $data );
		}

		wp_die();
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
						'doofinder-add-to-cart',
						Doofinder_For_WordPress::plugin_url() . 'assets/js/df-add-to-cart.js',
						array( 'jquery' ),
						Doofinder_For_WordPress::$version,
						true
					);
					wp_localize_script(
						'doofinder-add-to-cart',
						'df_cart',
						array(
							'nonce'              => wp_create_nonce( self::ACTION_NAME ),
							'ajax_url'           => admin_url( 'admin-ajax.php' ),
							'item_info_endpoint' => get_site_url( null, '/wp-json/doofinder-for-wc/v1/product-info/' ),
						)
					);
				}
			}
		);
	}
}
