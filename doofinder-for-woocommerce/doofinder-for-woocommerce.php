<?php

/**
 * Plugin Name: Doofinder WP & WooCommerce Search
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Version: 2.1.13
 * Requires at least: 5.6
 * Requires PHP: 7.0
 * Author: Doofinder
 * Description: Integrate Doofinder Search in your WordPress site or WooCommerce shop.
 *
 * @package WordPress
 */

namespace Doofinder\WP;

use Doofinder\WP\Multilanguage\Multilanguage;
use Doofinder\WP\Admin_Notices;

defined('ABSPATH') or die;

if (!class_exists('\Doofinder\WP\Doofinder_For_WordPress')) :

    /**
     * Main Plugin Class
     *
     * @class Doofinder_For_WordPress
     */
    class Doofinder_For_WordPress
    {

        /**
         * Plugin version.
         *
         * @var string
         */
        public static $version = '2.1.13';

        /**
         * The only instance of Doofinder_For_WordPress
         *
         * @var Doofinder_For_WordPress
         */
        protected static $_instance = null;

        /**
         * Returns the only instance of Doofinder_For_WordPress
         *
         * @since 1.0.0
         * @return Doofinder_For_WordPress
         */
        public static function instance()
        {
            if (is_null(self::$_instance)) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /* Hacking is forbidden *******************************************************/

        /**
         * Cloning is forbidden.
         *
         * @since 1.0.0
         */
        public function __clone()
        {
            _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'wordpress-doofinder'), '0.1');
        }

        /**
         * Unserializing instances of this class is forbidden.
         *
         * @since 1.0.0
         */
        public function __wakeup()
        {
            _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'wordpress-doofinder'), '0.1');
        }

        /* Initialization *************************************************************/

        /**
         * Doofinder_For_WordPress constructor.
         *
         * @since 1.0.0
         */
        public function __construct()
        {
            $class = __CLASS__;

            if (!function_exists('is_plugin_active')) {
                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }

            // Load classes on demand
            self::autoload(self::plugin_path() . 'includes/');

            add_action('init', function () use ($class) {

                //Initialize update on save
                Update_On_Save::init();
                //Initialize reset credentials
                Reset_Credentials::init();
                //Initialize custom endpoints
                Endpoints::init();

                Landing::init();

                // Init admin functionalities
                if (is_admin()) {
                    Post::add_additional_settings();
                    Settings::instance();
                    if (Setup_Wizard::should_activate()) {
                        Setup_Wizard::activate(true);
                    }

                    Setup_Wizard::instance();
                    Update_On_Save::register_hooks();

                    self::register_ajax_action();
                    self::register_admin_scripts_and_styles();
                }

                // Init frontend functionalities
                if (!is_admin()) {
                    JS_Layer::instance();
                }

                // Register all custom URLs
                call_user_func(array($class, 'register_urls'));

                if (is_plugin_active('woocommerce/woocommerce.php'))
                    Add_To_Cart::instance();

                //Check if the plugin exists
                $old_plugin_notice_name = 'doofinder-for-wp-old-version-detected';
                if (file_exists(WP_PLUGIN_DIR . '/doofinder/doofinder.php')) {
                    Admin_Notices::add_notice($old_plugin_notice_name, __('Deprecated version of Doofinder plugin detected', 'wordpress-doofinder'), __('The Doofinder plugin has been merged into the new version of Doofinder for WooCommerce and is no longer needed. Therefore, we have deactivated it. We recommend uninstalling it to avoid future issues.', 'wordpress-doofinder'), 'warning');
                } else {
                    Admin_Notices::remove_notice($old_plugin_notice_name);
                }
            });

            add_action('plugins_loaded', array($class, 'plugin_update'));
            self::initialize_rest_endpoints();

            if (is_admin()) {
                Admin_Notices::init();
            }
        }

        /**
         * Autoload custom classes. Folders represent namespaces (after the predefined plugin prefix),
         * and files containing classes begin with "class-" prefix, so for example following file:
         * example-folder/class-example.php
         * Contains following class:
         * Doofinder\WP\Example_Folder\Example
         *
         * @since 1.0.0
         *
         * @param string $dir Root directory of libraries (where to begin lookup).
         */
        public static function autoload($dir)
        {
            $self = __CLASS__;
            spl_autoload_register(function ($class) use ($self, $dir) {
                $prefix = 'Doofinder\\WP\\';

                /*
				 * Check if the class uses the plugins namespace.
				 */
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    return;
                }

                /*
				 * Class name after and path after the plugins prefix.
				 */
                $relative_class = substr($class, $len);

                /*
				 * Class names and folders are lowercase and hyphen delimited.
				 */
                $relative_class = strtolower(str_replace('_', '-', $relative_class));

                /*
				 * WordPress coding standards state that files containing classes should begin
				 * with 'class-' prefix. Also, we are looking specifically for .php files.
				 */
                $classes                          = explode('\\', $relative_class);
                $last_element                     = end($classes);
                $classes[count($classes) - 1] = "class-$last_element.php";
                $filename                         = $dir . implode('/', $classes);

                if (file_exists($filename)) {
                    require_once $filename;
                }
            });
        }

        /**
         * Get the plugin path.
         *
         * @since 1.0.0
         * @return string
         */
        public static function plugin_path()
        {
            return plugin_dir_path(__FILE__);
        }

        /**
         * Get the plugin URL.
         *
         * @since 1.0.0
         * @return string
         */
        public static function plugin_url()
        {
            return plugin_dir_url(__FILE__);
        }

        /**
         * Initialize all functionalities that register custom URLs.
         *
         * @since 1.0.0
         */
        public static function register_urls()
        {
            Platform_Confirmation::register();
        }

        /* Plugin activation and deactivation *****************************************/

        /**
         * Activation Hook to configure routes and so on
         *
         * @since 1.0.0
         * @return void
         */
        public static function plugin_enabled()
        {
            $df_wc_plugin = 'doofinder/doofinder.php';
            if (is_plugin_active($df_wc_plugin))
                deactivate_plugins($df_wc_plugin);

            self::autoload(self::plugin_path() . 'includes/');
            self::register_urls();
            flush_rewrite_rules();

            Update_On_Save::create_update_on_save_db();
            Update_On_Save::activate_update_on_save_task();

            $log = new Log();
            $log->log('Plugin enabled');

            if (Setup_Wizard::should_activate()) {
                Setup_Wizard::activate(true);
            }
        }

        /**
         * Deactivation Hook to flush routes
         *
         * @since 1.0.0
         * @return void
         */
        public static function plugin_disabled()
        {
            flush_rewrite_rules();
            Update_On_Save::clean_update_on_save_db();
            Update_On_Save::delete_update_on_save_db();
            Update_On_Save::deactivate_update_on_save_task();
        }


        public static function plugin_update()
        {
            $current_db_version = Settings::get_plugin_version();
            if ($current_db_version != self::$version) {
                Update_Manager::check_updates(self::$version);
            }
        }

        /**
         * This function runs when WordPress completes its upgrade process
         * It iterates through each plugin updated to see if ours is included
         *
         * @param array $upgrader_object
         * @param array $options
         */
        public static function upgrader_process_complete($upgrader_object, $options)
        {
            $log = new Log();
            $log->log('upgrader_process - start');
            // The path to our plugin's main file
            $our_plugin = plugin_basename(__FILE__);

            $log->log($our_plugin);
            $log->log($options);

            // If an update has taken place and the updated type is plugins and the plugins element exists
            if ($options['action'] == 'update' && $options['type'] == 'plugin') {

                $log->log('upgrader_process - updating plugin');

                if (isset($options['plugins'])) {
                    $plugins = $options['plugins'];
                } elseif (isset($options['plugin'])) {
                    $plugins = [$options['plugin']];
                }

                $log->log($plugins);
            }
        }

        public static function register_admin_scripts_and_styles()
        {
            add_action('admin_enqueue_scripts', function () {
                wp_enqueue_script('doofinder-admin-js', plugins_url('assets/js/admin.js', __FILE__));
                wp_localize_script('doofinder-admin-js', 'Doofinder', [
                    'nonce' => wp_create_nonce('doofinder-ajax-nonce'),
                    'show_indexing_notice' => Setup_Wizard::should_show_indexing_notice() ? 'true' : 'false',
                    'RESERVED_CUSTOM_ATTRIBUTES_NAMES' => Settings::RESERVED_CUSTOM_ATTRIBUTES_NAMES,
                    'reserved_custom_attributes_error_message' => __("The '%field_name%' field name is reserved, please use a different field name, e.g.: 'custom_%field_name%'", "wordpress-doofinder"),
                    'duplicated_custom_attributes_error_message' => __("The '%field_name%' field name is already in use, please use a different field name", "wordpress-doofinder")
                ]);

                // CSS
                wp_enqueue_style('doofinder-admin-css', Doofinder_For_WordPress::plugin_url() . '/assets/css/admin.css');
            });
        }

        public static function load_only_doofinder_admin_scripts_and_styles() {
            $current_screen = get_current_screen();

            // Verify if it is the specific page by its unique identifier
            if ($current_screen->id === 'toplevel_page_doofinder_for_wp') {
                //Add the Select2 CSS file
                wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0');
                //Add the Select2 JavaScript file
                wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 'jquery', '4.1.0-rc.0');
            }
        }

        public static function initialize_rest_endpoints()
        {
            add_action('rest_api_init', function () {
                Config::register();

                if(empty($_SERVER["HTTP_DOOFINDER_TOKEN"])){
                    REST_API_Handler::initialize();
                }

                Index_Status_Handler::initialize();
                Landing_Cache::register_endpoint();
            });
        }

        /**
         * Register an ajax action that processes wizard step 2 and creates search engines.
         *
         *
         * @since 1.0.0
         */
        private static function register_ajax_action()
        {
            //Check Indexing status
            add_action('wp_ajax_doofinder_check_indexing_status', function () {
                $multilanguage = Multilanguage::instance();
                $lang = ($multilanguage->get_current_language() === $multilanguage->get_base_language()) ? "" : $multilanguage->get_current_language();
                $status = Settings::get_indexing_status($lang);

                if (Index_Status_Handler::is_indexing_status_timed_out($lang)) {
                    Setup_Wizard::dismiss_indexing_notice();
                    $status = 'timed-out';
                    Settings::set_indexing_status($status, $lang);
                }

                wp_send_json([
                    'status' => $status
                ]);
                exit;
            });

            //Notice dismiss
            add_action('wp_ajax_doofinder_notice_dismiss', function () {
                if (!wp_verify_nonce($_POST['nonce'], 'doofinder-ajax-nonce')) {
                    status_header(\WP_Http::UNAUTHORIZED);
                    die('Unauthorized request');
                }
                $notice_id = $_POST['notice_id'];
                Admin_Notices::remove_notice($notice_id);
                wp_send_json([
                    'success' => true
                ]);
                exit;
            });
        }
    }

endif;

register_activation_hook(__FILE__, array('\Doofinder\WP\Doofinder_For_WordPress', 'plugin_enabled'));
register_deactivation_hook(__FILE__, array('\Doofinder\WP\Doofinder_For_WordPress', 'plugin_disabled'));

add_action('admin_enqueue_scripts', array('\Doofinder\WP\Doofinder_For_WordPress', 'load_only_doofinder_admin_scripts_and_styles'), 10, 2);
add_action('plugins_loaded', array('\Doofinder\WP\Doofinder_For_WordPress', 'instance'), 0);
add_action('upgrader_process_complete', array('\Doofinder\WP\Doofinder_For_WordPress', 'upgrader_process_complete'), 10, 2);
