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
	 * Is the class set up, everything working
	 * and we can perform the search?
	 *
	 * @var bool
	 */
	private $working = true;

	/**
	 * Results of the performed search.
	 *
	 * @var Results
	 */
	private $results;

	/**
	 * How many posts per page are there in the results.
	 *
	 * @var int
	 */
	private $per_page;

	/**
	 * How many posts was there in the results in total.
	 *
	 * @var int
	 */
	private $found_posts;

	/**
	 * Total number of pages of results
	 *
	 * @var int
	 */
	private $pages;

	/**
	 * List of IDs of posts returned by Doofinder.
	 *
	 * @var int[]
	 */
	private $ids = array();


	/**
	 * Internal_Search constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$log = Transient_Log::instance();
		//$log->log( 'Internal Search - Start' );

		// Get global disable_api_calls flag
		$this->disable_api = Doofinder_For_WooCommerce::$disable_api_calls ?? $this->disable_api;

		if ($this->disable_api) {
			$log->log( '==== API IS DISABLED ====' );
		}

		$multilanguage = Multilanguage::instance();

		// Load Internal Search settings
		$enabled = Settings::get( 'internal_search', 'enable' ); // Per language setting+

		$language_code = '';
		$lang = '';
		if ( $multilanguage->is_active() ) {
			$default_language = $multilanguage->get_default_language();
			$language_code = $default_language['prefix'] ?? '';
			$lang = 'all';
		}

		$this->api_key = Settings::get( 'internal_search', 'api_key', $lang ); // Global setting
		$this->hashid = Settings::get( 'internal_search', 'hashid' ); // Per language setting
		$this->server = Settings::get( 'internal_search', 'search_server', $lang ); // Global setting

		$log->log('Hash ID: '.$this->hashid.', API-KEY: '.$this->api_key.', SERVER: ' . $this->server);
		


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

		try {
			$this->client = new Client( $this->server, $this->api_key );
		} catch ( \Exception $exception ) {
			$this->working = false;
			$log->log( $exception );
		}

		$log->log( 'Internal Search - Crate Api Client' );
	}

	/**
	 * Check the status of the search.
	 *
	 * If this returns true that means everything is ok and
	 * it's safe to perform search.
	 *
	 * @return bool
	 */
	public function is_ok() {
		return $this->working;
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
	 * NOTICE: This is older search replaced with search_new
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
			$log->log( 'Search term not found.' );

		}

		// Perform a Doofinder search
		
		$searchParams = [
			"hashid" => $this->hashid,
			"query" => $this->search,
			"page" => 1,
			'rpp' => 100
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

			// If no ids where found via Doofinder return early with no results
			if(empty($ids)) {
				return array(
					'ids'           => [],
					'found_posts'   => 0,
					'max_num_pages' => 0,
				);
			}
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

	/* New search ******************************************************************/

	/**
	 * Perform a Doofinder search, grab results and extract
	 * from the results all data that will be interesting
	 * to other classes (list of post ids, number of pages
	 * of results, etc).
	 *
	 * @param string $query
	 * @param int    $page
	 * @param int    $per_page
	 */
	public function search_new( $query, $page = 1, $per_page = 100 ) {

		$log = Transient_Log::instance();
		
		$log->log( 'Start Doofinder search new' );
		$this->init();

		if ( null === $query ) {
			$log->log( 'Search term not found.' );
		}

		$this->per_page = $per_page;

		$log->log( 'Per page: ' .$per_page );
		// Doofinder API throws exceptions when anything goes wrong./
		// We don't actually need to handle this in any way. If search
		// throws an exception, the list of IDs will be empty
		// and Internal Search will display empty list of results.
		try {

			$queryParams = apply_filters(
				'woocommerce_doofinder_internal_search_params', 
				[
					"hashid" => $this->hashid,
					"query" => $query,
					"page" => $page,
					'rpp' => $per_page
				],
				$this
			);

			$log->log( 'Internal Search - Search: ' );
			$log->log( $queryParams );

			
			if(!$this->disable_api) {
				$log->log('=== API CALL === ');
				$this->results = $this->client->search( $queryParams );
				$log->log(' Search successfull ');
				
			} else {
				$this->results = [];
			}

			$this->extract_ids();
			$this->calculate_totals();

			$log->log( 'Calling Doofinder API. Results:' );
			$log->log( $this->results  );

			$log->log( sprintf(
				'Extracted ids: %s',
				join( ', ', $this->ids )
			) );


		} catch ( \Exception $exception ) {
			$log->log( 'Internal Search - Exception' );
			$log->log( 'There is a problem with Doofinder Search. Error:' );
			$log->log( $exception->getMessage() );
			$log->log( 'Falling back to default wordpress search.' );

			$this->working = false;

		}
	}

	/**
	 * Retrieve the list of ids of posts returned by the search.
	 *
	 * @return int[]
	 */
	public function get_ids() {
		return $this->ids;
	}

	/**
	 * Retrieve the number of posts found by Doofinder.
	 *
	 * @return int
	 */
	public function get_total_posts() {
		return $this->found_posts;
	}

	/**
	 * How many pages of posts did Doofinder find?
	 *
	 * @return int
	 */
	public function get_total_pages() {
		return $this->pages;
	}

	/**
	 * Go through the results from Doofinder and generate
	 * a list of post IDs based on the returned results.
	 */
	private function extract_ids() {
		$results   = $this->results->getResults();
		$this->ids = array();
		foreach ( $results as $result ) {
			$this->ids[] = $result['id'];
		}
	}

	/**
	 * Determine how many posts the search returned and how many
	 * pages of results there are.
	 */
	private function calculate_totals() {
		$this->found_posts = $this->results->getProperty( 'total' );
		$this->pages       = ceil( $this->found_posts / $this->per_page );
	}


	/* Tracking *******************************************************************/

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
