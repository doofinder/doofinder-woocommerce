<?php

namespace Doofinder\WC;

use Doofinder\WC\Setup_Wizard;

$wizard = Setup_Wizard::instance();


?>

<form action="<?php echo Setup_Wizard::get_url(['step' => '3']); ?>" method="post" class="dfwc-setup-step__actions">
	<p class="loading"><?php _e('Enabling doofinder search. Please wait...', 'woocommerce-doofinder'); ?></p>
</form>
<?php if (isset($step_state) && $step_state === 3) : ?>
	<script>
		window.addEventListener('load', () => {
			//document.getElementById('enable-doofinder-search').click();

			setTimeout(function() {
				document.location.href = "/wp-admin/admin.php?page=dfwc-setup&step=4"
			}, 3000)
		});
	</script>
<?php endif; ?>