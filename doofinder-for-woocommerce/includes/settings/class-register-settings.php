<?php

namespace Doofinder\WP\Settings;

use Doofinder\WP\Multilanguage\Language_Plugin;
use Doofinder\WP\Setup_Wizard;
use Doofinder\WP\Log;

defined('ABSPATH') or die();

/**
 * Contains all settings registration / all calls to Settings API.
 *
 * @property Language_Plugin $language
 */
trait Register_Settings
{

    /**
     * Create settings page.
     *
     * This function registers all settings fields and holds the names
     * of all options.
     *
     * @since 1.0.0
     */
    private function add_plugin_settings()
    {
        add_action('admin_init', function () {
            // When saving settings make sure not to register settings if we are not
            // saving our own settings page. If the current action is called on
            // the settings page of another plugin it might cause conflicts.
            if (
                // If we are saving the settings...
                $_SERVER['REQUEST_METHOD'] === 'POST'
                && (
                    // ...and "option_page" is either not present...
                    !isset($_POST['option_page'])

                    // ...or is set to something else than our custom page.
                    || $_POST['option_page'] !== self::$top_level_menu
                )
            ) {
                return;
            }

            // Figure out which tab is open / which tab is being saved.
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $selected_tab = $_POST['doofinder_for_wp_selected_tab'];
            } elseif (isset($_GET['tab'])) {
                $selected_tab = $_GET['tab'];
            } else {
                $selected_tab = array_keys(self::$tabs)[0];
            }

            if (!isset(self::$tabs[$selected_tab])) {
                return;
            }

            call_user_func([$this, self::$tabs[$selected_tab]['fields_cb']]);
        });
    }

    /**
     * Section 1 / tab 1 fields.
     *
     * IDE might report this as unused, because it's dynamically called.
     *
     * @see Settings::$tabs
     */
    private function add_general_settings()
    {
        $field_id = 'doofinder-for-wp-general';

        add_settings_section(
            $field_id,
            __('General Settings', 'doofinder_for_wp'),
            function () {
?>
            <p class="description"><?php _e(
                                        'The following options allow to identify you and your search engine in Doofinder servers.',
                                        'doofinder_for_wp'
                                    ); ?></p>
        <?php
            },
            self::$top_level_menu
        );

        // Enable JS Layer
        $enable_js_layer_option_name =
            $this->language->get_option_name('doofinder_for_wp_enable_js_layer');
        add_settings_field(
            $enable_js_layer_option_name,
            __('Enable Doofinder Layer', 'doofinder_for_wp'),
            function () use ($enable_js_layer_option_name) {
                $this->render_html_enable_js_layer($enable_js_layer_option_name);
            },
            self::$top_level_menu,
            $field_id
        );

        register_setting(self::$top_level_menu, $enable_js_layer_option_name);

        // API Key
        $api_key_option_name = 'doofinder_for_wp_api_key';
        add_settings_field(
            $api_key_option_name,
            __('Api Key', 'doofinder_for_wp'),
            function () use ($api_key_option_name) {
                $this->render_html_api_key($api_key_option_name);
            },
            self::$top_level_menu,
            $field_id
        );

        register_setting(self::$top_level_menu, $api_key_option_name, array($this, 'validate_api_key'));

        // API Host
        $api_host_option_name = 'doofinder_for_wp_api_host';
        add_settings_field(
            $api_host_option_name,
            __('Server', 'doofinder_for_wp'),
            function () use ($api_host_option_name) {
                $this->render_html_api_host($api_host_option_name);
            },
            self::$top_level_menu,
            $field_id,
            [
                'class' => 'hidden'
            ]
        );

        register_setting(self::$top_level_menu, $api_host_option_name, array($this, 'validate_api_host'));

        // Search engine hash
        $search_engine_hash_option_name =
            $this->language->get_option_name('doofinder_for_wp_search_engine_hash');
        add_settings_field(
            $search_engine_hash_option_name,
            __('Search Engine HashID', 'doofinder_for_wp'),
            function () use ($search_engine_hash_option_name) {
                $this->render_html_search_engine_hash($search_engine_hash_option_name);
            },
            self::$top_level_menu,
            $field_id
        );

        register_setting(self::$top_level_menu, $search_engine_hash_option_name, array(
            $this,
            'validate_search_engine_hash',
        ));

        // Update on save
        $update_on_save_option_name = $this->language->get_option_name('doofinder_for_wp_update_on_save');
        add_settings_field(
            $update_on_save_option_name,
            __('Update on save', 'doofinder_for_wp'),
            function () use ($update_on_save_option_name) {
                $this->render_html_update_on_save($update_on_save_option_name);
            },
            self::$top_level_menu,
            $field_id
        );

        register_setting(self::$top_level_menu, $update_on_save_option_name, array($this, 'validate_update_on_save'));


        // JS Layer
        $js_layer_option_name =
            $this->language->get_option_name('doofinder_for_wp_js_layer');
        add_settings_field(
            $js_layer_option_name,
            __('JS Layer Script', 'doofinder_for_wp'),
            function () use ($js_layer_option_name) {
                $this->render_html_js_layer($js_layer_option_name);
            },
            self::$top_level_menu,
            $field_id
        );

        register_setting(self::$top_level_menu, $js_layer_option_name);
    }

    private function add_data_settings()
    {
        $field_id = 'doofinder-for-wp-data';

        add_settings_section(
            $field_id,
            __('Data Settings', 'doofinder_for_wp'),
            function () {
        ?>
            <p class="description"><?php _e(
                                        'The following options allow you to set up which data would you like to index',
                                        'doofinder_for_wp'
                                    ); ?></p>
<?php
            },
            self::$top_level_menu
        );

        // Custom Attributes
        $additional_attributes_option_name = $this->language->get_option_name('doofinder_for_wp_custom_attributes');
        add_settings_field(
            $additional_attributes_option_name,
            __('Custom Attributes', 'doofinder_for_wp'),
            function () use ($additional_attributes_option_name) {
                $this->render_html_additional_attributes($additional_attributes_option_name);
            },
            self::$top_level_menu,
            $field_id
        );

        register_setting(self::$top_level_menu, $additional_attributes_option_name, array($this, 'sanitize_additional_attributes'));
    }

    /**
     * Add top level menu.
     *
     * @since 1.0.0
     */
    private function add_settings_page()
    {
        add_action('admin_menu', function () {
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
        });
    }

    /**
     * Validate api key.
     *
     * @param string
     *
     * @return string|null
     */
    function validate_api_key($input)
    {
        if (null == $input) {
            add_settings_error(
                'doofinder_for_wp_messages',
                'doofinder_for_wp_message_api_key',
                __('API Key is mandatory.', 'doofinder_for_wp')
            );
        }

        /**
         * Old API keys use prefixes like eu1- and us1-,
         * in api 2.0 there aren't needed.
         */
        if (strpos($input, '-')) {
            return substr($input, 4);
        } else {
            return $input;
        }
    }

    /**
     * Validate api host.
     *
     * @param string
     *
     * @return string
     */
    function validate_api_host($input)
    {
        if (null == $input) {
            add_settings_error(
                'doofinder_for_wp_messages',
                'doofinder_for_wp_message_api_host',
                __('API Host is mandatory.', 'doofinder_for_wp')
            );
        }

        /**
         * New API host must include https:// protocol.
         */
        if (!empty($input)) {
            $url = parse_url($input);

            if ($url['scheme'] !== 'https' && $url['scheme'] !== 'http') {
                return 'https://' . $input;
            } elseif ($url['scheme'] == 'http') {
                return 'https://' . substr($input, 7);
            } else {
                return $input;
            }
        }
    }

    /**
     * Validate search engine hash.
     *
     * @param string $input
     *
     * @return string|null $input
     */
    public function validate_search_engine_hash($input)
    {
        if (null == $input) {
            add_settings_error(
                'doofinder_for_wp_messages',
                'doofinder_for_wp_message_search_engine_hash',
                __('HashID is mandatory.', 'doofinder_for_wp')
            );
        }

        return $input;
    }

    /**
     * Validate api host.
     *
     * @param string
     *
     * @return string|null
     */
    function validate_update_on_save($input)
    {
        if (null == $input) {
            add_settings_error(
                'doofinder_for_wp_messages',
                'doofinder_for_wp_message_update_on_save',
                __('Update on save is mandatory.', 'doofinder_for_wp')
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
     * @param array $input
     *
     * @return array
     */
    public function sanitize_additional_attributes($input)
    {
        $output = array();

        // We want to save a regular array containing all attributes,
        // but what we send from the frontend is an associative array
        // (because it has "new" entry).
        // Convert data from frontend to nicely-indexed regular array,
        // removing all the records that we want to delete, and those
        // with empty "field" value along the way.
        foreach ($input as $attribute) {
            if (!$attribute['field']) {
                continue;
            }

            if (isset($attribute['delete']) && $attribute['delete']) {
                continue;
            }

            $output[] = $attribute;
        }

        return $output;
    }
}
