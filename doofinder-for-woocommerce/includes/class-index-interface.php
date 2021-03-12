<?php

namespace Doofinder\WC;

use Doofinder\WC\Multilanguage\Language_Plugin;
use Doofinder\WC\Multilanguage\Multilanguage;
use Doofinder\WC\Multilanguage\No_Language_Plugin;
use Doofinder\WC\Settings\Settings;
use Doofinder\WC\Helpers\Helpers;

/**
 * Prints interface that allows user to index the posts.
 */
class Index_Interface {

	/**
	 * The only instance of Index_Interface
	 *
	 * @var Index_Interface
	 */
	private static $_instance = null;

	/**
	 * Instance of the class handling multilanguage.
	 *
	 * @var Language_Plugin
	 */
	public $language;

	/**
	 * Contains information about indexing progress.
	 *
	 * @var Indexing_Data
	 */
	private $indexing_data;

	/**
	 * If true the message informing the user that indexing has been
	 * completed successfully will be shown.
	 *
	 * @var bool
	 */
	private $show_success_message = false;

	/**
	 * Contains information whether we should process all languages at once
	 *
	 * @var bool
	 */
	public $process_all_languages = false;


	/**
	 * Returns the only instance of Index_Interface
	 *
	 * @since 1.0.0
	 * @return Index_Interface
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Index_Interface constructor.
	 */
	private function __construct() {
		$this->language      = Multilanguage::instance();
		$this->indexing_data = Indexing_Data::instance();
		

		$this->process_cookies();

		// Add a submenu page with indexing interface.
		// $this->add_indexing_subpage();

		// Register JS action that will handle sending one batch of data to API.
		$this->register_ajax_action();
		$this->register_ajax_action_cancel();

		// Add frontend scripts.
		$this->add_admin_scripts();
	}

	/**
	 * After indexing is finished JS sets a cookie in order to make backend
	 * display the message. We need to display the message and clear
	 * the cookie, so that the message is not displayed again after refreshing
	 * the page.
	 *
	 * Because cookies are sent as headers this all needs to be done
	 * before rendering any HTML.
	 */
	private function process_cookies() {
		add_action( 'admin_init', function () {
			if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wc-settings' ) {
				return;
			}

			// We need to remove the cookie before rendering HTML, so if the cookie
			// to display success message is set - remember that information.
			if ( isset( $_COOKIE['doofinder_wc_show_success_message'] ) ) {
				$this->show_success_message = true;
			}

			// Clear the cookie.
			unset( $_COOKIE['doofinder_wc_show_success_message'] );
			setcookie( 'doofinder_wc_show_success_message', null, - 1 );
		} );
	}

	/**
	 * Add a subpage displaying the interface allowing to index
	 * all posts from the blog.
	 */
	// private function add_indexing_subpage() {
	// 	add_action( 'admin_menu', function () {
	// 		add_submenu_page(
	// 			Settings::$top_level_menu,
	// 			__( 'Index Posts', 'woocommerce-doofinder' ),
	// 			__( 'Index Posts', 'woocommerce-doofinder' ),
	// 			'manage_options',
	// 			'index_posts',
	// 			function () {
	// 				$this->render_html_subpage();
	// 			}
	// 		);
	// 	} );
	// }

	/**
	 * Register an ajax action that indexes (sends to the Doofinder API) a single batch
	 * of the posts.
	 *
	 * JS will call this endpoint multiple time, each time adding new batch of posts.
	 *
	 * @since 1.0.0
	 */
	private function register_ajax_action() {
		add_action( 'wp_ajax_doofinder_for_wc_index_content', function () {
			$data = new Data_Index();
			$data->ajax_handler();
		} );
	}

	/**
	 * Register an ajax action that cancels the indexing.
	 */
	private function register_ajax_action_cancel() {
		add_action( 'wp_ajax_doofider_for_wc_cancel_indexing', function () {
			$data = Indexing_Data::instance();
			$data->set( 'status', 'completed' );
			$data->save();

			wp_send_json_success();
		} );
	}

	/**
	 * Register scripts used by the indexing interface.
	 */
	private function add_admin_scripts() {
		add_action( 'admin_enqueue_scripts', function () {
			// Don't add these scripts on pages other than the indexing interface.
			// Other pages don't use them.
			$screen = get_current_screen();
			$page = $_GET['page'] ?? '';
			$tab = $_GET['tab'] ?? '';
			$section = $_GET['section'] ?? '';

			if ( $screen->id !== 'woocommerce_page_wc-settings' && $page !== 'wc-settings' && $tab !== 'doofinder' && $section !== 'indexing' ) {
				return;
			}

			// JS
			wp_enqueue_script( 'doofinder-for-wc-script',
				Doofinder_For_WooCommerce::plugin_url() . 'assets/js/admin.js',
				array( 'jquery' )
			);
			wp_localize_script( 'doofinder-for-wc-script', 'DoofinderForWC', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			) );

			// CSS
			wp_enqueue_style(
				'doofinder-for-wc-styles',
				Doofinder_For_WooCommerce::plugin_url() . 'assets/css/admin.css'
			);
		} );
	}

	/**
	 * Check if language is selected (provided a multilanguage plugin is active).
	 *
	 * If no multilanguage plugins are active this function will return true,
	 * because in that case it's not possible to deselect language.
	 *
	 * @return bool
	 */
	private function is_language_selected() {
		return ( $this->language instanceof No_Language_Plugin ) || $this->language->get_active_language();
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
	public static function are_api_keys_present( bool $process_all_languages, $language ) {


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

			foreach($language->get_languages() as $lang) {
				$code = $lang['code'];
				$code = $code === $language->get_base_language() ? '' : $code;
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
	 * Generate the HTML of the indexing page.
	 */
	public function render_html_subpage() {

		$status = $this->indexing_data->get( 'status' );

		$this->process_all_languages = $this->language->get_languages() && !$this->language->get_active_language();

		// API keys are not present.
		$has_api_keys = self::are_api_keys_present($this->process_all_languages, $this->language);

		// Set the flag that indexing is impossible because of missing api keys
		$is_indexing_impossible = !$has_api_keys || (is_array($has_api_keys) && Helpers::in_array_r('no-hash',$has_api_keys,true)  ) ;
		
		?>

        <div class="wrap">
			<?php

			// Check if we have multilanguage plugin
			if ( $this->language->get_languages() ) {
				if ($this->language->get_active_language()) {
					// We have multilanguage plugin, and language is selected.
					$this->render_html_current_language_info();
				} else {
					// We have multilanguage plugin, but no language is selected.
					$this->render_html_all_language_info();
				}
			}

			if ( Doofinder_For_WooCommerce::$disable_api_calls ) {
				self::render_html_api_disabled_notice();
			}
			if ( Data_Index::$should_fail || Setup_Wizard::$should_fail ) {
				Setup_Wizard::render_html_should_fail_notice();
			}

			?>
			<?php if (!$is_indexing_impossible) : ?>
			<h1><?php _e( 'Indexing Data', 'woocommerce-doofinder' ); ?></h1>
			<?php endif; ?>

			<?php

			// If api key or hash id is not present do not display indexing interface
			if ( $is_indexing_impossible ) {

			
				$this->render_html_missing_api_keys($has_api_keys);

				// Generally it should not be possible that we are in
				// the middle of processing if keys are invalid, but some
				// people have experienced that. Probably because of some
				// DB shenanigans? In any case, if the index is being
				// processed and we have no API keys that's probably and error
				// so we should reset the processing.
				if ( $status === 'processing' ) {
					$this->indexing_data->set( 'status', 'new' );
					$this->indexing_data->save();

					$this->render_html_indexing_reset();
				}

			// Settings are ok, we have API keys, etc.
			// We can render the indexing interface.
			} else {
				$this->render_html_wp_debug_warning();
				$this->render_html_processing_status();
				$this->render_html_progress_bar();
				$this->render_html_progress_bar_status();
				$this->render_html_indexing_messages();
				$this->render_html_indexing_error();
				$this->render_html_index_button();
			}

			?>
        </div>

		<?php
	}

	/**
	 * Render error notifying the user that they should select
	 * a language first.
	 */
	public function render_html_select_language() {
		?>

        <div class="notice notice-error inline">
            <p><?php _e( 'You have a multi-language plugin installed. Please choose a language first to index data in Doofinder.', 'woocommerce-doofinder' ); ?></p>
        </div>

		<?php
	}

	/**
	 * Render info notifying the user that they have language selected.
	 */
	public function render_html_current_language_info() {
		?>

        <div class="notice notice-warning inline">
			<?php /* ?><p><strong><?php _e( 'Indexing data - language information', 'woocommerce-doofinder' ); ?></strong></p><?php */ ?>
            <p><?php _e( 'You have a multi-language plugin installed and editing in "'. $this->language->get_current_language()['english_name'].'" language is selected. ', 'woocommerce-doofinder' ); ?></p>
			<p><?php _e( 'Data will only be indexed for the selected language.', 'woocommerce-doofinder' ); ?></p>
			<p><?php _e( 'If you want to index data for all languages, please change to editing all languages.', 'woocommerce-doofinder' ); ?></p>
        </div>

		<?php
	}

		/**
	 * Render error notifying the user that they should select
	 * a language first.
	 */
	public function render_html_all_language_info() {
		?>

		<div class="notice notice-warning inline">
			<?php /* ?><p><strong><?php _e( 'Indexing data - language information', 'woocommerce-doofinder' ); ?></strong></p><?php */ ?>
            <p><?php _e( 'You have a multi-language plugin installed and editing all languages is selected.', 'woocommerce-doofinder' ); ?></p>
			<p><?php _e( 'Data will be indexed for all languages.', 'woocommerce-doofinder' ); ?></p>
			<p><?php _e( 'If you want to index data only for one language, please change editing language.', 'woocommerce-doofinder' ); ?></p>
        </div>

		<?php
	}

	/**
	 * Render error notifying the user that API keys are missing.
	 * 
	 * @param array $api_keys_info
	 */
	public function render_html_missing_api_keys($api_keys_info = null) {

		$message = '
		<div class="notice notice-error inline">
			<p>'. __( "API Key, API Host and/or Search Engine Hash ID are not set in Doofinder Settings%s", "woocommerce-doofinder" ) .'</p>
		</div>';

		if (Multilanguage::$is_multilang) {

			if (is_array($api_keys_info)) :

				foreach ($api_keys_info as $item) {
					if($item['hash'] === 'no-hash') {
						echo sprintf($message,"  for {$item['lang']['english_name']} language.");
					}
				}
			elseif ($this->language->get_active_language()) :
				echo sprintf($message,' for selected language.');
			else :
				echo sprintf($message,'.');
			endif;

		} else {
			echo sprintf($message,'.');
		}
	}

	/**
	 * Render a warning that we have just reset the indexing.
	 */
	public function render_html_indexing_reset() {
		?>

        <div class="notice notice-warning">
            <p><?php _e( 'Indexing was in progress despite errors in configuration. Indexing has been reset.', 'woocommerce-doofinder' ); ?></p>
        </div>

		<?php
	}

	/**
	 * Render a warning that we api is disabled (in code not via settings)
	 */
	public static function render_html_api_disabled_notice() {
		?>

        <div class="notice notice-warning inline">
            <p><?php _e( 'API calls are disabled.', 'woocommerce-doofinder' ); ?></p>
        </div>

		<?php
	}

	/**
	 * Because if WP_DEBUG is turned on we'll logging to local file instead of sending
	 * to the API, let's display the warning so the user knows what's going on.
	 */
	public function render_html_wp_debug_warning() {
		if ( ! Helpers::is_debug_mode() ) {
			return;
		}

		?>

        <p class="doofinder-for-wc-warning"><?php _e( 'Your site is in debug mode. Nothing will be sent to the Doofinder API.', 'woocommerce-doofinder' ); ?></p>

		<?php
	}

	/**
	 * Display additional information about the status/progress
	 * of the indexing process.
	 */
	public function render_html_processing_status() {
		$status = $this->indexing_data->get( 'status' );

		if ( ! Settings::is_configuration_complete() ) {
			$url = Settings::get_url();

			?>

            <div class="error settings-error notice inline">
                <p><?php printf( __( 'Indexing posts is unavailable. Check your <a href="%s">configuration</a> and try again.', 'woocommerce-doofinder' ), $url ); ?></p>
            </div>

			<?php
		} elseif ( $status === 'new' ) {
			?>

            <div class="error settings-error notice inline">
                <p><?php _e( 'Your data must be reindexed. No data found in Doofinder or it\'s outdated.', 'woocommerce-doofinder' ); ?></p>
            </div>

			<?php
		} elseif ( $status === 'processing' ) {
			?>

            <div class="error settings-error notice inline">
                <p><?php _e( 'Indexing posts is in progress, but was interrupted.', 'woocommerce-doofinder' ); ?></p>
            </div>

			<?php
		} elseif ( $status === 'completed' && $this->show_success_message ) {
			?>

            <div class="updated settings-error notice inline">
                <p><?php _e( 'Indices are ready and up-to-date.', 'woocommerce-doofinder' ); ?></p>
            </div>

			<?php
		}
	}

	/**
	 * Render progress bar displaying the progress of indexing process.
	 *
	 * JS will update it as indexing goes on.
	 */
	public function render_html_progress_bar() {
		?>

        <div id="doofinder-for-wc-progress-bar" class="doofinder-for-wc-progress-bar">
            <div class="doofinder-for-wc-bar" data-bar></div>
        </div>

		<?php
	}

	/**
	 * Render message displaying the status of indexing process.
	 *
	 * JS will update it as indexing goes on.
	 */
	public function render_html_progress_bar_status() {
		?>

        <div id="doofinder-for-wc-progress-bar-status" class="doofinder-for-wc-progress-bar-status">
            <p class="preparing"><?php _e( 'Preparing to index: please be patient, it can take some time. Please don\'t leave this page.', 'woocommerce-doofinder' ); ?></p>
            <p class="indexing"><?php _e( 'Please, don\'t leave the page for the indexing to complete.', 'woocommerce-doofinder' ); ?></p>
			<p class="creating-engines"><?php _e( 'Creating search engines... Please don\'t leave this page.', 'woocommerce-doofinder' ); ?></p>
        </div>

		<?php
	}

	/**
	 * Render additional messages providing additional information
	 * about what the backend is doing.
	 */
	public function render_html_indexing_messages() {
		?>

        <div id="doofinder-for-wc-additional-messages" class="doofinder-for-wc-additional-messages">
        </div>

		<?php
	}

	/**
	 * Render the error message that will be displayed if indexing error occurs.
	 */
	public function render_html_indexing_error() {
		?>
        <p id="doofinder-for-wc-indexing-error" class="doofinder-for-wc-indexing-error">
			<?php _e( 'An error occurred when indexing posts. Maybe the Doofinder API is down, but maybe it\'s just a temporary hiccup. Try refreshing and resuming indexing in a few minutes. Don\'t worry - posts that have already been indexed will not be lost.', 'woocommerce-doofinder' ); ?>
        </p>
		<?php
	}

	/**
	 * Render the error message that will be displayed if indexing error occurs in setup wizard.
	 */
	public function render_html_indexing_error_wizard() {
		?>
		<div>
			<p id="doofinder-for-wc-indexing-error" class="doofinder-for-wc-indexing-error">
				<?php _e( 'There was an unexpected error. Please try again. If the error persists, please contact us.', 'woocommerce-doofinder' ); ?>
				<a href="<?php echo Settings::get_url('reset-wizard=1'); ?>" class="button button-primary button-error"><?php _e('Exit setup','woocommerce-doofinder'); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render HTML of indexing button.
	 *
	 * Clicking this button starts the process of indexing the posts.
	 */
	public function render_html_index_button() {
		$status   = $this->indexing_data->get( 'status' );
		$disabled = Settings::is_configuration_complete() ? '' : 'disabled';

		$buttonText = __( 'Index all content', 'woocommerce-doofinder' );
		switch ( $status ) {
			case 'processing':
				$buttonText = __( 'Resume', 'woocommerce-doofinder' );
				break;

			case 'completed':
				$buttonText = __( 'Reindex All', 'woocommerce-doofinder' );
		}

		?>

        <p><strong><?php _e( 'WARNING:', 'woocommerce-doofinder' ); ?></strong></p>
        <ul class="custom-list">
            <li><?php _e( 'This process will delete and reindex all data in Doofinder servers. It won\'t delete anything in your database.', 'woocommerce-doofinder' ); ?></li>
            <li><?php _e( 'Indexing can take some time and search could return no results while indexing.', 'woocommerce-doofinder' ); ?></li>
            <li><?php _e( 'You can switch internal search off before launching this process and enable it again when it finishes.', 'woocommerce-doofinder' ); ?></li>
            <li><?php _e( 'Don\'t leave this page until the process finishes to ensure all data is properly indexed.', 'woocommerce-doofinder' ); ?></li>
        </ul>

		<script>
			const doofinderCurrentLanguage = '<?php echo $this->language->get_active_language(); ?>';
		</script>

        <button
                type="button"
                id="doofinder-for-wc-index-button"
                class="button button-primary"
			<?php echo $disabled; ?>
        >
			<?php echo $buttonText; ?>
        </button>

		<?php if ( $status === 'processing' ): ?>
            <button
                    type="button"
                    id="doofinder-for-wc-cancel-indexing"
                    class="button"
            >
				<?php _e( 'Cancel', 'woocommerce-doofinder' ); ?>
            </button>
		<?php endif; ?>

        <div id="doofinder-for-wc-spinner" class="doofinder-for-wc-spinner spinner"></div>

		<?php
	}
}
