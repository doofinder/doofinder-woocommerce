<?php
/**
 * DooFinder Endpoint_Custom methods.
 *
 * @package Doofinder\WP\Endpoints
 */

use Doofinder\WP\Endpoints;
use Doofinder\WP\Helpers\Helpers;
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
	const FIELDS   = array(
		'_embedded',
		'author',
		'categories',
		'content',
		'excerpt',
		'id',
		'image_link',
		'link',
		'post_tags',
		'slug',
		'title',
	);


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

		if ( ! $config_request ) {
			Endpoints::check_secure_token();

			$locale_or_lang_code = $request->get_param( 'lang' ) ?? '';
			$lang_code           = Helpers::apply_locale_to_rest_context( $locale_or_lang_code );

			// Get the 'fields' parameter from the request.
			$fields = ( 'all' === $request->get_param( 'fields' ) ) ? array() : self::get_fields();

			$config_request = array(
				'per_page' => $request->get_param( 'per_page' ) ?? self::PER_PAGE,
				'page'     => $request->get_param( 'page' ) ?? 1,
				'lang'     => $lang_code,
				'ids'      => $request->get_param( 'ids' ) ?? '',
				'type'     => $request->get_param( 'type' ) ?? '',
				'fields'   => $fields,
			);
		} else {
			$fields = ! empty( $config_request['fields'] ) ? explode( ',', $config_request['fields'] ) : array();
		}

		$items = self::get_items( $config_request );

		foreach ( $items as $item_data ) {

			if ( 'noindex' === get_post_meta( $item_data['id'], '_doofinder_for_wp_indexing_visibility', true ) ) {
				continue;
			}

			$custom_attr = Settings::get_post_custom_attributes();

			$filtered_data = ! empty( $fields ) ? array_intersect_key( $item_data, array_flip( $fields ) ) : $item_data;

			$filtered_data = self::get_title( $filtered_data );
			$filtered_data = self::get_content( $filtered_data );
			$filtered_data = self::get_description( $filtered_data );
			$filtered_data = self::get_author( $filtered_data, $fields, $config_request );
			$filtered_data = self::get_image_link( $filtered_data, $fields );
			$filtered_data = self::get_post_tags( $filtered_data, $fields );
			$filtered_data = self::get_categories( $filtered_data, $fields );
			$filtered_data = self::get_meta_attributes( $filtered_data, $custom_attr );
			$filtered_data = self::clear_unused_fields( $filtered_data );

			$modified_items[] = $filtered_data;
		}

		// Return the modified items data as a response.
		return new WP_REST_Response( $modified_items ?? array() );
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
	 * Get custom data from our endpoint products
	 *
	 * @param array  $ids ID product we want to get data.
	 * @param string $type Type of custom data.
	 *
	 * @return array  Array of custom data.
	 */
	public static function get_data( $ids, $type ) {

		$request_params = array(
			'ids'    => implode( ',', $ids ),
			'fields' => implode( ',', self::get_fields() ),
			'type'   => $type,
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
	 * Retrieves and merges custom meta field data from a post type into existing data.
	 *
	 * This function fetches metadata for a specified post using custom attribute configurations.
	 * It converts native meta field names into custom names specified in the plugin's Data Configuration tab.
	 * The function then merges the fetched metadata into the provided data array. Although get_post_meta allows
	 * '' as an option to retrieve all the fields in a single call, metadata calls always try to get the data
	 * from WordPress cache so there should not be any risk calling it in the loop. In fact, it
	 * could be worse getting every single metadata at once.
	 *
	 * @param array $data        The original data array, containing at least an 'id' key with the post ID.
	 * @param array $custom_attr An array of custom attributes, where each item is an associative array with:
	 *                           - 'attribute' (string): The native meta key in the database.
	 *                           - 'field'     (string): The custom name to assign in the returned data array.
	 *
	 * @return array The merged data array, including custom meta fields if available.
	 */
	private static function get_meta_attributes( $data, $custom_attr ) {
		foreach ( $custom_attr as $attr ) {
			$post_meta = get_post_meta( $data['id'], $attr['attribute'] );
			if ( null === $post_meta ) {
				continue;
			}
			$data[ $attr['field'] ] = self::format_metadata( $post_meta );
		}

		return $data;
	}

	/**
	 * Retrieves and processes the post tags information if requested by the fields.
	 *
	 * @param array $filtered_data The filtered data array.
	 * @param array $fields        The requested fields.
	 *
	 * @return array The filtered data array with post tags information if requested.
	 */
	private static function get_post_tags( $filtered_data, $fields ) {
		$filtered_data['post_tags'] = array();

		if ( in_array( 'post_tags', $fields, true ) && isset( $filtered_data['_embedded']['wp:term'][0] ) ) {
			$filtered_data['post_tags'] = self::get_terms( 'post_tag', $filtered_data['_embedded']['wp:term'] );
		}

		return $filtered_data;
	}

	/**
	 * Retrieves and processes the categories information if requested by the fields.
	 *
	 * @param array $filtered_data The filtered data array.
	 * @param array $fields        The requested fields.
	 *
	 * @return array The filtered data array with categories information if requested.
	 */
	private static function get_categories( $filtered_data, $fields ) {
		$filtered_data['categories'] = array();

		if ( in_array( 'categories', $fields, true ) && isset( $filtered_data['_embedded']['wp:term'][0] ) ) {
			$filtered_data['categories'] = self::get_terms( 'category', $filtered_data['_embedded']['wp:term'] );
		}

		return $filtered_data;
	}


	/**
	 * Retrieves and processes the title information if requested by the fields.
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
	 * Retrieves and processes the content information if requested by the fields.
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
	 * Retrieves and processes the description information if requested by the fields.
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
	 * Retrieves and processes the author information if requested by the fields.
	 *
	 * @param array $filtered_data Product data array.
	 * @param array $fields        The requested fields.
	 * @param array $config_request The configuration request array.
	 *
	 * @return array The filtered data array with author information if requested.
	 */
	private static function get_author( $filtered_data, $fields, $config_request ) {
		if ( in_array( 'author', $fields, true ) && 'posts' !== $config_request['type'] ) {
			$filtered_data['author'] = $filtered_data['_embedded']['author'][0]['name'] ?? 'Default';
		}

		return $filtered_data;
	}

	/**
	 * Retrieves and processes the image link information if requested by the fields.
	 *
	 * @param array $filtered_data The filtered data array.
	 * @param array $fields        The requested fields.
	 *
	 * @return array The filtered data array with image link information if requested.
	 */
	private static function get_image_link( $filtered_data, $fields ) {
		$filtered_data_array = json_decode( wp_json_encode( $filtered_data ), true );

		$should_obtain_image_link          = is_array( $filtered_data_array ) && in_array( 'image_link', $fields, true );
		$filtered_data_array['image_link'] = $should_obtain_image_link ? self::obtain_image_link( $filtered_data ) : null;

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

		if ( is_object( $size_image ) ) {
			$image_link = $filtered_data['_embedded']['wp:featuredmedia'][0]['media_details']['source_url'];
		} else {
			$medium_size_image = $size_image['medium'];
			$image_link        = is_array( $medium_size_image ) ? $medium_size_image['source_url'] : null;
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
		unset( $filtered_data['excerpt'] );
		unset( $filtered_data['_embedded'] );
		unset( $filtered_data['author'] );

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
	 * @param array $config_request Config request params (page, per_page, type).
	 * @return array|null   An array of items data or null on failure.
	 */
	private static function get_items( $config_request ) {
		// Retrieve the original items data.
		$request = new WP_REST_Request( 'GET', '/wp/v2/' . $config_request['type'] );
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
}
