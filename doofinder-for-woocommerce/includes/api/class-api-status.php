<?php

namespace Doofinder\WC\Api;

defined( 'ABSPATH' ) or die();

class Api_Status {

	/**
	 * API call ended successfully.
	 *
	 * @var string
	 */
	public static $success = 'success';

	/**
	 * API refused to process the request because it's busy.
	 *
	 * @var string
	 */
	public static $indexing_in_progress = 'indexing_in_progress';

	/**
	 * API threw an exception because of invalid token.
	 *
	 * @var string
	 */
	public static $invalid_search_engine = 'invalid_search_engine';

	/**
	 * API threw an exception because of invalid or missing API Key.
	 *
	 * @var string
	 */
	public static $not_authenticated = 'not_authenticated';

	/**
	 * Next post type does not exist.
	 *
	 * @var string
	 */
	public static $no_next_post_type = 'no_next_post_type';

	/**
	 * The client made a bad request.
	 *
	 * @var string
	 */
	public static $bad_request = 'bad_request';

	/**
	 * Other error.
	 *
	 * @var string
	 */
	public static $unknown_error = 'unknown_error';

	/**
	 * Get status from doofinder api response
	 * @param string $api_message
	 * @param object $api_body
	 * @return array
	 */
	public static function get_api_response_status($api_message,$api_body) {

		// Show api response message for user in the backend
		$exception_body = json_decode($api_body);

		if (isset($exception_body->error->code) && isset($exception_body->error->message)) {
			return array(
				'status'  => $exception_body->error->code,
				'message' => $api_message . ' ' .$exception_body->error->message,
				'error'   => true
			);
		}

		return false;
	}
}
