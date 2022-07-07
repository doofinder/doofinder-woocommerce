<?php

namespace Doofinder\WC\Api;


use Doofinder\GuzzleHttp\Client as GuzzleClient;
use Doofinder\WC\Doofinder_For_WooCommerce;
use Doofinder\WC\Log;
use Doofinder\WC\Multilanguage;
use Doofinder\WC\Settings\Settings;
use Exception;

defined('ABSPATH') or die();

class Store_Api
{

    private $log;
    private $language;
    private $process_all_languages = true;

    public function __construct()
    {
        // Get global disable_api_calls flag
        $this->disable_api = Doofinder_For_WooCommerce::$disable_api_calls ?? $this->disable_api;

        $this->log = new Log('stores-api.txt');

        if ($this->disable_api) {
            $this->log->log('-------------  API IS DISABLED ------------- ');
        }

        $this->api_key = Settings::get_api_key();
        $this->admin_host = Settings::get_admin_endpoint(); //This api

        $this->log->log('-------------  API HOST ------------- ');
        $this->log->log($this->api_host);

        $this->language = Multilanguage::instance();
    }

    /**
     * Create a Store, Search Engine and Datatype
     *
     * @param array  $api_keys
     *
     * @return mixed
     */
    public function create_store($api_keys)
    {
        if (is_array($api_keys)) {
            $primary_language = explode("_", get_locale())[0];
            if ($this->language->is_active()) {
                $primary_language = $this->language->get_base_language();
            }

            $domain = str_ireplace('www.', '', parse_url(get_bloginfo('url'), PHP_URL_HOST));

            $store_data = [
                "name" =>  get_bloginfo('name'),
                "platform" => "woocommerce",
                "primary_language" => $primary_language,
                "skip_indexation" => true,
                "search_engines" => [],
                "sector" => Settings::get_sector()
            ];

            foreach ($api_keys as $item) {
                if ($item['hash'] === 'no-hash') {
                    $code = $item['lang']['code'] ?? $primary_language;
                    // Prepare search engine body
                    $this->log->log('Wizard Step 2 - Prepare Search Enginge body : ');
                    $store_data["search_engines"][] = [
                        'name' => $domain . ($code ? ' (' . strtoupper($code) . ')' : ''),
                        'language' => $code,
                        'currency' => get_woocommerce_currency(),
                        'site_url' => get_bloginfo('url'),
                        'datatypes' => [
                            [
                                "name" => "product",
                                "preset" => "product"
                            ]
                        ]
                    ];
                }
            }

            $this->log->log("store_data: ");
            $this->log->log($store_data);

            $response = $this->sendRequest("POST", "plugins/create-store", $store_data);
            return $this->process_response($response);
        }

        throw new Exception("API keys must be an array");
    }

    public function sendRequest($method, $endpoint, $body)
    {
        $client = new GuzzleClient();
        $uri = "{$this->admin_host}/{$endpoint}";
        $options = [
            'headers' => [
                'Authorization' => "Token {$this->api_key}"
            ],
            'json' => $body
        ];
        return $client->request($method, $uri, $options);
    }

    public function process_response($response)
    {
        $body = $response->getBody()->getContents();
        if ($response->getStatusCode() > 199 && $response->getStatusCode() <= 299) {
            return json_decode($body);
        } else {
            throw new Exception("Error #{$response->getStatusCode()} creating store structure. $body", $response->getStatusCode());
        }
    }
}
