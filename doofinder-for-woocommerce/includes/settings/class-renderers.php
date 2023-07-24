<?php

namespace Doofinder\WP\Settings;

use Doofinder\WP\Multilanguage\Language_Plugin;
use Doofinder\WP\Multilanguage\No_Language_Plugin;
use Doofinder\WP\Setup_Wizard;
use Doofinder\WP\Settings;

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

            <form action="options.php" method="post">
                <?php

                settings_fields(self::$top_level_menu);
                $this->render_html_current_tab_id();
                do_settings_sections(self::$top_level_menu);
                submit_button('Save Settings');

                ?>
            </form>
            <?php echo Setup_Wizard::get_configure_via_setup_wizard_button_html(); ?>
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
}
