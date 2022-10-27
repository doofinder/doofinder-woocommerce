<?php

namespace Doofinder\WC\Api;

use Doofinder\Management\ManagementClient as Client;
use Doofinder\Api\Management\SearchEngine;
use Doofinder\WC\Doofinder_For_WooCommerce;
use Doofinder\WC\Indexing_Data;
use Doofinder\WC\Log;
use Doofinder\WC\Settings\Settings;
use Doofinder\WC\Multilanguage\Language_Plugin;
use Doofinder\WC\Multilanguage\Multilanguage;

use Doofinder\Management\Errors\NotAllowed;
use Doofinder\Management\Errors\NotFound;
use Doofinder\Management\Errors\BadRequest;
use Doofinder\Management\Errors\DoofinderError;

defined( 'ABSPATH' ) or die();

class Doofinder_Api implements Api_Wrapper {

	/**
	 * Instance of a class used to log to a file.
	 *
	 * @var Log
	 */
	private $log;

	/**
	 * Search engine we'll index the items with.
	 *
	 * @var SearchEngine
	 */
	public $search_engine;

	/**
	 * Search engine api status
	 *
	 * @var string
	 */
	public $search_engine_api_status;

	/**
	 * Current language
	 *
	 * @var string
	 */
	public $language;


	/**
	 * Api key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Api host
	 *
	 * @var string
	 */
	private $api_host;


	/**
	 * Search enginde Hash ID
	 *
	 * @var string
	 */
	private $hash;

	/**
	 * Client we'll index the items with.
	 *
	 * @var ManagmentClient
	 */
	private $client;

	/**
	 * Api calls count
	 *
	 * @var int;
	 */
	private $api_calls = 0;

	/**
	 * Disable api calls for debugging and testing. Set to false for production.
	 *
	 * @var int;
	 */
	private $disable_api = false;


	public function __construct($language = null) {
		$this->language = $language;

		// Get global disable_api_calls flag
		$this->disable_api = Doofinder_For_WooCommerce::$disable_api_calls ?? $this->disable_api;

		$this->log = new Log('api.txt' );
		//$this->log->log( '------------- Doofinder API construct ------------' );

		if ($this->disable_api) {
			$this->log->log( '-------------  API IS DISABLED ------------- ' );
		}

		$this->api_key = Settings::get_api_key();
		$this->api_host = Settings::get_api_host();
		$this->hash = Settings::get_search_engine_hash($language);


		if ( ! $this->api_key || ! $this->hash || !$this->api_host ) {
			$this->log->log( 'Doofinder Api: Api key or Api host or Hash ID is missing.' );

			return Api_Status::$unknown_error;
		}

		$this->client = false;
		$this->search_engine = false;

		try {
			$this->log->log( 'Crate Api Client' );
			$this->log->log( 'API Key: ' . $this->api_key );
			$this->log->log( 'API Host: ' . $this->api_host );
			$this->client = new Client($this->api_host, $this->api_key);
		} catch ( \Exception $exception ) {
			$this->log->log( $exception->getMessage() );

			if ( $exception instanceof DoofinderError ) {
				$this->log->log( $exception->getBody() );
			}
		}

		if ( $this->client ) {
			$this->client = new Throttle( $this->client );
			//$this->log->log( 'Wrap Client in Throttle' );
		}

		try {
			$this->search_engine = $this->get_search_engine();
			$this->log->log($this->search_engine);
		} catch ( NotFound $exception ) {

			$this->log->log( 'Could not get search engine - Not Found' );
			$this->log->log( 'Status code: ' . $exception->getCode() );
			$this->log->log( $exception->getMessage() );
			$this->log->log( get_class($exception));

			if ( $exception instanceof DoofinderError ) {
				$this->log->log( $exception->getBody() );
			}

			$this->search_engine_api_status = Api_Status::$invalid_search_engine;

		} catch ( NotAllowed $exception ) {

			$this->log->log( 'Could not get search engine - Not Allowed' );
			$this->log->log( 'Status code: ' . $exception->getCode() );
			$this->log->log( $exception->getMessage() );
			$this->log->log( get_class($exception));

			if ( $exception instanceof DoofinderError ) {
				$this->log->log( $exception->getBody() );
			}

			$this->search_engine_api_status =  Api_Status::$not_authenticated;

		} catch ( \Exception $exception ) {

			$this->log->log( 'Could not get search engine - Unknown' );
			$this->log->log( 'Status code: ' . $exception->getCode() );
			$this->log->log( $exception->getMessage() );
			$this->log->log( get_class($exception));

			if ( $exception instanceof DoofinderError ) {
				$this->log->log( $exception->getBody() );
			}

			$this->search_engine_api_status =  Api_Status::$unknown_error;

		}

		//$this->log->log( '-------------  Doofinder API End construct ------------- ' );
	}

	/**
	 * Update the data of a single item in the API.
	 *
	 * @param string $item_type
	 * @param int    $id
	 * @param array  $data
	 * @param int 	 $update_time Timestamp of the update time
	 *
	 * @return mixed
	 */
	public function update_item( $item_type, $id, $data, $update_time = null ) {
		$this->log->log('Update Item' . "\n");
		// Doofinder API throws exceptions if something goes wrong.
		try {
			if ( ! $this->search_engine ) {
				$this->log->log( 'Update Item: Invalid search engine.' );

				return $this->search_engine_api_status;
			}

			// Update item in Doofinder index.

			$this->log->log('Update Item - Try update item' . "\n");
			$this->log->log( $item_type );
			$this->log->log( $id );
			$this->log->log( $data );
			$this->log->log( $this->hash );

			if (!$this->disable_api) {
				$this->log->log('=== API CALL === ');
				$this->client->updateItem($this->hash, $id, $item_type, json_encode($data));
				$this->api_calls++;
			}
			Settings::set_last_modified_index($this->language, $update_time);

			$this->log->log('Update Item - Item updated' . "\n");
			return Api_Status::$success;

		} catch ( BadRequest $exception ) {
			// If updating item failed it might mean that the post does not exist in the
			// index yet, so we will try to create it instead

			$this->log->log('Update Item - Exception 1' . "\n");
			$this->log->log( $exception->getMessage() );

			if ( $exception instanceof DoofinderError ) {
				$this->log->log( $exception->getBody() );
			}

			// Item type may not exist, but is required.
			// Let's create it.

			try {
				// Lets first try to create type if it doesn't exist
				$this->maybe_create_type( $item_type );

				$this->log->log('Update Item - Try Create item' . "\n");

				if (!$this->disable_api) {
					$this->log->log('=== API CALL === ');
					$this->client->createItem($this->hash, $item_type, json_encode($data));
					$this->api_calls++;
				}
				Settings::set_last_modified_index($this->language, $update_time);

				$this->log->log('Update Item - Item created' . "\n");
				return Api_Status::$success;

			} catch ( \Exception $exception ) {

				$this->log->log('Update Item - Item does not exist or cannot create item.' . "\n");
				$this->log->log( $exception->getMessage() );

				if ( $exception instanceof DoofinderError ) {
					$this->log->log( $exception->getBody() );
				}

				return Api_Status::$bad_request;

			}

		} catch ( \Exception $exception ) {
			$this->log->log('Update Item - Exception 2' . "\n");
			$this->log->log( get_class($exception));
			$this->log->log( $exception->getMessage() );

			if ( $exception instanceof DoofinderError ) {
				$this->log->log( $exception->getBody() );
			}

			return Api_Status::$unknown_error;
		}
	}

	/**
	 * Remove given item from indexing.
	 *
	 * @param string $item_type
	 * @param int    $id
	 * @param int $update_time Timestamp of the update time
	 *
	 * @return mixed
	 */
	public function remove_item( $item_type, $id, $update_time = null ) {
		$this->log->log('Remove item' . "\n");
		// Doofinder API throws exceptions if something goes wrong.
		try {
			if ( ! $this->search_engine ) {
				$this->log->log( 'Remove item: Invalid search engine.' );

				return $this->search_engine_api_status;
			}

			// Remove item from Doofinder index.

			$this->log->log('Remove Item - Try delete item' . "\n");
			$this->log->log( $item_type );
			$this->log->log( $id );
			$this->log->log( $this->hash );

			if (!$this->disable_api) {
				$this->log->log('=== API CALL === ');
				$this->client->deleteItem($this->hash, $id, $item_type);
				$this->api_calls++;
			}
			Settings::set_last_modified_index($this->language, $update_time);

			$this->log->log('Remove Item - Item deleted' . "\n");
			return Api_Status::$success;

		} catch ( \Exception $exception ) {
			$this->log->log('Remove Item - Exception' . "\n");
			$this->log->log( get_class($exception));
			$this->log->log( $exception->getMessage() );

			if ( $exception instanceof DoofinderError ) {
				$this->log->log( $exception->getBody() );
			}

			return Api_Status::$unknown_error;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function send_batch( $items_type, array $items, $language = null ) {
		$this->log->log( 'Start Send Batch' . "\n" );
		$this->log->log( 'Send batch - items type: "' . $items_type .'"' );
		$this->log->log( 'Send batch - language: "' . $language . '"' );

		// Doofinder API will throw an exception in case of invalid token
		// or something like that.

		// Check if we need to add the type in our own status.
		// This should reduce the number of requests made to the
		// Doofinder API.
		$indexing_data = Indexing_Data::instance();

		try {
			if ( ! $this->search_engine ) {
				$this->log->log( 'Send batch: Invalid search engine.' );
				$this->log->log( 'Send batch: API Status: ' . $this->search_engine_api_status );
				return $this->search_engine_api_status;
			}

			$this->log->log( 'Send Batch  - Temp Index Status' );
			$this->log->log( $indexing_data->get('temp_index') );

			if ( ! $indexing_data->has( 'temp_index', $items_type ) ) {
				try {
					$this->create_index($items_type);
				} catch (\Exception $ex) {
					if($ex->getMessage() === Api_Status::$unknown_error){
						return Api_Status::$unknown_error;
					}
				}
			}

			$this->log->log( 'Send Batch  - Before Create Bulk Temp Index Status' );
			
			$temp_index = $indexing_data->get('temp_index');
			$this->log->log( $temp_index );
			$this->log->log(json_encode($items));

			if (!$this->disable_api) {
				$this->log->log('=== API CALL === ');
				$this->client->createTempBulk(
					$this->hash,
					$items_type, //index_name
					json_encode($items)
				);
				$this->api_calls++;
			}

			$this->log->log( 'Send batch - Batch Sent' );
			$this->log->log( 'Send batch - API CALLS ------  : ' . $this->api_calls  );

			return Api_Status::$success;

		} catch ( NotFound $exception ) {
			//The API returned that the temporary index wasn't found, we'll reset temp_index and call send_batch again
			$this->log->log( 'Temp index not found, reset temp_index and retry' . "\n" );
			$indexing_data->set( 'temp_index', [], true );
			return $this->send_batch( $items_type, $items, $language );
		} catch ( \Exception $exception ) {
			$this->log->log( 'Send Batch - Exception 1' . "\n" );
			$this->log->log( get_class($exception));
			$this->log->log( $exception->getMessage() );

			if ( $exception instanceof DoofinderError ) {
				$this->log->log( $exception->getBody() );

				// Show doofinder api response message for user in the backend
				$response_status = Api_Status::get_api_response_status($exception->getMessage(),$exception->getBody());
				if ( $response_status ) {
					return $response_status;
				}
			}

			return Api_Status::$unknown_error;
		}

		$this->log->log('End Send Batch' . "\n");
	}

	public function create_index($items_type){
		$this->log->log('Send batch - createTemporaryIndex' . "\n");
		// Try to create the temp index.
		try {
			$this->create_temporary_index($items_type);
		} catch ( NotFound $exception ) {
			// If real index does not exists creating temp index will fail,
			// So we need to create real index first
			$this->create_real_index($items_type);

			// Finally try to create temp index
			try {
				$this->create_temporary_index($items_type);
			} catch ( \Exception $exception ) {
				// For some reason Index could not be created.
				$this->log->log('Send batch - Temp Index NOT Created' . "\n");
				$this->log->log( get_class($exception));
				$this->log->log( $exception->getMessage() );

				if ( $exception instanceof DoofinderError ) {
					$this->log->log( $exception->getBody() );
				}
				throw new \Exception(Api_Status::$unknown_error);
				
			}
		} catch ( \Exception $exception ) {
			// Temp Index could not be created it probably exists already. Move on.
			$this->log->log('Send batch - Temp Index probably exists already' . "\n");
			$this->log->log( get_class($exception));
			$this->log->log( $exception->getMessage() );

			if ( $exception instanceof DoofinderError ) {
				$this->log->log( $exception->getBody() );
			}
		}
	}

	public function create_temporary_index($items_type){
		$indexing_data = Indexing_Data::instance();

		$this->log->log('Send batch - Try to create Temp Index' . "\n");
		if (!$this->disable_api) {
			$this->log->log('=== API CALL === ');
			$this->client->createTemporaryIndex( $this->hash,  $items_type );
			$this->api_calls++;
		}
		$this->log->log('Send batch - Temp Index Created' . "\n");

		// Mark it in our status.
		$indexing_data->set( 'temp_index', $items_type );
	}

	public function create_real_index($items_type){
		$this->log->log('Send batch - Exception - Real Index Not Found' . "\n");

		try {
			$this->log->log('Send batch - Try to create Real Index' . "\n");
			if (!$this->disable_api) {
				$this->log->log('=== API CALL === ');
				// Prepare index data
				$body = [
					'name' => $items_type,
					'preset' => 'product'
				];
				$this->client->createIndex( $this->hash, json_encode($body) );
				$this->api_calls++;
			}
			$this->log->log('Send batch - Real Index Created' . "\n");
		} catch ( \Exception $exception ) {
			// For some reason Index could not be created.
			$this->log->log('Send batch - Real Index NOT Created' . "\n");
			$this->log->log( get_class($exception));
			$this->log->log( $exception->getMessage() );

			if ( $exception instanceof DoofinderError ) {
				$this->log->log( $exception->getBody() );
			}			
			throw new \Exception(Api_Status::$unknown_error);				
		}
	}

	/**
	 * @inheritdoc
	 */
	public function remove_types() {
		$this->log->log('Remove types' . "\n");

		// This is not needed in API v2, because of using temp indexes

		/* if ( ! $this->search_engine ) {
			$this->log->log( 'Remove types: Invalid search engine.' );

			return $this->search_engine_api_status;
		}

		// Doofinder API will throw an exception in case of invalid token
		// or something like that.
		try {
			$this->search_engine->deleteType( Settings::get_post_types_to_index() );

			return Api_Status::$success;
		} catch ( \Exception $exception ) {
			// This exception is thrown after we've sent request to delete a type
			// and the server is not finished processing it yet.
			// We'll have to retry the request in a moment.

			$this->log->log( $exception->getMessage() );

			return Api_Status::$indexing_in_progress;
		} catch ( \Exception $exception ) {
			$this->log->log( $exception->getMessage() );

			return Api_Status::$unknown_error;
		} */
		return null;
	}

	/**
	 * Retrieve search engine based on keys from settings.
	 *
	 * @return SearchEngine
	 * @throws \Exception
	 */
	private function get_search_engine() {
		$this->log->log('Start Get search engine' . "\n");

		if (!$this->disable_api) {
			$this->log->log('=== API CALL === ');
			/** @var SearchEngine[] $search_engine */
			$this->log->log( 'Get search engine - hash: ' . $this->hash);

			$search_engine = $this->client->getSearchEngine($this->hash);
			$this->api_calls++;
		} else {
			$search_engine = true;
		}

		$this->log->log('Search Engine: ');
		$this->log->log($search_engine);

		if ($search_engine) {
			$this->log->log( 'End Get search engine - success' . "\n" );
			return $search_engine;
		}

		$this->log->log( 'End Get search engine - failed' . "\n");
		// We have not found the selected search engine.
		// Most likely user provided a wrong hash.
		return null;
	}

	/**
	 * Add a type to the index, if the type does not exist yet.
	 *
	 * @param string $item_type
	 */
	private function maybe_create_type( $item_type ) {
		$this->log->log('Maybe create type : ' . $item_type . "\n");
		$this->log->log('Maybe create type - listIndices' . "\n");

		if (!$this->disable_api) {
			$this->log->log('=== API CALL === ');
			try {
				$types = $this->client->listIndices($this->hash);
				$this->api_calls++;
			} catch (\Exception $exception) {
				$this->log->log('Maybe create type - Exception 1');
				$this->log->log( $exception->getMessage() );

				if ( $exception instanceof DoofinderError ) {
					$this->log->log( $exception->getBody() );
				}
			}
		}

		$this->log->log('Indices: ' . "\n");

		$typesList = [];

		foreach($types as $type) {
			$typesList[] = $type->name;
		}

		$this->log->log( $typesList );


		if ( ! in_array( $item_type, $typesList ) ) {
			$this->log->log('Maybe create type - createIndex' . "\n");

			if (!$this->disable_api) {
				$this->log->log('=== API CALL === ');

				// Prepare request body
				$body = [
					'name' => $item_type,
					'preset' => $item_type
				];

				try {
					$this->client->createIndex( $this->hash,  json_encode($body) );
					$this->api_calls++;
				} catch (\Exception $exception) {
					// The index probably exists already or could not be created
					// Move on
					$this->log->log('Maybe create type - Exception 2');
					$this->log->log( $exception->getMessage() );

					if ( $exception instanceof DoofinderError ) {
						$this->log->log( $exception->getBody() );
					}
				}
			}
		} else {
			$this->log->log('Maybe create type - index already exists' . "\n");
		}
	}

	/**
	 * Replace real index with temporary one.
	 *
	 * @param string $index_name Name of the index to replace
	 */
	public function replace_index( $index_name ) {
		$this->log->log('Replace Index Start' . "\n");

		try {
			if ( ! $this->search_engine ) {
				$this->log->log( 'Replace Index - Invalid search engine.' );

				return $this->search_engine_api_status;
			}
			$indexing_data = Indexing_Data::instance();

			// Clear internal status of the temp index
			$this->log->log( 'Replace Index - Clear Inner Temp Index Status' );
			$indexing_data->set( 'temp_index', [], true );
			$this->log->log( $indexing_data->get( 'temp_index'));

			// Replace index
			$this->log->log( 'Replace Index - Replace with: ' . $index_name  );

			if (!$this->disable_api) {
				$this->log->log('=== API CALL === ');
				$this->client->replace($this->hash, $index_name );
				$this->api_calls++;
			}
			Settings::set_last_modified_index($this->language);

			$this->log->log( 'Replace Index - "'.$index_name.'" index replaced successfully' );
			$this->log->log( 'Replace Index - API CALLS ------  : ' . $this->api_calls  );

			return Api_Status::$success;

		} catch ( \Exception $exception ) {
			$this->log->log('Replace Index - Exception' . "\n");
			$this->log->log( get_class($exception));
			$this->log->log( $exception->getMessage() );

			if ( $exception instanceof DoofinderError ) {
				$this->log->log( $exception->getBody() );
			}

			return Api_Status::$unknown_error;
		}
	}


	/**
	 * Create index body array used as parameter in  $this->client->createIndex($has,$index_body)
	 *
	 * @param string $item_type
	 */
	public function create_index_body( $item_type ) {

		$index_body = [
			'name' => $item_type,
			'preset' => $item_type
		];
		$index_body = json_encode($index_body);

		$this->log->log('Index body: ' . $index_body);

		return $index_body;
	}
}
