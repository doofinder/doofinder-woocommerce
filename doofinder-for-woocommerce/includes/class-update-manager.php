<?php
/**
 * DooFinder Update_Manager methods.
 *
 * @package Doofinder\WP\Update;
 */

namespace Doofinder\WP;

use Doofinder\WP\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the plugin Update_Manager process.
 */
class Update_Manager {

	/**
	 * To log anything.
	 *
	 * @var Log
	 */
	private static $logger = null;

	/**
	 * Checks and performs any pending DB updates.
	 *
	 * @param string $plugin_version Version of the plugin.
	 *
	 * @since 1.0
	 */
	public static function check_updates( $plugin_version ) {
		$db_version = Settings::get_plugin_version();
		self::log( "Check updates from $db_version to $plugin_version" );

		if ( Settings::is_plugin_update_running() ) {
			// The update is being executed by another thread, ignore.
			return;
		}

		Settings::plugin_update_started();
		if ( empty( $plugin_version ) ) {

			self::log( "invalid plugin version: $plugin_version" );

			return false;
		}
		$current_normalized_version = self::normalize_plugin_version( Settings::get_plugin_version() );
		$plugin_normalized_version  = (int) self::normalize_plugin_version( $plugin_version );

		$result = null;
		for ( $version = $current_normalized_version + 1; $version <= $plugin_normalized_version; $version++ ) {
			$version_number  = str_pad( $version, 6, '0', STR_PAD_LEFT );
			$update_function = 'update_' . $version_number;

			self::log( "check if the update  $update_function exists." );
			if ( method_exists( self::class, $update_function ) ) {
				self::log( "Executing $update_function update..." );
				try {
					$result = call_user_func( array( self::class, $update_function ) );
				} catch ( \Exception $ex ) {
					self::update_failed( $version_number, $ex->getMessage() );
					break;
				}

				if ( $result ) {
					// Remove the current update notice in case it exists.
					self::remove_admin_notice( $version_number );
				} else {
					self::update_failed( $version_number );
					break;
				}

				self::log( "The update $update_function Succeeded" );
				$formatted_version = self::format_normalized_plugin_version( $version_number );

				// Update the database version to the newer one.
				Settings::set_plugin_version( $formatted_version );
			} else {
				// If the update function doesn't exists, return true to update the db version value.
				$result = true;
			}
		}

		if ( $result ) {
			// All updates executed successfully, update the plugin version to the latest one.
			Settings::set_plugin_version( $plugin_version );
		}

		Settings::plugin_update_ended();
		self::log( 'Updates ended, plugin db version is: ' . Settings::get_plugin_version() );
	}

	/**
	 * Formats the plugin version to a normalized version.
	 * Example: 1.5.13 => 010513
	 *
	 * @param string $version Version of the plugin (SemVer).
	 */
	private static function normalize_plugin_version( $version ) {
		$normalized = '';
		$version    = explode( '.', $version );
		foreach ( $version as $key => $version_part ) {
			$normalized .= str_pad( $version_part, 2, '0', STR_PAD_LEFT );
		}
		return $normalized;
	}

	/**
	 * Formats a normalized version back to the x.y.z format version.
	 * Example: 010513 => 1.5.13
	 *
	 * @param string $normalized Normalized version.
	 *
	 * @return string Version as SemVer.
	 */
	private static function format_normalized_plugin_version( $normalized ) {
		$version = str_split( $normalized, 2 );
		$version = array_map(
			function ( $vnum ) {
				return (int) $vnum;
			},
			$version
		);

		return implode( '.', $version );
	}

	/**
	 * Logs the error and adds an admin notice.
	 *
	 * @param string $version Normalized version (e.g. 010513).
	 * @param string $message Error message to display.
	 *
	 * @return void
	 */
	private static function update_failed( $version, $message = '' ) {
		$formatted_version = self::format_normalized_plugin_version( $version );
		self::log( "ERROR: The update $formatted_version failed with message: " . $message );
		self::add_admin_notice( $version, $message );
	}

	/**
	 * Adds an admin notice using WooCommerce.
	 *
	 * @param string $version Normalized version (e.g. 010513).
	 * @param string $message Error message to display.
	 *
	 * @return void
	 */
	private static function add_admin_notice( $version, $message = '' ) {
		$formatted_version = self::format_normalized_plugin_version( $version );
		/* translators: %s is replaced with the version number. */
		$title = sprintf( __( 'An error occurred while updating the Doofinder Database to the %s version.', 'wordpress-doofinder' ), $formatted_version );
		/* translators: %1$s is replaced with the `<a>` opening tag and %2$s by `</a>`. */
		$message .= '<p>' . sprintf( __( 'For more details please contact us at our %1$s support center%2$s.', 'wordpress-doofinder' ), '<a target="_blank" href="https://support.doofinder.com/pages/contact-us.html">', '</a>' ) . '</p>';
		Admin_Notices::add_notice( 'update-' . $version, $title, $message, 'error', null, '', true );
	}

	/**
	 * Removes an admin notice using WooCommerce.
	 *
	 * @param string $version Normalized version (e.g. 010513).
	 */
	private static function remove_admin_notice( $version ) {
		Admin_Notices::remove_notice( 'update-' . $version );
	}

	/**
	 * Logs messages to the updates.log.
	 *
	 * @param string $message Message to store in the updates.log.
	 */
	private static function log( $message ) {
		if ( empty( static::$logger ) ) {
			static::$logger = new Log( 'updates.log' );
		}
		static::$logger->log( $message );
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
	public static function update_020000() {
		Migration::migrate();
		return true;
	}

	/**
	 * Update: 2.0.3
	 * Remove the indexing failed notice to solve any existing problem.
	 *
	 * @return bool
	 */
	public static function update_020003() {
		Admin_Notices::remove_notice( 'indexing-status-failed' );
		return true;
	}

	/**
	 * Update: 2.0.13
	 * Update the woocommerce product attributes.
	 *
	 * @return bool
	 */
	public static function update_020013() {
		if ( get_option( 'woocommerce_doofinder_feed_attributes_additional_attributes' ) ) {
			Migration::migrate_custom_attributes();
		}
		return true;
	}

	/**
	 * Update: 2.1.0
	 * Update the woocommerce product attributes.
	 */
	public static function update_020100() {
		if ( Settings::is_configuration_complete() ) {
			Migration::create_token_auth();
		}
		return true;
	}

	/**
	 * Update: 2.1.12
	 * Remove stock_status from custom_attributes.
	 *
	 * @return bool
	 */
	public static function update_020112() {
		// Remove the stock_status custom_attribute if existing.
		$custom_attributes = Settings::get_custom_attributes();
		foreach ( $custom_attributes as $key => $attr ) {
			if ( 'stock_status' === $attr['attribute'] ) {
				unset( $custom_attributes[ $key ] );
				update_option( Settings::$custom_attributes_option, $custom_attributes );
				break;
			}
		}
		// Delete the custom_attributes transient.
		delete_transient( 'df_product_rest_attributes' );
		return true;
	}

	/**
	 * Update: 2.2.6
	 * Set the region
	 *
	 * @return bool
	 */
	public static function update_020206() {
		// Set Region.
		if ( Settings::is_configuration_complete() ) {
			Migration::maybe_set_region();
		}
		return true;
	}
}
