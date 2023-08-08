<?php

namespace Doofinder\WP;

use Doofinder\WP\Api\Reset_Credentials_Api;
use Doofinder\WP\Helpers\Store_Helpers;
use Doofinder\WP\Log;
use Doofinder\WP\Multilanguage\Multilanguage;

class Reset_Credentials_Index
{
    /**
     * Instance of a class used to log to a file.
     *
     * @var Store_Helpers
     */
    private $store_helper;

    /**
     * Instance of class handling multilanguage environments.
     *
     * @var Language_Plugin
     */
    private $language;

    /**
     * Language selected for this operation.
     *
     * @var string
     */
    private $current_language;

    /**
     * Class handling API calls.
     *
     * @var Reset_Credentials_Api
     */
    private $api;

    /**
     * Instance of a class used to log to a file.
     *
     * @var Log
     */
    private $log;


    public function __construct()
    {
        $this->language             = Multilanguage::instance();
        $this->current_language     = $this->language->get_current_language();
        $this->api                  = new Reset_Credentials_Api($this->current_language);
        $this->log                  = new Log('reset_credentials.txt');
        $this->store_helper         = new Store_Helpers;
    }

    public function reset_credentials()
    {
        $payload = $this->store_helper->get_store_options();
        $this->api->resetCredentials($payload);
    }
}
