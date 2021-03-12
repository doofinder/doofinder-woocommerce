<?php

namespace Doofinder\WC\Api;

use Doofinder\Api\Management\Errors\IndexingInProgress;
//use Doofinder\Api\Management\Errors\ThrottledResponse;
use Doofinder\WC\Log;

/**
 * Handles throttling of the requests to the Doofinder API.
 *
 * The API only allows 2 requests every second, and we typically need to make more
 * (retrieve search engines, retrieve post types, maybe create post type,
 * send data). So if we hit the throttle we need to wait 1s, and then try again.
 *
 * Usage:
 * Instantiate Throttle passing the class you want to throttle for:
 * $throttle = new Throttle( $my_object );
 * You can call your class methods on $throttle, as if it was $my_object.
 * Throttle will check for throttling errors, and wait if necessary
 * before retrying calling the same method.
 */
class Throttle {

	/**
	 * Maximum number of times we retry sending the request
	 * in case exception is thrown.
	 *
	 * @const int
	 */
	const MAX_RETRIES = 5;

	/**
	 * A class (instance!) we are throttling for.
	 *
	 * @var object
	 */
	private $target;

	public function __construct( $target ) {
		$this->target = $target;
	}

	/**
	 * Passes the method call to the class we are throttling for,
	 * but handles throttling of the calls to that method.
	 *
	 * Than means if the method call does not success we wait a bit,
	 * and then try again.
	 *
	 * @param string $name
	 * @param array  $arguments
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function __call( $name, array $arguments ) {
		if ( ! method_exists( $this->target, $name ) ) {
			throw new \InvalidArgumentException(
				"Method $name does not exist on " . get_class( $this->target )
			);
		}

		return $this->throttle( $name, $arguments );
	}

	/**
	 * Throttle the method call.
	 *
	 * Call the method, if the exception is thrown we wait a bit, and then
	 * retry calling the same method, until it succeeds.
	 *
	 * @see Throttle::MAX_RETRIES
	 *
	 * @param string $method
	 * @param array  $arguments
	 * @param int    $count
	 *
	 * @return mixed
	 * @throws \Exception
	 * 
	 */
	private function throttle( $method, array $arguments, $count = 1 ) {
		$log = new Log();

		try {
			return call_user_func_array( array( $this->target, $method ), $arguments );
		} catch ( IndexingInProgress $exception ) { 
			$log->log( "Throttling when indexing - $method: $count" );
			if ( $count >= self::MAX_RETRIES ) {
				throw $exception;
			}

			sleep( 3 );
		} catch ( \Exception $exception ) { 
			$log->log( "Throttling $method: $count" );
			if ( $count >= self::MAX_RETRIES ) {
				throw $exception;
			}

			sleep( 1 );
		}

		return $this->throttle( $method, $arguments, $count + 1 );
	}
}
