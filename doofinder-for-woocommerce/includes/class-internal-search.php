<?php

namespace Doofinder\WC;

use Doofinder\Api\Search\Client;
use Doofinder\Api\Search\Results;
use Doofinder\WC\Settings\Settings;

defined( 'ABSPATH' ) or die;

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
	 * Search term (if displaying a search page).
	 *
	 * @var string
	 */
	private $search;

	/**
	 * Internal_Search constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$multilanguage = Multilanguage::instance();

		// Load Internal Search settings
		$enabled = Settings::get( 'internal_search', 'enable' );

		$language_code = '';
		if ( $multilanguage->is_active() ) {
			$language_code = $multilanguage->get_default_language()['prefix'];
		}

		$this->api_key = Settings::get( 'internal_search', 'api_key', $language_code );
		$this->hashid  = Settings::get( 'internal_search', 'hashid' );

		// Check if the search is enabled and API Key and Hash ID are present
		$this->enabled = false;
		if ( 'yes' === $enabled && ! empty( $this->api_key ) && ! empty( $this->hashid ) ) {
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
		$this->client = new Client( $this->hashid, $this->api_key );
	}

	/**
	 * Perform a Doofinder search and modify WooCommerce query.
	 */
	public function search( $args ) {
		global $skip_internal_search;

		$this->init();

		// If we are not searching for anything, then just let WordPress do its thing
		$this->parse_search_term();
		if ( null === $this->search ) {
			return null;
		}

		// Perform a Doofinder search
		$results = $this->client->query( $this->search, null, array(
			'rpp' => 10000
		) );

		$ids = $this->ids_from_results( $results );

		// Remove WP search - we don't want WP and Doofinder search to overlap.
		unset( $args['s'] );

		// Only take posts with IDs returned from Doofinder.
		$args['post__in'] = $ids;
		$args['fields'] = 'ids';
		$args['orderby'] = 'post__in';

		// $skip_internal_search is a flag preventing firing the filter in nested queries.
		$skip_internal_search = true;
		$posts = new \WP_Query( $args );
		$skip_internal_search = false;

		return array(
			'ids' => $posts->posts,
			'found_posts' => $posts->found_posts,
			'max_num_pages' => $posts->max_num_pages
		);
	}

	/* Search parameters **********************************************************/

	/**
	 * Grab the term searched for and transform into value accepted by Doofinder API.
	 */
	private function parse_search_term() {
		if ( is_search() ) {
			$term = get_query_var( 's' );

			// Doofinder API does not accept empty string. Null displays all products.
			if ( empty( $term ) ) {
				$term = null;
			}

			$this->search = $term;
		}
	}

	/* Helpers ********************************************************************/

	/**
	 * Extract IDs from Doofinder Search Results.
	 *
	 * @param Results $results Results returned by Doofinder Search Client.
	 * @return array IDs of products.
	 */
	private function ids_from_results( Results $results ) {
		return array_map( function( $item ) {
			return $item['id'];
		}, $results->getResults() );
	}
}
