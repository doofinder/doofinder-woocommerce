<?php

namespace Doofinder\WC\Multilanguage;

use Doofinder\WC\Log;
use Doofinder\WC\Settings\Settings;

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

		$log = new Log('api.txt');

		// Set post types to query depending on split_variable option
		if ('yes' === Settings::get( 'feed', 'split_variable' ) && $post_type === 'product') {
			$query = "
			SELECT DISTINCT posts.ID
			FROM $wpdb->posts as posts
			LEFT JOIN $wpdb->posts as postparents
				ON posts.post_parent = postparents.ID
			WHERE (posts.post_type = '{$post_type}' OR posts.post_type = '{$post_type}_variation')
			AND posts.post_status = 'publish'
			AND (postparents.post_status IS NULL OR postparents.post_status = 'publish') 
			AND posts.ID > $ids_greater_than
			ORDER BY posts.ID
			LIMIT $number_of_posts
		";
		} else {
			$query = "
			SELECT DISTINCT $wpdb->posts.ID
			FROM $wpdb->posts
			WHERE $wpdb->posts.post_type = '{$post_type}'
			AND $wpdb->posts.post_status = 'publish'
			AND $wpdb->posts.ID > $ids_greater_than
			ORDER BY $wpdb->posts.ID
			LIMIT $number_of_posts
		";
		}

		

		$log->log( 'Get Posts IDs - Query:' );
		$log->log($query);

		$ids = $wpdb->get_results( $query, ARRAY_N );

		//$log->log( 'Get Posts IDs - Result:' );
		//$log->log( $ids );

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
