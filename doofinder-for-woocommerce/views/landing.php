<?php

namespace Doofinder\WP;

error_reporting(E_ALL);
ini_set('display_errors', '1');

use Doofinder\WP\Landing;

if (!class_exists('Doofinder\WP\Landing')) {
    // La clase Landing no está definida, carga el núcleo de WordPress.
    $parse_uri = explode( 'wp-content', $_SERVER['SCRIPT_FILENAME'] );
    require_once( $parse_uri[0] . 'wp-load.php' );
}

/**
 * @var Landing $landing
 */
$landing = new Landing();

$landing_slug = get_query_var('df-landing');


if ($landing_slug != false) {
    $hashid = get_query_var('hashid');;
} else {
    $landing_slug = $_GET['slug'];
    $hashid = $_GET['hashid'];
}

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
        <head>
            <link rel="stylesheet" href="<?php echo Doofinder_For_WordPress::plugin_url(); ?>assets/css/landing.css">
            <?php get_header(); ?>      
        </head>
       <body class="woocommerce woocommerce-page woocommerce-js">
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
        </body>
</html>