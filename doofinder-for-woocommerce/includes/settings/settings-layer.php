<?php

use Doofinder\WC\Multilanguage;
use Doofinder\WC\Settings\Settings;

defined( 'ABSPATH' ) or die;

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
