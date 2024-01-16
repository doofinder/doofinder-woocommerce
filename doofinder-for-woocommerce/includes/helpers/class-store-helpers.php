<?php

namespace Doofinder\WP\Helpers;

use Exception;
use WP_Application_Passwords;

class Store_Helpers
{

    /**
     * Generates an api_password and returns the store options.
     *
     * @return array Store options
     */
    public static function get_store_options()
    {
        $endpoints_token = self::create_endpoints_token();

        update_option('doofinder_for_wp_token', $endpoints_token);
     
        return [
            "url" => get_bloginfo('url'),
            'df_token'    => $endpoints_token,
        ];
    }

    /**
     * To create a new token that will be used to authenticate new product endpoints via headers
     *
     * @return string New token to authenticate woocommerce endpoints created by doofinder
     */
    public static function create_endpoints_token()
    {
        $randomString = uniqid();
        return md5($randomString);
    }
}
