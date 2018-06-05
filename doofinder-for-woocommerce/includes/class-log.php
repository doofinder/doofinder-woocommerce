<?php

namespace Doofinder\WC;

class Log {

	/**
	 * Name of the file we'll be logging data into.
	 *
	 * @var string
	 */
	private $file_name = 'log.txt';

	/**
	 * Maximum allowed size of the log file.
	 *
	 * Files larger than this will be cleared in order to make sure
	 * we don't take up too much hard drive space.
	 *
	 * @var int
	 */
	private $max_size = 1048576;

	public function __construct( $file_name = null ) {
		if ( $file_name ) {
			$this->file_name = $file_name;
		}
	}

	/**
	 * Add a given value in the log file.
	 *
	 * To make sure the file size does not grow out of control this funciton
	 * checks if the file is below 1mb, and clears it if it exceeds that.
	 *
	 * @param mixed $value
	 */
	public function log( $value ) {
		// Check if logs directory exits. Create it if it doesn't.
		if ( ! is_dir( Doofinder_For_WooCommerce::plugin_path() . 'logs' ) ) {
			mkdir( Doofinder_For_WooCommerce::plugin_path() . 'logs' );
		}

		$log_file = Doofinder_For_WooCommerce::plugin_path() . 'logs/' . $this->file_name;

		// We don't want the log file to grow to large, so clear it
		// if it takes up more than 1 mb of disk space.
		if ( is_file( $log_file ) && filesize( $log_file ) > $this->max_size ) {
			$file_handler = fopen( $log_file, 'w' );
			fclose( $file_handler );
		}

		// Line break before entries.
		$to_log = "\n";

		// Current time.
		$to_log .= date( 'j M y, H:i' ) . "\n";

		// We want to store value in the log file in a nice format.
		// print_r formats complex types nicely, but fails when trying
		// to print out simple values.
		if ( is_array( $value ) || is_object( $value ) ) {
			$to_log .= print_r( $value, true );
		} else {
			$to_log .= var_export( $value, true ) . "\n";
		}

		// Append the things we fake send to API to the log file.
		file_put_contents(
			$log_file,
			$to_log,
			FILE_APPEND
		);
	}
}
