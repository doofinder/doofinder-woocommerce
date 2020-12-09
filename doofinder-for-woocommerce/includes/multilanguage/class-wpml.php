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
		
		if ( defined('ICL_LANGUAGE_CODE') ) {
			$sitepress->switch_lang( ICL_LANGUAGE_CODE );
		}

		$this->languages = array();

		foreach ( $active_languages as $active_language ) {
			$is_default = ( $active_language['code'] === $sitepress->get_default_language() );
			$language = array(
				'name'    => $active_language['native_name'] ?? '',
				'english_name' => $active_language['english_name'] ?? '',
				'code'    => $active_language['code'] ?? '',
				'active'  => $active_language['active'] ?? '',
				'default' => $is_default,
				'prefix'  => ( $is_default ? '' : ($active_language['code'] ?? '') ),
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
		return $this->languages[ $sitepress->get_default_language() ] ?? '';
	}

	/**
	 * @inheritdoc
	 */
	public function get_base_language() {
		global $sitepress;

		return $sitepress->get_default_language();
	}

	/**
	 * @inheritdoc
	 */
	public function get_active_language() {
		global $sitepress;

		if ( $sitepress ) {
			// WPML allows us to select "All languages"./
			// Let's treat it as no language selected.
			$lang = $sitepress->get_current_language() ?? '';

			if ( $lang === 'all' ) {
				return '';
			}

			return $lang;
		}

		return '';
	}

	/**
	 * @since 1.0.0
	 * @inheritdoc
	 */
	public function get_current_language() {
		global $sitepress; 

		$lang = $sitepress ? $sitepress->get_current_language() : null;

		if ( $sitepress && isset( $this->languages[ $lang  ] ) ) {

			// WPML allows us to select "All languages"./
			// Let's treat it as no language selected.
			if ( $this->languages[ $lang ] === 'all' ) {
				return '';
			}
	
			return $this->languages[ $lang ];
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
	 * @inheritdoc
	 */
	public function get_formatted_languages() {
		if ( ! function_exists( 'icl_get_languages' ) ) {
			return array();
		}

		// "wpml_active_languages" filters the list of the
		// languages enabled (active) for a site.
		$languages = apply_filters( 'wpml_active_languages', null, 'orderby=code&order=desc' );

		if ( empty( $languages ) ) {
			return array();
		}

		// Create associative array with lang code / lang name pairs.
		// For example 'en' => 'English'.
		$formatted_languages = array();
		foreach ( $languages as $key => $value ) {
			$formatted_languages[ $key ] = $value['translated_name'];
		}

		return $formatted_languages;
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

	/**
	 * @inheritdoc
	 */
	public function get_posts_ids( $language_code, $post_type, $ids_greater_than, $number_of_posts ) {
		global $wpdb;

		$query = "
			SELECT element_id
			FROM {$wpdb->prefix}icl_translations
			WHERE {$wpdb->prefix}icl_translations.language_code = '$language_code'
			AND {$wpdb->prefix}icl_translations.element_type = 'post_{$post_type}'
			AND {$wpdb->prefix}icl_translations.element_id > $ids_greater_than 
			ORDER BY {$wpdb->prefix}icl_translations.element_id
			LIMIT $number_of_posts
		";

		$ids = $wpdb->get_results( $query, ARRAY_N );

		if ( ! $ids ) {
			return array();
		}

		return array_map( function ( $item ) {
			return $item[0];
		}, $ids );
	}

	/**
	 * @inheritdoc
	 */
	public function get_option_name( $base ) {
		$language_code = $this->get_active_language();
		if ( ! $language_code ) {
			return $base;
		}

		$base_language = $this->get_base_language();
		if ( $language_code === $base_language ) {
			return $base;
		}

		return "{$base}_{$language_code}";
	}
}
