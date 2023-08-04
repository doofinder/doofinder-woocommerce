<?php

namespace Doofinder\WP;

class Tax_Prices_Handler {

    public static function apply_correct_taxes_for_product_prices_in_rest_api(): void {
        add_filter( 'woocommerce_product_get_regular_price', array( __CLASS__, 'filter_woocommerce_prices_with_taxes' ), 99, 2 );
        add_filter( 'woocommerce_product_get_sale_price', array( __CLASS__, 'filter_woocommerce_prices_with_taxes' ), 99, 2 );
        add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_woocommerce_prices_with_taxes' ), 99, 2 );
        add_filter( 'woocommerce_product_variation_get_regular_price', array( __CLASS__, 'filter_woocommerce_prices_with_taxes' ), 99, 2 );
        add_filter( 'woocommerce_product_variation_get_sale_price', array( __CLASS__, 'filter_woocommerce_prices_with_taxes' ), 99, 2 );
        add_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'filter_woocommerce_prices_with_taxes' ), 99, 2 );
    }

    public static function filter_woocommerce_prices_with_taxes( $price, $product ) {
        // Only affects the prices shown on the REST API requests
        if ( ! function_exists( 'WC' ) || ! WC()->is_rest_api_request() ) {
            return $price;
        }

        // Just in case the sale price has not been defined, we're going to use the price instead
        if ( empty( $price ) ) {
            return $product->price;
        }

        $raw_real_price = self::get_raw_real_price( $price, $product );
        // Idea extracted directly from the original WC function wc_price()
        $args = array();
        $args = apply_filters(
            'wc_price_args',
            wp_parse_args(
                $args,
                array(
                    'ex_tax_label'       => false,
                    'currency'           => '',
                    'decimal_separator'  => wc_get_price_decimal_separator(),
                    'thousand_separator' => wc_get_price_thousand_separator(),
                    'decimals'           => wc_get_price_decimals(),
                    'price_format'       => get_woocommerce_price_format(),
                )
            )
        );
        $price = apply_filters( 'formatted_woocommerce_price', number_format( $raw_real_price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'] ), $price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'], $price );
        if ( apply_filters( 'woocommerce_price_trim_zeros', false ) && $args['decimals'] > 0 ) {
            $price = wc_trim_zeros( $price );
        }
        return $price;
    } 

    private static function get_raw_real_price( $price, $product ) {
        $woocommerce_tax_display_shop = get_option( 'woocommerce_tax_display_shop', 'incl' );
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
}
