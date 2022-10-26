<?php

/**
 * Plugin Name: Doofinder for WooCommerce
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Version: 1.5.38
 * Author: doofinder
 * Description: Integrate Doofinder Search in your WooCommerce shop.
 *
 * Woo: Woo Plugin Header For Updates (ref: https://woocommerce.com/document/create-a-plugin/#section-12)
 * WC requires at least: 3.0
 * WC tested up to: 5.9
 *
 * @package WordPress
 */


namespace Doofinder\WC;

use Doofinder\WC\Settings\Settings;

defined('ABSPATH') or die;

// Initialize only if WooCommerce is installed
if (
    // Check if plugin is installed on the current site
    in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ||

    /**
     * Check if plugin is installed site-wide in multi-site environment.
     * First we need to check if apply_filters is array, if no multisite
     * environment it will return false and produce error.
     */
    (is_array(apply_filters('active_sitewide_plugins', get_site_option('active_sitewide_plugins'))) && array_key_exists('woocommerce/woocommerce.php', apply_filters('active_sitewide_plugins', get_site_option('active_sitewide_plugins'))))
) :

    if (!class_exists('\Doofinder\WC\Doofinder_For_WooCommerce')) :

        /**
         * Main Plugin Class
         *
         * @class Doofinder_For_WooCommerce
         */
        class Doofinder_For_WooCommerce
        {
            /**
             * Previous error level.
             *
             * @var int
             */
            public static $prev_errors;

            /**
             * Plugin version.
             *
             * @var string
             */
            public static $version = '1.5.38';

            /**
             * The only instance of Doofinder_For_WooCommerce
             *
             * @var Doofinder_For_WooCommerce
             */
            protected static $_instance = null;



            /**
             * Returns the only instance of Doofinder_For_WooCommerce
             *
             * @since 1.0.0
             * @return Doofinder_For_WooCommerce
             */
            public static function instance()
            {
                if (is_null(self::$_instance)) {
                    self::$_instance = new self();
                }

                return self::$_instance;
            }

            /**
             * Should api calls be disabled for local testing
             *
             * @var bool
             */
            public static $disable_api_calls = false;

            /* Hacking is forbidden *******************************************************/

            /**
             * Cloning is forbidden.
             *
             * @since 1.0.0
             */
            public function __clone()
            {
                _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'woocommerce-doofinder'), '0.1');
            }

            /**
             * Unserializing instances of this class is forbidden.
             *
             * @since 1.0.0
             */
            public function __wakeup()
            {
                _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'woocommerce-doofinder'), '0.1');
            }

            /* Initialization *************************************************************/

            /**
             * Doofinder_For_WooCommerce constructor.
             *
             * @since 1.0.0
             */
            public function __construct()
            {
                $class = __CLASS__;

                // Load classes on demand
                self::autoload(self::plugin_path() . 'includes/');
                require_once 'lib/vendor/autoload.php';
                require_once 'lib/autoload.php';

                // Register all custom URLs
                add_action('init', function () use ($class) {
                    call_user_func(array($class, 'register_urls'));
                });

                add_action('plugins_loaded', array($class, 'plugin_update'));

                // Initialize Admin Panel functionality on admin side, and front functionality on front side
                if (is_admin()) {
                    if (Setup_Wizard::should_activate()) {
                        Setup_Wizard::activate();
                    }

                    // if ( Setup_Wizard::should_show_notice() ) {
                    // 	Setup_Wizard::add_notice();
                    // }

                    // Try to migrate settings if possible and necessary
                    if (Setup_Wizard::should_migrate()) {
                        Setup_Wizard::migrate();
                    }

                    Thumbnail::prepare_thumbnail_size();
                    Post::add_additional_settings();
                    Post::register_webhooks();

                    Setup_Wizard::instance();
                    Admin::instance();
                    Index_Interface::instance();
                } else {
                    Front::instance();
                    Post::register_rest_api_webhooks();
                }

                // Some functionalities need to be initialized on both admin side, and frontend.
                Both_Sides::instance();
                Add_To_Cart::instance();
            }

            /**
             * Callback for WP Rest Api custom endpoint
             */
            public static function connect()
            {
                return Setup_Wizard::connect();
            }

            /**
             * Suppress notices on production environments.
             *
             * WP tries to access a property of a `null` when loading plugins,
             * which generates a notice, that, when generating an XML feed
             * generates a written warning that is not a legal XML.
             */
            public static function maybe_suppress_notices()
            {
                if (is_ssl() || getenv('APP_ENV') === 'production' && !isset(static::$prev_errors)) {
                    static::$prev_errors = error_reporting();
                    error_reporting(static::$prev_errors & ~E_NOTICE);
                }
            }

            public static function maybe_restore_notices()
            {
                if (is_ssl() || getenv('APP_ENV') === 'production' && isset(static::$prev_errors)) {
                    error_reporting(static::$prev_errors);
                }
            }

            /**
             * Autoload custom classes. Folders represent namespaces (after the predefined plugin prefix),
             * and files containing classes begin with "class-" prefix, so for example following file:
             * example-folder/class-example.php
             * Contains following class:
             * Doofinder\WC\Example_Folder\Example
             *
             * @since 1.0.0
             *
             * @param string $dir Root directory of libraries (where to begin lookup).
             */
            public static function autoload($dir)
            {
                $self = __CLASS__;
                spl_autoload_register(function ($class) use ($self, $dir) {
                    $prefix = 'Doofinder\\WC\\';

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
                    $classes = explode('\\', $relative_class);
                    $last_element = end($classes);
                    $classes[count($classes) - 1] = "class-$last_element.php";
                    $filename = $dir . implode('/', $classes);

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
                Data_Feed::register();
                Config::register();
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
                self::autoload(self::plugin_path() . 'includes/');
                self::register_urls();
                flush_rewrite_rules();

                $log = new Log();
                $log->log('plugin enabled');

                if (Setup_Wizard::should_activate()) {
                    Setup_Wizard::activate(true);
                }

                if (Setup_Wizard::should_show_notice()) {
                    Setup_Wizard::add_notice();
                }

                if (Setup_Wizard::should_migrate()) {
                    Setup_Wizard::migrate();
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

                $log = new Log();
                $log->log('plugin disabled');
                Setup_Wizard::remove_notice();
                //Reset migration status
                Setup_Wizard::remove_migration_notice();
                update_option(Setup_Wizard::$wizard_migration_option, '');
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

                    // Iterate through the plugins being updated and check if ours is there
                    foreach ($plugins as $plugin) {
                        $log->log($plugin);

                        if ($plugin == $our_plugin) {

                            if (Setup_Wizard::should_activate()) {
                                Setup_Wizard::activate();
                            }

                            if (Setup_Wizard::should_show_notice()) {
                                Setup_Wizard::add_notice();
                            }

                            $log->log('upgrader_process - try to migrate');
                            // Try to migrate settings if possible and necessary
                            if (Setup_Wizard::should_migrate()) {
                                Setup_Wizard::migrate();
                            }
                        }
                    }
                }
            }

            /**
             * Add settings link next to deactivate on plugin's page in admin panel
             *
             * @since 1.0.0
             * @return string
             */
            public static function plugin_add_settings_link($links)
            {
                $links['settings'] = '<a href="' . Settings::get_url() . '">' . __('Settings', 'woocommerc-doofinder') . '</a>';

                return array_reverse($links); // array reverse to display "Settings" link first
            }

            public static function plugin_update()
            {
                if (Settings::get_plugin_version() != self::$version) {
                    Update_Manager::check_updates(self::$version);
                }
            }
        }

    endif;

    register_activation_hook(__FILE__, array('\Doofinder\WC\Doofinder_For_WooCommerce', 'plugin_enabled'));
    register_deactivation_hook(__FILE__, array('\Doofinder\WC\Doofinder_For_WooCommerce', 'plugin_disabled'));

    add_action('plugins_loaded', array('\Doofinder\WC\Doofinder_For_WooCommerce', 'instance'), 0);
    add_action('upgrader_process_complete', array('\Doofinder\WC\Doofinder_For_WooCommerce', 'upgrader_process_complete'), 10, 2);

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), array('\Doofinder\WC\Doofinder_For_WooCommerce', 'plugin_add_settings_link'));

else :

    // Doofinder requires WooCommerce plugin installed
    add_action('admin_notices', function () {

?><div id="message" class="error notice is-dismissible"><?php
                                                        ?><p class="main"><?php _e('Doofinder for WooCommerce: No active WooCommerce plugin was found. Please install/activate WooCommerce.', 'woocommerce-doofinder') ?></p><?php
                                                                                                                                                                                                                                ?></div><?php

                                                                                                                                                                                                                                    });

                                                                                                                                                                                                                                endif;
