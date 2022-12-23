<?php

namespace Doofinder\WC\Helpers;

use Doofinder\WC\Settings\Settings;

class Helpers
{

    /**
     * Returns `true` if we are currently in debug mode, `false` otherwise.
     *
     * @return bool
     */
    public static function is_debug_mode()
    {
        return Settings::get_enable_debug_mode();
    }

    /**
     * Check if string contains https:// if not then add it
     */
    public static function prepare_host($host)
    {
        if ($host) {
            $has_http = preg_match('@^http[s]?:\/\/@', $host);
            $host = !$has_http ? 'https://' . $host : $host;
        }
        return $host;
    }

    public static function get_memory_usage($real = false, $peak = false, $in_megabytes = false)
    {

        $unit = $in_megabytes ? ' MB' : ' bytes';
        $megabyte = 1048576;

        if ($peak) {
            $amount = $in_megabytes ? round(memory_get_peak_usage($real) / $megabyte, 2) : number_format(memory_get_peak_usage($real), 0, ',', ' ');
            $amount_in_mb = round(memory_get_peak_usage($real) / $megabyte, 2);
        } else {
            $amount = $in_megabytes ? round(memory_get_usage($real) / $megabyte, 2) : number_format(memory_get_usage($real), 0, ',', ' ');
            $amount_in_mb = round(memory_get_usage($real) / $megabyte, 2);
        }

        return $amount . $unit . ' ' . ' (' . $amount_in_mb . ' MB)';
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

    /**
     * Unfortunately while updating we can't obtain the languages for each site 
     * with our own methods, so we have to make a query to get the available 
     * languages for each site.
     *
     * @param int $blog_id
     * @return array Available languages for the given blog_id
     */
    public static function getSiteLanguages($blog_id)
    {
        $blog_languages = array();
        global $wpdb;
        $blog_id = $blog_id == 1 ? '' :  $blog_id;

        $query_blog_id = $blog_id == '' ? '' :  $blog_id . "_";

        $QUERY  = $wpdb->prepare('SELECT code,  default_locale FROM ' . $wpdb->base_prefix . $query_blog_id . 'icl_languages WHERE active =  %d', 1) . PHP_EOL;
        $result = $wpdb->get_results($QUERY);

        $blog_languages = [];
        if (!empty($result)) {
            foreach ($result as $key2 => $langObj) {
                $blog_languages[$langObj->code] = [
                    'code' => $langObj->code,
                    'default_locale' => $langObj->default_locale,
                ];
            }
        }

        return $blog_languages;
    }
}
