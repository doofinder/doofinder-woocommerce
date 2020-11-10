<?php

namespace Doofinder\WC\Multilanguage;

class Multilanguage {

	/**
	 * Singleton instance of class that implements
	 * Language_Plugin interface.
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Flag if site is multilang or not
	 */
	public static $is_multilang = false;

	/**
	 * Create (or retrieve, if already exists) the singleton
	 * instance of class that implements Language_Plugin
	 * interface.
	 *
	 * @return Language_Plugin
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			if ( class_exists( 'SitePress' ) ) {
				self::$instance = new WPML();
				self::$is_multilang = true;

				return self::$instance;
			}

			// TODO Test and finish Polylang support implementation
			/*
			if ( defined( 'POLYLANG_BASENAME' ) ) {
				self::$instance = new Polylang();
				self::$is_multilang = true;

				return self::$instance;
			}
			*/
		}

		// Still no instance?
		// That means we have no Multilanguage plugins installed.
		if ( ! self::$instance ) {
			self::$is_multilang = false;
			self::$instance = new No_Language_Plugin();
		}

		return self::$instance;
	}
}
