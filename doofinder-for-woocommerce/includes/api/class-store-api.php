<?php
/**
 * DooFinder Reset_Credentials_Api methods.
 *
 * @package Doofinder\WP\Api
 */

namespace Doofinder\WP\Api;

use Doofinder\WP\Helpers\Helpers;
use Doofinder\WP\Helpers\Store_Helpers;
use Doofinder\WP\Setup_Wizard;
use Doofinder\WP\Log;
use Doofinder\WP\Multilanguage\Multilanguage;
use Doofinder\WP\Multilanguage\No_Language_Plugin;
use Doofinder\WP\Settings;
use Doofinder\WP\Doofinder_For_WordPress;
use Exception;
use WP_Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles requests to the Store API.
 */
class Store_Api {


	/**
	 * Instance of a class used to log to a file.
	 *
	 * @var Log
	 */
	private $log;

	/**
	 * Instance of a class used to log to a file.
	 *
	 * @var Multilanguage
	 */
	private $language;

	/**
	 * Dooplugins Host
	 *
	 * @var string
	 */
	private $dooplugins_host;


	/**
	 * API Key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Store_Api constructor.
	 */
	public function __construct() {
		// Get global disable_api_calls flag.
		$this->log = new Log( 'store_create_api.log' );

		$this->api_key         = Settings::get_api_key();
		$this->dooplugins_host = Settings::get_dooplugins_host();

		$this->log->log( '-------------  DOOPLUGINS HOST ------------- ' );
		$this->log->log( $this->dooplugins_host );

		$this->language = Multilanguage::instance();
	}

	/**
	 * Create a Store, Search Engine and Datatype
	 *
	 * @param array $api_keys The list of API keys with search engine ids.
	 *
	 * @return mixed
	 */
	public function create_store( $api_keys ) {
		if ( is_array( $api_keys ) ) {
			$store_payload = $this->build_store_payload( $api_keys );
			$this->log->log( 'store_data: ' );

			$store_payload_log = $store_payload;
			unset( $store_payload_log['options'] );

			$this->log->log( $store_payload_log );
			return $this->send_request( 'install', $store_payload );
		}
	}

	/**
	 * Sends a request to update the store options with the api password and to create any missing datatype.
	 *
	 * Payload example:
	 * $payload = array(
	 *    'store_options' => array(
	 *        'url' => 'http://wordpress.doofinder.com',
	 *        'df_token' => 'G41cXNeVoX4JGL2bhvbcMlQ4',
	 *        'api_pass' => 'fwafwaG41cXNeVoX4JGL2bhvbcMlQ4',
	 *        'api_user' => 'doofinder'
	 *    ),
	 *    'search_engines' => array(
	 *        'fde92a8f364b8d769262974e95d82dba' => array(
	 *          'lang' => 'en'
	 *        )
	 *    )
	 * )
	 *
	 * @return void
	 */
	public function normalize_store_and_indices() {
		$wizard   = Setup_Wizard::instance();
		$api_keys = Setup_Wizard::are_api_keys_present( $wizard->process_all_languages, $wizard->language );

		if ( ! Multilanguage::$is_multilang ) {
			$api_keys = array(
				'' => array(
					'hash' => Settings::get_search_engine_hash(),
				),
			);
		}

		$store_payload = $this->build_store_payload( $api_keys );

		$payload = array(
			'store_options' => $store_payload['options'],
			'platform'      => $store_payload['platform'],
		);

		foreach ( $store_payload['search_engines'] as $search_engine ) {
			$lang      = Helpers::get_language_from_locale( $search_engine['language'] );
			$lang_real = $search_engine['locale'];
			$base_lang = Helpers::get_language_from_locale( $this->language->get_base_language() );

			// If the installation is not multilanguage or it's the base language, replace the lang with ''.
			if ( is_a( $this->language, No_Language_Plugin::class ) || $lang === $base_lang ) {
				$lang = '';
			}

			if ( isset( $api_keys[ $lang ] ) ) {
				$se_hashid                               = $api_keys[ $lang ]['hash'];
				$payload['search_engines'][ $se_hashid ] = array( 'lang' => $lang_real );
			} else {
				$this->log->log( 'No search engine retrieved for the language - ' . $lang_real );
			}
		}

		$this->log->log( 'Sending request to normalize indices.' );
		$response = $this->send_request( 'wordpress/normalize-indices/', $payload, true );

		if ( ! is_array( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			$this->log->log( 'The store and indices normalization has failed due to an invalid response: ' . print_r( $response, true ) );
		} elseif ( array_key_exists( 'errors', $response ) ) {
			$this->log->log( 'The store and indices normalization has failed!' );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			$this->log->log( print_r( $response['errors'], true ) );
		} else {
			$this->log->log( 'The store and indices normalization has finished successfully!' );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			$this->log->log( "Response: \n" . print_r( $response, true ) );
		}
	}

	/**
	 * Send a POST request with the given $body to the given $endpoint.
	 *
	 * @param string $endpoint The endpoint url.
	 * @param array  $body The array containing the payload to be sent.
	 * @param bool   $migration This must be set manually to true, otherwise it will throw an exception.
	 *
	 * @return array The request decoded response.
	 */
	private function send_request( $endpoint, $body, $migration = false ) {
		$data = array(
			'headers'     => array(
				'Authorization' => "Token {$this->api_key}",
				'Content-Type'  => 'application/json; charset=utf-8',
			),
			'body'        => wp_json_encode( $body ),
			'method'      => 'POST',
			'data_format' => 'body',
			'timeout'     => 20,
		);

		$url = "{$this->dooplugins_host}/{$endpoint}";
		$this->log->log( "Making a request to: $url" );
		$response      = wp_remote_request( $url, $data );
		$response_code = wp_remote_retrieve_response_code( $response );

		$this->log->log( "Response code: $response_code" );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$this->log->log( 'Response: ' . print_r( $response, true ) );

		if ( ! $migration ) {
			$this->throw_exception( $response, $response_code );
		}

		$response_body = wp_remote_retrieve_body( $response );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$this->log->log( 'Response body: ' . print_r( $response_body, true ) );

		$decoded_response = json_decode( $response_body, true );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$this->log->log( 'Decoded response: ' . print_r( $decoded_response, true ) );

		return $decoded_response;
	}

	/**
	 * Generates the create-store payload.
	 *
	 * @param array $api_keys The list of search engine ids.
	 *
	 * @return array Store payload.
	 */
	private function build_store_payload( $api_keys ) {
		$primary_language = $this->get_primary_language();

		$name       = get_bloginfo( 'name' );
		$store_name = ! empty( $name ) ? $name : 'Default Store';

		$store_payload = array(
			'name'             => $store_name,
			'platform'         => is_plugin_active( 'woocommerce/woocommerce.php' ) ? 'woocommerce' : 'wordpress',
			'primary_language' => $primary_language,
			'site_url'         => get_bloginfo( 'url' ),
			'sector'           => Settings::get_sector(),
			'options'          => Store_Helpers::get_store_options(),
			'search_engines'   => $this->build_search_engines( $api_keys, $primary_language ),
			'plugin_version'   => Doofinder_For_WordPress::$version,
		);

		return $store_payload;
	}

	/**
	 * Builds an array of search engines configurations based on the provided API keys and primary language.
	 *
	 * This function creates a list of search engine configurations, each containing details such as
	 * the domain name, language, locale, currency, site URL, feed type, and callback URL.
	 *
	 * @param array  $api_keys         An array of API keys and associated language information used to configure search engines.
	 * @param string $primary_language The primary language code to use as a fallback if specific language codes are not available in the API keys.
	 *
	 * @return array An array of search engine configurations, each represented as an associative array.
	 */
	private function build_search_engines( $api_keys, $primary_language ) {
		$search_engines = array();
		$domain         = str_ireplace( 'www.', '', wp_parse_url( get_bloginfo( 'url' ), PHP_URL_HOST ) );
		$currency       = is_plugin_active( 'woocommerce/woocommerce.php' ) ? get_woocommerce_currency() : 'EUR';

		foreach ( $api_keys as $item ) {
			// Prioritize the locale code.
			$code = $item['lang']['locale'] ?? $item['lang']['code'] ?? $primary_language;
			$code = Helpers::format_locale_to_hyphen( $code );
			$lang = Helpers::get_language_from_locale( $code );

			$home_url = $this->language->get_home_url( $lang );

			// Prepare search engine body.
			$this->log->log( 'Wizard Step 2 - Prepare Search Engine body : ' );
			$search_engines[] = array(
				'name'         => $domain . ( $code ? ' (' . strtoupper( $code ) . ')' : '' ),
				'language'     => $code,
				'locale'       => $item['lang']['code'] ?? $primary_language,
				'currency'     => $currency,
				'site_url'     => $home_url,
				'feed_type'    => is_plugin_active( 'woocommerce/woocommerce.php' ) ? 'product' : 'posts',
				'callback_url' => $this->build_callback_url( $home_url, '/?rest_route=/doofinder/v1/index-status&token=' . $this->api_key ),
			);
		}

		return $search_engines;
	}

	/**
	 * This function returns the primary language in locale format: en-US,
	 * es-ES, etc.
	 *
	 * @return string Primary language.
	 */
	private function get_primary_language() {
		$primary_language = get_locale();
		if ( null !== $this->language->get_languages() ) {
			$primary_language = $this->language->get_base_locale();
		}
		$primary_language = Helpers::format_locale_to_hyphen( $primary_language );
		return $primary_language;
	}

	/**
	 * Constructs a full callback URL by combining a base URL with an endpoint path.
	 *
	 * This function parses the base URL, handles any existing query parameters, and appends the specified endpoint path.
	 * If both the base URL and endpoint path contain query parameters, they are merged into the final callback URL.
	 *
	 * @param string $base_url      The base URL to which the endpoint path will be appended.
	 * @param string $endpoint_path The endpoint path to append to the base URL.
	 *
	 * @return string The fully constructed callback URL with combined query parameters, if any.
	 */
	private function build_callback_url( $base_url, $endpoint_path ) {
		$parsed_url = wp_parse_url( $base_url );
		$parameters = null;
		if ( array_key_exists( 'query', $parsed_url ) ) {
			parse_str( $parsed_url['query'], $parameters );
		}

		$callback_url  = $parsed_url['scheme'] . '://' . $parsed_url['host'];
		$callback_url .= isset( $parsed_url['path'] ) ? rtrim( $parsed_url['path'], '/' ) : '';
		$callback_url .= '/' . ltrim( $endpoint_path, '/' );

		// Combine any existing parameters with any possible endpoint path parameters.
		if ( ! empty( $parameters ) ) {
			parse_str( wp_parse_url( $callback_url, PHP_URL_QUERY ), $endpoint_parameters );
			$combined_parameters = array_merge( $parameters, $endpoint_parameters );
			$callback_url        = strtok( $callback_url, '?' );
			$callback_url       .= '?' . http_build_query( $combined_parameters );
		}

		return $callback_url;
	}

	/**
	 * Throws an exception based on the response or response code.
	 *
	 * This function checks if the response is a WordPress error (`WP_Error`). If so, it throws an exception with the error message and code.
	 * If the response code indicates an HTTP error (anything below 200 or 400 and above), it retrieves the response message and throws an exception with the message and the response code.
	 *
	 * @param mixed $response      The response from a WordPress HTTP request. This could be a `WP_Error` object or a successful response array.
	 * @param int   $response_code The HTTP status code of the response.
	 *
	 * @throws Exception If the response is an error or the response code indicates a failure.
	 */
	private function throw_exception( $response, $response_code ) {

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			throw new Exception( wp_kses_data( $error_message ), (int) $response->get_error_code() );
		}

		if ( $response_code < WP_Http::OK || $response_code >= WP_Http::BAD_REQUEST ) {
			$error_message = wp_remote_retrieve_response_message( $response );
			throw new Exception( wp_kses_data( $error_message ), (int) $response_code );
		}
	}
}
