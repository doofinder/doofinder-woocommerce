<?php

use Doofinder\WP\Endpoints;
use Doofinder\WP\Helpers;
use Doofinder\WP\Log;
use Doofinder\WP\Multilanguage\Multilanguage;
use Doofinder\WP\Settings;

/**
 * Class Endpoint_Custom
 *
 * This class defines a method to update the current script to the single script version.
 */
class Endpoint_Single_Script {
    const CONTEXT  = "doofinder/v1";
    const ENDPOINT = "/single-script";

    /**
     * Initialize the custom single script endpoint.
     *
     * @return void
     */
    public static function initialize() {
        add_action( 'rest_api_init', function () {
            register_rest_route( self::CONTEXT, self::ENDPOINT, array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'update_script_to_single_script' ),
                'permission_callback' => '__return_true',
            ) );
        } );
    }

    /**
     * Replaces the current Doofinder script with the single one.
     *
     * @return string
     */
    public static function update_script_to_single_script() {
        Endpoints::CheckSecureToken();
        $log           = new Log();
        $multilanguage = Multilanguage::instance();

        // If there's no Multilanguage plugin active we still need to process 1 language.
        $languages = $multilanguage->get_formatted_languages();

        if ( ! $languages ) {
            $languages[''] = '';
        }

        $scripts = array();

        foreach ( $languages as $language_code => $language_name ) {
            /*
            Suffix for options.
            This should be empty for default language, and language code
            for any other.
            */
            // These ones will be used in the template
            $language        = ( $language_code === $multilanguage->get_base_locale() ) ? '' : Helpers::get_language_from_locale( $language_code );
            $installation_id = self::get_installation_id_from_database_script( $language );

            if ( empty( $installation_id ) ) {
                $log->log( "Single script could not be updated for language: $language because Installation ID could not be determined." );
                continue;
            }

            $region = Settings::get_region();
            
            ob_start();
            require DOOFINDER_PLUGIN_PATH . '/views/single-script.php';
            $single_script = ob_get_clean();

            $scripts[ $language ] = $single_script;

            Settings::set_js_layer( $single_script, $language );
        }

        return $scripts;
    }

    /**
     * Gets the installation ID from the script stored in the database.
     *
     * @return string
     */
    private static function get_installation_id_from_database_script( $language ) {
        $current_script = Settings::get_js_layer( $language );
        preg_match( "/installationId: '([a-z0-9-]+)'/", $current_script, $matches );
        if ( empty( $matches[1] ) ) {
            return '';
        }

        return $matches[1];
    }
}
