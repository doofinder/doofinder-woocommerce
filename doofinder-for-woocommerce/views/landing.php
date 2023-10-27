<?php

namespace Doofinder\WP;

use Doofinder\WP\Landing;

if (!class_exists('Doofinder\WP\Landing')) {
    // The Landing class is not defined, it loads the WordPress core.
    // This means that it is accessed directly from the PHP file.
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
    $landing->create_redirect($_GET['slug'], $_GET['hashid']); 
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