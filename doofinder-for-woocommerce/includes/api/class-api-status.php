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
}