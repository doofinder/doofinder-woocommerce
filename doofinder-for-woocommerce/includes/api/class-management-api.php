<?php

namespace Doofinder\WC\Api;

use Doofinder\Management\Errors\Utils;
use DoofinderManagement\ApiException;
use Doofinder\WC\Log;
use Exception;
use WP_Error;

/**
 * Handles requests to the Management API.
 */
class Management_Api {
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
	private $api_host;

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

	public function __construct( $api_host, $api_key, $hash ) {
		$this->log                  = new Log( 'management-api.txt' );
		$this->api_host             = $api_host;
		$this->api_key              = $api_key;
		$this->hash                 = $hash;
		$this->authorization_header = array(
			'Authorization' => "Token $this->api_key",
			'content-type'  => 'application/json'
		);

		$this->log->log( 'Crate Management API Client' );
		$this->log->log( 'API Key: ' . $this->api_key );
		$this->log->log( 'API Host: ' . $this->api_host );
		$this->log->log( 'Hash: ' . $this->hash );
	}

	/**
	 * Handle request errors and throw exception
	 *
	 * @param array|WP_Error $request
	 *
	 * @throws Exception
	 */
	private function handleError( $request ) {
		if ( ! is_wp_error( $request ) ) {
			$response_code    = wp_remote_retrieve_response_code( $request );
			$response_message = wp_remote_retrieve_response_message( $request );
			$response_body    = json_decode( wp_remote_retrieve_body( $request ) );
			if ( isset( $response_body->error ) ) {
				$this->log->log( "Error $response_code - $response_message" );
				$this->log->log( $response_body );
				throw Utils::handleErrors( $response_code, wp_remote_retrieve_body( $request ), $request );
			} else {
				return $request;
			}
		} else {
			$error_code    = $request->get_error_code();
			$error_message = $request->get_error_message();
			$this->log->log( "Error $error_code - $error_message" );
			throw Utils::handleErrors( $error_code, $error_message, $request );
		}
	}

	/**
	 * Handle sending requests to API
	 *
	 * @param $url
	 * @param $data
	 *
	 * @return array|WP_Error
	 * @throws ApiException
	 * @throws \Doofinder\Management\Errors\APITimeout
	 * @throws \Doofinder\Management\Errors\BadGateway
	 * @throws \Doofinder\Management\Errors\BadRequest
	 * @throws \Doofinder\Management\Errors\ConflictRequest
	 * @throws \Doofinder\Management\Errors\NotAllowed
	 * @throws \Doofinder\Management\Errors\NotFound
	 * @throws \Doofinder\Management\Errors\TooManyItems
	 * @throws \Doofinder\Management\Errors\TooManyRequests
	 * @throws \Doofinder\Management\Errors\WrongResponse
	 */
	private function sendRequest( $url, $data ) {
		try {
			return $this->handleError( wp_remote_request( $url, $data ) );
		} catch ( ApiException $e ) {
			$statusCode      = $e->getCode();
			$contentResponse = $e->getResponseBody();
			$error           = Utils::handleErrors( $statusCode, $contentResponse, $e );

			throw $error;
		}
	}

	/**
	 * Partially updates item from index by its id. The operation returns the updated item.
	 *
	 * @param string $item_id
	 * @param string $name
	 * @param string $data
	 *
	 * @throws Exception
	 */
	public function updateItem( $item_id, $name, $data ) {
		$this->log->log( 'Update item' );

		return $this->sendRequest( "$this->api_host/api/v2/search_engines/$this->hash/indices/$name/items/$item_id", array(
			'headers' => $this->authorization_header,
			'method'  => 'PATCH',
			'body'    => $data
		) );
	}

	/**
	 * Creates an item in the index with the data provided.
	 *
	 * @param string $name
	 * @param string $data
	 *
	 * @throws Exception
	 */
	public function createItem( $name, $data ) {
		$this->log->log( 'Create item' );

		return $this->sendRequest( "$this->api_host/api/v2/search_engines/$this->hash/indices/$name/items", array(
			'headers' => $this->authorization_header,
			'method'  => 'POST',
			'body'    => $data
		) );
	}

	/**
	 * Deletes item from the index by its id.
	 *
	 * @param string $item_id
	 * @param string $name
	 *
	 * @throws Exception
	 */
	public function deleteItem( $item_id, $name ) {
		$this->log->log( 'Delete item' );

		return $this->sendRequest( "$this->api_host/api/v2/search_engines/$this->hash/indices/$name/items/$item_id", array(
			'headers' => $this->authorization_header,
			'method'  => 'DELETE',
		) );
	}

	/**
	 * Creates a new empty temporary index for the same index name.
	 *
	 * @param string $name
	 *
	 * @throws Exception
	 */
	public function createTemporaryIndex( $name ) {
		$this->log->log( 'Create Temporary Index' );

		return $this->sendRequest( "$this->api_host/api/v2/search_engines/$this->hash/indices/$name/temp", array(
			'headers' => $this->authorization_header,
			'method'  => 'POST',
		) );
	}

	/**
	 * Deletes the temporary index. This also removes the lock in the search engine.
	 * If there is no temporary index this will return a 404 (Not found).
	 *
	 * @param string $name
	 *
	 * @throws Exception
	 */
	public function deleteTemporaryIndex( $name ) {
		$this->log->log( 'Delete Temporary Index' );

		return $this->sendRequest( "$this->api_host/api/v2/search_engines/$this->hash/indices/$name/temp", array(
			'headers' => $this->authorization_header,
			'method'  => 'DELETE',
		) );
	}

	/**
	 * Creates a new index for a search engine.
	 *
	 * @param string $body
	 *
	 * @throws Exception
	 */
	public function createIndex( $body ) {
		$this->log->log( 'Create Index' );

		return $this->sendRequest( "$this->api_host/api/v2/search_engines", array(
			'headers' => $this->authorization_header,
			'method'  => 'POST',
			'body'    => $body
		) );
	}

	/**
	 * Creates a list of items in the temporal index in a single bulk operation.
	 *
	 * @param string $name
	 * @param string $items
	 *
	 * @throws Exception
	 */
	public function createTempBulk( $name, $items ) {
		$this->log->log( 'Create Temporary Bulk' );

		return $this->sendRequest( "$this->api_host/api/v2/search_engines/$this->hash/indices/$name/temp/items/_bulk", array(
			'headers' => $this->authorization_header,
			'method'  => 'POST',
			'body'    => $items
		) );
	}


	/**
	 * Gets a search engine details.
	 */
	public function getSearchEngine() {
		$this->log->log( 'Get Search Engine' );

		return $this->sendRequest( "$this->api_host/api/v2/search_engines/$this->hash", array(
			'headers' => $this->authorization_header,
			'method'  => 'GET',
		) );
	}

	/**
	 * List all indices of search engine.
	 */
	public function listIndices() {
		$this->log->log( 'List Indices' );

		return $this->sendRequest( "$this->api_host/api/v2/search_engines/$this->hash/indices", array(
			'headers' => $this->authorization_header,
			'method'  => 'GET',
		) );
	}

	/**
	 * Replaces the content of the current "production" index with the content of the temporary one.
	 *
	 * @param string $name
	 *
	 * @throws Exception
	 */
	public function replace( $name ) {
		$this->log->log( 'Replace' );

		return $this->sendRequest( "$this->api_host/api/v2/search_engines/$this->hash/indices/$name/_replace_by_temp", array(
			'headers' => $this->authorization_header,
			'method'  => 'POST',
		) );
	}
}
