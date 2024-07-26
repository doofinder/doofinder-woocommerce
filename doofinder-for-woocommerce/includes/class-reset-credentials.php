<?php

namespace Doofinder\WP;

use Doofinder\WP\Reset_Credentials_Index;
use WP_Http;

class Reset_Credentials {

	public static function init() {
		$class = __CLASS__;
		add_action( 'doofinder_reset_credentials', array( $class, 'launch_reset_credentials' ), 10, 0 );

		add_action(
			'wp_ajax_doofinder_reset_credentials',
			function () {
				if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['nonce'], 'doofinder-ajax-nonce' ) ) {
					status_header( WP_Http::UNAUTHORIZED );
					die( 'Unauthorized request' );
				}
				do_action( 'doofinder_reset_credentials' );
				wp_send_json_success();
			}
		);
	}

	public static function launch_reset_credentials() {
		$reset_credentials_context = new Reset_Credentials_Index();
		$reset_credentials_context->reset_credentials();
	}

	/**
	 * Get reset credentials button html
	 *
	 * @param bool $settings
	 *
	 * @return string
	 */
	public static function get_configure_via_reset_credentials_button_html() {

		$html = '';

		ob_start();

		?>
		<p class="doofinder-button-reset-credentials" style="left: 10px;float:right;position:relative;top:-68px;">
			<a id="doofinder-reset-credentials" href="#" class="button-secondary"><?php _e( 'Reset Credentials', 'wordpress-doofinder' ); ?></a>
		</p>
		<?php

		$html = ob_get_clean();

		// endif;

		return $html;
	}
}
