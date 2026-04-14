<?php
/**
 * DooFinder Endpoint_Product_Category methods.
 *
 * @package Doofinder\WP\Endpoints
 */

/**
 * Class Endpoint_Product_Category
 *
 * This is kept for backward compatibility, it will call the Endpoint_Custom class to handle the request,
 * but it will be removed in the future when all the customers have updated the plugin to at least version 2.12.0
 * and when we apply a change in our internal services.
 */
class Endpoint_Product_Category {

	const PER_PAGE = 100;
	const CONTEXT  = 'doofinder/v1';
	const ENDPOINT = '/product_category';

	/**
	 * Initialize the custom item endpoint.
	 *
	 * @return void
	 */
	public static function initialize() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					self::CONTEXT,
					self::ENDPOINT,
					array(
						'methods'             => 'GET',
						'callback'            => array( self::class, 'product_category_endpoint' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
	}

	/**
	 * Custom item endpoint callback. It has been kept due to backward compatibility purposes.
	 * Since the product category is a taxonomy, it will set the type parameter to 'product_cat'
	 * and call the Endpoint_Custom class to handle the request like the rest of taxonomies.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response containing modified data.
	 */
	public static function product_category_endpoint( $request ) {
		$request->set_param( 'type', 'product_cat' );
		return Endpoint_Custom::custom_endpoint( $request );
	}
}
