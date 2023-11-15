<?php

use Doofinder\WP\Endpoints;

/**
 * Class Endpoint_Custom
 *
 * This class defines various methods for handling item wordpress endpoints.
 */
class Endpoint_Custom
{
    const PER_PAGE = 100;
    const CONTEXT  = "doofinder/v1";
    const ENDPOINT = "/custom";

    /**
     * Initialize the custom item endpoint.
     *
     * @return void
     */
    public static function initialize() {
        add_action('rest_api_init', function () {
            register_rest_route(self::CONTEXT, self::ENDPOINT, array(
                'methods'  => 'GET',
                'callback' => array(self::class, 'custom_endpoint')
            ));
        });
    }

    /**
     * Custom item endpoint callback.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing modified data.
     */
    public static function custom_endpoint($request) {
        Endpoints::CheckSecureToken();

        $fields = explode(',', $request->get_param('fields') ?? '');

        $config_request = [
            'per_page' => $request->get_param('per_page') ?? self::PER_PAGE,
            'page'     => $request->get_param('page') ?? 1,
            'lang'     => $request->get_param('lang') ?? "",
            'type'     => $request->get_param('type'),
            'ids'      => $request->get_param('ids') ?? ""
        ];

        $items = self::get_items($config_request);
        $modified_items = [];

        foreach ($items as $item_data) {
            $filtered_data = array_intersect_key($item_data, array_flip($fields));

            $filtered_data = self::get_title($filtered_data, $fields);
            $filtered_data = self::get_content($filtered_data, $fields);
            $filtered_data = self::get_description($filtered_data, $fields);
            $filtered_data = self::get_author($filtered_data, $fields, $config_request);
            $filtered_data = self::get_image_link($filtered_data, $fields);
            $filtered_data = self::get_post_tags($filtered_data, $fields);
            $filtered_data = self::get_categories($filtered_data, $fields);
            $filtered_data = self::clear_unused_fields($filtered_data);

            $modified_items[] = $filtered_data;
        }

        // Return the modified items data as a response
        return new WP_REST_Response($modified_items);
    }

    /**
     * Retrieves and processes the post tags information if requested by the fields.
     *
     * @param array $filtered_data The filtered data array.
     * @param array $fields        The requested fields.
     *
     * @return array The filtered data array with post tags information if requested.
     */
    private static function get_post_tags($filtered_data, $fields) {
        if (in_array('post_tags', $fields) && isset($filtered_data["_embedded"]["wp:term"][0])) {
            $filtered_data["post_tags"] = self::get_terms("post_tag", $filtered_data["_embedded"]["wp:term"]);
        }

        return $filtered_data;
    }

    /**
     * Retrieves and processes the categories information if requested by the fields.
     *
     * @param array $filtered_data The filtered data array.
     * @param array $fields        The requested fields.
     *
     * @return array The filtered data array with categories information if requested.
     */
    private static function get_categories($filtered_data, $fields) {
        if (in_array('categories', $fields) && isset($filtered_data["_embedded"]["wp:term"][0])) {
            $filtered_data["categories"] = self::get_terms("category", $filtered_data["_embedded"]["wp:term"]);
        }

        return $filtered_data;
    }


    /**
     * Retrieves and processes the title information if requested by the fields.
     *
     * @param array $filtered_data The filtered data array.
     * @param array $fields        The requested fields.
     *
     * @return array The filtered data array with title information if requested.
     */
    private static function get_title($filtered_data, $fields) {
        $filtered_data["title"] = $filtered_data["title"]["rendered"] ?? null;

        return $filtered_data;
    }

    /**
     * Retrieves and processes the content information if requested by the fields.
     *
     * @param array $filtered_data The filtered data array.
     * @param array $fields        The requested fields.
     *
     * @return array The filtered data array with content information if requested.
     */
    private static function get_content($filtered_data, $fields) {
        $filtered_data["content"] = self::process_content($filtered_data["content"]["rendered"] ?? null);

        return $filtered_data;
    }

    /**
     * Retrieves and processes the description information if requested by the fields.
     *
     * @param array $filtered_data The filtered data array.
     * @param array $fields        The requested fields.
     *
     * @return array The filtered data array with description information if requested.
     */
    private static function get_description($filtered_data, $fields) {
        $filtered_data["description"] = self::process_content($filtered_data["excerpt"]["rendered"] ?? null);

        return $filtered_data;
    }

    /**
     * Retrieves and processes the author information if requested by the fields.
     *
     * @param array $filtered_data Product data array.
     * @param array $fields        The requested fields.
     * @param array $config_request The configuration request array.
     *
     * @return array The filtered data array with author information if requested.
     */
    private static function get_author($filtered_data, $fields, $config_request) {
        if (in_array('author', $fields) && $config_request["type"] != "posts") {
            $filtered_data["author"] = $filtered_data["_embedded"]["author"][0]["name"] ?? "Default";
        }

        return $filtered_data;
    }

    /**
     * Retrieves and processes the image link information if requested by the fields.
     *
     * @param array $filtered_data The filtered data array.
     * @param array $fields        The requested fields.
     *
     * @return array The filtered data array with image link information if requested.
     */
    private static function get_image_link($filtered_data, $fields) {
        $featured_media = $filtered_data["_embedded"]["wp:featuredmedia"][0]["media_details"]["sizes"]["medium"]["source_url"] ?? null;
        $filtered_data["image_link"] = in_array('image_link', $fields) ? $featured_media : null;

        return $filtered_data;
    }


    /**
     * Clears unused fields from the filtered data array.
     *
     * This function removes specific keys from the provided array, including "excerpt," "_embedded," and "author."
     *
     * @param array $filtered_data The data array to be processed.
     *
     * @return array The processed data array with unused fields removed.
     */
    private static function clear_unused_fields($filtered_data){
        unset($filtered_data["excerpt"]);
        unset($filtered_data["_embedded"]);
        unset($filtered_data["author"]);

        return $filtered_data;
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
     * Retrieves the names of taxonomies of a specific type within an array of items.
     *
     * @param string $type The taxonomy type to search for (e.g., "category" or "post_tag").
     * @param array $array_items The array of items containing taxonomy information.
     * @return array An array of taxonomy names that match the specified type.
    */
    private static function get_terms($type, $array_items){
        $names = array();
        foreach($array_items as $array_item){
            foreach($array_item as $item){
                if(isset($item["taxonomy"]) && $item["taxonomy"] == $type){
                    $names[] = self::process_content($item["name"]);
                }
            }
        }
        return $names;
    }

    /**
     * Retrieve a list of items with pagination.
     *
     * @param array $config_request Config request params (page, per_page, type)
     * @return array|null   An array of items data or null on failure.
     */
    private static function get_items($config_request){
        // Retrieve the original items data
        $request = new WP_REST_Request('GET', "/wp/v2/".$config_request["type"]);
        $request->set_query_params(array(
            'page'     => $config_request["page"],
            'per_page' => $config_request["per_page"],
            'lang'     => $config_request["lang"],
            'include'  => $config_request["ids"]
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
