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

    public function __construct()
    {
        // Get global disable_api_calls flag
        $this->log = new Log('landing_api.log');

        $this->api_key = Settings::get_api_key();
        $this->api_host = Settings::get_api_host();
        $this->language = Multilanguage::instance();
    }

    /**
     * Send a POST request with the given $body to the given $endpoint.
     *
     * @param string $endpoint The endpoint url.
     * @return array The request decoded response
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
     * Send a POST request with the given $body to the given $endpoint.
     *
     * @param string $hashid The endpoint url.
     * @param string $query The endpoint url. 
     * @return array The request decoded response
     */
    public function get_custom_result($hashid, $query)
    {
        $endpoint = "/6/{$hashid}/_search?query={$query}";
        $data = [
            'headers' => [
                'Authorization' => "Token {$this->api_key}",
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'method'      => 'GET',
            'timeout' => 20,
        ];

        $api_host = $this->api_host;
        // Divide la URL en partes usando el separador '-'.
        $parts = explode('-', parse_url($api_host, PHP_URL_HOST));

        // Verifica si el segundo elemento es "admin".
        if (isset($parts[1]) && $parts[1] === 'admin.doofinder.com') {
            // La zona está en el primer elemento.
            $zone = $parts[0];
            $this->log->log("Zona: $zone");
        } else {
            $error_url = "URL no válida. {$api_host}";
            $this->log->log($error_url);
            return ['error' => $error_url];
        }

        $url = "https://{$zone}-search.doofinder.com{$endpoint}";
        $this->log->log("Making a request to: $url");

        $decoded_response = $this->sendRequest($url, $data);

        return $decoded_response;
    }

    /**
     * Send a POST request with the given $body to the given $endpoint.
     *
     * @param string $url The endpoint url.
     * @param array $data The endpoint url.
     * @return array The request decoded response
     */
    private function sendRequest($url, $data)
    {
        $response = wp_remote_post($url, $data);

        if (is_wp_error($response)) {
            // Si se produce un error en la solicitud, devuelve información de error
            $error = $response->get_error_message();
            $this->log->log("Try request: " . $error);
            return ['error' => $error];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);

        if (is_null($decoded_response)) {
            $error_message = "There was a failure with the request. [{$response_code}] - {$response_body}. Try again later or contact support." ;
            $this->log->log("Error in response: [{$response_code}] - {$response_body} )");
            return ['error' => $error_message];
        }

        $this->log->log("We have obtained a valid answer");

        return $decoded_response;
    }
}
