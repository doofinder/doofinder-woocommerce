<?php
/**
 * DooFinder Endpoint_Product methods.
 *
 * @package Doofinder\WP\Endpoints
 */

ini_set( 'serialize_precision', '-1' ); // phpcs:ignore WordPress.PHP.IniSet

use Doofinder\WP\Endpoints;
use Doofinder\WP\Helpers\Helpers;
use Doofinder\WP\Settings;
use Doofinder\WP\Thumbnail;

/**
 * Class Endpoint_Product
 *
 * This class defines various methods for handling custom product endpoints.
 */
class Endpoint_Product {

	const PER_PAGE = 100;
	const CONTEXT  = 'doofinder/v1';
	const ENDPOINT = '/product';
	const FIELDS   = array(
		'attributes',
		'average_rating',
		'best_price',
		'catalog_visibility',
		'categories',
		'description',
		'df_group_leader',
		'df_indexable',
		'df_variants_information',
		'group_id',
		'id',
		'image_link',
		'link',
		'meta_data',
		'name',
		'parent_id',
		'permalink',
		'price',
		'purchasable',
		'regular_price',
		'sale_price',
		'short_description',
		'sku',
		'status',
		'slug',
		'stock_status',
		'tags',
		'type',
	);

	const TAXONOMY = 'product_cat';

	/**
	 * Initialize the custom product endpoint.
	 *
	 * @return void
	 */
	public static function initialize() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					self::CONTEXT,
					self::ENDPOINT,
					array(
						'methods'             => 'GET',
						'callback'            => array( self::class, 'custom_product_endpoint' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
	}

	/**
	 * Custom product endpoint callback.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @param array           $config_request Array config for internal requests.
	 *
	 * @return WP_REST_Response Response containing modified data.
	 */
	public static function custom_product_endpoint( $request, $config_request = false ) {

		$custom_attr        = Settings::get_custom_attributes();
		$custom_attr_fields = self::get_field_attributes( $custom_attr );

		if ( ! $config_request ) {
			Endpoints::check_secure_token();

			$fields = ( 'all' === $request->get_param( 'fields' ) ) ? array() : array_merge( self::get_fields(), array_values( $custom_attr_fields ) );

			$locale_or_lang_code = $request->get_param( 'lang' ) ?? '';
			$lang_code           = Helpers::apply_locale_to_rest_context( $locale_or_lang_code );

			$config_request = array(
				'per_page' => $request->get_param( 'per_page' ) ?? self::PER_PAGE,
				'page'     => $request->get_param( 'page' ) ?? 1,
				'lang'     => $lang_code,
				'ids'      => $request->get_param( 'ids' ) ?? '',
				'orderby'  => $request->get_param( 'orderby' ) ?? 'id',
				'order'    => $request->get_param( 'order' ) ?? 'desc',
				'fields'   => $fields,
			);
		} else {
			// Update on save.
			$fields_param             = $config_request['fields'] ?? '';
			$fields                   = ! empty( $fields_param ) ? explode( ',', $fields_param ) : array();
			$fields                   = array_merge( $fields, array_values( $custom_attr_fields ) );
			$config_request['fields'] = $fields;
		}

		// Retrieve the original product data.
		$products          = self::get_products( $config_request );
		$custom_attr       = Settings::get_custom_attributes();
		$modified_products = array();

		// Process and filter product data.
		if ( ! empty( $products ) ) {

			// Include variants if requested.
			$products = self::get_variations( $products );

			foreach ( $products as $product_data ) {
				// If the product is not a valid WC product, ignore it.
				if ( ! isset( $product_data['id'] ) || empty( $product_data['id'] ) || false === wc_get_product( $product_data['id'] ) ) {
					continue;
				}

				$indexable_opt = get_post_meta( $product_data['id'], '_doofinder_for_wp_indexing_visibility', true );

				// Filter fields.
				$filtered_product_data = ! empty( $fields ) ? array_intersect_key( $product_data, array_flip( $fields ) ) : $product_data;

				$filtered_product_data = self::set_indexable( $filtered_product_data, $indexable_opt );
				$filtered_product_data = self::get_category_merchandising( $filtered_product_data );
				$filtered_product_data = self::get_categories( $filtered_product_data );
				$filtered_product_data = self::merge_custom_attributes( $filtered_product_data, $custom_attr );
				$filtered_product_data = self::get_image_field( $filtered_product_data );
				$filtered_product_data = self::format_prices( $filtered_product_data );
				$filtered_product_data = self::check_stock_status( $filtered_product_data );
				$filtered_product_data = self::get_description( $filtered_product_data );
				$filtered_product_data = self::get_short_description( $filtered_product_data );
				$filtered_product_data = self::get_tags( $filtered_product_data );
				$filtered_product_data = self::get_meta_attributes( $filtered_product_data, $custom_attr );
				$filtered_product_data = self::clean_fields( $filtered_product_data );

				$modified_products[] = $filtered_product_data;
			}
			// Merge variants into their parent products.
			$modified_products = self::merge_variants_into_parents( $modified_products );
		}
		// Return the modified product data as a response.
		return new WP_REST_Response( $modified_products );
	}

	/**
	 * Get the array of custom attributes name fields.
	 *
	 * @param array $custom_attrs Array of custom attributes.
	 *
	 * @return array The array of fields.
	 */
	public static function get_field_attributes( $custom_attrs ) {

		$custom_fields = array();
		foreach ( $custom_attrs as $custom_attr ) {
			$custom_fields[ $custom_attr['field'] ] = $custom_attr['attribute'];
		}
		return $custom_fields;
	}

	/**
	 * Get the array of fields.
	 *
	 * @return array The array of fields.
	 */
	public static function get_fields() {
		return self::FIELDS;
	}

	/**
	 * Get products data from our endpoint products
	 *
	 * @param array  $ids ID product we want to get data.
	 * @param string $lang Lang.
	 *
	 * @return array  Array Products
	 */
	public static function get_data( $ids, $lang ) {

		$request_params = array(
			'ids'    => implode( ',', $ids ),
			'fields' => implode( ',', self::get_fields() ),
			'lang'   => $lang,
		);

		$items = self::custom_product_endpoint( false, $request_params )->data;

		array_walk(
			$items,
			function ( &$product ) {
				if ( is_array( $product ) && array_key_exists( '_links', $product ) ) {
					unset( $product['_links'] );
				}
			}
		);

		return $items;
	}

	/**
	 * Get custom meta fields data from a WooCommerce product.
	 *
	 * @param array $data        The data to merge into.
	 * @param array $custom_attr The custom attributes to merge.
	 * @return array The merged data.
	 */
	private static function get_meta_attributes( $data, $custom_attr ) {
		foreach ( $custom_attr as $attr ) {
			if ( 'metafield' !== $attr['type'] ) {
				continue;
			}

			foreach ( $data['meta_data'] as $meta ) {
				$meta_data = $meta->get_data();
				if ( $attr['attribute'] === $meta_data['key'] ) {
					$data[ $attr['field'] ] = $meta_data['value'] ?? '';
				}
			}
		}
		unset( $data['meta_data'] );
		return $data;
	}

	/**
	 * Set indexable option.
	 *
	 * @param array  $data   The data to process.
	 * @param string $indexable The indexable option.
	 * @return array The processed data.
	 */
	private static function set_indexable( $data, $indexable ) {
		$data['df_indexable'] = $indexable;
		return $data;
	}

	/**
	 * Get categories in the data.
	 *
	 * @param array $data The data to process.
	 *
	 * @return array The processed data.
	 */
	private static function get_categories( $data ) {
		if ( isset( $data['categories'] ) ) {
			$data['categories'] = self::get_category_path( $data['categories'] );
		} else {
			$data['categories'] = array();
		}
		return $data;
	}

	/**
	 * Processes product categories and adds a new `category_merchandising` field
	 * containing relative URLs for each product category.
	 *
	 * @param array $data The product data to process.
	 * @return array Product data including the new `category_merchandising` field.
	 * @throws WP_Error If there is an error retrieving term links.
	 * @since 2.7.6
	 */
	private static function get_category_merchandising( $data ) {
		$data['category_merchandising'] = array();

		// Obtain category base.
		$category_base = get_option( 'woocommerce_permalinks' );
		$category_base = isset( $category_base['category_base'] ) ? trim( $category_base['category_base'], '/' ) : 'product-category';

		if ( isset( $data['categories'] ) ) {
			foreach ( $data['categories'] as $category ) {
				if ( empty( $category['id'] ) || ! is_numeric( $category['id'] ) ) {
					continue;
				}

				$term = get_term( (int) $category['id'], self::TAXONOMY );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}

				$ancestors     = get_ancestors( $term->term_id, self::TAXONOMY );
				$ancestors     = array_reverse( $ancestors );
				$full_path_ids = array_merge( $ancestors, array( $term->term_id ) );

				$full_path = array();

				foreach ( $full_path_ids as $term_id ) {
					$term_link = get_term_link( (int) $term_id, self::TAXONOMY );
					if ( is_wp_error( $term_link ) ) {
						continue;
					}

					$components    = wp_parse_url( $term_link );
					$relative_path = isset( $components['path'] ) ? trim( $components['path'], '/' ) : '';

					// Remove category base if it exists.
					if ( $category_base && str_starts_with( $relative_path, $category_base ) ) {
						$relative_path = substr( $relative_path, strlen( $category_base ) );
						$relative_path = ltrim( $relative_path, '/' );
					}

					$segments = explode( '/', $relative_path );
					if ( ! empty( $segments ) ) {
						$full_path[] = $segments[ count( $segments ) - 1 ]; // just add the last segment.
						$path        = implode( '/', $full_path );

						if ( ! in_array( $path, $data['category_merchandising'], true ) ) {
							$data['category_merchandising'][] = $path;
						}
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Merge custom attributes into the data.
	 *
	 * @param array $data        The data to merge into.
	 * @param array $custom_attr The custom attributes to merge.
	 * @return array The merged data.
	 */
	private static function merge_custom_attributes( $data, $custom_attr ) {
		// Filter out metafield custom attributes and variants attributes.
		$custom_attr = array_values(
			array_filter(
				$custom_attr,
				function ( $attr ) use ( $data ) {
					return isset( $attr['type'] ) &&
						'metafield' !== $attr['type'] && ( empty( $data['df_variants_information'] ) ||
						! in_array( $attr['field'], $data['df_variants_information'], true ) );
				}
			)
		);

		if ( empty( $custom_attr ) ) {
			return $data;
		}

		$data_with_attr = array_merge( $data, self::get_custom_attributes( $data['id'], $custom_attr ) );

		foreach ( $custom_attr as $custom ) {
			$attribute_key = $custom['attribute'];
			$field_key     = $custom['field'];

			if ( ! isset( $data_with_attr[ $attribute_key ] ) ) {
				continue;
			}

			// Exchange renamed fields.
			$data_with_attr[ $field_key ] = $data_with_attr[ $attribute_key ];

			// We delete the original key only if it has been renamed to a different alias.
			if ( $field_key !== $attribute_key ) {
				unset( $data_with_attr[ $attribute_key ] );
			}

			// List of value options.
			if ( is_array( $data_with_attr[ $field_key ] ) ) {
				$name_column = array_column( $data_with_attr[ $field_key ], 'name' );

				if ( ! empty( $name_column ) ) {
					$data_with_attr[ $field_key ] = $name_column;
				}
			}
		}
		return $data_with_attr;
	}

	/**
	 * Get the image link in the data.
	 *
	 * @param array $data   The data to process.
	 *
	 * @return array The processed data.
	 */
	private static function get_image_field( $data ) {
		return self::clear_images_fields( $data );
	}

	/**
	 * Check the stock status in the data.
	 *
	 * @param array $data   The data to check.
	 *
	 * @return array The processed data.
	 */
	private static function check_stock_status( $data ) {
		return self::check_availability( $data );
	}

	/**
	 * Process the description field in the data.
	 *
	 * @param array $data   The data to process.
	 *
	 * @return array The processed data.
	 */
	private static function get_description( $data ) {
		$data['description'] = self::process_content( $data['description'] );
		return $data;
	}

	/**
	 * Process the short description field in the data.
	 *
	 * @param array $data   The data to process.
	 *
	 * @return array The processed data.
	 */
	private static function get_short_description( $data ) {
		$data['short_description'] = self::process_content( $data['short_description'] );
		return $data;
	}

	/**
	 * Get tags in the data.
	 *
	 * @param array $data   The data to process.
	 *
	 * @return array The processed data.
	 */
	private static function get_tags( $data ) {
		$data['tags'] = self::get_tag_names( $data['tags'] );
		return $data;
	}

	/**
	 * Retrieves an array of names from a given array.
	 *
	 * @param array $elem_array The input array containing the elements.
	 *
	 * @return array An array containing only the names of the elements.
	 */
	private static function get_tag_names( $elem_array ) {
		$names = array();
		foreach ( $elem_array as $element ) {
			$names[] = self::process_content( $element['name'] );
		}
		return $names;
	}

	/**
	 * Process content by decoding HTML entities, stripping HTML tags, and replacing sequences of whitespace characters.
	 *
	 * @param string $content The content to process, including HTML markup.
	 *
	 * @return string The processed content with HTML entities decoded, HTML tags removed, and whitespace sequences replaced with a single space.
	 */
	private static function process_content( $content ) {
		$content = html_entity_decode( wp_strip_all_tags( $content ) );
		$content = preg_replace( '/[ \t\r\n]+/', ' ', $content );

		return trim( $content );
	}

	/**
	 * Retrieve a list of products with pagination.
	 *
	 * @param array $config   Config request for get products.
	 *
	 * @return array|null   An array of product data or null on failure.
	 */
	private static function get_products( $config ) {
		// Retrieve the original product data.
		$request = new WP_REST_Request( 'GET', '/wc/v3/products' );
		$request->set_query_params(
			array(
				'page'     => $config['page'] ?? 1,
				'per_page' => $config['per_page'] ?? self::PER_PAGE,
				'lang'     => $config['lang'] ?? '',
				'status'   => 'publish',
				'_fields'  => $config['fields'],
				'include'  => $config['ids'],
				'orderby'  => $config['orderby'] ?? 'id',
				'order'    => $config['order'] ?? 'desc',
			)
		);
		$original_response = rest_do_request( $request );
		return $original_response->data;
	}

	/**
	 * Format prices of product
	 *
	 * @param array $product The product array to format prices.
	 * @return array $product with formatted prices
	 */
	private static function format_prices( $product ) {
		$wc_product = wc_get_product( $product['id'] );

		$regular_price = self::get_regular_price( $wc_product );
		$price         = self::get_price( $wc_product );
		$sale_price    = self::get_sale_price( $wc_product );

		/**
		 * In some niche cases, we receive a regular_price of 0 but it is not the real regular_price.
		 * For this reason, in case we have a regular_price of 0, we ignore it.
		 */
		$product['regular_price'] = $regular_price;
		$product['price']         = '' === (string) $regular_price || 0 === $regular_price ? $price : $regular_price;
		$final_sale_price         = '' === (string) $sale_price || $price < $regular_price ? $price : $sale_price;

		if ( empty( $final_sale_price ) || $final_sale_price === $regular_price ) {
			unset( $product['sale_price'] );
		} else {
			$product['sale_price'] = $final_sale_price;
		}

		return $product;
	}

	/**
	 * Returns the raw price for the given product.
	 *
	 * @param \WC_Product|null $wc_product WooCommerce Product.
	 * @param string           $price_name The price name. By default 'price'.
	 *
	 * @return float|string
	 */
	private static function get_raw_price( $wc_product, $price_name = 'price' ) {
		$fn_name = "get_$price_name";
		if ( ! is_a( $wc_product, 'WC_Product' ) || ! method_exists( $wc_product, $fn_name ) ) {
			return '';
		}

		$price = $wc_product->$fn_name();
		// If sale price is empty, do not attempt to get the raw real price, as we will get the original price.
		$raw_price = 'sale_price' === $price_name && '' === $price ? '' : self::get_raw_real_price( $price, $wc_product );
		// If price is equal to 0, return an empty string.
		$raw_price = ( false === $raw_price || 0 === $raw_price ) ? '' : $raw_price;
		return $raw_price;
	}

	/**
	 * Returns the raw price for the given product with taxes or without taxes depends on the tax display.
	 *
	 * @param string      $price Type of price we want.
	 * @param \WC_Product $product WooCommerce Product.
	 *
	 * @return float|string
	 */
	private static function get_raw_real_price( $price, $product ) {
		$woocommerce_tax_display_shop = get_option( 'woocommerce_tax_display_shop', 'incl' );
		return 'incl' === $woocommerce_tax_display_shop ?
			wc_get_price_including_tax(
				$product,
				array(
					'price' => $price,
				)
			) :
			wc_get_price_excluding_tax(
				$product,
				array(
					'price' => $price,
				)
			);
	}

	/**
	 * Get the raw regular price.
	 *
	 * @param \WC_Product|null $product WooCommerce Product.
	 *
	 * @return float The raw regular price including or excluding taxes (defined in WC settings).
	 */
	private static function get_price( $product ) {
		return self::get_raw_price( $product, 'regular_price' );
	}

	/**
	 * Get the raw sale price including or excluding taxes and taking into account the scheduled dates. If the current date is outside the scheduled dates
	 * range, the regular price will be returned instead.
	 *
	 * @param \WC_Product|null $product WooCommerce Product.
	 *
	 * @return float
	 */
	private static function get_sale_price( $product ) {
		return self::get_raw_price( $product, 'price' );
	}

	/**
	 * Get the raw regular price.
	 *
	 * @param \WC_Product|null $product WooCommerce Product.
	 *
	 * @return float The raw regular price including or excluding taxes (defined in WC settings).
	 */
	private static function get_regular_price( $product ) {
		return self::get_raw_price( $product, 'regular_price' );
	}

	/**
	 * Returns the image link for a given product.
	 * If the product is a variation and doesn't have an image, return the parent image link.
	 *
	 * @param array $id Product ID selected.
	 *
	 * @return string The image link.
	 */
	public static function get_image_link( $id ) {
		$post       = get_post( $id );
		$thumbnail  = new Thumbnail( $post );
		$image_link = $thumbnail->get();
		if ( empty( $image_link ) && 'product_variation' === $post->post_type ) {
			$thumbnail  = new Thumbnail( get_post( $post->post_parent ) );
			$image_link = $thumbnail->get();
		}

		// If neither the variant and the product have an image, return the WooCommerce placeholder image.
		$image_link = empty( $image_link ) ? wc_placeholder_img_src( Thumbnail::get_size() ) : $image_link;
		$image_link = self::add_base_url_if_needed( $image_link );

		return $image_link;
	}

	/**
	 * Check that image link is absolute, if not, add the site url
	 *
	 * @param string $image_link URL of the image.
	 *
	 * @return string $image_link
	 */
	private static function add_base_url_if_needed( $image_link ) {
		if ( 0 === strpos( $image_link, '/' ) ) {
			$image_link = get_site_url() . $image_link;
		}
		return $image_link;
	}

	/**
	 * Field names to exchange and clean unused or empty fields
	 *
	 * @param array $product The product array to process.
	 * @return array $product without fields excluded
	 */
	private static function clean_fields( $product ) {
		$product['title'] = $product['name'];
		$product['link']  = $product['permalink'];

		unset( $product['attributes'] );
		unset( $product['name'] );
		unset( $product['permalink'] );

		if ( empty( $product['parent_id'] ) ) {
			unset( $product['parent_id'] );
		}

		$product = array_filter(
			$product,
			function ( $value ) {
				return ! is_null( $value );
			}
		);

		return $product;
	}

	/**
	 * Check availability product
	 *
	 * @param array $product The product array to process.
	 *
	 * @return array $product with availability string type (in stock / out of stock).
	 */
	private static function check_availability( $product ) {
		if ( $product['purchasable'] && ( 'instock' === $product['stock_status'] || 'onbackorder' === $product['stock_status'] ) ) {
			$product['availability'] = 'in stock';
		} else {
			$product['availability'] = 'out of stock';
		}

		return $product;
	}

	/**
	 * Clears image fields from a product array.
	 *
	 * @param array $product The product array to process.
	 *
	 * @return array The product array with empty image fields removed.
	 */
	private static function clear_images_fields( $product ) {
		$product['image_link'] = self::get_image_link( $product['id'] );
		unset( $product['images'] );

		return $product;
	}

	/**
	 * Groups variants under their parent product, adds the 'variants' array to the parent,
	 * and overwrites the parent's prices and link with those of the cheapest variant.
	 *
	 * For each parent product:
	 *   - Adds the 'variants' key with all its variants.
	 *   - Updates the 'price', 'regular_price', 'sale_price', and 'link' fields with those of the cheapest variant.
	 *
	 * @param array $products Array of products (parents and variants).
	 * @return array Array of parent products with grouped variants and updated prices/link.
	 */
	private static function merge_variants_into_parents( $products ) {
		$parents          = array();
		$cheapest_variant = array();
		$all_variants     = array();

		foreach ( $products as $product ) {
			if ( 'variation' === $product['type'] && ! empty( $product['parent_id'] ) ) {
				$parent_id = (string) $product['parent_id'];
				// Save all variants by parent.
				if ( ! isset( $all_variants[ $parent_id ] ) ) {
					$all_variants[ $parent_id ] = array();
				}
				$all_variants[ $parent_id ][] = $product;
				// Save the cheapest variant.
				if (
					! isset( $cheapest_variant[ $parent_id ] ) ||
					( isset( $product['price'] ) && (float) $product['price'] < (float) $cheapest_variant[ $parent_id ]['price'] )
				) {
					$cheapest_variant[ $parent_id ] = $product;
				}
			} else {
				$parents[ $product['id'] ] = $product;
			}
		}

		// Update parents with the cheapest variant info and the array of variants.
		foreach ( $all_variants as $parent_id => $variants ) {
			if ( isset( $parents[ $parent_id ] ) ) {
				$parents[ $parent_id ]['variants']      = $variants;
				$variant                                = $cheapest_variant[ $parent_id ];
				$parents[ $parent_id ]['price']         = $variant['price'];
				$parents[ $parent_id ]['regular_price'] = $variant['regular_price'] ?? $variant['price'];
				$parents[ $parent_id ]['link']          = $variant['link'];
				if ( isset( $variant['sale_price'] ) ) {
					$parents[ $parent_id ]['sale_price'] = $variant['sale_price'];
				} else {
					unset( $parents[ $parent_id ]['sale_price'] );
				}
			}
		}

		return array_values( $parents );
	}

	/**
	 * Get variations for variable products.
	 *
	 * @param array $products_data The array of product data.
	 * @return array The modified array of product data with variations.
	 */
	private static function get_variations( $products_data ) {

		$products = array();

		foreach ( $products_data as $product ) {
			$type = '';

			if ( isset( $product['type'] ) ) {
				$type = $product['type'];
			}

			$attributes = isset( $product['attributes'] ) && is_array( $product['attributes'] ) ? $product['attributes'] : array();

			if ( 'variable' === $type ) {
				$variations_data = self::process_variations( $product );

				// Setting df_variants_information when `variation attribute = true`.
				$attr_variation                     = self::get_df_variants_information( $product, $attributes );
				$product['df_variants_information'] = $attr_variation;
				$products[]                         = $product;
				$products                           = array_merge( $products, $variations_data );
			} else {
				$products[] = $product;
			}
		}
		return $products;
	}

	/**
	 * Process variations for a variable product.
	 *
	 * This function retrieves variations for a variable product, merges them with the product data,
	 * and sets the "parent_id" field.
	 *
	 * @param array $product The product data for a variable product.
	 *
	 * @return array The processed array of variations for the variable product.
	 */
	private static function process_variations( $product ) {
		$variations_data = self::request_variations( $product['id'] );

		foreach ( $variations_data as &$variation ) {
			$variation         = array_merge( $product, $variation ?? array(), array( 'parent_id' => $product['id'] ) );
			$variation['name'] = $product['name'];
		}
		return $variations_data;
	}

	/**
	 * Request variations for a given product ID.
	 *
	 * @param int $product_id The ID of the product.
	 * @return array The variations data.
	 */
	private static function request_variations( $product_id ) {

		$page            = 1;
		$variations_data = array();

		do {
			$request = new WP_REST_Request( 'GET', '/wc/v3/products/' . $product_id . '/variations' );
			$request->set_query_params(
				array(
					'page'     => $page,
					'per_page' => self::PER_PAGE,
				)
			);
			$variants_response            = rest_do_request( $request );
			$variations_data              = array_merge( $variations_data, $variants_response->data );
			$variants_response_data_count = count( $variants_response->data );

			++$page;
		} while ( $variants_response_data_count >= self::PER_PAGE );

		return $variations_data;
	}

	/**
	 * Generate df_variants_information node response.
	 *
	 * @param array $product WooCommerce Product as array.
	 * @param array $attributes Product attributes.
	 *
	 * @return array df_variants_information
	 */
	private static function get_df_variants_information( $product, $attributes ) {

		$product_attributes = array_keys( wc_get_product( $product['id'] )->get_attributes() );
		$product_attributes = array_map(
			function ( $attr ) {
				return str_replace( array( 'pa_', 'wc_' ), '', $attr );
			},
			$product_attributes
		);

		$custom_attributes_mapping = Settings::get_custom_attributes();
		$custom_attr_fields        = self::get_field_attributes( $custom_attributes_mapping );

		$variation_attributes = array();
		foreach ( $attributes as $p_attr ) {
			$slug = strtolower( str_replace( 'pa_', '', $p_attr['slug'] ) );
			if ( $p_attr['variation'] && ( in_array( $slug, $product_attributes, true ) ) ) {
				$attribute              = self::get_real_product_attribute_name( $p_attr, $custom_attr_fields );
				$variation_attributes[] = $attribute;
			}
		}
		return $variation_attributes;
	}

	/**
	 * Retrieves the mapped custom name for a WooCommerce product attribute.
	 *
	 * This function checks if a WooCommerce product attribute ID is mapped to a custom name
	 * specified in the Data Configuration tab. If a custom name is found, it returns that name.
	 * Otherwise, it returns the lowercase version of the original attribute name. Due to the
	 * structure of the custom attributes mapping, it is necessary to flip the keys and the
	 * values to achieve the described purpose.
	 *
	 * Example of custom attributes mapping structure:
	 *
	 * array(
	 *    "size_custom"  => "wc_2",
	 *    "color_custom" => "wc_1",
	 *    [...]
	 * );
	 *
	 * @param array $product_attribute An associative array representing the product attribute, containing:
	 *                                 - 'id'   (int): The WooCommerce attribute ID.
	 *                                 - 'name' (string): The default attribute name.
	 * @param array $custom_attr_fields An associative array of custom attribute fields, where each custom name maps
	 *                                  to a WooCommerce attribute key (e.g., 'wc_{id}').
	 *
	 * @return string The custom attribute name if found; otherwise, the original attribute name in lowercase.
	 */
	private static function get_real_product_attribute_name( $product_attribute, $custom_attr_fields ) {
		$wc_id                      = 'wc_' . $product_attribute['id'];
		$custom_attr_fields_mapping = array_flip( $custom_attr_fields );
		if ( array_key_exists( $wc_id, $custom_attr_fields_mapping ) ) {
			return $custom_attr_fields_mapping[ $wc_id ];
		}

		return strtolower( $product_attribute['name'] );
	}

	/**
	 * Get custom attributes for a product.
	 *
	 * @param integer $product_id The ID of the product.
	 * @param array   $custom_attr List of custom attributes.
	 *
	 * @return array The custom attributes for the product.
	 */
	public static function get_custom_attributes( $product_id, $custom_attr ) {

		$product_attributes = self::get_all_attributes( $product_id );
		$custom_attributes  = array();

		foreach ( $product_attributes as $attribute_name => $attribute_data ) {
			$attribute_slug = str_replace( 'pa_', '', $attribute_name );
			$found_key      = array_search( $attribute_slug, array_column( $custom_attr, 'attribute' ), true );

			// If the slug was not found, it is because the field has been renamed in the plugin's DooFinder panel.
			if ( false === $found_key ) {
				$attribute_slug = self::get_slug_from_map_attributes( $custom_attr, $attribute_slug );
				$found_key      = (bool) $attribute_slug;
			}

			if ( false !== $found_key ) {
				$attribute_options                    = is_string( $attribute_data ) ? array( $attribute_data ) : $attribute_data->get_slugs();
				$custom_attributes[ $attribute_slug ] = array();
				foreach ( $attribute_options as $option ) {
					// If it is an attribute with taxonomy, we need to get taxonomy value.
					if ( taxonomy_exists( $attribute_name ) ) {
						$term   = get_term_by( 'slug', $option, $attribute_name );
						$option = $term ? preg_replace( '/(?<!\/)\/(?!\/)/', '//', html_entity_decode( wp_strip_all_tags( $term->name ) ) ) : '';
					}
					$custom_attributes[ $attribute_slug ][] = $option;
				}

				if ( ! empty( $custom_attributes[ $attribute_slug ] ) &&
					is_array( $custom_attributes[ $attribute_slug ] ) &&
					1 === count( $custom_attributes[ $attribute_slug ] ) ) {
					$custom_attributes[ $attribute_slug ] = $custom_attributes[ $attribute_slug ][0];
				}
			}
		}

		return $custom_attributes;
	}

	/**
	 * Obtain all attributes of product (basic and custom).
	 *
	 * @param integer $product_id The ID of the product.
	 *
	 * @return array List of attributes
	 */
	private static function get_all_attributes( $product_id ) {

		$product_attributes = wc_get_product( $product_id )->get_attributes();
		$basic_attributes   = get_post_meta( $product_id );
		$basic_clean        = array();

		foreach ( $basic_attributes as $key_attr => $basic_attr ) {
			$key_attr                 = '_' === $key_attr[0] ? substr( $key_attr, 1 ) : $key_attr;
			$basic_clean[ $key_attr ] = $basic_attr[0] ?? '';
		}
		return array_merge( $product_attributes, $basic_clean );
	}

	/**
	 * To obtain the slug mapped from the original product attribute.
	 *
	 * @param array  $custom_attr Array of custom attributes.
	 * @param string $attribute_slug slug we are looking for.
	 *
	 * @return string Found slug or false
	 */
	private static function get_slug_from_map_attributes( $custom_attr, $attribute_slug ) {

		$all_attributes            = wc_get_attribute_taxonomies();
		$custom_map                = array_column( $custom_attr, 'field', 'attribute' );
		$normalized_attribute_slug = str_replace( '-', '_', $attribute_slug );

		foreach ( $all_attributes as $attribute ) {
			$normalized_attribute_name = str_replace( '-', '_', $attribute->attribute_name );

			if ( $normalized_attribute_name === $normalized_attribute_slug ) {
				$found_key  = (int) $attribute->attribute_id;
				$custom_key = 'wc_' . $found_key;

				if ( isset( $custom_map[ $custom_key ] ) ) {
					return $custom_map[ $custom_key ];
				}
			}
		}
		return false;
	}


	/**
	 * Get the category path for a product.
	 *
	 * @param array $category_ids The array of category IDs.
	 * @return array The array of category paths.
	 */
	private static function get_category_path( $category_ids ) {

		$category_paths = array();

		foreach ( $category_ids as $category_id ) {
			$category_path = self::get_category_hierarchy( $category_id['id'] );
			if ( ! empty( $category_path ) ) {
				$category_paths[] = self::process_content( $category_path );
			}
		}
		return $category_paths;
	}

	/**
	 * Get the hierarchy of a category.
	 *
	 * @param int $category_id The ID of the category.
	 * @return string The category hierarchy.
	 */
	private static function get_category_hierarchy( $category_id ) {
		$category = get_term( $category_id, self::TAXONOMY );
		if ( is_wp_error( $category ) ) {
			return '';
		}
		$category_path = $category->name;
		$parent_id     = $category->parent;

		while ( ! empty( $parent_id ) ) {
			$parent_category = get_term( $parent_id, self::TAXONOMY );
			if ( ! is_wp_error( $parent_category ) ) {
				$category_path = $parent_category->name . ' > ' . $category_path;
				$parent_id     = $parent_category->parent;
			}
		}
		return $category_path;
	}
}
