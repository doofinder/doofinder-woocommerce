<?php
/**
 * DooFinder Multilanguage methods.
 *
 * @package Doofinder\WP\Multilanguage
 */

namespace Doofinder\WP\Multilanguage;

/**
 * Handles the Multilanguage in the site.
 */
class Multilanguage {

	/**
	 * Singleton instance of class that implements
	 * Language_Plugin interface.
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Flag if site is multilang or not.
	 *
	 * @var bool
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
				self::$instance     = new WPML();
				self::$is_multilang = true;

				return self::$instance;
			}

			// TODO Test and finish Polylang support implementation.
			// phpcs:disable
			/*
			if ( defined( 'POLYLANG_BASENAME' ) ) {
				self::$instance = new Polylang();
				self::$is_multilang = true;

				return self::$instance;
			}
			*/
			// phpcs:enable
		}

		// Still no instance?
		// That means we have no Multilanguage plugins installed.
		if ( ! self::$instance ) {
			self::$is_multilang = false;
			self::$instance     = new No_Language_Plugin();
		}

		return self::$instance;
	}
}
