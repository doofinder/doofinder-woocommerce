<?php
/**
 * DooFinder Index_Status_Handler methods.
 *
 * @package Indexing
 */

namespace Doofinder\WP;

use WP_REST_Response;
use WP_REST_Request;
use Doofinder\WP\Multilanguage\Multilanguage;
use WP_Http;

/**
 * Class Index_Status_Handler
 *
 * Class to check the indexing status.
 */
class Index_Status_Handler {

	const NAMESPACE        = 'doofinder';
	const API_VERSION      = 1;
	const INDEXING_TIMEOUT = 12 * HOUR_IN_SECONDS;

	/**
	 * To log anything.
	 *
	 * @var Log
	 */
	private static $logger;

	/**
	 * Registers the index-status REST ROUTE.
	 *
	 * @return void
	 */
	public static function initialize() {
		self::$logger   = new Log( 'store_create_index_status.log' );
		$namespace_path = self::NAMESPACE . '/v' . self::API_VERSION;
		register_rest_route(
			$namespace_path,
			'/index-status',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'index_status' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Checks the index status as the callback of the REST API declared endpoint via `initialize()`.
	 *
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function index_status( WP_REST_Request $request ) {
		self::$logger->log( "Received indexing status request with payload:\n" . print_r( $request, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$valid_message = 'Sources were processed successfully.';
		if ( $request->get_param( 'token' ) !== Settings::get_api_key() ) {
			return new WP_REST_Response(
				array(
					'status'   => WP_Http::UNAUTHORIZED,
					'response' => 'Invalid token',
				),
				WP_Http::UNAUTHORIZED
			);
		}

		$error_message = $request->get_param( 'message' );
		if ( ! empty( $error_message ) && $error_message !== $valid_message ) {
			$notice_title   = __( 'An error has occurred while indexing your catalog', 'wordpress-doofinder' );
			$notice_content = __( 'To obtain further details, you can check the indexing results by accessing the "Indices" section in your Doofinder admin panel. If the problem persists, please contact our support team at <a href="mailto:support@doofinder.com">support@doofinder.com</a>', 'wordpress-doofinder' );
			// Dismiss the indexing notice as it has already finished.
			Setup_Wizard::dismiss_indexing_notice();
			Admin_Notices::add_notice( 'indexing-status-failed', $notice_title, $notice_content, 'error', null, '', true );

			return new WP_REST_Response(
				array(
					'status'          => WP_Http::OK,
					'indexing_status' => 'failed',
					'response'        => $request->get_param( 'message' ),
				)
			);
		}

		$multilanguage = Multilanguage::instance();
		$lang          = ( $multilanguage->get_current_language() === $multilanguage->get_base_language() ) ? '' : $multilanguage->get_current_language();
		// Hide the indexing notice.
		Setup_Wizard::dismiss_indexing_notice();
		Settings::set_indexing_status( 'processed', $lang );
		// Enable JS Layer for the indexed language.
		Settings::enable_js_layer( $lang );

		return new WP_REST_Response(
			array(
				'status'          => WP_Http::OK,
				'indexing_status' => Settings::get_indexing_status( $lang ),
				'response'        => 'Indexing status updated',
			)
		);
	}

	/**
	 * Marks the start of the indexation process.
	 *
	 * This function sets a transient flag to indicate that the indexation process has started.
	 * The transient has a predefined expiration time.
	 *
	 * @param string $lang The language code for the indexation.
	 *
	 * @return void
	 */
	public static function indexing_started( $lang = '' ) {
		$lang_suffix = empty( $lang ) ? '' : "_$lang";
		set_transient( "doofinder_indexing$lang_suffix", true, self::INDEXING_TIMEOUT );
	}

	/**
	 * Checks if the indexation has timed out.
	 *
	 * This function verifies if the indexation process has exceeded the set timeout.
	 * When indexation begins, a transient with the timeout as the expiration date is created.
	 * If the transient returns false, it indicates that the indexation has timed out.
	 *
	 * @param string $lang The language code of the indexation.
	 * @return boolean True if the indexing has timed out, False if it's still within the time limit.
	 */
	public static function is_indexing_status_timed_out( $lang = '' ) {
		$lang_suffix = empty( $lang ) ? '' : "_$lang";
		$timed_out   = ! get_transient( "doofinder_indexing$lang_suffix" );
		return $timed_out;
	}
}
