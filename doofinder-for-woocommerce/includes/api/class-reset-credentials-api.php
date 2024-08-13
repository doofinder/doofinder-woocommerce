<?php
/**
 * DooFinder Reset_Credentials_Api methods.
 *
 * @package Doofinder\WP\Api
 */

namespace Doofinder\WP\Api;

use Doofinder\WP\Settings;
use Doofinder\WP\Log;
use WP_Http;

/**
 * Handles requests to the Management API.
 */
class Reset_Credentials_Api {

	/**
	 * Instance of a class used to log to a file.
	 *
	 * @var Log
	 */
	private $log;

	/**
	 * API Host
	 *
	 * @var string
	 */
	private $dooplugins_host;

	/**
	 * API Key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Hash
	 * The search engine's unique id
	 *
	 * @var string
	 */
	private $hash;

	/**
	 * Authorization Header
	 *
	 * @var string
	 */
	private $authorization_header;

	/**
	 * Reset_Credentials_Api constructor.
	 *
	 * @param string $language Language code.
	 */
	public function __construct( $language ) {
		$this->log                  = new Log( 'reset-credential-api.log' );
		$this->api_key              = Settings::get_api_key();
		$this->dooplugins_host      = Settings::get_dooplugins_host();
		$this->hash                 = Settings::get_search_engine_hash( $language );
		$this->authorization_header = array(
			'Authorization' => "Token $this->api_key",
			'content-type'  => 'application/json',
		);

		$this->log->log( 'Create Management API Client' );
		$this->log->log( 'Dooplugins Host: ' . $this->dooplugins_host );
		$this->log->log( 'Hash: ' . $this->hash );
	}

	/**
	 * Resets the credentials by sending a request to a specified URL.
	 *
	 * This function logs the action of resetting credentials, constructs the URL for the reset request,
	 * and then sends the request with the provided data.
	 *
	 * @param array $data The data to be sent with the reset credentials request.
	 *
	 * @return mixed The response from the `send_request` method, which could vary depending on its implementation.
	 */
	public function reset_credentials( $data ) {
		$this->log->log( 'Reset Credentials' );

		$uri = $this->build_url( 'wordpress/' . $this->hash . '/reset-credentials' );
		$this->log->log( "Making a request to: $uri" );

		return $this->send_request( $uri, $data );
	}

	/**
	 * Handles sending requests to API. Send a POST request with the given $body to the given endpoint $url.
	 *
	 * @param string $url Endpoint URL.
	 * @param array  $body The array containing the payload to be sent.
	 *
	 * @return void
	 */
	private function send_request( $url, $body ) {
		$data = array(
			'headers'     => array(
				'Authorization' => "Token {$this->api_key}",
				'Content-Type'  => 'application/json; charset=utf-8',
			),
			'body'        => wp_json_encode( $body ),
			'method'      => 'POST',
			'data_format' => 'body',
			'timeout'     => 20,
		);

		$response  = wp_remote_request( $url, $data );
		$resp_code = wp_remote_retrieve_response_code( $response );
		if ( ! is_wp_error( $response ) && $resp_code >= WP_Http::OK && $resp_code < WP_Http::BAD_REQUEST ) {
			$response_body    = wp_remote_retrieve_body( $response );
			$decoded_response = json_decode( $response_body, true );
			$this->log->log( "The request has been made correctly: $decoded_response" );
		} else {
			$this->log->log( print_r( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			$error_message = wp_remote_retrieve_response_message( $response );
			$this->log->log( "Error in the request: $error_message" );
		}
	}

	/**
	 * Constructs a full URL by appending a given path to the base host URL.
	 *
	 * This function takes a relative path and appends it to the Dooplugins base URL.
	 *
	 * @param string $path The relative path to be appended to the base host URL.
	 *
	 * @return string The fully constructed URL.
	 */
	private function build_url( $path ) {
		return "{$this->dooplugins_host}/{$path}";
	}
}
