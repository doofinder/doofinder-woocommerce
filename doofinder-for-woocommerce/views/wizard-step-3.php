<?php

namespace Doofinder\WC;

use Doofinder\WC\Settings\Settings;
use Doofinder\WC\Setup_Wizard;
use Doofinder\WC\Index_Interface;


$index_interface = Index_Interface::instance();
?>
<form action="<?php echo Setup_Wizard::get_url(['step' => '3']); ?>" method="post">
	<div class="dfwc-setup-step__actions">
		<button type="button" id="doofinder-for-wc-index-button" class="button button-primary" style="display:none;"><?php _e('Start', 'woocommerce-doofinder'); ?></button>
		<div id="doofinder-for-wc-spinner" class="doofinder-for-wc-spinner spinner" style="display:none;"></div>
	</div>

	<div class="dfwc-setup-step__progress-bar-wrapper">
		<?php
		$index_interface->render_html_wp_debug_warning();
		?>
		<div class="doofinder-for-wc-progress-bar-status progress-bar-status">
			<span id="progress-value" class="progress-value">0</span>% <?php _e('complete', 'woocommerce-doofinder'); ?>
		</div>
		<div id="doofinder-for-wc-progress-bar" class="progress-bar">
			<div data-bar=""></div>
		</div>
		<?php
		$index_interface->render_html_progress_bar_status();
		$index_interface->render_html_indexing_messages();
		$index_interface->render_html_indexing_error_wizard();
		?>
	</div>
	<input type="hidden" id="process-step-input" name="process-step" value="3" />
	<input type="hidden" id="next-step-input" name="next-step" value="4" />
</form>
<?php if (isset($step_state) && $step_state === 3) : ?>
	<script>
		window.addEventListener('load', () => {
			const clickWhenScriptsLoaded = () => {
				if (typeof initAjaxIndexContentLoaded === "boolean" && initAjaxIndexContentLoaded) {
					clearInterval(initializeWhenReady);
					document.getElementById('doofinder-for-wc-index-button').click();
				}
			}
			const initializeWhenReady = setInterval(clickWhenScriptsLoaded, 100);
		});
	</script>
<?php endif; ?>