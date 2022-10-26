<?php

namespace Doofinder\WC;

use Doofinder\WC\Api\Api_Factory;
use Doofinder\WC\Api\Api_Wrapper;
use Doofinder\WC\Api\Api_Status;
use Doofinder\WC\Multilanguage\Language_Plugin;
use Doofinder\WC\Multilanguage\Multilanguage;
use Doofinder\WC\Settings\Settings;
use Doofinder\WC\Data_Feed;
use Doofinder\WC\Helpers\Helpers;
use Doofinder\WC\Log;

defined('ABSPATH') or die;

/**
 * Handles the process of indexing the posts.
 *
 * Posts are indexed in batches - post type after post type, so each call
 * to index the posts will index only one batch, and move the "pointer"
 * (data about what has already been indexed, which is stored in DB).
 *
 * Core of the class functionality are methods "ajax_handler" and "index_posts"
 * which are responsible for indexing one batch of posts, rest are helpers
 * for building SQL queries to retrieve the data, etc.
 *
 * This class does not print any interface, just handles retrieving
 * posts from DB and sending them to Doofinder API.
 *
 * @see Data_Index::ajax_handler()
 * @see Data_Index::index_posts()
 * @see Index_Interface
 * @see Indexing_Data
 */
class Data_Index
{

	/**
	 * Number of posts per page.
	 *
	 * @var int
	 */
	private static $posts_per_page = 25; // Max is 100.

	/**
	 * Instance of class handling multilanguage environments.
	 *
	 * @var Language_Plugin
	 */
	private $language;

	/**
	 * Data containing progress of indexing.
	 *
	 * @var Indexing_Data
	 */
	private $indexing_data;

	/**
	 * Class handling API calls.
	 *
	 * @var Api_Wrapper
	 */
	private $api;

	/**
	 * Instance of a class used to log to a file.
	 *
	 * @var Log
	 */
	private $log;

	/**
	 * List of posts prepared to be indexed.
	 *
	 * @var array[]
	 */
	private $items = array();

	/**
	 * List of posts ids fetched from DB.
	 *
	 * @var array
	 */
	private $posts_ids;

	/**
	 * List of posts objects fetched from DB.
	 *
	 * @var \WP_Post[]
	 */
	private $posts;

	/**
	 * Number of posts fetched from DB.
	 *
	 * @var int
	 */
	private $post_count = 0;

	/**
	 * Posts meta.
	 *
	 * @var array
	 */
	private $posts_meta;

	/**
	 * List with all post types.
	 *
	 * @var array
	 */
	private $post_types;

	/**
	 * List with all languages
	 *
	 * @var array
	 */
	private $languages;

	/**
	 * Current Language selected. Empty string '' if selected all.
	 *
	 * @var array
	 */
	private $current_language;

	/**
	 * Contains information whether we should process all languages at once
	 *
	 * @var bool
	 */
	private $process_all_languages = false;

	/**
	 * Should processing data fail (used for testing)
	 *
	 * @var bool
	 */
	public static $should_fail = false;


	public function __construct()
	{
		$this->language      			= Multilanguage::instance();
		$this->indexing_data 			= Indexing_Data::instance();
		$this->current_language   		= $_POST['lang'] ?? '';
		$this->process_all_languages 	= $this->language->get_languages() && $this->current_language === '';

		$this->log 			 			= new Log('api.txt');

		$this->log->log('-------------------------------------------------------------------------------------------------------------------------------------');
		$this->log->log('-------------------------------------------------------------------------------------------------------------------------------------');
		$this->log->log('-------------------------------------------------------------------------------------------------------------------------------------');
		//$this->log->log( '---------------------------------------------------------------------------------------------' );
		//$this->log->log( 'Data Index _construct ' );

	}

	/**
	 *
	 * Handle ajax request
	 *
	 * Index active language if $language param is not present.
	 *
	 * @param string $language Language code of posts to index.
	 */
	public function ajax_handler()
	{

		//$this->log->log( 'Ajax Handler ' );

		$status = $this->indexing_data->get('status');

		// If the indexing has been completed we are reindexing.
		// Reset the status of indexing.
		if ($status === 'completed') {
			$this->log->log('Ajax Handler - Reset indexing data ');
			$this->indexing_data->reset();
		}


		// Index the posts.
		if ($this->index_posts()) {

			$this->ajax_response(true, 'Wrapping up...');

			return;
		}

		$post_type = $this->get_post_type_name($this->indexing_data->get('post_type'));
		$lang_message = '';

		if ($this->process_all_languages) {
			$language = $this->get_language_name($this->indexing_data->get('lang'));
			$lang_message = ", for \"$language\" language";
		}


		$this->ajax_response(false, "Indexing \"$post_type\" type contents" . $lang_message . "...");
	}

	/**
	 * Get posts from DB, send via API, and return status
	 * (if the process of indexing has been completed) as JSON.
	 *
	 * @since 1.0.0
	 * @param string $language Language code of posts to index.
	 * @return bool True if the indexing has finished.
	 */
	public function index_posts($language = null)
	{

		if (self::$should_fail) {
			$this->ajax_response_error(array(
				'status'  => Api_Status::$unknown_error,
				'message' => 'Some text for forced error',
				'error'   => true
			));

			return;
		}

		// TODO Maybe make it work with different post types enabled, not only with products

		//$this->log->log( 'Index Posts -----------------------------' );

		// Change indexing status to 'processing'
		$this->set_processing_status();

		// This is not needed anymore, in API v2 we utilize temp indexes
		//$this->maybe_remove_posts();



		// Load languages
		$this->log->log('Load Languages');
		$this->load_languages();
		$this->log->log($this->languages);


		// Load the data that we'll use to fetch posts.
		$this->log->log('Load Post Types');
		$this->load_post_types();
		$this->log->log($this->post_types);


		// Count have many posts there are to process
		$this->calculate_progress(true);


		// This function also removes the current post type.
		// This is done because "load_posts_id" can skip a post type
		// if it contains 0 posts, but we still need to remove
		// the post type, or the old posts will remain in the DB.
		$this->log->log('Load Post IDs');
		$this->load_posts_ids($language);
		$this->log->log('Post IDs : ');
		$this->log->log($this->posts_ids);


		// Get API client instance (for current language)
		$language = $this->indexing_data->get('lang');
		$this->log->log('Index Posts  - lang: ' . $language);
		$this->log->log('Index Posts  - Get API Client Instance');
		$this->api = Api_Factory::get($language, true);

		// We fetch next batch of post IDs, from the current post type,
		// but advance to the next post type, if there are no more posts.
		// If we hit 0 posts at this point that means we checked current
		// post type, advanced to the next one, there was none, which
		// means we are done.
		if ($this->post_count === 0) {

			// We are done so replace real index with temp one.
			// If replacing is not successfull will send api error response.
			$this->log->log('Index Posts  - Call replace Index');
			$this->call_replace_index();

			// Set indexing status if above is successfull
			$this->indexing_data->set('status', 'completed');

			return true;
		}

		// Load actual posts and their data.
		// For now we only index product so this is not needed.

		//$this->load_posts();
		//$this->load_posts_meta();

		// Prepare posts to be sent to the API.
		//$this->log->log( 'Generate Items - Start' );
		$this->generate_items();
		//$this->log->log( 'Generate Items - End' );
		//$this->log->log( $this->items );


		// Send posts to the API.
		// At this point if the posts are sent successfully
		// we'll advance the "pointer" pointing to the last
		// indexed post.

		if ($this->items) {

			$sent_to_api = $this->api->send_batch(
				$this->indexing_data->get('post_type'),
				$this->items,
				$this->indexing_data->get('lang')
			);

			// Show ajax response from api response body
			if (is_array($sent_to_api)) {

				$this->ajax_response_error(array(
					'status'  => $sent_to_api['status'] ?? '',
					'message' => $sent_to_api['message'] ?? '',
					'error'   => isset($sent_to_api['error']) ? $sent_to_api['error'] : true
				));
			} else if ($sent_to_api !== Api_Status::$success) {
				$post_type = $this->get_post_type_name($this->indexing_data->get('post_type'));

				$lang_message = '';

				if ($this->process_all_languages) {
					$language_code = $this->get_language_name($this->indexing_data->get('lang'));
					$lang_message = " for \"$language_code\" language";
				}

				// Setting error flag to true will cause indexing to stop without additional retries
				// For example when api key or hash id is invalid we do not need to retry the request
				$error = false;

				$message = __("Indexing \"$post_type\" type contents" . $lang_message . "...", 'woocommerce-doofinder');

				if ($sent_to_api === Api_Status::$indexing_in_progress) {
					$message = __("Processing \"$post_type\" index" . $lang_message . "...", 'woocommerce-doofinder');
				}

				if ($sent_to_api === Api_Status::$invalid_search_engine) {
					$error = true;
					$message = __("Invalid search engine. Please check hash id" . $lang_message, 'woocommerce-doofinder');
				}

				if ($sent_to_api === Api_Status::$not_authenticated) {
					$error = true;
					$message = __("Request not authenticated. Please check API key" . $lang_message, 'woocommerce-doofinder');
				}

				if ($sent_to_api === Api_Status::$unknown_error) {
					$error = true;
					$message = __("An unknown error has occured while indexing data", 'woocommerce-doofinder');
				}

				$this->ajax_response_error(array(
					'status'  => $sent_to_api,
					'message' => $message,
					'error'   => $error
				));
			}
		}

		// We land here in two cases:
		// 1. We had items and sent them to API successfully. If API
		//    call fails the script will terminate.
		// 2. There were no items. This happens when for example we hit
		//    a batch of posts that all have settings preventing them
		//    from being indexed.
		$this->log->log('Push Pointer Forwards');
		$this->push_pointer_forwards();

		return false;
	}

	/**
	 * Remove all post types, but only once, at the beginning of indexing process.
	 */
	private function maybe_remove_posts()
	{
		if ($this->indexing_data->get('status') === 'processing') {
			return;
		}

		$types_removed = $this->api->remove_types();

		$this->indexing_data->set('status', 'processing');

		if ($types_removed !== Api_Status::$success) {
			$this->ajax_response_error(array(
				'status'  => $types_removed,
				'message' => __('Deleting objects...', 'woocommerce-doofinder'),
			));
		}

		$this->indexing_data->set('post_types_removed', Settings::get_post_types_to_index());
	}

	/**
	 * Set status to prcessing at the beginning of indexing process.
	 */
	private function set_processing_status()
	{
		if ($this->indexing_data->get('status') === 'processing') {
			return;
		}

		$this->indexing_data->set('status', 'processing');
	}

	/**
	 * Load post types from DB, and set current post type if is not defined.
	 *
	 * @since 1.0.0
	 */
	private function load_post_types()
	{
		$post_types       = Post_Types::instance();
		$this->post_types = $post_types->get_indexable();

		// if we start indexing, then post type is not set, so we get first post type from list
		if (!$this->indexing_data->get('post_type')) {
			$this->indexing_data->set('post_type', $this->post_types[0]);
		}
	}

	/**
	 * Load languages from DB, and set current language if is not defined.
	 *
	 * @since 1.0.0
	 */
	private function load_languages()
	{

		if (!$this->process_all_languages) {

			if (!$this->indexing_data->get('lang')) {
				$this->indexing_data->set('lang', $this->current_language);
			}

			return;
		}

		$languages = $this->language->get_languages();

		if (is_array($languages)) {
			$this->languages = array_keys($languages);
		} else {
			$this->languages = [''];
		}

		// if we start indexing, then language is not set, so we get first language from list
		if (!$this->indexing_data->get('lang')) {
			$this->indexing_data->set('lang', $this->languages[0]);
		}
	}

	/**
	 * Load posts ids from DB.
	 *
	 * @since 1.0.0
	 */
	private function load_posts_ids($language = null)
	{
		$last_id        = $this->indexing_data->get('post_id');
		$post_type      = $this->indexing_data->get('post_type');

		$lang 		    = $this->indexing_data->get('lang');

		$this->log->log('Load Posts Ids - lang : ' . $lang);
		$this->log->log('Load Posts Ids - post_type : ' . $post_type);

		$posts_per_page = self::$posts_per_page;

		if ($language) {
			$lang_code = $language;
		} else {
			$lang_code = $lang;
		}


		$this->posts_ids = $this->language->get_posts_ids(
			$lang_code,
			$post_type,
			$last_id,
			$posts_per_page
		);

		$this->post_count = count($this->posts_ids);

		// Get API Class instance so we can check if search engine is valid
		// If not then we skip to next type / language
		//$api = Api_Factory::get();

		if ($this->post_count === 0 /* || !$api->search_engine  */) {

			// If search engine is invalid we don't want to call the request for replacing index
			// $skip_replace_index = !$api->search_engine ? true : false;

			// if ( $this->check_next_post_type() ) {
			// 	$this->log->log('Load Posts Ids - check next post type - true ');
			// 	$this->load_posts_ids();
			// } else
			if ($this->process_all_languages && $this->check_next_language(/* $skip_replace_index */)) {
				$this->log->log('Load Posts Ids - check next language - true ');
				$this->load_posts_ids();
			}
		}
	}

	/**
	 * Load posts from DB.
	 *
	 * @since 1.0.0
	 */
	private function load_posts()
	{
		// Whilst the default WP_Query post_status is "publish",
		// attachments have a default post_status of "inherit".
		// This means no attachments will be returned unless we
		// also explicitly set post_status to "inherit" or "any".
		if ($this->indexing_data->get('post_type') === 'attachment') {
			$post_status = 'inherit';
		} else {
			$post_status = 'publish';
		}

		$args = array(
			'post_type'   => $this->indexing_data->get('post_type'),
			'post__in'    => $this->posts_ids,
			'post_status' => $post_status,

			'posts_per_page' => self::$posts_per_page,
			'orderby'        => 'ID',
			'order'          => 'ASC',

			'cache_results'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$query = new \WP_Query($args);

		$this->posts = $query->posts;
	}

	/**
	 * Load posts meta from DB.
	 *
	 * @since 1.0.0
	 */
	private function load_posts_meta()
	{
		global $wpdb;
		$posts_ids_list = implode(', ', $this->posts_ids);

		$visibility_meta  = Post::$options['visibility']['meta_name'];
		$yoast_visibility = Post::$options['yoast_visibility']['meta_name'];
		$query            = "
			SELECT post_id, meta_key, meta_value
			FROM $wpdb->postmeta
			WHERE $wpdb->postmeta.post_id IN ($posts_ids_list)
			AND (
              $wpdb->postmeta.meta_key NOT LIKE '\_%' OR
              $wpdb->postmeta.meta_key = '$visibility_meta' OR
              $wpdb->postmeta.meta_key = '$yoast_visibility'
            )
			ORDER BY $wpdb->postmeta.post_id
		 ";

		$this->posts_meta = $wpdb->get_results($query, OBJECT);
	}

	/**
	 * Generate items to be indexed via API.
	 *
	 * @since 1.0.0
	 */
	private function generate_items()
	{

		// Use functionality from XML data feed to retrieve products
		$this->log->log('Generate items - Start ');
		$this->log->log('Current Memory Usage: ' . Helpers::get_memory_usage());
		//$this->log->log( 'Generate items - Start :  ' . $this->indexing_data->get('lang'));

		$this->log->log('Generate items - Get Data Feed Instance ');
		$data_feed = new Data_Feed(false, $this->posts_ids, $this->indexing_data->get('lang'));

		$this->log->log('Generate items - Get Items ');
		$this->items = $data_feed->get_items();

		$this->log->log('Generate items - Set Current Progress ');
		$current_progress = $this->indexing_data->get('current_progress');
		$this->indexing_data->set('current_progress', $current_progress + count($this->items));

		if (!empty($this->items)) {
			$this->log->log('Generate items - Items generated : ' . count($this->items));
			$this->log->log('Generate items - Items : ');
			$this->log->log($this->items);
		} else {
			$this->log->log('Generate items - No Items generated');
		}
	}

	/**
	 * Advance the pointer (last indexed post) forward, to the last
	 * post we indexed in this batch.
	 *
	 * This should be called only if the posts are successfully sent
	 * to the API, because if API call fails, and we move forward
	 * despite that, then we will miss some posts.
	 */
	private function push_pointer_forwards()
	{
		if (!$this->posts_ids) {
			return;
		}

		$this->indexing_data->set(
			'post_id',
			$this->posts_ids[count($this->posts_ids) - 1]
		);
	}

	/**
	 * Wrapper function for check and set next item from the container list.
	 * If next item does not exist, then simple return false, otherwise true.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function check_next_($item, $container, $get_new_api = false, $skip_replace_index = false)
	{
		$current_item_index = array_search($this->indexing_data->get($item), $container);
		$next_item_index    = $current_item_index + 1;

		if (isset($container[$next_item_index]) && $container[$next_item_index]) {

			if (!$skip_replace_index) {
				// We are done with this batch, replace temp index
				$this->log->log('Check Next ' . $item . '  - Call replace Index');
				if ($this->api || $get_new_api) {
					$this->call_replace_index($get_new_api);
				}
			}

			$this->indexing_data->set($item, $container[$next_item_index]);
			$this->indexing_data->set('post_id', 0);

			$current_progress = $this->indexing_data->get('current_progress');
			$this->log->log('Check Next ' . $item . ' - Current progress: ', $current_progress);

			$this->indexing_data->set('processed_posts_count', $current_progress);

			return true;
		}

		return false;
	}

	/**
	 * Check and set next post type from the post types list.
	 * If next post type does not exist, then simple return false, otherwise true.
	 *
	 * @since 1.0.0
	 * @param bool $skip_replace_index
	 * @return bool
	 */
	private function check_next_post_type($skip_replace_index = false)
	{
		return $this->check_next_('post_type', $this->post_types, false, $skip_replace_index);
	}

	/**
	 * Check and set next language from the languages list.
	 * If next language does not exist, then simple return false, otherwise true.
	 *
	 * @since 1.0.0
	 * @param bool $skip_replace_index
	 * @return bool
	 */
	private function check_next_language($skip_replace_index = false)
	{
		return $this->check_next_('lang', $this->languages, true, $skip_replace_index);
	}

	/**
	 * Prepare ajax response.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $completed Status of indexing posts.
	 * @param string $message Additional message to pass to front.
	 */
	private function ajax_response($completed, $message = '')
	{
		// We're about to call "die", so we need to make sure our data
		// gets saved. Originally this was in the destructor but there
		// was an error - when sometimes WP cache crashed destructor
		// was not called, and the pointer information was not saved
		// in the DB as a result.

		$content = array(
			'completed' => $completed,
			'progress'  => $this->calculate_progress(),
		);

		$this->indexing_data->save();

		if ($message) {
			$content['message'] = $message;
		}

		wp_send_json_success($content);
	}

	/**
	 * Whenever we send response to the frontend the script terminates
	 * so if we don't save the indexing data it will be lost.
	 *
	 * @param array $args Arguments for "wp_send_json_error".
	 */
	private function ajax_response_error($args = array())
	{
		// We're about to call "die", so we need to make sure our data
		// gets saved. Originally this was in the destructor but there
		// was an error - when sometimes WP cache crashed destructor
		// was not called, and the pointer information was not saved
		// in the DB as a result.
		$this->indexing_data->save();

		wp_send_json_error($args);
	}

	/**
	 * Calculate the percentage of images already processed.
	 *
	 * We send posts to API post type after post type. Therefore we cannot
	 * find out how many posts we already sent just by looking at the ID,
	 * posts from post type B might come before posts from post type A.
	 *
	 * So the way to find out how many posts were processed already
	 * is to find out:
	 * - Total number of posts withing post types that were already
	 *   completely processed.
	 * - How many posts from the post type currently being processed
	 *   were already sent (all posts from the current post type up
	 *   to and including the ID of the last processed post).
	 *
	 * This function builds one SQL query that contains three COUNTs -
	 * the two mention above plus the count of all posts from all
	 * post types that we are indexing, and calculates percentage
	 * based on that.
	 *
	 * @return float Percentage of already processed posts.
	 */
	private function calculate_progress($get_count_all = false)
	{
		global $wpdb;
		global $sitepress;

		if ($get_count_all) {
			$this->log->log('Calculate Progress - Get Count All');
		}

		if (!$get_count_all) {
			$this->log->log('Current Memory Usage: ' . Helpers::get_memory_usage());
			//$this->log->log( 'Real Size OF Memory Allocated From System: ' . round(memory_get_usage(true)/1048576,2) . ' MB' );
			$this->log->log('Peak Memory Usage: ' . Helpers::get_memory_usage(false, true));
			//$this->log->log( 'Peak Memory Usage (real): ' . round(memory_get_peak_usage(true)/1048576,2) . ' MB'  );

			//$this->log->log( '---- Calculate Progress' );
		}
		// Base query - count of all posts of all supported post types.
		// Essentially - how many total posts are there to index.
		$split_variable_lang = $sitepress ? 'all' : '';

		$this->log->log('Calculate Progress - Split Variabable: ' . Settings::get('feed', 'split_variable', $split_variable_lang));

		if ('yes' === Settings::get('feed', 'split_variable', $split_variable_lang)) {
			$this->post_types[] = 'product_variation';
		}

		$post_types_list = $this->make_sql_list($this->post_types);

		//$this->log->log( 'Calculate Progress - Post types list : ' . $post_types_list );

		// Get currently processed language
		$lang = $this->indexing_data->get('lang');

		if (!$get_count_all) {
			$this->log->log('Calculate Progress - current lang: "' . $lang . '"');
		}

		$query = "";

		if (!$lang || !$sitepress) {
			// WMPL is not active. Make sure to get product variations that are not child of a product
			// with 'draft' status
			$query .= "
			SELECT
			(
				SELECT COUNT(DISTINCT posts.ID)
				FROM $wpdb->posts as posts
				LEFT JOIN {$wpdb->prefix}posts as postparents
                	ON posts.post_parent = postparents.ID
				WHERE posts.post_type IN ($post_types_list)
				AND posts.post_status = 'publish'
				AND (postparents.post_status IS NULL OR postparents.post_status = 'publish')
				AND (
					posts.ID NOT IN (
						SELECT object_id
						FROM {$wpdb->prefix}term_relationships
						WHERE term_taxonomy_id IN (
							SELECT term_id
							FROM `{$wpdb->prefix}terms`
							WHERE slug = 'exclude-from-search'
						)
					)
				)
			";
		} else if ($this->process_all_languages) {
			// WMPL is active and we want to count posts for all languages. Make sure to get product
			// variations that are not child of a product with 'draft' status
			$query .= "
			SELECT
			(
				SELECT COUNT(DISTINCT posts.ID)
				FROM {$wpdb->prefix}icl_translations as translations
				LEFT JOIN {$wpdb->prefix}posts as posts
					ON ( translations.element_id = posts.ID )
				LEFT JOIN {$wpdb->prefix}posts as postparents
                	ON posts.post_parent = postparents.ID
				WHERE posts.post_type IN ($post_types_list)
				AND posts.post_status = 'publish'
				AND (postparents.post_status IS NULL OR postparents.post_status = 'publish')
				AND (
					posts.ID NOT IN (
						SELECT object_id
						FROM {$wpdb->prefix}term_relationships
						WHERE term_taxonomy_id IN (
							SELECT term_id
							FROM `{$wpdb->prefix}terms`
							WHERE slug = 'exclude-from-search'
						)
					)
				)
			";
		} else {
			// When mulilang (WPML) is active we need to calculate posts only for
			// that language so we need to join translations table to the query
			// and make sure to get product variations that are not child of a product
			// with 'draft' status
			$query .= "
			SELECT
			(
				SELECT COUNT(DISTINCT posts.ID)
				FROM {$wpdb->prefix}icl_translations as translations
				LEFT JOIN {$wpdb->prefix}posts as posts
					ON ( translations.element_id = posts.ID )
				LEFT JOIN {$wpdb->prefix}posts as postparents
                	ON posts.post_parent = postparents.ID
				WHERE translations.language_code = '{$lang}'
				AND posts.post_type IN ($post_types_list)
				AND posts.post_status = 'publish'
				AND (postparents.post_status IS NULL OR postparents.post_status = 'publish')
				AND (
					posts.ID NOT IN (
						SELECT object_id
						FROM {$wpdb->prefix}term_relationships
						WHERE term_taxonomy_id IN (
							SELECT term_id
							FROM `{$wpdb->prefix}terms`
							WHERE slug = 'exclude-from-search'
						)
					)
				)
			";
		}

		$query .= "

			)
			AS 'all_posts'
			";


		// If there are any post types that we already fully indexed,
		// count posts from them.
		$indexed_post_types = array();

		// Take all post types that are before the post type we're
		// currently working on.
		foreach ($this->post_types as $post_type) {
			if ($post_type === $this->indexing_data->get('post_type')) {
				break;
			}

			$indexed_post_types[] = $post_type;
		}


		// Ok, if we have already indexed post types, add them to query.
		if ($indexed_post_types) {
			$indexed_post_types_list = $this->make_sql_list($indexed_post_types);


			$query .= "
				, -- Separates this select from previous
				(
					SELECT
					COUNT(*)
					FROM $wpdb->posts
				";

			// When mulilang (WPML) is active we need to calculate posts only for
			// that language so we need to join translations table to the query

			if ($lang && $sitepress) {

				$query .= "LEFT JOIN {$wpdb->prefix}icl_translations
				ON $wpdb->posts.ID = {$wpdb->prefix}icl_translations.element_id
				WHERE $wpdb->posts.post_type IN ($indexed_post_types_list)
				AND {$wpdb->prefix}icl_translations.language_code='{$this->indexing_data->get('lang')}'
				";
			} else {
				$query .= "
					WHERE $wpdb->posts.post_type IN ($indexed_post_types_list)
				";
			}

			$query .= "
				)
			    AS 'already_processed'
			";
		}

		if (!$get_count_all) {
			$this->log->log('Calculate Progress - Query:');
			$this->log->log($query);
		}
		// Add posts from the currently processed post type.
		if ($this->indexing_data->get('post_type')) {
			$post_type = $this->indexing_data->get('post_type');
			$last_id   = $this->indexing_data->get('post_id');

			$query .= "
				, -- Separates this select from previous
				(
					SELECT COUNT(*)
					FROM $wpdb->posts
			";

			// When mulilang (WPML) is active we need to calculate posts only for
			// that language so we need to join translations table to the query



			if ('yes' === Settings::get('feed', 'split_variable') && $post_type === 'product') {
				$post_type_query = "($wpdb->posts.post_type = '$post_type' OR $wpdb->posts.post_type = '{$post_type}_variation')";
			} else {
				$post_type_query = "$wpdb->posts.post_type = '$post_type'";
			}


			if ($lang && $sitepress) {
				$query .= "
					LEFT JOIN {$wpdb->prefix}icl_translations
					ON $wpdb->posts.ID = {$wpdb->prefix}icl_translations.element_id
					WHERE " . $post_type_query . "
					AND {$wpdb->prefix}icl_translations.language_code='{$this->indexing_data->get('lang')}'
				";
			} else {
				$query .= "
					WHERE " . $post_type_query . "
				";
			}

			$query .= "
					AND $wpdb->posts.ID <= $last_id

				)
				AS 'current_progress'
			";
		}

		// Check if returned data is valid. It should be array containing one element
		// (query returns one row of results)
		$result = $wpdb->get_results($query);



		//$this->log->log( 'Calculate Progress - Result:' );
		//$this->log->log( $result );

		if (!$result || !$result[0]) {
			return 0;
		}

		$result = $result[0];


		if ($get_count_all) {
			$this->log->log('Calculate Progress - All posts to index: ' . $result->all_posts);
			$this->indexing_data->set('all_posts_count', $result->all_posts);
			return $result->all_posts;
		}

		$already_processed = $this->indexing_data->get('processed_posts_count');
		$current_progress_get = $this->indexing_data->get('current_progress');

		$this->log->log('Calculate Progress - Current Progress get: ' . $current_progress_get);
		//$this->log->log( 'Calculate Progress - Already Processed get: ' . $already_processed);
		//$this->log->log( 'Calculate Progress - Result:' );
		//$this->log->log( $result );

		// Calculate percentage of posts processed.
		// This will be - percentage of  all posts from already processed post types
		// plus all the posts from post type currently being processed
		// that were already indexed.
		// $processed_posts = 0;
		// if ( isset( $already_processed ) && $already_processed > $result->current_progress  ) {
		// 	$processed_posts += $already_processed;
		// }

		// if ( isset( $result->current_progress ) ) {
		// 	$processed_posts += $result->current_progress;
		// }

		$processed_posts = $current_progress_get;

		$this->log->log('Calculate Progress - Processed: ' . $processed_posts . ' out of: ' . $result->all_posts);

		$this->indexing_data->set('current_progress', $processed_posts);

		if ($processed_posts > 0) {
			$current_progress = ($processed_posts / $result->all_posts) * 100;
		} else {
			$current_progress = 100;
			$this->log->log('No items found.');
		}

		$this->log->log('Calculate Progress - Summary: ' . round($current_progress, 2) . '%');
		return $current_progress;
	}

	/**
	 * Convert an array of string values into a list usable in an SQL query,
	 * so - items will be comma separated, and wrapped in "'", e.g.
	 * 'one','two','three'
	 *
	 * @param string[] $items
	 *
	 * @return string
	 */
	private function make_sql_list(array $items)
	{
		return implode(',', array_map(function ($item) {
			return "'$item'";
		}, $items));
	}

	/**
	 * Get the real, public name (label) of the post type with a given slug.
	 *
	 * @param string $slug
	 *
	 * @return string
	 */
	private function get_post_type_name($slug)
	{
		$post_type = get_post_type_object($slug);
		if (!$post_type) {
			return $slug;
		}

		return $post_type->labels->singular_name;
	}

	/**
	 * Get the real, public name (label) of the language with a given code.
	 *
	 * @param string $slug
	 *
	 * @return string
	 */
	private function get_language_name($code)
	{
		$languages = $this->language->get_languages();

		if (!$languages) {
			return $code;
		}

		return $languages[$code]['english_name'] ?? $code;
	}


	/**
	 * We are done so replace real index with temp one. Call replace temp index API method.
	 *
	 */
	private function call_replace_index($get_api = false)
	{

		if ($get_api) {
			$language = $this->indexing_data->get('lang');
			//$this->log->log( 'Call Replace Index  - lang: ' . $language );
			$this->log->log('Call Replace Index  - Get API Client Instance');
			$this->api = Api_Factory::get($language);
		}

		$post_type = $this->indexing_data->get('post_type');

		$api_response = $this->api->replace_index($post_type);

		$this->log->log($api_response);

		if ($api_response !== Api_Status::$success) {

			$message = __("Replacing Index \"$post_type\" with temporary one.", 'woocommerce-doofinder');

			// if ( $sent_to_api === Api_Status::$indexing_in_progress ) {
			// 	$message = __( "Deleting \"$post_type\" type...", 'woocommerce-doofinder' );
			// }

			$this->ajax_response_error(array(
				'status'  => $api_response,
				'message' => $message,
			));
		}
	}


	/**
	 * Check index status if it is up to date with db changes. Compare last modified dates
	 * for index and db changes.
	 *
	 */
	public static function is_index_data_up_to_date()
	{
		$last_modified_db = (int) Settings::get_last_modified_db();
		$last_modified_index = (int) Settings::get_last_modified_index();

		if ($last_modified_db - $last_modified_index <= 5) {
			return true;
		} else {
			return false;
		}
	}
}