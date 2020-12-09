<?php

defined( 'ABSPATH' ) or die();

use Doofinder\WC\Index_Interface;

$index_interface = Index_Interface::instance();
?>
<style>
    .doofinder-search__indexing + p.submit {
        display: none;
    }
</style>
<div class="doofinder-search__indexing">
	<?php
		$index_interface->render_html_subpage();
	?>
</div><?php

return array();
