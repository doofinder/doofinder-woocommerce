<?php
/**
 * DooFinder Accessors methods.
 *
 * @package Doofinder\WP\Settings
 */

namespace Doofinder\WP\Settings;

use Doofinder\WP\Index_Status_Handler;
use Doofinder\WP\Multilanguage\Multilanguage;
use Doofinder\WP\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Contains all methods used to retrieve or save option values.
 */
trait Accessors {


	/**
	 * Retrieve the URL to the Doofinder settings page.
	 *
	 * @return string
	 */
	public static function get_url() {
		return menu_page_url( self::$top_level_menu, false );
	}

	/**
	 * Retrieve the API Key.
	 *
	 * Just an alias for "get_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @return string
	 */
	public static function get_api_key() {
		return get_option( 'doofinder_for_wp_api_key' );
	}

	/**
	 * Set the value of the API Key.
	 *
	 * Just an alias for "update_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @param string $api_key DooFinder API Key.
	 */
	public static function set_api_key( $api_key ) {
		update_option( 'doofinder_for_wp_api_key', $api_key );
	}

	/**
	 * Sets the region.
	 *
	 * @param string $region The region identifier (eu1, us1 or ap1).
	 *
	 * @return bool The update_option result. True if successfully updated false in case of failure.
	 */
	public static function set_region( $region ) {
		return update_option( 'doofinder_for_wp_region', $region );
	}

	/**
	 * Retrieve the region.
	 *
	 * @return string The Region key (eu1, us1 or ap1).
	 */
	public static function get_region() {
		return get_option( 'doofinder_for_wp_region' );
	}

	/**
	 * Retrieve the API Host.
	 *
	 * Just an alias for "get_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @return string
	 */
	public static function get_dooplugins_host() {
		// If we are in local environment, return the DF_PLUGINS_HOST set in wp-config.
		if ( wp_get_environment_type() === 'local' && defined( 'DF_PLUGINS_HOST' ) ) {
			return DF_PLUGINS_HOST;
		}

		$region = self::get_region();
		$region = empty( $region ) ? '' : "$region-";

		$plugins_host = sprintf( 'https://%splugins.doofinder.com', $region );
		return self::normalize_host( $plugins_host );
	}

	/**
	 * Retrieve the API Host.
	 *
	 * Just an alias for "get_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @return string
	 */
	public static function get_api_host() {
		// If we are in local environment, return the DF_API_HOST set in wp-config.
		if ( wp_get_environment_type() === 'local' && defined( 'DF_API_HOST' ) ) {
			return DF_API_HOST;
		}
		$region = self::get_region();
		$region = empty( $region ) ? '' : "$region-";

		$admin_host = sprintf( 'https://%sadmin.doofinder.com', $region );

		return self::normalize_host( $admin_host );
	}

	/**
	 * Set the value of the API Host.
	 *
	 * Just an alias for "update_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @param string $api_host Doomanager host.
	 */
	public static function set_api_host( $api_host ) {
		update_option( 'doofinder_for_wp_api_host', $api_host );
	}

	/**
	 * Set the value of the Dooplugins Host.
	 *
	 * Just an alias for "update_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @param string $dp_host Dooplugins host.
	 */
	public static function set_dooplugins_host( $dp_host ) {
		update_option( 'doofinder_for_wp_dooplugins_host', $dp_host );
	}

	/**
	 * Retrieve the hash of the chosen Search engine.
	 *
	 * Just an alias for "get_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @param string $language Language code to retrieve the hash for.
	 *
	 * @return string
	 */
	public static function get_search_engine_hash( $language = '' ) {
		return get_option(
			self::option_name_for_language(
				'doofinder_for_wp_search_engine_hash',
				$language
			)
		);
	}

	/**
	 * Set the value of search engine hash.
	 *
	 * Just an alias for "update_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @param string $hash Search Engine hash.
	 * @param string $language Language code to set the hash for.
	 */
	public static function set_search_engine_hash( $hash, $language = '' ) {
		update_option(
			self::option_name_for_language(
				'doofinder_for_wp_search_engine_hash',
				$language
			),
			$hash
		);
	}

	/**
	 * Retrieve the chosen update on save option.
	 *
	 * Just an alias for "get_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @param string $language Language code to retrieve the hash for.
	 *
	 * @return string
	 */
	public static function get_update_on_save( $language = '' ) {
		return get_option(
			self::option_name_for_language(
				'doofinder_for_wp_update_on_save',
				$language
			),
			'wp_doofinder_disabled'
		);
	}

	/**
	 * Determine if the configuration is completed.
	 *
	 * Complete configuration means that API Key and Search Engine HashID fields are filled.
	 *
	 * @return bool
	 */
	public static function is_configuration_complete() {
		return (bool) ( self::get_api_key() && self::get_search_engine_hash() );
	}

	/**
	 * Determine if the JS Layer is enabled in the settings.
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 *
	 * @return bool
	 */
	public static function is_js_layer_enabled( $language = '' ) {
		return (bool) get_option(
			self::option_name_for_language(
				'doofinder_for_wp_enable_js_layer',
				$language
			)
		);
	}

	/**
	 * Enable JS Layer.
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 */
	public static function enable_js_layer( $language = '' ) {
		update_option(
			self::option_name_for_language(
				'doofinder_for_wp_enable_js_layer',
				$language
			),
			1
		);
	}

	/**
	 * Disable JS Layer.
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 */
	public static function disable_js_layer( $language = '' ) {
		update_option(
			self::option_name_for_language(
				'doofinder_for_wp_enable_js_layer',
				$language
			),
			0
		);
	}

	/**
	 * Retrieve the code of the JS Layer.
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 *
	 * @return string
	 */
	public static function get_js_layer( $language = '' ) {
		$base_script = self::get_script_backwards_compatibility( $language );
		return self::maybe_prepend_extra_config( $base_script, $language );
	}

	/**
	 * The legacy script required one specific script per language, but
	 * with the single script it will be inserted inly for the default language.
	 *
	 * @param string $language Language code.
	 *
	 * @return string
	 */
	private static function get_script_backwards_compatibility( $language ) {
		$is_doofinder_script_migrated = get_option( 'doofinder_script_migrated', '0' );
		// Unique script will be unified for every language.
		$language_to_use = '';

		if ( ! $is_doofinder_script_migrated ) {
			$language_to_use = $language;
		}

		$base_script = wp_unslash(
			get_option(
				self::option_name_for_language(
					'doofinder_for_wp_js_layer',
					$language_to_use
				)
			)
		);

		if ( ! $is_doofinder_script_migrated && ( empty( $base_script ) || str_contains( $base_script, 'config.doofinder.com' ) ) ) {
			update_option( 'doofinder_script_migrated', true );
			$base_script = wp_unslash( get_option( 'doofinder_for_wp_js_layer', '' ) );
			// Ensure that the script in the DB is the one-liner version.
			if ( preg_match( '/<script src="https:\/\/(?P<region>eu1|us1|ap1)-config\.doofinder\.com\/2\.x\/(?P<installation_id>[a-zA-Z0-9-]+)\.js" async><\/script>/', $base_script, $matches ) ) { // phpcs:ignore WordPress.WP.EnqueuedResources
				$base_script = sprintf( '<script src="https://%1$s-config.doofinder.com/2.x/%2$s.js" async></script>', $matches['region'], $matches['installation_id'] ); // phpcs:ignore WordPress.WP.EnqueuedResources
				Settings::set_js_layer( $base_script );
			}
		}

		return $base_script;
	}

	/**
	 * Update the value of the JS Layer script.
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $value Doofinder script string.
	 * @param string $language Language code.
	 */
	public static function set_js_layer( $value, $language = '' ) {
		update_option( self::option_name_for_language( 'doofinder_for_wp_js_layer', $language ), $value );
	}

	/**
	 * Generate the name of the option for a given language.
	 *
	 * Values of the fields for different languages are stored under different options.
	 * Language code is added to option name, except for default language, because we want
	 * settings for default language be exactly the same as if language plugin
	 * was disabled.
	 *
	 * @param string $option_name Base option name, before adding a suffix.
	 * @param string $language Language code.
	 *
	 * @return string Option name with optionally added suffix.
	 */
	private static function option_name_for_language( $option_name, $language = '' ) {
		if ( $language ) {
			return $option_name .= '_' . strtolower( $language );
		} elseif ( '' === $language ) {
			return $option_name;
		} else {
			$language = Multilanguage::instance();
			return $language->get_option_name( $option_name );
		}
	}

	/**
	 * Retrieve last modified date for index (in Doofinder)
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 */
	public static function get_last_modified_index( $language = '' ) {
		return get_option(
			self::option_name_for_language(
				'doofinder_for_wp_last_modified_index',
				$language
			)
		);
	}

	/**
	 * Set last modified date for index (in Doofinder)
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 * @param int    $update_time Timestamp of the update time.
	 */
	public static function set_last_modified_index( $language = '', $update_time = null ) {
		$update_time = $update_time ?? strtotime( gmdate( 'Y-m-d H:i:s' ) );

		update_option(
			self::option_name_for_language(
				'doofinder_for_wp_last_modified_index',
				$language
			),
			$update_time
		);
	}

	/**
	 * Retrieve the Business Sector
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @return string
	 */
	public static function get_sector() {
		return get_option( 'doofinder_sector' );
	}


	/**
	 * Update the value of the Business Sector
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $value DooFinder store business sector.
	 */
	public static function set_sector( $value ) {
		update_option( 'doofinder_sector', $value );
	}

	/**
	 * Determine if the configuration of data is completed.
	 *
	 * Complete configuration means that API Key  and API Host and Admin Endpoint fields are filled.
	 *
	 * @return bool
	 */
	public static function is_api_configuration_complete() {
		return (bool) ( self::get_api_key() && self::get_api_host() );
	}


	/**
	 * Retrieve the Indexing status.
	 *
	 * @param string $language Language code.
	 *
	 * @return bool
	 */
	public static function get_indexing_status( $language = '' ) {
		$status = get_option(
			self::option_name_for_language(
				'doofinder_for_wp_indexing_status',
				$language
			),
			'processing'
		);
		return $status;
	}


	/**
	 * Update the value of the Indexing status.
	 *
	 * @param string $value Indexing status.
	 * @param string $language Language code.
	 */
	public static function set_indexing_status( $value, $language = '' ) {
		// If the new status is processing, mark the indexing start.
		if ( 'processing' === $value ) {
			Index_Status_Handler::indexing_started( $language );
		}
		update_option( self::option_name_for_language( 'doofinder_for_wp_indexing_status', $language ), $value );
	}

	/**
	 * Retrieves the current version of the Doofinder plugin.
	 *
	 * This function fetches the plugin version from the WordPress options table.
	 * If the version is not set, it defaults to '1.9.9'.
	 *
	 * @return string The current plugin version.
	 */
	public static function get_plugin_version() {
		return get_option( 'doofinder_for_wp_plugin_version', '1.9.9' );
	}

	/**
	 * Marks the start of the plugin update process.
	 *
	 * This function sets an option in the WordPress database to indicate that a plugin update is currently running.
	 *
	 * @return void This function does not return any value.
	 */
	public static function plugin_update_started() {
		update_option( 'doofinder_for_wp_plugin_update_running', 1 );
	}

	/**
	 * Marks the end of the plugin update process.
	 *
	 * This function updates an option in the WordPress database to indicate that the plugin update has finished.
	 *
	 * @return void This function does not return any value.
	 */
	public static function plugin_update_ended() {
		update_option( 'doofinder_for_wp_plugin_update_running', 0 );
	}

	/**
	 * Checks whether a plugin update is currently running.
	 *
	 * This function retrieves the status of the plugin update process from the WordPress options table.
	 *
	 * @return bool Returns `true` if a plugin update is currently running, `false` otherwise.
	 */
	public static function is_plugin_update_running() {
		return (bool) get_option( 'doofinder_for_wp_plugin_update_running', 0 );
	}

	/**
	 * Sets the version of the Doofinder plugin.
	 *
	 * This function updates the plugin version in the WordPress options table to the specified version.
	 *
	 * @param string $version The version number to set for the plugin.
	 *
	 * @return bool Returns `true` if the option value was updated successfully, `false` otherwise.
	 */
	public static function set_plugin_version( $version ) {
		return update_option( 'doofinder_for_wp_plugin_version', $version );
	}

	/**
	 * Determine if the update on save is enabled.
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @return bool
	 */
	public static function is_update_on_save_enabled() {
		$option = get_option( 'doofinder_for_wp_update_on_save', 'wp_doofinder_disabled' );
		return 'wp_doofinder_disabled' !== $option;
	}

	/**
	 * Retrieves the configured image size for the Doofinder plugin.
	 *
	 * This function fetches the image size setting from the WordPress options table.
	 * If the setting is not found, it defaults to 'medium'.
	 *
	 * @return string The configured image size, or 'medium' if not set.
	 */
	public static function get_image_size() {
		return get_option( Settings::$image_size_option, 'medium' );
	}

	/**
	 * Retrieves the custom attributes configured for the Doofinder plugin for Products.
	 *
	 * This function fetches an array of custom attributes from the WordPress options table.
	 * If no custom attributes are configured, it defaults to an empty array.
	 *
	 * @return array The configured custom attributes, or an empty array if none are set.
	 */
	public static function get_custom_attributes() {
		return get_option( Settings::$custom_attributes_option, array() );
	}

	/**
	 * Retrieves the custom attributes configured for the Doofinder plugin for Posts.
	 *
	 * This function fetches an array of custom attributes from the WordPress options table.
	 * If no custom attributes are configured, it defaults to an empty array.
	 *
	 * @return array The configured custom attributes, or an empty array if none are set.
	 */
	public static function get_post_custom_attributes() {
		return get_option( Settings::$post_custom_attributes_option, array() );
	}

	/**
	 * Sets the state of the rewrite rules for the Doofinder plugin.
	 *
	 * This function updates the state of the rewrite rules in the WordPress options table.
	 *
	 * @param mixed $state The state to set for the rewrite rules.
	 *
	 * @return bool Returns `true` if the option value was updated successfully, `false` otherwise.
	 */
	public static function set_rewrite_rules_state( $state ) {
		return update_option( 'doofinder_for_wp_rewrite_rules_state', $state );
	}

	/**
	 * Retrieves the current state of the rewrite rules for the Doofinder plugin.
	 *
	 * This function fetches the state of the rewrite rules from the WordPress options table.
	 * If the state is not set, it defaults to `false`.
	 *
	 * @return mixed The current state of the rewrite rules, or `false` if not set.
	 */
	public static function get_rewrite_rules_state() {
		return get_option( 'doofinder_for_wp_rewrite_rules_state', false );
	}

	/**
	 * Normalizes the given host URL by ensuring it starts with 'https://'.
	 *
	 * This function checks if the provided host URL begins with 'https://'. If it does not,
	 * the function prepends 'https://' to the host URL.
	 *
	 * @param string $host The host URL to normalize.
	 *
	 * @return string The normalized host URL.
	 */
	private static function normalize_host( $host ) {
		if ( 0 !== strpos( $host, 'https://' ) ) {
			$host = 'https://' . $host;
		}

		return $host;
	}
	/**
	 * There are some customers that may still have the legacy Live Layer script or
	 * the single script with the languages variations inserted directly on the database.
	 * For these cases, we must skip this step of adding the extra doofinderApp config.
	 *
	 * @param string $base_script Base DooFinder script.
	 * @param string $language Language code.
	 *
	 * @return string The final script.
	 */
	private static function maybe_prepend_extra_config( $base_script, $language ) {
		if ( empty( $language ) ||
		str_contains( $base_script, 'dfLayerOptions' ) ||
		str_contains( $base_script, 'doofinderApp' ) ) {
			return $base_script;
		}

		$language = htmlspecialchars( $language, ENT_QUOTES, 'UTF-8' );
		ob_start();
		?>
		<script>
		(function(w, k) {w[k] = window[k] || function () { (window[k].q = window[k].q || []).push(arguments) }})(window, "doofinderApp");

		doofinderApp("config", "language", "<?php echo esc_attr( $language ); ?>");
		</script>
		<?php
		$extra_config = ob_get_clean();

		return $extra_config . $base_script;
	}
}
