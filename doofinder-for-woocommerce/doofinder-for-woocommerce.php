<?php
/**
 * Plugin Name: Doofinder for WooCommerce
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Version: 1.2.17
 * Author: doofinder
 * Description: Integrate Doofinder Search in your WooCommerce shop.
 * WC requires at least: 2.1.0
 * WC tested up to: 3.7.0
 *
 * @package WordPress
 */

namespace Doofinder\WC;

defined( 'ABSPATH' ) or die;

// Initialize only if WooCommerce is installed
if (
	// Check if plugin is installed on the current site
	in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ||

	// Check if plugin is installed site-wide in multi-site environment
	array_key_exists( 'woocommerce/woocommerce.php', apply_filters( 'active_sitewide_plugins', get_site_option( 'active_sitewide_plugins' ) ) )
):

	if ( ! class_exists( '\Doofinder\WC\Doofinder_For_WooCommerce' ) ):

		/**
		 * Main Plugin Class
		 *
		 * @class Doofinder_For_WooCommerce
		 */
		class Doofinder_For_WooCommerce {

			/**
			 * Plugin version.
			 *
			 * @var string
			 */
			public static $version = '1.2.17';

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
			public static function instance() {
				if ( is_null( self::$_instance ) ) {
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
			public function __clone() {
				_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woocommerce-doofinder' ), '0.1' );
			}

			/**
			 * Unserializing instances of this class is forbidden.
			 *
			 * @since 1.0.0
			 */
			public function __wakeup() {
				_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woocommerce-doofinder' ), '0.1' );
			}

			/* Initialization *************************************************************/

			/**
			 * Doofinder_For_WooCommerce constructor.
			 *
			 * @since 1.0.0
			 */
			public function __construct() {
				$class = __CLASS__;

				// Load classes on demand
				self::autoload( self::plugin_path() . 'includes/' );
				include_once 'lib/autoload.php';

				// Register all custom URLs
				add_action( 'init', function() use ( $class ) {
					call_user_func( array( $class, 'register_urls' ) );
				} );

				// Initialize Admin Panel functionality on admin side, and front functionality on front side
				if ( is_admin() ) {
					Admin::instance();
				} else {
					Front::instance();
				}

				// Some functionalities need to be initialized on both admin side, and frontend.
				Both_Sides::instance();
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
			public static function autoload( $dir ) {
				$self = __CLASS__;
				spl_autoload_register( function( $class ) use ( $self, $dir ) {
					$prefix = 'Doofinder\\WC\\';

					/*
					 * Check if the class uses the plugins namespace.
					 */
					$len = strlen( $prefix );
					if ( strncmp( $prefix, $class, $len ) !== 0 ) {
						return;
					}

					/*
					 * Class name after and path after the plugins prefix.
					 */
					$relative_class = substr( $class, $len );

					/*
					 * Class names and folders are lowercase and hyphen delimited.
					 */
					$relative_class = strtolower( str_replace( '_', '-', $relative_class ) );

					/*
					 * WordPress coding standards state that files containing classes should begin
					 * with 'class-' prefix. Also, we are looking specifically for .php files.
					 */
					$classes = explode( '\\', $relative_class );
					$last_element = end( $classes );
					$classes[ count( $classes ) - 1 ] = "class-$last_element.php";
					$filename = $dir . implode( '/', $classes );

					if ( file_exists( $filename ) ) {
						require_once $filename;
					}
				} );
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

			/**
			 * Initialize all functionalities that register custom URLs.
			 *
			 * @since 1.0.0
			 */
			public static function register_urls() {
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
			public static function plugin_enabled() {
				self::autoload( self::plugin_path() . 'includes/' );
				self::register_urls();
				flush_rewrite_rules();
			}

			/**
			 * Deactivation Hook to flush routes
			 *
			 * @since 1.0.0
			 * @return void
			 */
			public static function plugin_disabled() {
				flush_rewrite_rules();
			}
		}

	endif;

	register_activation_hook( __FILE__, array( '\Doofinder\WC\Doofinder_For_WooCommerce', 'plugin_enabled' ) );
	register_deactivation_hook( __FILE__, array( '\Doofinder\WC\Doofinder_For_WooCommerce', 'plugin_disabled' ) );

	add_action( 'plugins_loaded', array( '\Doofinder\WC\Doofinder_For_WooCommerce', 'instance' ), 0 );

endif;
