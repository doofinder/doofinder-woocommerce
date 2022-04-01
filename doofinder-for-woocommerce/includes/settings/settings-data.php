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

$feed_settings = array(
	array(
		'title' => __( 'Settings', 'woocommerce-doofinder' ),
		'type'  => 'title',
		'desc'  => '',
		'id'    => Multilanguage::code_suffix( 'feed_options', $affix ),
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

	// array(
	// 	'title'   => __( 'Protect feed with password', 'woocommerce-doofidner' ),
	// 	'desc'    => '',
	// 	'id'      => Settings::option_id( 'feed', 'password_protected', $affix ),
	// 	'type'    => 'checkbox',
	// 	'default' => 'no',
	// ),

	// array(
	// 	'title'   => __( 'Feed password', 'woocommerce-doofinder' ),
	// 	'desc'    => '',
	// 	'id'      => Settings::option_id( 'feed', 'password', $affix ),
	// 	'type'    => 'text',
	// 	'css'     => 'width: 100%',
	// 	'default' => '',
	// ),

	array(
		'title'   => __( 'Split variable products', 'woocommerce-doofinder' ),
		'desc'    => '',
		'id'      => Settings::option_id( 'feed', 'split_variable', $affix ),
		'type'    => 'checkbox',
		'default' => 'no',
	),

	array(
		'title'   => __( 'Export values with units', 'woocommerce-doofinder' ),
		'desc'    => '',
		'id'      => Settings::option_id( 'feed', 'export_units', $affix ),
		'type'    => 'checkbox',
		'default' => 'no',
	),

	/**
	 * This setting would have be better implemented in indexing-settings but it is not
	 * possible because that section is used to show the indexation interface.
	 */

	array(
		'title'   => __( 'Update on save', 'woocommerce-doofinder' ),
		'desc'    => 'The index will be updated every time you make a change in your product',
		'id'      => Settings::option_id( 'indexing', 'update_on_save' ),
        'type'    => 'checkbox',
		'default' => 'yes',
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
		'type' => 'sectionend',
		'id'   => Multilanguage::code_suffix( 'feed_options', $affix ),
	),
);

// Transform fields config into array accepted by Select fields
$options = array();

foreach ( $this->fields as $name => $settings ) {
	$options[ $name ] = $settings['title'];
}

$attributes =  array(
	array(
		'title' => __( 'Attributes', 'woocommerce-doofinder' ),
		'type'  => 'title',
		'desc'  => '',
		'id'    => Multilanguage::code_suffix( 'feed_attributes', $affix ),
	),

	array(
		'id'      => Settings::option_id( 'feed_attributes', 'title', $affix ),
		'title'   => __( 'Title', 'woocommerce-doofinder' ),
		'type'    => 'select',
		'desc'    => '',
		'css'     => 'width: 100%',
		'options' => $options,
		'default' => 'post_title',
	),

	array(
		'id'      => Settings::option_id( 'feed_attributes', 'description', $affix ),
		'title'   => __( 'Description', 'woocommerce-doofinder' ),
		'type'    => 'select',
		'desc'    => '',
		'css'     => 'width: 100%',
		'options' => $options,
		'default' => 'post_content',
	),

	array(
		'id'      => Settings::option_id( 'feed_attributes', 'link', $affix ),
		'title'   => __( 'Link', 'woocommerce-doofinder' ),
		'type'    => 'select',
		'desc'    => '',
		'css'     => 'width: 100%',
		'options' => $options,
		'default' => 'permalink',
	),

	array(
		'id'      => Settings::option_id( 'feed_attributes', 'additional_attributes', $affix ),
		'title'   => __( 'Additional Attributes', 'woocommerce-doofinder' ),
		'type'    => 'doofinder-wc-attributes-repeater',
		'options' => $options,
	),

	array(
		'type' => 'sectionend',
		'id'   => Multilanguage::code_suffix( 'feed_attributes', $affix ),
	),
);


$indexing_settings = array(
	array(
		'title' => __( 'Indexing', 'woocommerce-doofinder' ),
		'type'  => 'title',
		'desc'  => '',
		'id'    => Multilanguage::code_suffix( 'indexing_options', $affix ),
	),

	array(
		'title'   => __( 'Enable debug mode', 'woocommerce-doofinder' ),
		'desc'    => '',
		'id'      => Settings::option_id( 'indexing', 'enable_debug_mode', $affix ),
		'type'    => 'checkbox',
		'default' => 'no',
	),


	array(
		'type' => 'sectionend',
		'id'   => Multilanguage::code_suffix( 'indexing_options', $affix ),
	),
);


return array_merge($feed_settings, $attributes, $indexing_settings);
