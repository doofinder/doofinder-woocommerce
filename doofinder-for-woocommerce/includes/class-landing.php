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
     * @param string  $meta_title
     * @param string  $meta_description
     * @param string  $index
     *
     * @return mixed
     */
    public static function set_meta_data($meta_title, $meta_description, $index) 
    {                
        // Configure the page meta title
        add_filter('wp_title', function ($title) use ($meta_title) {
            return $meta_title;
        }, 10, 2);
        
        // Configure the meta description
        add_filter('document_title_parts', function ($title_parts) use ($meta_description) {
            $title_parts['description'] = $meta_description;
            return $title_parts;
        });
        
        // Configure indexing policies
        if ($index === false) {
            add_filter('wp_robots', function ($robots) {
                return 'noindex,nofollow';
            });
        }
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
            $this->build_blocks($hashid);

        return $this->landing_data;

    }

    /**
     * Landing content html
     *
     * @param string $landing_slug
     *
     */
    public function get_landing_html($landing_slug)
    {
        ob_start();
    ?>

        <section id="primary" class="df-content-area content-area">
                <header class="woocommerce-products-header df-products-header">
                    <h1 class="woocommerce-products-header__title page-title"><?php echo $this->landing_data['data']['title']; ?></h1>
                </header>
                    <?php

                    foreach ($this->landing_data['data']['blocks'] as &$block) {
                        ?>
                        <div class="df-landing-block df-landing-block-<?php echo $landing_slug; ?>">
                            <div class="df-above-block"><?php echo $block['above']; ?></div>
                            <div class="df-product-block"><?php echo $this->render_products($block['products']); ?></div>
                            <div class="df-below-block"><?php echo $block['below']; ?></div>
                         
                         </div>
                         <?php
                    }
                    ?>
        </section>
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

    public function build_blocks($hashid) {
        foreach ($this->landing_data['data']['blocks'] as &$block) {
            $products = $this->landing_api->get_custom_result($hashid, $block['query']);
            $products_ids = $this->get_product_ids($products['results']);

            if (is_array($products_ids) && !empty($products_ids))
                $block['products'] = $products_ids; 
            
        }
    }

    private function get_product_ids($results) {
        $products_ids = array();
        foreach ($results as $product) {
            $products_ids[] =  strval($product['id']);
        }
        return $products_ids;
    }

    /**
     * Renders product listings.
     *
     * @param array $products_ids
     * 
     */
    private function render_products($products_ids) {
        $ids = implode(',', $products_ids);
        echo do_shortcode('[products limit="4" columns="4" paginate="false" ids="' . $ids . '"]');
        
    }
}
