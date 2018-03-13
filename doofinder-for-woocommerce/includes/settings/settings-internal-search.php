<?php

use Doofinder\WC\Multilanguage;
use Doofinder\WC\Settings\Settings;

defined( 'ABSPATH' ) or die;

$multilanguage = Multilanguage::instance();
$lang_affix    = $multilanguage->get_language_prefix();

$api = array(
	array(
		'title' => __( 'API Key', 'woocommerce-doofinder' ),
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
	),

	array(
		'type' => 'sectionend',
		'id'   => 'internal_search_api',
	),
);

$search_engine = array();
if (
	$multilanguage->is_active() && $multilanguage->get_language_code() ||
	! $multilanguage->is_active()
) {
	$enable_question = __( 'Enable?', 'woocommerce-doofinder' );

	// If we have internationalization - ask a question for specific language.
	if ( $multilanguage->is_active() ) {
		$lang            = $multilanguage->get_current_language();
		$enable_question = sprintf( __( 'Enable for %s?', 'woocommerce-doofinder' ), $lang['name'] );
	}

	$search_engine = array(
		array(
			'title' => __( 'Search Engine', 'woocommerce-doofinder' ),
			'type'  => 'title',
			'desc'  => '',
			'id'    => Multilanguage::code_suffix( 'internal_search_engine', $lang_affix ),
		),

		array(
			'title'   => $enable_question,
			'desc'    => '',
			'id'      => Settings::option_id( 'internal_search', 'enable', $lang_affix ),
			'type'    => 'checkbox',
			'default' => 'no',
		),

		array(
			'title'   => __( 'Hash ID', 'woocommerce-doofinder' ),
			'desc'    => '',
			'id'      => Settings::option_id( 'internal_search', 'hashid', $lang_affix ),
			'type'    => 'text',
			'css'     => 'width: 100%',
			'default' => '',
		),

		array(
			'title'   => __( 'Display banners above search results', 'woocommerce-doofinder' ),
			'desc'    => __( '(You can disable this and use the Doofinder Banner widget instead)', 'woocommerce-doofinder' ),
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

return array_merge( $api, $search_engine );
