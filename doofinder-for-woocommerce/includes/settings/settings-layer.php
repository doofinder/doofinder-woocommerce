<?php

use Doofinder\WC\Doofinder_For_WooCommerce;
use Doofinder\WC\Multilanguage;
use Doofinder\WC\Settings\Settings;
use Doofinder\WC\Setup_Wizard;

defined( 'ABSPATH' ) or die;



$show_welcome_screen = !get_option( Setup_Wizard::$wizard_step_option ) && !Settings::is_configuration_complete()  && !Settings::is_api_configuration_complete();
$show_failed_migration_screen = !(Settings::is_configuration_complete() && Settings::is_api_configuration_complete()) && get_option(Setup_Wizard::$wizard_migration_option) === 'failed';

if (
	//First time user, configuration not completed
	$show_welcome_screen
	// Plugin upated but settings migration has failed
	|| $show_failed_migration_screen
	) :
?>
<style>
	.subsubsub {
		display: none;
	}

    p.submit {
        display: none;
	}
	.doofinder-button-setup-wizard {
		display: none;
	}
</style>
<script>
	document.querySelector('.doofinder-notice-setup-wizard').parentNode.style = "display:none;";
</script>
<?php
endif;

// Welcome screen for first time users

if ( $show_welcome_screen ) :
?>
<div class="doofinder-for-wc-welcome-screen">
	<div class="doofinder-for-wc-welcome-screen__icon">
		<img src="<?php echo Doofinder_For_WooCommerce::plugin_url() . 'assets/img/waving-hand_light-skin-tone.png'; ?>" alt="👋🏻" />
	</div>
	<h2 class="doofinder-for-wc-welcome-screen__heading">
		<?php _e('Welcome to Doofinder!','woocommerce-doofinder'); ?>
	</h2>
	<div class="doofinder-for-wc-welcome-screen__text">
		<h4><?php _e("Let's start by improving your store search.",'woocommerce-doofinder'); ?></h4>
	</div>
	<a class="button button-primary" href="<?php echo Setup_Wizard::get_url(); ?>"><?php _e('Start','woocommerce-doofinder'); ?></a>
</div>
<?php
return array();
endif;

// Info screen for users after update when migration failed
if ( $show_failed_migration_screen ) :
?>
<div class="doofinder-for-wc-welcome-screen">
	<div class="doofinder-for-wc-welcome-screen__icon">
		<img src="<?php echo Doofinder_For_WooCommerce::plugin_url() . 'assets/img/waving-hand_light-skin-tone.png'; ?>" alt="👋🏻" />
	</div>
	<h2 class="doofinder-for-wc-welcome-screen__heading">
		<?php _e('Hi again!','woocommerce-doofinder'); ?>
	</h2>
	<div class="doofinder-for-wc-welcome-screen__text">
		<h4><?php _e("We changed some stuff under the hood...<br><strong>...but your settings are not compatible</strong> 😅",'woocommerce-doofinder'); ?></h4>
	</div>
	<a class="button button-primary" href="<?php echo Setup_Wizard::get_url(); ?>"><?php _e('Setup Wizard','woocommerce-doofinder'); ?></a>
	<div class="doofinder-for-wc-welcome-screen__text">
		<p class=""><?php _e('This will create new search engines and settings','woocommerce-doofinder'); ?></p>
	</div>
</div>
<?php
return array();
endif;


$multilanguage = Multilanguage::instance();
$lang_affix    = $multilanguage->get_language_prefix();


$auth = array(
	array(
		'title' => __( 'Authentication', 'woocommerce-doofinder' ),
		'type'  => 'title',
		'desc'  => '',
		'id'    => 'internal_search_api',
	),

	array(
		'title'   => __( 'API Key', 'woocommerce-doofinder' ),
		'desc'    => '',
		'id'      => Settings::option_id( 'internal_search', 'api_key' ),
		'type'    => 'text',
		'css'     => 'width: 100%',
		'default' => '',
		// 'custom_attributes' => array('readonly' => 'readonly'),
	),

	// TODO Maybe hide this
	array(
		'title'   => __( 'API Host', 'woocommerce-doofinder' ),
		'desc'    => '',
		'id'      => Settings::option_id( 'internal_search', 'api_host' ),
		'type'    => 'text',
		'css'     => 'width: 100%',
		'class'	  => 'dfwc-url-input',
		'default' => '',
		// 'custom_attributes' => array('readonly' => 'readonly'),
	),


	// TODO Maybe hide this
	array(
		'title'   => __( 'Admin Endpoint', 'woocommerce-doofinder' ),
		'desc'    => '',
		'id'      => Settings::option_id( 'api', 'admin_endpoint' ),
		'type'    => 'text',
		'css'     => 'width: 100%',
		'class'	  => 'dfwc-url-input',
		'default' => '',
		// 'custom_attributes' => array('readonly' => 'readonly'),
	),

	array(
		'type' => 'sectionend',
		'id'   => 'internal_search_api',
	),
);

if ( $multilanguage->is_active() && ! $multilanguage->get_language_code() ) {
	echo $multilanguage->get_choose_language_notice(false);
	return $auth;
}

$search_engine = array();

if (
	$multilanguage->is_active() && $multilanguage->get_language_code() ||
	! $multilanguage->is_active()
) {
	$enable_question = __( 'Enable Internal Search', 'woocommerce-doofinder' );

	// If we have internationalization - ask a question for specific language.
	if ( $multilanguage->is_active() ) {
		$lang            = $multilanguage->get_current_language();
		$enable_question = sprintf( __( 'Enable Internal Search for %s', 'woocommerce-doofinder' ), $lang['name'] );
	}

	$search_engine = array(
		array(
			'title' => __( 'Search', 'woocommerce-doofinder' ),
			'type'  => 'title',
			'desc'  => '',
			'id'    => Multilanguage::code_suffix( 'internal_search_engine', $lang_affix ),
		),

		array(
			'title'   => __( 'Hash ID', 'woocommerce-doofinder' ),
			'desc'    => '',
			'id'      => Settings::option_id( 'internal_search', 'hashid', $lang_affix ),
			'type'    => 'text',
			'css'     => 'width: 100%',
			'default' => ''
		),

		array(
			'title'   => __( 'Search Server', 'woocommerce-doofinder' ),
			'desc'    => '',
			'id'      => Settings::option_id( 'internal_search', 'search_server' ), // Lang affix removed to make this setting global
			'type'    => 'text',
			'css'     => 'width: 100%',
			'class'	  => 'dfwc-url-input',
			'default' => '',
			// 'custom_attributes' => array('readonly' => 'readonly'),
		),

		array(
			'title'   => __( 'Layer Snippet', 'woocommerce-doofinder' ),
			'desc'    => '',//__( 'Paste here the Javascript code you will find in your Doofinder Control Panel under <em><strong>Configuration &gt; Installation Scripts &gt; Doofinder Layer</strong></em>.', 'woocommerce-doofinder' ),
			'id'      => Settings::option_id( 'layer', 'code', $lang_affix ),
			'css'     => 'margin-top: 5px; width: 100%; height: 350px; font-family: Consolas,Monaco,monospace; background: rgb(255 255 255 / 100%);',
			'type'    => 'textarea',
			'default' => ''
		),

		array(
			'title'   => $enable_question,
			'desc'    => '',
			'id'      => Settings::option_id( 'internal_search', 'enable', $lang_affix ),
			'type'    => 'checkbox',
			'default' => 'no',
		),

		array(
			'title'   => __( 'Enable Doofinder Layer', 'woocommerce-doofinder' ),
			'desc'    => '',
			'id'      => Settings::option_id( 'layer', 'enabled', $lang_affix ),
			'type'    => 'checkbox',
			'default' => 'no',
		),

		array(
			'title'   => __( 'Enable Banners', 'woocommerce-doofinder' ),
			'desc'    => __( '(Banners will be displayed above search results. You can use Doofinder Banner widget instead)', 'woocommerce-doofinder' ),
			'id'      => Settings::option_id( 'internal_search', 'banner', $lang_affix ),
			'type'    => 'checkbox',
			'default' => 'no',
		),

		array(
			'type' => 'sectionend',
			'id'   => Multilanguage::code_suffix( 'internal_search_engine', $lang_affix ),
		),
	);
}


return array_merge($auth, $search_engine);