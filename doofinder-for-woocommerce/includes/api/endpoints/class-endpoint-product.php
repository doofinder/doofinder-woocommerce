<?php

ini_set('serialize_precision','-1');

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
    const ENDPOINT = "/product";
    const FIELDS = [
        "attributes",
        "average_rating",
        "best_price",
        "catalog_visibility",
        "categories",
        "description",
        "df_group_leader",
        "df_variants_information",
        "group_id",
        "id",
        "image_link",
        "link",
        "meta_data",
        "name",
        "parent_id",
        "permalink",
        "price",
        "purchasable",
        "regular_price",
        "sale_price",
        "short_description",
        "sku",
        "slug",
        "stock_status",
        "tags",
        "type"
      ];

    /**
     * Initialize the custom product endpoint.
     *
     * @return void
     */
    public static function initialize(){
        add_action('rest_api_init', function () {
            register_rest_route(self::CONTEXT, self::ENDPOINT, array(
                'methods'  => 'GET',
                'callback' => array(self::class, 'custom_product_endpoint'),
            ));
        });
    }

    /**
     * Custom product endpoint callback.
     *
     * @param WP_REST_Request $request The REST request object.
     * @param array $config_request Array config for internal requests.
     * @return WP_REST_Response Response containing modified data.
     */
    public static function custom_product_endpoint($request, $config_request = false) {

        if(!$config_request){
            Endpoints::CheckSecureToken();

            // Get the 'fields' parameter from the request
            $fields = $request->get_param('fields') == "all" ? [] : self::get_fields();

            $config_request = [
                'per_page' => $request->get_param('per_page') ?? self::PER_PAGE,
                'page'     => $request->get_param('page') ?? 1,
                'lang'     => $request->get_param('lang') ?? "",
                'ids'      => $request->get_param('ids') ?? "",
                'fields'   => $fields
            ];
        }
        else{
            $fields_param = $config_request['fields'] ?? "";
            $fields       = !empty($fields_param) ? explode(',', $fields_param) : [];
        }


        // Retrieve the original product data
        $products          = self::get_products($config_request);
        $custom_attr       = Settings::get_custom_attributes();
        $modified_products = array();

        // Process and filter product data
        if (!empty($products)) {

            // Include variants if requested
            $products = self::get_variations($products, $fields);

            foreach ($products as $product_data) {
                // Filter fields
                $filtered_product_data = !empty($fields) ? array_intersect_key($product_data, array_flip($fields)) : $product_data;

                $filtered_product_data = self::get_categories($filtered_product_data, $fields);
                $filtered_product_data = self::merge_custom_attributes($filtered_product_data, $custom_attr);
                $filtered_product_data = self::get_image_field($filtered_product_data, $fields);
                $filtered_product_data = self::format_prices($filtered_product_data);
                $filtered_product_data = self::check_stock_status($filtered_product_data, $fields);
                $filtered_product_data = self::get_description($filtered_product_data, $fields);
                $filtered_product_data = self::get_short_description($filtered_product_data, $fields);
                $filtered_product_data = self::get_tags($filtered_product_data, $fields);
                $filtered_product_data = self::get_meta_attributes($filtered_product_data, $custom_attr);
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
     * Get the array of fields.
     *
     * @return array The array of fields.
     */
    public static function get_fields() {
        return self::FIELDS;
    }

    /**
     * Get products data from our endpoint products
     *
     * @param array  $ids ID product we want to get data
     * @return array  Array Products
     */
    public static function get_data($ids){

        $request_params = array(
            "ids"      => implode(",", $ids),
            "fields"   => implode(",", self::get_fields())
        );

        $items = self::custom_product_endpoint(false, $request_params)->data;

        array_walk($items, function (&$product) {
            unset($product['_links']);
        });

        return $items;
    }

    /**
     * Get categories in the data.
     *
     * @param array $data   The data to process.
     * @param array $fields The list of fields being processed.
     * @return array The processed data.
     */
    private static function get_categories($data, $fields) {
        if (isset($data["categories"])) {
            $data['categories'] = self::get_category_path($data["categories"]);
        }
        return $data;
    }

    /**
     * Merge custom attributes into the data.
     *
     * @param array $data        The data to merge into.
     * @param array $custom_attr The custom attributes to merge.
     * @return array The merged data.
     */
    private static function merge_custom_attributes($data, $custom_attr) {
        if (count($custom_attr) > 0) {
            return array_merge($data, self::get_custom_attributes($data["id"], $custom_attr));
        }
        return $data;
    }

    /**
     * Get custom meta field data from product
     *
     * @param array $data        The data to merge into.
     * @param array $custom_attr The custom attributes to merge.
     * @return array The merged data.
     */
    private static function get_meta_attributes($data, $custom_attr) {
        foreach ($custom_attr as $attr) {
            if ($attr["type"] == "metafield") {
                foreach ($data["meta_data"] as $meta) {
                    $meta_data = $meta->get_data();
                    if ($meta_data["key"] == $attr["field"]) {
                        $data[$attr["field"]] = $meta_data["value"] ?? "";
                    }
                }
            }
        }
        unset($data["meta_data"]);
        return $data;
    }

    /**
     * Get the image link in the data.
     *
     * @param array $data   The data to process.
     * @param array $fields The list of fields being processed.
     * @return array The processed data.
     */
    private static function get_image_field($data, $fields) {
        return self::clear_images_fields($data);
    }

    /**
     * Check the stock status in the data.
     *
     * @param array $data   The data to check.
     * @param array $fields The list of fields being processed.
     * @return array The processed data.
     */
    private static function check_stock_status($data, $fields) {
        return self::check_availability($data);
    }

    /**
     * Process the description field in the data.
     *
     * @param array $data   The data to process.
     * @param array $fields The list of fields being processed.
     * @return array The processed data.
     */
    private static function get_description($data, $fields) {
        $data['description'] = self::process_content($data['description']);
        return $data;
    }

    /**
     * Process the short description field in the data.
     *
     * @param array $data   The data to process.
     * @param array $fields The list of fields being processed.
     * @return array The processed data.
     */
    private static function get_short_description($data, $fields) {
        $data['short_description'] = self::process_content($data['short_description']);
        return $data;
    }

    /**
     * Get tags in the data.
     *
     * @param array $data   The data to process.
     * @param array $fields The list of fields being processed.
     * @return array The processed data.
     */
    private static function get_tags($data, $fields) {
        $data['tags'] = self::get_tag_names($data['tags']);
        return $data;
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
     * @param int $config   Config request for get products
     * @return array|null   An array of product data or null on failure.
     */
    private static function get_products($config){
        // Retrieve the original product data
        $request = new WP_REST_Request('GET', '/wc/v3/products');
        $request->set_query_params(array(
            'page'     => $config["page"] ?? self::PER_PAGE,
            'per_page' => $config["per_page"],
            'lang'     => $config["lang"],
            '_fields'  => $config["fields"],
            'include'  => $config["ids"],
            "orderby"  => "id",
            "order"    => "asc"
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
        $wc_product = wc_get_product($product["id"]);

        $product["price"]         = self::get_price($product["id"], $wc_product);
        $product["regular_price"] = self::get_regular_price($product["id"], $wc_product);
        $product["sale_price"]    = self::get_sale_price($product["id"], $wc_product);

        return $product;
    }

    /**
     * Returns the raw price for the given product.
     *
     * @param array WooCommerce Product
     * @param string $price_name The price name. By default 'price'
     * @return void
     */
    private static function get_raw_price($wc_product, $price_name = 'price')
    {
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
    private static function get_price($id, $product)
    {
        return  self::get_raw_price($product);
    }

    /**
     * Get the raw sale price
     *
     * @param integer $id Product ID to get field.
     * @return float The raw sale price including or excluding taxes (defined in WC settings).
     */
    private static function get_sale_price($id, $product)
    {
        return  self::get_raw_price($product, 'sale_price');
    }

    /**
     * Get the raw regular price
     *
     * @param integer $id Product ID to get field.
     * @return float The raw regular price including or excluding taxes (defined in WC settings).
     */
    private static function get_regular_price($id, $product)
    {
        return  self::get_raw_price($product, 'regular_price');
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
     * Field names to exchange and clean unused or empty fields
     *
     * @param array $product The product array to process.
     * @return array $product without fields excluded
     */
    private static function clean_fields($product){
        $product["title"] = $product["name"];
        $product["link"] = $product["permalink"];

        unset($product["attributes"]);
        unset($product["name"]);
        unset($product["permalink"]);

        if(empty($product["parent_id"])){
            unset($product["parent_id"]);
        }

        $product = array_filter($product, function ($value) {
            return $value !== '' && $value !== null;
        });

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
        unset($product["stock_status"]);
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
            $type       = $product["type"];
            $attributes = $product["attributes"];

            unset($product["type"]);

            if($type == "variable"){
                $variations_data = self::processVariations($product);

                //Setting df_variants_information when variation attribute = true
                $attr_variation_true                = self::get_df_variants_information($product, $attributes);
                $product["df_variants_information"] = $attr_variation_true;
                $products[]                         = $product;
                $products = array_merge($products, $variations_data);
            }
            else{
                $products[] = $product;
            }
        }
        return $products;
    }

    /**
     * Process variations for a variable product.
     *
     * This function retrieves variations for a variable product, merges them with the product data,
     * and sets the "parent_id" field.
     *
     * @param array $product The product data for a variable product.
     * @return array The processed array of variations for the variable product.
     */
    private static function processVariations($product) {
        $variations_data = self::request_variations($product["id"]);

        foreach ($variations_data as &$variation) {
            $variation = array_merge($product, $variation, ["parent_id" => $product["id"]]);
        }
        return $variations_data;
    }

    /**
     * Request variations for a given product ID.
     *
     * @param int $product_id The ID of the product.
     * @return array The variations data.
     */
    private static function request_variations($product_id) {

        $page            = 1;
        $variations_data = array();

        do {
            $request = new WP_REST_Request('GET', '/wc/v3/products/' . $product_id . '/variations');
            $request->set_query_params(array(
                'page'     => $page,
                'per_page' => self::PER_PAGE
            ));
            $variants_response = rest_do_request($request);
            $variations_data   = array_merge($variations_data, $variants_response->data);

            $page++;

        } while (count($variants_response->data) >= self::PER_PAGE);

        return $variations_data;
    }

    /**
     * Generate df_variants_information node response
     *
     * @param array $product
     * @param array $attributes
     * @return array df_variants_information
     */
    private static function get_df_variants_information($product, $attributes){

        $product_attributes = array_keys(wc_get_product($product["id"])->get_attributes());

        array_walk($product_attributes, function (&$element) {
            $element = str_replace(['pa_', 'wc_'], '', $element);
        });

        $variation_attributes = [];
        foreach($attributes as $p_attr){
            if($p_attr["variation"] && in_array(strtolower($p_attr["name"]), $product_attributes)){
                $variation_attributes[] = strtolower($p_attr["name"]);
            }
        }
        return $variation_attributes;
    }

    /**
     * Get custom attributes for a product.
     *
     * @param int $product_id The ID of the product.
     * @param array $custom_attr List of custom attributes.
     * @return array The custom attributes for the product.
     */
    private static function get_custom_attributes($product_id, $custom_attr){

        $product_attributes   = wc_get_product($product_id)->get_attributes();
        $custom_attributes    = array();

        foreach ($product_attributes as $attribute_name => $attribute_data) {
            $attribute_slug = str_replace("pa_", "", $attribute_name);
            $found_key      = array_search($attribute_slug, array_column($custom_attr, 'field'));

            if (is_integer($found_key)) {
                $attribute_options = is_string($attribute_data) ? [$attribute_data] : $attribute_data->get_slugs();

                foreach ($attribute_options as $option) {
                    $custom_attributes[$attribute_slug][] = $option;
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