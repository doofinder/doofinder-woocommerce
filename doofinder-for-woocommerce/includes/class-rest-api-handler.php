<?php

namespace Doofinder\WP;

use \WP_HTTP_Response;
use \WP_REST_Server;
use \WP_REST_Request;

class REST_API_Handler
{
    /**
     * Add the actions and filters needed to modify REST requests
     *
     * @return void
     */
    public static function initialize()
    {
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            add_action('rest_post_dispatch', array(__CLASS__, 'add_woocommerce_product_images'), 99, 3);
            add_action('rest_post_dispatch', array(__CLASS__, 'add_taxonomy_image_link'), 99, 3);
        }
    }

    /**
     * Add image link to taxonomy rest request
     *
     * @param WP_HTTP_Response $result
     * @param WP_REST_Server $server
     * @param WP_REST_Request $request
     * @return WP_HTTP_Response
     */
    public static function add_taxonomy_image_link($result,  $server,  $request)
    {
        if ($request->get_route() === "/wp/v2/product_cat") {
            $terms = $result->data;
            foreach ($terms as $key => $term) {
                // get the thumbnail id using the queried category term_id
                $thumbnail_id = get_term_meta($term['id'], 'thumbnail_id', true);
                $result->data[$key]['image_link'] = empty($thumbnail_id) ? "" : wp_get_attachment_url($thumbnail_id);
            }
        }
        return $result;
    }

    /**
     * Add image links to products and variations
     *
     * @param WP_HTTP_Response $result
     * @param WP_REST_Server $server
     * @param WP_REST_Request $request
     * @return WP_HTTP_Response
     */
    public static function add_woocommerce_product_images($result,  $server,  $request)
    {
        if (
            $request->get_route() === "/wc/v3/products" || //for products
            preg_match_all('/\/wc\/v3\/products\/\d+\/variations/', $request->get_route()) //for product variations
        ) {
            $products = $result->data;
            foreach ($products as $key => $product) {
                $post = get_post($product['id']);
                $thumbnail = new Thumbnail($post);
                $image_link = $thumbnail->get();
                $result->data[$key]['df_image_link'] = empty($image_link) ? wc_placeholder_img_src(Thumbnail::get_size()) : $image_link;
            }
        }
        return $result;
    }
}
