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
// TODO: When the cache part is developed, it is checked whether the call needs to be made or not.
$landing_data = $landing->get_landing_info($hashid, $landing_slug);

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<?php

get_header();


// Configure metadata and indexing policies
// $meta_title = $this->landing_data['meta_title'];
// $meta_description = $this->landing_data['meta_description'];
// $index = $this->landing_data['index'];

// // Configure the page meta title
// add_filter('wp_title', function ($title) use ($meta_title) {
//     return $meta_title;
// }, 10, 2);

// // Configure the meta description
// add_filter('document_title_parts', function ($title_parts) use ($meta_description) {
//     $title_parts['description'] = $meta_description;
//     return $title_parts;
// });

// // Configure indexing policies
// if ($index === false) {
//     add_filter('wp_robots', function ($robots) {
//         return 'noindex,nofollow';
//     });
// }
?>

<div class="df-landing site-content">
    <?php
    if (isset($landing_data['error'])) {
        echo $landing->get_error_html($landing_data['error']);
    } elseif (isset($landing_data['data_not_set'])) {
        echo $landing->get_error_html($landing_data['data_not_set']);
    } elseif (isset($landing_data['data'])) {
        echo $landing->get_landing_html();
    }
    ?>

</div>

</html>