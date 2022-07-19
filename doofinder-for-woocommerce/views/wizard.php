<?php

namespace Doofinder\WC;

use Doofinder\WC\Settings\Settings;

/**
 * @var Setup_Wizard $this
 */

$wp_scripts = wp_scripts();
$wp_styles = wp_styles();

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta name="viewport" content="width=device-width" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title><?php printf(__('%s - Doofinder Setup Wizard', 'woocommerce-doofinder'), get_bloginfo('name')); ?></title>
    <link rel="stylesheet" href="<?php echo $wp_styles->base_url . $wp_styles->registered['common']->src; ?>">
    <link rel="stylesheet" href="<?php echo Doofinder_For_WooCommerce::plugin_url(); ?>assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo Doofinder_For_WooCommerce::plugin_url(); ?>assets/css/wizard.css">

</head>

<body class="dfwc-setup">
    <main>
        <h1><?php _e('Doofinder Setup Wizard', 'woocommerce-doofinder'); ?></h1>

        <div class="dfwc-setup-content box">
            <?php
            $this->render_wizard_step();
            ?>
        </div>
        <div class="dfwc-setup-modal-wrapper"></div>

        <a href="<?php echo Settings::get_url(); ?>" class="dfwc-setup-skip dfwc-setup-skip-main"><?php _e('Skip and exit setup', 'woocommerce-doofinder'); ?></a>
    </main>
    <script>
        const doofinderCurrentLanguage = '';
        const DoofinderForWC = <?php echo json_encode(['ajaxUrl' => admin_url('admin-ajax.php')]); ?>;
        const createSearchEnginesBeforeIndexing = true;
        const doofinderConnectEmail = '<?php echo get_bloginfo('admin_email'); ?>';
        <?php
        $token = $this->generateToken();
        $this->saveToken($token);
        ?>
        const doofinderConnectToken = '<?php echo $token; ?>';
        const doofinderConnectReturnPath = '<?php echo $this->getReturnPath(); ?>';
        const doofinderAdminPath = '<?php echo $this->getAdminPath(); ?>';
        const doofinderSetupWizardUrl = '<?php echo $this->get_url(); ?>';
    </script>
    <script src="<?php echo $wp_scripts->base_url . $wp_scripts->registered['jquery-core']->src; ?>"></script>
    <script src="<?php echo Doofinder_For_WooCommerce::plugin_url(); ?>assets/js/admin.js"></script>
    <script src="<?php echo Doofinder_For_WooCommerce::plugin_url(); ?>assets/js/wizard.js"></script>
</body>

</html>