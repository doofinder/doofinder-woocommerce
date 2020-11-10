<?php

namespace Doofinder\WC\Api;

use Doofinder\WC\Helpers\Helpers;
use Doofinder\WC\Multilanguage\Multilanguage;
use Doofinder\WC\Log;

defined( 'ABSPATH' ) or die();

/**
 * Generates instance of the class making API calls.
 *
 * There are few, because we want to be able to dump whatever's going to the API
 * to local log file during development, and send data to real API
 * when in production.
 */
class Api_Factory {

	/**
	 * Singleton instance of the class implementing API calls.
	 *
	 * @var Api_Wrapper
	 */
	private static $instance;

	/**
	 * Retrieve the instance of class making calls to the API.
	 *
	 * @return Api_Wrapper
	 */
	public static function get($language = null, $get_new = false) {
		$log = new Log( 'api.txt' );
		//$log->log('-------- API FACTORY - GET -----------');

		if ( self::$instance && !$get_new ) {
			$log->log('API FACTORY - GET - Instance already exists');
			return self::$instance;
		}

		$multilang = Multilanguage::instance();

		$base_language = $multilang->get_base_language();

		$language = $language === $base_language ? '' :  $language;

		//$log->log('API FACTORY - GET - LANG : ' . $language );

		// Use real API in production.
		// Dump data to local log file when in development.

		$is_debug_mode = Helpers::is_debug_mode();
		
		if ($is_debug_mode) {

			$log->log( 'API FACTORY - DEBUG MODE IS ENABLED' );
		}

		if ( $is_debug_mode ) {
			self::$instance = new Local_Dump($language);
		} else {
			self::$instance = new Doofinder_Api($language);
		}

		return self::$instance;
	}
}
