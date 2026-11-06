<?php
/**
 * DooFinder Sales_From_Platform methods.
 *
 * @package Doofinder\WP\Sales_From_Platform
 */

namespace Doofinder\WP;

/**
 * Sends the orders to Doofinder when they are paid, so they are registered as sales from the
 * server instead of from the order received page.
 *
 * The Doofinder session lives in the browser's localStorage, so the storefront copies it to a
 * cookie that is saved in the order as hidden meta when it is placed, from the classic checkout
 * or the checkout block. The sale is sent in the background with the Action Scheduler, like
 * WooCommerce's own webhooks, and sent again later if Doofinder doesn't take it.
 */
class Sales_From_Platform {

	const COOKIE        = 'doofinder_session';
	const META_KEY      = '_doofinder_session';
	const SENT_META_KEY = '_doofinder_sale_sent';
	const ACTION        = 'doofinder_send_sale';

	/**
	 * Seconds to wait before each new attempt: 5 minutes, 30 minutes, 2 hours and 12 hours.
	 */
	const RETRY_DELAYS = array( 300, 1800, 7200, 43200 );

	/**
	 * Registers the hooks that take the Doofinder session to the order and send it when paid.
	 */
	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'output_session_script' ) );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_session' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'save_session' ) );

		// The statuses WooCommerce takes as paid. Orders waiting for a bank transfer or a cheque stay
		// on hold until the merchant marks them as paid; cash on delivery ones are paid when placed.
		foreach ( wc_get_is_paid_statuses() as $status ) {
			add_action( "woocommerce_order_status_$status", array( __CLASS__, 'queue_sale' ) );
		}

		add_action( self::ACTION, array( __CLASS__, 'send_sale' ), 10, 2 );
	}

	/**
	 * Copies the Doofinder session to a cookie. It runs on load and again on every click and form
	 * submission, as the first page the loader runs in creates the session after this script.
	 */
	public static function output_session_script() {
		?>
		<script>
			(function () {
				function writeSession() {
					var session = JSON.parse(localStorage.getItem("df-session"));
					var userId = JSON.parse(localStorage.getItem("df-random-userid"));
					if (!session || !userId) return;

					var config = window.Doofinder && window.Doofinder.getConfig ? window.Doofinder.getConfig() : {};
					var value = JSON.stringify({ session_id: session.id, user_id: userId, hashid: config.hashid });
					document.cookie = "<?php echo esc_js( self::COOKIE ); ?>=" + encodeURIComponent(value) +
						"; path=/; max-age=86400; SameSite=Lax" + (location.protocol === "https:" ? "; Secure" : "");
				}

				writeSession();
				document.addEventListener("click", writeSession, true);
				document.addEventListener("submit", writeSession, true);
			})();
		</script>
		<?php
	}

	/**
	 * Saves the Doofinder session of the cookie in the order being placed.
	 *
	 * @param \WC_Order $order The order being placed.
	 */
	public static function save_session( $order ) {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return;
		}

		$cookie  = json_decode( wp_unslash( $_COOKIE[ self::COOKIE ] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitized below.
		$session = array();
		foreach ( array( 'session_id', 'user_id', 'hashid' ) as $key ) {
			if ( isset( $cookie[ $key ] ) && is_string( $cookie[ $key ] ) ) {
				$session[ $key ] = sanitize_text_field( $cookie[ $key ] );
			}
		}

		if ( isset( $session['session_id'], $session['user_id'] ) ) {
			$order->update_meta_data( self::META_KEY, $session );
		}
	}

	/**
	 * Queues the sale of an order that has just been paid, to be sent in the background.
	 *
	 * @param int $order_id The order id.
	 */
	public static function queue_sale( $order_id ) {
		as_enqueue_async_action( self::ACTION, array( $order_id, 1 ), 'doofinder' );
	}

	/**
	 * Sends the sale of an order to Doofinder, only once, though the order goes from one paid status
	 * to the other. Doofinder registers it only in the stores it gets the sales from the server for,
	 * so the rest keep sending them from the browser. If Doofinder doesn't take it, it is sent again
	 * later, up to the attempts in RETRY_DELAYS.
	 *
	 * Doofinder gets only what the sale needs: no customer data.
	 *
	 * @param int $order_id The order id.
	 * @param int $attempt  The number of this attempt, from 1.
	 */
	public static function send_sale( $order_id, $attempt ) {
		$order = wc_get_order( $order_id );
		if ( $order->get_meta( self::SENT_META_KEY ) ) {
			return;
		}

		// The subtotal of a line is its price before the coupons, as the cart shows it.
		$line_items = array();
		foreach ( $order->get_items() as $item ) {
			$line_items[] = array(
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'quantity'     => $item->get_quantity(),
				'subtotal'     => (float) $item->get_subtotal(),
				'subtotal_tax' => (float) $item->get_subtotal_tax(),
				'name'         => $item->get_name(),
			);
		}

		$sale = array(
			'order_id'        => $order->get_id(),
			'currency'        => $order->get_currency(),
			'user_agent'      => $order->get_customer_user_agent(),
			'session'         => $order->get_meta( self::META_KEY ) ? $order->get_meta( self::META_KEY ) : null,
			// Whether the lines take their taxes, as the store displays its prices, like the feed.
			'prices_with_tax' => 'incl' === get_option( 'woocommerce_tax_display_shop', 'incl' ),
			'line_items'      => $line_items,
		);

		$response = wp_remote_post(
			Settings::get_dooplugins_host() . '/wordpress/' . Settings::get_search_engine_hash() . '/sales',
			array(
				'headers' => array(
					'Authorization' => 'Token ' . Settings::get_api_key(),
					'content-type'  => 'application/json',
				),
				'body'    => wp_json_encode( $sale ),
			)
		);

		if ( \WP_Http::OK === wp_remote_retrieve_response_code( $response ) ) {
			$order->update_meta_data( self::SENT_META_KEY, time() );
			$order->save_meta_data();
		} elseif ( $attempt <= count( self::RETRY_DELAYS ) ) {
			as_schedule_single_action( time() + self::RETRY_DELAYS[ $attempt - 1 ], self::ACTION, array( $order_id, $attempt + 1 ), 'doofinder' );
		}
	}
}
