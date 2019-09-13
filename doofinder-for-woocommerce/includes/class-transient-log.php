<?php

namespace Doofinder\WC;

defined( 'ABSPATH' ) or die;

class Transient_Log {

    /**
     * A singleton instance of the log.
     *
     * @var self
     */
    private static $_instance;

    /**
     * After this much time the log will expire.
     *
     * @var int
     */
    private static $duration = HOUR_IN_SECONDS;

    /**
     * We'll store all the information we want to log here. These will be then
     * passed to the transient.
     *
     * @var array
     */
    private $messages = array();

    /**
     * @return self
     */
    public static function instance() {
        if ( ! isset( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public static function get_log() {
        return get_transient( 'doofinder_log' );
    }

    public function log( $value ) {
        if ( ! $this->can_we_log() ) {
            return;
        }

        $this->messages[] = $value;
    }

    public function save() {
        if ( ! $this->can_we_log() ) {
            return;
        }

        set_transient( 'doofinder_log', $this->messages, self::$duration );
    }

    private function can_we_log() {
        return is_user_logged_in() && is_search();
    }
}
