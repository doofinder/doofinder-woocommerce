<?php

namespace Doofinder\WP;

class Helpers {

    /**
     * Returns `true` if we are currently in debug mode, `false` otherwise.
     *
     * @return bool
     */
    public static function is_debug_mode() {
        return WP_DEBUG && ! Settings::get_disable_debug_mode();
    }

    /**
     * This function extracts the language code from a language country code.
     * Example: 'en-US' or 'en_US' is converted to to ISO 639-1 language code
     * en.
     *
     * @param string $language_code
     * @return string The language code
     */
    public static function get_language_from_locale($language_code)
    {
        $language_code = str_replace("-", "_", $language_code);
        return explode("_", $language_code)[0];
    }

    /**
     * Recursive in_array() for multidimensional arrays
     *
     * @return bool
     */
    public static function in_array_r($needle, $haystack, $strict = false)
    {

        foreach ($haystack as $item) {
            if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && self::in_array_r($needle, $item, $strict))) {

                return true;
            }
        }
        return false;
    }

    /**
     * This function converts a locale code (language and country code) from 
     * 'en-US' to 'en_US' format.
     *
     * @param string $locale_code
     * @return string The formatted locale code
     */
    public static function format_locale_to_underscore($locale_code)
    {
        return str_replace("-", "_", $locale_code);
    }

    /**
     * This function converts a locale code (language and country code) from 
     * 'en_US' to 'en-US' format used by Live Layer.
     *
     * @param string $locale_code
     * @return string The formatted locale code
     */
    public static function format_locale_to_hyphen($locale_code)
    {
        return str_replace("_", "-", $locale_code);
    }
}
