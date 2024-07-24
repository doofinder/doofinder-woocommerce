<?php

/**
 * Final step of the wizard
 */

namespace Doofinder\WP;

use Doofinder\WP\Setup_Wizard;

?>
<div class="df-setup-finished <?php echo $step_state >= $no_steps ? 'active' : ''; ?>">
	<figure class="df-setup-finished__icon">🏆</figure>
	<h2 class="df-setup-finished__title"><?php _e( 'Congrats!', 'wordpress-doofinder' ); ?></h2>
	<h4 class="df-setup-finished__desc"><?php _e( 'Your store has been optimized with the best search experience', 'wordpress-doofinder' ); ?></h4>
	<input type="hidden" name="process-step" value="3" />
	<a class="button button-primary" href="/wp-admin/admin.php?page=doofinder_for_wp"><?php _e( 'Close', 'wordpress-doofinder' ); ?></a>
</div>
