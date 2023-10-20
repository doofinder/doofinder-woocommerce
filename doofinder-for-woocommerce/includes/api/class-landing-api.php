<?php

namespace Doofinder\WP\Api;

use Doofinder\WP\Helpers;
use Doofinder\WP\Helpers\Store_Helpers;
use Doofinder\WP\Setup_Wizard;
use Doofinder\WP\Log;
use Doofinder\WP\Multilanguage\Multilanguage;
use Doofinder\WP\Multilanguage\No_Language_Plugin;
use Doofinder\WP\Settings;
use Exception;
use WP_Application_Passwords;
use WP_Http;

defined('ABSPATH') or die();

class Landing_Api
{

    /**
     * Instance of a class used to log to a file.
     *
     * @var Log
     */
    private $log;

    /**
     * Instance of a class used to log to a file.
     *
     * @var Multilanguage
     */
    private $language;


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

    public function __construct()
    {
        // Get global disable_api_calls flag
        $this->log = new Log('landing_api.log');

        $this->api_key = Settings::get_api_key();
        $this->api_host = Settings::get_api_host();

        $this->log->log('-------------  API HOST ------------- ');
        $this->log->log($this->api_host);

        $this->language = Multilanguage::instance();
    }

    /**
     * 
     *
     * @param array  $api_keys
     *
     * @return mixed
     */
    public function get_landing_info($hashid, $slug)
    {
            $this->log->log("Set landing params" );
            $this->log->log("Hashid: " . $hashid);
            $this->log->log("Slug: " . $slug);

            $endpoint = "/plugins/landing_new/" . $hashid . "/" . $slug;

            $this->log->log("to: " . $endpoint);

            return $this->sendRequest($endpoint);
    }

    /**
     * 
     *
     * @param string  $query
     *
     * @return array
     */
    public function get_custom_result($query)
    {
            // $endpoint = "/plugins/landing_new/" . $hashid . "/" . $slug;

            // $this->log->log("to: " . $endpoint);

            //NOW IS MOCK

            return ["14", "15", "17", "32", "33", "34"];

            // return $this->sendRequest($endpoint);
    }

    /**
     * Send a POST request with the given $body to the given $endpoint.
     *
     * @param string $endpoint The endpoint url.
     * @return array The request decoded response
     */
    private function sendRequest($endpoint)
    {
        $data = [
            'headers' => [
                'Authorization' => "Token {$this->api_key}",
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'method'      => 'GET',
            'timeout' => 20
        ];

        $url = "{$this->api_host}/{$endpoint}";
        $this->log->log("Making a request to: $url");
        $response = wp_remote_post($url, $data);

        if (is_wp_error($response)) {
            // Si se produce un error en la solicitud, devuelve información de error
            return ['error' => $response->get_error_message()];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);

        if (is_null($decoded_response)) {
            $error_message = 'There was a failure with the request to doomanager. [' . $response_code . ']. Try again later or contact support.' ;
            $this->log->log($error_message);
            return ['error' => $error_message];
        }

        $data = [
            'title' => $decoded_response['title'],
            'meta_title' => $decoded_response['meta_title'],
            'meta_description' => $decoded_response['meta_description'],
            'index' => $decoded_response['index'],
        ];

        if (is_array($decoded_response['blocks']) && count($decoded_response['blocks']) > 0) {
            $data['blocks'] = [];
            foreach ($decoded_response['blocks'] as $block) {
                $data['blocks'][] = [
                    'above' => base64_decode($block['above']),
                    'below' => base64_decode($block['below']),
                    'position' => $block['position'],
                    'query' => $block['query'],
                ];
            }
        }

        return ['data' => $data];
    }
}
