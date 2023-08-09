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
                if (!is_array($product)) {
                    continue;
                }
                $product = self::add_df_prices($product);
                $product = self::add_df_image_link($product);
                $result->data[$key] = $product;
            }
        }
        return $result;
    }

    private static function get_raw_real_price($price, $product)
    {
        $woocommerce_tax_display_shop = get_option('woocommerce_tax_display_shop', 'incl');
        return 'incl' === $woocommerce_tax_display_shop ?
            wc_get_price_including_tax(
                $product,
                array(
                    'price' => $price,
                )
            ) :
            wc_get_price_excluding_tax(
                $product,
                array(
                    'price' => $price,
                )
            );
    }

    /**
     * Function that adds the image link for 
     *
     * @param array $product The product or variant we are modifying
     * @return array $product The product with the new df_image_link
     */
    private static function add_df_image_link($product)
    {
        $product_id = $product['id'];
        $post = get_post($product_id);
        $thumbnail = new Thumbnail($post);
        $image_link = $thumbnail->get();
        if (empty($image_link) && $post->post_type === 'product_variation') {
            $thumbnail = new Thumbnail(get_post($post->post_parent));
            $image_link = $thumbnail->get();
        }
        //If neither the variant and the product have an image, return the woocommerce placeholder image
        $product['df_image_link'] = empty($image_link) ? wc_placeholder_img_src(Thumbnail::get_size()) : $image_link;
        return $product;
    }

    /**
     * Add the raw prices with taxes applied if needed
     *
     * @param array $product The product or variant we are modifying
     * @return array $product The product with the raw price keys
     */
    private static function add_df_prices($product)
    {
        $product_id = $product['id'];
        $wc_product = wc_get_product($product_id);
        $prices = [
            "regular_price" => "",
            "sale_price" => "",
            "price" => ""
        ];

        foreach ($prices as $price_name => $value) {
            $get_price_fn = 'get_' . $price_name;
            $price = $wc_product->$get_price_fn();
            $product['df_' . $price_name] = self::get_raw_real_price($price, $wc_product);
        }
        return $product;
    }
}
