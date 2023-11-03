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
                'callback' => array(Endpoint_Custom::class, 'custom_endpoint')
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

        // Get the 'fields' parameter from the request
        $fields_param = $request->get_param('fields');
        $fields       = !empty($fields_param) ? explode(',', $fields_param) : array();

        $config_request["per_page"] = $request->get_param('per_page') ?? self::PER_PAGE;
        $config_request["page"]     = $request->get_param('page') ?? 1;
        $config_request["lang"]     = $request->get_param('lang') ?? "";
        $config_request["type"]     = $request->get_param('type');

        // Retrieve the original items data
        $items          = self::get_items($config_request);
        $modified_items = array();

        // Process and filter items data
        if (!empty($items)) {
            foreach ($items as $item_data) {
                // Filter fields
                $filtered_data = !empty($fields) ? array_intersect_key($item_data, array_flip($fields)) : $item_data;

                if(isset($filtered_data["title"]["rendered"]) && in_array('title', $fields)){
                    $filtered_data["title"] = $filtered_data["title"]["rendered"];
                }
                if(isset($filtered_data["content"]["rendered"]) && in_array('content', $fields)){
                    $filtered_data["content"] = self::process_content($filtered_data["content"]["rendered"]);
                }
                if(isset($filtered_data["excerpt"]["rendered"]) && in_array('excerpt', $fields)){
                    $filtered_data["description"] = self::process_content($filtered_data["excerpt"]["rendered"]);
                    unset($filtered_data["excerpt"]);
                }
                unset($filtered_data["author"]);
                if(in_array('author', $fields) && $config_request["type"] != "posts"){
                    $filtered_data["author"] = isset($filtered_data["_embedded"]["author"][0]["name"]) ? $filtered_data["_embedded"]["author"][0]["name"] : "Default";
                }
                if(isset($filtered_data["_embedded"]["wp:featuredmedia"][0]["media_details"]["sizes"]["medium"]["source_url"]) && in_array('image_link', $fields)){
                    $filtered_data["image_link"] = $filtered_data["_embedded"]["wp:featuredmedia"][0]["media_details"]["sizes"]["medium"]["source_url"];
                }
                if(isset($filtered_data["_embedded"]["wp:term"][0]) && in_array('post_tags', $fields)){
                    $filtered_data["post_tags"] = self::get_terms("post_tag", $filtered_data["_embedded"]["wp:term"]);
                }
                if(isset($filtered_data["_embedded"]["wp:term"][0]) && in_array('categories', $fields)){
                    $filtered_data["categories"] = self::get_terms("category", $filtered_data["_embedded"]["wp:term"]);
                }
                unset($filtered_data["_embedded"]);
                $modified_items[] = $filtered_data;
            }
        }

        // Return the modified items data as a response
        return new WP_REST_Response($modified_items);
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
            'lang'     => $config_request["lang"]
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
