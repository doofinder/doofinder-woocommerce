<?php

namespace Doofinder\WC;

use Doofinder\WC\Api\Api_Factory;
use Doofinder\WC\Data_Feed;
use Doofinder\WC\Settings\Settings;
use Doofinder\WC\Multilanguage\Multilanguage;
use Doofinder\WC\Log;


defined( 'ABSPATH' ) or die();

class Post {

	/**
	 * Instance of a class used to log to a file.
	 *
	 * @var Log
	 */
	public static $log;

	/**
	 * A list of option names/indexes for the additional options of the plugin.
	 *
	 * Kept here in one place, so we don't have to repeat the strings everywhere.
	 *
	 * @var array[]
	 */
	public static $options = array(
		'visibility' => array(
			'form_name' => 'doofinder-for-wc-indexing-visibility',
			'meta_name' => '_doofinder_for_wc_indexing_visibility',
		),

		'yoast_visibility' => array(
			'meta_name' => '_yoast_wpseo_meta-robots-noindex'
		)
	);

	/**
	 * List of all available options of indexing visibility,
	 * kept in one place to avoid repeating it everywhere it's used.
	 *
	 * @var string[]
	 */
	private static $visibilityOptions = array(
		'auto',
		'index',
		'noindex',
	);

	/**
	 * Instance of class handling multilanguage environments.
	 *
	 * @var Language_Plugin
	 */
	private static $language;

	/**
	 * WP_Post this class represents.
	 *
	 * @var \WP_Post
	 */
	private $post;

	/**
	 * Array of meta for this post.
	 *
	 * This is optional. Meta fields can be provided in order to reduce the number
	 * of DB queries if for example these were fetched earlier.
	 *
	 * If it's not provided, then this class will fetch meta fields
	 * when necessary.
	 *
	 * @var \stdClass[]
	 */
	private $meta = null;

	/**
	 * Visibility of current post set in the settings.
	 *
	 * This is not total visibility of the page (accounting for settings,
	 * post status, etc.), just whatever's set in the
	 * visibility metabox.
	 *
	 * @var string
	 */
	private $visibility = 'auto';

	/**
	 * Post visibility set in Yoast settings.
	 *
	 * @var string|int
	 */
	private $yoast_visibility;

	public $post_update_time;

	/**
	 * Add metaboxes with additional settings to all indexable
	 * post types.
	 */
	public static function add_additional_settings() {
		add_action( 'add_meta_boxes', function () {
			$post_types = Post_Types::instance();

			add_meta_box(
				'doofinder-for-wc-visibility-settings',
				__( 'Doofinder - Indexing', 'doofinder_for_wc' ),
				function () {
					self::render_html_indexing_visibility();
				},
				$post_types->get_indexable(),
				'side'
			);
		} );

		add_action( 'save_post', function ( $post_id ) {
			self::handle_additional_settings_save( $post_id );
		} );
	}

	/**
	 * Register webhooks for saving, and removing the post.
	 */
	public static function register_webhooks() {
		add_action( 'wp_insert_post', function ( $post_id, \WP_Post $post, $updated ) {
			self::check_indexable( $post, $updated );
		}, 99, 3 );
	}

	/**
	 * Register api webhooks for creating, modyfing, and removing the post
	 * via REST API requests. In the case of modification of
	 * products by REST API requests, the index should be updated
	 * to avoid displaying outdated or non-existent products.
	 */
	public static function register_rest_api_webhooks() {
		self::$log = new Log( 'api.txt' );

		add_action( 'woocommerce_rest_delete_product_object', function ( $post, $request )  {
			$post = get_post( $request->data['id'] );

			self::$log->log( 'API request - delete action.' );
			self::check_indexable( $post, true );
		}, 99, 2 );

		add_action( 'woocommerce_rest_insert_product_object', function ( $post, $request )  {
			$post = get_post( $post->get_id() );

			self::$log->log( 'API request - insert action.' );
			self::check_indexable( $post, true );
		}, 99, 2 );
	}

	/**
     * Only allowed post types should be indexable.
     *
     * @param \WP_Post $post
     * @param bool $updated
     */
    private static function check_indexable($post, $updated)
    {
        $post_types = Post_Types::instance();

        /**
         * If the post is not indexable OR update on save is disabled we don't
         * want to send request to the API so we skip the update_post process
         */
        if (! in_array($post->post_type, $post_types->get_indexable())
            || !Settings::is_update_on_save_enabled()) {
            return;
        }
        self::webhook_update_post($post, $updated);
    }

	/**
	 * Render the contents of the metabox containing the visibility settings.
	 */
	private static function render_html_indexing_visibility() {
		$option_name = self::$options['visibility']['form_name'];
		$meta_name   = self::$options['visibility']['meta_name'];

		$saved_value = get_post_meta( get_the_ID(), $meta_name, true );

		?>

        <select name="<?php echo $option_name; ?>" class="widefat">
			<?php foreach ( self::$visibilityOptions as $option ): ?>
                <option value="<?php echo $option; ?>"

					<?php if ( $saved_value && $saved_value === $option ): ?>
                        selected
					<?php endif; ?>
                >
					<?php echo $option; ?>
                </option>
			<?php endforeach; ?>
        </select>

		<?php
	}

	/**
	 * Executed when the post is being saved.
	 *
	 * If the indexing visibility settings is present in the request
	 * save it in the meta for later use.
	 *
	 * @param int $post_id
	 */
	private static function handle_additional_settings_save( $post_id ) {
		$option_name = self::$options['visibility']['form_name'];
		$meta_name   = self::$options['visibility']['meta_name'];

		// Is the option being saved?
		if ( ! isset( $_REQUEST[ $option_name ] ) ) {
			return;
		}

		// We only allow few specific values, so make sure
		// someone does not try to save some funny business.
		$visibility = $_REQUEST[ $option_name ];
		if ( ! in_array( $visibility, self::$visibilityOptions ) ) {
			return;
		}

		// Everything is ok, save the option in DB.
		update_post_meta( $post_id, $meta_name, $visibility );
	}

	/**
	 * Send the post to Doofinder API when saving.
	 *
	 * Remove post from index if the post settings suggest
	 * that the post should not be indexed.
	 *
	 * @param \WP_Post $post
	 * @param bool $updated
	 */
	private static function webhook_update_post( $post, $updated ) {

		// Get update time. Pass this to api methods below so the index update time is
		// equal to db update time.
		$update_time = time();

		// Update last modified date for posts in db
		Settings::set_last_modified_db('', $update_time);

		// IF Doofinder search and Doofinder JS layer is disabled we don't want to send
		// request to the API so we exit early
		if ( !Settings::is_internal_search_enabled() && !Settings::is_js_layer_enabled()  ) {

			return;
		}

		$doofinder_post = new Post( $post );

		// When we create a new post in Wordpress, it is automatically saved
		// with post status 'auto-draft', so to prevent unnecessary API calls
		// check if post has status 'auto-draft' and exit early if true
		if ( $doofinder_post->post->post_status === 'auto-draft' ) {
			return;
		}


		$api            = Api_Factory::get();
		$lang 			= Multilanguage::instance();
		$active_lang 	= $lang->get_active_language();


		// If post is of type 'product' we want to collect post data via
		// Data_Feed class and get_items() method insead of Post class and
		// format_for_api() method.

		if ($doofinder_post->post->post_type === 'product') {
			$data_feed = new Data_Feed( false, [$doofinder_post->post->ID], $active_lang);
			$post_data = $data_feed->get_items()[0] ?? null;
		} else {
			$post_data = $doofinder_post->format_for_api();
		}

		// If posts settings suggest that it can be indexed
		// we update its data in the API.
		if ( $doofinder_post->is_indexable() ) {
			$api->update_item(
				$doofinder_post->post->post_type,
				$doofinder_post->post->ID,
				$post_data,
				$update_time
			);

			return;
		}

		// Post cannot be indexed (it's not published, moved to trash, etc),
		// we remove it from the index.
		$api->remove_item(
			$doofinder_post->post->post_type,
			$doofinder_post->post->ID,
			$update_time
		);
	}

	/**
	 * Post constructor.
	 *
	 * @param \WP_Post|int $post
	 * @param \stdClass[] $meta
	 */
	public function __construct( $post, $meta = null ) {
		if ( $meta !== null ) {
			$this->meta = $meta;
			$this->process_meta();
		}

		if ( is_a( $post, \WP_Post::class ) ) {
			$this->post = $post;

			return;
		}

		$this->post = get_post( $post );

		$this->language = Multilanguage::instance();


	}

	/**
	 * Determine if the current post should be indexed.
	 *
	 * All published posts will be indexed, but the setting in metabox
	 * can override that.
	 *
	 * @return bool
	 */
	public function is_indexable() {
		// Posts visibility settings are stored in meta, so we need to
		// fetch it from the database if we don't have it yet.
		if ( ! $this->meta ) {
			$this->fetch_meta();
		}

		// Visibility setting from the metabox has the highest priority.
		if ( $this->visibility === 'index' ) {
			return true;
		}

		if ( $this->visibility === 'noindex' ) {
			return false;
		}

		// If visibility is "auto" let's look at Yoast settings.
		// Yoast saves it's settings as 1 or 2, so it's set if it's not empty.
		// If Yoast settings is set to "auto" Yoast will remove meta
		// from DB entirely, so we don't have to worry about that.
		if ( $this->yoast_visibility ) {
			if ( (int) $this->yoast_visibility === 1 ) {
				return false;
			}

			if ( (int) $this->yoast_visibility === 2 ) {
				return true;
			}
		}

		// Visibility is "auto", and there are no Yoast settings.
		// In that case post status is checked.
		// First of all - password protected posts are not publicly visible
		// so don't index them.
		if ( $this->post->post_password ) {
			return false;
		}

		// Default post_status of attachments is "inherit"
		// so we need to check it separately.
		if ( $this->post->post_status === 'publish' || ( $this->post->post_type === 'attachment' && $this->post->post_status === 'inherit' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Generate (from post data and post meta) array that Doofinder API accepts.
	 *
	 * @return array
	 */
	public function format_for_api() {
		// Base data, that all posts must have.
		// All other data will be added if present.
		$data = array(
			'id'        => $this->post->ID,
			'title'     => $this->post->post_title,
			'link'      => get_the_permalink( $this->post ),
			'post_date' => $this->get_post_date()
		);

		// Post content.
		$content = $this->get_content();
		if ( $content ) {
			$data['content'] = $content;
		}

		// Post description.
		// Excerpt serves as a description.
		$description = $this->get_excerpt();
		if ( $description ) {
			$data['description'] = $description;
		}

		// Post thumbnail.
		$thumbnail = $this->get_thumbnail();
		if ( $thumbnail ) {
			$data['image_link'] = $thumbnail;
		}

		// Add meta.
		$meta = $this->get_meta_fields();
		if ( $meta ) {
			$data = array_merge( $data, $meta );
		}

		// Add categories.
		if ( Settings::get_index_categories() ) {
			$data['categories'] = $this->get_categories();
		}

		// Add tags.
		if ( Settings::get_index_tags() ) {
			$data['tags'] = $this->get_tags();
		}

		// Add additional attributes.
		if ( Settings::get_additional_attributes() ) {
			$data = array_merge(
				$data,
				$this->get_additional_attributes()
			);
		}

		return $data;
	}

	/**
	 * Retrieve categories for the current post in the following format:
	 * parent category > child category > child of child
	 *
	 * @return string[]
	 */
	private function get_categories() {
		$all_categories       = get_categories();
		$post_categories      = wp_get_post_categories( $this->post->ID, array( 'fields' => 'all' ) );
		$formatted_categories = array();

		// We have categories as a regular array. Remap to associative
		// array indexed by Term ID for easier lookup.
		$categories = array();

		/** @var \WP_Term $term */
		foreach ( $all_categories as $term ) {
			$categories[ $term->term_id ] = $term;
		}

		foreach ( $post_categories as $category ) {
			$formatted_categories[] = join(
				' > ',
				array_reverse( $this->get_category_full_path(
					$categories,
					$category
				) )
			);
		}

		return $formatted_categories;
	}

	/**
	 * From a given category this function recursively follows the parents,
	 * and generates array representing chain of parents of categories.
	 *
	 * @param \WP_Term[] $categories
	 * @param \WP_Term $category
	 *
	 * @return string[]
	 */
	private function get_category_full_path( $categories, $category ) {
		$path = array( $category->name );

		if ( $category && $category->parent ) {
			return array_merge(
				$path,
				$this->get_category_full_path(
					$categories,
					$categories[ $category->parent ]
				)
			);
		}

		return $path;
	}

	/**
	 * Generate a list of tags of the current post to send to the API.
	 *
	 * @return string[]
	 */
	private function get_tags() {
		return array_map(
			function ( $tag ) {
				return $tag->name;
			},
			wp_get_post_tags( $this->post->ID )
		);
	}

	/**
	 * Prepare all meta fields of this post formatted for
	 * the Doofinder API. Meta fields will be sent along with other data
	 * in the following format:
	 * meta_{meta name} = {value}
	 *
	 * This method will fetch meta fields from DB if we don't
	 * have them yet.
	 *
	 * @return string[]
	 */
	public function get_meta_fields() {
		// Fetch meta fields from DB if we don't have them yet.
		if ( $this->meta === null ) {
			$this->fetch_meta();
		}

		$meta = array();
		foreach ( $this->meta as $post_meta ) {
			if ( ! isset( $post_meta->meta_key ) || ! isset( $post_meta->meta_value ) ) {
				continue;
			}

			$meta[ 'meta_' . $post_meta->meta_key ] = strip_tags( $post_meta->meta_value );
		}

		return $meta;
	}

	/**
	 * Return the date of the post in the following format:
	 * 2018-09-18T15:27:00Z
	 *
	 * @return string
	 */
	public function get_post_date() {
		return date( 'Y-m-d\TH:i:s\Z', strtotime( $this->post->post_date ) );
	}

	/**
	 * Prepare post content for the Doofinder API.
	 *
	 * Shortcodes will be processed (so we send to the API as much data
	 * as possible), and HTML will be removed (to have a clean content).
	 *
	 * @return string
	 */
	public function get_content() {
		$content = apply_filters( 'the_content', $this->post->post_content );
		$content = str_replace( ']]>', ']]&gt;', $content );
		$content = strip_tags( $content );
		$content = trim( str_replace( array( "\n", "\r" ), ' ', $content ) );

		return $content;
	}

	/**
	 * Prepare post excerpt for the Doofinder API.
	 *
	 * This is sent to the API separately from content, as "description".
	 * We'll generate excerpts from content when there is no excerpts
	 * provided by the users.
	 *
	 * @return string
	 */
	public function get_excerpt() {
		if ( ! $this->post->post_excerpt ) {
			// Return trimmed version of content if
			// excerpt field is empty
			return wp_trim_words( $this->get_content(), 55, '' );
		}

		// No processing should be required, as the field
		// for excerpt is just a textarea.
		return $this->post->post_excerpt;
	}

	/**
	 * Retrieve the post thumbnail.
	 *
	 * Small size image (WP size "thumbnail" if exists) will be returned.
	 * We check if the image physically exists on the drive, and generate it
	 * if it doesn't.
	 *
	 * @return bool
	 */
	public function get_thumbnail() {
		$thumbnail = new Thumbnail( $this->post );

		return $thumbnail->get();
	}

	/**
	 * Remove from the meta fields all the meta that should not be sent
	 * to the Doofinder API, and extract all information from them
	 * (like the additional post settings) in one go.
	 *
	 * This is done to reduce the number of database calls, and above all
	 * make sure we don't call the DB in a loop. Settings are fetched
	 * in one go with public meta for the API.
	 */
	private function process_meta() {
		$filtered_meta = array();

		foreach ( $this->meta as $post_meta ) {
			if ( ! isset( $post_meta->meta_key ) || ! isset( $post_meta->meta_value ) ) {
				continue;
			}

			// Meta field representing a visibility settings.
			// Extract the value from this, and do not add it to the
			// final collection of meta fields.
			if ( $post_meta->meta_key === self::$options['visibility']['meta_name'] ) {
				$this->visibility = $post_meta->meta_value;

				continue;
			}

			// Meta field from Yoast settings.
			// Extract value from meta, and do not add it to the
			// final collection of meta fields that goes to the API.
			if ( $post_meta->meta_key === self::$options['yoast_visibility']['meta_name'] ) {
				$this->yoast_visibility = $post_meta->meta_value;

				continue;
			}

			$filtered_meta[] = $post_meta;
		}

		$this->meta = $filtered_meta;
	}

	/**
     * Generate array of values generated from additional attributes settings.
     * These are configured by the user, so won't be exactly the same
     * in every installation.
     *
	 * @return array
	 */
	private function get_additional_attributes() {
		$additional_attributes = Settings::get_additional_attributes();
		$output                = array();

		foreach ( $additional_attributes as $attribute ) {
			$value = null;

			switch ( $attribute['attribute'] ) {
				case 'post_title':
					$value = $this->post->post_title;
					break;

				case 'post_content':
					$value = $this->get_content();
					break;

				case 'excerpt':
					$value = $this->get_excerpt();
					break;

				case 'permalink':
					$value = get_the_permalink( $this->post );
					break;

				case 'thumbnail':
					$value = $this->get_thumbnail();
					break;
			}

			if ( $value ) {
				$output[ $attribute['field'] ] = $value;
			}
		}

		return $output;
	}

	/**
	 * Fetch post meta information if not present yet.
	 *
	 * This does not fetch all meta, but only public meta, that will
	 * be sent to the Doofinder API.
	 */
	private function fetch_meta() {
		global $wpdb;

		if ( $this->meta !== null ) {
			return;
		}

		// Grab all non-public meta and as the only exception meta field
		// containing visibility settings.
		// This field will not be sent to the API, but we grab it now to avoid
		// making multiple calls to the DB.
		$post_id          = $this->post->ID;
		$visibility_meta  = self::$options['visibility']['meta_name'];
		$yoast_visibility = self::$options['yoast_visibility']['meta_name'];
		$query            = "
			SELECT post_id, meta_key, meta_value
			FROM $wpdb->postmeta
			WHERE $wpdb->postmeta.post_id = $post_id
			AND (
              $wpdb->postmeta.meta_key NOT LIKE '\_%' OR
              $wpdb->postmeta.meta_key = '$visibility_meta' OR
              $wpdb->postmeta.meta_key = '$yoast_visibility'
            )
			ORDER BY $wpdb->postmeta.post_id
		 ";

		$this->meta = $wpdb->get_results( $query, OBJECT );
		$this->process_meta();
	}
}
