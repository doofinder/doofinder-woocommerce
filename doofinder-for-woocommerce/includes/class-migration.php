<?php
/**
 * DooFinder Migration methods.
 *
 * @package Doofinder\WP\Migration
 */

namespace Doofinder\WP;

use Doofinder\WP\Api\Store_Api;
use Doofinder\WP\Settings;
use Doofinder\WP\Log;
use Doofinder\WP\Reset_Credentials_Index;

/**
 * Manages everything regarding database migrations.
 */
class Migration {


	/**
	 * Instance of a class used to log to a file.
	 *
	 * @var Log
	 */
	private static $log;

	/**
	 * Attributes regarding to the dimensions.
	 *
	 * @var array
	 */
	private static $dimension_attributes = array(
		'width',
		'height',
		'length',
	);

	/**
	 * Additional attributes that were indexed in previous versions, but they are currently deprecated.
	 *
	 * @var array
	 */
	private static $deprecated_attributes = array(
		'post_title',
		'post_content',
		'permalink',
	);

	/**
	 * Try migrating old settings.
	 */
	public static function migrate() {
		self::$log = new Log( 'migration.log' );
		self::$log->log( 'Migrate - Start' );

		self::initialize_migration();
		$migration_result = self::do_woocommerce_migration();

		$token_auth = get_option( 'doofinder_for_wp_token' );

		if ( '' === $token_auth && Settings::is_configuration_complete() ) {
			self::$log->log( 'Migrate - We need to create the token.' );
			$store_api = new Store_Api();
			$store_api->normalize_store_and_indices();
		}
		self::finish_migration();
	}

	/**
	 * Function to migrate only custom attributes specifically when updating to
	 * the plugin version 2.0.13.
	 *
	 * @return void
	 */
	public static function migrate_custom_attributes() {
		self::$log = new Log( 'migration.log' );
		self::$log->log( 'Migrate Custom Attributes - Start' );

		self::initialize_migration();
		self::migrate_option( 'woocommerce_doofinder_feed_attributes_additional_attributes', 'doofinder_for_wp_custom_attributes' );
		self::finish_migration();
		self::add_notices();
	}

	/**
	 * Initialize the migration.
	 *
	 * @return void
	 */
	private static function initialize_migration() {
		add_filter( 'doofinder_for_wp_migration_transform_woocommerce_doofinder_feed_attributes_additional_attributes', array( self::class, 'transform_additional_attributes' ), 10, 1 );
	}

	/**
	 * Adds the migration notice.
	 *
	 * @return void
	 */
	public static function add_notices() {
		$migration_completed = 'completed' === get_option( Setup_Wizard::$wizard_migration_option );
		if ( $migration_completed ) {
			$notice_title   = __( 'Migration status', 'wordpress-doofinder' );
			$notice_message = __( 'Doofinder settings have been migrated successfully.', 'wordpress-doofinder' );
			$notice_name    = 'migration-status';
			Admin_Notices::add_notice( $notice_name, $notice_title, $notice_message, 'success' );
			// Set this notice to be shown once.
			Admin_Notices::set_show_once( $notice_name );
		}
	}

	/**
	 * This function migrates the options from the former woocommerce plugin to
	 * the current plugin options.
	 *
	 * @return bool
	 */
	private static function do_woocommerce_migration() {
		if ( get_option( 'woocommerce_doofinder_internal_search_api_key', false ) ) {
			// There was a woocommerce plugin installed, try to import data to the new plugin.
			$generic_options   = array(
				'woocommerce_doofinder_internal_search_api_key' => 'doofinder_for_wp_api_key',
				'woocommerce_doofinder_api_admin_endpoint' => 'doofinder_for_wp_api_host',
				'woocommerce_doofinder_feed_attributes_additional_attributes' => 'doofinder_for_wp_custom_attributes',
				'doofinder_for_wc_sector'                  => 'doofinder_sector',
			);
			$multilang_options = array(
				'woocommerce_doofinder_internal_search_hashid' => 'doofinder_for_wp_search_engine_hash',
				'woocommerce_doofinder_layer_enabled' => 'doofinder_for_wp_enable_js_layer',
				'woocommerce_doofinder_layer_code'    => 'doofinder_for_wp_js_layer',
			);

			// Migrate the generic options.
			foreach ( $generic_options as $wc_option_name => $wp_option_name ) {
				self::migrate_option( $wc_option_name, $wp_option_name );
			}

			// Migrate the Multilang options.
			$wizard        = Setup_Wizard::instance();
			$base_language = $wizard->language->get_base_language();
			$langs         = $wizard->language->get_languages();
			// define empty language for main language options.
			$langs[''] = '';

			foreach ( $langs as $lang_key => $value ) {
				$lang = ( $lang_key === $base_language ) ? '' : $lang_key;
				foreach ( $multilang_options as $wc_key => $wp_key ) {
					$wc_option_name = empty( $lang ) ? $wc_key : $wc_key . '_' . $lang;
					$wp_option_name = empty( $lang ) ? $wp_key : $wp_key . '_' . $lang;
					self::migrate_option( $wc_option_name, $wp_option_name );
				}
			}

			self::maybe_fix_api_host();
			self::maybe_set_region();

			return true;
		}
		return false;
	}

	/**
	 * Ensures that the API host URL is correctly formatted and updates it if necessary.
	 *
	 * This function checks the stored API host option for the presence of a URL prefix and
	 * the correct path. If the prefix is missing or incorrect, it adds 'https://' as the prefix.
	 * If the path does not match 'admin.doofinder.com', it appends '-admin.doofinder.com' to the host and updates the option.
	 *
	 * @return void
	 */
	private static function maybe_fix_api_host() {
		$api_host_option_name = 'doofinder_for_wp_api_host';
		$api_host             = get_option( $api_host_option_name );

		// Checks if api host contains prefix, then isolate prefix.
		if ( preg_match( '@-@', $api_host ) ) {
			$arr = explode( '-', $api_host );
		}

		$api_host_prefix = $arr[0] ?? null;
		$api_host_path   = $arr[1] ?? null;

		if ( ! preg_match( '#^((https?://))#i', $api_host_prefix ) ) {
			$api_host_prefix = 'https://' . $api_host_prefix;
		}

		if ( 'admin.doofinder.com' !== $api_host_path ) {
			$new_api_host = $api_host_prefix . '-admin.doofinder.com';
			update_option( $api_host_option_name, $new_api_host );
		}
	}

	/**
	 * Extracts the region from the API host URL and sets it in the settings.
	 *
	 * This function retrieves the `doofinder_for_wp_api_host` option, extracts the region code from the URL using a regular expression,
	 * and sets the region in the settings if it is found.
	 *
	 * @return void
	 */
	public static function maybe_set_region() {
		$api_host = get_option( 'doofinder_for_wp_api_host' );
		if ( ! $api_host ) {
			return;
		}

		$re = '/:\/\/(?<region>[a-z]{2}[0-9])-.*/m';
		preg_match_all( $re, $api_host, $matches, PREG_SET_ORDER, 0 );

		if ( ! empty( $matches ) && array_key_exists( 'region', $matches[0] ) ) {
			$region = $matches[0]['region'];
			Settings::set_region( $region );
		}
	}

	/**
	 * This function migrates the value of the first option into the second if it is empty.
	 *
	 * @param string $wc_option_name The woocommerce option that we are going to migrate.
	 * @param string $wp_option_name The WordPress option that we should create if it is empty.
	 *
	 * @return void
	 */
	private static function migrate_option( $wc_option_name, $wp_option_name ) {
		$current_option_value = get_option( $wp_option_name );
		if ( ! empty( $current_option_value ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			self::$log->log( "No need to migrate the wc option from '" . $wc_option_name . "' to '" . $wp_option_name . "', the value is already set to: \n" . print_r( $current_option_value, true ) );
		} else {
			$wc_option_value = get_option( $wc_option_name );
			/**
			 * Allows to override option values related to the migration.
			 *
			 * @since 1.1
			 */
			$wc_option_value = apply_filters( "doofinder_for_wp_migration_transform_$wc_option_name", $wc_option_value );

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			self::$log->log( "Migrate option from '" . $wc_option_name . "' to '" . $wp_option_name . "' with value: \n" . print_r( $wc_option_value, true ) );
			update_option( $wp_option_name, $wc_option_value );
		}
	}

	/**
	 * This function executes any needed processes after finalizing migrations.
	 * For example: update options and show migration notice.
	 *
	 * @return void
	 */
	private static function finish_migration() {
		// Migration completed.
		self::$log->log( 'Migrate - Migration Completed' );
		update_option( Setup_Wizard::$wizard_migration_option, 'completed' );
	}

	/**
	 * Transforms the former custom_attributes array to the new format
	 *
	 * @param array $additional_attributes Additional attributes as array.
	 *
	 * @return array Transformed custom attributes array.
	 */
	public static function transform_additional_attributes( $additional_attributes ) {
		if ( false === $additional_attributes ) {
			return $additional_attributes;
		}
		$transformed_attributes = array();
		foreach ( $additional_attributes as $key => $value ) {
			$attribute = array();
			foreach ( explode( '&', $value ) as $attr_value ) {
				$attr                  = explode( '=', $attr_value );
				$attribute[ $attr[0] ] = $attr[1];
				$attribute['type']     = 'base_attribute';
			}

			if ( strpos( $attribute['attribute'], 'pa_' ) === 0 ) {
				// Product attribute, find the wc_attribute_id.
				$attribute['attribute']         = static::transform_product_attribute( $attribute['attribute'] );
				$attribute['type']              = 'wc_attribute';
				$transformed_attributes[ $key ] = $attribute;
				continue;
			}

			if ( 'custom' === $attribute['attribute'] ) {
				// Custom Meta attribute, set the attribute from value.
				if ( ! isset( $attribute['value'] ) ) {
					// no value defined, ignore attribute.
					continue;
				}
				$attribute['type']      = 'metafield';
				$attribute['attribute'] = $attribute['value'];
				unset( $attribute['value'] );
				$transformed_attributes[ $key ] = $attribute;
				continue;
			}

			// Add the dimensions: for dimension attributes.
			if ( in_array( $attribute['attribute'], static::$dimension_attributes, true ) ) {
				$attribute['attribute']         = 'dimensions:' . $attribute['attribute'];
				$transformed_attributes[ $key ] = $attribute;
				continue;
			}

			// Add the dimensions: for dimension attributes.
			if ( in_array( $attribute['attribute'], static::$dimension_attributes, true ) ) {
				$attribute['attribute']         = 'dimensions:' . $attribute['attribute'];
				$transformed_attributes[ $key ] = $attribute;
				continue;
			}

			// Remove the attributes that we are not using anymore as they are being indexed by default.
			if ( in_array( $attribute['attribute'], static::$deprecated_attributes, true ) ) {
				continue;
			}
		}
		return $transformed_attributes;
	}

	/**
	 * Initializes the process of creating a df_token authentication.
	 *
	 * This function creates an instance of the `Reset_Credentials_Index` class and calls
	 * its `reset_token_auth` method to reset or create the token authentication.
	 *
	 * @return void
	 */
	public static function create_token_auth() {
		$reset_credentials_context = new Reset_Credentials_Index();
		$reset_credentials_context->reset_token_auth();
	}

	/**
	 * Converts the former product attribute name from pa_<attribute_name>
	 * format to wc_<attribute_id> format.
	 * Example:
	 * pa_color => wc_4
	 *
	 * @param string $attribute_name The former attribute name.
	 * @return string The transformed attribute name.
	 */
	private static function transform_product_attribute( $attribute_name ) {
		$wc_attributes = wc_get_attribute_taxonomies();
		foreach ( $wc_attributes as $wc_attribute ) {
			if ( 'pa_' . $wc_attribute->attribute_name === $attribute_name ) {
				return 'wc_' . $wc_attribute->attribute_id;
			}
		}
		return $attribute_name;
	}
}
