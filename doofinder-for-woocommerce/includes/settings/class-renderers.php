<?php
/**
 * DooFinder Renderers methods.
 *
 * @package Doofinder\WP\Settings
 */

namespace Doofinder\WP\Settings;

use Doofinder\WP\Api\Store_Api;
use Doofinder\WP\Config;
use Doofinder\WP\Multilanguage\Language_Plugin;
use Doofinder\WP\Multilanguage\No_Language_Plugin;
use Doofinder\WP\Reset_Credentials;
use Doofinder\WP\Settings;
use Doofinder\WP\Setup_Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contains all methods used to render the admin panel HTML.
 *
 * @property Language_Plugin $language
 */
trait Renderers {


	/**
	 * Display form for DooFinder settings page.
	 *
	 * If language plugin is active, but no language is selected we'll prompt the user
	 * to select a language instead of displaying settings.
	 *
	 * @since 1.0.0
	 */
	private function render_html_settings_page() {
		if ( ( $this->language instanceof No_Language_Plugin ) || $this->language->get_active_language() ) {
			$this->render_html_settings();

			return;
		}

		$this->render_html_pick_language_prompt();
	}

	/**
	 * Display the tabs.
	 */
	private function render_html_tabs() {
		// URL to the current page, but without GET for the nav item.
		$base_url = add_query_arg(
			'page',
			self::$top_level_menu,
			admin_url( 'admin.php' )
		);

		if ( count( self::$tabs ) > 1 ) :
			?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( self::$tabs as $id => $options ) : ?>
					<?php

					$current_tab_url = add_query_arg( 'tab', $id, $base_url );

					$is_active = false;
					if (
						// Current tab is selected.
						( isset( $_GET['tab'] ) && $_GET['tab'] === $id )

						// No tab is selected, and current tab is the first one.
						|| ( ! isset( $_GET['tab'] ) && array_keys( self::$tabs )[0] === $id )
					) {
						$is_active = true;
					}

					?>

					<a href="<?php echo esc_url( $current_tab_url ); ?>" class="nav-tab 
										<?php
										if ( $is_active ) :
											?>
						nav-tab-active<?php endif; ?>">
						<?php echo esc_html( $options['label'] ); ?>
					</a>

				<?php endforeach; ?>
			</nav>

			<?php
		endif;
	}

	/**
	 * Display the settings.
	 */
	private function render_html_settings() {
		// Only users that have access to wp settings can view this form.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['force_normalization'] ) ) {
			echo '<h1>Force Normalization</h1>';
			$store_api = new Store_Api();
			$store_api->normalize_store_and_indices();
		}

		// add update messages if doesn't exist.
		$errors = get_settings_errors( 'doofinder_for_wp_messages' );

		if ( isset( $_GET['settings-updated'] ) && ! $this->in_2d_array( 'doofinder_for_wp_message', $errors ) ) {
			add_settings_error(
				'doofinder_for_wp_messages',
				'doofinder_for_wp_message',
				__( 'Settings Saved', 'wordpress-doofinder' ),
				'updated'
			);
		}

		// show error/update messages.
		settings_errors( 'doofinder_for_wp_messages' );
		get_settings_errors( 'doofinder_for_wp_messages' );

		?>

		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php $this->render_html_tabs(); ?>

			<form id="df-settings-form" action="options.php" method="post">
				<?php

				settings_fields( self::$top_level_menu );
				$this->render_html_current_tab_id();
				do_settings_sections( self::$top_level_menu );
				submit_button( 'Save Settings' );

				?>
			</form>

			<?php
			if ( ! isset( $_GET['tab'] ) || 'authentication' === $_GET['tab'] ) {
				if ( in_array( 'administrator', wp_get_current_user()->roles, true ) ) {
					echo wp_kses_post( Reset_Credentials::get_configure_via_reset_credentials_button_html() );
				}
				echo wp_kses_post( Setup_Wizard::get_configure_via_setup_wizard_button_html() );
			}

			?>
		</div>

		<?php
	}

	/**
	 * Prompt the user to select a language.
	 */
	private function render_html_pick_language_prompt() {
		?>

		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="notice notice-error">
				<p>
				<?php
				esc_html_e(
					'You have a multi-language plugin installed. Please choose language first to configure Doofinder.',
					'wordpress-doofinder'
				);
				?>
					</p>
			</div>
		</div>

		<?php
	}

	/**
	 * Renders the hidden input containing the information which tab
	 * is currently selected.
	 *
	 * We need to render the same group of settings when displaying
	 * them to the user (via GET) and when processing the save action
	 * (POST), otherwise validation will fail. Since we cannot know
	 * from which tab the data was posted (there's not GET variables
	 * in POST request), we'll submit it in the hidden field.
	 */
	private function render_html_current_tab_id() {
		$selected_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : array_keys( self::$tabs )[0];

		?>

		<input type="hidden" name="doofinder_for_wp_selected_tab" value="<?php echo esc_attr( htmlspecialchars( $selected_tab ) ); ?>">

		<?php
	}

	/**
	 * Print HTML for the "API Key" option.
	 *
	 * @param string $option_name Name of the option.
	 */
	private function render_html_api_key( $option_name ) {
		$saved_value = Config::get_multilang_option( $option_name );

		?>

		<span class="doofinder-tooltip"><span>
		<?php
		esc_html_e(
			'The secret token is used to authenticate requests. Don`t need to use prefixes like eu1-, us1-...',
			'wordpress-doofinder'
		);
		?>
												</span></span>
		<input type="text" name="<?php echo esc_attr( $option_name ); ?>" class="widefat" 
											<?php
											if ( $saved_value ) :
												?>
			value="<?php echo esc_attr( htmlspecialchars( $saved_value ) ); ?>" <?php endif; ?>>

		<?php
	}

	/**
	 * Print HTML for the "API Host" option.
	 *
	 * @param string $option_name Name of the option.
	 */
	private function render_html_zone_select( $option_name ) {
		$saved_value = Config::get_multilang_option( $option_name );

		$key_eu = 'eu1';
		$key_us = 'us1';
		$key_ap = 'ap1';

		?>

		<span class="doofinder-tooltip"><span>
		<?php
		esc_html_e(
			'The region in which you are registered in doofinder',
			'wordpress-doofinder'
		);
		// Allow `<option>` and its attributes in `wp_kses()` function.
		$kses_args = array(
			'option' => array(
				'value'    => true,
				'selected' => true,
			),
		);
		?>
												</span></span>

		<select name="<?php echo esc_attr( $option_name ); ?>" class="widefat">
			<option> - Select a region - </option>
			<?php
			$selected_eu = $saved_value === $key_eu ? ' selected ' : '';
			echo wp_kses( '<option value=" ' . esc_attr( $key_eu ) . ' "' . $selected_eu . '> Europe -  ' . strtoupper( $key_eu ) . '  </option>', $kses_args );
			$selected_us = $saved_value === $key_us ? ' selected ' : '';
			echo wp_kses( '<option value=" ' . esc_attr( $key_us ) . ' "' . $selected_us . '> USA -  ' . strtoupper( $key_us ) . '  </option>', $kses_args );
			$selected_ap = $saved_value === $key_ap ? ' selected ' : '';
			echo wp_kses( '<option value=" ' . esc_attr( $key_ap ) . ' "' . $selected_ap . '> Asia-Pacific -  ' . strtoupper( $key_ap ) . '  </option>', $kses_args );
			?>
		</select>
		<?php
	}

	/**
	 * Print HTML for the "API Host" option.
	 *
	 * @param string $option_name Name of the option.
	 */
	private function render_html_dooplugins_host( $option_name ) {
		$saved_value = Config::get_multilang_option( $option_name );

		$key_eu = 'https://eu1-plugins.doofinder.com';
		$key_us = 'https://us1-plugins.doofinder.com';
		$key_ap = 'https://ap1-plugins.doofinder.com';

		// Allow `<option>` and its attributes in `wp_kses()` function.
		$kses_args = array(
			'option' => array(
				'value'    => true,
				'selected' => true,
			),
		);

		?>

		<span class="doofinder-tooltip"><span>
		<?php
		esc_html_e(
			'The host for plugins must contain point to the server where you have registered.',
			'wordpress-doofinder'
		);
		?>
												</span></span>

		<select name="<?php echo esc_attr( $option_name ); ?>" class="widefat">
			<?php
			$selected_eu = $saved_value === $key_eu ? ' selected ' : '';
			echo wp_kses( '<option value=" ' . esc_attr( $key_eu ) . ' "' . $selected_eu . '> Europa -  ' . $key_eu . '  </option>', $kses_args );
			$selected_us = $saved_value === $key_us ? ' selected ' : '';
			echo wp_kses( '<option value=" ' . esc_attr( $key_us ) . ' "' . $selected_us . '> USA -  ' . $key_us . '  </option>', $kses_args );
			$selected_ap = $saved_value === $key_ap ? ' selected ' : '';
			echo wp_kses( '<option value=" ' . esc_attr( $key_ap ) . ' "' . $selected_ap . '> Asia-Pacific -  ' . $key_ap . '  </option>', $kses_args );
			?>
		</select>
		<?php
	}

	/**
	 * Print HTML for the "Search engine hash" option.
	 *
	 * @param string $option_name Name of the option.
	 */
	private function render_html_search_engine_hash( $option_name ) {
		$saved_value = Config::get_multilang_option( $option_name );

		?>

		<span class="doofinder-tooltip"><span>
		<?php
		esc_html_e(
			'The Hash id of a search engine in your Doofinder Account.',
			'wordpress-doofinder'
		);
		?>
												</span></span>

		<input type="text" name="<?php echo esc_attr( $option_name ); ?>" class="widefat" 
											<?php
											if ( $saved_value ) :
												?>
			value="<?php echo esc_attr( htmlspecialchars( $saved_value ) ); ?>" <?php endif; ?>>

		<?php
	}

	/**
	 * Print HTML for the "API Host" option.
	 *
	 * @param string $option_name Name of the option.
	 */
	private function render_html_update_on_save( $option_name ) {
		$saved_value = Settings::get_update_on_save( $this->language->get_current_language() );
		$schedules   = wp_get_schedules();
		// Sort by interval.
		uasort(
			$schedules,
			function ( $a, $b ) {
				return $a['interval'] - $b['interval'];
			}
		);

		// Allow `<option>` and its attributes in `wp_kses()` function.
		$kses_args = array(
			'option' => array(
				'value'    => true,
				'selected' => true,
			),
		);
		?>

		<span class="doofinder-tooltip">
			<span>
				<?php esc_html_e( 'Configure how often changes will be sent to Doofinder. It will only be executed if there are changes.', 'wordpress-doofinder' ); ?>
			</span>
		</span>
		<select name="<?php echo esc_attr( $option_name ); ?>" class="widefat">
			<?php
			foreach ( $schedules as $key => $schedule ) {
				if ( strpos( $key, 'wp_doofinder' ) === 0 ) {
					$selected = $saved_value === $key ? " selected='selected' " : '';
					echo wp_kses( '<option value="' . esc_attr( $key ) . '" ' . $selected . '>' . esc_html( $schedule['display'] ) . '</option>', $kses_args );
				}
			}
			?>
		</select>
		<?php
		if ( Settings::is_update_on_save_enabled() ) :
			?>
				<a id="force-update-on-save" href="#" class="button-secondary update-on-save-btn update-icon">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Update now!', 'wordpress-doofinder' ); ?>
				</a>
		<?php endif; ?>
		<span class="update-result-wrapper"></span>
		<?php
	}

	/**
	 * Render a checkbox allowing user to enable / disable the JS layer.
	 *
	 * @param string $option_name Name of the option.
	 */
	private function render_html_enable_js_layer( $option_name ) {
		$saved_value = Config::get_multilang_option( $option_name );

		?>
		<span class="doofinder-tooltip">
			<span>
				<?php esc_html_e( 'Activating this option you are inserting the script into your store code. You can manage product visibility in Doofinder.', 'wordpress-doofinder' ); ?>
			</span>
		</span>

		<label class="df-toggle-switch">
			<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>" 
													<?php
													if ( $saved_value ) :
														?>
				checked <?php endif; ?>>
			<span class="toggle-slider"></span>
		</label>
		<?php
	}

	/**
	 * Render a checkbox allowing user to enable / disable the JS layer.
	 *
	 * @param string $option_name Name of the option.
	 */
	private function render_html_load_js_layer_from_doofinder( $option_name ) {
		$saved_value = Config::get_multilang_option( $option_name );

		?>
		<span class="doofinder-tooltip"><span>
		<?php
		esc_html_e(
			'The script is obtained from Doofinder servers instead of from the JS Layer Script field.',
			'wordpress-doofinder'
		);
		?>
												</span></span>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>" 
													<?php
													if ( $saved_value ) :
														?>
				checked <?php endif; ?>>

			<?php esc_html_e( 'Load JS Layer directly from Doofinder', 'wordpress-doofinder' ); ?>
		</label>

		<?php
	}

	/**
	 * Render the textarea containing Doofinder JS Layer code.
	 *
	 * @param string $option_name Name of the option.
	 */
	private function render_html_js_layer( $option_name ) {
		$saved_value = Config::get_multilang_option( $option_name );

		?>
		<span class="doofinder-tooltip"><span>
		<?php
		esc_html_e(
			'Paste here the JS Layer code obtained from Doofinder.',
			'wordpress-doofinder'
		);
		$textarea_value = ! empty( $saved_value ) ? wp_unslash( $saved_value ) : '';
		?>
												</span></span>
		<textarea 
			name="<?php echo esc_attr( $option_name ); ?>" 
			class="widefat" rows="16"><?php echo $textarea_value; // phpcs:ignore WordPress.Security.EscapeOutput ?></textarea>
		<?php
	}


	/**
	 * Render the inputs for Additional Attributes. This is a table
	 * of inputs and selects where the user can choose any additional
	 * fields to add to the exported data.
	 *
	 * @param string $custom_attribute_name Name of the custom attribute.
	 * @param bool   $should_exclude_woocommerce_attributes Decides whether the WooCommerce attributes should be taken into account or not. This is intended for the second dropdown, which should display only basic attributes and metafields.
	 */
	private function render_html_additional_attributes( $custom_attribute_name, $should_exclude_woocommerce_attributes = false ) {
		$saved_attributes = get_option( $custom_attribute_name );
		?>
		<table class="doofinder-for-wp-attributes">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Attribute', 'wordpress-doofinder' ); ?></th>
					<th><?php esc_html_e( 'Field', 'wordpress-doofinder' ); ?></th>
					<th colspan="10"><?php esc_html_e( 'Action', 'wordpress-doofinder' ); ?></th>
					<th colspan="100%"></th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ( ! empty( $saved_attributes ) ) {
					foreach ( $saved_attributes as $index => $attribute ) {
						$this->render_html_single_additional_attribute(
							$custom_attribute_name,
							$index,
							$should_exclude_woocommerce_attributes,
							$attribute
						);
					}
				}

				$this->render_html_single_additional_attribute(
					$custom_attribute_name,
					'new',
					$should_exclude_woocommerce_attributes
				);
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders a single row representing additional attribute.
	 * A helper for "render_html_additional_attributes".
	 *
	 * @see Renderers::render_html_additional_attributes
	 *
	 * @param string     $option_name Name of the option.
	 * @param string|int $index       Index number.
	 * @param bool       $should_exclude_woocommerce_attributes Decides whether the WooCommerce attributes should be taken into account or not. This is intended for the second dropdown, which should display only basic attributes and metafields.
	 * @param ?array     $attribute   WooCommerce Attribute. Defaults to null.
	 */
	private function render_html_single_additional_attribute( $option_name, $index, $should_exclude_woocommerce_attributes, $attribute = null ) {
		$option_groups = Settings::get_additional_attributes_options( $should_exclude_woocommerce_attributes );

		?>
		<tr>
			<td>


				<?php
				$type = ( null !== $attribute ) ? $attribute['type'] : null;
				if ( $should_exclude_woocommerce_attributes || ( 'metafield' === $type && 'new' !== $index ) ) :
					?>
					<input class="df-attribute-text" type="text" name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $index ); ?>][attribute]" 
																					<?php
																					if ( $attribute ) :
																						?>
						value="<?php echo esc_attr( $attribute['attribute'] ); ?>" <?php endif; ?> placeholder="<?php echo ( $should_exclude_woocommerce_attributes ) ? 'Enter the metafield key' : ''; ?>"/>
				<?php else : ?>
					<select class="df-attribute-select df-select-2" name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $index ); ?>][attribute]" required>
						<option disabled <?php echo ( 'new' === $index ) ? 'selected' : ''; ?>>- <?php esc_html_e( 'Select an attribute', 'wordpress-doofinder' ); ?> -</option>
						<?php foreach ( $option_groups as $group ) : ?>
							<optgroup label="<?php echo esc_attr( $group['title'] ); ?>">
								<?php foreach ( $group['options'] as $id => $attr ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>" 
																<?php
																if ( $attribute && $attribute['attribute'] === $id ) :
																	?>
										selected="selected" <?php endif; ?> <?php
										if ( isset( $attr['field_name'] ) && ! empty( $attr['field_name'] ) ) :
											?>
	data-field-name="<?php echo esc_attr( $attr['field_name'] ); ?>" <?php endif; ?> data-type="<?php echo esc_attr( $attr['type'] ); ?>">
										<?php echo esc_html( $attr['title'] ); ?>
									</option>
								<?php endforeach; ?>
							</optgroup>

						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</td>

			<td>
				<input id="df-field-text-<?php echo esc_attr( $index ); ?>" class="df-field-text" type="text" name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $index ); ?>][field]" 
													<?php
													if ( $attribute ) :
														?>
					value="<?php echo esc_attr( htmlspecialchars( $attribute['field'] ) ); ?>" <?php endif; ?> />
				<input class="df-field-type" type="hidden" name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $index ); ?>][type]" 
																			<?php
																			if ( $attribute ) :
																				?>
					value="<?php echo esc_attr( $attribute['type'] ); ?>" <?php endif; ?> />
			</td>

			<td>
				<?php if ( 'new' === $index ) : ?>
					<a href="#" class="df-add-attribute-btn df-action-btn"><span class="dashicons dashicons-insert"></span></a>
				<?php else : ?>
					<a href="#" class="df-delete-attribute-btn df-action-btn"><span class="dashicons dashicons-trash"></span></a>
				<?php endif; ?>
			</td>
			<td>
				<div class="errors"></div>
			</td>
		</tr>

		<?php
	}

	/**
	 * Renders an HTML select field for choosing the image size option.
	 *
	 * This function generates and renders a select dropdown field in the HTML form, allowing the user to choose
	 * from the available image sizes. The current value is pre-selected, and the "medium" size is recommended by default.
	 *
	 * @param string $option_name The name of the option to be saved in the WordPress database.
	 *
	 * @return void This function outputs the HTML for the select field and does not return any value.
	 */
	private function render_html_image_size_field( $option_name ) {
		$current_value = Settings::get_image_size();
		$image_sizes   = $this->get_all_image_sizes();
		$options       = array();
		foreach ( $image_sizes as $id => $image_size ) {
			$name      = $id . ' - ' . $image_size['width'] . ' x ' . $image_size['height'];
			$name      = 'medium' === $id ? $name . ' (' . __( 'Recommended', 'wordpress-doofinder' ) . ')' : $name;
			$options[] = array(
				'value' => $id,
				'name'  => $name,
			);
		}

		$this->render_select_field( 'df-image-size', $option_name, $options, array( 'df-image-size-select' ), $current_value );
	}

	/**
	 * Get all the registered image sizes along with their dimensions
	 *
	 * @global array $_wp_additional_image_sizes
	 *
	 * @return array $image_sizes The image sizes
	 */
	private function get_all_image_sizes() {
		global $_wp_additional_image_sizes;

		$default_image_sizes = get_intermediate_image_sizes();

		$image_sizes = array();

		foreach ( $default_image_sizes as $size ) {
			$image_sizes[ $size ]           = array();
			$image_sizes[ $size ]['width']  = intval( get_option( "{$size}_size_w" ) );
			$image_sizes[ $size ]['height'] = intval( get_option( "{$size}_size_h" ) );
			$image_sizes[ $size ]['crop']   = get_option( "{$size}_crop" ) ? get_option( "{$size}_crop" ) : false;
		}

		if ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) ) {
			$image_sizes = array_merge( $image_sizes, $_wp_additional_image_sizes );
		}

		return $image_sizes;
	}

	/**

	 * E.g.:
	 *
	 * @param array $config The configuration used to render the select.
	 * @return void
	 */

	/**
	 * Generates a select field with the received configuration.
	 *
	 * @param string $id The id of the select field.
	 * @param string $name The name attribute of the select field.
	 * @param array  $options The available options. Each option should have the keys value and name.
	 * @param array  $classes (Optional) The classes for the select field.
	 * @param string $selected (Optional) The key of the selected option.
	 * @param string $default_option (Optional) A default option name to add to your select. This option will appear as disabled.
	 *
	 * @return void
	 */
	private function render_select_field( $id, $name, $options, $classes = array(), $selected = null, $default_option = null ) {
		// Add default class.
		$classes[] = 'df-select';
		$classes   = implode( ' ', array_unique( $classes ) );
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="<?php echo esc_attr( $classes ); ?>">
			<?php if ( ! empty( $default_option ) ) : ?>
				<option disabled><?php echo esc_html( $default_option ); ?></option>

			<?php endif; ?>
			<?php
			foreach ( $options as $option ) {
				?>
				<option value="<?php echo esc_attr( $option['value'] ); ?>" <?php echo $selected === $option['value'] ? 'selected' : ''; ?>><?php echo esc_html( $option['name'] ); ?></option>
				<?php
			}
			?>
		</select>
		<?php
	}
}
