<?php

use Doofinder\WP\Endpoints;
use Doofinder\WP\Settings;
use Doofinder\WP\Thumbnail;

/**
 * Class Endpoint_Product
 *
 * This class defines various methods for handling custom product endpoints.
 */
class Endpoint_Product
{
    const PER_PAGE = 100;
    const CONTEXT  = "doofinder/v1";
    const ENDPOINT = "/products";

    /**
     * Initialize the custom product endpoint.
     *
     * @return void
     */
    public static function initialize(){
        add_action('rest_api_init', function () {
            register_rest_route(self::CONTEXT, self::ENDPOINT, array(
                'methods'  => 'GET',
                'callback' => array(Endpoint_Product::class, 'custom_product_endpoint'),
            ));
        });
    }

    /**
     * Custom product endpoint callback.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing modified data.
     */
    public static function custom_product_endpoint($request) {

        Endpoints::CheckSecureToken();

        // Get the 'fields' parameter from the request
        $fields_param = $request->get_param('fields');
        $fields       = !empty($fields_param) ? explode(',', $fields_param) : array();
        $per_page     = $request->get_param('per_page') ?? self::PER_PAGE;
        $page         = $request->get_param('page') ?? 1;
        $lang         = $request->get_param('lang') ?? "";

        // Retrieve the original product data
        $products          = self::get_products($per_page, $page, $lang, $fields);
        $custom_attr       = Settings::get_custom_attributes();
        $modified_products = array();

        // Process and filter product data
        if (!empty($products)) {

            // Include variants if requested
            $products = self::get_variations($products);

            foreach ($products as $product_data) {
                // Filter fields
                $filtered_product_data = !empty($fields) ? array_intersect_key($product_data, array_flip($fields)) : $product_data;
                if (in_array('categories', $fields) && isset($product_data["categories"])) {
                    $filtered_product_data['categories'] = self::get_category_path($product_data["categories"]);
                }
                //If shop has custom attributes
                if (count($custom_attr) > 0) {
                    $filtered_product_data = array_merge($filtered_product_data, self::get_custom_attributes($product_data["id"]));
                    //unset($filtered_product_data["attributes"]);
                }
                // Include images if requested
                if (in_array('image_link', $fields)) {
                    $filtered_product_data = self::clear_images_fields($filtered_product_data);
                }
                $filtered_product_data = self::format_prices($filtered_product_data);

                if (in_array('stock_status', $fields)) {
                    $filtered_product_data = self::check_availability($filtered_product_data);
                }

                if (in_array('description', $fields)) {
                    $filtered_product_data["description"] = self::process_content($filtered_product_data["description"]);
                }
                if (in_array('short_description', $fields)) {
                    $filtered_product_data["short_description"] = self::process_content($filtered_product_data["short_description"]);
                }
                if (in_array('tags', $fields)) {
                    $filtered_product_data["tags"] = self::get_tag_names($filtered_product_data["tags"]);
                }

                $filtered_product_data = self::clean_fields($filtered_product_data);
                $modified_products[]   = $filtered_product_data;
            }
            // Cascade variants to their parent products
            $modified_products = self::cascade_variants($modified_products);
        }
        // Return the modified product data as a response
        return new WP_REST_Response($modified_products);
    }

    /**
    * Retrieves an array of names from a given array.
    *
    * @param array $array The input array containing the elements.
    *
    * @return array An array containing only the names of the elements.
    */
    private static function get_tag_names($array) {
        $names = array();
        foreach ($array as $element) {
            $names[] = self::process_content($element['name']);
        }
        return $names;
    }

    /**
     * Process content by decoding HTML entities, stripping HTML tags, and replacing sequences of whitespace characters.
     *
     * @param string $content The content to process, including HTML markup.
     *
     * @return string The processed content with HTML entities decoded, HTML tags removed, and whitespace sequences replaced with a single space.
    */
    private static function process_content($content) {
        $content = html_entity_decode(strip_tags($content));
        $content = preg_replace('/[ \t\r\n]+/', ' ', $content);

        return trim($content);
    }

    /**
     * Retrieve a list of products with pagination.
     *
     * @param int $per_page The number of products per page.
     * @param int $page     The current page number.
     * @param int $lang     Language.
     * @param int $fields   Fields API we want to order
     * @return array|null   An array of product data or null on failure.
     */
    private static function get_products($per_page, $page, $lang, $fields){
        // Retrieve the original product data
        $request = new WP_REST_Request('GET', '/wc/v3/products');
        $request->set_query_params(array(
            'page'     => $page,
            'per_page' => $per_page,
            'lang'     => $lang,
            '_fields'  => $fields
        ));
        $original_response = rest_do_request($request);
        return $original_response->data;
    }

    /**
    * Format prices of product
    *
    * @param array $product The product array to format prices.
    * @return array $product with formatted prices
    */
   private static function format_prices($product)
   {
        $product["price"]         = self::get_price($product["id"]);
        $product["regular_price"] = self::get_regular_price($product["id"]);
        $product["sale_price"]    = self::get_sale_price($product["id"]);

        return $product;
    }

    /**
     * Returns the raw price for the given product.
     *
     * @param array $product The product we want to add the field
     * @param string $price_name The price name. By default 'price'
     * @return void
     */
    private static function get_raw_price($id, $price_name = 'price')
    {
        $wc_product = wc_get_product($id);
        $fn_name = "get_$price_name";
        if (is_a($wc_product, 'WC_Product') && method_exists($wc_product, $fn_name)) {
            $price = $wc_product->$fn_name();
            $raw_price =  $price_name === "sale_price" && $price === "" ? "" : self::get_raw_real_price($price, $wc_product);
            //If price is equal to 0, return an empty string
            $raw_price = (0 == $raw_price) ? "" : $raw_price;
            return $raw_price;
        }
    }

    /**
     * Returns the raw price for the given product with taxes or witouht taxes depends the tax display.
     *
     * @param string $product Type of price we want
     * @param array WooCommerce Product $product Select product
     * @return void
     */
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
     * Get the raw price
     *
     * @param integer $id Product ID to get field.
     * @return float The raw price including or excluding taxes (defined in WC settings).
     */
    private static function get_price($id)
    {
        return  self::get_raw_price($id);
    }

    /**
     * Get the raw sale price
     *
     * @param integer $id Product ID to get field.
     * @return float The raw sale price including or excluding taxes (defined in WC settings).
     */
    private static function get_sale_price($id)
    {
        return  self::get_raw_price($id, 'sale_price');
    }

    /**
     * Get the raw regular price
     *
     * @param integer $id Product ID to get field.
     * @return float The raw regular price including or excluding taxes (defined in WC settings).
     */
    private static function get_regular_price($id)
    {
        return  self::get_raw_price($id, 'regular_price');
    }

    /**
     * Returns the image link for a given product.
     * If the product is a variation and doesn't have an image, return the parent image link
     *
     * @param array $id Product ID selected
     * @return string The image link
     */
    public static function get_image_link($id)
    {
        $post = get_post($id);
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
     * Field names to exchange
     *
     * @param array $product The product array to process.
     * @return array $product without fields excluded
     */
    private static function clean_fields($product){
        $product["title"] = $product["name"];
        $product["link"] = $product["permalink"];

        unset($product["name"]);
        unset($product["permalink"]);

        return $product;
    }

    /**
     * Check availability product
     *
     * @param array $product The product array to process.
     * @return array $product with availability string type (in stock / out of stock)
     */
    private static function check_availability($product){
        if($product["purchasable"] && ($product["stock_status"] == "instock" || $product["stock_status"] == "onbackorder")){
            $product["availability"] = "in stock";
        }
        else{
            $product["availability"] = "out of stock";
        }
        return $product;
    }

    /**
     * Clears image fields from a product array.
     *
     * @param array $product The product array to process.
     * @return array The product array with empty image fields removed.
     */
    private static function clear_images_fields($product){
        $product["image_link"] = self::get_image_link($product["id"]);
        unset($product["images"]);

        return $product;
    }

    /**
     * Cascade variants to their parent products.
     *
     * @param array $products The array of product data.
     * @return array The modified array of product data with variants cascaded.
     */
    private static function cascade_variants($products) {
        foreach ($products as $key => $product) {
            if (!empty($product["parent_id"])) {
                foreach ($products as $key2 => $product2) {
                    if ($product2["id"] == $product["parent_id"]) {
                        if (!isset($products[$key2]["variants"])) {
                            $products[$key2]["variants"] = array();
                        }
                        $products[$key2]["variants"][] = $products[$key];
                        unset($products[$key]);
                    }
                }
            }
        }
        return array_values($products);
    }

    /**
     * Get variations for variable products.
     *
     * @param array $products_data The array of product data.
     * @return array The modified array of product data with variations.
     */
    private static function get_variations($products_data){

        $products = array();

        foreach($products_data as $product){

            if($product["type"] == "variable"){

                $page            = 1;
                $variations_data = array();

                //variations pagination
                do{
                    $request = new WP_REST_Request('GET', '/wc/v3/products/'.$product["id"].'/variations');
                    $request->set_query_params(array(
                        'page'     => $page,
                        'per_page' => self::PER_PAGE,
                    ));
                    $variants_response = rest_do_request($request);
                    $variations_data   = array_merge($variations_data, $variants_response->data);
                    $page++;
                }
                while(count($variants_response->data) >= self::PER_PAGE);

                //Setting parent_id in variations
                foreach ($variations_data as &$variation) {
                    foreach ($product as $field => $value) {
                        if (!isset($variation[$field])) {
                            $variation[$field]      = $value;
                            $variation["parent_id"] = $product["id"];
                        }
                    }
                }
                //Setting df_variants_information when variation attribute = true
                if($product["parent_id"] == 0){
                    $attr_variation_true = self::get_df_variants_information($product);
                    $product["df_variants_information"] = $attr_variation_true;
                    $products[]                         = $product;
                }
                $products = array_merge($products, $variations_data);
            }
            else{
                $products[] = $product;
            }
        }
        return $products;
    }


    /**
     * Generate df_variants_information node response
     *
     * @param array $product
     * @return array df_variants_information
     */
    private static function get_df_variants_information($product){

        $product_attributes = array_keys(wc_get_product($product["id"])->get_attributes());

        array_walk($product_attributes, function (&$element) {
            $element = str_replace(['pa_', 'wc_'], '', $element);
        });
        $attr_variation_true = [];
        foreach($product["attributes"] as $p_attr){
            if($p_attr["variation"] && in_array(strtolower($p_attr["name"]), $product_attributes)){
                $attr_variation_true[] = strtolower($p_attr["name"]);
            }
        }
        return $attr_variation_true;
    }

    /**
     * Get custom attributes for a product.
     *
     * @param int $product_id The ID of the product.
     * @return array The custom attributes for the product.
     */
    private static function get_custom_attributes($product_id){

        $doofinder_attributes = Settings::get_custom_attributes();
        $product_attributes   = wc_get_product($product_id)->get_attributes();
        $custom_attributes    = array();

        foreach ($product_attributes as $attribute_name => $attribute_data) {
            $attribute_slug = str_replace("pa_", "", $attribute_name);
            $found_key      = array_search($attribute_slug, array_column($doofinder_attributes, 'field'));

            if ($found_key){
                $attribute_options = $attribute_data['options'] ?? array($attribute_data);

                foreach ($attribute_options as $option) {
                    $term = get_term_by('name', $option, $attribute_name);
                    if(empty($term)){
                        $term = get_term_by('id', $option, $attribute_name);
                    }
                    if(empty($term)){
                        $custom_attributes[$attribute_slug][] = $option;
                    }
                    else if ($term && !is_wp_error($term)) {
                        $custom_attributes[$attribute_slug][] = $term->name;
                    }
                }
            }
        }
        return $custom_attributes;
    }

    /**
     * Get the category path for a product.
     *
     * @param array $category_ids The array of category IDs.
     * @return array The array of category paths.
     */
    private static function get_category_path($category_ids) {

        $category_paths = array();

        foreach ($category_ids as $category_id) {
            $category_path = self::get_category_hierarchy($category_id["id"]);
            if (!empty($category_path)) {
                $category_paths[] = self::process_content($category_path);
            }
        }
        return $category_paths;
    }

    /**
     * Get the hierarchy of a category.
     *
     * @param int $category_id The ID of the category.
     * @return string The category hierarchy.
     */
    private static function get_category_hierarchy($category_id) {
        $category = get_term($category_id, 'product_cat');
        if (is_wp_error($category)) {
            return '';
        }
        $category_path = $category->name;
        $parent_id     = $category->parent;

        while ($parent_id !== 0) {
            $parent_category = get_term($parent_id, 'product_cat');
            if (!is_wp_error($parent_category)) {
                $category_path = $parent_category->name . ' > ' . $category_path;
                $parent_id     = $parent_category->parent;
            }
        }
        return $category_path;
    }
}
?>
