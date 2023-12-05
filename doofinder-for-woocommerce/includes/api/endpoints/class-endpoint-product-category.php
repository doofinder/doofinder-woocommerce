<?php

use Doofinder\WP\Endpoints;
use Doofinder\WP\Thumbnail;

/**
 * Class Endpoint_Product_Category
 *
 * This class defines various methods for handling item wordpress endpoints.
 */
class Endpoint_Product_Category
{
    const PER_PAGE = 100;
    const CONTEXT  = "doofinder/v1";
    const ENDPOINT = "/product_category";
    const FIELDS = [
        "description",
        "_embedded",
        "id",
        "image_link",
        "link",
        "name",
        "parent",
        "slug"
        ];

    /**
     * Initialize the custom item endpoint.
     *
     * @return void
     */
    public static function initialize() {
        add_action('rest_api_init', function () {
            register_rest_route(self::CONTEXT, self::ENDPOINT, array(
                'methods'             => 'GET',
                'callback'            => array(self::class, 'product_category_endpoint'),
                'permission_callback' => '__return_true'
            ));
        });
    }

    /**
     * Custom item endpoint callback.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing modified data.
     */
    public static function product_category_endpoint($request) {

        Endpoints::CheckSecureToken();

        $config_request["per_page"] = $request->get_param('per_page') ?? self::PER_PAGE;
        $config_request["page"]     = $request->get_param('page') ?? 1;
        $config_request["lang"]     = $request->get_param('lang') ?? "";
        $config_request["fields"]   = $request->get_param('fields') == "all" ? "" : implode(",", self::get_fields());

        // Get the 'fields' parameter from the request
        $fields = !empty($config_request["fields"]) ? self::get_fields() : [];

        // Retrieve the original items data
        $items = self::get_items($config_request);

        if (!empty($items)) {
            foreach ($items as &$item_data) {

                if(in_array('image_link', $fields) || empty($fields)){
                    $item_data["image_link"] = self::get_product_cat_df_image_link($item_data);
                }
            }
        }

        // Return the modified items data as a response
        return new WP_REST_Response($items);
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

    /**
     * Retrieve a list of items with pagination.
     *
     * @param array $config_request Config request params (page, per_page, type)
     * @return array|null   An array of items data or null on failure.
     */
    private static function get_items($config_request){
        // Retrieve the original items data
        $request = new WP_REST_Request('GET', "/wp/v2/product_cat");
        $request->set_query_params(array(
            'page'     => $config_request["page"],
            'per_page' => $config_request["per_page"],
            'lang'     => $config_request["lang"],
            '_fields'  => $config_request["fields"]
        ));
        $response = rest_do_request($request);
        $data = rest_get_server()->response_to_data($response, true);

        if(!empty($data["data"]["status"]) && $data["data"]["status"] != 200){
            $data = [];
        }

        return $data;
    }
}
?>