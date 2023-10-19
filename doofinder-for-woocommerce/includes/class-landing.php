<?php

namespace Doofinder\WP;

use Doofinder\WP\Api\Landing_Api;
use Doofinder\WP\Multilanguage\Language_Plugin;
use Doofinder\WP\Multilanguage\Multilanguage;
use Doofinder\WP\Settings;
use Doofinder\WP\Log;

class Landing
{
    public $products = [];

    public $landing_data = [];

    /**
     * Instance of the class handling the multilanguage.
     *
     * @var Language_Plugin
     */
    public $language;


    /**
     * List with all languages
     *
     * @var array
     */
    public $languages;

    /**
     * Language selected for this operation.
     *
     * @var string
     */
    private $current_language;

    /**
     * Contains information whether we should process all languages at once
     *
     * @var bool
     */
    public $process_all_languages = true;

    /**
     * Instance of a class used to log to a file.
     *
     * @var Log
     */
    private $log;

    /**
     * Instance of a class used to call api.
     *
     * @var Landing_Api
     */
    private $landing_api;

    public function __construct()
    {

        $this->log                          = new Log('landing.log');
        $this->language                     = Multilanguage::instance();
        $this->current_language             = $this->language->get_current_language();
        $this->landing_api                  = new Landing_Api();
    }

    public static function init()
    {        
        add_rewrite_tag( '%df-landing%', '([^/]+)' );
        add_rewrite_rule(
            '^df/(.+)/?$', 
            'index.php?df-landing=$matches[1]', 
            'top'
        );
        
        add_action( 'template_redirect', function() {
            global $wp_query;
          
            $model = $wp_query->get( 'df-landing' );
          
            if ( ! empty( $model ) ) {
                include Doofinder_For_WordPress::plugin_path() . '/views/landing.php';
                exit;
            }
          } );
    }

    /**
     * 
     *
     * @param string  $hashid
     * @param string  $slug
     *
     * @return mixed
     */
    public function get_landing_info($hashid, $slug)
    {
        $hashid = isset($hashid) ? Settings::get_search_engine_hash($this->current_language) : $hashid;

        $error_not_set = ['error' => "The page is not well constructed. The hashid or slug is missing."];

        $this->landing_data = self::have_params($hashid, $slug) ? $this->landing_api->get_landing_info($hashid, $slug) : $error_not_set ;

        if(isset($this->landing_data['data']))
            $this->build_blocks();

        return $this->landing_data;

    }

    /**
     * Landing content html
     *
     *
     */
    public function get_landing_html()
    {
        ob_start();
    ?>

        <div id="primary" class="df-content-area content-area">
                <header class="woocommerce-products-header df-products-header">
                    <h1 class="woocommerce-products-header__title page-title"><?php echo $this->landing_data['data']['title']; ?></h1>
                    <p class="df-products-description"><?php echo $this->landing_data['data']['description']; ?></p>
                </header>
                    <?php

                    foreach ($this->landing_data['data']['blocks'] as &$block) {
                         echo $block['above'];
                         echo $this->render_products($block['products']);
                         echo $block['below'] ;
                    }
                    ?>

        </div>
    <?php
        $html = ob_get_clean();
        return $html;
    }
    
    /**
     * Landing error html
     *
     * @param string  $error
     *
     * @return string
     */
    public function get_error_html($error)
    {
        ob_start();
    ?>
        <div id="primary" class="df-content-area content-area">
             <main id="main" class="site-main">
                <div class="df-error-col content">
                    <h3> We couldnt retrieve the API data. Please try again later. </h3>
                    <p>
                        <?php echo $error; ?>
                    </p>
                </div>
            </div>
        </div>
    <?php
        $html = ob_get_clean();
        return $html;
    }

    /**
     * 
     *
     * @param string  $hashid
     * @param string  $slug
     *
     * @return bool
     */
    public function have_params($hashid, $slug)
    {
        return !empty($hashid) && !empty($slug) ? true : false;
    }

    public function build_blocks() {
        foreach ($this->landing_data['data']['blocks'] as &$block) {
            $product_ids = $this->landing_api->get_custom_result($block['query']);
            if (is_array($product_ids) && !empty($product_ids))
                $block['products'] = $product_ids; 
            
        }
    }

    /**
     * Renders product listings.
     *
     * @param array $products_ids
     */
    private function render_products($products_ids) {
        if (!function_exists('wc_get_products')) return;

        // Definimos las variables de paginación y filtro
        $paged = (get_query_var('paged')) ? absint(get_query_var('paged')) : 1;
        $ordering = WC()->query->get_catalog_ordering_args();
        $explode = explode(' ', $ordering['orderby']);
        $ordering['orderby'] = array_shift($explode);
        $ordering['orderby'] = stristr($ordering['orderby'], 'price') ? 'meta_value_num' : $ordering['orderby'];
        $products_per_page = apply_filters('loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page());
        
        // Construimos la consulta usando diferentes argumentos, solo necesitaremos los Ids de los productos
        $custom_products = wc_get_products(array(
            'status' => 'publish',
            'visibility' => 'visible',
            'limit' => $products_per_page,
            'page' => $paged,
            'paginate' => true,
            'return' => $products_ids,
            'orderby' => $ordering['orderby'],
            'order' => $ordering['order'],
        ));

        // Establecemos las propiedades globales para el bucle
        wc_set_loop_prop('current_page', $paged);
        wc_set_loop_prop('is_paginated', true);
        wc_set_loop_prop('page_template', get_page_template_slug());
        wc_set_loop_prop('per_page', $products_per_page);
        wc_set_loop_prop('total', $custom_products->total);
        wc_set_loop_prop('total_pages', $custom_products->max_num_pages);

        // Construcción del bucle de WooCommerce teniendo en cuenta los hooks
        if ($custom_products) {
            do_action('woocommerce_before_shop_loop');
            woocommerce_product_loop_start();

            // Recorremos todos los Ids obtenidos
            foreach ($custom_products->products as $item) {
                // $product = wc_get_product($item);

                // // Mostramos el producto usando la plantilla por defecto de WC
                // wc_get_template_part('archive', 'product', '', array('product' => $product));

                wc_setup_product_data($item);

                // Mostramos el producto usando la plantilla por defecto de WC
                wc_get_template_part('content', 'product');
            }
            wp_reset_postdata();
            woocommerce_product_loop_end();
            do_action('woocommerce_after_shop_loop');
        } else {
            do_action('woocommerce_no_products_found');
        }
        do_action('woocommerce_after_main_content');
        
        
    }
}
