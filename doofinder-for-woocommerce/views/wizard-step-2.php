<?php
/**
 * 2nd step of the wizard
 *
 * @package Doofinder\WP\Setup_Wizard
 */

namespace Doofinder\WP;

$df_error = $df_error ?? false;

use Doofinder\WP\Setup_Wizard;

/**
 * What is $this in this context?
 *
 * @var Setup_Wizard $this The Setup Wizard object.
 */
?>

<div class="df-setup-step__actions">
	<a class="button button-primary open-window" data-type="login" href="#"><?php esc_html_e( 'Log in', 'wordpress-doofinder' ); ?></a>
	<a class="button button-primary open-window" data-type="signup" href="#"><?php esc_html_e( 'Sign up', 'wordpress-doofinder' ); ?></a>
</div>

<div class="errors-wrapper doofinder-for-wc-indexing-error" style="display:none">
	<h3 style="color: #cc0000;"><?php esc_html_e( 'An error ocurred while connecting with doofinder', 'wordpress-doofinder' ); ?> :</h3>
</div>
