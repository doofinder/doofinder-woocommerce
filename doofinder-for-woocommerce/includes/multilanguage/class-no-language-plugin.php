<?php

namespace Doofinder\WC\Multilanguage;

class No_Language_Plugin extends Language_Plugin {

	/**
	 * @inheritdoc
	 */
	public function get_languages() {
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function get_formatted_languages() {
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function get_active_language() {
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function get_current_language() {
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function get_default_language() {
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function get_base_language() {
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function get_posts_ids( $language_code, $post_type, $ids_greater_than, $number_of_posts ) {
		global $wpdb;

		$query = "
			SELECT ID
			FROM $wpdb->posts
			WHERE $wpdb->posts.post_type = '{$post_type}'
			AND $wpdb->posts.ID > $ids_greater_than
			ORDER BY $wpdb->posts.ID
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
	 * Retrieve the name of the wordpress option
	 * for the current languages.
	 *
	 * Some fields in Doofinder settings will have different values,
	 * depending on language.
	 *
	 * @param string $base
	 *
	 * @return string
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
