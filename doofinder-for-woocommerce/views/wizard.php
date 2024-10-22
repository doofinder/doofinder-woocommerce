<?php
/**
 * DooFinder Store Wizard.
 *
 * @package Doofinder\WP\Setup_Wizard
 */

namespace Doofinder\WP;

use Doofinder\WP\Settings;

/**
 * What is $this in this context?
 *
 * @var Setup_Wizard $this The Setup Wizard object.
 */

$scripts = wp_scripts();
$styles  = wp_styles();

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta name="viewport" content="width=device-width" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<?php /* translators: %s is replaced with the name of the WordPress site. */ ?>
	<title><?php echo esc_html( sprintf( __( '%s - Doofinder Setup Wizard', 'wordpress-doofinder' ), get_bloginfo( 'name' ) ) ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( $styles->base_url . $styles->registered['common']->src ); ?>"><?php // phpcs:ignore WordPress.WP.EnqueuedResources ?>
	<link rel="stylesheet" href="<?php echo esc_url( Doofinder_For_WordPress::plugin_url() ); ?>assets/css/admin.css"><?php // phpcs:ignore WordPress.WP.EnqueuedResources ?>
	<link rel="stylesheet" href="<?php echo esc_url( Doofinder_For_WordPress::plugin_url() ); ?>assets/css/wizard.css"><?php // phpcs:ignore WordPress.WP.EnqueuedResources ?>

</head>

<body class="df-setup">
	<main>
		<h1><?php esc_html_e( 'Doofinder Setup Wizard', 'wordpress-doofinder' ); ?></h1>

		<div class="df-setup-content box">
			<?php
			$this->render_wizard_step();
			?>
		</div>
		<div class="df-setup-modal-wrapper"></div>

		<a href="<?php echo esc_url( Settings::get_url() ); ?>" class="df-setup-skip df-setup-skip-main"><?php esc_html_e( 'Skip and exit setup', 'wordpress-doofinder' ); ?></a>
	</main>
	<script>
		const doofinderCurrentLanguage = '';
		const Doofinder = 
		<?php
		echo wp_json_encode(
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'doofinder_set_connection_data' ),
			)
		);
		?>
		;
		const doofinderConnectEmail = '<?php echo esc_attr( get_bloginfo( 'admin_email' ) ); ?>';
		<?php
		$token = $this->generate_token();
		$this->save_token( $token );
		?>
		const doofinderConnectToken = '<?php echo esc_attr( $token ); ?>';
		const doofinderConnectReturnPath = '<?php echo esc_url( $this->get_return_path() ); ?>';
		const doofinderAdminPath = '<?php echo esc_url( Settings::get_api_host() ); ?>';
		const doofinderSetupWizardUrl = '<?php echo esc_url( $this->get_url() ); ?>';
	</script>
	<script src="<?php echo esc_url( $scripts->base_url . $scripts->registered['jquery-core']->src ); ?>"></script><?php // phpcs:ignore WordPress.WP.EnqueuedResources ?>
	<script src="<?php echo esc_url( Doofinder_For_WordPress::plugin_url() ); ?>assets/js/admin.js"></script><?php // phpcs:ignore WordPress.WP.EnqueuedResources ?>
	<script src="<?php echo esc_url( Doofinder_For_WordPress::plugin_url() ); ?>assets/js/wizard.js"></script><?php // phpcs:ignore WordPress.WP.EnqueuedResources ?>
</body>

</html>
