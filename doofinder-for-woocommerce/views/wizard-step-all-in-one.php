<?php
/**
 * DooFinder Store Wizard all steps in one.
 *
 * @package Doofinder\WP\Setup_Wizard
 */

namespace Doofinder\WP;

use Doofinder\WP\Helpers\Template_Engine;

/**
 * What is $this in this context?
 *
 * @var Setup_Wizard $this The Setup Wizard object.
 */

// Reset wizard step in DB if GET step is set to 1.
if ( isset( $_GET['step'] ) && 1 === (int) $_GET['step'] ) {
	update_option( Setup_Wizard::$wizard_step_option, 1 );
}
// Get current wizard step to show/process either from GET or DB.
$step_state = isset( $_GET['step'] ) ? (int) $_GET['step'] : ( $this->get_step() ?? 1 );


$this->process_wizard_step( $step_state );

?>

<div class="dfwc-setup-steps <?php echo $step_state < $this::$no_steps ? 'active' : ''; ?>">
	<?php

	// Render step 1.
	Template_Engine::get_template(
		'wizard-step',
		array(
			'step'       => 1,
			'step_state' => $step_state,
			'title'      => __( 'Select your sector', 'wordpress-doofinder' ),
			'desc'       => __( 'Please select your business sector', 'wordpress-doofinder' ),
		)
	); // Render step 2.

	Template_Engine::get_template(
		'wizard-step',
		array(
			'step'       => 2,
			'step_state' => $step_state,
			'title'      => __( 'Connect with Doofinder', 'wordpress-doofinder' ),
			'desc'       => __( "If you don't have a Doofinder account a new one will be created", 'wordpress-doofinder' ),
		)
	);
	?>
</div>
<?php
// Render (final) step 3.
Template_Engine::get_template(
	'wizard-step-3',
	array(
		'step_state' => $step_state,
		'no_steps'   => $this::$no_steps,
	)
);
