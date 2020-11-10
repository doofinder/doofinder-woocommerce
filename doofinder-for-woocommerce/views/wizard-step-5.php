<?php

/**
 * Final step of the wizard
 */

namespace Doofinder\WC;

use Doofinder\WC\Setup_Wizard;

?>
<form action="<?php echo Setup_Wizard::get_url(['step' => '5']); ?>" method="post" class="dfwc-setup-finished <?php echo $step_state >= $no_steps ? 'active' : ''; ?>">
    <figure class="dfwc-setup-finished__icon">🏆</figure>
    <h2 class="dfwc-setup-finished__title"><?php _e('Congrats!', 'woocommerce-doofinder'); ?></h2>
    <h4 class="dfwc-setup-finished__desc"><?php _e('Your store has been optimized with the best search experience', 'woocommerce-doofinder'); ?></h4>
    <input type="hidden" name="process-step" value="5" />
    <button type="submit" class="button button-primary"><?php _e('Close', 'woocommerce-doofinder'); ?></button>
</form>