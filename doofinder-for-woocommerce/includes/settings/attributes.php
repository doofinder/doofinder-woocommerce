<?php

// Predefined attributes that will always be present in the dropdown.
/*
* WordPress fields
*/
$attributes = array(
    'post_excerpt' => array(
        'title'  => __('Attribute: Short Description', 'doofinder_for_wp'),
        'type'   => 'base_attribute',
        'source' => 'post_excerpt',
        'field_name' => 'excerpt'
    )
);

if (is_plugin_active('woocommerce/woocommerce.php')) {
    /*
	 * WooCommerce fields
	 */
    $wc_default_attributes = array(
        'downloadable' => array(
            'title'  => __('Attribute: Downloadable', 'doofinder_for_wp'),
            'type'   => 'base_attribute',
            'source' => 'downloadable',
            'field_name' => 'downloadable'
        ),

        'virtual' => array(
            'title'  => __('Attribute: Virtual', 'doofinder_for_wp'),
            'type'   => 'base_attribute',
            'source' => 'virtual',
            'field_name' => 'virtual'
        ),

        'purchase_note' => array(
            'title'  => __('Attribute: Purchase Note', 'doofinder_for_wp'),
            'type'   => 'base_attribute',
            'source' => 'purchase_note',
            'field_name' => 'purchase_note'
        ),

        'featured' => array(
            'title'  => __('Attribute: Featured', 'doofinder_for_wp'),
            'type'   => 'base_attribute',
            'source' => 'featured',
            'field_name' => 'featured'
        ),

        'weight' => array(
            'title'  => __('Attribute: Weight', 'doofinder_for_wp'),
            'type'   => 'base_attribute',
            'source' => '_weight',
            'field_name' => 'weight'
        ),

        'dimensions:length' => array(
            'title'  => __('Attribute: Length', 'doofinder_for_wp'),
            'type'   => 'base_attribute',
            'source' => 'length',
            'field_name' => 'length'
        ),

        'dimensions:width' => array(
            'title'  => __('Attribute: Width', 'doofinder_for_wp'),
            'type'   => 'base_attribute',
            'source' => 'width',
            'field_name' => 'width'
        ),

        'dimensions:height' => array(
            'title'  => __('Attribute: Height', 'doofinder_for_wp'),
            'type'   => 'base_attribute',
            'source' => 'height',
            'field_name' => 'height'
        ),
    );

    $attributes = array_merge($attributes, $wc_default_attributes);
    // WooCommerce attributes.
    // (the taxonomy registered by WC, in Products > Attributes)
    $wc_attributes = wc_get_attribute_taxonomies();
    foreach ($wc_attributes as $wc_attribute) {
        $attributes['wc_' . $wc_attribute->attribute_id] = array(
            'title'  => __('Custom Attribute:', 'doofinder_for_wp') . ' ' . $wc_attribute->attribute_label,
            'type'   => 'wc_attribute',
            'source' => $wc_attribute->attribute_name,
            'field_name' => $wc_attribute->attribute_name
        );
    }
}

// Custom attribute.
// Allowing user to provide a custom meta field name.
$attributes['metafield'] = array(
    'title' => __('Custom (Post Meta)', 'doofinder_for_wp'),
    'type' => 'metafield'
);



return $attributes;
