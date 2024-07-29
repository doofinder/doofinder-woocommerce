<?php
/**
 * DooFinder class that allows to get some useful properties from WooCommerce methods such as the price and the product image.
 *
 * @package  Doofinder\WP\REST_API_Handler
 */

namespace Doofinder\WP;

/**
 * REST_API_Handler Class.
 */
class REST_API_Handler {

	const PRODUCT_FIELDS = array(
		'df_price',
		'df_sale_price',
		'df_regular_price',
		'df_image_link',
	);

	/**
	 * Register the REST Fields we want to add.
	 *
	 * @return void
	 */
	public static function initialize() {
		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			// Register the product category image field.
			register_rest_field( 'product_cat', 'image_link', array( 'get_callback' => array( self::class, 'get_product_cat_df_image_link' ) ) );

			// Register the product fields.
			foreach ( self::PRODUCT_FIELDS as $field ) {
				register_rest_field( array( 'product', 'product_variation' ), $field, array( 'get_callback' => array( self::class, 'get_' . $field ) ) );
			}

			/*
			Return prices applying base location taxes
			More info: https://github.com/woocommerce/woocommerce/wiki/How-Taxes-Work-in-WooCommerce#prices-including-tax---experimental-behavior
			*/
			add_filter(
				'woocommerce_adjust_non_base_location_prices',
				function ( $value ) {
					return isset( $_SERVER['HTTP_DOOFINDER_ORIGIN'] ) ? false : $value;
				}
			);
		}
	}

	/**
	 * Get the real price from WooCommerce taking into account if the taxes are included or not.
	 *
	 * @param string      $price The original raw item price without taking into account if the taxes should be applied or not.
	 * @param \WC_Product $product WooCommerce product object. More info at: https://woocommerce.github.io/code-reference/classes/WC-Product.html.
	 * @return float|string
	 */
	private static function get_raw_real_price( $price, $product ) {
		$woocommerce_tax_display_shop = get_option( 'woocommerce_tax_display_shop', 'incl' );
		return 'incl' === $woocommerce_tax_display_shop ?
			wc_get_price_including_tax(
				$product,
				array(
					'price' => $price,
				)
			) :
			wc_get_price_excluding_tax(
				$product,
				array(
					'price' => $price,
				)
			);
	}

	/**
	 * Checks that image link is absolute, if not, it adds the site URL.
	 *
	 * @param string $image_link absolute or relative URL of the image.
	 * @return string $image_link
	 */
	private static function add_base_url_if_needed( $image_link ) {
		if ( 0 === strpos( $image_link, '/' ) ) {
			$image_link = get_site_url() . $image_link;
		}
		return $image_link;
	}

	/**
	 * Returns the raw price for the given product.
	 *
	 * @param array  $product The product we want to add the field.
	 * @param string $price_name The price name. By default 'price'.
	 * @return float|string
	 */
	private static function get_raw_price( $product, $price_name = 'price' ) {
		$product_id = $product['id'];
		$wc_product = wc_get_product( $product_id );
		$fn_name    = "get_$price_name";
		if ( is_a( $wc_product, 'WC_Product' ) && method_exists( $wc_product, $fn_name ) ) {
			$price     = $wc_product->$fn_name();
			$raw_price = 'sale_price' === $price_name && '' === $price ? '' : self::get_raw_real_price( $price, $wc_product );
			// If price is equal to 0, return an empty string.
			// phpcs:ignore Universal.Operators.StrictComparisons
			$raw_price = ( 0 == $raw_price ) ? '' : $raw_price;
			return $raw_price;
		}

		return '';
	}

	/**
	 * Get the raw price
	 *
	 * @param array $product WooCommerce Product or Variable Product as array.
	 * @return float The raw price including or excluding taxes (defined in WC settings).
	 */
	public static function get_df_price( $product ) {
		return self::get_raw_price( $product );
	}

	/**
	 * Get the raw sale price
	 *
	 * @param array $product WooCommerce Product or Variable Product as array.
	 * @return float The raw sale price including or excluding taxes (defined in WC settings).
	 */
	public static function get_df_sale_price( $product ) {
		return self::get_raw_price( $product, 'sale_price' );
	}

	/**
	 * Get the raw regular price
	 *
	 * @param array $product WooCommerce Product or Variable Product as array.
	 * @return float The raw regular price including or excluding taxes (defined in WC settings).
	 */
	public static function get_df_regular_price( $product ) {
		return self::get_raw_price( $product, 'regular_price' );
	}

	/**
	 * Returns the image link for a given product.
	 * If the product is a variation and doesn't have an image, return the parent image link.
	 *
	 * @param array $product WooCommerce Product or Variable Product as array.
	 * @return string The image link.
	 */
	public static function get_df_image_link( $product ) {
		$product_id = $product['id'];
		$post       = get_post( $product_id );
		$thumbnail  = new Thumbnail( $post );
		$image_link = $thumbnail->get();
		if ( empty( $image_link ) && 'product_variation' === $post->post_type ) {
			$thumbnail  = new Thumbnail( get_post( $post->post_parent ) );
			$image_link = $thumbnail->get();
		}

		// If neither the variant and the product have an image, return the woocommerce placeholder image.
		$image_link = empty( $image_link ) ? wc_placeholder_img_src( Thumbnail::get_size() ) : $image_link;
		$image_link = self::add_base_url_if_needed( $image_link );
		return $image_link;
	}

	/**
	 * Returns the image link for a given term.
	 *
	 * @param array $term WordPress Term as array.
	 * @return string The image link.
	 */
	public static function get_product_cat_df_image_link( $term ) {
		// get the thumbnail id using the queried category term_id.
		$thumbnail_id = get_term_meta( $term['id'], 'thumbnail_id', true );
		$image_link   = empty( $thumbnail_id ) ? '' : wp_get_attachment_url( $thumbnail_id );
		$image_link   = empty( $image_link ) ? wc_placeholder_img_src( Thumbnail::get_size() ) : $image_link;
		$image_link   = self::add_base_url_if_needed( $image_link );
		return $image_link;
	}
}
