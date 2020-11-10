<?php

use Doofinder\WC\Multilanguage;
use Doofinder\WC\Settings\Settings;

$multilanguage = Multilanguage::instance();

if ( $multilanguage->is_active() && ! $multilanguage->get_language_code() ) {
	echo $multilanguage->get_choose_language_notice();
	return array();
}

$affix = $multilanguage->get_language_prefix();

// Transform fields config into array accepted by Select fields
$options = array();
foreach ( $this->fields as $name => $settings ) {
	$options[ $name ] = $settings['title'];
}

return array(
	array(
		'title' => __( 'Data Feed Attributes', 'woocommerce-doofinder' ),
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
