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
</div>

<div class="errors-wrapper doofinder-for-wc-indexing-error" style="display:none">
    <h3 style="color: #cc0000;"><?php _e('An error ocurred while connecting with doofinder', 'woocommerce-doofinder'); ?> :</h3>
</div>