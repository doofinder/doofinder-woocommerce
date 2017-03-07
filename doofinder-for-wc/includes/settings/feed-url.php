<?php

namespace Doofinder\WC\Settings;

$protect = Settings::get( 'feed', 'password_protected' );
$password = Settings::get( 'feed', 'password' );
$feed_link = get_feed_link( 'doofinder' );

if ( 'yes' === $protect ) {
	$feed_link = add_query_arg( 'secret', $password, $feed_link );
}

?>

<div style="margin-top: 10px;">
	<span class="description"><?php _e( 'Feed URL', 'woocommerce-doofinder' ); ?></span>
	<input type="text" readonly="readonly" value="<?php echo $feed_link; ?>" style="width: 100%;">
</div>
