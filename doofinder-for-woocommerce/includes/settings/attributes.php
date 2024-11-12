<?php
/**
 * Predefined attributes that will always be present in the dropdown.
 *
 * WordPress fields
 *
 * @package Doofinder\WP\Settings
 */

use Doofinder\WP\Settings;

$attributes = array(
	'post_excerpt' => array(
		'title'      => __( 'Short Description', 'wordpress-doofinder' ),
		'type'       => 'base_attribute',
		'source'     => 'post_excerpt',
		'field_name' => 'excerpt',
	),
);

if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
	/*
	 * WooCommerce fields
	 */
	$wc_default_attributes = array(
		'dimensions:length' => array(
			'title'      => __( 'Length', 'wordpress-doofinder' ),
			'type'       => 'base_attribute',
			'source'     => 'length',
			'field_name' => 'length',
		),

		'dimensions:width'  => array(
			'title'      => __( 'Width', 'wordpress-doofinder' ),
			'type'       => 'base_attribute',
			'source'     => 'width',
			'field_name' => 'width',
		),

		'dimensions:height' => array(
			'title'      => __( 'Height', 'wordpress-doofinder' ),
			'type'       => 'base_attribute',
			'source'     => 'height',
			'field_name' => 'height',
		),
	);

	$attributes = array_merge( $attributes, $wc_default_attributes );
	// WooCommerce attributes.
	// (the taxonomy registered by WC, in Products > Attributes).
	$wc_attributes = wc_get_attribute_taxonomies();
	foreach ( $wc_attributes as $wc_attribute ) {
		$field_name                                        = $wc_attribute->attribute_name;
		$field_name                                        = in_array( $field_name, Settings::RESERVED_CUSTOM_ATTRIBUTES_NAMES, true ) ? 'custom_' . $field_name : $field_name;
		$attributes[ 'wc_' . $wc_attribute->attribute_id ] = array(
			'title'      => $wc_attribute->attribute_label,
			'type'       => 'wc_attribute',
			'source'     => $wc_attribute->attribute_name,
			'field_name' => $field_name,
		);
	}
}

$rest_attributes = array();

if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
	$rest_attributes = Settings::get_product_rest_attributes();
}

if ( ! empty( $rest_attributes ) ) {
	foreach ( $rest_attributes as $attribute_key ) {
		$attribute_name               = ucfirst( trim( str_replace( '_', ' ', $attribute_key ) ) );
		$attributes[ $attribute_key ] = array(
			'title'      => $attribute_name,
			'type'       => 'base_attribute',
			'source'     => $attribute_key,
			'field_name' => $attribute_key,
		);
	}
}

// Custom attribute.
// Allowing user to provide a custom meta field name.
$attributes['metafield'] = array(
	'title' => __( 'Custom (Post Meta)', 'wordpress-doofinder' ),
	'type'  => 'metafield',
);

// Sort alphabetically.
uasort(
	$attributes,
	function ( $a, $b ) {
		if ( $a['title'] === $b['title'] ) {
			return 0;
		}
		return ( $a['title'] < $b['title'] ) ? -1 : 1;
	}
);

return $attributes;
