<?php
/**
 * DooFinder Reset_Credentials_Index methods.
 *
 * @package Indexing
 */

namespace Doofinder\WP;

use Doofinder\WP\Api\Reset_Credentials_Api;
use Doofinder\WP\Helpers\Store_Helpers;
use Doofinder\WP\Multilanguage\Multilanguage;

/**
 * Handles the process of resetting the required credentials for indexing.
 */
class Reset_Credentials_Index {

	/**
	 * Instance of class handling multilanguage environments.
	 *
	 * @var Language_Plugin
	 */
	private $language;

	/**
	 * Language selected for this operation.
	 *
	 * @var string
	 */
	private $current_language;

	/**
	 * Class handling API calls.
	 *
	 * @var Reset_Credentials_Api
	 */
	private $api;

	/**
	 * Reset_Credentials_Index constructor.
	 */
	public function __construct() {
		$this->language         = Multilanguage::instance();
		$this->current_language = $this->language->get_current_language();
		$this->api              = new Reset_Credentials_Api( $this->current_language );
	}

	/**
	 * Performs the reset_credentials.
	 *
	 * @return void
	 */
	public function reset_credentials() {
		$payload = Store_Helpers::get_store_options();
		$this->api->reset_credentials( $payload );
	}

	/**
	 * Resets the token auth.
	 *
	 * @return void
	 */
	public function reset_token_auth() {
		$endpoints_token = Store_Helpers::create_endpoints_token();
		update_option( 'doofinder_for_wp_token', $endpoints_token );

		$this->reset_credentials();
	}
}
