<?php

namespace Doofinder\WC;

$error = $error ?? false;

use Doofinder\WC\Settings\Settings;
use Doofinder\WC\Setup_Wizard;

/** @var Setup_Wizard $this */
?>

<div class="dfwc-setup-step__actions">
    <a class="button button-primary open-window" data-type="login" href="#"><?php _e('Log in', 'woocommerce-doofinder'); ?></a>
    <a class="button button-primary open-window" data-type="signup" href="#"><?php _e('Sign up', 'woocommerce-doofinder'); ?></a>
    <a href="<?php echo Settings::get_url(); ?>" class="dfwc-setup-skip"><?php _e('Skip and exit setup','woocommerce-doofinder'); ?></a>
</div>
