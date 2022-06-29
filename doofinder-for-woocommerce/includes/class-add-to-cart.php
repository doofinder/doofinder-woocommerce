<?php

namespace Doofinder\WC;

class Add_To_Cart
{

    /**
     * Singleton of this class.
     *
     * @var Add_To_Cart
     */
    private static $_instance;

    /**
     * Returns the only instance of Add_To_Cart.
     *
     * @since 1.5.23
     * @return Add_To_Cart
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Add_To_Cart constructor.
     *
     * @since 1.5.23
     */
    public function __construct()
    {
        add_action('wp_ajax_woocommerce_ajax_add_to_cart',  array(__CLASS__, 'woocommerce_ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_woocommerce_ajax_add_to_cart',  array(__CLASS__, 'woocommerce_ajax_add_to_cart'));

        // Register custom WP REST Api endpoint
        add_action('rest_api_init', function () {
            register_rest_route('doofinder-for-wc/v1', '/product-info/(?P<id>\d+)', array(
                'methods' => ['GET'],
                'callback' => array(__CLASS__, 'product_info'),
                'permission_callback' => '__return_true'
            ));
        });
    }

    /**
     * Callback for WP Rest Api item-info endpoint
     */
    public static function product_info(\WP_REST_Request $request)
    {
        $post_id = $request->get_param("id");
        $product = wc_get_product($post_id);
        $variations = $product->get_children();
        $default_variation_attributes = $product->get_default_attributes();

        $data = [
            "variations" => $variations,
            "product_url" => get_the_permalink($post_id),
            "default_variation" => 0,
            "add_to_cart" => true
        ];

        if (is_a($product, 'WC_Product_Variable') && !empty($variations)) {
            foreach ($variations as $variation_ID) {
                // get variations meta
                $product_variation = new \WC_Product_Variation($variation_ID);
                $variation_data = $product_variation->get_data();
                $attributes = $variation_data["attributes"];
                if (empty(array_diff($attributes, $default_variation_attributes))) {
                    $data['default_variation'] = $variation_ID;
                }
            }
        }

        //The product is variable and doesn't have a default variation, so it can't be added to the cart
        if (!empty($variations) && $data['default_variation'] == 0) {
            $data['add_to_cart'] = false;
        }

        if ($data['add_to_cart']) {
            switch (get_class($product)) {
                case 'WC_Product_External':
                case 'WC_Product_Grouped':
                    $data['add_to_cart'] = false;
                    break;
            }
        }

        return wp_send_json($data);
    }


    public static function woocommerce_ajax_add_to_cart()
    {

        $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($_POST['product_id']));
        $quantity = empty($_POST['quantity']) ? 1 : wc_stock_amount($_POST['quantity']);
        $variation_id = absint($_POST['variation_id']);
        $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity);
        $product_status = get_post_status($product_id);

        if ($passed_validation && WC()->cart->add_to_cart($product_id, $quantity, $variation_id) && 'publish' === $product_status) {
            do_action('woocommerce_ajax_added_to_cart', $product_id);

            if ('yes' === get_option('woocommerce_cart_redirect_after_add')) {
                wc_add_to_cart_message(array($product_id => $quantity), true);
            }

            \WC_AJAX::get_refreshed_fragments();
        } else {
            $data = array(
                'error' => true,
                'product_url' => apply_filters('woocommerce_cart_redirect_after_error', get_permalink($product_id), $product_id)
            );

            echo wp_send_json($data);
        }

        wp_die();
    }
}
