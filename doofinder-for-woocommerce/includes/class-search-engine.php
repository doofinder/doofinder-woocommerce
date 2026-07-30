<?php
/**
 * DooFinder Search_Engine methods.
 *
 * @package Doofinder\WP
 */

namespace Doofinder\WP;

use Doofinder\WP\Api\Store_Api;
use Doofinder\WP\Multilanguage\Multilanguage;
use WP_Http;

/**
 * Search_Engine Class.
 *
 * Groups Search Engine related admin operations. Currently only handles the
 * "Create Search Engine" button shown in the settings page next to the
 * Search Engine hash field, for languages that don't have one yet.
 */
class Search_Engine {

	/**
	 * Initializes the action hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_doofinder_create_search_engine', array( __CLASS__, 'handle_ajax_request' ) );
	}

	/**
	 * Handles the AJAX request to create a Search Engine for the given language.
	 *
	 * @return void
	 */
	public static function handle_ajax_request() {
		if ( ! isset( $_POST['nonce'] ) || ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'doofinder-ajax-nonce' ) ) {
			status_header( WP_Http::UNAUTHORIZED );
			die( 'Unauthorized request' );
		}

		$multilanguage = Multilanguage::instance();
		$lang_code     = isset( $_POST['lang'] ) ? sanitize_text_field( wp_unslash( $_POST['lang'] ) ) : '';
		$lang          = null;

		if ( $lang_code ) {
			$languages = $multilanguage->get_languages();
			$lang      = is_array( $languages ) ? ( $languages[ $lang_code ] ?? null ) : null;

			if ( ! $lang ) {
				wp_send_json_error( array( 'message' => 'Invalid language' ) );
			}
		}

		$storage_code = self::get_storage_code( $multilanguage, $lang_code );
		$existing     = Settings::get_search_engine_hash( $storage_code );

		if ( $existing ) {
			// Already created (e.g. a concurrent request); nothing else to do.
			wp_send_json_success( array( 'hashid' => $existing ) );
		}

		$hash = ( new Store_Api() )->create_search_engine_for_language( $lang );

		if ( ! $hash ) {
			wp_send_json_error( array( 'message' => 'Search Engine creation failed' ) );
		}

		Settings::set_search_engine_hash( $hash, $storage_code );

		wp_send_json_success( array( 'hashid' => $hash ) );
	}

	/**
	 * Normalizes a raw WPML language code into the code used to store the
	 * Search Engine hash: an empty string for the base/default language,
	 * the language code itself otherwise. Mirrors the same normalization
	 * WPML::get_option_name() applies when building the settings option name.
	 *
	 * @param object $multilanguage Multilanguage instance.
	 * @param string $lang_code     Raw WPML language code (empty for no multilanguage plugin).
	 *
	 * @return string
	 */
	private static function get_storage_code( $multilanguage, $lang_code ) {
		if ( ! $lang_code ) {
			return '';
		}

		return $lang_code === $multilanguage->get_base_language() ? '' : $lang_code;
	}
}
