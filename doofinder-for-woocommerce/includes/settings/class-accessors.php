<?php

namespace Doofinder\WC\Settings;

use Doofinder\WC;

use Doofinder\WC\Multilanguage\Multilanguage;
use Doofinder\WC\Helpers\Helpers;

defined('ABSPATH') or die();

/**
 * Contains all methods used to retrieve or save option values.
 */
trait Accessors
{

	/**
	 * Retrieve the URL to the Doofinder settings page.
	 *
	 * @return string
	 */
	public static function get_url($param = null)
	{
		return admin_url('admin.php?page=wc-settings&tab=doofinder' . ($param ? '&' . $param : ''));
	}

	/**
	 * Retrieve the API Key.
	 *
	 * Just an alias for "get_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @return string
	 */
	public static function get_api_key()
	{
		return get_option('woocommerce_doofinder_internal_search_api_key');
	}

	/**
	 * Set the value of the API Key.
	 *
	 * Just an alias for "update_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @param string $api_key
	 */
	public static function set_api_key($api_key)
	{
		update_option('woocommerce_doofinder_internal_search_api_key', $api_key);
	}

	/**
	 * Retrieve the API Host.
	 *
	 * Just an alias for "get_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @return string
	 */
	public static function get_api_host()
	{
		if (defined('WC_DF_API_HOST')) {
			return WC_DF_API_HOST;
		}
		return get_option('woocommerce_doofinder_internal_search_api_host');
	}

	/**
	 * Set the value of the API Host.
	 *
	 * Just an alias for "update_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @param string $api_key
	 */
	public static function set_api_host($api_host)
	{
		update_option('woocommerce_doofinder_internal_search_api_host', $api_host);
	}

	/**
	 * Set the value of the API Admin Endpooint.
	 *
	 * Just an alias for "update_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @param string $admin_endpoint
	 */
	public static function set_admin_endpoint($admin_endpoint)
	{
		update_option('woocommerce_doofinder_api_admin_endpoint', $admin_endpoint);
	}

	/**
	 * Retrieve the API Admin Endpoint.
	 *
	 * Just an alias for "get_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @return string
	 */
	public static function get_admin_endpoint()
	{
		return get_option('woocommerce_doofinder_api_admin_endpoint');
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
	public static function get_search_engine_hash($language = '')
	{
		return get_option(self::option_name_for_language(
			'woocommerce_doofinder_internal_search_hashid',
			$language
		));
	}

	/**
	 * Set the value of search engine hash.
	 *
	 * Just an alias for "update_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @param string $hash
	 * @param string $language Language code to set the hash for.
	 */
	public static function set_search_engine_hash($hash, $language = '')
	{
		update_option(self::option_name_for_language(
			'woocommerce_doofinder_internal_search_hashid',
			$language
		), $hash);
	}

	/**
	 * Retrieve the search server of the chosen Search engine.
	 *
	 * Just an alias for "get_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @param string $language Language code to retrieve the hash for.
	 *
	 * @return string
	 */
	public static function get_search_engine_server($language = '')
	{
		return get_option(self::option_name_for_language(
			'woocommerce_doofinder_internal_search_search_server',
			$language
		));
	}

	/**
	 * Set the value of search engine server.
	 *
	 * Just an alias for "update_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 * @param string $server
	 * @param string $language Language code to set the hash for.
	 */
	public static function set_search_engine_server($server, $language = '')
	{
		update_option(self::option_name_for_language(
			'woocommerce_doofinder_internal_search_search_server',
			$language
		), $server);
	}

	/**
	 * Returns `true` if debug mode is disabled, `false` otherwise.
	 *
	 * Just an alias for "get_option" to avoid repeating the string
	 * (option name) in multiple files.
	 *
	 *
	 * @return string
	 */
	public static function get_enable_debug_mode()
	{

		$debug_mode = get_option('woocommerce_doofinder_indexing_enable_debug_mode');

		return $debug_mode === 'yes' ? true : false;
	}

	/**
	 * Retrieve all the post types that the user chose to index.
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 *
	 * @return string[]
	 */
	public static function get_post_types_to_index($language = '')
	{
		$post_types = get_option(self::option_name_for_language(
			'doofinder_for_wc_post_types_to_index',
			$language
		));
		if (!$post_types) {
			return array();
		}

		return array_keys($post_types);
	}

	/**
	 * Set the value of post types to index.
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param array [string => 'on'] $post_types
	 * @param string $language Language code.
	 */
	public static function set_post_types_to_index($post_types, $language = '')
	{
		update_option(self::option_name_for_language(
			'doofinder_for_wc_post_types_to_index',
			$language
		), $post_types);
	}

	/**
	 * Retrieve the information whether or not we should index categories.
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 *
	 * @return bool
	 */
	public static function get_index_categories($language = '')
	{
		return (bool) get_option(self::option_name_for_language(
			'doofinder_for_wc_index_categories',
			$language
		));
	}

	/**
	 * Retrieve the information whether or not we should index tags.
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 *
	 * @return bool
	 */
	public static function get_index_tags($language = '')
	{
		return (bool) get_option(self::option_name_for_language(
			'doofinder_for_wc_index_tags',
			$language
		));
	}

	/**
	 * Determine if the configuration is completed.
	 *
	 * Complete configuration means that API Key and Search Engine HashID fields are filled.
	 *
	 * @return bool
	 */
	public static function is_configuration_complete()
	{
		return (bool) (self::get_api_key() && self::get_search_engine_hash());
	}

	/**
	 * Determine if the configuration of data for API connection is completed.
	 *
	 * Complete configuration means that API Key  and API Host and Admin Endpoint fields are filled.
	 *
	 * @return bool
	 */
	public static function is_api_configuration_complete()
	{
		return (bool) (self::get_api_key() && self::get_api_host() && self::get_admin_endpoint());
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
	public static function is_js_layer_enabled($language = '')
	{
		$option_name = self::option_name_for_language(
			'woocommerce_doofinder_layer_enabled',
			$language
		);
		//var_dump($option_name);

		$option =  get_option($option_name);
		//var_dump($option);

		return $option === 'yes' ? true : false;
	}

	/**
	 * Determine if we should grab JS layer directly from Doofinder.
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 *
	 * @return bool
	 */
	public static function is_js_layer_from_doofinder_enabled($language = '')
	{
		return (bool) get_option(self::option_name_for_language(
			'doofinder_for_wc_load_js_layer_from_doofinder',
			$language
		));
	}

	/**
	 * Enable JS Layer.
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 */
	public static function enable_js_layer($language = '')
	{
		update_option(self::option_name_for_language(
			'woocommerce_doofinder_layer_enabled',
			$language
		), 'yes');
	}

	/**
	 * Disable JS Layer.
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 */
	public static function disable_js_layer($language = '')
	{
		update_option(self::option_name_for_language(
			'woocommerce_doofinder_layer_enabled',
			$language
		), 'no');
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
	public static function get_js_layer($language = '')
	{
		return wp_unslash(get_option(self::option_name_for_language(
			'woocommerce_doofinder_layer_code',
			$language
		)));
	}

	/**
	 * Update the value of the JS Layer script.
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $value
	 * @param string $language Language code.
	 */
	public static function set_js_layer($value, $language = '')
	{
		update_option(self::option_name_for_language('woocommerce_doofinder_layer_code', $language), $value);
	}

	/**
	 * Determine if the Internal Search is enabled.
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 *
	 * @return bool
	 */
	public static function is_internal_search_enabled($language = '')
	{

		$option_name = self::option_name_for_language(
			'woocommerce_doofinder_internal_search_enable',
			$language
		);
		//var_dump($option_name);

		$option = get_option($option_name);
		//var_dump($option);

		return  $option === 'yes' ? true : false;
	}

	/**
	 * Enable Internal Search.
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 */
	public static function enable_internal_search($language = '')
	{
		update_option(self::option_name_for_language(
			'woocommerce_doofinder_internal_search_enable',
			$language
		), 'yes');
	}

	/**
	 * Disable Internal Search.
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 */
	public static function disable_internal_search($language = '')
	{
		update_option(self::option_name_for_language(
			'woocommerce_doofinder_internal_search_enable',
			$language
		), 'no');
	}


	/**
	 * Determine if the update on save is enabled.
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @return bool
	 */
	public static function is_update_on_save_enabled()
	{
		$option = get_option('woocommerce_doofinder_indexing_update_on_save', 'yes');
		return  $option === 'yes' ? true : false;
	}

	/**
	 * Enable update on save.
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 */
	public static function enable_update_on_save()
	{
		update_option('woocommerce_doofinder_indexing_update_on_save', 'yes');
	}

	/**
	 * Disable update on save.
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 */
	public static function disable_update_on_save()
	{
		update_option('woocommerce_doofinder_indexing_update_on_save', 'no');
	}

	/**
	 * Retrieve additional attributes to be added to the index
	 * by the user.
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 *
	 * @return array
	 */
	public static function get_additional_attributes($language = '')
	{
		return get_option(self::option_name_for_language(
			'woocommerce_doofinder_feed_attributes_additional_attributes',
			$language
		));
	}

	/**
	 * Retrieve last modified date for index (in Doofinder)
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 */
	public static function get_last_modified_index($language = '')
	{
		return get_option(self::option_name_for_language(
			'woocommerce_doofinder_last_modified_index',
			$language
		));
	}

	/**
	 * Set last modified date for index (in Doofinder)
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 * @param int $update_time Timestamp of the update time
	 */
	public static function set_last_modified_index($language = '', $update_time = null)
	{

		$update_time = $update_time ?: time();

		update_option(self::option_name_for_language(
			'woocommerce_doofinder_last_modified_index',
			$language
		), $update_time);
	}


	/**
	 * Retrieve last modified date for db data 
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 */
	public static function get_last_modified_db($language = '')
	{
		return get_option(self::option_name_for_language(
			'woocommerce_doofinder_last_modified_db',
			$language
		));
	}

	/**
	 * Set last modified date for db data
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 * @param int $update_time Timestamp of the update time
	 */
	public static function set_last_modified_db($language = '', $update_time = null)
	{

		$update_time = $update_time ?: time();

		update_option(self::option_name_for_language(
			'woocommerce_doofinder_last_modified_db',
			$language
		), $update_time);
	}


	/**
	 * Retrieve the Business Sector
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $language Language code.
	 *
	 * @return bool
	 */
	public static function get_sector($language = '')
	{
		return get_option(self::option_name_for_language(
			'doofinder_for_wc_sector',
			$language
		));
	}


	/**
	 * Update the value of the Business Sector
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $value
	 * @param string $language Language code.
	 */
	public static function set_sector($value, $language = '')
	{
		update_option(self::option_name_for_language('doofinder_for_wc_sector', $language), $value);
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
	private static function option_name_for_language($option_name, $language = '')
	{
		if ($language) {
			$option_name .= "_{$language}";
		} else {
			$language    = Multilanguage::instance();
			$option_name = $language->get_option_name($option_name);
		}

		return $option_name;
	}


	/**
	 * Retrieve the Plugin Version
	 *
	 * Just an alias for "get_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 *
	 * @return string
	 */
	public static function get_plugin_version()
	{
		return get_option('doofinder_for_wc_plugin_version', '1.5.29');
	}


	/**
	 * Update the value of the Plugin Version
	 *
	 * Just an alias for "update_option", because ideally we don't
	 * want to replace the option name in multiple files.
	 *
	 * @param string $value
	 */
	public static function set_plugin_version($value)
	{
		update_option("doofinder_for_wc_plugin_version", $value);
	}
}
