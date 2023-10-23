<?php
$base_path = dirname(__FILE__) . '/../../../';
require( $base_path. '/wp-load.php');

function print_options()
{
    global $wpdb;
    $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}options WHERE option_name LIKE '%doofinder%'", OBJECT);

    echo "<h1>DOOFINDER OPTIONS</h1>";
    echo "<table><thead><tr><td>ID</td><td>Name</td><td>Value</td></tr></thead>";
    foreach ($results as $key => $option) {
        $value = $option->option_value;
        if (is_serialized($value)) {
            $value = print_r(unserialize($value), true);
        }
        echo "<tr><td>" . $option->option_id . "</td><td>" . $option->option_name . "</td><td>" . $value . "</td></tr>";
    }
    echo "</table>";
}

/**
 * This function resets some fields needed to the migration to run again
 *
 * @return void
 */
function reset_migration()
{
    $options = [
        'doofinder_for_wp_api_key',
        'doofinder_for_wp_api_host',
        'doofinder_v2_migration_status',
        'doofinder_for_wp_plugin_version'
    ];

    foreach ($options as $key => $option) {
        delete_option($option);
    }
}

if (isset($_GET['print_options'])) {
    print_options();
}

if (isset($_GET['reset_migration'])) {
    reset_migration();
    print_options();
}
