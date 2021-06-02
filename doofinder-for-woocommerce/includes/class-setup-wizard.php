<?php

namespace Doofinder\WC;

use Doofinder\WC\Multilanguage\Language_Plugin;
use Doofinder\WC\Multilanguage\Multilanguage;
use Doofinder\WC\Multilanguage as Multilang;

use Doofinder\WC\Settings\Settings;
use Doofinder\WC\Helpers\Template_Engine;
use Doofinder\WC\Helpers\Helpers;
use Doofinder\Management\ManagementClient;
use Doofinder\WC\Log;
use Doofinder\WC\Api\Api_Factory;
use Doofinder\WC\Api\Api_Wrapper;
use Doofinder\WC\Api\Api_Status;
use Doofinder\WC\Index_Interface;

use Doofinder\GuzzleHttp\Client as GuzzleClient;

use Error;
use Doofinder\Management\Errors\DoofinderError;

class Setup_Wizard {

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
	private static $wizard_done_option = 'doofinder_for_wc_setup_wizard_done';

	/**
	 * Name of the option determining whether or not setup wizard
	 * notice should be displayed to the user.
	 *
	 * @var string
	 */
	private static $wizard_show_notice_option = 'doofinder_for_wc_setup_wizard_show_notice';

	/**
	 * Name of the option determining whether or not setup wizard
	 * should be displayed to the user.
	 *
	 * @var string
	 */
	private static $wizard_active_option = 'doofinder_for_wc_setup_wizard_active';

	/**
	 * Name of the option storing the current step of the wizard.
	 *
	 * @var string
	 */
	public static $wizard_step_option = 'doofinder_for_wc_setup_wizard_step';

	/**
	 * Name of the option storing the random token for step 1 verification.
	 *
	 * @var string
	 */
	private static $wizard_request_token = 'doofinder_for_wc_setup_wizard_token';

	/**
	 * Name of the woocommerce notice shown after plugin activation
	 *
	 * @var string
	 */
	private static $wizard_notice_name = 'doofinder_show_wizard_notice';

	/**
	 * Name of the woocommerce notice shown after plugin activation
	 *
	 * @var string
	 */
	private static $wizard_migration_notice_name = 'doofinder_show_the_migration_complete_notice';

	/**
	 * Name of the option storing settgins migration info
	 *
	 * @var string
	 */
	public static $wizard_migration_option = 'woocommerce_doofinder_migration_status';

	/**
	 * Name of the transient controling wheter to show migration notice
	 *
	 * @var string
	 */
	public static $wizard_migration_notice_transient = 'doofinder_for_wc_migration_complete';
/**
	 * How many steps does the wizard have.
	 *
	 * @var int
	 */
	private static $no_steps = 5;

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
	 * Data containing progress of indexing.
	 *
	 * @var Indexing_Data
	 */
	private $indexing_data;

	/**
	 * Class handling API calls.
	 *
	 * @var Api_Wrapper
	 */
	private $api;

	/**
	 * Should api be disabled for local testing
	 *
	 * @var bool
	 */
	private $disable_api = false;

	/**
	 * Should processing data fail (used for testing)
	 *
	 * @var bool
	 */
	public static $should_fail = false;


	public function __construct() {

		// Get global disable_api_calls flag
		$this->disable_api = Doofinder_For_WooCommerce::$disable_api_calls ?? $this->disable_api;

		$this->log 						= new Log();
		$this->indexing_data 			= Indexing_Data::instance();
		$this->language					= Multilanguage::instance();
		$this->process_all_languages 	= empty($this->language->get_languages()) ? false : true;

		//$this->log->log("Setup Wizard Construct");

		// Load erros stored in cookies and delete after
		if ( isset($_COOKIE['doofinderError'])) {
			foreach ( $_COOKIE['doofinderError'] as $key => $value ) {
				$this->errors[$key] = $value;

				// Delete error cookie when reloading wizard page
				if (self::is_wizard_page() && !\wp_doing_ajax()) {
					$this->log->log("Deleting Error Cookies");
					unset($_COOKIE['doofinderError'][$key]);
					setcookie("doofinderError[{$key}]", null, -1, '/');
				}
			}
		}

		// $this->log->log("Setup Wizard Errors: ");
		// $this->log->log($this->errors);

		if ( current_user_can( 'manage_woocommerce' ) ) {
			add_action( 'admin_menu', array( $this, 'admin_menus' ) );

			$this->register_ajax_action();
		}
	}

	/**
	 * Check if on setup wizard page
	 *
	 * @return bool
	 */

	public static function is_wizard_page() {
		return (is_admin() && isset($_GET['page']) && $_GET['page'] === 'dfwc-setup');
	}

	/**
	 * Callback for WP rest api endpoint for connecting doofinder account
	 */
	public static function connect() {

		$setup_wizard = self::instance();
		$setup_wizard->log->log('Setup Wizard - Connect');
		$setup_wizard->process_step_1(true);

		return new \WP_REST_Response( "ok", 200 );
	}

	/**
	 * Create (or retrieve, if already exists), the singleton
	 * instance of this class.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( ! self::$instance ) {
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
	private function register_ajax_action() {

		//$this->log->log('Setup Wizard - Register ajax actons');

		add_action( 'wp_ajax_doofinder_for_wc_process_step_2', function () {
			// $data = Setup_Wizard::instance();
			// $data->process_step_2(true, $_REQUEST);
			$this->process_step_2(true, $_REQUEST);
		} );

		add_action( 'wp_ajax_doofinder_for_wc_check_data', function () {
			//$data = Setup_Wizard::instance();
			//$this->log->log('Doing ajax - doofinder_for_wc_check_data');
			self::check_data();
		} );
	}


	/**
	 * Check if we should enable the setup wizard, or if it's
	 * not necessary (because for example, it's been already performed).
	 *
	 * @return bool
	 */
	public static function should_activate() {
		$after_wizard = get_option( self::$wizard_done_option );

		return ! (bool) $after_wizard;
	}

	/**
	 * Check if we should show admin notice about wizard setup, or if it's
	 * not necessary (because for example, user dissmised it or completed setup).
	 *
	 * @return bool
	 */
	public static function should_show_notice() {
		$show_notice = get_option( self::$wizard_show_notice_option );

		return ((bool) $show_notice) && !Settings::is_configuration_complete();
	}

	/**
	 * Activate the setup wizard.
	 *
	 * When it is active (the option is set to truthy value) users that can
	 * manage options will see custom screen (the setup wizard) instead
	 * of admin panel.
	 */
	public static function activate($notice = false) {
		update_option( self::$wizard_active_option, true );

		if($notice) {
			update_option( self::$wizard_show_notice_option, true );
		}
	}

	/**
	 * Deactivate the setup wizard and set the flag making sure
	 * to not display it anymore.
	 */
	public static function deactivate() {
		update_option( self::$wizard_active_option, false );
		update_option( self::$wizard_done_option, true );
		update_option( self::$wizard_show_notice_option, false );

	}

	/**
	 * Dissmiss the admin setup wizard notice and set the flag making sure
	 * to not display it anymore.
	 */
	public static function dissmiss_notice() {
		update_option( self::$wizard_show_notice_option, false );
	}

	/**
	 * Is the setup wizard active (should we display it)?
	 *
	 * @return bool
	 */
	public static function is_active() {
		return (bool) get_option( self::$wizard_active_option );
	}

	/**
	 * Generate token used for login/signup via popup
	 *
	 * @return string token
	 */
	public function generateToken() {
		$time = time();
		$rand = rand();
		return md5("$time$rand");
	}

	/**
	 * Save token used for login/signup in db
	 *
	 * @param string $token
	 */
	public function saveToken($token) {
		update_option( self::$wizard_request_token, $token );
	}

	/**
	 * Get token used for login/signup saved in db
	 *
	 * @return string $token
	 */
	public function getToken() {
		return get_option( self::$wizard_request_token );
	}

	/**
	 * Get the absolute path of the URL to setup wizard that Doofinder will
	 * use for the POST request. The path will be appended to the origin domain.
	 *
	 * @return string path
	 */
	public function getReturnPath() {
		$setup_wizard_url= get_rest_url(null,'/doofinder-for-wc/v1/connect/');

		//return urlencode(str_replace($base_url,'',$setup_wizard_url));
		return $setup_wizard_url;
	}

	/**
	 * What the current step of the wizard is? This is the last step
	 * that the user have seen and not submitted yet.
	 *
	 * @return int
	 */
	public static function get_step() {
		$step = get_option( self::$wizard_step_option );
		if ( ! $step ) {
			$step = 1;
		}

		return (int) $step;
	}

	/**
	 * Move to the next step. If this was the last step
	 * deactivate the Setup Wizard.
	 */
	public static function next_step($step = null, $redirect = true) {

		if ( $step === null ) {
			$current_step = self::get_step();
			$current_step ++;
			$redirect_url = self::get_url();
		} else {
			$current_step = $step;
			$redirect_url = self::get_url( [ 'step' => $step ] );
		}

		// If on last step deactivate wizard and redirect to settings page

		if ( $current_step > self::$no_steps ) {

			//self::deactivate(); // Do not deactive the setup wizard, we need it to work for configure doofinder notice (that shows up when settings are empty)

			self::remove_notice();

			// Reset wizard to step 1
			update_option( self::$wizard_step_option, 1 );

			if ( $redirect ) {
				wp_safe_redirect( Settings::get_url() );
				die();
			}


		}

		// Else update step option and move to the next step

		update_option( self::$wizard_step_option, $current_step );

		if ( $redirect ) {
			wp_safe_redirect( $redirect_url );
			die();
		}
	}

	/**
	 * Show wizard
	 *
	 * @return void
	 */
	private function admin_page_init() {

		if ( empty( $_GET['page'] ) || 'dfwc-setup' !== $_GET['page'] ) {
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
		if ( self::is_active() ) {
			$this->show_wizard();
		} else {
			wp_safe_redirect( Settings::get_url() );
			die();
		}
	}

	/**
	 * Add admin page for setup wizard.
	 *
	 * @return void
	 */
	public function admin_menus() {
		add_dashboard_page( '', '', 'manage_options', 'dfwc-setup', $this->admin_page_init() );
	}

	/**
	 * Get url of the setup wizard admin page,
	 * you can add url parameters via $args
	 *
	 * @param array $args Associative array with query parameters key -> value
	 *
	 * @return string
	 */
	public static function get_url($args = []) {

		$url = admin_url( 'admin.php?page=dfwc-setup' );

		if ( !empty($args) ) {
			$url = add_query_arg($args,$url);
		}

		return $url;
	}

	/**
	 * Check if setting exist in db and return response
	 */
	public static function check_data() {

		$error_cookie = $_COOKIE['doofinderError']['wizard-step-1'] ?? false;

		if ($error_cookie) {
			$status = 'error';
		} elseif (Settings::is_api_configuration_complete() && self::get_step() >= 2 ) {
			$status = 'saved';
			// // Check if step was already set to 2, if not do it
			// if(self::get_step() !== 2) {
			// 	self::next_step(2,false);
			// }
		} else {
			$status = 'not-saved';
		}

		wp_send_json_success([
			'status' => $status
		]);
	}

	/**
	 * Wizard setup notice html
	 *
	 * @param bool $settings
	 *
	 * @return string
	 */
	public static function get_setup_wizard_notice_html($settings = true) {

		$message_intro = __( '<strong>Welcome to Doofinder for WooCommerce</strong>', 'woocommerce-doofinder' );

		$message = __( ' &#8211; Run setup wizard to finish installation.', 'woocommerce-doofinder' );

		if (Settings::is_configuration_complete()) {
			$message = __( ' &#8211; Looks like Doofinder is already set up. You can review the configuration in the settings or run the setup wizard.', 'woocommerce-doofinder' );
		}

		ob_start();

		?><div id="message" class="woocommerce-message doofinder-notice-setup-wizard"><?php
			?><figure class="logo" style="width:5rem;height:auto;float:left;margin:.5em 0;margin-right:0.75rem;"><img src="<?php echo Doofinder_For_WooCommerce::plugin_url() .'/assets/svg/imagotipo1.svg'; ?>"/></figure><?php
			?><p class="main" style="margin-top:1em;"><?php echo $message_intro . $message; ?></p><?php
			?><p class="submit"><?php
				?><a href="<?php echo self::get_url(); ?>" class="button-primary button-setup-wizard"><?php _e( 'Setup Wizard', 'woocommerce-doofinder' ); ?></a><?php
				/*
				?>&nbsp;<a class="button-secondary skip" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-hide-notice', 'doofinder_show_wizard_notice' ), 'woocommerce_hide_notices_nonce', '_wc_notice_nonce' ) ); ?>"><?php _e( 'Skip setup', 'woocommerce-doofinder' ); ?></a><?php */
				if ( $settings ) :
				?>&nbsp;<a class="button-secondary button-settings" href="<?php echo Settings::get_url(); ?>"><?php _e( 'Settings', 'woocommerce-doofinder' ); ?></a><?php
				endif;
			?></p><?php
		?></div><?php

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
	public static function get_setup_wizard_migration_notice_html() {

		ob_start();

		?><div id="message" class="woocommerce-message doofinder-migration-notice"><?php
			?><p class="main"><?php _e('Doofinder settings have been migrated successfully.','woocommerce-doofinder') ?></p><?php
		?></div><?php

		$html = ob_get_clean();

		// $log = new Log();
		// $log->log( 'Migration Notice - Transient' );
		// $log->log(get_transient(self::$wizard_migration_notice_transient));

		//if (get_transient(self::$wizard_migration_notice_transient)) {
			//$log->log( 'Migration Notice - Show' );
			//$log->log( $html );
			return $html;
			//$log->log( 'Migration Notice - Delete Transient' );
			//delete_transient(self::$wizard_migration_notice_transient);
		//}
	}


	/**
	 * Render a warning that we api is disabled (in code not via settings)
	 */
	public static function render_html_should_fail_notice() {
		?>

        <div class="notice notice-warning inline">
            <p><?php _e( 'Warning: The \'should_fail\' flag is enabled, and some steps will fail.', 'woocommerce-doofinder' ); ?></p>
        </div>

		<?php
	}

	/**
	 * Not dissmisable Configure via Wizard setup notice html
	 *
	 * @param bool $settings
	 *
	 * @return string
	 */
	public static function get_configure_via_setup_wizard_notice_html() {

		$html = '';

		if (!Settings::is_configuration_complete()) :

			$message = __( ' Configure Doofinder in minutes with Doofinder Setup Wizard', 'woocommerce-doofinder' );

			ob_start();

			?><div class="woocommerce-message notice doofinder-notice-setup-wizard"><?php

				?><p class="main" style="margin-top:1em;"><?php echo $message; ?></p><?php
				?><p><?php
					?><a href="<?php echo self::get_url(); ?>" class="button-primary"><?php _e( 'Configure', 'woocommerce-doofinder' ); ?></a><?php
				?></p><?php
			?></div><?php

			$html = ob_get_clean();

		endif;

		return $html;
	}

	/**
	 * Get setup wizard recongigure button html
	 *
	 * @param bool $settings
	 *
	 * @return string
	 */
	public static function get_configure_via_setup_wizard_button_html() {

		$html = '';

		//if (!Settings::is_configuration_complete()) :

			ob_start();

			?><?php
				?><p class="doofinder-button-setup-wizard" style="width:100px;float:right;position:relative;top:-68px;"><?php
					?><a href="<?php echo self::get_url(); ?>" class="button-secondary"><?php _e( 'Setup Wizard', 'woocommerce-doofinder' ); ?></a><?php
				?></p><?php
			?><?php

			$html = ob_get_clean();

		//endif;

		return $html;
	}

	/**
	 * Add/show custom Woocommerce notice in admin panel
	 *
	 * @return void
	 */
	public static function add_notice() {
		if (class_exists('WC_Admin_Notices')) {
			\WC_Admin_Notices::add_custom_notice(self::$wizard_notice_name, Setup_Wizard::get_setup_wizard_notice_html());
		}
	}

	/**
	 * Remove custom Woocommerce notice in admin panel
	 *
	 * @return void
	 */
	public static function remove_notice() {
		if (class_exists('WC_Admin_Notices')) {
			\WC_Admin_Notices::remove_notice(self::$wizard_notice_name);
		}
		self::dissmiss_notice();
	}

	/**
	 * Remove custom Woocommerce migration notice in admin panel
	 *
	 * @return void
	 */
	public static function remove_migration_notice() {
		if (class_exists('WC_Admin_Notices')) {
			\WC_Admin_Notices::remove_notice(self::$wizard_migration_notice_name);
		}
	}

	/**
	 * Display the setup wizard view.
	 */
	private function show_wizard() {
		if ( empty( $_GET['page'] ) || 'dfwc-setup' !== $_GET['page'] ) {
			return;
		}

		include Doofinder_For_WooCommerce::plugin_path() . '/views/wizard.php';

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
	private function render_wizard_step() {

		include Doofinder_For_WooCommerce::plugin_path() . "/views/wizard-step-all-in-one.php";
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
	public function get_error( $name ) {
		if ( isset( $this->errors[ $name ] )  ) {
			return $this->errors[ $name ];
		} elseif ( isset($_COOKIE['doofinderError'])) {
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
	public function get_errors_html( $name ) {
		$error = $this->get_error($name);
		$error_template = '<div class="error-text">%s</div>';


		$html = '';

		if ( $error ) {

			if(is_array($error)) {
				foreach($error as $err) {
					$html .= sprintf($error_template,$err);
				}
			} else {
				$html .= sprintf($error_template,$error);
			}
		}

		return $html;
	}

	/**
	 * Get languages from DB, and set current language if is not defined.
	 *
	 * @since 1.0.0
	 */
	private function get_languages($set_state = false) {

		if (!$this->process_all_languages) {

			if ( ! $this->indexing_data->get( 'lang' ) && $set_state ) {
				$this->indexing_data->set( 'lang', $this->active_lang );
			}

			return;
		}

		$languages = $this->language->get_languages();

		if(is_array($languages)) {
			$this->languages = array_keys($languages);
		} else {
			$this->languages = [''];
		}

		// if we start indexing, then language is not set, so we get first language from list
		if ( ! $this->indexing_data->get( 'lang' ) && $set_state ) {
			$this->indexing_data->set( 'lang', $this->languages[0] );
		}

		return $languages;

	}

	/**
	 * A callback for processing installation wizard steps.
	 *
	 * Each step of the wizard is being handled by its own method.
	 */
	private function process_wizard_step($step = null) {

		$step = $step ?: self::get_step();

		switch ( $step ) {
			case 1:
				$this->process_step_1();
				break;

			case 2:
				$this->process_step_2();
				break;

			case 3:
				$this->process_step_3();
				break;

			case 4:
				$this->process_step_4();
				break;

			case 5:
				$this->process_step_5();
				break;
		}
	}

	/**
	 * Handle the submission of step 1 - Api Key and Api Host .
	 */
	private function process_step_1($processing = false) {

		$is_processing = (isset($_REQUEST['process-step']) && $_REQUEST['process-step'] === '1') || $processing === true;

		if (!$is_processing) {
			return;
		}

		$this->log->log( 'Processing Wizard Step 1 - Processing...' );

		$token = $_POST['token'] ?? '';
		$saved_token = $this->getToken();

		// Exit early if tokens do not match
		if ($token !== $saved_token) {
			$this->errors['wizard-step-1'] = __( 'Invalid token', 'woocommerce-doofinder' );
			setcookie( "doofinderError[wizard-step-1]", __( 'Invalid token', 'woocommerce-doofinder' ),0,'/');
			$this->log->log( 'Processing Wizard Step 1 - Error - Invalid token' );
			$this->log->log( 'Processing Wizard Step 1 - Recieved Token - ' . $token );
			$this->log->log( 'Processing Wizard Step 1 - Saved Token - ' . $saved_token );
			return;
		}

		$api_key = $_REQUEST['api_token'] ?? null;
		$api_host = isset($_REQUEST['api_endpoint']) ? $_REQUEST['api_endpoint'] : null; // i.e: eu1-api.doofinder.com for API v2.0
		$admin_endpoint = isset($_REQUEST['admin_endpoint']) ? $_REQUEST['admin_endpoint'] : null; // i.e: eu1-app.doofinder.com for API v2.0
		$search_endpoint = isset($_REQUEST['search_endpoint']) ? $_REQUEST['search_endpoint'] : null; // i.e: eu1-search.doofinder.com for API v2.0

		// Required fields are not present or empty.
		if ( ! isset($api_key) || !$api_key || ! isset($api_host) || !$api_host || ! isset($admin_endpoint) || !$admin_endpoint || ! isset($search_endpoint) || !$search_endpoint) {

			if ( ! isset( $api_key ) || !$api_key ) {
				$this->errors['wizard-step-1']['api-key'] = __( 'API key is missing.', 'woocommerce-doofinder' );
				setcookie( "doofinderError[wizard-step-1][api-key]", __( 'API key is missing.', 'woocommerce-doofinder' ),0,'/');
				$this->log->log( 'Processing Wizard Step 1 - Error -  API key is missing.' );
			}

			if ( ! isset( $api_host) || !$api_host ) {
				$this->errors['wizard-step-1']['api-host'] = __( 'API host is missing.', 'woocommerce-doofinder' );
				setcookie( "doofinderError[wizard-step-1][api-host]", __( 'API host is missing.', 'woocommerce-doofinder' ),0,'/');
				$this->log->log( 'Processing Wizard Step 1 - Error - API host is missing.' );
			}

			if ( ! isset( $admin_endpoint) || !$admin_endpoint ) {
				$this->errors['wizard-step-1']['admin-endpoint'] = __( 'Admin endpoint is missing.', 'woocommerce-doofinder' );
				setcookie( "doofinderError[wizard-step-1][admin-endpoint]", __( 'Admin endpoint is missing.', 'woocommerce-doofinder' ),0,'/');
				$this->log->log( 'Processing Wizard Step 1 - Error - Admin endpoint is missing.' );
			}

			if ( ! isset( $search_endpoint) || !$search_endpoint ) {
				$this->errors['wizard-step-1']['search-endpoint'] = __( 'Search endpoint is missing.', 'woocommerce-doofinder' );
				setcookie( "doofinderError[wizard-step-1][search-endpoint]", __( 'Search endpoint is missing.', 'woocommerce-doofinder' ),0,'/');
				$this->log->log( 'Processing Wizard Step 1 - Error - Search endpoint is missing.' );
			}

			return;
		}

		// Check if api key and api host is valid, make test call to API

		$response = false;

		if(!$this->disable_api) {
			try {
				$client = new ManagementClient($api_host,$api_key);
				$this->log->log( 'Wizard Step 1 - Call Api - List search engines ' );
				$this->log->log( 'Wizard Step 1 - API key: ' . $api_key );
				$this->log->log( 'Wizard Step 1 - API host: ' . $api_host );

				$response = $client->listSearchEngines();
				$this->log->log( 'Wizard Step 1 - List Search engines Response: ' );
				$this->log->log( $response );

				$this->log->log( 'Wizard Step 1 - List search engines - success ' );
				$response = true;

			} catch (\DoofinderManagement\ApiException $exception) {
				$this->log->log( 'Wizard Step 1: '. $exception->getMessage() );
				$this->errors['wizard-step-1'] = __( 'Could not connect to the API. API Key or Host is not valid.', 'woocommerce-doofinder' );
				setcookie( "doofinderError[wizard-step-1]", __( 'Could not connect to the API. API Key or Host is not valid.','woocommerce-doofinder' ),0,'/');
			} catch (\Exception $exception) {
				$this->log->log( 'Wizard Step 1 - Exception ' );
				$this->log->log( $exception );

				if ( $exception instanceof DoofinderError ) {
					$this->log->log( $exception->getBody() );
				}
			}
		} else {
			$response = true;
		}

		if ($response) {
			// Everything is ok - save the options

			// Check if api key already exists and is the same
			// If api key is different clear all settings
			$saved_api_key = Settings::get_api_key();

			if ($saved_api_key !== $api_key) {
				$this->clear_all_settings();
			}

			Settings::set_api_key( $api_key );
			//$this->log->log($api_key);

			// Api Host should contain 'https://' protocol, i.e. https://eu1-api.doofinder.com
			if(!preg_match("#^((https?://)|www\.?)#i", $api_host)) {
				Settings::set_api_host('https://' . $api_host);
			} else {
				Settings::set_api_host($api_host);
			}
			//$this->log->log($api_host);

			// Admin Endpoint should contain 'https://' protocol, i.e. https://eu1-api.doofinder.com
			if(!preg_match("#^((https?://)|www\.?)#i", $admin_endpoint)) {
				Settings::set_admin_endpoint('https://' . $admin_endpoint);
			} else {
				Settings::set_admin_endpoint( $admin_endpoint );
			}
			//$this->log->log($admin_endpoint);

			// Search Endpoint should contain 'https://' protocol, i.e. https://eu1-api.doofinder.com
			if(!preg_match("#^((https?://)|www\.?)#i", $search_endpoint)) {
				Settings::set_search_engine_server('https://' . $search_endpoint);
			} else {
				Settings::set_search_engine_server( $search_endpoint );
			}
			//$this->log->log($search_endpoint);

			$this->log->log( 'Processing Wizard Step 1 - All data saved' );
			// ...and move to the next step.
			self::next_step(2);

		} else {

			$this->errors['wizard-step-1'] = __( 'Something went wrong.', 'woocommerce-doofinder' );
			setcookie( "doofinderError[wizard-step-1]", __( 'Something went wrong.','woocommerce-doofinder' ),0,'/');
			$this->log->log( 'Processing Wizard Step 1 - Error - Something went wrong.' );

			return;
		}

	}

	/**
	 * Handle the submission of step 2 - Search Engine Hash and Indexing.
	 */
	private function process_step_2($is_ajax = false, $data = null) {

		$is_processing = isset($_REQUEST['process_step']) && $_REQUEST['process_step'] === '2';

		if (!$is_processing) {
			return;
		}

		$this->log->log( 'Processing Wizard Step 2' );

		// Test error response
		if (self::$should_fail) {

			$this->log->log( 'Processing Wizard Step 2 - Failed.' );

			// Send failed ajax response
			wp_send_json_error(array(
				'status'  => false,
				'error' => true,
				'message' => _('','woocommerce-doofinder'),
			));

			return;
		}


		$api_key = Settings::get_api_key();
		$api_host = Settings::get_api_host();
		$client = false;

		// Try to create API client instance

		try {
			if(!$this->disable_api) {
				$client = new ManagementClient( $api_host, $api_key );
			}
		} catch (\DoofinderManagement\ApiException $exception) {
			$this->log->log( 'Wizard Step 2 - Exception 1' );
			$this->log->log( $exception->getMessage() );
			$this->errors['wizard-step-2'] =
				__(
					'Could not create "ManagementClient" class instance.',
					'woocommerce-doofinder'
				);
			// Send failed ajax response
			wp_send_json_error(array(
				'status'  => false,
				'error' => true,
				'message' => _('','woocommerce-doofinder'),
			));

			return;
		}

		// Check if search engines (Hash ID in DB) already exists (for each language if multilang)

		$has_api_keys = Index_Interface::are_api_keys_present($this->process_all_languages, $this->language);

		//$this->log->log( 'Wizard Step 2 - Has Api Keys : ');
		//$this->log->log( $has_api_keys );

		if ( !$has_api_keys || (is_array($has_api_keys) && Helpers::in_array_r( 'no-hash', $has_api_keys, true )  )) {

			// Api keys are missing for some / all languages. We need to create
			// search engine for that language

			// If hash id is missing create search engine

			// If there's no plugin active we still need to process 1 language.
			if ( !Multilanguage::$is_multilang ) {
				$has_api_keys[''] = [
					'hash' => 'no-hash'
				];
			}

			if ( is_array( $has_api_keys )) :

				foreach ( $has_api_keys as $item ) {

					if ( $item['hash'] === 'no-hash' ) {

						$code = $item['lang']['code'] ?? '';
						$search_engine = false;

						try {
							// Prepare search engine body
							$this->log->log( 'Wizard Step 2 - Prepare Search Enginge body : ');
							$domain = str_ireplace('www.', '', parse_url(get_bloginfo('url'), PHP_URL_HOST));
							$data = [
								'name' => $domain . ( $code ? ' ('.strtoupper($code).')' : '' ),
								'language' => $code,
								// 'indices' => [
								// 	[
								// 		'name' => 'product',
								// 		'preset' => 'product'
								// 	]
								// ],
								'site_url' => get_bloginfo('url')
							];
							//$this->log->log( $data );
							$this->log->log( 'Wizard Step 2 - Try Create Search Engine' );


							// Create search enginge

							if (!$this->disable_api) {

								$this->log->log('=== API CALL === ');

								/** @var \DoofinderManagement\Model\SearchEngine $search_engine */

								$search_engine = $client->createSearchEngine(json_encode($data));

							}
							else {
								// If api is disabled that means we are testing so we need to set
								// some fake data in order for the setup wizard to advance to the next
								// step
								$search_engine = new \DoofinderManagement\Model\SearchEngine();
								$search_engine->setHashid('123456789testdatatobechanged');
								$search_engine->setSearchUrl('https://test-url-to-be-changed.com');
							}

							$this->log->log( 'Wizard Step 2 - Created Search Engine: ' );
							//$this->log->log( $search_engine );

						} catch (\DoofinderManagement\ApiException $exception) {
							$this->log->log( 'Wizard Step 2 - Exception 2 ' );
							$this->log->log( $exception->getMessage() );
							$this->errors['wizard-step-2'] =
							__(
								'Could not create Search Engine for ' . $item['english_name'] . ' language.',
								'woocommerce-doofinder'
							);

							// Send failed ajax response
							wp_send_json_error(array(
								'status'  => false,
								'error' => true,
								'message' => _('','woocommerce-doofinder'),
							));

							return;
						}
						// And finally save hash id for each language

						if ( $search_engine instanceof \DoofinderManagement\Model\SearchEngine ) {

							$this->log->log( 'Wizard Step 2 - Save search engine ');

							$code = $code === $this->language->get_base_language() ? '' : $code;
							$this->log->log( 'Wizard Step 2 - Save search engine  - option suffix: ' . $code);

							$hash_id = $search_engine->getHashid();
							$this->log->log( 'Wizard Step 2 - Save search engine  - hash id: ' . $hash_id);

							// $server = $search_engine->getSearchUrl();
							// $this->log->log( 'Wizard Step 2 - Save search engine  - server: ' . $server);

							Settings::set_search_engine_hash( $hash_id , $code );
							//Settings::set_search_engine_server( $server , $code );
							$this->log->log( 'Wizard Step 2 - Save search engine  - Saved');
						}
					}

				}


			endif;

		}


		if ($is_ajax) {

			// Check again if api keys exist before advancing to the next step
			$has_api_keys = Index_Interface::are_api_keys_present($this->process_all_languages, $this->language);

			if ($has_api_keys) {
				// Move pointer to the next step, so when indexing is finished
				// and the page is reloaded via JS we show step 3
				self::next_step(3, false);
			}

			// Send success ajax response
			wp_send_json_success( array(
				'completed' => true
			) );


		} else {
			// Move to the next step.
			self::next_step(3);
		}
	}

	/**
	 * Handle the submit of step 3 - Internal search.
	 */
	private function process_step_3() {

		$is_processing = isset($_POST['process-step']) && $_POST['process-step'] === '3';

		if (!$is_processing) {
			return;
		}

		$this->log->log( 'Processing Wizard Step 3' );

		// Delay the processing so the user can see what is happening
		sleep(3);

		// If there's no plugin active we still need to process 1 language.
		$languages = $this->language->get_formatted_languages();

		if ( ! $languages ) {
			$languages[''] = '';
		}

		foreach ( $languages as $language_code => $language_name ) {
			// Suffix for options.
			// This should be empty for default language, and language code
			// for any other.
			$options_suffix = '';
			$name_suffix    = '';

			if ( $language_code !== $this->language->get_base_language() ) {
				$options_suffix = $language_code;
				$name_suffix    = "-$language_code";
			}

			// Internal Search
			if (
				isset( $_POST["enable-internal-search{$name_suffix}"] ) &&
				$_POST["enable-internal-search{$name_suffix}"]
			) {

				Settings::enable_internal_search( $options_suffix );
			}

		}

		self::next_step(4);
	}

	/**
	 * Handle the submit of step 4 - JS Layer.
	 */
	private function process_step_4() {

		$is_processing = isset($_POST['process-step']) && $_POST['process-step'] === '4';

		if (!$is_processing) {
			return;
		}

		$this->log->log( 'Processing Wizard Step 4' );

		sleep(2);

		// Test error response
		if (self::$should_fail) {

			$this->log->log( 'Processing Wizard Step 4 - Failed.' );

			$this->errors['wizard-step-4']['create-layer'] = __( 'Could not retrieve JS layer. Please, try again. If the error persists, please contact us.', 'woocommerce-doofinder' );
			$this->log->log( 'Processing Wizard Step 4 - Error - Could not retrieve JS layer' );
			return false;
		}

		// Delay the processing so the user can see what is happening
		sleep(3);

		// If there's no plugin active we still need to process 1 language.
		$languages = $this->language->get_formatted_languages();
		if ( ! $languages ) {
			$languages[''] = '';
		}

		foreach ( $languages as $language_code => $language_name ) {
			// Suffix for options.
			// This should be empty for default language, and language code
			// for any other.
			$options_suffix = '';
			$name_suffix    = '';

			if ( $language_code !== $this->language->get_base_language() ) {
				$options_suffix = $language_code;
				$name_suffix    = "-$language_code";
			}

			$this->log->log( 'Creating JS layer for ' . $language_code );

			if (!$this->disable_api) {

				// Get search engine hash id for current language
				$hash = Settings::get_search_engine_hash( $options_suffix );
				// Create JS layer for current search engine
				$js_layer_code_api_result = $this->create_js_layer($hash);

				if (!$js_layer_code_api_result) {
					$this->errors['wizard-step-4']['create-layer'] = __( 'Could not retrieve JS layer. Please, try again. If the error persists, please contact us.', 'woocommerce-doofinder' );
					$this->log->log( 'Processing Wizard Step 4 - Error - Could not retrieve JS layer' );
					return false;
				}
				$this->log->log( 'Processing Wizard Step 4 - JS Layer Created' );

			} else {
				//Get results for local testing
				$js_layer_code_api_result = "<!--//{$language_name} JS layer code from API goes here.-->";
			}


			// Enable JS Layer
			if ( isset( $_POST["enable-js-layer{$name_suffix}"] ) && $_POST["enable-js-layer{$name_suffix}"] ) {
				Settings::enable_js_layer( $options_suffix );
			}

			// JS Layer Code
			if ( isset( $js_layer_code_api_result ) && $js_layer_code_api_result ) {
				Settings::set_js_layer( $js_layer_code_api_result, $options_suffix );
			}
		}

		self::next_step(5);

	}

	/**
	 * Handle the submit of step 5 - Close and disable Setup Wizard.
	 */
	private function process_step_5() {

		$is_processing = isset($_POST['process-step']) && $_POST['process-step'] === '5';

		if (!$is_processing) {
			return;
		}

		$this->log->log( 'Processing Wizard Step 5' );

		// Move to the next step. Step that exceedes number of steps to deactivate the wizard.
		self::next_step(self::$no_steps + 1);
	}

	/**
	 * Clear all settings in Doofinder Search Tab for each language
	 */
	private function clear_all_settings() {

		$this->log->log( 'Clear All Settings' );

		// Clear global settings

		Settings::set_api_key('');
		Settings::set_api_host('');
		Settings::set_admin_endpoint('');
		Settings::set_search_engine_server('');



		// If there's no plugin active we still need to process 1 language.
		$languages = $this->language->get_formatted_languages();

		if ( ! $languages ) {
			$languages[''] = '';
		}

		// Clear per language settings

		foreach ( $languages as $language_code => $language_name ) {
			// Suffix for options.
			// This should be empty for default language, and language code
			// for any other.
			$options_suffix = '';
			$name_suffix    = '';

			if ( $language_code !== $this->language->get_base_language() ) {
				$options_suffix = $language_code;
				$name_suffix    = "-$language_code";
			}

			// Search engine data
			Settings::set_search_engine_hash( '', $options_suffix );

			// Internal Search
			Settings::disable_internal_search( $options_suffix );

			// JS Layer
			Settings::disable_js_layer( $options_suffix );
			// JS Layer Code
			Settings::set_js_layer( '', $options_suffix );

		}

	}

	/**
	 * Get or create JS Layer for given search engine
	 *
	 * @param string $hash Search engine hash id
	 * @param string $admin_endpoint
	 * @param bool   $get Request method switch. True for GET false for POST.
	 *
	 * @return mixed Code of the JS layer or false on error
 	 */

	public function manage_js_layer($hash, $get) {

		if ($get) {
			$label = 'Get';
			$method = 'GET';
		} else {
			$label = 'Create';
			$method = 'POST';
		}

		$this->log->log( $label . ' JS layer' );

		$api_key = Settings::get_api_key();

		if (!$api_key) {
			$this->errors['wizard-step-4']['api-key'] = __( 'API key is missing.', 'woocommerce-doofinder' );
			$this->log->log( $label . ' JS layer - Error -  API key is missing.' );
			return false;
		}

		$admin_endpoint = Helpers::prepare_host(Settings::get_admin_endpoint());

		if (!$admin_endpoint) {
			$this->errors['wizard-step-4']['admin-endpoint'] = __( 'Admin Endpoint is missing.', 'woocommerce-doofinder' );
			$this->log->log( $label . ' JS layer - Error - Admin Endpoint is missing.' );
			return false;
		}

		$client = new GuzzleClient();

		$body = '';
		$script = '';

		try {

			if (!$this->disable_api) {

				$this->log->log('=== API CALL === ');
				//$this->log->log("{$admin_endpoint}/plugins/{$hash}/script/woocommerce");

				$res = $client->request( $method, "{$admin_endpoint}/plugins/{$hash}/script/woocommerce", [
					'headers' => [
						'Authorization' => "Token {$api_key}"
					]
				]);

				if ($res) {
					$body = json_decode($res->getBody()->getContents());
					$script = $body->script ?? '';
				}
			}

			//$this->log->log( $label . ' JS layer Response - Script'  );
			//$this->log->log( $script );

			return $script;

		} catch ( \Exception $exception) {
			$this->log->log( $label . ' JS layer - Exception' );

			$this->log->log( $exception->getMessage() );

			return false;

		} catch ( \Error $error) {
			$this->log->log( $label . ' JS layer - Error' );

			$this->log->log( $error->getMessage() );

			return false;
		}
	 }

	/**
	 * Alias for creating JS Layer for given search engine
	 *
	 * @param string $hash Search engine hash id
	 *
	 * @return mixed  Code of the JS layer or false on error
	 *
	 */

	public function create_js_layer($hash) {
		return $this->manage_js_layer($hash, false);
	}


	/**
	 * Check if we should migrate settings
	 *
	 * @return bool
	 */
	public static function should_migrate() {

		$log = new Log();
		$migration_option = get_option( self::$wizard_migration_option );

		// Migration was already done, we should abort
		if (  $migration_option === 'completed' || $migration_option === 'failed') {
			//$log->log( 'Should migrate - Migration already done or not possible' );
			return false;
		}

		$api_key = Settings::get_api_key();

		if ($api_key) {

			if (preg_match('@-@', $api_key)) {
				//$log->log( 'Should migrate - Migration possible - Api Key' );
				return true;
			}

			if(!Settings::get_api_host()) {
				//$log->log( 'Should migrate - Migration possible - Api Host' );
				return true;
			}

			if(!Settings::get_admin_endpoint()) {
				//$log->log( 'Should migrate - Migration possible - Admin Endpoint' );
				return true;
			}

			if(!Settings::get_search_engine_server()) {
				//$log->log( 'Should migrate - Migration possible - Search Server' );
				return true;
			}
		}

		// Migration not necessary
		$log->log( 'Should migrate - Migration not necessary' );
		update_option( 'woocommerce_doofinder_migration_status', 'completed' );

		return false;
	}

	/**
	 * Try migrating old settings
	 */

	public static function migrate() {
		$log = new Log();
		//$log->log( 'Migrate - Start' );

		$api_key = Settings::get_api_key();

		if(preg_match('@-@', $api_key)) {
			$arr = explode('-',$api_key);
		}

		$api_key_prefix = $arr[0] ?? null;
		$api_key_value = $arr[1] ?? null;

		if (!($api_key && $api_key_prefix && $api_key_value)) {

			// Migration not possible
			$log->log( 'Migrate - Migration Not Possible' );
			update_option( self::$wizard_migration_option, 'failed' );

			// Disable doofinder search
			Settings::disable_internal_search();
			Settings::disable_js_layer();

			return false;
		}

		// All good, save api key value
		$log->log( 'Migrate - Set Api key' );
		Settings::set_api_key($api_key_value);

		// Check and update api host
		$api_host_base = '-api.doofinder.com';
		$api_host = Settings::get_api_host();

		if(!$api_host || !preg_match("@$api_key_prefix-api@", $api_host)) {
			$log->log( 'Migrate - Set Api Host' );
			Settings::set_api_host($api_key_prefix . $api_host_base);
		}

		// Check and update admin endpoint
		$admin_endpoint_base = '-app.doofinder.com';
		$admin_endpoint = Settings::get_admin_endpoint();

		if(!$admin_endpoint || !preg_match("@$api_key_prefix-app@", $admin_endpoint)) {
			$log->log( 'Migrate - Set Admin Endpoint' );
			Settings::set_admin_endpoint($api_key_prefix . $admin_endpoint_base);
		}

		// Check and update search server
		$search_server_base = '-search.doofinder.com';
		$search_server = Settings::get_search_engine_server();

		if(!$search_server || !preg_match("@$api_key_prefix-search@", $search_server)) {
			$log->log( 'Migrate - Set Search Server' );
			Settings::set_search_engine_server($api_key_prefix . $search_server_base);
		}

		// Add notice about successfull migration
		$log->log( 'Migrate - Add custom notice' );
		\WC_Admin_Notices::add_custom_notice(self::$wizard_migration_notice_name, Setup_Wizard::get_setup_wizard_migration_notice_html());
		//$log->log( 'Migrate - Set Transient' );
		//set_transient( self::$wizard_migration_notice_transient, 1 );

		// Migration completed
		$log->log( 'Migrate - Migration Completed' );
		update_option( self::$wizard_migration_option, 'completed' );
	}

}
