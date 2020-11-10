<?php
namespace Doofinder\WC;

use Doofinder\WC\Setup_Wizard;

$wizard = Setup_Wizard::instance();
?>

<form action="<?php echo Setup_Wizard::get_url(['step' => '4']); ?>" method="post"  class="dfwc-setup-step__actions">
	<?php

	// If there's no plugin active we still need to process 1 language.
	$languages = $wizard->language->get_formatted_languages();
	if ( ! $languages ) {
		$languages[''] = '';
	}

	foreach ( $languages as $language_code => $language_name ):

		// Language code (suffix) for options.
		// Default language has no suffix, all other languages do.
		$name_suffix    = '';

		if ( $language_code !== $wizard->language->get_base_language() ) {
			$name_suffix    = "-$language_code";
		}
		?>

		<input type="hidden" name="enable-js-layer<?php echo $name_suffix; ?>" value="1" />

	<?php endforeach; ?>
	
	<input type="hidden" name="process-step" value="4" />
	<button type="submit" class="button button-primary"><?php _e('Create layer', 'woocommerce-doofinder'); ?></button>
	<a href="<?php echo Setup_Wizard::get_url(['step'=>'5']); ?>" data-go-to-step="5" class="skip-step"><?php _e("Skip, I'll do it later", 'woocommerce-doofinder'); ?></a>
</form>
