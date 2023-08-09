<?php

namespace Doofinder\WP;

use Doofinder\WP\Api\Reset_Credentials_Api;
use Doofinder\WP\Helpers\Store_Helpers;
use Doofinder\WP\Multilanguage\Multilanguage;

class Reset_Credentials_Index
{
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

    public function __construct()
    {
        $this->language             = Multilanguage::instance();
        $this->current_language     = $this->language->get_current_language();
        $this->api                  = new Reset_Credentials_Api($this->current_language);
    }

    public function reset_credentials()
    {
        $payload = Store_Helpers::get_store_options();
        $this->api->resetCredentials($payload);
    }
}
