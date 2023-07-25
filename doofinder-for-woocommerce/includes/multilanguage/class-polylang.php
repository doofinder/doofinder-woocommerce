<?php

namespace Doofinder\WP\Multilanguage;

class Polylang extends Language_Plugin
{

    /**
     * There is a built in method "pll_the_languages" but it only works
     * on the frontend. Even if we try to extract from it functionalities
     * that just grab languages from DB it still fails on the backend
     * because some functions are missing (due to Polylang including
     * different files on the backend and different on the front).
     *
     * Therefore we have to grab the languages from the taxonomy terms manually.
     *
     * @inheritdoc
     */
    public function get_languages()
    {
        $language_slugs = array();

        $languages = get_terms(array('taxonomy' => 'language'));
        foreach ($languages as $language) {
            $language_slugs[$language->slug] = $language->name;
        }

        return $language_slugs;
    }

    /**
     * @inheritdoc
     */
    public function get_active_language()
    {
        // Sometimes even if Polylang constant exists the function does not.
        if (!function_exists('pll_current_language')) {
            return '';
        }

        // There's "Show all languages" option. When selected we want to treat
        // it as if no language was selected. Luckily Polylang already
        // returns empty string for it so we don't have to do anything else.
        return \pll_current_language();
    }

    /**
     * @inheritdoc
     */
    public function get_current_language()
    {
        // Sometimes even if Polylang constant exists the function does not.
        if (!function_exists('pll_current_language')) {
            return '';
        }

        // There's "Show all languages" option. When selected we want to treat
        // it as if no language was selected. Luckily Polylang already
        // returns empty string for it so we don't have to do anything else.
        return \pll_current_language();
    }

    /**
     * @inheritdoc
     */
    public function get_base_language()
    {
        return \pll_default_language();
    }

    /**
     * @inheritdoc
     */
    public function get_default_language()
    {
        return \pll_default_language();
    }
}
