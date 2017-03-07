<?php

namespace Doofinder\WC\Multilanguage;

class WPML implements I18n_Handler {

	/**
	 * A cached list of available languages in the format "get_languages" expects.
	 *
	 * @var array
	 */
	private $languages;

	/**
	 * @since 1.0.0
	 */
	public function __construct() {
		global $sitepress;

		$sitepress->switch_lang( $sitepress->get_default_language() );
		$active_languages = apply_filters( 'wpml_active_languages', NULL, 'orderby=id&order=desc' );
		$sitepress->switch_lang( ICL_LANGUAGE_CODE );

		$this->languages = array();

		foreach ( $active_languages as $active_language ) {
			$is_default = ( $active_language['code'] === $sitepress->get_default_language() );
			$language = array(
				'name'    => $active_language['translated_name'],
				'code'    => $active_language['code'],
				'active'  => $active_language['active'],
				'default' => $is_default,
				'prefix'  => ( $is_default ? '' : $active_language['code'] ),
			);

			$this->languages[ $active_language['code'] ] = $language;
		}
	}

	/**
	 * @since 1.0.0
	 * @inheritdoc
	 */
	public function get_default_language() {
		global $sitepress;
		return $this->languages[ $sitepress->get_default_language() ];
	}

	/**
	 * @since 1.0.0
	 * @inheritdoc
	 */
	public function get_current_language() {
		if ( defined( 'ICL_LANGUAGE_CODE' ) && isset( $this->languages[ ICL_LANGUAGE_CODE ] ) ) {
			return $this->languages[ ICL_LANGUAGE_CODE ];
		}

		return false;
	}

	/**
	 * @since 1.0.0
	 * @inheritdoc
	 */
	public function get_home_url( $language ) {
		global $sitepress;

		$sitepress->switch_lang( $language );
		$url = apply_filters( 'wpml_home_url', get_option( 'home' ) );
		$sitepress->switch_lang( $this->get_current_language() );

		return $url;
	}

	/**
	 * @since 1.0.0
	 * @inheritdoc
	 */
	public function get_feed_link( $name, $language = '' ) {
		if ( empty( $language ) ) {
			return get_feed_link( $name );
		}

		global $sitepress;

		$sitepress->switch_lang( $language );
		$link = get_feed_link( $name );
		$sitepress->switch_lang( ICL_LANGUAGE_CODE );

		return $link;
	}

	/**
	 * @since 1.0.0
	 * @inheritdoc
	 */
	public function get_languages() {
		return $this->languages;
	}

	/**
	 * @since 1.0.0
	 * @inheritdoc
	 */
	public function get_posts( $args, $language ) {
		global $sitepress;

		$sitepress->switch_lang( $language );
		$posts = get_posts( $args );
		$sitepress->switch_lang( $this->get_current_language() );

		return $posts;
	}
}
