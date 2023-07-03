<?php

namespace Doofinder\WP\Multilanguage;

use Doofinder\WP\Helpers\Helpers;
use Doofinder\WP\Log;
use Doofinder\WP\Settings\Settings;

class WPML implements I18n_Handler
{

	/**
	 * A cached list of available languages in the format "get_languages" expects.
	 *
	 * @var array
	 */
	private $languages;

	/**
	 * Instance of a class used to log to a file.
	 *
	 * @var Log
	 */
	private $log;

	/**
	 * @since 1.0.0
	 */
	public function __construct()
	{
		global $sitepress;

		$this->log = new Log('api.txt');

		$sitepress->switch_lang($sitepress->get_default_language());
		$active_languages = apply_filters('wpml_active_languages', NULL, 'orderby=id&order=desc');

		if (defined('ICL_LANGUAGE_CODE')) {
			$sitepress->switch_lang(ICL_LANGUAGE_CODE);
		}

		$this->languages = array();

		foreach ($active_languages as $active_language) {
			$is_default = ($active_language['code'] === $sitepress->get_default_language());
			$language = array(
				'name' => $active_language['native_name'] ?? '',
				'english_name' => $active_language['english_name'] ?? '',
				'code' => $active_language['code'] ?? '',
				'active' => $active_language['active'] ?? '',
				'default' => $is_default,
				'prefix' => ($is_default ? '' : ($active_language['code'] ?? '')),
				'locale' => $active_language['default_locale'] ?? ''
			);

			$this->languages[$active_language['code']] = $language;
		}
	}

	/**
	 * @since 1.0.0
	 * @inheritdoc
	 */
	public function get_default_language()
	{
		global $sitepress;
		return $this->languages[$sitepress->get_default_language()] ?? '';
	}

	/**
	 * @inheritdoc
	 */
	public function get_base_language()
	{
		global $sitepress;

		$lang_code = $sitepress->get_default_language();
		return $this->languages[$lang_code]['code'];
	}


	public function get_base_locale()
	{
		global $sitepress;

		$lang_code = $sitepress->get_default_language();
		return $this->languages[$lang_code]['locale'];
	}

	public function get_locale_by_lang_code($lang_code){
		return $this->languages[$lang_code]['locale'];
	}

	/**
	 * @inheritdoc
	 */
	public function get_active_language()
	{
		global $sitepress;

		if ($sitepress) {
			// WPML allows us to select "All languages"./
			// Let's treat it as no language selected.
			$lang = $sitepress->get_current_language() ?? '';

			if ($lang === 'all') {
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
	public function get_current_language()
	{
		global $sitepress;

		$lang = $sitepress ? $sitepress->get_current_language() : null;

		if ($sitepress && isset($this->languages[$lang])) {

			// WPML allows us to select "All languages"./
			// Let's treat it as no language selected.
			if ($this->languages[$lang] === 'all') {
				return '';
			}


            if($lang === $this->get_base_language()){
                return '';
            }

			return $lang;
		}

		return false;
	}

	/**
	 * @since 1.0.0
	 * @inheritdoc
	 */
	public function get_home_url($language)
	{
		global $sitepress;

		$sitepress->switch_lang($language);
		$url = apply_filters('wpml_home_url', get_option('home'));
		$sitepress->switch_lang($this->get_current_language());

		return $url;
	}

	/**
	 * @since 1.0.0
	 * @inheritdoc
	 */
	public function get_languages()
	{
		return $this->languages;
	}

    /**
     * @inheritdoc
     */
    public function get_formatted_languages()
    {
        if (!function_exists('icl_get_languages')) {
            return array();
        }

        // "wpml_active_languages" filters the list of the
        // languages enabled (active) for a site.
        $languages = apply_filters('wpml_active_languages', null, 'orderby=code&order=desc');

        if (empty($languages)) {
            return array();
        }

        // Create associative array with lang code / lang name pairs.
        // For example 'en' => 'English'.
        $formatted_languages = array();
        foreach ($languages as $key => $value) {
            $language_code = $value['default_locale'];
            $translated_name = isset($value['translated_name']) ? $value['translated_name'] : '';
            if(empty($translated_name)){
                $translated_name = isset($value['display_name']) ? $value['display_name'] : '';
            }
            $formatted_languages[$language_code] = $translated_name;
        }

        return $formatted_languages;
    }

	/**
	 * @inheritdoc
	 */
	public function get_option_name($base)
	{
		$language_code = $this->get_active_language();
		if (!$language_code) {
			return $base;
		}

		$base_language = $this->get_base_language();
		if ($language_code === $base_language) {
			return $base;
		}

		//Replace hyphens with underscores in language code
		$language_code = Helpers::get_language_from_locale($language_code);

		return "{$base}_{$language_code}";
	}
}
