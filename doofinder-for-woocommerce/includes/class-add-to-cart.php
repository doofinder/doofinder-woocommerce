<?php

namespace Doofinder\WC;

use Doofinder\GuzzleHttp\Promise\Is;
use Doofinder\WC\Settings\Settings;

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
        $this->enqueue_script();

        add_action('wp_ajax_doofinder_ajax_add_to_cart',  array(__CLASS__, 'doofinder_ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_doofinder_ajax_add_to_cart',  array(__CLASS__, 'doofinder_ajax_add_to_cart'));
        add_action('wp_ajax_doofinder_get_product_info',  array(__CLASS__, 'product_info'));
        add_action('wp_ajax_nopriv_doofinder_get_product_info',  array(__CLASS__, 'product_info'));
    }

    /**
     * Returns the product info for a given id
     */
    public static function product_info()
    {
        $post_id = $_REQUEST['id'] ?? NULL;
        if (empty($post_id)) {
            return '';
        }

        $product = wc_get_product($post_id);
        if (empty($product)) {
            return '';
        }

        $variation_id = 0;

        if (is_a($product, 'WC_Product_Variation')) {
            $post_id =  $product->get_parent_id();
            $variation_id =  $product->get_id();
        }

        $data = [
            "product" => $post_id,
            "product_url" => get_the_permalink($post_id),
            "variation" => $variation_id,
            "add_to_cart" => true
        ];

        if ($data['add_to_cart']) {
            switch (get_class($product)) {
                case 'WC_Product_External':
                case 'WC_Product_Variable':
                case 'WC_Product_Grouped':
                    $data['add_to_cart'] = false;
                    break;
            }
        }

        return wp_send_json($data);
    }


    public static function doofinder_ajax_add_to_cart()
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

    /**
     * Enqueue plugin styles and scripts.
     *
     * @since 1.5.23
     */
    public function enqueue_script()
    {
        add_action('wp_enqueue_scripts', function () {
            if ('yes' === Settings::get('layer', 'enabled')) {
                wp_enqueue_script(
                    'doofinder-add-to-cart',
                    Doofinder_For_WooCommerce::plugin_url() . 'assets/js/df-add-to-cart.js',
                    ['jquery'],
                    false,
                    true
                );
                wp_localize_script('doofinder-add-to-cart', 'df_cart', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'item_info_endpoint' =>  get_site_url(null, '/wp-json/doofinder-for-wc/v1/product-info/')
                ]);
            }
        });
    }
}
