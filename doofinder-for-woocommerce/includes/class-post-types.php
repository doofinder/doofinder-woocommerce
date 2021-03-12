<?php

namespace Doofinder\WC;

use Doofinder\WC\Settings\Settings;
use Doofinder\WC\Log;

defined( 'ABSPATH' ) or die();

class Post_Types {

	/**
	 * Singleton instance of this class.
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Post types that are indexed by default, unless user
	 * changes the settings.
	 *
	 * @var string[]
	 */
	private static $default_post_types = array(
		'product'
	);

	/**
	 * Instance of a class used to log to a file.
	 *
	 * @var Log
	 */
	private $log;

	/**
	 * List of (processed, products removed) post type slugs
	 * that are available.
	 *
	 * @var string[]
	 */
	private $post_types = array();

	/**
	 * Generate (or retrieve if exists) a singleton instance of this class.
	 *
	 * @return Post_Types
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Determine if the given post type is indexed by default.
	 *
	 * @param string $post_type
	 *
	 * @return bool
	 */
	public static function is_default( $post_type ) {
		return in_array( $post_type, self::$default_post_types );
	}

	private function __construct() {

		$this->log = new Log( 'api.txt' );

		// get all public post_types
		$post_types = get_post_types( array( 'public' => true ) );

		// remove 'product' post_type
		// $product = array_search( 'product', $post_types );
		// unset( $post_types[ $product ] );

		// make array non associative
		$this->post_types = array_values( $post_types );
	}

	/**
	 * Retrieve list of all post types that hypothetically
	 * can be indexed. This does not take into account what
	 * the user selected in the settings, just all post types
	 * that are public, and not forbidden (like "product").
	 *
	 * @return string[]
	 */
	public function get() {
		return $this->post_types;
	}

	/**
	 * Retrieve the list of all post types that should be indexed.
	 * That accounts for what post types user selected in the settings.
	 *
	 * @return string[]
	 */
	public function get_indexable() {
		$all           = $this->get();
		$from_settings = Settings::get_post_types_to_index();
		
		// If there's nothing saved in settings, index
		// the default post types.
		if ( ! $from_settings ) {
			return self::$default_post_types;
		}


		// Double check there's nothing weird saved in settings.
		$allowed_post_types = array();
		foreach ( $from_settings as $post_type ) {
			if ( in_array( $post_type, $all ) ) {
				$allowed_post_types[] = $post_type;
			}
		}

		return $allowed_post_types;
	}
}
