<?php

namespace Doofinder\WC;

use Doofinder\Search\Client;
use Doofinder\Search\Results;
use Doofinder\WC\Settings\Settings;

use WP_Error;

defined( 'ABSPATH' ) or die;

// TODO Implement session id creating/handling/storing for stats requests in Doofinder when migrating Banners to API v2
class Internal_Search {

	/**
	 * Doofinder Search Client instance.
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * Is the Internal Search enabled?
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Doofinder API Key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Hash ID of the search engine handling current language.
	 *
	 * @var string
	 */
	private $hashid;

	/**
	 * Search session ID of the search engine handling current language.
	 *
	 * @var string
	 */
	private $sessionid = null;

	/**
	 * Server of the search engine.
	 *
	 * @var string
	 */
	private $server;

	/**
	 * Search term (if displaying a search page).
	 *
	 * @var string
	 */
	private $search;

	/**
	 * Some search results return a banner that should be displayed at the top
	 * of the results.
	 *
	 * @var array
	 */
	private $banner;

	/**
	 * Should api be disabled for local testing
	 *
	 * @var bool
	 */
	private $disable_api = false;

	/**
	 * Internal_Search constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$log = Transient_Log::instance();
		$log->log( 'Internal Search  _construct ' );

		// Get global disable_api_calls flag
		$this->disable_api = Doofinder_For_WooCommerce::$disable_api_calls ?? $this->disable_api;

		if ($this->disable_api) {
			$log->log( '==== API IS DISABLED ====' );
		}

		$multilanguage = Multilanguage::instance();

		// Load Internal Search settings
		$enabled = Settings::get( 'internal_search', 'enable' );

		$language_code = '';
		if ( $multilanguage->is_active() ) {
			$default_language = $multilanguage->get_default_language();
			$language_code = $default_language['prefix'] ?? '';
		}

		$this->api_key = Settings::get( 'internal_search', 'api_key', 'all'); // Global setting
		$this->hashid = Settings::get( 'internal_search', 'hashid' ); // Per language setting
		$this->server = Settings::get( 'internal_search', 'search_server' , 'all'); // Global setting


		// Check if the search is enabled and API Key and Hash ID and Server are present
		$this->enabled = false;
		if ( 'yes' === $enabled && ! empty( $this->api_key ) && ! empty( $this->hashid ) && ! empty( $this->server ) ) {
			$this->enabled = true;
		}
	}

	/**
	 * Check if the Internal Search is enabled for the current language
	 * and that API Key and Hash ID are present.
	 *
	 * @return bool Is the Internal Search enabled?
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Initialize Doofinder Search Client.
	 */
	private function init() {
		$log = Transient_Log::instance();
		$log->log( sprintf(
			'Creating Doofinder Search client: Hash ID: %s, API-KEY: %s, SERVER: %s',
			$this->hashid,
			$this->api_key,
			$this->server
		) );

		$this->client = new Client( $this->server, $this->api_key );

		$log->log( 'Internal Search - Crate Api Client' );
	}

	/**
	 * 
	 * Starts a session in doofinder search server
	 * 
	 */
	public function initSession(){
		if ( ! $this->client ) {
			$this->init();
		}

		$this->client->registerSession($this->getSessionId(), $this->hash);
	}

	/**
	 * Retrieve session id 
	 * 
	 * @return mixed Session string or null
	 */
	public function getSessionId($refresh = false) {
		if ( ! $this->client ) {
			$this->init();
		}

		if(!$this->sessionId || $refresh){		  
			$this->sessionId = $this->client->createSessionId();
		}

		return $this->sessionid;
	}

	/**
	 * Clean session id
	 * 
	 */
	public function cleanSession(){
		$this->sessionId = null;
	}

	/**
	 * Perform a Doofinder search and modify WooCommerce query.
	 */
	public function search( $args ) {
		global $skip_internal_search;

		$log = Transient_Log::instance();

		$log->log( 'start Doofinder Search' );
		$this->init();

		// If we are not searching for anything, then just let WordPress do its thing
		$this->parse_search_term();
		if ( null === $this->search ) {
			$log->log( 'Search term not found. Aborting.' );

			return null;
		}

		// Perform a Doofinder search
		
		$searchParams = [
			"hashid" => $this->hashid,
			"query" => $this->search,
			"page" => null,
			'rpp' => 10000
		];
		
		$results = null;

		try {
			$log->log( 'Internal Search - Search: ' );
			$log->log( $searchParams );

			if(!$this->disable_api) {
				$log->log('=== API CALL === ');
				$results = $this->client->search( $searchParams );
				$log->log(' Search successfull ');
				
			} else {
				$results = [];
			}

		} catch (\Exception $exception) {
			$log->log( 'Internal Search - Exception' );
			$log->log( 'There is a problem with Doofinder Search. Error:' );
			$log->log( $exception->getMessage() );
			
			wp_die("There is a problem with Doofinder Search. Error: " . $exception->getMessage());
		}
		
		$log->log( 'Calling Doofinder API. Results:' );
		$log->log( $results );

		$ids = [];
		
		if ( $results instanceof Results ) {

			// Store a banner for later use
			$this->banner = $results->getProperty( 'banner' );

			// Process the search results we got from Doofinder
			$ids = $this->ids_from_results( $results );
			$log->log( sprintf(
				'Extracted ids: %s',
				join( ', ', $ids )
			) );
		}

		// Remove WP search - we don't want WP and Doofinder search to overlap.
		unset( $args['s'] );

		// Only take posts with IDs returned from Doofinder.
		$args['post__in'] = $ids;

		// If we're splitting variable products they are being exported as separate entries
		// in the feed, and the JS Layer will display them as separate products.
		// In order to keep Internal Search consistent and display them as separate products
		// there too we need make sure to query for them too.
		$args['post_type'] = ['product', 'product_variation'];

		$args['fields'] = 'ids';
		$args['orderby'] = 'post__in';

		// $skip_internal_search is a flag preventing firing the filter in nested queries.
		$log->log( 'start nested search' );
		$skip_internal_search = true;
		$posts = new \WP_Query( $args );
		$skip_internal_search = false;
		$log->log( 'end nested search' );

		$log->log( 'Doofinder Search completed.' );

		return array(
			'ids'           => $posts->posts,
			'found_posts'   => $posts->found_posts,
			'max_num_pages' => $posts->max_num_pages,
		);
	}

	/**
	 * Retrieve the banner we obtained when making a Doofinder search.
	 *
	 * This will be null if the search did not contain a banner.
	 *
	 * @since 1.3.0
	 * @return array
	 */
	public function getBanner() {
		return $this->banner;
	}

	/* Tracking *******************************************************************/

	
	/**
	 * Track banner impression.
	 * 
	 * @since 1.3.0
	 */
	public function trackBannerImpression() {
		// NOTE: This is not used anymore in API v2
		// if ( ! $this->banner || ! $this->banner['id'] ) {
		// 	return;
		// }
		// $this->client->registerBannerDisplay( (int) $this->banner['id'] );
		return;
	}

	/**
	 * Track banner click.
	 *
	 * @since 1.3.0
	 *
	 * @param int $bannerId
	 */
	public function trackBannerClick( $bannerId ) {
		if ( ! $this->client ) {
			$this->init();
		}
		// TODO Migrate this to API v2
		//$this->client->registerBannerClick( $bannerId );
	}

	/* Search parameters **********************************************************/

	/**
	 * Grab the term searched for and transform into value accepted by Doofinder API.
	 */
	private function parse_search_term() {
		$log = Transient_Log::instance();
		$log->log( 'looking for search term' );

		if ( ! is_search() ) {
			$log->log( 'not a search page, aborting' );

			return;
		}

		$term = get_query_var( 's' );

		// Doofinder API does not accept empty string. Null displays all products.
		if ( empty( $term ) ) {
			$log->log( 'found empty search' );

			$term = null;
		}

		$log->log( sprintf( 'search term found: %s', $term ) );
		$this->search = $term;
	}

	/* Helpers ********************************************************************/

	/**
	 * Extract IDs from Doofinder Search Results.
	 *
	 * @param Results $results Results returned by Doofinder Search Client.
	 *
	 * @return array IDs of products.
	 */
	private function ids_from_results( Results $results ) {
		return array_map( function ( $item ) {
			return $item['id'];
		}, $results->getResults() );
	}
}
