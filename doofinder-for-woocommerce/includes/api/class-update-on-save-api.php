<?php

namespace Doofinder\WP\Api;

use Doofinder\WP\Settings;
use Doofinder\WP\Log;

use Endpoint_Product;
use Endpoint_Custom;

/**
 * Handles requests to the Management API.
 */
class Update_On_Save_Api
{
    /**
     * Instance of a class used to log to a file.
     *
     * @var Log
     */
    private $log;

    /**
     * API Host
     *
     * @var string
     */
    private $api_host;

    /**
     * API Key
     *
     * @var string
     */
    private $api_key;

    /**
     * Dooplugins Host
     *
     * @var string
     */
    private $dp_host;

    /**
     * Hash
     * The search engine's unique id
     *
     * @var string
     */
    private $hash;

    /**
     * Authorization Header
     *
     * @var string
     */
    private $authorization_header;

    public function __construct($language)
    {
        $this->log                  = new Log('update-on-save-api.log');
        $this->api_key              = Settings::get_api_key();
        $this->api_host             = Settings::get_api_host();
        $this->dp_host              = Settings::get_dooplugins_host();
        $this->hash                 = Settings::get_search_engine_hash($language);
        $this->authorization_header = array(
            'Authorization' => "Token $this->api_key",
            'content-type'  => 'application/json'
        );

        $this->log->log('Create Management API Client');
        $this->log->log('API Host: ' . $this->api_host);
        $this->log->log('Hash: ' . $this->hash);
    }

    /**
     * Handle sending requests to API
     *
     * @param $url
     * @param $data
     *
     */
    private function sendRequest($url, $data, $method = 'POST')
    {
        $this->log->log("Making a request to: $url");
        $data = [
            'headers' => $this->authorization_header,
            'method'  => $method,
            'body' => json_encode($data),
        ];

        $response = wp_remote_request($url, $data);

        if (!is_wp_error($response) && $response['response']['code'] === 200) {
            $this->log->log("The update on save request has been processed correctly");
            return TRUE;
        } else {
            $error_message = $response->get_error_message();
            $this->log->log("Error in the request: $error_message");
        }
        return false;
    }

    public function buildURL($host, $path)
    {
        return "{$host}/{$path}";
    }

    /**
     * Updates multiple items in the Doofinder index.
     *
     * This method updates multiple items of a specific post type in the Doofinder index.
     * It sends a POST request to the Doofinder API with the data to be updated.
     *
     * @param string $post_type The post type for which the items should be updated.
     * @param array  $ids      The ids representing the items to be updated.
     * @return mixed The response from the Doofinder API.
     * @since 1.0.0
     */
    public function updateBulk($post_type, $ids)
    {
        $this->log->log('Update items');
        $uri = $this->buildURL($this->dp_host, "item/" . $this->hash . "/" . $post_type . "?action=update&platform=wordpress");

        $chunks = array_chunk($ids, 100);
        $resp = true;

        foreach ($chunks as $chunk) {
            $items    = $this->get_items($chunk, $post_type);
            $resp     = $resp && $this->sendRequest($uri, $items);
        }
        return $resp;
    }

    /**
     * Get items data from our endpoint products (depends with post_type)
     *
     * @param array  $ids ID product we want to get data
     * @param string  $post_type Type of item to request
     * @return array API response with items list
     * @since 1.0.0
     */
    public function get_items($ids, $post_type){
        if($post_type == "product"){
            return $this->get_products_data($ids);
        }
        else{
            return $this->get_custom_data($ids, $post_type);
        }
    }

    /**
     * Get products data from our endpoint products
     *
     * @param array  $ids ID product we want to get data
     * @param array  $feedtype Feedtype Product
     * @since 1.0.0
     */
    public function get_products_data($ids){

        require_once ('endpoints/class-endpoint-product.php');
        return Endpoint_Product::get_data($ids);
    }

    /**
     * Get products data from our endpoint products
     *
     * @param array  $ids ID product we want to get data
     * @param array  $feedtype Feedtype Product
     * @since 1.0.0
     */
    public function get_custom_data($ids, $post_type){

        require_once ('endpoints/class-endpoint-custom.php');
        return Endpoint_Custom::get_data($ids, $post_type);
    }

    /**
     * Deletes multiple items from the Doofinder index.
     *
     * This method deletes multiple items of a specific post type from the Doofinder index.
     * It sends a POST request to the Doofinder API with the data to be deleted.
     *
     * @param string $post_type The post type for which the items should be deleted.
     * @param array  $ids      The ids representing the items to be deleted.
     * @return mixed The response from the Doofinder API.
     * @since 1.0.0
     */
    public function deleteBulk($post_type, $ids)
    {
        $this->log->log('Delete items');

        $uri = $this->buildURL($this->dp_host, "item/" . $this->hash . "/" . $post_type . "?action=delete&platform=wordpress");

        return $this->sendRequest($uri, $ids);
    }
}
