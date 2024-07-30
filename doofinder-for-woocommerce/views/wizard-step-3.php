<?php
/**
 * Final step of the wizard
 *
 * @package Doofinder\WP\Setup_Wizard
 */

namespace Doofinder\WP;

?>
<div class="df-setup-finished <?php echo $step_state >= $no_steps ? 'active' : ''; ?>">
	<figure class="df-setup-finished__icon">🏆</figure>
	<h2 class="df-setup-finished__title"><?php esc_html_e( 'Congrats!', 'wordpress-doofinder' ); ?></h2>
	<h4 class="df-setup-finished__desc"><?php esc_html_e( 'Your store has been optimized with the best search experience', 'wordpress-doofinder' ); ?></h4>
	<input type="hidden" name="process-step" value="3" />
	<a class="button button-primary" href="/wp-admin/admin.php?page=doofinder_for_wp"><?php esc_html_e( 'Close', 'wordpress-doofinder' ); ?></a>
</div>
