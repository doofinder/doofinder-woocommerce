<?php
/**
 * DooFinder Endpoint_Single_Script methods.
 *
 * @package Doofinder\WP\Endpoints
 */

use Doofinder\WP\Endpoints;
use Doofinder\WP\Helpers\Helpers;
use Doofinder\WP\Log;
use Doofinder\WP\Multilanguage\Multilanguage;
use Doofinder\WP\Settings;

/**
 * Class Endpoint_Single_Script
 *
 * This class defines a method to update the current script to the single script version.
 */
class Endpoint_Single_Script {
	const CONTEXT  = 'doofinder/v1';
	const ENDPOINT = '/single-script';

	/**
	 * Initialize the custom single script endpoint.
	 *
	 * @return void
	 */
	public static function initialize() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					self::CONTEXT,
					self::ENDPOINT,
					array(
						'methods'             => 'GET',
						'callback'            => array( __CLASS__, 'update_script_to_single_script' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
	}

	/**
	 * Replaces the current Doofinder script with the single one.
	 *
	 * @return string
	 */
	public static function update_script_to_single_script() {
		Endpoints::check_secure_token();
		$log             = new Log();
		$installation_id = self::get_installation_id_from_database_script();
		if ( empty( $installation_id ) ) {
			$log->log( 'Single script could not be updated because Installation ID could not be determined.' );
			return '';
		}

		$multilanguage = Multilanguage::instance();
		$languages     = $multilanguage->get_formatted_languages();

		if ( ! is_array( $languages ) ) {
			$languages = array();
		}

		foreach ( $languages as $language_code => $language_data ) {
			if ( empty( $language_code ) || $language_code === $multilanguage->get_base_locale() ) {
				continue;
			}

			Settings::set_js_layer( '', $language_data['code'] );
		}

		$region = Settings::get_region();
		if ( ! empty( $region ) ) {
			$region .= '-';
		}

		$single_script = sprintf( '<script src="https://%1$sconfig.doofinder.com/2.x/%2$s.js" async></script>', $region, $installation_id ); // phpcs:ignore
		Settings::set_js_layer( $single_script );

		update_option( 'doofinder_script_migrated', true );

		return $single_script;
	}

	/**
	 * Gets the installation ID from the script stored in the database.
	 *
	 * @param string $language Language code with two letters.
	 *
	 * @return string
	 */
	private static function get_installation_id_from_database_script( $language = '' ) {
		$current_script = Settings::get_js_layer( $language );
		preg_match( "/installationId: '([a-z0-9-]+)'/", $current_script, $matches );
		if ( empty( $matches[1] ) ) {
			return '';
		}

		return $matches[1];
	}
}
