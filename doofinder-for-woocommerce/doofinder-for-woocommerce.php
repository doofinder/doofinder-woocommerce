<?php
/**
 * Plugin Name: DOOFINDER Search and Discovery for WP & WooCommerce
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Version: 2.9.1
 * Requires at least: 5.6
 * Requires PHP: 7.0
 * Author: Doofinder
 * Description: Integrate Doofinder Search in your WordPress site or WooCommerce shop.
 *
 * @package WordPress
 */

namespace Doofinder\WP;

use Doofinder\WP\Admin_Notices;
use Doofinder\WP\Log;
use Doofinder\WP\Multilanguage\Multilanguage;
use Doofinder\WP\Settings;
use WP_Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Doofinder\WP\Doofinder_For_WordPress' ) ) :

	/**
	 * Main Plugin Class
	 *
	 * @class Doofinder_For_WordPress
	 */
	class Doofinder_For_WordPress {

		const PLUGIN_DIR = __DIR__;

		/**
		 * Plugin version.
		 *
		 * @var string
		 */

		public static $version = '2.9.1';

		/**
		 * The only instance of Doofinder_For_WordPress
		 *
		 * @var Doofinder_For_WordPress
		 */
		protected static $instance = null;

		/**
		 * Returns the only instance of Doofinder_For_WordPress
		 *
		 * @since 1.0.0
		 * @return Doofinder_For_WordPress
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/* Hacking is forbidden *******************************************************/

		/**
		 * Cloning is forbidden.
		 *
		 * @since 1.0.0
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'wordpress-doofinder' ), '0.1' );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 *
		 * @since 1.0.0
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'wordpress-doofinder' ), '0.1' );
		}

		/* Initialization *************************************************************/

		/**
		 * Doofinder_For_WordPress constructor.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			$php_class = __CLASS__;

			if ( ! function_exists( 'is_plugin_active' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$includes_path = self::plugin_path() . 'includes/';

			include_once $includes_path . 'polyfills.php';

			// Load classes on demand.
			self::autoload( $includes_path );

			add_action(
				'init',
				function () use ( $php_class ) {

					// Initialize update on save.
					Update_On_Save::init();
					// Initialize reset credentials.
					Reset_Credentials::init();

					// Init admin functionalities.
					if ( is_admin() ) {
						Post::add_additional_settings();
						Settings::instance();
						if ( Setup_Wizard::should_activate() ) {
							Setup_Wizard::activate( true );
						}

						Setup_Wizard::instance();
						Update_On_Save::register_hooks();

						self::register_notices_styles();
						self::register_ajax_action();
					}

					if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
						Add_To_Cart::instance();
						Klaviyo_Integration::instance();
					}

					// Init frontend functionalities.
					if ( ! is_admin() ) {
						JS_Layer::instance();
					}

					// Check if the plugin exists.
					$old_plugin_notice_name = 'doofinder-for-wp-old-version-detected';
					if ( file_exists( WP_PLUGIN_DIR . '/doofinder/doofinder.php' ) ) {
						Admin_Notices::add_notice( $old_plugin_notice_name, __( 'Deprecated version of Doofinder plugin detected', 'wordpress-doofinder' ), __( 'The Doofinder plugin has been merged into the new version of Doofinder for WooCommerce and is no longer needed. Therefore, we have deactivated it. We recommend uninstalling it to avoid future issues.', 'wordpress-doofinder' ), 'warning' );
					} else {
						Admin_Notices::remove_notice( $old_plugin_notice_name );
					}
				}
			);

			add_action( 'plugins_loaded', array( $php_class, 'plugin_update' ) );
			self::initialize_rest_endpoints();

			if ( is_admin() ) {
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
		public static function autoload( $dir ) {
			$self = __CLASS__;
			spl_autoload_register(
				function ( $php_class ) use ( $self, $dir ) {
					$prefix = 'Doofinder\\WP\\';

					/*
					* Check if the class uses the plugins namespace.
					*/
					$len = strlen( $prefix );
					if ( strncmp( $prefix, $php_class, $len ) !== 0 ) {
						return;
					}

					/*
					* Class name after and path after the plugins prefix.
					*/
					$relative_class = substr( $php_class, $len );

					/*
					* Class names and folders are lowercase and hyphen delimited.
					*/
					$relative_class = strtolower( str_replace( '_', '-', $relative_class ) );

					/*
					* WordPress coding standards state that files containing classes should begin
					* with 'class-' prefix. Also, we are looking specifically for .php files.
					*/
					$classes                          = explode( '\\', $relative_class );
					$last_element                     = end( $classes );
					$classes[ count( $classes ) - 1 ] = "class-$last_element.php";
					$filename                         = $dir . implode( '/', $classes );

					if ( file_exists( $filename ) ) {
						require_once $filename;
					}
				}
			);
		}

		/**
		 * Get the plugin path.
		 *
		 * @since 1.0.0
		 * @return string
		 */
		public static function plugin_path() {
			return plugin_dir_path( __FILE__ );
		}

		/**
		 * Get the plugin URL.
		 *
		 * @since 1.0.0
		 * @return string
		 */
		public static function plugin_url() {
			return plugin_dir_url( __FILE__ );
		}

		/* Plugin activation and deactivation *****************************************/

		/**
		 * Activation Hook to configure routes and so on.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public static function plugin_enabled() {
			$df_wc_plugin = 'doofinder/doofinder.php';
			if ( is_plugin_active( $df_wc_plugin ) ) {
				deactivate_plugins( $df_wc_plugin );
			}

			self::autoload( self::plugin_path() . 'includes/' );
			flush_rewrite_rules();

			Update_On_Save::create_update_on_save_db();
			Update_On_Save::activate_update_on_save_task();

			$log = new Log();
			$log->log( 'Plugin enabled' );

			if ( Setup_Wizard::should_activate() ) {
				Setup_Wizard::activate( true );
			}
		}

		/**
		 * Deactivation Hook to flush routes
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public static function plugin_disabled() {
			flush_rewrite_rules();
			Update_On_Save::clean_update_on_save_db();
			Update_On_Save::delete_update_on_save_db();
			Update_On_Save::deactivate_update_on_save_task();
		}

		/**
		 * Hook to manage the plugin update. Useful for migrations.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public static function plugin_update() {
			$current_db_version = Settings::get_plugin_version();
			if ( $current_db_version !== self::$version ) {
				Update_Manager::check_updates( self::$version );
			}
		}

		/**
		 * This function runs when WordPress completes its upgrade process
		 * It iterates through each plugin updated to see if ours is included.
		 * More info about the parameters at https://developer.wordpress.org/reference/hooks/upgrader_process_complete/
		 *
		 * @param \WP_Upgrader $upgrader_object Upgrader object.
		 * @param array        $options Array of bulk item update data, like the action or the type.
		 */
		public static function upgrader_process_complete( $upgrader_object, $options ) {
			$log = new Log();
			$log->log( 'upgrader_process - start' );
			// The path to our plugin's main file.
			$our_plugin = plugin_basename( __FILE__ );

			$log->log( $our_plugin );
			$log->log( $options );

			// If an update has taken place and the updated type is plugins and the plugins element exists.
			if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {

				$log->log( 'upgrader_process - updating plugin' );

				if ( isset( $options['plugins'] ) ) {
					$plugins = $options['plugins'];
				} elseif ( isset( $options['plugin'] ) ) {
					$plugins = array( $options['plugin'] );
				}

				$log->log( $plugins );
			}
		}

		/**
		 * Load Doofinder scripts and styles in the admin only if the current page is our
		 * plugin configuration page.
		 *
		 * @return void
		 */
		public static function load_only_doofinder_admin_scripts_and_styles() {
			$current_screen = get_current_screen();

			// Verify if it is the specific page by its unique identifier.
			if ( 'toplevel_page_doofinder_for_wp' !== $current_screen->id ) {
				return;
			}

			wp_enqueue_script( 'doofinder-admin-js', plugins_url( 'assets/js/admin.js', __FILE__ ), array(), self::$version, array( 'in_footer' => false ) );
			wp_localize_script(
				'doofinder-admin-js',
				'Doofinder',
				array(
					'nonce'                            => wp_create_nonce( 'doofinder-ajax-nonce' ),
					'show_indexing_notice'             => Setup_Wizard::should_show_indexing_notice() ? 'true' : 'false',
					'RESERVED_CUSTOM_ATTRIBUTES_NAMES' => Settings::RESERVED_CUSTOM_ATTRIBUTES_NAMES,
					/* translators: %1$s is replaced with the field name. */
					'reserved_custom_attributes_error_message' => sprintf( __( "The '%1\$s' field name is reserved, please use a different field name, e.g.: 'custom_%1\$s'", 'wordpress-doofinder' ), '%field_name%' ),
					/* translators: %s is replaced with the field name. */
					'duplicated_custom_attributes_error_message' => sprintf( __( "The '%s' field name is already in use, please use a different field name", 'wordpress-doofinder' ), '%field_name%' ),
				)
			);

			// CSS.
			wp_enqueue_style( 'doofinder-admin-css', self::plugin_url() . '/assets/css/admin.css', array(), self::$version );
			// Add the Select2 CSS file.
			wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0' );
			// Add the Select2 JavaScript file.
			wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0-rc.0', array( 'in_footer' => false ) );
		}

		/**
		 * Registers styles used across the admin.
		 *
		 * @return void
		 */
		public static function register_notices_styles() {
			wp_enqueue_style( 'doofinder-notice', self::plugin_url() . '/assets/css/doofinder-notice.css', array(), self::$version );
		}

		/**
		 * This method initializes REST API endpoints.
		 *
		 * We must remember that 'rest_api_init' hooks should be added outside the 'init' hook to prevent endpoints from
		 * not being registered because the 'rest_api_init' hook is executed earlier.
		 * This is because we cannot guarantee that the order will always be 'init > rest_api_init'.
		 *
		 * @return void
		 */
		public static function initialize_rest_endpoints() {
			// Initialize custom endpoints.
			Endpoints::init();

			add_action(
				'rest_api_init',
				function () {
					Config::register();

					if ( empty( $_SERVER['HTTP_DOOFINDER_TOKEN'] ) ) {
						REST_API_Handler::initialize();
					}

					Index_Status_Handler::initialize();
				}
			);
		}

		/**
		 * Register an ajax action that processes wizard step 2 and creates search engines.
		 *
		 * @since 1.0.0
		 */
		private static function register_ajax_action() {
			// Check Indexing status.
			add_action(
				'wp_ajax_doofinder_check_indexing_status',
				function () {
					$multilanguage = Multilanguage::instance();
					$lang          = ( $multilanguage->get_current_language() === $multilanguage->get_base_language() ) ? '' : $multilanguage->get_current_language();
					$status        = Settings::get_indexing_status( $lang );

					if ( Index_Status_Handler::is_indexing_status_timed_out( $lang ) ) {
						Setup_Wizard::dismiss_indexing_notice();
						$status = 'timed-out';
						Settings::set_indexing_status( $status, $lang );
					}

					wp_send_json(
						array(
							'status' => $status,
						)
					);
					exit;
				}
			);

			// Notice dismiss.
			add_action(
				'wp_ajax_doofinder_notice_dismiss',
				function () {
					if ( ! isset( $_POST['nonce'] ) || ! isset( $_POST['notice_id'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'doofinder-ajax-nonce' ) ) {
						status_header( WP_Http::UNAUTHORIZED );
						die( 'Unauthorized request' );
					}
					$notice_id = sanitize_text_field( wp_unslash( $_POST['notice_id'] ) );
					Admin_Notices::remove_notice( $notice_id );
					wp_send_json(
						array(
							'success' => true,
						)
					);
					exit;
				}
			);
		}

		/**
		 * Method that adds some custom schedules to be used in WP Cron.
		 *
		 * @param array $schedules Current WP Schedules as array.
		 *
		 * @return array List of previous schedules + DooFinder ones.
		 */
		public static function add_schedules( $schedules ) {
			$df_schedules = array(
				'wp_doofinder_each_5_minutes'  => array(
					/* translators: %s is replaced with an integer number representing the minutes. */
					'display'  => sprintf( __( 'Each %s minutes', 'wordpress-doofinder' ), 5 ),
					'interval' => MINUTE_IN_SECONDS * 5,
				),
				'wp_doofinder_each_15_minutes' => array(
					/* translators: %s is replaced with an integer number representing the minutes. */
					'display'  => sprintf( __( 'Each %s minutes', 'wordpress-doofinder' ), 15 ),
					'interval' => MINUTE_IN_SECONDS * 15,
				),
				'wp_doofinder_each_30_minutes' => array(
					/* translators: %s is replaced with an integer number representing the minutes. */
					'display'  => sprintf( __( 'Each %s minutes', 'wordpress-doofinder' ), 30 ),
					'interval' => MINUTE_IN_SECONDS * 30,
				),
				'wp_doofinder_each_60_minutes' => array(
					'display'  => __( 'Each hour', 'wordpress-doofinder' ),
					'interval' => HOUR_IN_SECONDS,
				),
				'wp_doofinder_each_2_hours'    => array(
					/* translators: %s is replaced with an integer number representing the hours. */
					'display'  => sprintf( __( 'Each %s hours', 'wordpress-doofinder' ), 2 ),
					'interval' => HOUR_IN_SECONDS * 2,
				),
				'wp_doofinder_each_6_hours'    => array(
					/* translators: %s is replaced with an integer number representing the hours. */
					'display'  => sprintf( __( 'Each %s hours', 'wordpress-doofinder' ), 6 ),
					'interval' => HOUR_IN_SECONDS * 6,
				),
				'wp_doofinder_each_12_hours'   => array(
					/* translators: %s is replaced with an integer number representing the hours. */
					'display'  => sprintf( __( 'Each %s hours', 'wordpress-doofinder' ), 12 ),
					'interval' => HOUR_IN_SECONDS * 12,
				),
				'wp_doofinder_each_day'        => array(
					'display'  => __( 'Once a day', 'wordpress-doofinder' ),
					'interval' => DAY_IN_SECONDS,
				),
				'wp_doofinder_disabled'        => array(
					'display'  => __( 'Disabled', 'wordpress-doofinder' ),
					'interval' => DAY_IN_SECONDS,
				),
			);

			return array_merge( $schedules, $df_schedules );
		}
	}

endif;

register_activation_hook( __FILE__, array( '\Doofinder\WP\Doofinder_For_WordPress', 'plugin_enabled' ) );
register_deactivation_hook( __FILE__, array( '\Doofinder\WP\Doofinder_For_WordPress', 'plugin_disabled' ) );

add_action( 'admin_enqueue_scripts', array( '\Doofinder\WP\Doofinder_For_WordPress', 'load_only_doofinder_admin_scripts_and_styles' ), 10, 2 );
add_action( 'plugins_loaded', array( '\Doofinder\WP\Doofinder_For_WordPress', 'instance' ), 0 );
add_action( 'upgrader_process_complete', array( '\Doofinder\WP\Doofinder_For_WordPress', 'upgrader_process_complete' ), 10, 2 );
// Add cron_schedules here to avoid issues with hook order.
add_filter( 'cron_schedules', array( '\Doofinder\WP\Doofinder_For_WordPress', 'add_schedules' ), 100, 1 ); // phpcs:ignore WordPress.WP.CronInterval

// When doing update on save from cron we are not authenticated, so WP_REST_Request to get products data returned a 401.
add_filter(
	'woocommerce_rest_check_permissions',
	function ( $permission, $context ) {
		if ( wp_doing_cron() && 'read' === $context && doing_action( 'doofinder_update_on_save' ) ) {
			return true;
		}

		return $permission;
	},
	100,
	2
);
