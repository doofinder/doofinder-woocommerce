<?php

namespace Doofinder\WP;

use Doofinder\WP\Api\Store_Api;
use Doofinder\WP\Multilanguage\Multilanguage;
use Doofinder\WP\Settings;

defined('ABSPATH') or die;

class Update_Manager
{
    private static $logger = NULL;

    /**
     * Checks and performs any pending DB updates.
     *
     * @since 1.0
     */
    public static function check_updates($plugin_version)
    {
        $db_version = Settings::get_plugin_version();
        self::log("Check updates from $db_version to $plugin_version");

        if (empty($plugin_version)) {

            self::log("invalid plugin version: $plugin_version");

            return false;
        }
        $current_normalized_version = self::normalize_plugin_version(Settings::get_plugin_version());
        $plugin_normalized_version = (int)self::normalize_plugin_version($plugin_version);

        $result = NULL;
        for ($version = $current_normalized_version + 1; $version <= $plugin_normalized_version; $version++) {
            $version_number = str_pad($version, 6, '0', STR_PAD_LEFT);
            $update_function = 'update_' . $version_number;

            self::log("check if the update  $update_function exists.");
            if (method_exists(Update_Manager::class, $update_function)) {
                self::log("Executing $update_function update...");
                try {
                    $result = call_user_func(array(Update_Manager::class, $update_function));
                } catch (\Exception $ex) {
                    self::update_failed($version_number, $ex->getMessage());
                    break;
                }

                if ($result) {
                    //Remove the current update notice in case it exists
                    self::remove_admin_notice($version_number);
                } else {
                    self::update_failed($version_number);
                    break;
                }

                self::log("The update $update_function Succeeded");
                $formatted_version = self::format_normalized_plugin_version($version_number);

                //Update the database version to the newer one
                Settings::set_plugin_version($formatted_version);
            }
        }

        if ($result) {
            //All updates executed successfully, update the plugin version to the latest one
            Settings::set_plugin_version($plugin_version);
        }

        self::log("Updates ended, plugin db version is: " . Settings::get_plugin_version());
    }

    /**
     * Formats the plugin version to a normalized version.
     * Example: 1.5.13 => 010513
     */
    private static function normalize_plugin_version($version)
    {
        $normalized = "";
        $version = explode(".", $version);
        foreach ($version as $key => $version_part) {
            $normalized .= str_pad($version_part, 2, '0', STR_PAD_LEFT);
        }
        return $normalized;
    }

    /**
     * Formats a normalized version back to the x.y.z format version.
     * Example: 010513 => 1.5.13
     */
    private static function format_normalized_plugin_version($normalized)
    {
        $version = str_split($normalized, 2);
        $version = array_map(function ($vnum) {
            return (int)$vnum;
        }, $version);

        return implode(".", $version);
    }

    /**
     * Logs the error and adds an admin notice
     */
    private static function update_failed($version, $message = "")
    {
        $formatted_version = self::format_normalized_plugin_version($version);
        self::log("ERROR: The update $formatted_version failed with message: " .  $message);
        self::add_admin_notice($version, $message);
    }

    /**
     * Adds an admin notice using WooCommerce.
     */
    private static function add_admin_notice($version, $message = "")
    {
        $formatted_version = self::format_normalized_plugin_version($version);
        $title = sprintf(__('An error occurred while updating the Doofinder Database to the %s version.', 'doofinder_for_wp'), $formatted_version);
        $message .= "<p>" . sprintf(__('For more details please contact us at our %s support center%s.', 'doofinder_for_wp'), '<a target="_blank" href="https://support.doofinder.com/pages/contact-us.html">', '</a>') . '</p>';
        Admin_Notices::add_notice('update-' . $version, $title, $message, 'error', null, '', true);
    }

    /**
     * Removes an admin notice using WooCommerce.
     */
    private static function remove_admin_notice($version)
    {
        Admin_Notices::remove_notice('update-' . $version);
    }

    private static function log($message)
    {
        if (empty(static::$logger)) {
            static::$logger = new Log('updates.log');
        }
        static::$logger->log($message);
    }

    /*
	Place all updates here
	*/

    /**
     * Update: 2.0.0
     * Normalize store and indices and create application credentials for
     * accessing the rest API.
     * 
     * @return bool
     */
    public static function update_020000()
    {
        if (Settings::is_configuration_complete()) {
            Migration::migrate();
        }

        return true;
    }

    /**
     * Update: 2.0.3
     * Remove the indexing failed notice to solve any existing problem
     * 
     * @return bool
     */
    public static function update_020003()
    {
        Admin_Notices::remove_notice("indexing-status-failed");
        return true;
    }

    /**
     * Update: 2.0.13
     * Update the woocommerce product attributes
     * 
     * @return bool
     */
    public static function update_020013()
    {
        if (get_option("woocommerce_doofinder_feed_attributes_additional_attributes")) {
            Migration::migrate_custom_attributes();
        }
        return true;
    }

    /**
     * Update: 2.1.0
     * Update the woocommerce product attributes
     */
    public static function update_020100()
    {
        if (Settings::is_configuration_complete()) {
            Migration::create_token_auth();
        }
        return true;
    }

    /**
     * Update: 2.1.12
     * Remove stock_status from custom_attributes
     * 
     * @return bool
     */
    public static function update_020112()
    {
        //Remove the stock_status custom_attribute if existing
        $custom_attributes = Settings::get_custom_attributes();
        foreach ($custom_attributes as $key => $attr) {
            if ($attr['attribute'] === "stock_status") {
                unset($custom_attributes[$key]);
                update_option(Settings::$custom_attributes_option, $custom_attributes);
                break;
            }
        }
        //Delete the custom_attributes transient
        delete_transient("df_product_rest_attributes");
        return true;
    }

    /**
     * Update: 2.2.6
     * Create or update the Plugins Host.
     * 
     * @return bool
     */
    public static function update_020206()
    {
        //Set Region
        if (Settings::is_configuration_complete()) {
            $api_host = get_option('doofinder_for_wp_api_host');
            $re = '/:\/\/(?<region>[a-z]{2}[0-9])-.*/m';
            preg_match_all($re, $api_host, $matches, PREG_SET_ORDER, 0);

            if (!empty($matches) && array_key_exists('region', $matches[0])) {
                $region = $matches[0]['region'];
                Settings::set_region($region);

                //Delete api_host and plugins_host as they are not needed any more
                $del_keys = ['doofinder_for_wp_api_host', 'doofinder_for_wp_dooplugins_host'];
                foreach ($del_keys as $del_key) {
                    delete_option($del_key);
                }
            }
        }
        return true;
    }
}
