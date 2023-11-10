<?php

namespace Doofinder\WP\Api;

use Doofinder\WP\Log;
use Doofinder\WP\Multilanguage\Multilanguage;
use Doofinder\WP\Settings;

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

    const SEARCH_API_HOST = "search.doofinder.com/";

    public function __construct()
    {
        // Get global disable_api_calls flag
        $this->log = new Log('landing_api.log');

        $this->api_key = Settings::get_api_key();
        $this->api_host = Settings::get_api_host();
        $this->language = Multilanguage::instance();
    }

    /**
     * Retrieves landing page information from the API using the provided hashid and slug.
     *
     * @param string $hashid The hashid parameter
     * @param string $slug The slug parameter for the landing page.
     *
     * @return array An array containing landing page information or an error message.
     *
     * Example of 'data' array:
     *   [
     *     'title' => 'Landing Page Title',
     *     'meta_title' => 'Meta Title',
     *     'meta_description' => 'Meta Description',
     *     'index' => 'index_value',
     *     'blocks' => [
     *       [
     *         'above' => 'Above content for block',
     *         'below' => 'Below content for block',
     *         'position' => 'block_position',
     *         'query' => 'custom_query_for_block',
     *       ],
     *       // Additional blocks...
     *     ]
     *   ]
     */
    public function get_landing_info($hashid, $slug)
    {

        $endpoint = "/plugins/landing_new/{$hashid}/{$slug}";
        $data = [
            'headers' => [
                'Authorization' => "Token {$this->api_key}",
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'method'      => 'GET',
            'timeout' => 20
        ];

        $url = "{$this->api_host}/{$endpoint}";
        $decoded_response = $this->sendRequest($url, $data);

        if (isset($decoded_response['error']))
            return $decoded_response;

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

    /**
     * Retrieves custom search results from the Doofinder API based on the provided hashid and query.
     *
     * @param string $hashid The hashid parameter for the search engine.
     * @param string $query The custom query string for searching products.
     *
     * @return array An array containing custom search results or an error message.
     *
     */
    public function get_custom_result($hashid, $query)
    {
        $endpoint = "6/{$hashid}/_search?query={$query}";
        $data = [
            'headers' => [
                'Authorization' => "Token {$this->api_key}",
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'method'      => 'GET',
            'timeout' => 20,
        ];

        $zone = $this->get_zone();

        if (isset($zone['error']))
            return $zone;

        $url = "https://" . $zone . "-" . self::SEARCH_API_HOST . $endpoint;
        $this->log->log("Making a request to: $url");

        $decoded_response = $this->sendRequest($url, $data);

        return $decoded_response;
    }

    /**
     * Get the zone from the API host URL.
     *
     * This function extracts the zone from the given API host URL. The zone is part of the URL
     * and is used to determine the specific server zone for making requests to the Doofinder API.
     *
     * @return string|array Returns the zone if it's found in the URL. If the zone is not found or the URL is invalid, an array with an 'error' key is returned.
     */
    private function get_zone() {
        $api_host = $this->api_host;
        // Split the URL into parts using the '-' separator.
        $parts = explode('-', parse_url($api_host, PHP_URL_HOST));

        // Check if the second element is "admin".
        if (isset($parts[1]) && $parts[1] === 'admin.doofinder.com') {
            // The zone is in the first element.
            $zone = $parts[0];
            $this->log->log("Zone: $zone");
            return $zone;
        } else {
            $error_url = "Invaid zone in server. {$api_host}";
            $this->log->log($error_url);
            return ['error' => $error_url];
        }
    }

    /**
     * Sends an HTTP POST request to the specified URL with the provided data and handles the response.
     *
     * @param string $url The URL to which the request is sent.
     * @param array $data An array containing request parameters and headers.
     *
     * @return array An array containing the response data or an error message.
     *
     */
    private function sendRequest($url, $data)
    {
        $response = wp_remote_post($url, $data);

        if (is_wp_error($response)) {
            // If an error occurs in the request, returns error information.
            $error = $response->get_error_message();
            $this->log->log("Try request: " . $error);
            return ['error' => $error];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);

        if (is_null($decoded_response)) {
            $error_message = "There was a failure with the request. [{$response_code}] - {$response_body}. Try again later." ;
            $this->log->log("Error in response: [{$response_code}] - {$response_body} )");
            return ['error' => $error_message];
        }

        $this->log->log("We have obtained a valid answer");

        return $decoded_response;
    }
}
