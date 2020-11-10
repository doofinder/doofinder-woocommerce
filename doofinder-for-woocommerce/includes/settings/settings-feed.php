<?php

use Doofinder\WC\Multilanguage;
use Doofinder\WC\Settings\Settings;

defined( 'ABSPATH' ) or die();

$multilanguage = Multilanguage::instance();

if ( $multilanguage->is_active() && ! $multilanguage->get_language_code() ) {
	echo $multilanguage->get_choose_language_notice();
	return array();
}

$affix = $multilanguage->get_language_prefix();

$sizes = array();
foreach ( $this->get_image_sizes() as $name => $dimensions ) {
	$sizes[] = "<code>$name</code> (" . $dimensions['width'] . ' x ' . $dimensions['height'] . ')';
}

return array(
	array(
		'title' => __( 'Feed Options', 'woocommerce-doofinder' ),
		'type'  => 'title',
		'desc'  => '',
		'id'    => Multilanguage::code_suffix( 'feed_options', $affix ),
	),

	array(
		'title'   => __( 'Protect feed with password', 'woocommerce-doofidner' ),
		'desc'    => '',
		'id'      => Settings::option_id( 'feed', 'password_protected', $affix ),
		'type'    => 'checkbox',
		'default' => 'no',
	),

	array(
		'title'   => __( 'Feed password', 'woocommerce-doofinder' ),
		'desc'    => '',
		'id'      => Settings::option_id( 'feed', 'password', $affix ),
		'type'    => 'text',
		'css'     => 'width: 100%',
		'default' => '',
	),

	array(
		'title'   => __( 'Export product prices', 'woocommerce-doofinder' ),
		'desc'    => '',
		'id'      => Settings::option_id( 'feed', 'export_prices', $affix ),
		'type'    => 'checkbox',
		'default' => 'yes',
	),

	array(
		'title'   => __( 'Export product tags', 'woocommerce-doofinder' ),
		'desc'    => '',
		'id'      => Settings::option_id( 'feed', 'export_tags', $affix ),
		'type'    => 'checkbox',
		'default' => 'no',
	),

	/*
	 * Image size configuration temporarily omitted.
	 */
	array(
		'title'   => __( 'Image size', 'woocommerce-doofinder' ),
		'desc'    => sprintf(
			__( 'Image size to export products with. If left empty the thumbnail size will be exported. Available image sizes are: %s', 'woocommerce-doofinder' ),
			'<div style="line-height: 19px">' . implode( ', ', $sizes ) . '</div>'
		),
		'id'      => Settings::option_id( 'feed', 'image_size', $affix ),
		'type'    => 'text',
		'css'     => 'width: 100%',
		'default' => '',
	),

	array(
		'title'   => __( 'Split Variable products', 'woocommerce-doofinder' ),
		'desc'    => '',
		'id'      => Settings::option_id( 'feed', 'split_variable', $affix ),
		'type'    => 'checkbox',
		'default' => 'no',
	),

	array(
		'title'   => __( 'Export values with units where applicable', 'woocommerce-doofinder' ),
		'desc'    => '',
		'id'      => Settings::option_id( 'feed', 'export_units', $affix ),
		'type'    => 'checkbox',
		'default' => 'no',
	),

	array(
		'type' => 'sectionend',
		'id'   => Multilanguage::code_suffix( 'feed_options', $affix ),
	),
);
