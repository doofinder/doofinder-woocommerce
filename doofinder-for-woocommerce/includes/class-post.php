<?php
/**
 * DooFinder Post methods.
 *
 * @package Doofinder\WP\Post
 */

namespace Doofinder\WP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Doofinder Post Class, which has more DooFinder-related properties.
 */
class Post {

	/**
	 * A list of option names/indexes for the additional options of the plugin.
	 *
	 * Kept here in one place, so we don't have to repeat the strings everywhere.
	 *
	 * @var array[]
	 */
	public static $options = array(
		'visibility'       => array(
			'form_name' => 'doofinder-for-wp-indexing-visibility',
			'meta_name' => '_doofinder_for_wp_indexing_visibility',
		),

		'yoast_visibility' => array(
			'meta_name' => '_yoast_wpseo_meta-robots-noindex',
		),
	);

	/**
	 * List of all available options of indexing visibility,
	 * kept in one place to avoid repeating it everywhere it's used.
	 *
	 * @var string[]
	 */
	private static $visibility_options = array(
		'auto',
		'index',
		'noindex',
	);

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

	/**
	 * Add metaboxes with additional settings to all indexable
	 * post types.
	 */
	public static function add_additional_settings() {
		add_action(
			'add_meta_boxes',
			function () {
				add_meta_box(
					'doofinder-for-wp-visibility-settings',
					__( 'Doofinder - Indexing', 'wordpress-doofinder' ),
					function () {
						self::render_html_indexing_visibility();
					},
					get_post_types( array( 'public' => true ) ),
					'side'
				);
			}
		);

		add_action(
			'save_post',
			function ( $post_id ) {
				self::handle_additional_settings_save( $post_id );
			}
		);
	}

	/**
	 * Render the contents of the metabox containing the visibility settings.
	 */
	private static function render_html_indexing_visibility() {
		$option_name = self::$options['visibility']['form_name'];
		$meta_name   = self::$options['visibility']['meta_name'];

		$saved_value = get_post_meta( get_the_ID(), $meta_name, true );

		?>

		<select name="<?php echo esc_attr( $option_name ); ?>" class="widefat">
			<?php foreach ( self::$visibility_options as $option ) : ?>
				<option value="<?php echo esc_attr( $option ); ?>"

					<?php if ( $saved_value && $saved_value === $option ) : ?>
						selected
					<?php endif; ?>
				>
					<?php echo esc_html( $option ); ?>
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
	 * @param int $post_id numeric ID of the WP_Post.
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
		$visibility = sanitize_text_field( wp_unslash( $_REQUEST[ $option_name ] ) );
		if ( ! in_array( $visibility, self::$visibility_options, true ) ) {
			return;
		}

		// Everything is ok, save the option in DB.
		update_post_meta( $post_id, $meta_name, $visibility );
	}

	/**
	 * Post constructor.
	 *
	 * @param \WP_Post|int $post WordPress Post object or its integer ID.
	 * @param \stdClass[]  $meta Additional post metadata.
	 */
	public function __construct( $post, $meta = null ) {
		if ( null !== $meta ) {
			$this->meta = $meta;
			$this->process_meta();
		}

		if ( is_a( $post, \WP_Post::class ) ) {
			$this->post = $post;

			return;
		}

		$this->post = get_post( $post );
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
		if ( 'trash' === $this->post->post_status ) {
			return false;
		}

		// Posts visibility settings are stored in meta, so we need to
		// fetch it from the database if we don't have it yet.
		if ( ! $this->meta ) {
			$this->fetch_meta();
		}

		// Visibility setting from the metabox has the highest priority.
		if ( 'index' === $this->visibility ) {
			return true;
		}

		if ( 'noindex' === $this->visibility ) {
			return false;
		}

		// If visibility is "auto" let's look at Yoast settings.
		// Yoast saves it's settings as 1 or 2, so it's set if it's not empty.
		// If Yoast settings is set to "auto" Yoast will remove meta
		// from DB entirely, so we don't have to worry about that.
		if ( $this->yoast_visibility ) {
			if ( 1 === (int) $this->yoast_visibility ) {
				return false;
			}

			if ( 2 === (int) $this->yoast_visibility ) {
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
		if ( 'publish' === $this->post->post_status || ( 'attachment' === $this->post->post_type && 'inherit' === $this->post->post_status ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Generate (from post data and post meta) array that DooFinder API accepts.
	 *
	 * @return array
	 */
	public function format_for_api() {
		// Base data, that all posts must have.
		// All other data will be added if present.
		$data = array(
			'id'        => (string) $this->post->ID,
			'title'     => $this->sanitize_html_entities( $this->post->post_title ),
			'link'      => get_the_permalink( $this->post ),
			'post_date' => $this->get_post_date(),
		);

		// Post content.
		$content = $this->get_content();
		if ( $content ) {
			$data['content'] = $this->sanitize_html_entities( $content );
		}

		// Post description.
		// Excerpt serves as a description.
		$description = $this->get_excerpt();
		if ( $description ) {
			$data['description'] = $this->sanitize_html_entities( $description );
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

		$data['categories'] = $this->get_categories();

		$data['tags'] = $this->get_tags();

		return $data;
	}

	/**
	 * Replaces the html entity of a text with their corresponding character
	 *
	 * @param string $text String to decode.
	 *
	 * @return string
	 */
	private function sanitize_html_entities( $text ) {
		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Retrieve categories for the current post in the following format:
	 * parent category > child category > child of child
	 *
	 * @return string[]
	 */
	private function get_categories() {
		$all_categories  = get_categories();
		$post_categories = wp_get_post_categories( $this->post->ID, array( 'fields' => 'all' ) );

		if ( taxonomy_exists( 'portfolio_category' ) ) {
			$all_categories  = array_merge( $all_categories, get_terms( 'portfolio_category' ) );
			$post_categories = array_merge( $post_categories, wp_get_object_terms( $this->post->ID, 'portfolio_category', array( 'fields' => 'all' ) ) );
		}

		$formatted_categories = array();

		// We have categories as a regular array. Remap to associative
		// array indexed by Term ID for easier lookup.
		$categories = array();

		/**
		 * A WordPress Term object.
		 *
		 * @var \WP_Term $term
		 */
		foreach ( $all_categories as $term ) {
			$categories[ $term->term_id ] = $term;
		}

		foreach ( $post_categories as $category ) {
			$formatted_categories[] = join(
				' > ',
				array_reverse(
					$this->get_category_full_path(
						$categories,
						$category
					)
				)
			);
		}

		return $formatted_categories;
	}

	/**
	 * From a given category this function recursively follows the parents,
	 * and generates array representing chain of parents of categories.
	 *
	 * @param \WP_Term[] $categories Category objects array.
	 * @param \WP_Term   $category Category object.
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
		if ( null === $this->meta ) {
			$this->fetch_meta();
		}

		$meta = array();
		foreach ( $this->meta as $post_meta ) {
			if ( ! isset( $post_meta->meta_key ) || ! isset( $post_meta->meta_value ) ) {
				continue;
			}

			$meta[ 'meta_' . $post_meta->meta_key ] = wp_strip_all_tags( $post_meta->meta_value );
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
		return get_the_date( 'Y-m-d\TH:i:s\Z', $this->post->ID );
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
		/**
		 * Filters the post content (e.g. interprets shortcodes).
		 *
		 * @since 1.0.0
		 *
		 * @param string $print Post content filtered.
		 */
		$content = apply_filters( 'the_content', $this->post->post_content );
		$content = str_replace( ']]>', ']]&gt;', $content );
		$content = wp_strip_all_tags( $content );
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
			// excerpt field is empty.
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
	 * Fetch post meta information if not present yet.
	 *
	 * This does not fetch all meta, but only public meta, that will
	 * be sent to the Doofinder API.
	 */
	private function fetch_meta() {
		global $wpdb;

		if ( null !== $this->meta ) {
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
			WHERE $wpdb->postmeta.post_id = " . esc_sql( $post_id ) . "
			AND (
              $wpdb->postmeta.meta_key NOT LIKE '\_%' OR
              $wpdb->postmeta.meta_key = '" . esc_sql( $visibility_meta ) . "' OR
              $wpdb->postmeta.meta_key = '" . esc_sql( $yoast_visibility ) . "'
            )
			ORDER BY $wpdb->postmeta.post_id
		 ";

		// WordPress.DB.PreparedSQL can be ignored because we are sanitizing every sensitive data with `esc_sql()` function instead.
		$this->meta = $wpdb->get_results( $query, OBJECT ); // phpcs:ignore WordPress.DB.PreparedSQL
		$this->process_meta();
	}
}
