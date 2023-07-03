<?php

namespace Doofinder\WP;

use Doofinder\WP\Api\Update_On_Save_Api;
use Doofinder\WP\Log;
use Doofinder\WP\Multilanguage\Language_Plugin;
use Doofinder\WP\Multilanguage\Multilanguage;

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
 */
class Update_On_Save_Index
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
     * @var Update_On_Save_Api
     */
    private $api;

    /**
     * Instance of a class used to log to a file.
     *
     * @var Log
     */
    private $log;

    /**
     * List with default post types.
     *
     * @var array
     */
    private $post_types = array("product", "post", "page");

    public function __construct()
    {
        $this->language             = Multilanguage::instance();
        $this->current_language     = $this->language->get_current_language();
        $this->api                    = new Update_On_Save_Api($this->current_language);
        $this->log                     = new Log('update_on_save_api.txt');
    }

    /**
     * Launches the Doofinder update on save process.
     *
     * This method triggers the update on save process by calling the `update_on_save()` method
     * for both the "update" and "delete" actions.
     *
     * @return void
     * @since 1.0.0
     */
    public function launch_doofinder_update_on_save()
    {
        $this->log->log('Launch Doofinder update on save');
        $this->update_on_save('update');
        $this->update_on_save('delete');
        //Update last exec
        update_option('doofinder_update_on_save_last_exec', date('Y-m-d H:i:s'));
    }

    /**
     * Perform the update on save operation for the specified action.
     *
     * @param string $action The action to perform (either "update" or "delete").
     * @since 1.0.0
     */
    public function update_on_save($action)
    {
        // Load the data that we'll use to fetch posts.
        $this->log->log('Update on save is enabled for these types of posts: ');
        $this->log->log($this->post_types);

        foreach ($this->post_types as $post_type) {
            $this->log->log('Posts ids to update for ' . $post_type . ': ');
            $posts_ids_to_update = $this->get_posts_ids_by_type_indexation($post_type, $action);
            $this->log->log($posts_ids_to_update);

            if (!empty($posts_ids_to_update)) {
                $this->log->log('Ids ready to send ' . print_r($posts_ids_to_update, true));

                // Call the function passing the post type name as a parameter
                switch ($action) {
                    case 'update':
                        $this->log->log('We send the request to UPDATE items with this data:');
                        $this->api->updateBulk($post_type, $posts_ids_to_update);
                        break;
                    case 'delete':
                        $this->log->log('We send the request to DELETE items with this data:');
                        $this->api->deleteBulk($post_type, $posts_ids_to_update);
                        break;
                }
            } else {
                $this->log->log('No objects to index.');
            }
        }
    }

    /**
     * Get post IDs by type for indexation.
     *
     * @param string $post_type The type of posts to retrieve IDs for.
     * @param string $action The action type ('update' or 'delete').
     * @return array An array of post IDs.
     * @since 1.0.0
     */
    public function get_posts_ids_by_type_indexation($post_type, $action)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'doofinder_update_on_save';

        $query = "SELECT * FROM $table_name WHERE type_post = '$post_type' AND type_action = '$action'";

        $ids = $wpdb->get_results($query, ARRAY_N);

        if (!$ids) {
            return array();
        }

        return array_map(function ($item) {
            return $item[0];
        }, $ids);
    }
}
