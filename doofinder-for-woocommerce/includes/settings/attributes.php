<?php

// Predefined attributes that will always be present in the dropdown.
$attributes = array(
    /*
	 * WordPress fields
	 */

    'post_title' => array(
        'title'  => __('Attribute: Post Title', 'doofinder_for_wp'),
        'type'   => 'predefined',
        'source' => 'post_title',
        'field_name' => 'title'
    ),

    'post_content' => array(
        'title'  => __('Attribute: Description', 'doofinder_for_wp'),
        'type'   => 'predefined',
        'source' => 'post_content',
        'field_name' => 'content'
    ),

    'post_excerpt' => array(
        'title'  => __('Attribute: Short Description', 'doofinder_for_wp'),
        'type'   => 'predefined',
        'source' => 'post_excerpt',
        'field_name' => 'excerpt'
    ),

    'permalink' => array(
        'title'  => __('Attribute: Post Link', 'doofinder_for_wp'),
        'type'   => 'predefined',
        'source' => 'permalink',
        'field_name' => 'link'
    )
);

if (true) {
    /*
	 * WooCommerce fields
	 */
    $wc_default_attributes = array(
        'downloadable' => array(
            'title'  => __('Attribute: Downloadable', 'doofinder_for_wp'),
            'type'   => 'meta',
            'source' => '_downloadable',
            'field_name' => 'downloadable'
        ),

        'virtual' => array(
            'title'  => __('Attribute: Virtual', 'doofinder_for_wp'),
            'type'   => 'meta',
            'source' => '_virtual',
            'field_name' => 'virtual'
        ),

        'purchase_note' => array(
            'title'  => __('Attribute: Purchase Note', 'doofinder_for_wp'),
            'type'   => 'meta',
            'source' => '_purchase_note',
            'field_name' => 'purchase_note'
        ),

        'featured' => array(
            'title'  => __('Attribute: Featured', 'doofinder_for_wp'),
            'type'   => 'meta',
            'source' => '_featured',
            'field_name' => 'featured'
        ),

        'weight' => array(
            'title'  => __('Attribute: Weight', 'doofinder_for_wp'),
            'type'   => 'meta',
            'source' => '_weight',
            'field_name' => 'weight'
        ),

        'length' => array(
            'title'  => __('Attribute: Length', 'doofinder_for_wp'),
            'type'   => 'meta',
            'source' => '_length',
            'field_name' => 'length'
        ),

        'width' => array(
            'title'  => __('Attribute: Width', 'doofinder_for_wp'),
            'type'   => 'meta',
            'source' => '_width',
            'field_name' => 'width'
        ),

        'height' => array(
            'title'  => __('Attribute: Height', 'doofinder_for_wp'),
            'type'   => 'meta',
            'source' => '_height',
            'field_name' => 'height'
        ),
    );

    $attributes = array_merge($attributes, $wc_default_attributes);
    // WooCommerce attributes.
    // (the taxonomy registered by WC, in Products > Attributes)
    $wc_attributes = wc_get_attribute_taxonomies();
    foreach ($wc_attributes as $wc_attribute) {
        $attributes['pa_' . $wc_attribute->attribute_name] = array(
            'title'  => __('Custom Attribute:', 'doofinder_for_wp') . ' ' . $wc_attribute->attribute_label,
            'type'   => 'wc_attribute',
            'source' => $wc_attribute->attribute_name,
            'field_name' => $wc_attribute->attribute_name
        );
    }
}

// Custom attribute.
// Allowing user to provide a custom meta field name.
$attributes['custom'] = array(
    'title' => __('Custom (Post Meta)', 'doofinder_for_wp'),
    'type' => 'custom_meta'
);



return $attributes;
