<?php

namespace Doofinder\WC;

use Doofinder\WC\Log;

class Profiler
{

	/**
	 * Name of the file we'll be logging data into.
	 *
	 * @var string
	 */
	private $log_file_name = 'profiler.txt';

	/**
	 * Execution time start timestamp in microseconds
	 * 
	 * @var float[];
	 */
	private $time_start = [];

	/**
	 * Execution time end timestamp in microseconds
	 * 
	 * @var float[];
	 */
	private $time_end = [];

	/**
	 * Execution time in seconds
	 * 
	 * @var float[];
	 */
	private $time_diff = [];


	/**
	 * Instance of a class used to log to a file.
	 *
	 * @var Log
	 */
	private $log;

	/**
	 * Rounding precision
	 * 
	 * @var int
	 */
	private static $precision = 5;


	public function __construct($log_file_name = null)
	{
		if ($log_file_name) {
			$this->log_file_name = $log_file_name;
		}

		$this->log = new Log($this->log_file_name, true);
	}

	/**
	 * Start counting microtime 
	 */
	public function start()
	{
		$this->time_start[] = microtime(true);
	}

	/**
	 * Count execution time and log data
	 * 
	 */
	public function end($prefix = '', $content = '', $server_time = false, $pop_only = false)
	{
		if ($pop_only) {
			array_pop($this->time_start);
			return;
		}

		$this->time_end[] = microtime(true);

		if ($prefix || $content) {
			$time_started = $server_time ? $_SERVER["REQUEST_TIME_FLOAT"] : array_pop($this->time_start);
			$time_diff = number_format(round(array_pop($this->time_end) - $time_started, self::$precision), self::$precision);
			// Log data
			$this->log->log('Profiler -   ' . $prefix . ' - ' . $content . ' in [' . $time_diff . '] seconds');
		}
	}

	/**
	 * Log any information
	 */
	public function log($content)
	{
		$this->log->log($content);
	}

	/**
	 * Log any information with time info
	 */
	public function log_time($content, $server_time = false, $duration = false)
	{
		$time = $server_time ? $_SERVER["REQUEST_TIME_FLOAT"] : microtime(true);
		$time_arr = explode('.', $time);
		$microseconds = $time_arr[1];

		$log_value = 'Profiler - ' . $content . ' at [' . date('Y-m-d H:i:s', $time) . '.' . $microseconds . ']';

		if ($duration) {
			$time_start = array_pop($this->time_start);
			$time_end = array_pop($this->time_end);
			$time_diff = number_format(round($time_end - $time_start, self::$precision), self::$precision);
			$log_value .= ' and lasted [' . $time_diff . '] seconds';
		}

		if ($log_value) {
			$this->log->log($log_value);
		}
	}
}