<?php

namespace Doofinder\WC\Helpers;

use Doofinder\WC\Settings\Settings;

class Helpers {

    /**
     * Returns `true` if we are currently in debug mode, `false` otherwise.
     *
     * @return bool
     */
    public static function is_debug_mode() {
        return Settings::get_enable_debug_mode();
    }

    /**
     * Check if string contains https:// if not then add it
     */
    public static function prepare_host($host) {
        if($host) {
            $has_http = preg_match('@^http[s]?:\/\/@',$host);
            $host = !$has_http ? 'https://' . $host : $host;
        }
        return $host;
    }

    /**
     * Recursive in_array() for multidimensional arrays
     *
     * @return bool
     */
    public static function in_array_r($needle, $haystack, $strict = false) {
       
        foreach ($haystack as $item) {
            if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && self::in_array_r($needle, $item, $strict))) {

                return true;
            }
        }
        return false;
    }
}
