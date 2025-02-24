<?php
/**
 * DooFinder Settings methods.
 *
 * @package Doofinder\WP\Settings
 */

namespace Doofinder\WP;

use Doofinder\WP\Multilanguage\Language_Plugin;
use Doofinder\WP\Multilanguage\Multilanguage;

use Doofinder\WP\Settings\Accessors;
use Doofinder\WP\Settings\Register_Settings;
use Doofinder\WP\Settings\Renderers;
use Doofinder\WP\Settings\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Settings Class.
 */
class Settings {

	use Accessors;
	use Register_Settings;
	use Renderers;
	use Helpers;

	/**
	 * Slug of the top-level menu page.
	 *
	 * Other classes can use this to register submenus.
	 *
	 * @var string
	 */
	public static $top_level_menu = 'doofinder_for_wp';

	/**
	 * List of keys that are reserved for custom attributes fields
	 */
	const RESERVED_CUSTOM_ATTRIBUTES_NAMES = array(
		'attributes',
		'availability',
		'best_price',
		'catalog_visibility',
		'categories',
		'description',
		'df_variants_information',
		'df_group_leader',
		'dimensions',
		'group_id',
		'id',
		'image_link',
		'link',
		'meta_data',
		'name',
		'parent_id',
		'price',
		'rating_count',
		'regular_price',
		'sale_price',
		'short_description',
		'sku',
		'slug',
		'tags',
		'title',
		'type',
		'variants',
		'stock_status',
	);

	/**
	 * List of valid regions
	 *
	 * @var array
	 */
	const VALID_REGIONS = array( 'eu1', 'us1', 'ap1' );

	/**
	 * Array of tab settings, indexed by the id of the tag (the GET variable
	 * representing given tab). Values contain:
	 * label - Displayed in the tab.
	 * fields_cb - Function registering settings under given tab.
	 *
	 * No default, because the names of the tabs need to be translated,
	 * so we need to run them through translating functions. This will
	 * be then set in the constructor.
	 *
	 * @var array
	 */
	private static $tabs;

	/**
	 * The only instance of Settings
	 *
	 * @var Settings
	 */
	private static $instance = null;

	/**
	 * Instance of the class handling multilanguage.
	 *
	 * @var Language_Plugin
	 */
	private $language;

	/**
	 * DooFinder WooCommerce products custom attributes name in options table.
	 *
	 * @var string
	 */
	public static $custom_attributes_option = 'doofinder_for_wp_custom_attributes';

	/**
	 * DooFinder posts custom attributes name in options table.
	 *
	 * @var string
	 */
	public static $post_custom_attributes_option = 'doofinder_for_wp_post_custom_attributes';

	/**
	 * DooFinder image size name in options table.
	 *
	 * @var string
	 */
	public static $image_size_option = 'doofinder_for_wp_image_size';

	/**
	 * Returns the only instance of Settings
	 *
	 * @since 1.0.0
	 * @return Settings
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Settings constructor.
	 */
	private function __construct() {
		$this->language = Multilanguage::instance();

		self::$tabs = array(
			'authentication' => array(
				'label'     => __( 'General Settings', 'wordpress-doofinder' ),
				'fields_cb' => 'add_general_settings',
			),
		);

		self::$tabs['data_configuration'] = array(
			'label'     => __( 'Data Configuration', 'wordpress-doofinder' ),
			'fields_cb' => 'add_data_settings',
		);

		$this->add_plugin_settings();
		$this->add_settings_page();
		static::initialize();
	}

	/**
	 * Initialize the settings by updating the WordPress options.
	 *
	 * @return void
	 */
	public static function initialize() {
		$option = self::$custom_attributes_option;
		add_action(
			"update_option_{$option}",
			function () {
				add_settings_error(
					'doofinder_for_wp_messages',
					'doofinder_for_wp_message',
					__( 'Product Custom Attributes updated successfully. <br/> Please, keep in mind that you need to reindex in order for the changes to be reflected in the search layer.', 'wordpress-doofinder' ),
					'success'
				);
			},
			10,
			3
		);

		$post_option = self::$post_custom_attributes_option;
		add_action(
			"update_option_{$post_option}",
			function () {
				add_settings_error(
					'doofinder_for_wp_messages',
					'doofinder_for_wp_message',
					__( 'Post Custom Attributes updated successfully. <br/> Please, keep in mind that you need to reindex in order for the changes to be reflected in the search layer.', 'wordpress-doofinder' ),
					'success'
				);
			},
			10,
			3
		);
	}
	/**
	 * Returns an array with select options structured by option groups
	 *
	 * @param bool $should_exclude_woocommerce_attributes Decides whether the WooCommerce attributes should be taken into account or not. This is intended for the second dropdown, which should display only basic attributes and metafields.
	 *
	 * @return array Array of option groups with options inside
	 */
	public static function get_additional_attributes_options( $should_exclude_woocommerce_attributes ) {
		static $additional_attributes_options;
		if ( ! isset( $additional_attributes_options ) ) {
			$fields        = include_once 'settings/attributes.php';
			$option_groups = array(
				'base_attribute' => array(
					'title'   => __( 'Basic attributes', 'wordpress-doofinder' ),
					'options' => array(),
				),
				'wc_attribute'   => array(
					'title'   => __( 'Product attributes', 'wordpress-doofinder' ),
					'options' => array(),
				),
				'metafield'      => array(
					'title'   => __( 'Metafields', 'wordpress-doofinder' ),
					'options' => array(),
				),
			);

			foreach ( $fields as $key => $attr ) {
				$type                                      = $attr['type'];
				$option_groups[ $type ]['options'][ $key ] = $attr;
			}
			$additional_attributes_options = $option_groups;
		}
		if ( $should_exclude_woocommerce_attributes ) {
			unset( $additional_attributes_options['wc_attribute'] );
			unset( $additional_attributes_options['base_attribute'] );
		}

		return $additional_attributes_options;
	}

	/**
	 * Make a request to the WooCommerce Products endpoint to get the available
	 * field list.
	 *
	 * @return array List of Product base attributes
	 */
	public static function get_product_rest_attributes() {
		$transient_name  = 'df_product_rest_attributes';
		$rest_attributes = get_transient( $transient_name );
		if ( false === $rest_attributes || isset( $_GET['force'] ) ) {
			try {
				$request         = new \WP_REST_Request( 'GET', '/wc/v3/products' );
				$result          = rest_get_server()->dispatch( $request );
				$rest_attributes = array_keys( $result->data[0] );
				$rest_attributes = static::filter_product_rest_attributes( $rest_attributes );
				set_transient( $transient_name, $rest_attributes, 600 );
			} catch ( \Throwable $th ) {
				$rest_attributes = array();
			}
		}

		return $rest_attributes;
	}

	/**
	 * Method that removes unwanted attributes from rest attribute list
	 *
	 * @param array $rest_attributes All the attributes returned by WC REST API.
	 *
	 * @return array List of valid attributes
	 */
	private static function filter_product_rest_attributes( $rest_attributes ) {
		/**
		 * Remove WC unwanted attributes
		 */
		$rest_attributes = array_diff(
			$rest_attributes,
			array(
				'grouped_products',
				'images',
				'meta_data',
				'name',
				'permalink',
				'price',
				'price_html',
				'status',
				'variations',
			)
		);
		return array_diff( $rest_attributes, static::RESERVED_CUSTOM_ATTRIBUTES_NAMES );
	}
}
