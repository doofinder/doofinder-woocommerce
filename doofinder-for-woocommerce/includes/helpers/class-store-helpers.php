<?php

namespace Doofinder\WP\Helpers;

use Exception;
use WP_Application_Passwords;

class Store_Helpers
{

    /**
     * Generates an api_password and returns the store options.
     *
     * @return void
     */
    public static function get_store_options()
    {
        $password_data = Store_Helpers::create_application_credentials();
        if (!is_null($password_data)) {
            return [
                "url" => get_bloginfo('url'),
                'api_pass' => $password_data['api_pass'],
                'api_user' => $password_data['api_user']
            ];
        } else {
            throw new Exception("Error creating application credentials");
        }
    }

    /**
     * Creates a new application password.
     * If a password exists, it deletes it and creates a new password.
     *
     * We store the user_id and the uuid in order to know which application
     * password we must delete.
     *
     * @return array Array containing api_user and api_pass
     */
    private static function create_application_credentials()
    {
        $user_id = get_current_user_id();
        $user = get_user_by('id',  $user_id);
        $credentials_option_name = "doofinder_for_wp_app_credentials_" . get_current_blog_id();
        $credentials = get_option($credentials_option_name);
        $password_data = NULL;
        $app_name = 'doofinder_' . get_current_blog_id();

        if (is_array($credentials) && array_key_exists('user_id', $credentials) &&  array_key_exists('uuid', $credentials)) {
            WP_Application_Passwords::delete_application_password($credentials['user_id'], $credentials['uuid']);
        }

        if (!WP_Application_Passwords::application_name_exists_for_user($user_id, $app_name)) {
            $app_pass = WP_Application_Passwords::create_new_application_password($user_id, array('name' => $app_name));
            $credentials = [
                'user_id' => $user_id,
                'uuid' => $app_pass[1]['uuid']
            ];
            update_option($credentials_option_name, $credentials);

            $password_data = [
                'api_user' => $user->data->user_login,
                'api_pass' => $app_pass[0]
            ];
        }
        return $password_data;
    }
}
