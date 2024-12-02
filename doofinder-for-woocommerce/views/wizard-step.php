<?php
/**
 * DooFinder Store Wizard step.
 *
 * @package Doofinder\WP\Setup_Wizard
 */

use Doofinder\WP\Setup_Wizard;
use Doofinder\WP\Settings;

$wizard = Setup_Wizard::instance();

$step       = ! empty( $data['step'] ) ? $data['step'] : 1;
$step_state = ! empty( $data['step_state'] ) ? $data['step_state'] : 1;
$active     = isset( $active ) ? $active : false;
$active     = $step === $step_state;
$finished   = $step_state > $step;
$df_error   = $wizard->get_errors_html( "wizard-step-$step" );
?>
<div class="df-setup-step df-setup-step-connect <?php echo $active ? 'active' : ''; ?> <?php echo ! empty( $df_error ) ? 'has-error' : ''; ?> <?php echo $finished ? 'finished' : ''; ?>">
	<span class="df-setup-step__number"><?php echo esc_html( $step ); ?></span>
	<div class="df-setup-step__wrap">
		<div class="df-setup-step__header">
			<?php if ( ! empty( $data['title'] ) ) : ?>
			<h3 class="df-setup-step__title"><?php echo esc_html( $data['title'] ); ?></h3>
			<?php endif; ?>
			
			<?php if ( ! empty( $data['desc'] ) ) : ?>
			<p class="df-setup-step__desc"><?php echo esc_html( $data['desc'] ); ?></p>
			<?php endif; ?>
		</div>
		<div class="df-setup-step__content">
			<?php require "wizard-step-$step.php"; ?>
			
			<?php if ( $df_error ) : ?>
				<?php echo wp_kses_post( $df_error ); ?>
				<a href="<?php echo esc_url( Settings::get_url( 'reset-wizard=1' ) ); ?>" class="button button-primary button-error"><?php esc_html_e( 'Exit setup', 'wordpress-doofinder' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</div>
