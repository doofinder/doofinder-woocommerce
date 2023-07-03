<?php

namespace Doofinder\WP\Multilanguage;

class No_Language_Plugin extends Language_Plugin
{

    /**
     * @inheritdoc
     */
    public function get_languages()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function get_formatted_languages()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function get_active_language()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function get_current_language()
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function get_default_language()
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function get_base_language()
    {
        return '';
    }

    public function get_base_locale(){
        return get_locale();
    }

    /**
     * @inheritdoc
     */
    public function get_home_url($language)
    {
        return get_bloginfo('url');
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

        return "{$base}_{$language_code}";
    }
}
