<?php

namespace Doofinder\WC;

use Doofinder\WC\Settings\Feed_URL;
use Doofinder\WC\Settings\Settings;

defined( 'ABSPATH' ) or die;

class Settings_Page extends \WC_Settings_Page {

	/**
	 * A list of fields available in products that can be assigned to fields in Data Feed.
	 *
	 * @var array
	 */
	private $fields;

	/**
	 * Doofinder_Settings constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id     = 'doofinder';
		$this->label  = __( 'Doofinder', 'woocommerce-doofinder' );
		$this->fields = include 'settings/attributes.php';

		// Register settings Tab
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );

		// Output sections header and section settings
		add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
		add_action( 'woocommerce_settings_' . $this->id, array( $this, 'before_settings' ) );
		add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );

		// Custom overrides for settings saving to fix unwanted WC behavior
		add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );

		// Add custom field allowing to add Additional Attributes
		add_action( 'woocommerce_admin_field_doofinder-wc-attributes-repeater', array(
			$this,
			'custom_field_repeater',
		) );
	}

	/* WC_Settings_Page overrides *************************************************/

	/**
	 * Get settings sections array.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_sections() {
		$sections = array(
			''                     => __( 'Doofinder Layer', 'woocommerce-doofinder' ),
			'internal_search'      => __( 'Internal Search', 'woocommerce-doofinder' ),
			'data_feed'            => __( 'Data Feed', 'woocommerce-doofinder' ),
			'data_feed_attributes' => __( 'Data Feed Attributes', 'woocommerce-doofinder' ),
		);

		return $sections;
	}

	/**
	 * Get settings array.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_settings() {
		global $current_section;

		switch ( $current_section ) {
			case '':
				return include 'settings/settings-layer.php';

			case 'internal_search':
				return include 'settings/settings-internal-search.php';

			case 'data_feed':
				return include 'settings/settings-feed.php';

			case 'data_feed_attributes':
				return include 'settings/settings-feed-attributes.php';
		}
	}

	/**
	 * Save settings.
	 *
	 * @since 1.0.0
	 */
	public function save() {
		$multilanguage = Multilanguage::instance();

		$settings = $this->get_settings();
		\WC_Admin_Settings::save_fields( $settings );

		/*
		 * Re-save the script directly. WordPress will add slashes to the code.
		 * This way we ensure that the <script> tags are saved.
		 */
		$field = Settings::option_id( 'layer', 'code', $multilanguage->get_language_prefix() );

		if ( isset( $_POST[ $field ] ) ) {
			update_option( $field, $_POST[ $field ] );
		}

		/*
		 * Re-save additional attributes.
		 * First of all, they are as two arrays in HTML, and they need to be converted into a single array.
		 * Secondly, WooCommerce settings API doesn't handle nested arrays, so we need to save an array
		 * of plain strings.
		 */
		$field  = Settings::option_id( 'feed_attributes', 'additional_attributes', $multilanguage->get_language_prefix() );
		$delete = null;
		if ( isset( $_POST[ $field . '_delete' ] ) ) {
			$delete = $_POST[ $field . '_delete' ];
		}

		if ( isset( $_POST[ $field ] ) ) {
			$attributes = $_POST[ $field ];
			$to_save    = array();

			for ( $i = 0; $i < count( $attributes['field'] ); $i ++ ) {
				if ( empty( $attributes['field'][ $i ] ) || $attributes['field'][ $i ] === $delete ) {
					continue;
				}

				// "field" is the name it will appear in the generated XML.
				// "attribute" is the name of the thing to retrieve from the DB
				// (e.g. name of the meta field)
				$field_attributes = 'field=' . $attributes['field'][ $i ] . '&attribute=' . $attributes['attribute'][ $i ];

				// Some fields might want to save some additional attributes.
				if ( $attributes['value'][ $i ] ) {
					$field_attributes .= '&value=' . $attributes['value'][ $i ];
				}

				$to_save[] = $field_attributes;
			}

			update_option( $field, $to_save );
		}
	}

	/* Additional (custom) elements **********************************************/

	/**
	 * Print additional custom elements before the settings added using
	 * WooCommerce Settings API.
	 *
	 * @since 1.0.0
	 */
	public function before_settings() {
		global $current_section;

		if ( 'data_feed' === $current_section ) {
			include 'settings/feed-url.php';
		}
	}

	/* Helpers ********************************************************************/

	/**
	 * Retrieve all image sizes registered with WP.
	 *
	 * @since 1.0.0
	 * @return array Indexed by size name, array of array containing width and height.
	 */
	private function get_image_sizes() {
		global $_wp_additional_image_sizes;
		$default_sizes = array(
			'thumbnail',
			'medium',
			'medium-large',
			'large',
		);

		$sizes = array();
		foreach ( get_intermediate_image_sizes() as $size ) {
			if ( in_array( $size, $default_sizes ) ) {
				$sizes[ $size ] = array(
					'width'  => get_option( "{$size}_size_w" ),
					'height' => get_option( "{$size}_size_h" ),
				);
			} elseif ( isset( $_wp_additional_image_sizes[ $size ] ) ) {
				$sizes[ $size ] = array(
					'width'  => $_wp_additional_image_sizes[ $size ]['width'],
					'height' => $_wp_additional_image_sizes[ $size ]['height'],
				);
			}
		}

		return $sizes;
	}

	/* Custom fields **************************************************************/

	/**
	 * Output custom repeater field for Additional Attributes configuration.
	 *
	 * @param array $params Field configuration.
	 */
	public function custom_field_repeater( $params ) {
		$field_value = \woocommerce_settings_get_option( $params['id'] );
		if ( is_array( $field_value ) && ! empty( $field_value ) ) {
			$field_value = array_map( 'wp_parse_args', $field_value );
		}

		?>

        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $params['id'] ); ?>"><?php echo esc_html( $params['title'] ); ?></label>
            </th>

            <td class="forminp">
                <table class="doofinder-wc-additional-attributes">
                    <thead>
                    <tr>
                        <th><?php _e( 'Field', 'woocommerce-doofinder' ); ?></th>
                        <th><?php _e( 'Attribute', 'woocommerce-doofinder' ); ?></th>
                        <th></th>
                    </tr>
                    </thead>

                    <tbody>
					<?php if ( ! empty( $field_value ) ): ?>
						<?php foreach ( $field_value as $attribute ): ?>
                            <tr>
                                <td><input name="<?php echo $params['id']; ?>[field][]" type="text"
                                           value="<?php echo $attribute['field']; ?>"></td>

								<?php if ( 'custom' === $attribute['attribute'] ): ?>
                                    <td>
                                        <input
                                                type="hidden"
                                                name="<?php echo $params['id']; ?>[attribute][]"
                                                value="custom"
                                        >
                                        <input
                                                type="text"
                                                name="<?php echo $params['id']; ?>[value][]"
                                                value="<?php echo $attribute['value']; ?>"
                                                placeholder="<?php _e( 'Field name in data base', 'woocommerce-doofinder' ); ?>"
                                        >
                                    </td>
								<?php else: ?>
                                    <td><?php $this->_custom_field_repeater_select( $params['id'], $params['options'], $attribute['attribute'] ); ?></td>
								<?php endif; ?>

                                <td>
                                    <button type="submit" name="<?php echo $params['id']; ?>_delete" class="button"
                                            value="<?php echo $attribute['field']; ?>">Delete
                                    </button>
                                </td>
                            </tr>
						<?php endforeach; ?>
					<?php endif; ?>

                    <tr>
                        <td><input name="<?php echo $params['id']; ?>[field][]" type="text"></td>
                        <td><?php $this->_custom_field_repeater_select( $params['id'], $params['options'] ); ?></td>
                        <td></td>
                    </tr>
                    </tbody>
                </table>
            </td>
        </tr>

		<?php
	}

	/**
	 * custom_field_repeater helper.
	 * Outputs a select with options for a repeater row.
	 *
	 * @see custom_field_repeater
	 *
	 * @param string $id       Field id.
	 * @param array  $options  List of select options.
	 * @param string $selected Selected option (if any).
	 */
	private function _custom_field_repeater_select( $id, $options, $selected = null ) {
		?>

        <select name="<?php echo $id; ?>[attribute][]">
			<?php foreach ( $options as $value => $label ): ?>
                <option value="<?php echo $value; ?>" <?php selected( $value, $selected ); ?>><?php echo $label; ?></option>
			<?php endforeach; ?>
        </select>

        <input type="hidden" name="<?php echo $id; ?>[value][]"/>

		<?php
	}
}
