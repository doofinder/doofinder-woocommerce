<?php
/**
 * DooFinder Reset_Credentials methods.
 *
 * @package Doofinder\WP\Credentials
 */

namespace Doofinder\WP;

use Doofinder\WP\Reset_Credentials_Index;
use WP_Http;

/**
 * Reset_Credentials Class.
 */
class Reset_Credentials {

	/**
	 * Initializes the action hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$class = __CLASS__;
		add_action( 'doofinder_reset_credentials', array( $class, 'launch_reset_credentials' ), 10, 0 );

		add_action(
			'wp_ajax_doofinder_reset_credentials',
			function () {
				if ( ! isset( $_POST['nonce'] ) || ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'doofinder-ajax-nonce' ) ) {
					status_header( WP_Http::UNAUTHORIZED );
					die( 'Unauthorized request' );
				}
				/**
				 * Hook for doofinder_reset_credentials action.
				 *
				 * @since 1.0
				 */
				do_action( 'doofinder_reset_credentials' );
				wp_send_json_success();
			}
		);
	}

	/**
	 * Launches the reset credentials action.
	 *
	 * @return void
	 */
	public static function launch_reset_credentials() {
		$reset_credentials_context = new Reset_Credentials_Index();
		$reset_credentials_context->reset_credentials();
	}

	/**
	 * Gets reset credentials button HTML.
	 *
	 * @return string
	 */
	public static function get_configure_via_reset_credentials_button_html() {

		$html = '';

		ob_start();

		?>
		<p class="doofinder-button-reset-credentials" style="left: 10px;float:right;position:relative;top:-68px;">
			<a id="doofinder-reset-credentials" href="#" class="button-secondary"><?php esc_html_e( 'Reset Credentials', 'wordpress-doofinder' ); ?></a>
		</p>
		<?php

		$html = ob_get_clean();

		return $html;
	}
}
