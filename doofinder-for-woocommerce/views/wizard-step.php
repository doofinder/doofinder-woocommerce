<?php 
use Doofinder\WP\Setup_Wizard;
use Doofinder\WP\Settings;

$wizard = Setup_Wizard::instance();

$step = isset($step) ? $step : 1;
$active = isset($active) ? $active : false;
$active = $step === $step_state;
$finished = $step_state > $step;
$error = $wizard->get_errors_html("wizard-step-$step");
?>
<div class="df-setup-step df-setup-step-connect <?php echo $active ? 'active' : ''; ?> <?php echo !empty($error) ? 'has-error' : ''; ?> <?php echo $finished ? 'finished' : ''; ?>">
    <span class="df-setup-step__number"><?php echo $step; ?></span>
    <div class="df-setup-step__wrap">
        <div class="df-setup-step__header">
            <?php if(isset($title)) : ?>
            <h3 class="df-setup-step__title"><?php echo $title; ?></h3>
            <?php endif; ?>
            
            <?php if(isset($desc)) : ?>
            <p class="df-setup-step__desc"><?php echo $desc; ?></p>
            <?php endif; ?>
        </div>
        <div class="df-setup-step__content">
            <?php include "wizard-step-$step.php"; ?>
            
            <?php if ( $error ): ?>
                <?php echo $error; ?>
                <a href="<?php echo Settings::get_url('reset-wizard=1'); ?>" class="button button-primary button-error"><?php _e('Exit setup','wordpress-doofinder'); ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>
