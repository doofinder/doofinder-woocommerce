<?php

namespace Doofinder\WC;

use Doofinder\WC\Setup_Wizard;

$wizard = Setup_Wizard::instance();


?>

<form action="<?php echo Setup_Wizard::get_url(['step' => '4']); ?>" method="post" class="dfwc-setup-step__actions">
	<p class="loading"><?php _e('Enabling doofinder search. Please wait...', 'woocommerce-doofinder'); ?></p>
</form>
<?php if (isset($step_state) && $step_state === 4) : ?>
	<script>
		window.addEventListener('load', () => {
			//document.getElementById('enable-doofinder-search').click();

			setTimeout(function() {
				document.location.href = "/wp-admin/admin.php?page=dfwc-setup&step=5"
			}, 3000)
		});
	</script>
<?php endif; ?>