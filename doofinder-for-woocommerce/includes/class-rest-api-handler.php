<?php

namespace Doofinder\WP;

class REST_API_Handler
{
    const PRODUCT_FIELDS = [
        'df_price',
        'df_sale_price',
        'df_regular_price',
        'df_image_link'
    ];

    /**
     * Register the REST Fields we want to add
     *
     * @return void
     */
    public static function initialize()
    {
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            //Register the product category image field
            register_rest_field('product_cat', 'image_link', ['get_callback' => array(REST_API_Handler::class, 'get_product_cat_df_image_link')]);

            //Register the product fields
            foreach (self::PRODUCT_FIELDS as $field) {
                register_rest_field(array('product', 'product_variation'), $field, ['get_callback' => array(REST_API_Handler::class, 'get_' . $field)]);
            }
        }
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
     * Check that image link is absolute, if not, add the site url
     *
     * @param string $image_link
     * @return string $image_link
     */
    private static function add_base_url_if_needed($image_link)
    {
        if (0 === strpos($image_link, "/")) {
            $image_link = get_site_url() . $image_link;
        }
        return $image_link;
    }

    /**
     * Returns the raw price for the given product.
     *
     * @param array $product The product we want to add the field
     * @param string $price_name The price name. By default 'price'
     * @return void
     */
    private static function get_raw_price($product, $price_name = 'price')
    {
        $product_id = $product['id'];
        $wc_product = wc_get_product($product_id);
        $fn_name = "get_$price_name";
        if (is_a($wc_product, 'WC_Product') && method_exists($wc_product, $fn_name)) {
            $price = $wc_product->$fn_name();
            $raw_price =  $price_name === "sale_price" && $price === "" ? "" : self::get_raw_real_price($price, $wc_product);
            return $raw_price;
        }
    }

    /**
     * Get the raw price
     *
     * @param array $product WooCommerce Product or Variable Product as array.
     * @return float The raw price including or excluding taxes (defined in WC settings).
     */
    public static function get_df_price($product)
    {
        return  self::get_raw_price($product);
    }

    /**
     * Get the raw sale price
     *
     * @param array $product WooCommerce Product or Variable Product as array.
     * @return float The raw sale price including or excluding taxes (defined in WC settings).
     */
    public static function get_df_sale_price($product)
    {
        return  self::get_raw_price($product, 'sale_price');
    }

    /**
     * Get the raw regular price
     *
     * @param array $product WooCommerce Product or Variable Product as array.
     * @return float The raw regular price including or excluding taxes (defined in WC settings).
     */
    public static function get_df_regular_price($product)
    {
        return  self::get_raw_price($product, 'regular_price');
    }

    /**
     * Returns the image link for a given product.
     * If the product is a variation and doesn't have an image, return the parent image link
     *
     * @param array $product WooCommerce Product or Variable Product as array.
     * @return string The image link
     */
    public static function get_df_image_link($product)
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
        $image_link = empty($image_link) ? wc_placeholder_img_src(Thumbnail::get_size()) : $image_link;
        $image_link = self::add_base_url_if_needed($image_link);
        return $image_link;
    }

    /**
     * Returns the image link for a given term.     
     *
     * @param array $product WooCommerce Product or Variable Product as array.
     * @return string The image link
     */
    public static function get_product_cat_df_image_link($term)
    {
        // get the thumbnail id using the queried category term_id
        $thumbnail_id = get_term_meta($term['id'], 'thumbnail_id', true);
        $image_link = empty($thumbnail_id) ? "" : wp_get_attachment_url($thumbnail_id);
        $image_link = empty($image_link) ? wc_placeholder_img_src(Thumbnail::get_size()) : $image_link;
        $image_link = self::add_base_url_if_needed($image_link);
        return $image_link;
    }
}
