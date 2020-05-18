<?php

namespace Doofinder\WC;

use Doofinder\WC\Data_Feed\Data_Feed_Item;
use Doofinder\WC\Data_Feed\Feed_XML;
use Doofinder\WC\Settings\Settings;

defined( 'ABSPATH' ) or die;

class Data_Feed {

	/**
	 * Language code of the language to display feed for.
	 *
	 * @var string
	 */
	private $language;

	/**
	 * The feed being generated.
	 *
	 * @var Feed_XML
	 */
	private $feed;

	/**
	 * List of products to be included in the feed.
	 *
	 * @var array
	 */
	private $products;

	/**
	 * A cache of all product variations.
	 *
	 * @var array
	 */
	private $product_variations;

	/**
	 * True if displaying the beginning of products list.
	 *
	 * @var bool
	 */
	private $is_first = false;

	/**
	 * True if displaying the end of products list.
	 *
	 * @var bool
	 */
	private $is_last = false;

	/**
	 * Contains already traversed paths from the term to its oldest parent.
	 * In case some products have the exact same category we don't have to traverse them
	 * separately, just cache once and then read from cache in case of following products.
	 *
	 * @var array
	 */
	private $paths_cache = array();

	/**
	 * WP_Term object stores a term parent only as an ID, and only the closest ancestor.
	 * Loading parent terms each time we need them would create a lot of redundant DB calls,
	 * therefore we load all terms once at the beginning.
	 *
	 * @var array
	 */
	private $terms_cache = array();

	/**
	 * Data Feed settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Register the feed with the WordPress feeds.
	 * Necessary to be able to access the feed URL.
	 *
	 * @since 1.0.0
	 */
	public static function register() {
		$class = __CLASS__;
		add_feed( 'doofinder', function () use ( $class ) {
			$feed = new $class();
			$feed->generate();
		} );
	}

	/* Initialization *************************************************************/

	/**
	 * Data_Feed constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $language Language of the feed to show.
	 */
	public function __construct() {
		$multilanguage  = Multilanguage::instance();
		$this->language = $multilanguage->get_language_code();

		// Create XML document to fill out.
		$this->feed = new Feed_XML();

		// Load settings.
		$this->settings = array(
			// Doofinder settings
			'export_prices'  => Settings::get( 'feed', 'export_prices', $this->language ),
			'export_tags'    => Settings::get( 'feed', 'export_tags', $this->language ),
			'image_size'     => Settings::get( 'feed', 'image_size', $this->language ),
			'split_variable' => Settings::get( 'feed', 'split_variable', $this->language ),
			'protected'      => Settings::get( 'feed', 'password_protected', $this->language ),
			'password'       => Settings::get( 'feed', 'password', $this->language ),

			// WooCommerce settings
			'include_taxes'  => ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) ),
		);

		// Load required data from DB.
		foreach ( get_terms( 'product_cat' ) as $term ) {
			$this->terms_cache[ $term->term_id ] = $term;
		}

		$this->load_products();
		$this->load_product_variations();
	}

	/**
	 * Load all the products to be included in the feed from DB.
	 *
	 * @since 1.0.0
	 */
	private function load_products() {
		global $woocommerce;

		$args = array(
			'post_type'   => 'product',
			'post_status' => 'publish',

			'ignore_sticky_posts' => 1,

			'posts_per_page' => - 1,

			'orderby' => 'ID',
			'order'   => 'ASC',

			'cache_results'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		// Visibility
		// We should only show products that have catalog visibility set to search.
		// However different WooCommerce version store it in different places.

		// Version 3+
		// Since version 3.0.0 catalog visibility became a taxonomy.
		// Whenever the product is hidden from search the term "exclude-from-search"
		// is added.
		if ( version_compare( $woocommerce->version, '3.0.0', '>=' ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => array( 'exclude-from-search' ),
					'operator' => 'NOT IN',
				),
			);
		}

		// Version 2+
		// In older versions visibility was stored as a post meta on the product.
		else {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => '_visibility',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_visibility',
					'value'   => array( 'search', 'visible' ),
					'compare' => 'IN',
				),
				array(
					'key'   => '_visibility',
					'value' => '',
				),
			);
		}

		// GET parameters
		// 'limit' and 'offset' parameters create pagination.
		$limit = 0;
		if ( isset( $_GET['limit'] ) && ! empty( $_GET['limit'] ) ) {
			$limit                  = (int) $_GET['limit'];
			$args['posts_per_page'] = $_GET['limit'];
		}

		$offset = 0;
		if ( isset( $_GET['offset'] ) && ! empty( $_GET['offset'] ) ) {
			$offset         = (int) $_GET['offset'];
			$args['offset'] = $_GET['offset'];
		}

		$query          = new \WP_Query( $args );
		$this->products = $query->posts;

		// Check if this is the beginning or end of the feed
		if ( 0 === $offset ) {
			$this->is_first = true;
		}

		if ( ! isset( $_GET['limit'] ) || $offset + $limit >= $query->found_posts ) {
			$this->is_last = true;
		}
	}

	/**
	 * Load all variations from DB.
	 *
	 * @since 1.0.0
	 */
	private function load_product_variations() {
		if ( 'yes' !== $this->settings['split_variable'] ) {
			return;
		}

		$variations = get_posts( array(
			'post_type'      => 'product_variation',
			'posts_per_page' => - 1,

			// Only load variations for the loaded products.
			// If there are no products (e.g. offset value is too high, and we are past all
			// products) passing a negative value will ensure non variations will be loaded.
			// If we passed an empty array WordPress would load *all* variations.
			'post_parent__in' => empty( $this->products ) ? [-1] : array_map( function ( \WP_Post $post ) {
				return $post->ID;
			}, $this->products )
		) );

		// Index by ID in order to avoid iterating the entire array when we need to retrieve a variation
		$this->product_variations = array();
		foreach ( $variations as $variation ) {
			$this->product_variations[ $variation->ID ] = $variation;
		}
	}

	/* Feed generation ************************************************************/

	/**
	 * Build an XML document and print it out.
	 *
	 * @since 1.0.0
	 */
	public function generate() {
		header( 'Content-Type: text/plain' );

		if (
			'yes' !== $this->settings['protected'] ||
			( isset( $_GET['secret'] ) && $this->settings['password'] === $_GET['secret'] )
		) {
			$this->add_store_information();
			$this->add_products();
			$this->render();
		}

		echo '';
	}

	/**
	 * Add general information about the store to the XML document.
	 *
	 * @since 1.0.0
	 */
	private function add_store_information() {
		$this->feed->header['title']       = get_bloginfo( 'name' );
		$this->feed->header['link']        = Multilanguage::get_home_url( $this->language );
		$this->feed->header['description'] = sanitize_text_field( get_bloginfo( 'description' ) );
	}

	/**
	 * Add all items (products) to the feed.
	 *
	 * @since 1.0.0
	 */
	private function add_products() {
		foreach ( $this->products as $post ) {
			$product = WC()->product_factory->get_product( $post );

			if ( 'yes' === $this->settings['split_variable'] && $product->is_type( 'variable' ) ) {
				$children = $product->get_children();

				foreach ( $children as $child ) {
					// If product variations have non-public status they won't be present
					// in this array.
					if ( ! $this->product_variations[ $child ] ) {
						continue;
					}

					$item = new Data_Feed_Item(
						$this->product_variations[ $child ],
						get_post($product->get_id()),

						$this->settings,
						$this->paths_cache,
						$this->terms_cache
					);

					$this->add_item_to_feed( $item->get_fields() );
				}
			} else {
				$item = new Data_Feed_Item(
					$post,
					null,

					$this->settings,
					$this->paths_cache,
					$this->terms_cache
				);

				$this->add_item_to_feed( $item->get_fields() );
			}
		}
	}

	/**
	 * Add set of fields generated using Data_Feed_Item to the XML feed.
	 *
	 * @param array $fields Fields of the item to add.
	 */
	private function add_item_to_feed( $fields ) {
		$this->feed->items[] = $fields;
	}

	/**
	 * Render the generated XML document in the browser.
	 *
	 * @since 1.0.0
	 */
	private function render() {
		if ( empty( $this->products ) ) {
			echo '';
		} else {
			$this->feed->render( $this->is_first, $this->is_last );
			echo $this->feed->get();
		}
	}
}
