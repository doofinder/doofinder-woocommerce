<?php
/**
 * DooFinder Endpoint_Post_Category methods.
 *
 * @package Doofinder\WP\Endpoints
 */

use Doofinder\WP\Endpoints;
use Doofinder\WP\Helpers\Helpers;

/**
 * Class Endpoint_Post_Category
 *
 * This class defines various methods for handling item WordPress endpoints.
 */
class Endpoint_Post_Category {

	const PER_PAGE = 100;
	const CONTEXT  = 'doofinder/v1';
	const ENDPOINT = '/post_category';
	const FIELDS   = array(
		'description',
		'_embedded',
		'id',
		'image_link',
		'link',
		'name',
		'parent',
		'slug',
	);

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
						'callback'            => array( self::class, 'post_category_endpoint' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
	}

	/**
	 * Custom item endpoint callback.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response containing modified data.
	 */
	public static function post_category_endpoint( $request ) {

		Endpoints::check_secure_token();

		$locale_or_lang_code = $request->get_param( 'lang' ) ?? '';
		$lang_code           = Helpers::apply_locale_to_rest_context( $locale_or_lang_code );

		$config_request['per_page'] = $request->get_param( 'per_page' ) ?? self::PER_PAGE;
		$config_request['page']     = $request->get_param( 'page' ) ?? 1;
		$config_request['lang']     = $lang_code;
		$config_request['fields']   = ( 'all' === $request->get_param( 'fields' ) ) ? '' : implode( ',', self::get_fields() );

		// Retrieve the original items data.
		$items = self::get_items( $config_request );

		// Return the modified items data as a response.
		return new WP_REST_Response( $items );
	}

	/**
	 * Retrieve a list of items with pagination.
	 *
	 * @param array $config_request Config request params (page, per_page, type).
	 * @return array|null   An array of items data or null on failure.
	 */
	private static function get_items( $config_request ) {
		// Retrieve the original items data.
		$request = new WP_REST_Request( 'GET', '/wp/v2/categories' );
		$request->set_query_params(
			array(
				'page'     => $config_request['page'],
				'per_page' => $config_request['per_page'],
				'lang'     => $config_request['lang'],
				'_fields'  => $config_request['fields'],
			)
		);
		$response = rest_do_request( $request );
		$data     = rest_get_server()->response_to_data( $response, true );

		if ( ! empty( $data['data']['status'] ) && WP_Http::OK !== $data['data']['status'] ) {
			$data = array();
		}

		return $data;
	}

	/**
	 * Get the array of fields.
	 *
	 * @return array The array of fields.
	 */
	public static function get_fields() {
		return self::FIELDS;
	}
}
