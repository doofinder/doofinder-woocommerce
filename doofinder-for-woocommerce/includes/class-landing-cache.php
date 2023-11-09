<?php

namespace Doofinder\WP;

use WP_REST_Response;
use WP_REST_Request;
use Doofinder\WP\Multilanguage\Multilanguage;
use Doofinder\WP\Multilanguage\No_Language_Plugin;

class Landing_Cache
{

    const DF_LANDING_CACHE = "df_landing_cache";


    public static function register_endpoint()
    {
        register_rest_route('doofinder/v1',  '/clear_landing_cache', array(
            'methods' => 'POST',
            'callback' => array(Landing_Cache::class, 'doomanager_clear_cache'),
            'permission_callback' => '__return_true'
        ));
    }

    public static function set_cache($lang_cache, $landing_data)
    {
        // Cache the data for 15 minutes (900 seconds).
        set_transient($lang_cache, $landing_data, 900);
    }

    public static function get_cache_data($lang_cache)
    {
        return get_transient($lang_cache);
    }

    /**
     * Clears the landing cache for specific lang  by deleting the transient.
     */
    public static function clear_cache($lang)
    {
        delete_transient(self::lang_cache($lang));
    }

    public static function lang_cache($lang = null)
    {
        return is_null($lang) ? self::DF_LANDING_CACHE : self::DF_LANDING_CACHE . "_" . $lang;
    }

    /**
     * Clears the landings cache from doomanager.
     */
    public static function doomanager_clear_cache(WP_REST_Request $request)
    {
        $language                     = Multilanguage::instance();

        if (is_a($language, No_Language_Plugin::class)) {
            delete_transient(self::DF_LANDING_CACHE);
        } else {
            $languages = $language->get_languages();
            self::delete_all_caches($languages);
        }

        return new WP_REST_Response(
            [
                'status' => 200,
                'response' => "All caches are clean"
            ]
        );
    }

    private static function delete_all_caches($languages)
    {
        foreach ($languages as $language) {
            delete_transient(self::DF_LANDING_CACHE . "_" . $language['prefix']);
        }
    }
}
