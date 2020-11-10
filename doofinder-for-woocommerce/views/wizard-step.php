<?php 
use Doofinder\WC\Setup_Wizard;

$wizard = Setup_Wizard::instance();

$step = isset($step) ? $step : 1;
$active = isset($active) ? $active : false;
$active = $step === $step_state ? true : false;
$finished = $step_state > $step ? true : false;
$error = $wizard->get_errors_html("wizard-step-$step");
?>
<div class="dfwc-setup-step dfwc-setup-step-connect <?php echo $active ? 'active' : ''; ?> <?php echo $finished ? 'finished' : ''; ?>">
    <span class="dfwc-setup-step__number"><?php echo $step; ?></span>
    <div class="dfwc-setup-step__wrap">
        <div class="dfwc-setup-step__header">
            <?php if(isset($title)) : ?>
            <h3 class="dfwc-setup-step__title"><?php echo $title; ?></h3>
            <?php endif; ?>
            
            <?php if(isset($desc)) : ?>
            <p class="dfwc-setup-step__desc"><?php echo $desc; ?></p>
            <?php endif; ?>
        </div>
        <div class="dfwc-setup-step__content">
            <?php include "wizard-step-$step.php"; ?>
            
            <?php if ( $error ): ?>
                <?php echo $error; ?>
            <?php endif; ?>
        </div>
    </div>
</div>