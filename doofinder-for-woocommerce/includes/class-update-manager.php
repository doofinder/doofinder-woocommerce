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
        $plugin_normalized_version = self::normalize_plugin_version($plugin_version);

        for ($version = $current_normalized_version + 1; $version <= $plugin_normalized_version; $version++) {
            $version_number = str_pad($version, 6, '0', STR_PAD_LEFT);
            $update_function = 'update_' . $version_number;

            self::log("check if the update  $update_function exists.");
            if (method_exists(Update_Manager::class, $update_function)) {
                self::log("Executing $update_function update...");
                $result = NULL;
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
        $title = sprintf(__('An error occurred while updating the Doofinder Database to the %s version.', 'doofinder_for_wp'), $version);
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

    /**
     * Notice html content to show on error
     */
    private static function get_update_error_notice($version, $message = "")
    {

        $message_intro = sprintf(__('An error occurred while updating the Doofinder Database to the %s version.', 'woocommerce-doofinder'), $version);
        $message_help = sprintf(__('For more details please contact us at our %s support center%s.', 'woocommerce-doofinder'), '<a target="_blank" href="https://support.doofinder.com/pages/contact-us.html">', '</a>');
        ob_start();
?>
        <div id="message" class="woocommerce-message doofinder-notice-update-manager">
            <figure class="logo" style="width:5rem;height:auto;float:left;margin:.5em 0;margin-right:0.75rem;">
                <img src="<?php echo Doofinder_For_WordPress::plugin_url() . '/assets/svg/imagotipo1.svg'; ?>" />
            </figure>
            <p class="submit">
                <strong>
                    <?php echo $message_intro; ?>
                </strong>
                <?php echo $message_help; ?>

            </p>
            <?php if (!empty($message)) : ?>
                <p class="error-details">
                    <?php echo $message ?>
                </p>
            <?php endif; ?>

        </div>
<?php
        return ob_get_clean();
    }

    private static function log($message)
    {
        if (empty(static::$logger)) {
            static::$logger = new Log('updates.log');
        }
    }

    /*
	Place all updates here
	*/

    /**
     * Update: 2.0.0
     * Normalize store and indices and create application credentials for
     * accessing the rest API.
     */
    public static function update_020000()
    {
        //Update api host to point to admin.doofinder.com instead of api.doofinder.com
        $multilanguage = Multilanguage::instance();
        $languages = $multilanguage->get_languages();
        if (empty($languages)) {
            $languages = ['' => []];
        }

        foreach ($languages as $lang_key => $value) {
            $lang_key = ($lang_key != $multilanguage->get_base_language()) ? $lang_key : '';
            $api_host = Settings::get_api_host($lang_key);
            if (!strpos($api_host, "admin.doofinder.com")) {
                $api_host_parts = explode("-", $api_host);
                $new_api_host = $api_host_parts[0] . '-' . 'admin.doofinder.com';
                Settings::set_api_host($new_api_host);
            }
        }

        /*
        Detect if we are updating from a former version of Doofinder to
        normalize store and indices
        */
        if (Settings::is_configuration_complete() && !Store_Api::has_application_credentials()) {
            $store_api = new Store_Api();
            $store_api->normalize_store_and_indices();
        }
        return true;
    }

    /**
     * Update: 2.0.3
     * Remove the indexing failed notice to solve any existing problem
     */
    public static function update_020003()
    {
        Admin_Notices::remove_notice("indexing-status-failed");
        return true;
    }
}
