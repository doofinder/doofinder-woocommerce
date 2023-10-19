<?php

namespace Doofinder\WP;

use Doofinder\WP\Settings;

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
    <title><?php printf(__('%s - Doofinder Setup Wizard', 'wordpress-doofinder'), get_bloginfo('name')); ?></title>
    <link rel="stylesheet" href="<?php echo $wp_styles->base_url . $wp_styles->registered['common']->src; ?>">
    <link rel="stylesheet" href="<?php echo Doofinder_For_WordPress::plugin_url(); ?>assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo Doofinder_For_WordPress::plugin_url(); ?>assets/css/wizard.css">

</head>

<body class="df-setup">
    <main>
        <h1><?php _e('Doofinder Setup Wizard', 'wordpress-doofinder'); ?></h1>

        <div class="df-setup-content box">
            <?php
            $this->render_wizard_step();
            ?>
        </div>
        <div class="df-setup-modal-wrapper"></div>

        <a href="<?php echo Settings::get_url(); ?>" class="df-setup-skip df-setup-skip-main"><?php _e('Skip and exit setup', 'wordpress-doofinder'); ?></a>
    </main>
    <script>
        const doofinderCurrentLanguage = '';
        const Doofinder = <?php echo json_encode(['ajaxUrl' => admin_url('admin-ajax.php')]); ?>;
        const doofinderConnectEmail = '<?php echo get_bloginfo('admin_email'); ?>';
        <?php
        $token = $this->generateToken();
        $this->saveToken($token);
        ?>
        const doofinderConnectToken = '<?php echo $token; ?>';
        const doofinderConnectReturnPath = '<?php echo $this->getReturnPath(); ?>';
        const doofinderAdminPath = '<?php echo Settings::get_api_host(); ?>';
        // const doofinderAdminPath = 'http://edu-doomanager.ngrok.doofinder.com';
        const doofinderSetupWizardUrl = '<?php echo $this->get_url(); ?>';
    </script>
    <script src="<?php echo $wp_scripts->base_url . $wp_scripts->registered['jquery-core']->src; ?>"></script>
    <script src="<?php echo Doofinder_For_WordPress::plugin_url(); ?>assets/js/admin.js"></script>
    <script src="<?php echo Doofinder_For_WordPress::plugin_url(); ?>assets/js/wizard.js"></script>
</body>

</html>
