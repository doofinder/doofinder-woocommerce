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

    /**
     * Initializes the custom functionality of Doofinder for WordPress, including setting up rewrite rules and including custom templates.
     * 
    */
    public static function init() {
        // Add a custom rewrite tag
        add_rewrite_tag( '%df-landing%', '([^/]+)' );

        // Add a custom rewrite rule
        add_rewrite_rule(
            '^df/(.+)/?$', // The rewrite pattern
            'index.php?df-landing=$matches[1]', // The corresponding query
            'top' // Rule priority
        );

        // Add an action to redirect to the custom template when the rewrite rule is matched
        add_action( 'template_redirect', function() {
            global $wp_query;

            // Get the value of 'df-landing' from the query
            $model = $wp_query->get( 'df-landing' );

            // If 'df-landing' is not empty, include the custom template and exit the normal flow
            if ( ! empty( $model ) ) {
                // Include the custom template
                include Doofinder_For_WordPress::plugin_path() . '/views/landing.php';
                exit;
            }
        } );
        flush_rewrite_rules();
    }


    /**
     * Sets the meta data for a landing page, including meta title, meta description, and indexing policies.
     *
     * @param string $meta_title The meta title to be configured for the landing page.
     * @param string $meta_description The meta description to be configured for the landing page.
     * @param bool $index Determines the indexing policies for the landing page. If false, the page will be set to 'noindex, nofollow'.
     */
    public static function set_meta_data($meta_title, $meta_description, $index) {
        // Add classes to the body element
        add_filter('body_class', function($classes) {
            $classes[] = 'archive';
            $classes[] = 'woocommerce';
            return $classes;
        });

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
     * Create a redirect based on the desired language, slug, and hashid.
     *
     * @param string $slug The slug for the redirect.
     * @param string $hashid The hashid associated with the desired language.
     *
     * @return void
     */
    public function create_redirect($slug, $hashid) {
        $languages = $this->language->get_languages();
        $desired_lang = $this->get_desired_language($languages, $hashid);
        if ( !empty($desired_lang) && !empty($slug) && !empty($hashid) )  {
            $home_url = $this->language->get_home_url($desired_lang);
            $formated_url = $this->formated_url($home_url, $slug);
            $this->redirect($formated_url);
        }

    }

    /**
     * Determine the desired language based on the provided hashid and a list of languages.
     *
     * @param array $languages An array of available languages with their properties.
     * @param string $hashid The hashid associated with the desired language.
     *
     * @return string The code of the desired language, or an empty string if not found.
     */
    private function get_desired_language($languages, $hashid) {
        $desired_lang = '';
        foreach ($languages as $language) {
            $hashid_by_lang = Settings::get_search_engine_hash($language['code']);
            if($hashid_by_lang === $hashid) {
                $desired_lang = $language['code'];
                break;
            }
        }

        return $desired_lang;
    }

    /**
     * Format the URL by appending the slug to the home URL while considering different URL formats.
     *
     * @param string $home_url The home URL for the desired language.
     * @param string $slug The slug to be appended to the URL.
     *
     * @return string The formatted URL with the slug included.
     */
    private function formated_url($home_url, $slug) {
        $slug = "df/{$slug}";
        if(strpos($home_url, '?lang=') !== false) {
            $formated_url = str_replace('?lang=', "{$slug}/?lang=", $home_url);
        } else {
            $formated_url = "{$home_url}{$slug}";
        }
        return $formated_url;
    }

    /**
     * Redirect to a given formatted URL.
     *
     * @param string $formated_url The URL to which the redirection will occur.
     */
    private function redirect($formated_url) {
        header("Location: $formated_url");
        exit;
    }

    /**
     * Retrieves landing page information based on the provided hashid and slug.
     *
     * @param string $hashid The unique identifier (hashid).
     * @param string $slug The slug of the landing page.
     *
     * @return array An array containing landing page information or an error message if the page is not well-constructed.
     */
    public function get_landing_info($hashid, $slug) {
        // Determine the hashid to use based on the provided parameter or settings
        $hashid = !empty($hashid) ? $hashid : Settings::get_search_engine_hash($this->current_language);

        // Define an error message for cases where required parameters are missing
        $error_not_set = ['error' => "The page is not well constructed. The hashid or slug is missing."];

        // Check if the necessary parameters are available or return the error message
        $this->landing_data = self::have_params($hashid, $slug) ? $this->landing_api->get_landing_info($hashid, $slug) : $error_not_set;

        // If landing page data is available, build its blocks
        if (isset($this->landing_data['data']))
            $this->build_blocks($hashid);

        // Return the landing page information or error message
        return $this->landing_data;
    }


    /**
     * Generates the HTML content for a landing page based on the provided landing slug.
     *
     * @param string $landing_slug The slug of the landing page.
     *
     * @return string The HTML content for the landing page.
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
     * Generates HTML content for displaying an error message on the landing page.
     *
     * @param string $error The error message to display.
     *
     * @return string The HTML content for the error message.
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
     * Checks if both the hashid and slug parameters are not empty.
     *
     * @param string $hashid The hashid parameter.
     * @param string $slug The slug parameter.
     *
     * @return bool True if both parameters are not empty, otherwise false.
     */
    private function have_params($hashid, $slug)
    {
        return !empty($hashid) && !empty($slug) ? true : false;
    }

    /**
     * Builds product blocks within the landing page using custom queries and retrieves product IDs or an error message.
     *
     * @param string $hashid The hashid parameter used for custom queries.
     */
    private function build_blocks($hashid) {
        foreach ($this->landing_data['data']['blocks'] as &$block) {
            // Get custom search results based on the provided query
            $products = $this->landing_api->get_custom_result($hashid, $block['query']);
    
            if (isset($products['results'])) {
                // Extract product IDs from the search results
                $products_ids = $this->get_product_ids($products['results']);
    
                if (is_array($products_ids) && !empty($products_ids))
                    $block['products'] = $products_ids;

            } else {
                // If no results were found, store the original products array or an error message
                $block['products'] = isset($products['error']) ? $products : $products['results'];
            }
        }
    }
    /**
     * Extracts product IDs from an array of search results.
     *
     * @param array $results An array of search results containing product data.
     *
     * @return array An array of product IDs.
     */
    private function get_product_ids($results) {
        return array_map(function($product) {
            return strval($product['id']);
        }, $results);
    }

    /**
     * Renders a list of products based on provided product IDs or an error message.
     *
     * @param array $products_ids An array of product IDs or an error message.
     *
     */
    private function render_products($products_ids) {
        if (isset($products_ids['error']))
            echo 'Product ids could not be obtained in our request: ' . $products_ids['error'];

        $args = array(
            'post_type' => array('product', 'product_variation'),
            'post__in' => $products_ids,
            'posts_per_page' => 12
        );

        $query = new \WP_Query($args);

        if ($query->have_posts()) {
            woocommerce_product_loop_start();

            while ($query->have_posts()) {
                $query->the_post();

                wc_get_template_part('content', 'product');
            }

            woocommerce_product_loop_end();
        } else {
            echo 'No products were found for the list of ids we have obtained.';
        }

        wp_reset_postdata();
    }
}
