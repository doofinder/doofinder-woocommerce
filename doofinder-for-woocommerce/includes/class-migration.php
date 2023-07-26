<?php

namespace Doofinder\WP;

use Doofinder\WP\Api\Store_Api;
use Doofinder\WP\Settings;
use Doofinder\WP\Log;

class Migration
{

    /**
     * Instance of a class used to log to a file.
     *
     * @var Log
     */
    private static $log;

    /**
     * Try migrating old settings
     */

    public static function migrate()
    {
        self::$log = new Log('migration.log');
        self::$log->log('Migrate - Start');

        self::initialize_migration();
        $migration_result = self::do_woocommerce_migration();

        //check if app credentials are set
        if (!Store_Api::has_application_credentials() && Settings::is_configuration_complete()) {
            $store_api = new Store_Api();
            $store_api->normalize_store_and_indices();
        }
        self::finish_migration($migration_result);
    }

    /**
     * Initialize the migration
     *
     * @return void
     */
    private static function initialize_migration()
    {
        delete_option(Setup_Wizard::$wizard_migration_notice_name);
        delete_option(Setup_Wizard::$wizard_migration_option);
    }

    /**
     * Adds the migration notice
     *
     * @return void
     */
    public static function add_notices()
    {
        add_action('admin_notices', function () {
            $migration_completed = "completed" === get_option(Setup_Wizard::$wizard_migration_option);
            $show_migration_completed_notice = (bool) get_option(Setup_Wizard::$wizard_migration_notice_name);
            if ($migration_completed && $show_migration_completed_notice) {
                //Disable the migration notice after showing it once
                update_option(Setup_Wizard::$wizard_migration_notice_name, 0);
                echo Setup_Wizard::get_setup_wizard_migration_notice_html();
            }
        });
    }

    /**
     * This function migrates the options from the former woocommerce plugin to
     * the current plugin options.
     *
     * @return void
     */
    private static function do_woocommerce_migration()
    {
        if (get_option('woocommerce_doofinder_internal_search_api_key', FALSE)) {
            //There was a woocommerce plugin installed, try to import data to the new plugin
            $generic_options = [
                'woocommerce_doofinder_internal_search_api_key' => 'doofinder_for_wp_api_key',
                'woocommerce_doofinder_internal_search_api_host' => 'doofinder_for_wp_api_host',
                'doofinder_for_wc_sector' => 'doofinder_sector'
            ];
            $multilang_options = [
                'woocommerce_doofinder_internal_search_hashid' => 'doofinder_for_wp_search_engine_hash',
                'woocommerce_doofinder_layer_enabled' => 'doofinder_for_wp_enable_js_layer',
                'woocommerce_doofinder_layer_code' => 'doofinder_for_wp_js_layer'
            ];

            //Migrate the generic options
            foreach ($generic_options as $wc_option_name => $wp_option_name) {
                self::migrate_option($wc_option_name, $wp_option_name);
            }

            //Migrate the Multilang options
            $wizard = Setup_Wizard::instance();
            $base_language = $wizard->language->get_base_language();
            $langs = $wizard->language->get_languages();
            //define empty language for main language options
            $langs[''] = '';

            foreach ($langs as $lang_key => $value) {
                $lang = ($lang_key === $base_language) ? '' : $lang_key;
                foreach ($multilang_options as $wc_key => $wp_key) {
                    $wc_option_name = empty($lang) ? $wc_key : $wc_key . '_' . $lang;
                    $wp_option_name = empty($lang) ? $wp_key : $wp_key . '_' . $lang;
                    self::migrate_option($wc_option_name, $wp_option_name);
                }
            }

            self::maybe_fix_api_host();

            return true;
        }
        return false;
    }

    private static function maybe_fix_api_host()
    {
        $api_host_option_name = 'doofinder_for_wp_api_host';
        $api_host = get_option($api_host_option_name);

        // Check if api host contains prefix, then isolate prefix
        if (preg_match('@-@', $api_host)) {
            $arr = explode('-', $api_host);
        }

        $api_host_prefix = $arr[0] ?? null;
        $api_host_path = $arr[1] ?? null;

        if (!preg_match("#^((https?://))#i", $api_host_prefix)) {
            $api_host_prefix = "https://" . $api_host_prefix;
        }

        if ($api_host_path != "admin.doofinder.com") {
            $new_api_host = $api_host_prefix . "-admin.doofinder.com";
            update_option($api_host_option_name, $new_api_host);
        }
    }

    /**
     * This function migrates the value of the first option into the second
     * if it is empty.
     *
     * @param string $wc_option_name The woocommerce option that we are going to
     * migrate.
     *
     * @param string $wp_option_name The Wordpress option that we should create
     * if it is empty.
     *
     * @return void
     */
    private static function migrate_option($wc_option_name, $wp_option_name)
    {
        $current_option_value = get_option($wp_option_name);
        if (!empty($current_option_value)) {
            self::$log->log("No need to migrate the wc option from '" . $wc_option_name . "' to '" . $wp_option_name . "', the value is already set to: \n" . print_r($current_option_value, true));
        } else {
            $wc_option_value = get_option($wc_option_name);
            self::$log->log("Migrate option from '" . $wc_option_name . "' to '" . $wp_option_name . "' with value: \n" . print_r($wc_option_value, true));
            update_option($wp_option_name, $wc_option_value);
        }
    }

    /**
     * This function executes any needed processes after finalizing migrations.
     * For example: update options and show migration notice.
     *
     * @return void
     */
    private static function finish_migration($migration_result)
    {
        // Migration completed
        self::$log->log('Migrate - Migration Completed');
        update_option(Setup_Wizard::$wizard_migration_option, 'completed');

        if ($migration_result) {
            // Add notice about successfull migration
            self::$log->log('Migrate - Add custom notice');
            update_option(Setup_Wizard::$wizard_migration_notice_name, 1);
            self::add_notices();
        }
    }
}
