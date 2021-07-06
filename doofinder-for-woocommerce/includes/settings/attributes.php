<?php

// Predefined attributes that will always be present in the dropdown.
$attributes = array(
	/*
	 * WordPress fields
	 */

	'post_title' => array(
		'title'  => __( 'Attribute: Post Title', 'woocommerce-doofinder' ),
		'type'   => 'predefined',
		'source' => 'post_title',
	),

	'post_content' => array(
		'title'  => __( 'Attribute: Description', 'woocommerce-doofinder' ),
		'type'   => 'predefined',
		'source' => 'post_content',
	),

	'post_excerpt' => array(
		'title'  => __( 'Attribute: Short Description', 'woocommerce-doofinder' ),
		'type'   => 'predefined',
		'source' => 'post_excerpt',
	),

	'permalink' => array(
		'title'  => __( 'Attribute: Post Link', 'woocommerce-doofinder' ),
		'type'   => 'predefined',
		'source' => 'permalink',
	),

	/*
	 * WooCommerce fields
	 */

	'downloadable' => array(
		'title'  => __( 'Attribute: Downloadable', 'woocommerce-doofinder' ),
		'type'   => 'meta',
		'source' => '_downloadable',
	),

	'virtual' => array(
		'title'  => __( 'Attribute: Virtual', 'woocommerce-doofinder' ),
		'type'   => 'meta',
		'source' => '_virtual',
	),

	'purchase_note' => array(
		'title'  => __( 'Attribute: Purchase Note', 'woocommerce-doofinder' ),
		'type'   => 'meta',
		'source' => '_purchase_note',
	),

	'featured' => array(
		'title'  => __( 'Attribute: Featured', 'woocommerce-doofinder' ),
		'type'   => 'meta',
		'source' => '_featured',
	),

	'weight' => array(
		'title'  => __( 'Attribute: Weight', 'woocommerce-doofinder' ),
		'type'   => 'meta',
		'source' => '_weight',
	),

	'length' => array(
		'title'  => __( 'Attribute: Length', 'woocommerce-doofinder' ),
		'type'   => 'meta',
		'source' => '_length',
	),

	'width' => array(
		'title'  => __( 'Attribute: Width', 'woocommerce-doofinder' ),
		'type'   => 'meta',
		'source' => '_width',
	),

	'height' => array(
		'title'  => __( 'Attribute: Height', 'woocommerce-doofinder' ),
		'type'   => 'meta',
		'source' => '_height',
	),
);

// WooCommerce attributes.
// (the taxonomy registered by WC, in Products > Attributes)
$wc_attributes = wc_get_attribute_taxonomies();
foreach ( $wc_attributes as $wc_attribute ) {
	$attributes[ 'pa_' . $wc_attribute->attribute_name ] = array(
		'title'  => __( 'Custom Attribute:', 'woocommerce-doofinder' ) . ' ' . $wc_attribute->attribute_label,
		'type'   => 'wc_attribute',
		'source' => $wc_attribute->attribute_name,
	);
}

// Custom attribute.
// Allowing user to provide a custom meta field name.
$attributes['custom'] = array(
	'title' => __( 'Custom (Post Meta)', 'woocommerce-doofinder' ),
	'type' => 'custom_meta'
);

return $attributes;
