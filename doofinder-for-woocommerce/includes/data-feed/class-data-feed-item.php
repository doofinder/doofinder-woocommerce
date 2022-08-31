<?php

namespace Doofinder\WC\Data_Feed;

use Doofinder\WC\Settings\Attributes;
use Doofinder\WC\Settings\Settings;
use Doofinder\WC\Log;

defined( 'ABSPATH' ) or die;

class Data_Feed_Item {

	/**
	 * WP_Post representing the product (or product variation) being added.
	 *
	 * @var \WP_Post
	 */
	private $post;

	/**
	 * Post object representing a product.
	 *
	 * @var \WC_Product
	 */
	private $product;

	/**
	 * Parent of $post (in case of variable products).
	 *
	 * @var \WP_Post
	 */
	private $parent;

	/**
	 * Array of fields being exported.
	 *
	 * @var array
	 */
	private $fields = array();

	/**
	 * Doofinder integration and WooCommerce settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Attributes instance, allowing to retrieve custom attribute mappings.
	 *
	 * @var Attributes
	 */
	private $attributes;

	/**
	 * Contains already traversed paths from the term to its oldest parent.
	 * In case some products have the exact same category we don't have to traverse them
	 * separately, just cache once and then read from cache in case of following products.
	 *
	 * @var array
	 */
	private $paths_cache;

	/**
	 * WP_Term object stores a term parent only as an ID, and only the closest ancestor.
	 * Loading parent terms each time we need them would create a lot of redundant DB calls,
	 * therefore we load all terms once at the beginning.
	 *
	 * @var array
	 */
	private $terms_cache;

	/**
	 * Instance of a class used to log to a file.
	 *
	 * @var Log
	 */
	private $log;


	/**
	 * Data_Feed_Item constructor.
	 * $parent should be passed only when $post is a product variation.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post        WooCommerce product to add to feed.
	 * @param \WP_Post $parent      Parent of $product, if $product is a variable product.
	 * @param array    $settings    Doofinder and WC settings.
	 * @param array    $paths_cache Cache of already traversed category paths.
	 * @param array    $terms_cache All product categories loaded from DB.
	 */
	public function __construct( $post, $parent = null, $settings, &$paths_cache, &$terms_cache ) {

		$this->log = new Log( 'api.txt' );

		$this->post = $post;
		$this->product = WC()->product_factory->get_product( $post->ID );
		$this->parent = $parent;
		$this->settings = $settings;
		$this->attributes = Attributes::instance();
		$this->paths_cache = &$paths_cache;
		$this->terms_cache = &$terms_cache;

		$this->add_basic_fields();
		$this->add_link();
		$this->add_title();
		$this->add_description();
		$this->add_group_id();
		$this->add_variation_attributes();
		$this->add_availability();
		$this->add_sku();
		$this->add_thumbnail();
		$this->add_prices();
		$this->add_group_leader();
		$this->add_categories();
		$this->add_tags();
		$this->add_additional_attributes();
		$this->remove_empty_fields();
		$this->decode_fields();
	}

	/**
	 * Retrieve the item fields.
	 *
	 * @since 1.0.0
	 * @return array Fields to add to feed.
	 */
	public function get_fields() {
		return $this->fields;
	}

	/* Adding fields **************************************************************/

	/**
	 * Add basic fields - those that are present regardless of product type
	 * and don't require any additional checks.
	 *
	 * @since 1.0.0
	 */
	private function add_basic_fields() {
		$this->fields['id'] = $this->post->ID;
	}

	/**
	 * Add product link to the feed.
	 *
	 * @since 1.0.0
	 */
	private function add_link() {
		if ( $this->attributes->have( 'link' ) ) {
			$this->fields['link'] = $this->attributes->get( 'link', $this->post );
		} else {
			$this->fields['link'] = get_permalink( $this->post );
		}
	}

	/**
	 * In case of regular products add just the title.
	 * In case of variations add product name with variation attributes in brackets, e.g.
	 * "A shirt (blue)"
	 *
	 * @since 1.0.0
	 */
	private function add_title() {
		$suffix = '';
		if ( $this->parent && $this->product ) {
			$attributes = $this->product->get_variation_attributes();
			$suffix = ' (' . implode( ', ', $attributes ) . ')';
		}

		$post = $this->post;
		if ( $this->parent ) {
			$post = $this->parent;
		}

		$title = $post->post_title;
		if ( $this->attributes->have( 'title' ) ) {
			$title = $this->attributes->get( 'title', $post );
		}

		$this->add_field( 'title', $title . $suffix );
	}

	/**
	 * In case of regular products add content as description.
	 * Product variations don't have the content, so in their case add parents content.
	 *
	 * @since 1.0.0
	 */
	private function add_description() {
		$post = $this->post;
		if ( $this->parent ) {
			$post = $this->parent;
		}

		if ( $this->attributes->have( 'description' ) ) {
			$this->add_field( 'description', $this->attributes->get( 'description', $post ) );
		} else {
			$this->add_field( 'description', $post->post_content );
		}
	}

	/**
	 * Product variations require "item_group_id" field, which denotes which product variations
	 * they are.
	 *
	 * @since 1.0.0
	 */
	private function add_group_id() {
		if ( ! $this->parent ) {
			return;
		}

		$this->fields['item_group_id'] = $this->parent->ID;
	}

	/**
	 * When adding a variable products as one (not splitting it) add variation attributes
	 * as fields to the feed to make product ready for faceted search.
	 *
	 * @since 1.0.0
	 */
	private function add_variation_attributes() {
		if ( $this->parent || ! $this->product->is_type( 'variable' ) ) {
			return;
		}

		$attributes = $this->product->get_variation_attributes();
		foreach ( array_keys( $attributes ) as $attribute ) {
			$values = array_map( 'trim', preg_split( '/\,/', $this->product->get_attribute( $attribute ) ) );

			foreach ( $values as $key => $val ) {
				$val = str_replace('/', '//', $val );
				$values[ $key ] = str_replace( ' | ', '/', $val );
			}

			// Custom attributes added at the product level can have
			// characters in them that are not allowed in XML tags,
			// and therefore need to be slugified.

			// Woo adds 'pa_' to attributes added in "Attributes" men.
			$slug = str_replace( 'pa_', '', $attribute );

			// Make lowercase, remove all non-url characters.
			$slug = sanitize_title( $slug );

			// "sanitize_title" separates words using "-"; use "_" instead.
			$slug = preg_replace( '/-/', '_', $slug );

			$this->fields[ $slug ] = implode( '/', $values );
		}
	}

	/**
	 * Add product availability.
	 *
	 * @since 1.0.0
	 */
	private function add_availability() {

		$type = $this->product->get_type();

		if ($type === 'grouped' || $type === 'external') {
			$availability = $this->product->is_in_stock() ? 'in stock' : 'out of stock';
		} else {
			$availability = $this->product->is_purchasable() && $this->product->is_in_stock() ? 'in stock' : 'out of stock';
		}

		$this->fields['availability'] = $availability;
	}

	/**
	 * Add product type (simple, variable, etc.).
	 *
	 *
	 * @since 1.0.0
	 */
	private function add_type() {

		$this->fields['type'] = $this->product->get_type();
	}

	/**
	 * Add product SKU.
	 *
	 * @since 1.0.0
	 */
	private function add_sku() {
		if ( $mpn = $this->product->get_sku() ) {
			$this->fields['mpn'] = $mpn;
		}
	}

	/**
	 * Add post thumbnail.
	 * The size of the thumbnail is configurable in settings, but thumbnail size is exported if not set.
	 *
	 * @since 1.0.0
	 */
	private function add_thumbnail() {
		$size = 'thumbnail';
		$image_id = $this->product->get_image_id();


		$default_sizes = array(
			'thumbnail',
			'medium',
			'medium-large',
			'large',
		);

		if ( $this->settings['image_size'] && (has_image_size( $this->settings['image_size'] ) || in_array($this->settings['image_size'], $default_sizes) )) {
			$size = $this->settings['image_size'];
		}

		if ( $image_id ) {
			if ( $image_url = wp_get_attachment_image_src( $image_id, $size ) ) {
				$this->fields['image_link'] = $image_url[0];
			}
		} else {
			$this->fields['image_link'] = wc_placeholder_img_src( $size );		
		}
	}

	/**
	 * Add regular and sale price.
	 * Whether or not add prices to the feed can be controlled via settings.
	 *
	 * @since 1.0.0
	 */
	private function add_prices() {
		if ( 'no' === $this->settings['export_prices'] ) {
			return;
		}

		$prices = $this->get_prices( $this->product );

		if ( $prices['regular'] ) {
			$this->fields['price'] = (float) $prices['regular'];
		}

		if ( isset( $prices['sale'] ) && $prices['sale'] ) {
			// If there's no regular price display sale price as regular.
			$field_name = $prices['regular'] ? 'sale_price' : 'price';
			$this->fields[ $field_name ] = (float) $prices['sale'];
		}
	}

	/**
	 * Add product leader.
	 * Product with variations and variations themselves require
	 * 'df_group_leader' and 'group_id' fields.
	 * Product with variations is group leader but variations aren`t.
	 * Variation gets his parent id as a reference.
	 *
	 * @since 1.5.11
	 */
	private function add_group_leader() {
		if ( 'no' === $this->settings['split_variable'] ) {
			return;
		}

		$id = $this->post->ID;
		$product = wc_get_product( $id );
		$variation = wc_get_product( $product->get_parent_id() );


		if ( $variation ) {
			$this->fields[ 'df_group_leader' ] = 'false';
			$this->fields[ 'group_id' ] = (string) $variation->get_id();
		} else {
			if ( $product->is_type( 'variable' ) ) {
				$this->fields[ 'df_group_leader' ] = 'true';
				$this->fields[ 'group_id' ] = (string) $id;
			} else {
				$this->fields[ 'df_group_leader' ] = 'false';
				$this->fields[ 'group_id' ] = false;
			}
		}
	}

	/* Taxonomies *****************************************************************/

	/**
	 * Add categories.
	 * Product variations don't have categories so we have to grab them from parent.
	 *
	 * @since 1.0.0
	 */
	private function add_categories() {
		if ( $this->parent ) {
			$this->fields['categories'] = $this->get_categories( $this->parent->ID );
		} else {
			$this->fields['categories'] = $this->get_categories( $this->post->ID );
		}
	}

	/**
	 * Add tags.
	 * Product variations don't have tags so we have to grab them from parent.
	 * Whether or not tags are exported can be controlled via settings.
	 *
	 * @since 1.2.0
	 */
	private function add_tags() {
		if ( 'yes' !== $this->settings['export_tags'] ) {
			return;
		}

		$id = $this->post->ID;
		if ( $this->parent ) {
			$id = $this->parent->ID;
		}

		$tags = wp_get_post_terms( $id, 'product_tag' );
		if ( ! $tags ) {
			return;
		}

		$tags = array_map( function ( $tag ) {
			return $tag->name;
		}, $tags );


		$this->fields['tags'] = implode( '/', $tags );
	}

	/* Additional fields **********************************************************/

	/**
	 * Add additional attributes chosen by the user in config to the feed.
	 */
	private function add_additional_attributes() {
		$attributes = Settings::get( 'feed_attributes', 'additional_attributes' );
		if ( empty( $attributes ) || ! is_array( $attributes ) ) {
			return;
		}

		$attributes = array_map( 'wp_parse_args', $attributes );

		foreach ( $attributes as $attribute ) {

			if( isset($attribute['field']) && isset($attribute['attribute'])) {
				$this->fields[ $attribute['field'] ] = $this->attributes->get_attribute_value(
					$attribute['attribute'],
					$this->post,
					$attribute
				);

				// Inherit attributes from parent.
				if ($this->parent) {
					$this->fields[ $attribute['field'] ] = $this->attributes->get_attribute_value(
						$attribute['attribute'],
						$this->parent,
						$attribute
					);
				}
			}

		}
	}

	/**
	 * Removes all empty fields
	 *
	 * @since 1.2.2
	 */
	private function remove_empty_fields() {
		foreach ( $this->fields as $key => $field ) {
			if ( empty( $this->fields[ $key ] ) ) {
				unset( $this->fields[ $key ] );
			}
		}
	}

	/**
	 * Decode fields from special chars
	 *
	 * @since 1.5.15
	 */
	private function decode_fields() {
		// $this->log->log('Decode fields: ');
		// $this->log->log( $this->fields );

		array_walk_recursive($this->fields, function( &$value, $key ) {
            if(strpos($key, 'price') === false) {
                $value = html_entity_decode( $value, ENT_QUOTES );
            }
		} );

		// $this->log->log('Decoded fields: ');
		// $this->log->log($this->fields);
	}

	/* Helpers ********************************************************************/

	/**
	 * Add a field, but clean up the value from HTML, control characters, etc.
	 *
	 * @since 1.1.0
	 *
	 * @param string $name  Field name to add.
	 * @param string $value Field value to add.
	 */
	private function add_field( $name, $value ) {
		$value = preg_replace( '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
		$value = wp_strip_all_tags( $value );

		$this->fields[ $name ] = $value;
	}

	/**
	 * Get all categories (in forms of paths from term to the oldest ancestor) for a given term.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id ID of WP_Post to get categories for.
	 *
	 * @return string All category paths.
	 */
	private function get_categories( $id ) {
		$paths = array();
		$terms = get_the_terms( $id, 'product_cat' );
		$this->log->log('Data Feed Item - Get Categories: ');
		$this->log->log( $terms );
        
		if( !empty( $terms ) && is_array( $terms )) {
            foreach ( $terms as $term ) {
                $paths[] = $this->get_category_path( $term );
			}
			$this->clean_paths( $paths );
            
            $this->log->log('Returning paths: ');
            $this->log->log($paths);
			return $paths;
		}

	}

	/**
	 * Filter the list of all paths for the item leaving only the most complete category paths
	 * (removing sub-paths).
	 *
	 * @param array $paths Paths to clean up.
	 */
	private function clean_paths( &$paths ) {
		sort( $paths );
		for ( $x = 0, $i = 1, $j = count( $paths ); $i < $j; $x = $i ++ ) {

			if ( isset( $paths[ $i ] ) && isset( $paths[ $x ] ) ) {
				if ( strpos( $paths[ $i ], $paths[ $x ] ) === 0 ) {
					unset( $paths[ $x ] );
				}
			}

			$paths = array_values($paths);
		}
	}

	/**
	 * Generate a path from the given term to the oldest ancestor.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term|int $term
	 *
	 * @return array|mixed|string
	 */
	private function get_category_path( $term ) {

		if ( is_int($term) ) {

			if ( $term > 0 ) {
				$term = get_term_by('id',$term,'product_cat');
			} else {
				return '';
			}
		}

		// Don't traverse again if we already have the path cached.
		if ( isset( $this->paths_cache[ $term->term_id ] ) ) {
			return $this->paths_cache[ $term->term_id ];
		}

		$term_id = isset($term->term_id) ? $term->term_id : 0;
		$path = array();
		$path[] = isset($term->name) ? html_entity_decode($term->name) : null;

		/*
		 * Traverse from current term to the oldest ancestor.
		 * Terms are already loaded to the cache, so there's no need to load them from DB again.
		 */
		if (isset($term->parent)) {
			while ( $term->parent ) {
				$term = $this->terms_cache[ $term->parent ];
				$path[] = $term->name;
			}

			// Build a path, and cache it for future use.
			$path = implode( ' > ', array_reverse( $path ) );
			$this->paths_cache[ $term_id ] = $path;
		} else {
			$path = '';
		}

		return $path;
	}

	/**
	 * Get regular and sale price of the Product.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Product|\WC_Product_Variable $product
	 *
	 * @return array Regular and sale price.
	 */
	private function get_prices( $product ) {
		// Calculate prices for variable products
		if ( $product->is_type( 'variable' ) ) {
			return $this->get_variable_prices( $product );
		}

		// Calculate prices for non-variable products
		$prices = array( 'regular' => $product->get_regular_price() );
		if ( $product->is_on_sale() ) {
			$prices['sale'] = $product->get_sale_price();
		}

		if ( $this->settings['include_taxes'] ) {
			// Methods for retrieving prices with taxes on \WC_Product have been
			// deprecated in Woo 3.0.0, but we want to make sure we don't crash
			// and retrieve the prices correctly if older version of WooCommerce
			// is installed.
			if ( function_exists( 'wc_get_price_including_tax' ) ) {
				$prices = $this->get_simple_prices_with_taxes( $product, $prices );
			} else {
				$prices = $this->get_simple_prices_with_taxes_legacy( $product, $prices );
			}
		}

		return $prices;
	}

	/**
	 * Retrieve prices with taxes for non-variable product.
	 *
	 * @since 1.2.6
	 *
	 * @param \WC_Product $product
	 * @param array       $prices
	 *
	 * @return array
	 */
	private function get_simple_prices_with_taxes( $product, $prices ) {
		$prices['regular'] = wc_get_price_including_tax( $product, array(
			'qty'   => 1,
			'price' => $prices['regular'],
		) );

		if ( isset( $prices['sale'] ) ) {
			$prices['sale'] = wc_get_price_including_tax( $product, array(
				'qty'   => 1,
				'price' => $prices['sale'],
			) );
		}

		return $prices;
	}

	/**
	 * Retrieve prices with taxes for the non-variable product.
	 *
	 * This function is here to provide WooCommerce 2.* compatibility,
	 * as it uses legacy methods on \WC_Product that were deprecated
	 * in WooCommerce 3.0.0. It will not be used if new version
	 * of WooCommerce is installed.
	 *
	 * @since 1.2.6
	 *
	 * @param \WC_Product $product
	 * @param array       $prices
	 *
	 * @return array
	 */
	private function get_simple_prices_with_taxes_legacy( $product, $prices ) {
		$prices['regular'] = $product->get_price_including_tax( 1, $prices['regular'] );

		if ( isset( $prices['sale'] ) ) {
			$prices['sale'] = $product->get_price_including_tax( 1, $prices['sale'] );
		}

		return $prices;
	}

	/**
	 * Retrieve regular and (optionally) sale price for a variable product.
	 *
	 * @since 1.2.6
	 *
	 * @param \WC_Product_Variable $product
	 *
	 * @return array
	 */
	private function get_variable_prices( $product ) {
		$include_taxes = (bool) $this->settings['include_taxes'];

		$regular_price = $product->get_variation_regular_price( 'min', $include_taxes );
		$sale_price = false;

		if ( $product->is_on_sale() ) {
			$sale_price = $product->get_variation_sale_price( 'min', $include_taxes );
		}

		$prices = array( 'regular' => $regular_price );
		if ( $sale_price ) {
			$prices['sale'] = $sale_price;
		}

		return $prices;
	}
}
