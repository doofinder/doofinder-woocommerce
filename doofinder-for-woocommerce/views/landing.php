<?php

namespace Doofinder\WP;

use Doofinder\WP\Landing;

error_reporting(E_ALL);
ini_set('display_errors', '1');

/**
 * @var Landing $landing
 */
$landing = new Landing();

$landing_slug = get_query_var('df-landing');
$hashid = get_query_var('hashid');
$landing_data = $landing->get_landing_info($hashid, $landing_slug);

if (isset($landing_data['data'])) {
    $meta_title = $landing_data["data"]['meta_title'];
    $meta_description = $landing_data["data"]['meta_description'];
    $index = $landing_data["data"]['index'];
    $landing->set_meta_data($meta_title, $meta_description, $index);
}

?>
<!DOCTYPE html>
    <html <?php language_attributes(); ?>>
        <?php get_header(); ?>


        <?php
        if (isset($landing_data['error'])) {
            echo $landing->get_error_html($landing_data['error']);
        } elseif (isset($landing_data['data_not_set'])) {
            echo $landing->get_error_html($landing_data['data_not_set']);
        } elseif (isset($landing_data['data'])) {
            echo $landing->get_landing_html($landing_slug);
        }
        ?>

        <?php get_footer(); ?>

</html>