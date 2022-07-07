<?php

namespace Doofinder\WC;

use Doofinder\WC\Helpers\Template_Engine;

/**
 * @var Setup_Wizard $this 
 */

// Reset wizard step in DB if GET step is set to 1
if (isset($_GET['step']) && (int) $_GET['step'] === 1) {
    update_option(Setup_Wizard::$wizard_step_option, 1);
}
// Get current wizard step to show/process either from GET or DB
$step_state = isset($_GET['step']) ? (int) $_GET['step'] : ($this->get_step() ?: 1);


$this->process_wizard_step($step_state);

if (Doofinder_For_WooCommerce::$disable_api_calls) {
    Index_Interface::render_html_api_disabled_notice();
}

if (Data_Index::$should_fail || Setup_Wizard::$should_fail) {
    Setup_Wizard::render_html_should_fail_notice();
}

?>

<div class="dfwc-setup-steps <?php echo $step_state < $this::$no_steps ? 'active' : ''; ?>">
    <?php

    // Render step 1
    Template_Engine::get_template(
        'wizard-step',
        [
            'step' => 1,
            'step_state' => $step_state,
            'title' => __('Select your sector', 'woocommerce-doofinder'),
            'desc' => __("Please select your business sector", 'woocommerce-doofinder')
        ]
    ); // Render step 2

    Template_Engine::get_template(
        'wizard-step',
        [
            'step' => 2,
            'step_state' => $step_state,
            'title' => __('Connect with Doofinder', 'woocommerce-doofinder'),
            'desc' => __("If you don't have a Doofinder account a new one will be created", 'woocommerce-doofinder')
        ]
    );

    // Render step 3
    Template_Engine::get_template(
        'wizard-step',
        [
            'step' => 3,
            'setup_wizard' => $this,
            'step_state' => $step_state,
            'title' => __('Index your products', 'woocommerce-doofinder'),
            'desc' => __("We will send data to Doofinder for search optimization", 'woocommerce-doofinder')
        ]
    );

    // Render step 4
    Template_Engine::get_template(
        'wizard-step',
        [
            'step' => 4,
            'setup_wizard' => $this,
            'step_state' => $step_state,
            'title' => __('Enable Doofinder', 'woocommerce-doofinder'),
            'desc' => __("Replace the default search by the Doofinder search service", 'woocommerce-doofinder')
        ]
    );
    ?>
</div>
<?php
// Render (final) step 5
Template_Engine::get_template(
    'wizard-step-5',
    [
        'step_state' => $step_state,
        'no_steps' => $this::$no_steps
    ]
);
