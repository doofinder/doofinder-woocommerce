<?php

namespace Doofinder\WP\Settings;

use Doofinder\WP\Multilanguage\Language_Plugin;
use Doofinder\WP\Multilanguage\No_Language_Plugin;
use Doofinder\WP\Setup_Wizard;
use Doofinder\WP\Settings;
use Doofinder\WP\Settings\Settings as SettingsHelper;
use Doofinder\WP\Reset_Credentials;
use Doofinder\WP\Multilanguage;

defined('ABSPATH') or die();

/**
 * @property Language_Plugin $language
 */
trait Renderers
{

    /**
     * Display form for doofinder settings page.
     *
     * If language plugin is active, but no language is selected we'll prompt the user
     * to select a language instead of displaying settings.
     *
     * @since 1.0.0
     */
    private function render_html_settings_page()
    {
        if (($this->language instanceof No_Language_Plugin) || $this->language->get_active_language()) {
            $this->render_html_settings();

            return;
        }

        $this->render_html_pick_language_prompt();
    }

    /**
     * Display the tabs.
     */
    private function render_html_tabs()
    {
        // URL to the current page, but without GET for the nav item.
        $base_url = add_query_arg(
            'page',
            self::$top_level_menu,
            admin_url('admin.php')
        );

        if (count(self::$tabs) > 1) :
?>
            <nav class="nav-tab-wrapper">
                <?php foreach (self::$tabs as $id => $options) : ?>
                    <?php

                    $current_tab_url = add_query_arg('tab', $id, $base_url);

                    $is_active = false;
                    if (
                        // Current tab is selected.
                        (isset($_GET['tab']) && $_GET['tab'] === $id)

                        // No tab is selected, and current tab is the first one.
                        || (!isset($_GET['tab']) && $id === array_keys(self::$tabs)[0])
                    ) {
                        $is_active = true;
                    }

                    ?>

                    <a href="<?php echo $current_tab_url; ?>" class="nav-tab <?php if ($is_active) : ?>nav-tab-active<?php endif; ?>">
                        <?php echo $options['label']; ?>
                    </a>

                <?php endforeach; ?>
            </nav>

        <?php
        endif;
    }

    /**
     * Display the settings.
     */
    private function render_html_settings()
    {
        // only users that have access to wp settings can view this form
        if (!current_user_can('manage_options')) {
            return;
        }

        // add update messages if doesn't exist
        $errors = get_settings_errors('doofinder_for_wp_messages');

        if (isset($_GET['settings-updated']) && !$this->in_2d_array('doofinder_for_wp_message', $errors)) {
            add_settings_error(
                'doofinder_for_wp_messages',
                'doofinder_for_wp_message',
                __('Settings Saved', 'doofinder_for_wp'),
                'updated'
            );
        }

        // show error/update messages
        settings_errors('doofinder_for_wp_messages');
        get_settings_errors('doofinder_for_wp_messages');

        ?>

        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php $this->render_html_tabs(); ?>

            <form id="df-settings-form" action="options.php" method="post">
                <?php

                settings_fields(self::$top_level_menu);
                $this->render_html_current_tab_id();
                do_settings_sections(self::$top_level_menu);
                submit_button('Save Settings');

                ?>
            </form>

            <?php
            if (!isset($_GET['tab']) || $_GET['tab'] === 'authentication') {
                if (in_array('administrator',  wp_get_current_user()->roles)) {
                    echo Reset_Credentials::get_configure_via_reset_credentials_button_html();
                }
                echo Setup_Wizard::get_configure_via_setup_wizard_button_html();
            }

            ?>
        </div>

    <?php
    }

    /**
     * Prompt the user to select a language.
     */
    private function render_html_pick_language_prompt()
    {
    ?>

        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="notice notice-error">
                <p><?php _e(
                        'You have a multi-language plugin installed. Please choose language first to configure Doofinder.',
                        'doofinder_for_wp'
                    ); ?></p>
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
    private function render_html_current_tab_id()
    {
        $selected_tab = isset($_GET['tab']) ? $_GET['tab'] : array_keys(self::$tabs)[0];

    ?>

        <input type="hidden" name="doofinder_for_wp_selected_tab" value="<?php echo $selected_tab; ?>">

    <?php
    }

    /**
     * Print HTML for the "API Key" option.
     *
     * @param string $option_name
     */
    private function render_html_api_key($option_name)
    {
        $saved_value = get_option($option_name);

    ?>

        <span class="doofinder-tooltip"><span><?php _e(
                                                    'The secret token is used to authenticate requests. Don`t need to use eu1- or us1- prefix.',
                                                    'doofinder_for_wp'
                                                ); ?></span></span>
        <input type="text" name="<?php echo $option_name; ?>" class="widefat" <?php if ($saved_value) : ?> value="<?php echo $saved_value; ?>" <?php endif; ?>>

    <?php
    }

    /**
     * Print HTML for the "API Host" option.
     *
     * @param string $option_name
     */
    private function render_html_api_host($option_name)
    {
        $saved_value = get_option($option_name);

        $key_eu = "https://eu1-admin.doofinder.com";
        $key_us = "https://us1-admin.doofinder.com";

    ?>

        <span class="doofinder-tooltip"><span><?php _e(
                                                    'The host API must contain point to the server where you have registered.',
                                                    'doofinder_for_wp'
                                                ); ?></span></span>

        <select name="<?php echo $option_name; ?>" class="widefat">
            <?php
            $selected_eu = $saved_value === $key_eu ? " selected " : "";
            echo '<option value=" ' . $key_eu . ' "' . $selected_eu . '> Europa -  ' . $key_eu . '  </option>';
            $selected_us = $saved_value === $key_us ? " selected " : "";
            echo '<option value=" ' . $key_us . ' "' . $selected_us . '> USA -  ' . $key_us . '  </option>';
            ?>
        </select>
    <?php
    }

    /**
     * Print HTML for the "Search engine hash" option.
     *
     * @param string $option_name
     */
    private function render_html_search_engine_hash($option_name)
    {
        $saved_value = get_option($option_name);

    ?>

        <span class="doofinder-tooltip"><span><?php _e(
                                                    'The Hash id of a search engine in your Doofinder Account.',
                                                    'doofinder_for_wp'
                                                ); ?></span></span>

        <input type="text" name="<?php echo $option_name; ?>" class="widefat" <?php if ($saved_value) : ?> value="<?php echo $saved_value; ?>" <?php endif; ?>>

    <?php
    }

    /**
     * Print HTML for the "API Host" option.
     *
     * @param string $option_name
     */
    private function render_html_update_on_save($option_name)
    {
        $saved_value = Settings::get_update_on_save($this->language->get_current_language());
        $schedules = wp_get_schedules();
        //Sort by interval
        uasort($schedules, function ($a, $b) {
            return $a['interval'] - $b['interval'];
        });
    ?>

        <span class="doofinder-tooltip">
            <span>
                <?php _e('You can select the time interval at which the update on save is launched.', 'doofinder_for_wp'); ?>
            </span>
        </span>
        <select name="<?php echo $option_name; ?>" class="widefat">
            <?php
            foreach ($schedules as $key => $schedule) {
                if (strpos($key, 'wp_doofinder') === 0) {
                    $selected = $saved_value === $key ? " selected='selected' " : "";
                    echo '<option value="' . $key . '" ' . $selected . '>' . $schedule['display'] . '</option>';
                }
            }
            ?>
        </select>
        <a id="force-update-on-save" href="#" class="button-secondary update-on-save-btn update-icon">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Update now!', 'wordpress-doofinder'); ?>
        </a>
        <span class="update-result-wrapper"></span>
    <?php
    }

    /**
     * Render a checkbox allowing user to enable / disable the JS layer.
     *
     * @param string $option_name
     */
    private function render_html_enable_js_layer($option_name)
    {
        $saved_value = get_option($option_name);

    ?>
        <span class="doofinder-tooltip">
            <span>
                <?php _e('Enables or disables the Doofinder Search Bar.', 'doofinder_for_wp'); ?>
            </span>
        </span>

        <label class="df-toggle-switch">
            <input type="checkbox" name="<?php echo $option_name; ?>" <?php if ($saved_value) : ?> checked <?php endif; ?>>
            <span class="toggle-slider"></span>
        </label>
    <?php
    }

    /**
     * Render a checkbox allowing user to enable / disable the JS layer.
     *
     * @param string $option_name
     */
    private function render_html_load_js_layer_from_doofinder($option_name)
    {
        $saved_value = get_option($option_name);

    ?>
        <span class="doofinder-tooltip"><span><?php _e(
                                                    'The script is obtained from Doofinder servers instead of from the JS Layer Script field.',
                                                    'doofinder_for_wp'
                                                ); ?></span></span>
        <label>
            <input type="checkbox" name="<?php echo $option_name; ?>" <?php if ($saved_value) : ?> checked <?php endif; ?>>

            <?php _e('Load JS Layer directly from Doofinder', 'doofinder_for_wp'); ?>
        </label>

    <?php
    }

    /**
     * Render the textarea containing Doofinder JS Layer code.
     *
     * @param string $option_name
     */
    private function render_html_js_layer($option_name)
    {
        $saved_value = get_option($option_name);

    ?>
        <span class="doofinder-tooltip"><span><?php _e(
                                                    'Paste here the JS Layer code obtained from Doofinder.',
                                                    'doofinder_for_wp'
                                                ); ?></span></span>
        <textarea name="<?php echo $option_name; ?>" class="widefat" rows="16"><?php

                                                                                if ($saved_value) {
                                                                                    echo wp_unslash($saved_value);
                                                                                }

                                                                                ?></textarea>

    <?php
    }


    /**
     * Render the inputs for Additional Attributes. This is a table
     * of inputs and selects where the user can choose any additional
     * fields to add to the exported data.
     *
     * @param string $option_name
     */
    private function render_html_additional_attributes()
    {
        $saved_attributes = get_option(Settings::$custom_attributes_option);
    ?>
        <table class="doofinder-for-wp-attributes">
            <thead>
                <tr>
                    <th><?php _e('Attribute', 'doofinder_for_wp'); ?></th>
                    <th><?php _e('Field', 'doofinder_for_wp'); ?></th>
                    <th colspan="10"><?php _e('Action', 'doofinder_for_wp'); ?></th>
                    <th colspan="100%"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($saved_attributes)) {
                    foreach ($saved_attributes as $index => $attribute) {
                        $this->render_html_single_additional_attribute(
                            Settings::$custom_attributes_option,
                            $index,
                            $attribute
                        );
                    }
                }

                $this->render_html_single_additional_attribute(
                    Settings::$custom_attributes_option,
                    'new'
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
     * @param string $option_name
     * @param string|int $index
     * @param ?array $attribute
     */
    private function render_html_single_additional_attribute($option_name, $index, $attribute = null)
    {
        $option_groups = Settings::get_additional_attributes_options();

    ?>
        <tr>
            <td>


                <?php
                $type = ($attribute !== null) ? $attribute['type'] : null;
                if ($type === 'metafield' && $index !== "new") : ?>
                    <input class="df-attribute-text" type="text" name="<?php echo $option_name; ?>[<?php echo $index; ?>][attribute]" <?php if ($attribute) : ?> value="<?php echo $attribute['attribute']; ?>" <?php endif; ?> />
                <?php else : ?>
                    <select class="df-attribute-select df-select-2" name="<?php echo $option_name; ?>[<?php echo $index; ?>][attribute]" required>
                        <option disabled <?php echo ($index === "new") ? "selected" : ""; ?>>- <?php _e('Select an attribute', 'doofinder_for_wp'); ?> -</option>
                        <?php foreach ($option_groups as $group_id => $group) : ?>
                            <optgroup label="<?php echo $group['title']; ?>">
                                <?php foreach ($group['options'] as $id => $attr) : ?>
                                    <option value="<?php echo $id; ?>" <?php if ($attribute && $attribute['attribute'] === $id) : ?> selected="selected" <?php endif; ?> <?php if (isset($attr['field_name']) && !empty($attr['field_name'])) : ?> data-field-name="<?php echo $attr['field_name']; ?>" <?php endif; ?> data-type="<?php echo $attr['type']; ?>">
                                        <?php echo $attr['title']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>

                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </td>

            <td>
                <input id="df-field-text-<?php echo $index; ?>" class="df-field-text" type="text" name="<?php echo $option_name; ?>[<?php echo $index; ?>][field]" <?php if ($attribute) : ?> value="<?php echo $attribute['field']; ?>" <?php endif; ?> />
                <input class="df-field-type" type="hidden" name="<?php echo $option_name; ?>[<?php echo $index; ?>][type]" <?php if ($attribute) : ?> value="<?php echo $attribute['type']; ?>" <?php endif; ?> />
            </td>

            <td>
                <?php if ($index === "new") : ?>
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

    private function render_html_image_size_field($option_name)
    {
        $current_value = Settings::get_image_size();
        $image_sizes = $this->_get_all_image_sizes();
        $options = [];
        foreach ($image_sizes as $id => $image_size) {
            $name = $id . ' - ' . $image_size['width'] . ' x ' .  $image_size['height'];
            $name = $id === 'medium' ? $name . ' (' . __('Recommended', 'doofinder_for_wp') . ')' : $name;
            $options[] = [
                'value' => $id,
                'name' => $name
            ];
        }

        $this->_render_select_field('df-image-size',  $option_name, $options, ['df-image-size-select'], $current_value);
    }

    /**
     * Get all the registered image sizes along with their dimensions
     *
     * @global array $_wp_additional_image_sizes
     *
     * @return array $image_sizes The image sizes
     */
    private function _get_all_image_sizes()
    {
        global $_wp_additional_image_sizes;

        $default_image_sizes = get_intermediate_image_sizes();

        foreach ($default_image_sizes as $size) {
            $image_sizes[$size]['width'] = intval(get_option("{$size}_size_w"));
            $image_sizes[$size]['height'] = intval(get_option("{$size}_size_h"));
            $image_sizes[$size]['crop'] = get_option("{$size}_crop") ? get_option("{$size}_crop") : false;
        }

        if (isset($_wp_additional_image_sizes) && count($_wp_additional_image_sizes)) {
            $image_sizes = array_merge($image_sizes, $_wp_additional_image_sizes);
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
     * @param array $options The available options. Each option should have the keys value and name.
     * @param array $classes (Optional) The classes for the select field.
     * @param string $selected (Optional) The key of the selected option.
     * @param string $default_option (Optional) A default option name to add to your select. This option will appear as disabled
     * @return void
     */
    private function _render_select_field($id, $name, $options, $classes = [], $selected = NULL, $default_option = NULL)
    {
        //Add default class
        $classes[] = 'df-select';
        $classes = implode(" ", array_unique($classes));
    ?>
        <select id="<?php echo $id; ?>" name="<?php echo $name; ?>" class="<?php echo $classes; ?>">
            <?php if (!empty($default_option)) : ?>
                <option disabled><?php echo $default_option; ?></option>

            <?php endif; ?>
            <?php foreach ($options as $option) {
            ?>
                <option value="<?php echo $option['value']; ?>" <?php echo $selected === $option['value'] ? "selected" : "";  ?>><?php echo $option['name']; ?></option>
            <?php
            }
            ?>
        </select>
<?php
    }
}
