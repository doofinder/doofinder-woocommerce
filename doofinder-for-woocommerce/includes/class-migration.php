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

    private static $dimension_attributes = [
        'width',
        'height',
        'length'
    ];

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
     * Function to migrate only custom attributes specifically when updating to 
     * the plugin version 2.0.13
     *
     * @return void
     */
    public static function migrate_custom_attributes()
    {
        self::$log = new Log('migration.log');
        self::$log->log('Migrate Custom Attributes - Start');

        self::initialize_migration();
        self::migrate_option('woocommerce_doofinder_feed_attributes_additional_attributes', 'doofinder_for_wp_custom_attributes');
        self::finish_migration(TRUE);
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
        add_filter("doofinder-for-wp-migration-transform-woocommerce_doofinder_feed_attributes_additional_attributes", [self::class, 'transform_additional_attributes'], 10, 1);
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
                'woocommerce_doofinder_feed_attributes_additional_attributes' => 'doofinder_for_wp_custom_attributes',
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
            $wc_option_value = apply_filters("doofinder-for-wp-migration-transform-$wc_option_name", $wc_option_value);

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

    /**
     * Transforms the former custom_attributes array to the new format
     *
     * @param array $additional_attributes
     * @return array Transformed custom attributes array
     */
    public static function transform_additional_attributes($additional_attributes)
    {
        $transformed_attributes = [];
        foreach ($additional_attributes as $key => $value) {
            $attribute = [];
            foreach (explode('&', $value) as $attr_value) {
                $attr = explode('=', $attr_value);
                $attribute[$attr[0]] = $attr[1];
                $attribute['type'] =  'base_attribute';
            }

            if (strpos($attribute['attribute'], 'pa_') === 0) {
                //Product attribute, find the wc_attribute_id
                $attribute['attribute'] = static::transform_product_attribute($attribute['attribute']);
                $attribute['type'] = 'wc_attribute';
            } else if ($attribute['attribute'] === 'custom') {
                //Custom Meta attribute, set the attribute from value
                if (!isset($attribute['value'])) {
                    //no value defined, ignore attribute
                    continue;
                }
                $attribute['type'] = 'metafield';
                $attribute['attribute'] = $attribute['value'];
                unset($attribute['value']);
            }

            //Add the dimensions: for dimension attributes
            if (in_array($attribute['attribute'], static::$dimension_attributes)) {
                $attribute['attribute'] = 'dimensions:' . $attribute['attribute'];
            }

            $transformed_attributes[$key] = $attribute;
        }
        return $transformed_attributes;
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
    private static function transform_product_attribute($attribute_name)
    {
        $wc_attributes = wc_get_attribute_taxonomies();
        foreach ($wc_attributes as $wc_attribute) {
            if ($attribute_name === 'pa_' . $wc_attribute->attribute_name) {
                return 'wc_' . $wc_attribute->attribute_id;
            }
        }
        return $attribute_name;
    }
}
