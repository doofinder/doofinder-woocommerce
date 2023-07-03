<?php

namespace Doofinder\WP;

use Doofinder\WP\Multilanguage\Language_Plugin;
use Doofinder\WP\Multilanguage\Multilanguage;
use Doofinder\WP\Settings;
use Doofinder\WP\Helpers;
use Doofinder\WP\Log;
use Doofinder\WP\Api\Store_Api;
use Exception;

class Setup_Wizard
{

    /**
     * Singleton instance of this class.
     *
     * @var self
     */
    private static $instance;

    /**
     * Name of the option determining whether or not setup wizard
     * was already performed.
     *
     * @var string
     */
    private static $wizard_done_option = 'doofinder_setup_wizard_done';

    /**
     * Name of the option determining whether or not setup wizard
     * notice should be displayed to the user.
     *
     * @var string
     */
    private static $wizard_show_notice_option = 'doofinder_setup_wizard_show_notice';

    /**
     * Name of the option determining whether or not indexing notice should be
     * displayed to the user.
     *
     * @var string
     */
    private static $wizard_show_indexing_notice_option = 'doofinder_setup_wizard_show_indexing_notice';

    /**
     * Name of the option determining whether or not setup wizard
     * should be displayed to the user.
     *
     * @var string
     */
    private static $wizard_active_option = 'doofinder_setup_wizard_active';

    /**
     * Name of the option storing the current step of the wizard.
     *
     * @var string
     */
    public static $wizard_step_option = 'doofinder_setup_wizard_step';

    /**
     * Name of the option storing the random token for step 1 verification.
     *
     * @var string
     */
    private static $wizard_request_token = 'doofinder_setup_wizard_token';

    /**
     * Name of the migration notice
     *
     * @var string
     */
    public static $wizard_migration_notice_name = 'doofinder_show_the_migration_complete_notice';

    /**
     * Name of the option storing settgins migration info
     *
     * @var string
     */
    public static $wizard_migration_option = 'doofinder_v2_migration_status';

    /**
     * Name of the transient controling wheter to show migration notice
     *
     * @var string
     */
    public static $wizard_migration_notice_transient = 'doofinder_migration_complete';

    /**
     * Name of the option storing the current status of using wizard.
     * Possible values are: 'pending', 'started' or 'finished'.
     *
     * @var string
     */
    public static $wizard_status = 'doofinder_setup_wizard_status';

    /**
     * 'Pending' status of wizard - if the user never started the wizard (and it's not finished).
     *
     * @var string
     */
    public static $wizard_status_pending = 'pending';

    /**
     * 'Started' status of wizard - if the user started the wizard but it's not finished.
     *
     * @var string
     */
    public static $wizard_status_started = 'started';

    /**
     * 'Finished' status of wizad - if the user finished the wizard successfully or data has been indexed via API
     * (indicating a manual configuration after skipping the wizard).
     *
     * @var string
     */
    public static $wizard_status_finished = 'finished';

    /**
     * How many steps does the wizard have.
     *
     * @var int
     */
    private static $no_steps = 3;

    /**
     * Instance of the class handling the multilanguage.
     *
     * @var Language_Plugin
     */
    public $language;

    /**
     * Current active language
     *
     * @var string
     */
    public $active_lang;

    /**
     * List with all languages
     *
     * @var array
     */
    public $languages;

    /**
     * Contains information whether we should process all languages at once
     *
     * @var bool
     */
    public $process_all_languages = true;

    /**
     * Errors to display for the form fields.
     *
     * Index is the form field name, value is the error text.
     *
     * @var array[string => string]
     */
    private $errors = array();

    /**
     * Instance of a class used to log to a file.
     *
     * @var Log
     */
    private $log;

    /**
     * Admin path used to get the connection details
     *
     * @var string
     */
    const ADMIN_PATH = "http://admin.doofinder.com";

    public function __construct()
    {

        $this->log                         = new Log();
        $this->language                    = Multilanguage::instance();
        $this->process_all_languages     = empty($this->language->get_languages()) ? false : true;

        //$this->log->log("Setup Wizard Construct");

        // Load erros stored in cookies and delete after
        if (isset($_COOKIE['doofinderError'])) {
            foreach ($_COOKIE['doofinderError'] as $key => $value) {
                $this->errors[$key] = $value;

                // Delete error cookie when reloading wizard page
                if (self::is_wizard_page() && !\wp_doing_ajax()) {
                    $this->log->log("Deleting Error Cookies");
                    unset($_COOKIE['doofinderError'][$key]);
                    setcookie("doofinderError[{$key}]", null, -1, '/');
                }
            }
        }

        if (self::is_wizard_page() && !\wp_doing_ajax()) {
            $this->set_wizard_errors([]);
        }

        if (current_user_can('manage_options')) {
            add_action('admin_menu', array($this, 'admin_menus'));

            $this->register_ajax_action();
        }

        self::add_notices();
    }

    /**
     * Check if on setup wizard page
     *
     * @return bool
     */

    public static function is_wizard_page()
    {
        return (is_admin() && isset($_GET['page']) && $_GET['page'] === 'df-setup');
    }

    /**
     * Callback for WP rest api endpoint for connecting doofinder account
     */
    public static function connect()
    {

        $setup_wizard = self::instance();
        $setup_wizard->log->log('Setup Wizard - Connect');
        return $setup_wizard->process_step_2(true);
    }

    /**
     * Create (or retrieve, if already exists), the singleton
     * instance of this class.
     *
     * @return self
     */
    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register an ajax action that processes wizard step 2 and creates search engines.
     *
     *
     * @since 1.0.0
     */
    private function register_ajax_action()
    {

        add_action('wp_ajax_doofinder_check_data', function () {
            self::check_data();
        });

        add_action('wp_ajax_doofinder_set_connection_data', function () {
            $this->log->log('Setup Wizard - Connect');
            $this->process_step_2(true);
            $resp = ["success" => Settings::is_api_configuration_complete()];

            if (!empty($this->errors['wizard-step-2'])) {
                $resp = [
                    "success" => false,
                    "errors" => $this->errors['wizard-step-2']
                ];
            }

            die(json_encode($resp));
        });
    }


    /**
     * Check if we should enable the setup wizard, or if it's
     * not necessary (because for example, it's been already performed).
     *
     * @return bool
     */
    public static function should_activate()
    {
        $after_wizard = get_option(self::$wizard_done_option);

        return !(bool) $after_wizard;
    }

    /**
     * Check if we should show admin notice about wizard setup, or if it's
     * not necessary (because for example, user dissmised it or completed setup).
     *
     * @return bool
     */
    public static function should_show_notice()
    {
        $show_notice = get_option(self::$wizard_show_notice_option);

        $config_complete = Settings::is_configuration_complete();
        return ((bool) $show_notice) && !$config_complete;
    }

    /**
     * Check if we should show admin notice about indexing status, or if it's
     * not necessary.
     *
     * @return bool
     */
    public static function should_show_indexing_notice()
    {
        $multilanguage = Multilanguage::instance();
        $show_notice = (bool) get_option(self::$wizard_show_indexing_notice_option, 0);
        $lang = $multilanguage->get_current_language();
        $indexing_status = Settings::get_indexing_status($lang);
        $res = $show_notice && $indexing_status === "processing";
        return $res;
    }

    /**
     * Activate the setup wizard.
     *
     * When it is active (the option is set to truthy value) users that can
     * manage options will see custom screen (the setup wizard) instead
     * of admin panel.
     */
    public static function activate($notice = false)
    {
        update_option(self::$wizard_active_option, true);

        if ($notice) {
            update_option(self::$wizard_show_notice_option, true);
        }
    }

    /**
     * Deactivate the setup wizard and set the flag making sure
     * to not display it anymore.
     */
    public static function deactivate()
    {
        update_option(self::$wizard_active_option, false);
        update_option(self::$wizard_done_option, true);
        update_option(self::$wizard_show_notice_option, false);
        update_option(self::$wizard_show_indexing_notice_option, false);
    }

    /**
     * Dissmiss the admin setup wizard notice and set the flag making sure
     * to not display it anymore.
     */
    public static function dissmiss_notice()
    {
        update_option(self::$wizard_show_notice_option, false);
    }

    /**
     * Is the setup wizard active (should we display it)?
     *
     * @return bool
     */
    public static function is_active()
    {
        return (bool) get_option(self::$wizard_active_option);
    }

    /**
     * Generate token used for login/signup via popup
     *
     * @return string token
     */
    public function generateToken()
    {
        $time = time();
        $rand = rand();
        return md5("$time$rand");
    }

    /**
     * Save token used for login/signup in db
     *
     * @param string $token
     */
    public function saveToken($token)
    {
        update_option(self::$wizard_request_token, $token);
    }

    /**
     * Get token used for login/signup saved in db
     *
     * @return string $token
     */
    public function getToken()
    {
        return get_option(self::$wizard_request_token);
    }

    /**
     * Get the absolute path of the URL to setup wizard that Doofinder will
     * use for the POST request. The path will be appended to the origin domain.
     *
     * @return string path
     */
    public function getReturnPath()
    {
        $setup_wizard_url = get_site_url(null, 'wp-json/doofinder-for-wp/v1/connect/');
        return $setup_wizard_url;
    }

    /**
     * What the current step of the wizard is? This is the last step
     * that the user have seen and not submitted yet.
     *
     * @return int
     */
    public static function get_step()
    {
        $step = get_option(self::$wizard_step_option);
        if (!$step) {
            $step = 1;
        }

        return (int) $step;
    }

    /**
     * Move to the next step. If this was the last step
     * deactivate the Setup Wizard.
     */
    public static function next_step($step = null, $redirect = true)
    {

        if ($step === null) {
            $current_step = self::get_step();
            $current_step++;
            $redirect_url = self::get_url();
        } else {
            $current_step = $step;
            $redirect_url = self::get_url(['step' => $step]);
        }

        // If on last step deactivate wizard and redirect to settings page

        if ($current_step > self::$no_steps) {
            self::remove_notice();

            // Reset wizard to step 1
            update_option(self::$wizard_step_option, 1);

            //Show the indexing notice
            update_option(self::$wizard_show_indexing_notice_option, 1);

            //Set the indexing status to processing
            self::set_indexing_status('processing');

            // Update wizard status to finished if configuration is complete
            if (Settings::is_configuration_complete()) {
                update_option(self::$wizard_status, self::$wizard_status_finished);
            }

            //self::js_go_to_step(3);

?>
            <script>
                document.location.href = '<?php echo Settings::get_url(); ?>';
            </script>
        <?php

            /*
			if ($redirect) {
				wp_safe_redirect(Settings::get_url());
				die();
			}*/
        }

        // Else update step option and move to the next step

        update_option(self::$wizard_step_option, $current_step);

        if ($redirect) {
            wp_safe_redirect($redirect_url);
            die();
        }
    }

    /**
     * Sets the indexing status to the given value for all languages.
     *
     * @param string $status
     * @return void
     */
    private static function set_indexing_status($status)
    {
        $multilanguage = Multilanguage::instance();
        $languages = $multilanguage->get_languages();

        if (is_null($languages)) {
            Settings::set_indexing_status($status);
        } else {
            foreach ($languages as $lang => $value) {
                if ($lang === $multilanguage->get_base_language()) {
                    $lang = '';
                }
                Settings::set_indexing_status($status, $lang);
            }
        }
    }

    /**
     * Show wizard
     *
     * @return void
     */
    private function admin_page_init()
    {

        if (empty($_GET['page']) || 'df-setup' !== $_GET['page']) {
            return;
        }

        global $sitepress;

        if ($sitepress) {
            $sitepress->switch_lang('all');
            $this->active_lang = $sitepress->get_current_language();
        } else {
            $this->active_lang = '';
        }

        // Show wizard, if active.
        if (self::is_active()) {
            $this->show_wizard();
        } else {
            wp_safe_redirect(Settings::get_url());
            die();
        }
    }

    /**
     * Add admin page for setup wizard.
     *
     * @return void
     */
    public function admin_menus()
    {
        add_dashboard_page('', '', 'manage_options', 'df-setup', $this->admin_page_init());
    }

    /**
     * Get url of the setup wizard admin page,
     * you can add url parameters via $args
     *
     * @param array $args Associative array with query parameters key -> value
     *
     * @return string
     */
    public static function get_url($args = [])
    {

        $url = admin_url('admin.php?page=df-setup');

        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }

        return $url;
    }

    /**
     * Check if setting exist in db and return response
     */
    public static function check_data()
    {

        $has_error = FALSE;
        $error = '';
        $errors = self::get_wizard_errors();
        if (isset($errors['wizard-step-2']) && !empty($errors['wizard-step-2'])) {
            $has_error = true;
            $error = $errors['wizard-step-2'];
        }

        if ($has_error) {
            $status = 'error';
        } elseif (Settings::is_api_configuration_complete()) {
            $status = 'saved';
            // Check if step was already set to 3, if not do it
            if (self::get_step() !== 3) {
                self::next_step(3, false);
            }
        } else {
            $status = 'not-saved';
        }

        wp_send_json_success([
            'status' => $status,
            'error' =>  $error
        ]);
    }

    /**
     * Wizard setup notice html
     *
     * @param bool $settings
     *
     * @return string
     */
    public static function get_setup_wizard_notice_html($settings = true)
    {

        $message = __('Please, run setup wizard to finish installation.', 'wordpress-doofinder');
        if (Settings::is_configuration_complete()) {
            $message = __('Looks like Doofinder is already set up. You can review the configuration in the settings or run the setup wizard.', 'wordpress-doofinder');
        }

        //Hide the settings button in settings page
        if (@$_GET['page'] === "doofinder_for_wp") {
            $settings = false;
        }

        ob_start();
        ?>
        <div class="notice notice-success is-dismissible">
            <div id="message" class="wordpress-message df-notice df-notice-setup-wizard">
                <div class="df-notice-row">
                    <div class="df-notice-col logo">
                        <figure class="logo" style="width:5rem;height:auto;float:left;margin:.5em 0;margin-right:0.75rem;">
                            <img src="<?php echo Doofinder_For_WordPress::plugin_url() . 'assets/svg/imagotipo1.svg'; ?>" />
                        </figure>
                    </div>
                    <div class="df-notice-col content">
                        <h3><?php _e('Welcome to Doofinder', 'wordpress-doofinder') ?></h3>
                        <p>
                            <?php echo $message ?>
                        </p>
                    </div>
                    <div class="df-notice-col extra">
                        <div class="submit">
                            <a href="<?php echo self::get_url(); ?>" class="button-primary button-setup-wizard"><?php _e('Run Setup Wizard', 'wordpress-doofinder'); ?></a>
                            <?php if ($settings) : ?>
                                &nbsp;<a class="button-secondary button-settings" href="<?php echo Settings::get_url(); ?>"><?php _e('Go to Settings', 'wordpress-doofinder'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php
        $html = ob_get_clean();
        return $html;
    }

    /**
     * Indexing Status notice html
     *
     * @param bool $settings
     *
     * @return string
     */
    public static function get_indexing_status_notice_html()
    {
        ob_start();
    ?>
        <div class="notice notice-success is-dismissible">
            <div id="message" class="wordpress-message df-notice indexation-status processing">
                <div class="status-processing">
                    <div class="df-notice-row flex-end">
                        <div class="df-notice-col logo">
                            <figure class="logo" style="width:5rem;height:auto;float:left;margin:.5em 0;margin-right:0.75rem;">
                                <img src="<?php echo Doofinder_For_WordPress::plugin_url() . 'assets/svg/imagotipo1.svg'; ?>" />
                            </figure>
                        </div>
                        <div class="df-notice-col content">
                            <h3><?php _e("Doofinder Indexing Status", 'wordpress-doofinder'); ?></h3>
                            <p><?php _e("The product feed is being processed. Depending on the size of the store's product catalogue, this process may take a few minutes.", 'wordpress-doofinder'); ?></p>
                            <p><strong><?php _e("Your products may not appear correctly updated in search results until the process is complete.", 'wordpress-doofinder'); ?></strong></p>

                        </div>
                        <div class="df-notice-col extra align-center">
                            <figure class="logo" style="width:5rem;height:auto;float:left;margin:.5em 0;margin-right:0.75rem;">
                                <div class="spinner-wrapper">
                                    <div class="lds-spinner">
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                        <div></div>
                                    </div>
                                </div>
                            </figure>
                        </div>
                    </div>
                </div>
                <div class="status-processed">
                    <div class="df-notice-row flex-end">
                        <div class="df-notice-col logo">
                            <figure class="logo" style="width:5rem;height:auto;float:left;margin:.5em 0;margin-right:0.75rem;">
                                <img src="<?php echo Doofinder_For_WordPress::plugin_url() . 'assets/svg/imagotipo1.svg'; ?>" />
                            </figure>
                        </div>
                        <div class="df-notice-col content">
                            <h3><?php _e("Doofinder Indexing Status", 'wordpress-doofinder'); ?></h3>
                            <p><?php _e("The product feed has been processed.", "doofinder_for_wp"); ?></p>
                        </div>
                        <div class="df-notice-col extra align-center">
                            <figure class="logo" style="width:5rem;height:auto;float:left;margin:.5em 0;margin-right:0.75rem;">
                                <div class="success-icon-wrapper">
                                    <img src="wp-content/plugins/doofinder/assets/img/green_checkmark.png">
                                </div>
                            </figure>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php
        $html = ob_get_clean();
        return $html;
    }


    /**
     * Wizard setup notice html
     *
     * @param bool $settings
     *
     * @return string
     */
    public static function get_setup_wizard_migration_notice_html()
    {
        ob_start();
    ?>
        <div class="notice notice-success is-dismissible">
            <div id="message" class="wordpress-message df-notice migration-complete">
                <div class="df-notice-row">
                    <div class="df-notice-col logo">
                        <figure class="logo" style="width:5rem;height:auto;float:left;margin:.5em 0;margin-right:0.75rem;">
                            <img src="<?php echo Doofinder_For_WordPress::plugin_url() . 'assets/svg/imagotipo1.svg'; ?>" />
                        </figure>
                    </div>
                    <div class="df-notice-col content">
                        <h3><?php _e('Migration status', 'wordpress-doofinder') ?></h3>
                        <p>
                            <?php _e('Doofinder settings have been migrated successfully.', 'wordpress-doofinder') ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php
        $html = ob_get_clean();
        return $html;
    }

    /**
     * Get setup wizard recongigure button html
     *
     * @param bool $settings
     *
     * @return string
     */
    public static function get_configure_via_setup_wizard_button_html()
    {

        $html = '';

        //if (!Settings::is_configuration_complete()) :

        ob_start();

    ?>
        <p class="doofinder-button-setup-wizard" style="width:100px;float:right;position:relative;top:-68px;">
            <a href="<?php echo self::get_url(); ?>" class="button-secondary"><?php _e('Setup Wizard', 'wordpress-doofinder'); ?></a>
        </p>
    <?php

        $html = ob_get_clean();

        //endif;

        return $html;
    }


    public static function add_notices()
    {
        add_action('admin_notices', function () {
            if (Setup_Wizard::should_show_indexing_notice()) {
                echo Setup_Wizard::get_indexing_status_notice_html();
            }

            if (Setup_Wizard::should_show_notice()) {
                echo Setup_Wizard::get_setup_wizard_notice_html();
            }
        });
    }

    /**
     * Remove custom Wordpress notice in admin panel
     *
     * @return void
     */
    public static function remove_notice()
    {
        self::dismiss_notice();
    }

    public static function dismiss_notice()
    {
        update_option(self::$wizard_show_notice_option, false);
    }

    public static function dismiss_indexing_notice()
    {
        update_option(Setup_Wizard::$wizard_show_indexing_notice_option, 0);
    }

    /**
     * Display the setup wizard view.
     */
    private function show_wizard()
    {
        if (empty($_GET['page']) || 'df-setup' !== $_GET['page']) {
            return;
        }

        update_option(self::$wizard_status, self::$wizard_status_started);

        include Doofinder_For_WordPress::plugin_path() . '/views/wizard.php';

        // We only want to show our screen, don't give control
        // back to WordPress.
        exit();
    }

    /**
     * Render the HTML of the current wizard step.
     *
     * This is used in the view, so might be displayed
     * as not used in the IDE.
     */
    private function render_wizard_step()
    {

        include Doofinder_For_WordPress::plugin_path() . "/views/wizard-step-all-in-one.php";
    }

    /**
     * Get the error for a given field.
     *
     * This function is used in the views, so might be reported
     * as not used in the IDE.
     *
     * @param string $name
     *
     * @return string
     */
    public function get_error($name)
    {
        if (isset($this->errors[$name])) {
            return $this->errors[$name];
        } elseif (isset($_COOKIE['doofinderError'])) {
            $cookie = $_COOKIE['doofinderError'];
            unset($_COOKIE['doofinderError'][$name]);
            return $cookie;
        }

        return null;
    }

    /**
     * Render error html for a given field.
     *
     * @param string $name
     *
     * @return string
     */
    public function get_errors_html($name)
    {
        $error = $this->get_error($name);
        $error_template = '<div class="error-text">%s</div>';


        $html = '';

        if ($error) {

            if (is_array($error)) {
                foreach ($error as $err) {
                    $html .= sprintf($error_template, $err);
                }
            } else {
                $html .= sprintf($error_template, $error);
            }
        }

        return $html;
    }

    /**
     * A callback for processing installation wizard steps.
     *
     * Each step of the wizard is being handled by its own method.
     */
    private function process_wizard_step($step = null)
    {

        $step = $step ?: self::get_step();
        switch ($step) {
            case 1:
                $this->process_step_1();
                break;

            case 2:
                $this->process_step_2();
                break;

            case 3:
                $this->process_step_3();
                break;
        }
    }

    /**
     * Handle the submission of step 1 - Sector collection
     */
    private function process_step_1($processing = false)
    {
        $is_processing = (isset($_REQUEST['process-step']) && $_REQUEST['process-step'] === '1') || $processing === true;
        $step = 1;
        if (!$is_processing) {
            return;
        }

        $this->log->log('Processing Wizard Step 1 - Processing...');

        $sector = isset($_REQUEST['sector']) ? $_REQUEST['sector'] : null;
        if (!empty($sector)) {
            Settings::set_sector($sector);
            $this->js_go_to_step(2);
        } else {
            $this->add_wizard_step_error($step, 'sector', __('Please select a sector.', 'wordpress-doofinder'));
        }
    }


    /**
     * Handle the submission of step 2 - Login or setup. Save data.
     */
    private function process_step_2($processing = false)
    {

        $is_processing = (isset($_REQUEST['process-step']) && $_REQUEST['process-step'] === '2') || $processing === true;
        $step = 2;

        if (!$is_processing) {
            return;
        }

        $this->log->log('Processing Wizard Step 2 - Processing...');

        if (!$this->is_valid_token($step)) {
            return;
        }

        $api_settings = $this->check_api_settings($step);
        if (!is_array($api_settings)) {
            return;
        }

        extract($api_settings);
        $this->save_api_settings($api_settings);
        $this->log->log('Processing Wizard Step 2 - All data saved');

        $this->log->log('Make call to doomanager to create store');

        self::creating_all_structure();

        // ...and move to the next step.
        self::next_step(3, false);
    }

    /**
     * Create Store and save search_engine in DB.
     */
    private function creating_all_structure()
    {

        // Check if search engines (Hash ID in DB) already exists (for each language if multilang)

        $has_api_keys = self::are_api_keys_present($this->process_all_languages, $this->language);
        $store_data = [];
        if (!$has_api_keys || (is_array($has_api_keys) && Helpers::in_array_r('no-hash', $has_api_keys, true))) {
            // Api keys are missing for some / all languages. We need to create
            // search engine for that language

            // If hash id is missing create store

            // If there's no plugin active we still need to process 1 language.
            if (!Multilanguage::$is_multilang) {
                $has_api_keys[''] = [
                    'hash' => 'no-hash'
                ];
            }

            if (is_array($has_api_keys)) {
                // Create search engine
                $this->log->log('Wizard Step 2 - Try Create the Store');
                $this->log->log('=== Store API CALL === ');
                try {
                    $store_api = new Store_Api();
                    $store_data = $store_api->create_store($has_api_keys);

                    if (array_key_exists('errors', $store_data)) {
                        $message = "";
                        foreach ($store_data['errors'] as $error) {
                            $message .= $error . ". ";
                        }
                        throw new Exception($message);
                    }

                    $this->log->log('Store create result:');
                    $this->log->log(print_r($store_data, true));

                    $this->set_search_engines($store_data['search_engines']);
                    $this->enable_layer($store_data['script']);
                } catch (Exception $exception) {
                    $this->log->log('Wizard Step 2 - Exception');
                    $this->log->log($exception->getMessage());
                    $this->errors['wizard-step-2'] =
                        __(
                            sprintf("Couldn't create Store. Error: %s", $exception->getMessage()),
                            'wordpress-doofinder'
                        );

                    // Send failed ajax response
                    wp_send_json_error(array(
                        'status'  => false,
                        'errors' => [
                            $this->errors['wizard-step-2']
                        ],
                    ));

                    return;
                }
            }
            $this->log->log('Wizard Step 2 - Created Search Engine in db ');
        }
    }

    /**
     * Handle the submit of step 3
     */
    private function process_step_3()
    {
        // Move to the next step. Step that exceeds number of steps to deactivate the wizard.
        self::next_step(self::$no_steps + 1);
    }

    /**
     * Redirect using JS to avoid alredy sent headers issue
     */

    private function js_go_to_step($step)
    {
    ?>
        <script>
            document.location.href = 'admin.php?page=df-setup&step=<?php echo $step; ?>'
        </script>
<?php
    }

    /**
     * Clear all settings in Doofinder Search Tab for each language
     */
    private function clear_all_settings()
    {

        $this->log->log('Clear All Settings');

        // Clear global settings

        Settings::set_api_key('');
        Settings::set_api_host('');

        // If there's no plugin active we still need to process 1 language.
        $languages = $this->language->get_formatted_languages();

        if (!$languages) {
            $languages[''] = '';
        }

        // Clear per language settings

        foreach ($languages as $language_code => $language_name) {
            // Suffix for options.
            // This should be empty for default language, and language code
            // for any other.
            $options_suffix = ($language_code === $this->language->get_base_locale()) ? '' : Helpers::get_language_from_locale($language_code);

            // Search engine data
            Settings::set_search_engine_hash('', $options_suffix);

            // JS Layer
            Settings::disable_js_layer($options_suffix);
            // JS Layer Code
            Settings::set_js_layer('', $options_suffix);

            //Set the indexing status to processing
            Settings::set_indexing_status('processing', $options_suffix);
        }
    }


    private function check_api_settings($step)
    {
        $api_key = $_REQUEST['api_token'] ?? null;
        $api_host = $_REQUEST['admin_endpoint'] ?? null; // i.e: https://eu1-admin.doofinder.com

        if (empty($api_key)) {
            $this->add_wizard_step_error($step, 'api-key', __('API key is missing.', 'wordpress-doofinder'));
        } else {
            $this->remove_wizard_step_error($step, 'api-key');
        }

        if (empty($api_host)) {
            $this->add_wizard_step_error($step, 'api-host', __('API host is missing.', 'wordpress-doofinder'));
        } else {
            $this->remove_wizard_step_error($step, 'api-host');
        }

        if (!empty($this->errors['wizard-step-' . $step])) {
            return FALSE;
        }

        // Api Host should contain 'https://' protocol, i.e. https://eu1-admin.doofinder.com
        if (!preg_match("#^((https?://)|www\.?)#i", $api_host)) {
            $api_host = 'https://' . $api_host;
        }

        return [
            'api_key' => $api_key,
            'api_host' => $api_host,
        ];
    }

    private function is_valid_token($step)
    {
        $token = $_POST['token'] ?? '';
        $saved_token = $this->getToken();

        // Exit early if tokens do not match
        if ($token !== $saved_token) {
            $this->log->log('Processing Wizard Step 2 - Recieved Token - ' . $token);
            $this->log->log('Processing Wizard Step 2 - Saved Token - ' . $saved_token);
            $this->add_wizard_step_error($step, 'token', __('Invalid token', 'wordpress-doofinder'));
            return false;
        } else {
            $this->remove_wizard_step_error($step, 'token');
        }
        return true;
    }

    private function save_api_settings($api_settings)
    {
        extract($api_settings);
        // Check if api key already exists and is the same
        // If api key is different clear all settings
        $saved_api_key = Settings::get_api_key();

        if ($saved_api_key !== $api_key) {
            $this->clear_all_settings();
        }

        Settings::set_api_key($api_key);
        Settings::set_api_host($api_host);
    }


    private function get_wizard_errors()
    {
        return get_option('doofinder_wizard_errors', []);
    }

    private function set_wizard_errors($errors)
    {
        return update_option('doofinder_wizard_errors', $errors);
    }

    private function add_wizard_step_error($step, $field_name, $error)
    {
        $this->log->log('Processing Wizard Step ' . $step . ' - Error - ' . $error);

        $errors = $this->get_wizard_errors();
        $this->errors['wizard-step-' . $step][$field_name] = $error;

        if (!isset($errors['wizard-step-' . $step])) {
            $errors['wizard-step-' . $step] = [];
        }

        $errors['wizard-step-' . $step][$field_name] = $error;
        $this->set_wizard_errors($errors);
    }

    private function remove_wizard_step_error($step, $field_name)
    {
        $errors = $this->get_wizard_errors();
        if (isset($errors['wizard-step-' . $step]) && isset($errors['wizard-step-' . $step][$field_name])) {
            unset($errors['wizard-step-' . $step][$field_name]);
            $this->set_wizard_errors($errors);
        }
    }

    private function set_search_engines($search_engines)
    {
        $log = new Log();

        $currency = self::get_currency();

        foreach ($search_engines as $language => $search_engine) {
            $currency_key = strtoupper($currency);
            //format language to en_US instead of en-US format
            $language = Helpers::format_locale_to_underscore($language);
            $language_key = Helpers::get_language_from_locale($language);
            $is_primary_language = strtolower($this->language->get_base_locale()) === strtolower($language);
            if (!array_key_exists($currency_key, $search_engine)) {
                $currency_key = strtolower($currency);
            }

            if (array_key_exists($currency_key, $search_engine)) {
                $search_engine_hash = $search_engine[$currency_key];
                if (!$this->process_all_languages || $is_primary_language) {
                    $language_key = '';
                }
                $log->log("Setting SE hash for language '$language_key'");
                Settings::set_search_engine_hash($search_engine_hash, $language_key);
            } else {
                $log->log("Couldnt find currency $currency");
            }
        }
    }

    private function enable_layer($script)
    {
        $log = new Log();
        // If there's no plugin active we still need to process 1 language.
        $languages = $this->language->get_formatted_languages();
        $currency = self::get_currency();
        if (!$languages) {
            $languages[''] = '';
        }

        foreach ($languages as $language_code => $language_name) {
            // Suffix for options.
            // This should be empty for default language, and language code
            // for any other.
            $options_suffix = '';

            if ($language_code !== $this->language->get_base_locale()) {
                $options_suffix = Helpers::get_language_from_locale($language_code);
            }

            // Enable JS Layer
            Settings::enable_js_layer($options_suffix);

            //Convert language to hyphen format used by live layer (en-US)
            $language_code = Helpers::format_locale_to_hyphen($language_code);
            $lang_config = "language: '$language_code',\n    currency: '$currency',\n    installationId:";
            $aux_script  = !empty($language_code) ? str_replace("installationId:", $lang_config, $script) : $script;

            $log->log('Installing script for language: ' . $language_code);
            $log->log($aux_script);

            // JS Layer Code
            Settings::set_js_layer($aux_script, $options_suffix);
        }
    }

    private function get_currency()
    {
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            return strtolower(get_woocommerce_currency());
        } else {
            return "eur";
        }
    }

    /**
     * Check if API key, host and search engine hash are set in settings
     * for the current language. Indexing will be impossible if
     * they are missing.
     *
     * @param bool $process_all_languages
     * @param object $language
     *
     * @return mixed
     */
    public static function are_api_keys_present(bool $process_all_languages, $language)
    {


        $api_key = Settings::get_api_key();

        if (!$api_key) {
            return false;
        }

        $api_host = Settings::get_api_host();

        if (!$api_host) {
            return false;
        }

        if ($process_all_languages) {

            $api_keys_array = [];

            foreach ($language->get_languages() as $lang) {
                $code = $lang['locale'];
                $code = $code === $language->get_base_locale() ? '' : Helpers::get_language_from_locale($code);
                $hash = Settings::get_search_engine_hash($code);
                $hash = !$hash ? 'no-hash' : $hash;

                $api_keys_array[$code] = [
                    'lang' => $lang,
                    'hash' => $hash
                ];
            }

            return $api_keys_array;
        } else {
            $hash = Settings::get_search_engine_hash();

            if ($hash) {
                return true;
            } else {
                return false;
            }
        }
    }


    /**
     * Check if we should migrate settings
     *
     * @return bool
     */
    public static function should_migrate()
    {

        $log = new Log();
        $migration_option = get_option(self::$wizard_migration_option);

        // Migration was already done, we should abort
        if ($migration_option === 'completed' || $migration_option === 'failed') {
            //$log->log('Should migrate - Migration already done or not possible');
            return false;
        }

        if (!Settings::get_api_key()) {
            $log->log('Should migrate - Migration possible - Api Key');
            return true;
        }

        if (!Settings::get_api_host()) {
            $log->log('Should migrate - Migration possible - Api Host');
            return true;
        }

        // Migration not necessary
        $log->log('Should migrate - Migration not necessary');
        update_option(self::$wizard_migration_option, 'completed');

        return false;
    }
}
