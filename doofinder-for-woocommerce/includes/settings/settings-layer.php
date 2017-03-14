<?php

use Doofinder\WC\Multilanguage;
use Doofinder\WC\Settings\Settings;

defined( 'ABSPATH' ) or die;

$multilanguage = Multilanguage::instance();

if ( $multilanguage->is_active() && ! $multilanguage->get_language_code() ) {
	echo '<p>' . __( 'Please choose a language to view the settings.', 'woocommerce-doofinder' ) . '</p>';
	return array();
}

$affix = $multilanguage->get_language_prefix();

return array(
	array(
		'title' => __( 'Layer Options', 'woocommerce-doofinder' ),
		'type'  => 'title',
		'desc'  => '',
		'id'    => Multilanguage::code_suffix( 'layer_options', $affix ),
	),

	array(
		'title'   => __( 'Enable the Layer', 'woocommerce-doofinder' ),
		'desc'    => '',
		'id'      => Settings::option_id( 'layer', 'enabled', $affix ),
		'type'    => 'checkbox',
		'default' => 'no',
	),

	array(
		'title'   => __( 'Layer Javascript Code', 'woocommerce-doofinder' ),
		'desc'    => __( 'Paste here the Javascript code you will find in your Doofinder Control Panel under <em><strong>Configuration &gt; Installation Scripts &gt; Doofinder Layer</strong></em>.', 'woocommerce-doofinder' ),
		'id'      => Settings::option_id( 'layer', 'code', $affix ),
		'css'     => 'margin-top: 5px; width: 100%; height: 500px; font-family: Consolas,Monaco,monospace;',
		'type'    => 'textarea',
		'default' => '',
	),

	array(
		'type' => 'sectionend',
		'id'   => Multilanguage::code_suffix( 'layer_options', $affix ),
	),
);
