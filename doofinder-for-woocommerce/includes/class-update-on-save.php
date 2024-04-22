<?php

namespace Doofinder\WP;

use Doofinder\WP\Update_On_Save_Index;
use Doofinder\WP\Log;
use Doofinder\WP\Post;
use Doofinder\WP\Multilanguage\Multilanguage;

class Update_On_Save
{
    public static function init()
    {
        $class = __CLASS__;
        add_action('doofinder_update_on_save', array($class, 'launch_update_on_save_task'), 10, 0);

        //Force Update on save
        add_action('wp_ajax_doofinder_force_update_on_save', function () {
            if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'doofinder-ajax-nonce')) {
                status_header(\WP_Http::UNAUTHORIZED);
                die('Unauthorized request');
            }
            do_action("doofinder_update_on_save");
            wp_send_json_success();
        });
    }

    public static function launch_update_on_save_task()
    {
        $update_on_save_index = new Update_On_Save_Index();
        $update_on_save_index->launch_doofinder_update_on_save();
    }


    public static function activate_update_on_save_task()
    {
        if (Settings::is_update_on_save_enabled()) {
            $language = Multilanguage::instance();
            $current_language = $language->get_current_language();
            $update_on_save_schedule = Settings::get_update_on_save($current_language);
            wp_schedule_event(time(), $update_on_save_schedule, 'doofinder_update_on_save');
        }
    }

    public static function deactivate_update_on_save_task()
    {
        wp_clear_scheduled_hook('doofinder_update_on_save');
    }

    public static function update_on_save_schedule_updated($old_value, $value, $option)
    {
        self::deactivate_update_on_save_task();
        if (Settings::is_update_on_save_enabled()) {
            self::activate_update_on_save_task();
        }
    }

    public static function is_cron_enabled()
    {
        return !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON);
    }

    /**
     * Creates the database table for storing update on save information if it doesn't exist.
     */
    public static function create_update_on_save_db()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'doofinder_update_on_save';

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '" . $table_name . "'");

        if ($table_exists === NULL) {
            // The table does not exist, we create it
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                post_id INT NOT NULL,
                type_post VARCHAR(255),
                type_action VARCHAR(255),
                PRIMARY KEY (post_id)
            ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        update_option('doofinder_update_on_save_last_exec', date('Y-m-d H:i:s'));
    }

    /**
     * Register hooks and actions.
     */
    public static function register_hooks()
    {
        add_action('wp_insert_post', function ($post_id, \WP_Post $post, $updated) {
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
                return;
            self::add_item_to_update($post_id, $post, $updated);
        }, 99, 3);


        // add_action('doofinder_update_on_save', [self::class, 'launch_update_on_save'], 10, 0);
        add_action("update_option_doofinder_for_wp_update_on_save", [self::class, 'update_on_save_schedule_updated'], 10, 3);
    }

    /**
     * Determines if the current post should be indexed and adds it to the update queue.
     *
     * @param int $post_id The ID of the post.
     * @param WP_Post $post The post object.
     */
    public static function add_item_to_update($post_id, $post, $updated)
    {

        $log = new Log('update-on-save-add-item.log');
        $update_on_save_index = new Update_On_Save_Index();

        if (Settings::is_update_on_save_enabled()) {
            $doofinder_post = new Post($post);
            $log->log('Add this item to update on save: ' . print_r($doofinder_post, true));

            self::add_item_to_db($doofinder_post, $post_id, $post->post_status, $post->post_type);

            //If the cron is disabled, we execute the update on save workaround
            if (!self::is_cron_enabled() && self::allow_process_items()) {
                $log->log('We can send the data. ');
                $update_on_save_index->launch_doofinder_update_on_save();
            }
        }
    }

    /**
     * Adds the item to the update queue in the database.
     *
     * @param Post $doofinder_post The Doofinder post object.
     * @param int $post_id The ID of the post.
     * @param string $status The status of the post.
     * @param string $type The type of the post.
     */
    public static function add_item_to_db($doofinder_post, $post_id, $status, $type)
    {
        $log = new Log('update-on-save-add-item.log');

        if ($type == "post" || $type == "page") {
            $type .= "s";
        }

        if ($status === 'auto-draft' || $type === "revision") {
            # If it is draft or revision we don't need to do anything with it because we don't have to index it.
            $log->log('It is not necessary to save it as it is a draft. ');
        } elseif ($doofinder_post->is_indexable() && in_array($type, ["posts", "product", "pages"])) {
            # If the status of the post is still indexable after the changes we do an update.
            $log->log('The item will be saved with the update action. ');
            self::add_to_update_on_save_db($post_id, $type, "update");
        } elseif (in_array($type, ["posts", "product", "pages"])) {
            # If the status of the post is no longer indexable we have to delete it.
            $log->log('The item will be saved with the delete action. ');
            self::add_to_update_on_save_db($post_id, $type, "delete");
        } else {
            $log->log('It is not necessary to save it, since it is a non-indexable type.');
        }
    }

    /**
     * Adds an item to the update queue in the database.
     *
     * @param int $post_id The ID of the post.
     * @param string $post_type The type of the post.
     * @param string $action The action to perform on the post (update/delete).
     */
    public static function add_to_update_on_save_db($post_id, $post_type, $action)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'doofinder_update_on_save';

        $result = $wpdb->get_var("SELECT * FROM $table_name WHERE post_id = $post_id");

        if ($result != null) {
            $wpdb->update(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'type_post' => $post_type,
                    'type_action' => $action
                ),
                array( 'post_id' => $post_id )
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'type_post' => $post_type,
                    'type_action' => $action
                )
            );
        }
    }

    /**
     * Checks if processing of items is allowed based on the configured update frequency.
     *
     * @return bool True if processing is allowed, false otherwise.
     */
    public static function allow_process_items()
    {

        $log = new Log('update-on-save-add-item.log');
        $language = Multilanguage::instance();
        $current_language = $language->get_active_language();
        $update_on_save_schedule = Settings::get_update_on_save($current_language);

        // Get all available scheduled intervals
        $schedules = wp_get_schedules();

        // Check if the cron job is scheduled
        if (isset($schedules[$update_on_save_schedule])) {
            $delay = $schedules[$update_on_save_schedule]['interval'];
        } else {
            $delay = 900;
        }

        $last_exec = get_option('doofinder_update_on_save_last_exec');

        $log->log('The last execution was: ' . $last_exec);
        $log->log('The established delay is:  ' . $delay);

        $last_exec_ts = strtotime($last_exec);

        $diff_min = (time() - $last_exec_ts);

        $log->log('The difference is:  ' . $diff_min);

        if ($diff_min >= $delay) {
            return true;
        }

        return false;
    }

    /**
     * Cleans the update on save database table by deleting all entries.
     *
     * @return bool True if the database table was cleaned successfully, false otherwise.
     */
    public static function clean_update_on_save_db()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'doofinder_update_on_save';

        $wpdb->query("TRUNCATE TABLE $table_name");

        $log = new Log('update-on-save-add-item.log');
        $log->log('Cleaned database');
    }

    /**
     * Deletes the update on save database table.
     *
     * @return bool True if the database table was deleted successfully, false otherwise.
     */
    public static function delete_update_on_save_db()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'doofinder_update_on_save';

        $wpdb->query("DROP TABLE $table_name");

        $log = new Log('update-on-save-add-item.log');
        $log->log('Deleted database');
    }

    /**
     * Cleans the update on save database table by deleting the updated entries
     *
     * @return void
     */
    public static function clean_updated_items($ids, $action)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'doofinder_update_on_save';
        $ids = implode(",", $ids);
        $query = "DELETE FROM $table_name WHERE post_id IN ($ids) AND type_action = '$action'";
        $affected_rows = $wpdb->query($query);

        $log = new Log('update-on-save-add-item.log');
        $log->log("Update on save: " . $action . "d $affected_rows rows with IDs: $ids");
    }
}
