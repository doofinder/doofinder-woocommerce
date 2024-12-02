<?php
/**
 * DooFinder Update_On_Save_Api methods.
 *
 * @package Doofinder\WP\Api
 */

namespace Doofinder\WP\Api;

use Doofinder\WP\Settings;
use Doofinder\WP\Log;
use Doofinder\WP\Multilanguage\Language_Plugin;
use Doofinder\WP\Multilanguage\Multilanguage;

use Endpoint_Product;
use Endpoint_Custom;
use WP_Http;

/**
 * Handles requests to the Update On Save API.
 */
class Update_On_Save_Api {

	/**
	 * Instance of a class used to log to a file.
	 *
	 * @var Log
	 */
	private $log;

	/**
	 * Instance of the class handling the multilanguage.
	 *
	 * @var Language_Plugin
	 */
	public $language;

	/**
	 * API Host
	 *
	 * @var string
	 */
	private $api_host;

	/**
	 * API Key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Dooplugins Host
	 *
	 * @var string
	 */
	private $dp_host;

	/**
	 * Hash
	 * The search engine's unique id
	 *
	 * @var array
	 */
	private $search_engines;

	/**
	 * Hash
	 * The search engine's unique id
	 *
	 * @var string
	 */
	private $hash;

	/**
	 * Contains information whether we should process all languages at once
	 *
	 * @var bool
	 */
	public $process_all_languages = true;

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
		$this->log                   = new Log( 'update-on-save-api.log' );
		$this->language              = Multilanguage::instance();
		$this->api_key               = Settings::get_api_key();
		$this->api_host              = Settings::get_api_host();
		$this->dp_host               = Settings::get_dooplugins_host();
		$this->hash                  = Settings::get_search_engine_hash( $language );
		$this->process_all_languages = ! empty( $this->language->get_languages() );
		$this->search_engines        = self::build_search_engines( $this->process_all_languages, $this->language );
		$this->authorization_header  = array(
			'Authorization' => "Token $this->api_key",
			'content-type'  => 'application/json',
		);

		$this->log->log( 'Create Management API Client' );
		$this->log->log( 'API Host: ' . $this->api_host );
		$this->log->log( 'Hash: ' . print_r( $this->search_engines, true ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}

	/**
	 * Constructs a full URL by appending a given $path to the base $host URL.
	 *
	 * This function takes a relative path and appends it to the base $host URL.
	 *
	 * @param string $host Base host URL.
	 * @param string $path The relative path to be appended to the base host URL.
	 *
	 * @return string The fully constructed URL.
	 */
	public function build_url( $host, $path ) {
		return "{$host}/{$path}";
	}

	/**
	 * Updates multiple items in the Doofinder index.
	 *
	 * This method updates multiple items of a specific post type in the Doofinder index.
	 * It sends a POST request to the Doofinder API with the data to be updated.
	 *
	 * @param string $post_type The post type for which the items should be updated.
	 * @param array  $ids      The ids representing the items to be updated.
	 * @return bool
	 * @since 1.0.0
	 */
	public function update_bulk( $post_type, $ids ) {
		$this->log->log( 'Update items' );
		foreach ( $this->search_engines as $lang => $hashid ) {
			// phpcs:ignore I had to add a phpcs:ignore because the formatter was changing wordpress to WordPress, thus causing issues.
			$uri = $this->build_url( $this->dp_host, 'item/' . $hashid . '/' . $post_type . '?action=update&platform=wordpress' ); // phpcs:ignore WordPress.WP.CapitalPDangit

			$chunks = array_chunk( $ids, 100 );
			$resp   = true;

			foreach ( $chunks as $chunk ) {
				$items = $this->get_items( $chunk, $post_type, $lang );
				$resp  = $resp && $this->send_request( $uri, $items );
			}
		}

		return true;
	}

	/**
	 * Get items data from our endpoint products (depends with post_type).
	 *
	 * @param array  $ids product IDs we want to get data.
	 * @param string $post_type Type of item to request.
	 * @param string $lang Lang.
	 *
	 * @return array API response with items list.
	 * @since 1.0.0
	 */
	public function get_items( $ids, $post_type, $lang ) {
		if ( 'product' === $post_type ) {
			return $this->get_products_data( $ids, $lang );
		} else {
			return $this->get_custom_data( $ids, $post_type );
		}
	}

	/**
	 * Get products data from our endpoint products.
	 *
	 * @param array  $ids product IDs we want to get data.
	 * @param string $lang lang.
	 *
	 * @since 1.0.0
	 */
	public function get_products_data( $ids, $lang ) {

		require_once 'endpoints/class-endpoint-product.php';
		return Endpoint_Product::get_data( $ids, $lang );
	}

	/**
	 * Get products data from our endpoint products.
	 *
	 * @param array  $ids ID product we want to get data.
	 * @param string $post_type Post type.
	 *
	 * @since 1.0.0
	 */
	public function get_custom_data( $ids, $post_type ) {

		require_once 'endpoints/class-endpoint-custom.php';
		return Endpoint_Custom::get_data( $ids, $post_type );
	}

	/**
	 * Deletes multiple items from the Doofinder index.
	 *
	 * This method deletes multiple items of a specific post type from the Doofinder index.
	 * It sends a POST request to the Doofinder API with the data to be deleted.
	 *
	 * @param string $post_type The post type for which the items should be deleted.
	 * @param array  $ids      The ids representing the items to be deleted.
	 *
	 * @return mixed The response from the Doofinder API.
	 *
	 * @since 1.0.0
	 */
	public function delete_bulk( $post_type, $ids ) {
		$this->log->log( 'Delete items' );

		// phpcs:ignore I had to add a phpcs:ignore because the formatter was changing wordpress to WordPress, thus causing issues.
		$uri = $this->build_url( $this->dp_host, 'item/' . $this->hash . '/' . $post_type . '?action=delete&platform=wordpress' ); // phpcs:ignore WordPress.WP.CapitalPDangit

		return $this->send_request( $uri, $ids );
	}

	/**
	 * Handles sending requests to API.
	 *
	 * @param string $url Endpoint URL.
	 * @param array  $data The array containing the payload to be sent.
	 * @param string $method MEthod to be used. Defaults to POST.
	 *
	 * @return bool
	 */
	private function send_request( $url, $data, $method = 'POST' ) {
		$this->log->log( "Making a request to: $url" );
		$data = array(
			'headers' => $this->authorization_header,
			'method'  => $method,
			'body'    => wp_json_encode( $data ),
		);

		$response = wp_remote_request( $url, $data );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$this->log->log( "WP-Error in the request: $error_message" );
		} elseif ( WP_Http::OK === $response['response']['code'] ) {
			$this->log->log( 'The update on save request has been processed correctly' );
			return true;
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			$this->log->log( 'Error in the request: ' . print_r( $response, true ) );
		}
		return false;
	}

	/**
	 * Builds a list of search engines based on language settings and configuration.
	 *
	 * This function constructs an array of search engine hashes for each language available
	 * when processing all languages. It retrieves the search engine hash for each language
	 * using the base locale or language code.
	 *
	 * @param bool   $process_all_languages Determines if all available languages should be processed.
	 * @param object $language              An instance of a language handler providing methods:
	 *                                      - `get_languages()` to retrieve all available languages.
	 *                                      - `get_base_locale()` to get the base locale.
	 *
	 * @return array An associative array mapping base locales to search engine hashes
	 */
	public static function build_search_engines( bool $process_all_languages, $language ) {
		$search_engines = array();
		if ( $process_all_languages ) {
			foreach ( $language->get_languages() as $lang ) {
				$code       = $lang['locale'] === $language->get_base_locale() ? '' : $lang['code'];
				$first_part = strpos( $code, '-' ) !== false ? explode( '-', $code )[0] : $code;
				$hash       = Settings::get_search_engine_hash( $first_part );
				$search_engines[ $language->get_base_locale() ] = $hash;
			}
		} else {
			$search_engines[''] = Settings::get_search_engine_hash();
		}

		return $search_engines;
	}
}
