<?php

namespace Doofinder\WP;

$df_error = $df_error ?? false;

use Doofinder\WP\Setup_Wizard;

/** @var Setup_Wizard $this */
?>

<div class="df-setup-step__actions">
	<a class="button button-primary open-window" data-type="login" href="#"><?php _e( 'Log in', 'wordpress-doofinder' ); ?></a>
	<a class="button button-primary open-window" data-type="signup" href="#"><?php _e( 'Sign up', 'wordpress-doofinder' ); ?></a>
</div>

<div class="errors-wrapper doofinder-for-wc-indexing-error" style="display:none">
	<h3 style="color: #cc0000;"><?php _e( 'An error ocurred while connecting with doofinder', 'wordpress-doofinder' ); ?> :</h3>
</div>
