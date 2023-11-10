<?php

namespace Doofinder\WP;

use Doofinder\WP\Multilanguage\Multilanguage;
use Doofinder\WP\Multilanguage\No_Language_Plugin;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;

class Landing_Cache
{

    const DF_LANDING_CACHE = "df_landing_cache";

    /**
     * Registers the custom REST endpoint to clear the landing cache.
     *
     * @since 1.0.0
     */
    public static function register_endpoint()
    {
        register_rest_route('doofinder/v1',  '/clear_landing_cache', array(
            'methods' => 'POST',
            'callback' => array(Landing_Cache::class, 'doomanager_clear_cache'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * Sets the landing cache with specified language prefix and data.
     *
     * @param string $lang_cache The cache prefix for the language.
     * @param mixed $landing_data The landing page data to be cached.
     *
     * @since 1.0.0
     */
    public static function set_cache($lang_cache, $landing_data)
    {
        set_transient($lang_cache, $landing_data, 15 * MINUTE_IN_SECONDS);
    }

    /**
     * Retrieves landing cache data based on the specified language prefix.
     *
     * @param string $lang_cache The cache prefix for the language.
     *
     * @return mixed The cached landing page data.
     *
     * @since 1.0.0
     */
    public static function get_cache_data($lang_cache)
    {
        return get_transient($lang_cache);
    }

    /**
     * Clears the landing cache for a specific language by deleting the transient.
     *
     * @param string $lang The language code.
     *
     * @since 1.0.0
     */
    public static function clear_cache($lang)
    {
        delete_transient(self::lang_cache($lang));
    }

    /**
     * Clears the landing cache for a specific language by deleting the transient.
     *
     * @param string $lang_cache The cache prefix for the language.
     *
     * @since 1.0.0
     */
    public static function clear_cache_by_prefix($lang_cache)
    {
        delete_transient($lang_cache);
    }

    /**
     * Generates the cache prefix for the specified language or default language if not provided.
     *
     * @param string|null $lang The language code.
     *
     * @return string The cache prefix.
     *
     * @since 1.0.0
     */
    public static function lang_cache($lang = null)
    {
        return is_null($lang) ? self::DF_LANDING_CACHE : self::DF_LANDING_CACHE . "_" . $lang;
    }

    /**
     * Clears the landings cache from doomanager for all languages.
     *
     * @param array $languages An array of language data.
     *
     * @since 1.0.0
     */
    private static function delete_all_caches($languages)
    {
        foreach ($languages as $language) {
            delete_transient(self::DF_LANDING_CACHE . "_" . $language['prefix']);
        }
    }

    /**
     * Clears the landings cache from doomanager using the custom REST endpoint.
     *
     * @param WP_REST_Request $request The REST request object.
     *
     * @return WP_REST_Response The REST response.
     *
     * @since 1.0.0
     */
    public static function doomanager_clear_cache(WP_REST_Request $request)
    {
        $language = Multilanguage::instance();

        if (is_a($language, No_Language_Plugin::class)) {
            delete_transient(self::DF_LANDING_CACHE);
        } else {
            $languages = $language->get_languages();
            self::delete_all_caches($languages);
        }

        return new WP_REST_Response(
            [
                'status' => WP_Http::OK,
                'response' => "All caches are clean"
            ]
        );
    }
}
