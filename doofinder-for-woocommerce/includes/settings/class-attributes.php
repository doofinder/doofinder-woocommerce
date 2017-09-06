<?php

namespace Doofinder\WC\Settings;

class Attributes {

	/**
	 * Singleton of this class.
	 *
	 * @var Attributes
	 */
	private static $_instance;

	/**
	 * Available attributes configuration.
	 *
	 * @var array
	 */
	private $attributes;

	/**
	 * Fields where WC dimension units are applicable.
	 *
	 * @var array
	 */
	private $dimensions = array(
		'length', 'width', 'height'
	);

	/**
	 * Fields where WC weight units are applicable.
	 *
	 * @var array
	 */
	private $weight = array(
		'weight'
	);

	/**
	 * Returns the only instance of Attributes.
	 *
	 * @since 1.0.0
	 * @return Attributes
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Admin constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->attributes = include 'attributes.php';
	}

	/**
	 * Check if the attribute of given name is customized in settings.
	 *
	 * @param string $name Name of the attribute to check.
	 * @return bool True if the user configured and saved the attribute, false otherwise.
	 */
	public function have( $name ) {
		$option = Settings::get( 'feed_attributes', $name );
		return ! empty( $option );
	}

	/**
	 * Retrieve the value of the attribute by name.
	 *
	 * @param string   $name    Name of the attribute to retrieve.
	 * @param \WP_Post $product Product to retrieve attribute value from.
	 * @return mixed Attribute value.
	 */
	public function get( $name, $product ) {
		$attribute = Settings::get( 'feed_attributes', $name );
		if ( ! isset( $this->attributes[ $attribute ] ) ) {
			return '';
		}

		return $this->get_attribute_value( $attribute, $product );
	}

	/**
	 * Retrieve the value of the attribute from a given product.
	 *
	 * @param string   $attribute_name Attribute name, as present in attributes config.
	 * @param \WP_Post $product        Product to retrieve attribute value from.
	 * @return mixed Attribute value.
	 */
	public function get_attribute_value( $attribute_name, $product ) {
		$attribute = $this->attributes[ $attribute_name ];
		$value = '';
		switch ( $attribute['type'] ) {
			case 'predefined':
				$value = $this->get_attribute_predefined( $attribute['source'], $product );
				break;

			case 'meta':
				$value = $this->get_attribute_meta( $attribute['source'], $product );
				break;

			case 'wc_attribute':
				$value = $this->get_attribute_wc( $attribute['source'], $product );
				break;
		}

		// Check if we should export units
		if ( 'yes' === Settings::get( 'feed', 'export_units' ) && ! empty( $value ) ) {
			if ( in_array( $attribute_name, $this->dimensions ) ) {
				return $value . Settings::get_wc_option( 'woocommerce_dimension_unit' );
			}

			if ( in_array( $attribute_name, $this->weight ) ) {
				return $value . Settings::get_wc_option( 'woocommerce_weight_unit' );
			}
		}

		return $value;
	}

	/**
	 * Get value of predefined (coming from the WP functions relating to posts) attribute.
	 *
	 * @param string   $source  What is the source of the attribute (which part of WP functionality).
	 * @param \WP_Post $product Product to retrieve attribute from.
	 * @return mixed The attribute value.
	 */
	private function get_attribute_predefined( $source, $product ) {
		switch ($source) {
			case 'permalink':
				return get_permalink( $product );

			default:
				return $product->$source;
		}
	}

	/**
	 * Get the value of attribute stored in meta field.
	 *
	 * @param string   $source  Name of the meta field.
	 * @param \WP_Post $product Product to retrieve attribute from.
	 * @return mixed The attribute value.
	 */
	private function get_attribute_meta( $source, $product ) {
		return get_post_meta( $product->ID, $source, true );
	}

	/**
	 * Get the value of woocommerce product attribute.
	 *
	 * @param string   $source  Name of the product attribute.
	 * @param \WP_Post $product Product to retrieve attribute from.
	 * @return mixed The attribute value.
	 */
	private function get_attribute_wc( $source, $product ) {
		$_pf = new WC_Product_Factory();
		$product_object = $_pf->get_product($product->ID);
		return $product_object->get_attribute($source);
	}
}
