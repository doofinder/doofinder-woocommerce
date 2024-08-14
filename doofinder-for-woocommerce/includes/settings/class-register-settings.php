<?php
/**
 * DooFinder Register_Settings methods.
 *
 * @package Doofinder\WP\Settings
 */

namespace Doofinder\WP\Settings;

use Doofinder\WP\Multilanguage\Language_Plugin;
use Doofinder\WP\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contains all settings registration / all calls to Settings API.
 *
 * @property Language_Plugin $language
 */
trait Register_Settings {


	/**
	 * Create settings page.
	 *
	 * This function registers all settings fields and holds the names
	 * of all options.
	 *
	 * @since 1.0.0
	 */
	private function add_plugin_settings() {
		add_action(
			'admin_init',
			function () {
				// When saving settings make sure not to register settings if we are not
				// saving our own settings page. If the current action is called on
				// the settings page of another plugin it might cause conflicts.
				if (
				// If we are saving the settings...
				( ! isset( $_SERVER['REQUEST_METHOD'] )
				|| 'POST' === $_SERVER['REQUEST_METHOD'] )
				&& (
					// ...and "option_page" is either not present...
					! isset( $_POST['option_page'] )

					// ...or is set to something else than our custom page.
					|| $_POST['option_page'] !== self::$top_level_menu
				)
				) {
					return;
				}

				// Figure out which tab is open / which tab is being saved.
				if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
					if ( ! isset( $_POST['doofinder_for_wp_selected_tab'] ) ) {
						return;
					}
					$selected_tab = wp_unslash( $_POST['doofinder_for_wp_selected_tab'] );
				} elseif ( isset( $_GET['tab'] ) ) {
					$selected_tab = wp_unslash( $_GET['tab'] );
				} else {
					$selected_tab = array_keys( self::$tabs )[0];
				}

				if ( ! isset( self::$tabs[ $selected_tab ] ) ) {
					return;
				}

				call_user_func( array( $this, self::$tabs[ $selected_tab ]['fields_cb'] ) );
			}
		);
	}

	/**
	 * Section 1 / tab 1 fields.
	 *
	 * IDE might report this as unused, because it's dynamically called.
	 *
	 * @see Settings::$tabs
	 */
	private function add_general_settings() {
		$section_id = 'doofinder-for-wp-general';

		add_settings_section(
			$section_id,
			__( 'General Settings', 'wordpress-doofinder' ),
			function () {
				?>
			<p class="description">
				<?php
				esc_html_e(
					'The following options allow to identify you and your search engine in Doofinder servers.',
					'wordpress-doofinder'
				);
				?>
									</p>
				<?php
			},
			self::$top_level_menu
		);

		// Enable JS Layer.
		$enable_js_layer_option_name =
			$this->language->get_option_name( 'doofinder_for_wp_enable_js_layer' );
		add_settings_field(
			$enable_js_layer_option_name,
			__( 'Enable Doofinder Layer', 'wordpress-doofinder' ),
			function () use ( $enable_js_layer_option_name ) {
				$this->render_html_enable_js_layer( $enable_js_layer_option_name );
			},
			self::$top_level_menu,
			$section_id
		);

		register_setting( self::$top_level_menu, $enable_js_layer_option_name );

		// API Key.
		$api_key_option_name = 'doofinder_for_wp_api_key';
		add_settings_field(
			$api_key_option_name,
			__( 'Api Key', 'wordpress-doofinder' ),
			function () use ( $api_key_option_name ) {
				$this->render_html_api_key( $api_key_option_name );
			},
			self::$top_level_menu,
			$section_id
		);

		register_setting( self::$top_level_menu, $api_key_option_name, array( $this, 'validate_api_key' ) );

		// DF Server Region (Hidden once filled).
		$region_option_name = 'doofinder_for_wp_region';
		$saved_region_value = get_option( $region_option_name );

		add_settings_field(
			$region_option_name,
			__( 'Region', 'wordpress-doofinder' ),
			function () use ( $region_option_name ) {
				$this->render_html_zone_select( $region_option_name );
			},
			self::$top_level_menu,
			$section_id,
			array(
				'class' => empty( $saved_region_value ) ? '' : 'hidden',
			)
		);

		register_setting( self::$top_level_menu, $region_option_name );

		// Search engine hash.
		$search_engine_hash_option_name =
			$this->language->get_option_name( 'doofinder_for_wp_search_engine_hash' );
		add_settings_field(
			$search_engine_hash_option_name,
			__( 'Search Engine HashID', 'wordpress-doofinder' ),
			function () use ( $search_engine_hash_option_name ) {
				$this->render_html_search_engine_hash( $search_engine_hash_option_name );
			},
			self::$top_level_menu,
			$section_id
		);

		register_setting(
			self::$top_level_menu,
			$search_engine_hash_option_name,
			array(
				$this,
				'validate_search_engine_hash',
			)
		);

		// Update on save.
		$update_on_save_option_name = $this->language->get_option_name( 'doofinder_for_wp_update_on_save' );
		add_settings_field(
			$update_on_save_option_name,
			__( 'Automatically process modified products', 'wordpress-doofinder' ),
			function () use ( $update_on_save_option_name ) {
				$this->render_html_update_on_save( $update_on_save_option_name );
			},
			self::$top_level_menu,
			$section_id
		);

		register_setting( self::$top_level_menu, $update_on_save_option_name, array( $this, 'validate_update_on_save' ) );

		// JS Layer.
		$js_layer_option_name =
			$this->language->get_option_name( 'doofinder_for_wp_js_layer' );
		add_settings_field(
			$js_layer_option_name,
			__( 'JS Layer Script', 'wordpress-doofinder' ),
			function () use ( $js_layer_option_name ) {
				$this->render_html_js_layer( $js_layer_option_name );
			},
			self::$top_level_menu,
			$section_id
		);

		register_setting( self::$top_level_menu, $js_layer_option_name );
	}

	/**
	 * Adds the product data settings section and fields to the DooFinder plugin settings page.
	 *
	 * This function creates a new settings section on the plugin's settings page, allowing users to configure product data options.
	 * It adds fields for setting the image size and custom attributes that will be indexed by the DooFinder plugin.
	 *
	 * @return void This function does not return any value, as it directly registers settings and fields with WordPress.
	 */
	private function add_product_data_settings() {
		$section_id = 'doofinder-for-wp-data';

		add_settings_section(
			$section_id,
			__( 'Product Data Settings', 'wordpress-doofinder' ),
			function () {
				?>
			<p class="description">
				<?php
				esc_html_e(
					'The following options allow you to set up which data would you like to index',
					'wordpress-doofinder'
				);
				?>
									</p>
				<?php
			},
			self::$top_level_menu
		);

		// Image Size.
		$image_size_option_name = Settings::$image_size_option;
		add_settings_field(
			$image_size_option_name,
			__( 'Image Size', 'wordpress-doofinder' ),
			function () use ( $image_size_option_name ) {
				$this->render_html_image_size_field( $image_size_option_name );
			},
			self::$top_level_menu,
			$section_id
		);

		register_setting( self::$top_level_menu, $image_size_option_name );

		// Custom Attributes.
		$additional_attributes_option_name = Settings::$custom_attributes_option;
		add_settings_field(
			$additional_attributes_option_name,
			__( 'Custom Attributes', 'wordpress-doofinder' ),
			function () {
				$this->render_html_additional_attributes();
			},
			self::$top_level_menu,
			$section_id
		);

		register_setting( self::$top_level_menu, $additional_attributes_option_name, array( $this, 'sanitize_additional_attributes' ) );
	}

	/**
	 * Add top level menu.
	 *
	 * @since 1.0.0
	 */
	private function add_settings_page() {
		add_action(
			'admin_menu',
			function () {
				add_menu_page(
					'Doofinder WP & WooCommerce Search',
					'Doofinder',
					'manage_options',
					self::$top_level_menu,
					function () {
						$this->render_html_settings_page();
					},
					'dashicons-search'
				);
			}
		);
	}

	/**
	 * Validate API key.
	 *
	 * @param string $input The API Key to be validated.
	 *
	 * @return string|null
	 */
	public function validate_api_key( $input ) {
		if ( null === $input ) {
			add_settings_error(
				'doofinder_for_wp_messages',
				'doofinder_for_wp_message_api_key',
				__( 'API Key is mandatory.', 'wordpress-doofinder' )
			);
		}

		$sanitized_api_key = wp_strip_all_tags( $input );

		/**
		 * Old API keys use prefixes like eu1- and us1-,
		 * in api 2.0 there aren't needed.
		 */
		if ( strpos( $sanitized_api_key, '-' ) ) {
			return substr( $sanitized_api_key, 4 );
		} else {
			return $sanitized_api_key;
		}
	}

	/**
	 * Validate api host.
	 *
	 * @param string $input The host to be validated.
	 *
	 * @return string
	 */
	private function validate_hosts( $input ) {
		if ( null === $input ) {
			add_settings_error(
				'doofinder_for_wp_messages',
				'doofinder_for_wp_message_hosts',
				__( 'Hosts are mandatory.', 'wordpress-doofinder' )
			);
		}

		/**
		 * New API host must include https:// protocol.
		 */
		if ( ! empty( $input ) ) {
			$url = wp_parse_url( $input );

			if ( 'https' !== $url['scheme'] && 'http' !== $url['scheme'] ) {
				return 'https://' . $input;
			} elseif ( 'http' === $url['scheme'] ) {
				return 'https://' . substr( $input, 7 );
			} else {
				return $input;
			}
		}
	}

	/**
	 * Validate search engine hash.
	 *
	 * @param string $input The Search Engine Hash to be validated.
	 *
	 * @return string|null $input
	 */
	public function validate_search_engine_hash( $input ) {
		if ( null === $input ) {
			add_settings_error(
				'doofinder_for_wp_messages',
				'doofinder_for_wp_message_search_engine_hash',
				__( 'HashID is mandatory.', 'wordpress-doofinder' )
			);
		}

		$sanitized_engine_hash = wp_strip_all_tags( $input );

		return $sanitized_engine_hash;
	}

	/**
	 * Validate update on save.
	 *
	 * @param string $input update on save value.
	 *
	 * @return string|null
	 */
	public function validate_update_on_save( $input ) {
		if ( null === $input ) {
			add_settings_error(
				'doofinder_for_wp_messages',
				'doofinder_for_wp_message_update_on_save',
				__( 'Update on save is mandatory.', 'wordpress-doofinder' )
			);
		}
		return $input;
	}


	/**
	 * Process additional attributes sent from the frontend
	 * and convert them to the shape we want to store in the DB.
	 *
	 * This functional basically converts indexes, so we save a nice
	 * regular numerically-indexed array, and removes all records
	 * that are either selected to be deleted, or invalid.
	 *
	 * @param array $input Additional attributes.
	 *
	 * @return array
	 */
	public function sanitize_additional_attributes( $input ) {
		$output = array();

		// We want to save a regular array containing all attributes,
		// but what we send from the frontend is an associative array
		// (because it has "new" entry).
		// Convert data from frontend to nicely-indexed regular array,
		// removing all the records that we want to delete, and those
		// with empty "field" value along the way.
		foreach ( $input as $attribute ) {
			$attribute['field'] = wp_strip_all_tags( $attribute['field'] );
			if ( ! $attribute['field'] ) {
				continue;
			}

			if ( isset( $attribute['delete'] ) && $attribute['delete'] ) {
				continue;
			}

			if ( in_array( $attribute['field'], Settings::RESERVED_CUSTOM_ATTRIBUTES_NAMES, true ) ) {
				$field_name         = $attribute['field'];
				$attribute['field'] = 'custom_' . $field_name;
				add_settings_error(
					'doofinder_for_wp_messages',
					'doofinder_for_wp_message_update_on_save',
					/* translators: %1$s is replaced with the reserved field name and %2$s by the new field name (non-conflicting one). */
					sprintf( __( "The '%1\$s' field name is reserved, we have changed it to '%2\$s' automatically, but you can change it if you want", 'wordpress-doofinder' ), $field_name, $attribute['field'] )
				);
				return false;
			}

			$output[] = $attribute;
		}

		return $output;
	}
}
