<?php
/**
 * View for Landings
 *
 * @package Doofinder\WP\Landing
 */

namespace Doofinder\WP;

use Doofinder\WP\Landing;

if ( ! class_exists( 'Doofinder\WP\Landing' ) ) {
	// The Landing class is not defined, it loads the WordPress core.
	// This means that it is accessed directly from the PHP file.
	$script_name = ( isset( $_SERVER['SCRIPT_FILENAME'] ) ) ? wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) : '';
	$parse_uri   = explode( 'wp-content', $script_name );
	require_once $parse_uri[0] . 'wp-load.php';
}

if ( is_plugin_active( 'doofinder-for-woocommerce/doofinder-for-woocommerce.php' ) ) {

	$landing = new Landing();

	$landing_slug = get_query_var( 'df-landing' );


	if ( false !== (bool) $landing_slug ) {
		$hashid = get_query_var( 'hashid' );
	} elseif ( isset( $_GET['slug'], $_GET['hashid'] ) ) {
		$landing->create_redirect( wp_unslash( $_GET['slug'] ), wp_unslash( $_GET['hashid'] ) );
	} else {
		echo '';
	}

	$landing_data = $landing->get_landing_info( $hashid, $landing_slug );

	if ( isset( $landing_data['data'] ) ) {
		$meta_title       = $landing_data['data']['meta_title'];
		$meta_description = $landing_data['data']['meta_description'];
		$index            = $landing_data['data']['index'];
		$landing->set_meta_data( $meta_title, $meta_description, $index );
	}

	echo $landing->get_landing_html( $landing_data, $landing_slug ); // phpcs:ignore WordPress.Security.EscapeOutput
} else {
	echo $landing->get_disabled_html(); // phpcs:ignore WordPress.Security.EscapeOutput
}
