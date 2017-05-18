<?php

namespace Doofinder\WC\Data_Feed;

use Doofinder\WC\Settings\Attributes;
use Doofinder\WC\Settings\Settings;

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
	 * Data_Feed_Item constructor.
	 * $parent should be passed only when $post is a product variation.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post        WooCommerce product to add to feed.
	 * @param \WP_Post $parent      Parent of $product, if $product is a variable product.
	 * @param array    $settings    Doofinder and WC settings.
	 * @param array    $paths_cache Cache of already traversed category paths.
	 * @param array    $terms_cache All product categories loaded from DB.
	 */
	public function __construct( $post, $parent = null, $settings, &$paths_cache, &$terms_cache ) {
		$this->post        = $post;
		$this->product     = WC()->product_factory->get_product( $post );
		$this->parent      = $parent;
		$this->settings    = $settings;
		$this->attributes  = Attributes::instance();
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

		$this->add_product_type();
		$this->add_tags();

		$this->add_additional_attributes();
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
			$this->fields['link'] = $this->attributes->get( 'link', $this->product->post );
		} else {
			$this->fields['link'] = get_permalink( $this->product->post );
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
		if ( $this->parent ) {
			$attributes = $this->product->get_variation_attributes();
			$suffix = ' (' . implode( ', ', $attributes ) . ')';
		}

		$post = $this->product->post;
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
		$post = $this->product->post;
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
			$values = array_map( 'trim', preg_split('/\,/', $this->product->get_attribute( $attribute ) ) );
			$this->fields[ str_replace( 'pa_', '', $attribute ) ] = implode( '/', $values );
		}
	}

	/**
	 * Add product availability.
	 *
	 * @since 1.0.0
	 */
	private function add_availability() {
		$availability = $this->product->is_purchasable() && $this->product->is_in_stock() ? 'in stock' : 'out of stock';
		$this->fields['availability'] = $availability;
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
	 * The size of the thumbnail is configurable in settings, but full size is exported if not set.
	 *
	 * @since 1.0.0
	 */
	private function add_thumbnail() {
		$size = 'full';
		$image_id = get_post_thumbnail_id( $this->product->id );

		if ( $this->settings['image_size'] && has_image_size( $this->settings['image_size'] ) ) {
			$size = $this->settings['image_size'];
		}

		if ( $image_id ) {
			if ( $image_url = wp_get_attachment_image_src( $image_id, $size ) ) {
				$this->fields['image_link'] = $image_url[0];
			}
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

		list( $regular_price, $sale_price ) = $this->get_prices( $this->product );

		if ( $regular_price ) {
			$this->fields['price'] = $regular_price;
		}

		if ( $sale_price ) {
			$field_name = $regular_price ? 'sale_price' : 'price';
			$this->fields[ $field_name ] = $sale_price;
		}
	}

	/* Taxonomies *****************************************************************/

	/**
	 * Add categories.
	 * Product variations don't have categories so we have to grab them from parent.
	 *
	 * @since 1.0.0
	 */
	private function add_product_type() {
		if ( $this->parent ) {
			$this->fields['product_type'] = $this->get_categories( $this->parent->ID );
		} else {
			$this->fields['product_type'] = $this->get_categories( $this->product->post->ID );
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

		$id = $this->product->post->ID;
		if ( $this->parent ) {
			$id = $this->parent->ID;
		}

		$tags = wp_get_post_terms( $id, 'product_tag' );
		if ( ! $tags ) {
			return;
		}

		$tags = array_map( function( $tag ) {
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
		foreach( $attributes as $attribute ) {
			$this->fields[ $attribute['field'] ] = $this->attributes->get_attribute_value( $attribute[ 'attribute' ], $this->post );
		}
	}

	/* Helpers ********************************************************************/

	/**
	 * Add a field, but clean up the value from HTML, control characters, etc.
	 *
	 * @since 1.1.0
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
	 * @param int $id ID of WP_Post to get categories for.
	 * @return string All category paths.
	 */
	private function get_categories( $id ) {
		$paths = array();
		$terms = get_the_terms( $id, 'product_cat' );

		if ( is_array( $terms ) ) {
			foreach( $terms as $term ) {
				$paths[] = $this->get_category_path( $term );
			}
		}

		$this->clean_paths( $paths );
		return implode( '%%', $paths );
	}

	/**
	 * Filter the list of all paths for the item leaving only the most complete category paths
	 * (removing sub-paths).
	 *
	 * @param array $paths Paths to clean up.
	 */
	private function clean_paths( &$paths ) {
		sort( $paths );
		for ( $x = 0, $i = 1, $j = count( $paths ); $i < $j; $x = $i++ ) {
			if ( strpos( $paths[ $i ], $paths[ $x ] ) === 0 ) {
				unset( $paths[ $x ] );
			}
		}
	}

	/**
	 * Generate a path from the given term to the oldest ancestor.
	 *
	 * @since 1.0.0
	 * @param \WP_Term $term
	 * @return array|mixed|string
	 */
	private function get_category_path( \WP_Term $term ) {
		// Don't traverse again if we already have the path cached.
		if ( isset( $this->paths_cache[ $term->term_id ] ) ) {
			return $this->paths_cache[ $term->term_id ];
		}

		$term_id = $term->term_id;
		$path = array();
		$path[] = $term->name;

		/*
		 * Traverse from current term to the oldest ancestor.
		 * Terms are already loaded to the cache, so there's no need to load them from DB again.
		 */
		while ( $term->parent ) {
			$term = $this->terms_cache[ $term->parent ];
			$path[] = $term->name;
		}

		// Build a path, and cache it for future use.
		$path = implode( ' > ', array_reverse( $path ) );
		$this->paths_cache[ $term_id ] = $path;

		return $path;
	}

	/**
	 * Get regular and sale price of the Product.
	 *
	 * @since 1.0.0
	 * @param \WC_Product $product
	 * @return array Regular and sale price.
	 */
	private function get_prices( $product )	{
		$pricing = array();
		$regular_price = false;
		$sale_price = false;

		if ( $product->is_type( 'variable' ) ) {
			$regular_price = $product->get_variation_regular_price( 'min' );
			if ( $product->is_on_sale() ) {
				$sale_price = $product->get_variation_sale_price( 'min' );
			}
		} else {
			$regular_price = $product->get_regular_price();
			if ( $product->is_on_sale() ) {
				$sale_price = $product->get_sale_price();
			}
		}

		if ( $this->settings['include_taxes'] ) {
			$pricing[] = $product->get_price_including_tax( 1, $regular_price );
			$pricing[] = $sale_price ? $product->get_price_including_tax( 1, $sale_price ) : false;
		} else {
			$pricing[] = $product->get_price_excluding_tax( 1, $regular_price );
			$pricing[] = $sale_price ? $product->get_price_excluding_tax( 1, $sale_price ) : false;
		}

		return $pricing;
	}
}
