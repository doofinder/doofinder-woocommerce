<?php
/**
 * DooFinder Admin_Notices methods.
 *
 * @package Doofinder\WP\Admin_Notices
 */

namespace Doofinder\WP;

/**
 * Handles the Admin Notices.
 */
class Admin_Notices {

	/**
	 * Add the admin_notices action with our custom notices.
	 *
	 * @return void
	 */
	public static function init() {
		add_action(
			'admin_notices',
			function () {
				$notices = self::get_custom_notices();
				if ( ! empty( $notices ) ) {
					foreach ( $notices as $notice_id => $notice ) {
						if ( isset( $notice['is_custom'] ) && $notice['is_custom'] ) {
							self::render_custom_notice( $notice['html'] );
						} else {
							self::render_notice( $notice_id, $notice );
						}
					}
				}
			}
		);
	}

	/**
	 * Adds the given notice as a show once notice.
	 * It will be automatically disabled after being shown.
	 *
	 * @param string      $notice_id The unique notice ID.
	 * @param string      $title Title of the notice.
	 * @param string      $message Message of the notice. Only a limited set of HTML tags are allowed (inline ones).
	 * @param string      $type Notice type. Defaults to `info`.
	 * @param string|null $extra Extra data. Defaults to `null`.
	 * @param string      $classes Extra CSS classes to apply. Defaults to `''`.
	 * @param bool        $dismissible Whether the notice can be closed or not. Defaults to `false`.
	 *
	 * @return void
	 */
	public static function add_notice( $notice_id, $title, $message, $type = 'info', $extra = null, $classes = '', $dismissible = false ) {
		$current_notices               = self::get_custom_notices();
		$current_notices[ $notice_id ] = array(
			'type'        => $type,
			'title'       => $title,
			'message'     => $message,
			'extra'       => $extra,
			'classes'     => $classes,
			'dismissible' => $dismissible,
		);
		update_option( 'doofinder_for_wp_notices', $current_notices );
	}

	/**
	 * Adds the given custom notice as a show once notice.
	 * It will be automatically disabled after being shown.
	 *
	 * @param string $notice_id The unique notice ID.
	 * @param string $html HTML of the custom notice.
	 *
	 * @return void
	 */
	public static function add_custom_notice( $notice_id, $html ) {
		$current_notices               = self::get_custom_notices();
		$current_notices[ $notice_id ] = array(
			'is_custom' => true,
			'html'      => $html,
		);
		update_option( 'doofinder_for_wp_notices', $current_notices );
	}

	/**
	 * Removes the given custom notice by its ID.
	 *
	 * @param string $notice_id The unique notice ID.
	 *
	 * @return void
	 */
	public static function remove_notice( $notice_id ) {
		if ( ! self::is_notice_active( $notice_id ) ) {
			return;
		}

		$current_notices = self::get_custom_notices();
		if ( array_key_exists( $notice_id, $current_notices ) ) {
			unset( $current_notices[ $notice_id ] );
			update_option( 'doofinder_for_wp_notices', $current_notices );
		}
	}

	/**
	 * Retrieves all the generated notices.
	 *
	 * @return array
	 */
	public static function get_custom_notices() {
		return get_option( 'doofinder_for_wp_notices', array() );
	}

	/**
	 * Renders the notice HTML.
	 *
	 * @param string $notice_id The unique notice ID.
	 * @param array  $notice Notice data as associative array. Go to `add_notice()` function to see all the possible keys.
	 *
	 * @return void
	 */
	public static function render_notice( $notice_id, $notice ) {

		$classes = 'wordpress-message df-notice migration-complete ' . $notice['classes']
		?>
		<div id="<?php echo esc_attr( $notice_id ); ?>" class="notice doofinder notice-<?php echo esc_attr( $notice['type'] ); ?> <?php echo ( $notice['dismissible'] ) ? 'is-dismissible' : ''; ?>">
			<div class="<?php echo esc_attr( $classes ); ?>">
				<div class="df-notice-row">
					<div class="df-notice-col logo">
						<figure class="logo" style="width:5rem;height:auto;float:left;margin:.5em 0;margin-right:0.75rem;">
							<img src="<?php echo esc_url( Doofinder_For_WordPress::plugin_url() ); ?>assets/svg/imagotipo1.svg" />
						</figure>
					</div>
					<div class="df-notice-col content">
						<?php
						if ( ! empty( $notice['title'] ) ) :
							?>
							<h3><?php echo esc_html( $notice['title'] ); ?></h3>
							<?php
						endif;
						?>
						<p>
							<?php echo wp_kses_post( $notice['message'] ); ?>
						</p>
					</div>
					<?php
					if ( ! empty( $notice['extra'] ) ) :
						?>
						<div class="df-notice-col extra align-center">
							<?php echo wp_kses_data( $notice['extra'] ); ?>
						</div>
						<?php
					endif;
					?>
				</div>
			</div>
		</div>
		<?php
		self::notice_shown( $notice_id );
	}

	/**
	 * Renders the custom notice HTML.
	 *
	 * @param string $notice_html Notice HTML string.
	 *
	 * @return void
	 */
	public static function render_custom_notice( $notice_html ) {
		echo wp_kses_post( $notice_html );
	}

	/**
	 * Checks if the notice is active by its Notice ID.
	 *
	 * @param string $notice_id The unique notice ID.
	 *
	 * @return bool
	 */
	public static function is_notice_active( $notice_id ) {
		$current_notices = self::get_custom_notices();
		return array_key_exists( $notice_id, $current_notices );
	}

	/**
	 * Sets the given notice as a show once notice.
	 * It will be automatically disabled after being shown.
	 *
	 * @param string $notice_id The unique notice ID.
	 *
	 * @return void
	 */
	public static function set_show_once( $notice_id ) {
		if ( self::is_notice_active( $notice_id ) ) {
			$show_once_notices   = get_option( 'doofinder_for_wp_show_once_notices', array() );
			$show_once_notices[] = $notice_id;
			update_option( 'doofinder_for_wp_show_once_notices', $show_once_notices );
		}
	}

	/**
	 * If the notice was configured as show_once, it will remove the notice
	 *
	 * @param string $notice_id The notice unique identifier.
	 *
	 * @return void
	 */
	public static function notice_shown( $notice_id ) {
		$show_once_notices = get_option( 'doofinder_for_wp_show_once_notices', array() );
		if ( in_array( $notice_id, $show_once_notices, true ) ) {
			// Remove notice as it was shown.
			self::remove_notice( $notice_id );
		}
	}
}
