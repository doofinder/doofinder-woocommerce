<?php
/**
 * DooFinder Update_On_Save methods.
 *
 * @package Update_On_Save
 */

namespace Doofinder\WP;

use Doofinder\WP\Update_On_Save_Index;
use Doofinder\WP\Log;
use Doofinder\WP\Post;
use Doofinder\WP\Multilanguage\Multilanguage;

/**
 * Handles the update on save process.
 */
class Update_On_Save {

	/**
	 * Adds the actions for the update on save and its associated AJAX to force it via clicking the button.
	 */
	public static function init() {
		$class = __CLASS__;
		add_action( 'doofinder_update_on_save', array( $class, 'launch_update_on_save_task' ), 10, 0 );

		// Force Update on save.
		add_action(
			'wp_ajax_doofinder_force_update_on_save',
			function () {
				if ( ! isset( $_POST['nonce'] ) || ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'doofinder-ajax-nonce' ) ) {
					status_header( \WP_Http::UNAUTHORIZED );
					die( 'Unauthorized request' );
				}
				/**
				 * Triggers the `doofinder_update_on_save` action.
				 *
				 * @since 1.0.0
				 */
				do_action( 'doofinder_update_on_save' );
				wp_send_json_success();
			}
		);
	}

	/**
	 * Launches the update on save task.
	 */
	public static function launch_update_on_save_task() {
		$update_on_save_index = new Update_On_Save_Index();
		$update_on_save_index->launch_doofinder_update_on_save();
	}

	/**
	 * If enabled, activates the update on save task according to the defined schedule.
	 */
	public static function activate_update_on_save_task() {
		if ( Settings::is_update_on_save_enabled() ) {
			$language                = Multilanguage::instance();
			$current_language        = $language->get_current_language();
			$update_on_save_schedule = Settings::get_update_on_save( $current_language );
			wp_schedule_event( time(), $update_on_save_schedule, 'doofinder_update_on_save' );
		}
	}

	/**
	 * Clears the scheduled hook, so the update on save will be disabled.
	 */
	public static function deactivate_update_on_save_task() {
		wp_clear_scheduled_hook( 'doofinder_update_on_save' );
	}

	/**
	 * Updates the value of the update on save scheduled process.
	 */
	public static function update_on_save_schedule_updated() {
		self::deactivate_update_on_save_task();
		if ( Settings::is_update_on_save_enabled() ) {
			self::activate_update_on_save_task();
		}
	}

	/**
	 * Checks if WP Cron is enabled since it's need to schedule the update on save.
	 *
	 * @return bool
	 */
	public static function is_cron_enabled() {
		return ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON );
	}

	/**
	 * Creates the database table for storing update on save information if it doesn't exist.
	 */
	public static function create_update_on_save_db() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'doofinder_update_on_save';

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		if ( null === $table_exists ) {
			// The table does not exist, we create it.
			$charset_collate = $wpdb->get_charset_collate();
			$sql             = "CREATE TABLE $table_name (
                post_id INT NOT NULL,
                type_post VARCHAR(255),
                type_action VARCHAR(255),
                PRIMARY KEY (post_id)
            ) $charset_collate;";
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		update_option( 'doofinder_update_on_save_last_exec', gmdate( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Register hooks and actions.
	 */
	public static function register_hooks() {
		add_action(
			'wp_insert_post',
			function ( $post_id, \WP_Post $post ) {
				if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
					return;
				}
				self::add_item_to_update( $post_id, $post );
			},
			99,
			3
		);

		add_action( 'update_option_doofinder_for_wp_update_on_save', array( self::class, 'update_on_save_schedule_updated' ), 10, 3 );
	}

	/**
	 * Determines if the current post should be indexed and adds it to the update queue.
	 *
	 * @param int     $post_id The ID of the post.
	 * @param WP_Post $post The post object.
	 */
	public static function add_item_to_update( $post_id, $post ) {

		$log                  = new Log( 'update-on-save-add-item.log' );
		$update_on_save_index = new Update_On_Save_Index();

		if ( Settings::is_update_on_save_enabled() ) {
			$doofinder_post = new Post( $post );
			$log->log( 'Add this item to update on save: ' . print_r( $doofinder_post, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

			self::add_item_to_db( $doofinder_post, $post_id, $post->post_status, $post->post_type );

			// If the cron is disabled, we execute the update on save workaround.
			if ( ! self::is_cron_enabled() && self::allow_process_items() ) {
				$log->log( 'We can send the data. ' );
				$update_on_save_index->launch_doofinder_update_on_save();
			}
		}
	}

	/**
	 * Adds the item to the update queue in the database.
	 *
	 * @param Post   $doofinder_post The Doofinder post object.
	 * @param int    $post_id The ID of the post.
	 * @param string $status The status of the post.
	 * @param string $type The type of the post.
	 */
	public static function add_item_to_db( $doofinder_post, $post_id, $status, $type ) {
		$log = new Log( 'update-on-save-add-item.log' );

		if ( 'post' === $type || 'page' === $type ) {
			$type .= 's';
		}

		if ( 'auto-draft' === $status || 'revision' === $type ) {
			// If it is draft or revision we don't need to do anything with it because we don't have to index it.
			$log->log( 'It is not necessary to save it as it is a draft. ' );
		} elseif ( $doofinder_post->is_indexable() && in_array( $type, array( 'posts', 'product', 'pages' ), true ) ) {
			// If the status of the post is still indexable after the changes we do an update.
			$log->log( 'The item will be saved with the update action. ' );
			self::add_to_update_on_save_db( $post_id, $type, 'update' );
		} elseif ( in_array( $type, array( 'posts', 'product', 'pages' ), true ) ) {
			// If the status of the post is no longer indexable we have to delete it.
			$log->log( 'The item will be saved with the delete action. ' );
			self::add_to_update_on_save_db( $post_id, $type, 'delete' );
		} else {
			$log->log( 'It is not necessary to save it, since it is a non-indexable type.' );
		}
	}

	/**
	 * Adds an item to the update queue in the database.
	 *
	 * @param int    $post_id The ID of the post.
	 * @param string $post_type The type of the post.
	 * @param string $action The action to perform on the post (update/delete).
	 */
	public static function add_to_update_on_save_db( $post_id, $post_type, $action ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'doofinder_update_on_save';

		$result = $wpdb->get_var( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'doofinder_update_on_save WHERE post_id = %d', $post_id ) );

		if ( null !== $result ) {
			$wpdb->update(
				$table_name,
				array(
					'post_id'     => $post_id,
					'type_post'   => $post_type,
					'type_action' => $action,
				),
				array( 'post_id' => $post_id )
			);
		} else {
			$wpdb->insert(
				$table_name,
				array(
					'post_id'     => $post_id,
					'type_post'   => $post_type,
					'type_action' => $action,
				)
			);
		}
	}

	/**
	 * Checks if processing of items is allowed based on the configured update frequency.
	 *
	 * @return bool True if processing is allowed, false otherwise.
	 */
	public static function allow_process_items() {

		$log                     = new Log( 'update-on-save-add-item.log' );
		$language                = Multilanguage::instance();
		$current_language        = $language->get_active_language();
		$update_on_save_schedule = Settings::get_update_on_save( $current_language );

		// Get all available scheduled intervals.
		$schedules = wp_get_schedules();

		// Check if the cron job is scheduled.
		if ( isset( $schedules[ $update_on_save_schedule ] ) ) {
			$delay = $schedules[ $update_on_save_schedule ]['interval'];
		} else {
			$delay = 900;
		}

		$last_exec = get_option( 'doofinder_update_on_save_last_exec' );

		$log->log( 'The last execution was: ' . $last_exec );
		$log->log( 'The established delay is:  ' . $delay );

		$last_exec_ts = strtotime( $last_exec );

		/*
		 * We are using strtotime( gmdate( 'Y-m-d H:i:s' ) ) instead of time() since time
		 * is relying on the timezone (when it shouldn't). The dates are now saved using
		 * gmdate instead of date to fulfill WordPress standards. https://github.com/WordPress/WordPress-Coding-Standards/issues/1713.
		 */
		$diff_min = ( strtotime( gmdate( 'Y-m-d H:i:s' ) ) - $last_exec_ts );

		$log->log( 'The difference is:  ' . $diff_min );

		if ( $diff_min >= $delay ) {
			return true;
		}

		return false;
	}

	/**
	 * Cleans the update on save database table by deleting all entries.
	 *
	 * @return void
	 */
	public static function clean_update_on_save_db() {
		global $wpdb;

		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'doofinder_update_on_save' );

		$log = new Log( 'update-on-save-add-item.log' );
		$log->log( 'Cleaned database' );
	}

	/**
	 * Deletes the update on save database table.
	 *
	 * @return void.
	 */
	public static function delete_update_on_save_db() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'doofinder_update_on_save';

		$wpdb->query( 'DROP TABLE ' . $wpdb->prefix . 'doofinder_update_on_save' );

		$log = new Log( 'update-on-save-add-item.log' );
		$log->log( 'Deleted database' );
	}

	/**
	 * Cleans the update on save database table by deleting the updated entries.
	 *
	 * @param array  $ids Array of post IDs.
	 * @param string $action Update on save action (can be update, create or delete).
	 *
	 * @return void
	 */
	public static function clean_updated_items( $ids, $action ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'doofinder_update_on_save';
		$ids        = implode( ',', esc_sql( $ids ) );
		$query      = 'DELETE FROM ' . esc_sql( $table_name ) . " WHERE post_id IN ($ids) AND type_action = '" . esc_sql( $action ) . "'";
		// WordPress.DB.PreparedSQL can be ignored because we are sanitizing everything with `esc_sql()` function instead.
		$affected_rows = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL

		$log = new Log( 'update-on-save-add-item.log' );
		$log->log( 'Update on save: ' . $action . "d $affected_rows rows with IDs: $ids" );
	}
}
