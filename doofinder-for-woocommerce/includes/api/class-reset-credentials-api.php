<?php

namespace Doofinder\WP\Api;

use Doofinder\WP\Settings;
use Doofinder\WP\Log;

/**
 * Handles requests to the Management API.
 */
class Reset_Credentials_Api
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
        $this->hash                 = Settings::get_search_engine_hash($language);
        $this->authorization_header = array(
            'Authorization' => "Token $this->api_key",
            'content-type'  => 'application/json'
        );

        $this->log->log('Create Management API Client');
        $this->log->log('API Key: ' . $this->api_key);
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
    /**
     * Send a POST request with the given $body to the given $endpoint.
     *
     * @param array $body The array containing the payload to be sent.
     * @return array The request decoded response
     */
    private function sendRequest($body)
    {
        $data = [
            'headers' => [
                'Authorization' => "Token {$this->api_key}",
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'body' => json_encode($body),
            'method'      => 'POST',
            'data_format' => 'body',
            'timeout' => 20
        ];

        $response = wp_remote_request($url, $data);
        if (!is_wp_error($response)) {
            $response_body = wp_remote_retrieve_body($response);
            $decoded_response = json_decode($response_body, true);
            $this->log->log("The request has been made correctly: $decoded_response");
        } else {
            $error_message = $response->get_error_message();
            $this->log->log("Error in the request: $error_message");
        }
    }

    public function buildURL($path)
    {
        return "{$this->api_host}/{$path}";
    }

    /**
     * @since 1.0.0
     */
    public function resetCredentials($data)
    {
        $this->log->log('Reset Credentials');

        $uri = $this->buildURL("plugins/wordpress/" . $this->hash . "/reset-credentials");
        $this->log->log("Making a request to: $uri");

        return $this->sendRequest($uri, $data);
    }

}
