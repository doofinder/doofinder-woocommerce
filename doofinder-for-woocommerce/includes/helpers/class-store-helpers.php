<?php
/**
 * DooFinder Store_Helpers methods.
 *
 * @package Doofinder\WP\Helpers
 */

namespace Doofinder\WP\Helpers;

use WP_Application_Passwords;

/**
 * Adds helper methods regarding to the store.
 */
class Store_Helpers {


	/**
	 * Generates an api_password and returns the store options.
	 *
	 * @return array Store options
	 */
	public static function get_store_options() {
		$password_data   = self::create_application_credentials();
		$endpoints_token = self::create_endpoints_token();

		update_option( 'doofinder_for_wp_token', $endpoints_token );

		$blog_url = preg_replace( '#^https?://#', '', get_bloginfo( 'url' ) );

		$options = array(
			'blog_id'  => get_current_blog_id(),
			'url'      => $blog_url,
			'df_token' => $endpoints_token,
		);

		if ( ! is_null( $password_data ) ) {
			$options['api_pass'] = $password_data['api_pass'];
			$options['api_user'] = $password_data['api_user'];
			return $options;
		} else {
			return $options;
		}
	}

	/**
	 * Creates a new application password.
	 * If a password exists, it deletes it and creates a new password.
	 *
	 * We store the user_id and the uuid in order to know which application
	 * password we must delete.
	 *
	 * @return array|null Array containing api_user and api_pass
	 */
	private static function create_application_credentials() {
		$user_id                 = get_current_user_id();
		$user                    = get_user_by( 'id', $user_id );
		$credentials_option_name = 'doofinder_for_wp_app_credentials_' . get_current_blog_id();
		$credentials             = get_option( $credentials_option_name );
		$password_data           = null;
		$app_name                = 'doofinder_' . get_current_blog_id();

		// Check if the method exists before calling it.
		// This is to avoid errors in older versions of WordPress (WP < 5.7).
		if ( ! method_exists( WP_Application_Passwords::class, 'application_name_exists_for_user' ) ) {
			return $password_data;
		}

		if ( is_array( $credentials ) && array_key_exists( 'user_id', $credentials ) && array_key_exists( 'uuid', $credentials ) ) {
			WP_Application_Passwords::delete_application_password( $credentials['user_id'], $credentials['uuid'] );
		}

		if ( ! WP_Application_Passwords::application_name_exists_for_user( $user_id, $app_name ) ) {
			$app_pass    = WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => $app_name ) );
			$credentials = array(
				'user_id' => $user_id,
				'uuid'    => $app_pass[1]['uuid'],
			);
			update_option( $credentials_option_name, $credentials );

			$password_data = array(
				'api_user' => $user->data->user_login,
				'api_pass' => $app_pass[0],
			);
		}
		return $password_data;
	}

	/**
	 * To create a new token that will be used to authenticate new product endpoints via headers
	 *
	 * @return string New token to authenticate woocommerce endpoints created by doofinder
	 */
	public static function create_endpoints_token() {
		$random_string = uniqid();
		return md5( $random_string );
	}
}
