<?php

namespace Doofinder\WP;

/**
 * Class Endpoints
 *
 * This class is responsible for initializing and managing API endpoints within the WordPress plugin.
 * API endpoints are defined in separate PHP files located in the 'api/endpoints' directory.
 * It scans these files, includes them, and initializes the associated endpoints and their functionalities.
 *
 * @package Doofinder\WP
 */
class Endpoints
{
    /**
     * @var array $endpoints_api An array to store registered API endpoints.
     */
    public static $endpoints_api = array();

    /**
     * Initialize the Endpoints class and its associated API endpoints.
     *
     * This method scans the 'api/endpoints' directory for PHP files, includes them,
     * and initializes the associated endpoints. It also sets up a filter to check permissions
     * for WooCommerce REST API endpoints.
     */
    public static function init()
    {
        $endpoints_dir  = __DIR__ . '/api/endpoints';
        $endpoint_files = scandir($endpoints_dir);

        foreach ($endpoint_files as $file) {
            if (is_file($endpoints_dir . '/' . $file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {

                $class_name = pathinfo($file, PATHINFO_FILENAME);
                $class_name = str_replace('-', ' ', $class_name);
                $class_name = str_replace('class ', '', $class_name);
                $class_name = ucwords($class_name);
                $class_name = str_replace(' ', '_', $class_name);

                include_once($endpoints_dir . "/" . $file);

                if (class_exists($class_name) && method_exists($class_name, 'initialize')) {
                    self::$endpoints_api[] = "/" . constant("$class_name::CONTEXT") . constant("$class_name::ENDPOINT");
                    call_user_func([$class_name, 'initialize']);
                }
            }
        }

        add_filter('woocommerce_rest_check_permissions', function ($permission, $context, $object_id, $post_type) {
            if (isset($_GET["rest_route"]) && in_array($_GET["rest_route"], self::$endpoints_api)) {
                return true;
            }
            return $permission;
        }, 10, 4);
    }

    /**
     * Verify a security token in the HTTP header.
     *
     * This function checks whether a security token in the HTTP header matches
     * an md5 hash of the server's host name. If they do not match, it returns a
     * 403 (Forbidden) error response and terminates execution.
     */
    public static function CheckSecureToken() {

        $token_rcv = $_SERVER["HTTP_DOOFINDER_TOKEN"] ?? false;
        $token_chk = get_option("doofinder_for_wp_token");

        if(!$token_chk || $token_chk != $token_rcv){
            header('HTTP/1.1 403 Forbidden', true, 403);
            $msgError = 'Forbidden access. Maybe security token missed.';
            exit($msgError);
        }
    }
}
