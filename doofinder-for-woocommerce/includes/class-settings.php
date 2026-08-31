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
	}
}
