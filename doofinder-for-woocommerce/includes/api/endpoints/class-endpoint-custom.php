<?php
/**
 * DooFinder Endpoint_Custom methods.
 *
 * @package Doofinder\WP\Endpoints
 */

use Doofinder\WP\Endpoints;
use Doofinder\WP\Helpers\Helpers;
use Doofinder\WP\Multilanguage;
use Doofinder\WP\Settings;
use Doofinder\WP\Thumbnail;

/**
 * Class Endpoint_Custom
 *
 * This class defines various methods for handling item WordPress endpoints.
 */
class Endpoint_Custom {

	const PER_PAGE = 100;
	const CONTEXT  = 'doofinder/v1';
	const ENDPOINT = '/custom';

	/**
	 * Fields left out of the response.
	 *
	 * Only wrappers, traps and duplicates of something already emitted belong here. Deciding
	 * which fields are worth indexing is the search engine's job, so this is not the place to
	 * drop a field for being uninteresting.
	 */
	const EXCLUDED_FIELDS = array(
		// REST wrappers.
		'_embedded',
		'_links',
		'meta',
		'guid',
		// Already flattened into canonical fields.
		'excerpt',
		'author',
		// Raw term IDs; the names are emitted as `post_tags` and `categories`.
		'tags',
		// Not an item attribute.
		'password',
	);

	// Prefix every metafield gets, so its key can be kept verbatim without colliding with anything else.
	const META_PREFIX = 'meta_';

	/**
	 * Initialize the custom item endpoint.
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
						'callback'            => array( self::class, 'custom_endpoint' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
	}

	/**
	 * Custom item endpoint callback.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @param array           $config_request Array config for internal requests.
	 * @return WP_REST_Response Response containing modified data.
	 */
	public static function custom_endpoint( $request, $config_request = false ) {

		$multilanguage = Multilanguage::instance();
		if ( ! $config_request ) {
			Endpoints::check_secure_token();

			$locale_or_lang_code = $request->get_param( 'lang' ) ?? '';
			$lang_code           = Helpers::apply_locale_to_rest_context( $locale_or_lang_code );

			$config_request = array(
				'per_page' => $request->get_param( 'per_page' ) ?? self::PER_PAGE,
				'page'     => $request->get_param( 'page' ) ?? 1,
				'ids'      => $request->get_param( 'ids' ) ?? '',
				'type'     => $request->get_param( 'type' ) ?? '',
			);

			if ( $multilanguage->is_active() ) {
				$locale_or_lang_code    = $request->get_param( 'lang' ) ?? '';
				$lang_code              = Helpers::apply_locale_to_rest_context( $locale_or_lang_code );
				$config_request['lang'] = $lang_code;
			}
		} elseif ( $multilanguage->is_active() ) {
				// Apply locale context even when config_request is provided.
				$locale_or_lang_code = $config_request['lang'] ?? '';
				Helpers::apply_locale_to_rest_context( $locale_or_lang_code );
		}

		$type = $config_request['type'] ?? '';

		if ( taxonomy_exists( $type ) ) {
			return self::get_taxonomy( $config_request );
		}

		return self::get_post_type( $config_request );
	}

	/**
	 * Get custom data from our endpoint products
	 *
	 * @param array  $ids ID product we want to get data.
	 * @param string $type Type of custom data.
	 *
	 * @return array  Array of custom data.
	 */
	public static function get_data( $ids, $type ) {

		$request_params = array(
			'ids'  => implode( ',', $ids ),
			'type' => $type,
		);

		$items = self::custom_endpoint( false, $request_params )->data;

		array_walk(
			$items,
			function ( &$product ) {
				unset( $product['_links'] );
			}
		);

		return $items;
	}

	/**
	 * Get the taxonomy data.
	 *
	 * @param array $config_request Array config for internal requests.
	 *
	 * @return WP_REST_Response Response containing modified taxonomy data.
	 */
	private static function get_taxonomy( $config_request ) {
		$items = self::get_items( $config_request );

		foreach ( $items as $item_data ) {
			$filtered_data               = $item_data;
			$filtered_data['image_link'] = self::get_term_image_link( $item_data );

			$modified_items[] = self::clear_unused_fields( $filtered_data );
		}

		return new WP_REST_Response( $modified_items ?? array() );
	}

	/**
	 * Get the post type data.
	 *
	 * @param array $config_request Array config for internal requests.
	 *
	 * @return WP_REST_Response Response containing modified post type data.
	 */
	private static function get_post_type( $config_request ) {
		$items       = self::get_items( $config_request );
		$custom_attr = Settings::get_post_custom_attributes();

		foreach ( $items as $item_data ) {

			if ( 'noindex' === get_post_meta( $item_data['id'], '_doofinder_for_wp_indexing_visibility', true ) ) {
				continue;
			}

			$filtered_data = self::get_title( $item_data );
			$filtered_data = self::get_content( $filtered_data );
			$filtered_data = self::get_description( $filtered_data );
			$filtered_data = self::get_author( $filtered_data, $config_request );
			$filtered_data = self::get_image_link( $filtered_data );
			$filtered_data = self::get_post_tags( $filtered_data );
			$filtered_data = self::get_categories( $filtered_data );
			$filtered_data = self::get_meta_attributes( $filtered_data );
			$filtered_data = self::apply_legacy_aliases( $filtered_data, $custom_attr );
			$filtered_data = self::clear_unused_fields( $filtered_data );

			$modified_items[] = $filtered_data;
		}

		// Return the modified items data as a response.
		return new WP_REST_Response( $modified_items ?? array() );
	}

	/**
	 * Get every metafield of the item as a flat field, named `meta_` plus its key.
	 *
	 * Unlike products, there is no WooCommerce filter here: `get_post_meta` exposes the whole
	 * meta of the item, WordPress internals included.
	 *
	 * @param array $data The item data, containing at least an 'id' key.
	 * @return array The data with one flat field per metafield.
	 */
	private static function get_meta_attributes( $data ) {
		$meta = get_post_meta( $data['id'] );

		if ( empty( $meta ) || ! is_array( $meta ) ) {
			return $data;
		}

		foreach ( $meta as $meta_key => $meta_values ) {
			$field = self::meta_output_field_name( $meta_key );

			if ( '' === $field ) {
				continue;
			}

			$data[ $field ] = self::format_metadata( $meta_values );
		}

		return $data;
	}

	/**
	 * Build the name of a metafield, without the output prefix.
	 *
	 * The key is kept as it is stored, leading underscores included: `_ean` and `ean` are two
	 * different metafields and have to stay apart. The emitted field adds `META_PREFIX`, which
	 * is also what makes a `custom_` guard unnecessary here — no prefixed name can reach a
	 * canonical field.
	 *
	 * @param string $meta_key The meta key, as stored in `wp_postmeta`.
	 * @return string The metafield name, or an empty string when the key is unusable.
	 */
	private static function meta_field_name( $meta_key ) {
		return strtolower( trim( urldecode( (string) $meta_key ) ) );
	}

	/**
	 * Build the output field name of a metafield.
	 *
	 * @param string $meta_key The meta key, as stored in `wp_postmeta`.
	 * @return string The field name, or an empty string when the key is unusable.
	 */
	private static function meta_output_field_name( $meta_key ) {
		$name = self::meta_field_name( $meta_key );

		return '' === $name ? '' : self::META_PREFIX . $name;
	}

	/**
	 * Rename the emitted fields to the names the merchant has stored.
	 *
	 * The removal happens after every name has been copied, not inside the loop: two rows may
	 * point at the same meta key, and dropping it on the first would leave the second empty.
	 *
	 * @param array $data        The item data.
	 * @param array $custom_attr The stored name map.
	 * @return array The data with the stored names applied.
	 */
	private static function apply_legacy_aliases( $data, $custom_attr ) {
		$renamed = array();

		foreach ( $custom_attr as $attr ) {
			$alias = $attr['field'] ?? '';

			// The stored map names the meta key, not the field it is emitted under.
			$emitted = self::meta_output_field_name( $attr['attribute'] ?? '' );

			if ( '' === $alias || '' === $emitted || $alias === $emitted ) {
				continue;
			}

			if ( isset( $data[ $alias ] ) || ! isset( $data[ $emitted ] ) ) {
				continue;
			}

			$data[ $alias ] = $data[ $emitted ];
			$renamed[]      = $emitted;
		}

		foreach ( $renamed as $emitted_field ) {
			unset( $data[ $emitted_field ] );
		}

		return $data;
	}

	/**
	 * Retrieves and processes the post tags information.
	 *
	 * @param array $filtered_data The filtered data array.
	 *
	 * @return array The filtered data array with post tags information if requested.
	 */
	private static function get_post_tags( $filtered_data ) {
		$filtered_data['post_tags'] = array();

		if ( isset( $filtered_data['_embedded']['wp:term'][0] ) ) {
			$filtered_data['post_tags'] = self::get_terms( 'post_tag', $filtered_data['_embedded']['wp:term'] );
		}

		return $filtered_data;
	}

	/**
	 * Retrieves and processes the categories information.
	 *
	 * @param array $filtered_data The filtered data array.
	 *
	 * @return array The filtered data array with categories information if requested.
	 */
	private static function get_categories( $filtered_data ) {
		$filtered_data['categories'] = array();

		if ( isset( $filtered_data['_embedded']['wp:term'][0] ) ) {
			$filtered_data['categories'] = self::get_terms( 'category', $filtered_data['_embedded']['wp:term'] );
		}

		return $filtered_data;
	}


	/**
	 * Retrieves and processes the title information.
	 *
	 * @param array $filtered_data The filtered data array.
	 *
	 * @return array The filtered data array with title information if requested.
	 */
	private static function get_title( $filtered_data ) {
		$filtered_data['title'] = self::process_content( $filtered_data['title']['rendered'] ?? '' );

		return $filtered_data;
	}

	/**
	 * Retrieves and processes the content information.
	 *
	 * @param array $filtered_data The filtered data array.
	 *
	 * @return array The filtered data array with content information if requested.
	 */
	private static function get_content( $filtered_data ) {
		$filtered_data['content'] = self::process_content( $filtered_data['content']['rendered'] ?? '' );

		return $filtered_data;
	}

	/**
	 * Retrieves and processes the description information.
	 *
	 * @param array $filtered_data The filtered data array.
	 *
	 * @return array The filtered data array with description information if requested.
	 */
	private static function get_description( $filtered_data ) {
		$filtered_data['description'] = self::process_content( $filtered_data['excerpt']['rendered'] ?? '' );

		return $filtered_data;
	}

	/**
	 * Retrieves and processes the author information.
	 *
	 * @param array $filtered_data Product data array.
	 * @param array $config_request The configuration request array.
	 *
	 * @return array The filtered data array with author information if requested.
	 */
	private static function get_author( $filtered_data, $config_request ) {
		if ( 'posts' !== $config_request['type'] ) {
			$filtered_data['author'] = $filtered_data['_embedded']['author'][0]['name'] ?? 'Default';
		}

		return $filtered_data;
	}

	/**
	 * Retrieves and processes the image link information.
	 *
	 * @param array $filtered_data The filtered data array.
	 *
	 * @return array The filtered data array with image link information if requested.
	 */
	private static function get_image_link( $filtered_data ) {
		$filtered_data_array = json_decode( wp_json_encode( $filtered_data ), true );

		$filtered_data_array['image_link'] = is_array( $filtered_data_array ) ? self::obtain_image_link( $filtered_data ) : null;

		return $filtered_data_array;
	}

	/**
	 * Obtains the image link, either from media sources or using methods from the thumbnail class.
	 *
	 * @param array $filtered_data The filtered data array.
	 *
	 * @return string|null $image_link The image link or `null` if the filtered fields don't include any image size.
	 */
	private static function obtain_image_link( $filtered_data ) {
		$image_link = null;

		if ( empty( $filtered_data['_embedded']['wp:featuredmedia'][0]['media_details'] ) ) {
			return $image_link;
		}

		$media_details = $filtered_data['_embedded']['wp:featuredmedia'][0]['media_details'];

		// In some rare cases, the media_details is an empty stdObject. We ignore those.
		if ( is_object( $media_details ) || empty( $media_details['sizes'] ) ) {
			return $image_link;
		}

		$size_image = $filtered_data['_embedded']['wp:featuredmedia'][0]['media_details']['sizes'];

		$image_link = null;

		if ( is_object( $size_image ) ) {
			$image_link = $filtered_data['_embedded']['wp:featuredmedia'][0]['media_details']['source_url'];
		} elseif ( isset( $size_image['medium']['source_url'] ) ) {
			$image_link = $size_image['medium']['source_url'];
		} else {
			$first_size = reset( $size_image );
			if ( isset( $first_size['source_url'] ) ) {
				$image_link = $first_size['source_url'];
			}
		}

		if ( is_null( $image_link ) ) {
			$post = get_post( $filtered_data['id'] );
			if ( ! empty( $post ) ) {
				$thumbnail  = new Thumbnail( $post );
				$image_link = $thumbnail->get();
				$image_link = self::add_base_url_if_needed( $image_link );
			}
		}

		return $image_link;
	}

	/**
	 * Check that image link is absolute, if not, add the site url
	 *
	 * @param string $image_link Absolute or relative URL of the image.
	 * @return string $image_link
	 */
	private static function add_base_url_if_needed( $image_link ) {
		if ( 0 === strpos( $image_link, '/' ) ) {
			$image_link = get_site_url() . $image_link;
		}
		return $image_link;
	}

	/**
	 * Clears unused fields from the filtered data array.
	 *
	 * This function removes specific keys from the provided array, including "excerpt," "_embedded," and "author."
	 *
	 * @param array $filtered_data The data array to be processed.
	 *
	 * @return array The processed data array with unused fields removed.
	 */
	private static function clear_unused_fields( $filtered_data ) {
		foreach ( self::EXCLUDED_FIELDS as $excluded_field ) {
			unset( $filtered_data[ $excluded_field ] );
		}

		return $filtered_data;
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
	 * Retrieves the names of taxonomies of a specific type within an array of items.
	 *
	 * @param string $type The taxonomy type to search for (e.g., "category" or "post_tag").
	 * @param array  $array_items The array of items containing taxonomy information.
	 * @return array An array of taxonomy names that match the specified type.
	 */
	private static function get_terms( $type, $array_items ) {
		$names = array();
		foreach ( $array_items as $array_item ) {
			foreach ( $array_item as $item ) {
				if ( isset( $item['taxonomy'] ) && $type === $item['taxonomy'] ) {
					$names[] = self::process_content( $item['name'] );
				}
			}
		}
		return $names;
	}

	/**
	 * Retrieve a list of items with pagination.
	 *
	 * Handles both post types and taxonomies: for taxonomies the REST base is
	 * resolved via get_taxonomy() since it may differ from the taxonomy slug
	 * (e.g. 'category' → 'categories').
	 *
	 * @param array $config_request Config request params (page, per_page, type).
	 * @return array|null   An array of items data or null on failure.
	 */
	private static function get_items( $config_request ) {
		$type = $config_request['type'];

		if ( taxonomy_exists( $type ) ) {
			$taxonomy_obj = get_taxonomy( $type );
			$rest_base    = ! empty( $taxonomy_obj->rest_base ) ? $taxonomy_obj->rest_base : $type;
		} else {
			$rest_base = $type;
		}

		// Retrieve the original items data.
		$request = new WP_REST_Request( 'GET', '/wp/v2/' . $rest_base );
		$request->set_query_params(
			array(
				'page'     => ! empty( $config_request['page'] ) ? $config_request['page'] : 1,
				'per_page' => ! empty( $config_request['per_page'] ) ? $config_request['per_page'] : self::PER_PAGE,
				'lang'     => ! empty( $config_request['lang'] ) ? $config_request['lang'] : '',
				'include'  => ! empty( $config_request['ids'] ) ? $config_request['ids'] : '',
				'orderby'  => 'id',
				'order'    => 'asc',
			)
		);
		$response = rest_do_request( $request );
		$data     = rest_get_server()->response_to_data( $response, true );

		if ( ! empty( $data['data']['status'] ) && WP_Http::OK !== $data['data']['status'] ) {
			$data = array();
		}

		return $data;
	}

	/**
	 * Formats post metadata for output.
	 *
	 * This function processes an array of metadata values, returning an empty string if the metadata is empty.
	 * If there is only one metadata item, it returns that item directly. Otherwise, it returns the full metadata array.
	 *
	 * @param array $meta_data The metadata to format, expected as an array of values.
	 *
	 * @return mixed Returns an empty string if metadata is empty, a single item if metadata contains only one element,
	 *               or the original array if there are multiple items.
	 */
	private static function format_metadata( $meta_data ) {
		if ( empty( $meta_data ) ) {
			return '';
		} elseif ( 1 === count( $meta_data ) ) {
			return $meta_data[0];
		}

		return $meta_data;
	}

	/**
	 * Returns the image link for a given taxonomy term.
	 *
	 * Looks up the term's thumbnail_id meta key, which is the standard used by
	 * WooCommerce product categories and similar taxonomies. Returns an empty string
	 * if no image is associated with the term.
	 *
	 * @param array $term Term data as an array containing at least 'id'.
	 * @return string The image URL, or empty string if no image is found.
	 */
	private static function get_term_image_link( $term ) {
		$thumbnail_id = get_term_meta( $term['id'], 'thumbnail_id', true );
		$image_link   = empty( $thumbnail_id ) ? '' : wp_get_attachment_url( $thumbnail_id );

		if ( empty( $image_link ) ) {
			return '';
		}

		return self::add_base_url_if_needed( $image_link );
	}
}
