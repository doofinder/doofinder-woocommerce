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

defined('ABSPATH') or die();

class Store_Api
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
        $this->log = new Log('stores-api.txt');

        $this->api_key = Settings::get_api_key();
        $this->api_host = Settings::get_api_host();

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
            $store_payload = $this->build_store_payload($api_keys);
            $this->log->log("store_data: ");
            $this->log->log($store_payload);
            return $this->sendRequest("plugins/create-store", $store_payload);
        }
    }

    /**
     * Sends a request to update the store options with the api password and to create any missing datatype
     * Payload example:
     * $payload = array(
     *    'store_options' => array(
     *        'url' => 'http://pedro-wordpress.ngrok.doofinder.com',
     *        'api_pass' => 'G41cXNeVoX4JGL2bhvbcMlQ4',
     *        'api_user' => 'pedro'
     *    ),
     *    'search_engines' => array(
     *        'fde92a8f364b8d769262974e95d82dba' => array(
     *          'feed_type' => 'post',
     *          'url' => 'http://pedro-wordpress.ngrok.doofinder.com'
     *        )
     *    )
     * )
     * @return void
     */
    public function normalize_store_and_indices()
    {
        $wizard = Setup_Wizard::instance();
        $api_keys = Setup_Wizard::are_api_keys_present($wizard->process_all_languages, $wizard->language);

        if (!Multilanguage::$is_multilang) {
            $api_keys = [
                '' => [
                    'hash' => Settings::get_search_engine_hash()
                ]
            ];
        }

        $store_payload = $this->build_store_payload($api_keys);

        $payload = [
            'store_options' => $store_payload['options']
        ];

        foreach ($store_payload['search_engines'] as $search_engine) {
            $lang = Helpers::get_language_from_locale($search_engine['language']);

            //If the installation is not multilanguage, replace the lang with ''
            if (is_a($this->language, No_Language_Plugin::class) || $lang === $this->language->get_base_language()) {
                $lang = '';
            }

            $se_hashid = Settings::get_search_engine_hash($lang);
            $payload['search_engines'][$se_hashid] = $search_engine['datatypes'][0]['datasources'][0]['options'];
        }

        $this->log->log("Sending request to normalize indices.");
        $response = $this->sendRequest("plugins/wordpress/normalize-indices/", $payload);
        if (array_key_exists('errors', $response)) {
            $this->log->log("The store and indices normalization has failed!");
            $this->log->log(print_r($response['errors'], true));
        } else {
            $this->log->log("The store and indices normalization has finished succesfully!");
            $this->log->log("Response: \n" . print_r($response, true));
        }
    }

    /**
     * This method checks if there is an application password set.
     *
     * @return boolean
     */
    public static function has_application_credentials()
    {
        return WP_Application_Passwords::application_name_exists_for_user(get_current_user_id(), 'doofinder');
    }

    /**
     * Send a POST request with the given $body to the given $endpoint.
     *
     * @param string $endpoint The endpoint url.
     * @param array $body The array containing the payload to be sent.
     * @return array The request decoded response
     */
    private function sendRequest($endpoint, $body)
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

        $url = "{$this->api_host}/{$endpoint}";
        $this->log->log("Making a request to: $url");
        $response = wp_remote_post($url, $data);
        $response_code = wp_remote_retrieve_response_code($response);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            throw new Exception($error_message, (int)$response->get_error_code());
        }

        if ($response_code < 200 || $response_code >= 400) {
            $error_message = wp_remote_retrieve_response_message($response);
            throw new Exception($error_message, $response_code);
        }

        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);
        return $decoded_response;
    }

    /**
     * Generates the create-store payload
     *
     * @param array $api_keys The list of search engine ids
     * @return void
     */
    private function build_store_payload($api_keys)
    {
        $primary_language = $this->get_primary_language();

        $store_payload = [
            "name" =>  get_bloginfo('name'),
            "platform" =>  is_plugin_active('woocommerce/woocommerce.php') ? "woocommerce" : "wordpress",
            "primary_language" => $primary_language,
            "search_engines" => [],
            "sector" => Settings::get_sector(),
            "callback_urls" => $this->get_callback_urls($api_keys, $primary_language),
            "options" => Store_Helpers::get_store_options(),
            "search_engines" => $this->build_search_engines($api_keys, $primary_language)
        ];
        return $store_payload;
    }

    private function build_search_engines($api_keys, $primary_language)
    {
        $search_engines = [];
        $domain = str_ireplace('www.', '', parse_url(get_bloginfo('url'), PHP_URL_HOST));
        $currency = is_plugin_active('woocommerce/woocommerce.php') ? get_woocommerce_currency() : "EUR";

        foreach ($api_keys as $item) {
            //Prioritize the locale code
            $code = $item['lang']['locale'] ?? $item['lang']['code'] ?? $primary_language;
            $code = Helpers::format_locale_to_hyphen($code);
            $lang = Helpers::get_language_from_locale($code);

            // Prepare search engine body
            $this->log->log('Wizard Step 2 - Prepare Search Enginge body : ');
            $search_engines[] = [
                'name' => $domain . ($code ? ' (' . strtoupper($code) . ')' : ''),
                'language' => $code,
                'currency' => $currency,
                'site_url' => $this->language->get_home_url($lang),
                'datatypes' => [
                    $this->get_datatype($lang)
                ]
            ];
        }

        return $search_engines;
    }

    /**
     * This function returns the primary language in locale format: en-US,
     * es-ES, etc.
     *
     * @return string Primary language.
     */
    private function get_primary_language()
    {
        $primary_language = get_locale();
        if ($this->language->get_languages() != null) {
            $primary_language = $this->language->get_base_locale();
        }
        $primary_language = Helpers::format_locale_to_hyphen($primary_language);
        return $primary_language;
    }

    private function get_datatype($language)
    {
        return is_plugin_active('woocommerce/woocommerce.php') ?
            $this->get_product_datatype($language) :
            $this->get_post_datatype($language);
    }

    /**
     * Generates the product datatype structure.
     *
     * @return array The product datatype structure.
     */
    private function get_product_datatype($language)
    {
        return [
            "name" => "product",
            "preset" => "product",
            "datasources" => [
                [
                    "type" => "wordpress",
                    "options" => [
                        "feed_type" => "product",
                        "url" => $this->language->get_home_url($language)
                    ]
                ]
            ]
        ];
    }

    /**
     * Generates the post datatype structure.
     *
     * @return array The post datatype structure.
     */
    private function get_post_datatype($language)
    {
        return [
            "name" => "post",
            "preset" => "generic",
            "datasources" => [
                [
                    "type" => "wordpress",
                    "options" => [
                        "feed_type" => "post",
                        "url" =>  $this->language->get_home_url($language)
                    ]
                ]
            ]
        ];
    }

    private function get_callback_urls($api_keys, $primary_language)
    {
        $callback_urls = [];
        $currency = 'EUR';
        foreach ($api_keys as $item) {
            $code = $item['lang']['locale'] ?? $item['lang']['code'] ?? $primary_language;
            $lang = Helpers::get_language_from_locale($code);
            $code = Helpers::format_locale_to_hyphen($code);
            $callback_urls[$code][$currency] = $this->build_callback_url(
                $this->language->get_home_url($lang),
                '/wp-json/doofinder/v1/index-status/?token=' . $this->api_key
            );
        }
        return $callback_urls;
    }

    /**
     * This method takes the base url and adds
     *
     * @param [type] $base_url
     * @param [type] $endpoint_path
     * @return void
     */
    private function build_callback_url($base_url, $endpoint_path)
    {
        $parsed_url = parse_url($base_url);
        $parameters = null;
        if (array_key_exists('query', $parsed_url)) {
            parse_str($parsed_url['query'], $parameters);
        }

        $callback_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
        $callback_url .= isset($parsed_url['path']) ? rtrim($parsed_url['path'], '/') : '';
        $callback_url .= '/' . ltrim($endpoint_path, '/');

        // Combine any existing parameters with any possible endopoint path parameters
        if (!empty($parameters)) {
            parse_str(parse_url($callback_url, PHP_URL_QUERY), $endpoint_parameters);
            $combined_parameters = array_merge($parameters, $endpoint_parameters);
            $callback_url = strtok($callback_url, '?');
            $callback_url .= '?' . http_build_query($combined_parameters);
        }

        return $callback_url;
    }

}
