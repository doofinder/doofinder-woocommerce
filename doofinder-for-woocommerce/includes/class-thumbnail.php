<?php

namespace Doofinder\WP;

class Thumbnail
{

	/**
	 * The name (slug) of the thumbnail size to generate.
	 *
	 * @var string
	 */
	private static $size = 'medium';

	/**
	 * Post we'll be generating thumbnail for.
	 *
	 * @var \WP_Post
	 */
	private $post;

	public function __construct(\WP_Post $post)
	{
		$this->post = $post;
		self::$size = self::get_size();
	}

	public static function get_size()
	{
		return Settings::get_image_size();
	}

	/**
	 * Retrieve the address to the thumbnail of the post.
	 *
	 * If the thumbnail does not exist it will be generated.
	 *
	 * @return string
	 */
	public function get()
	{
		if (!has_post_thumbnail($this->post)) {
			return null;
		}

		$thumbnail_id = get_post_thumbnail_id($this->post);
		$thumbnail = image_get_intermediate_size($thumbnail_id, self::$size);

		if (FALSE != $thumbnail) {
			return $thumbnail[0];
		}

		$img_url = $this->check_size_image_url($thumbnail_id);
		if($img_url){
			return $img_url;
		}

		$this->regenerate_thumbnail($thumbnail_id);
		$thumbnail = wp_get_attachment_image_src($thumbnail_id, self::$size);

		return $thumbnail[0];
	}

	/**
	 * Checks if we are going to be able to generate the thumbnail from
	 * the original image and if it cannot then it will return the URL
	 * of the original image.
	 *
	 * @param string $thumbnail_id Thumb ID por check
	 * @return string URL of Thumb or false
	 */
	private function check_size_image_url($thumbnail_id){

		//Thumb requested size
		$thumb_size = $this->get_thumbnail_size(self::$size);

		//Full img size
		$img_original = $this->get_original_size($thumbnail_id);

		//Check if is posible generate a thumb or not
		if($thumb_size["w"] >= $img_original["w"] || $thumb_size["h"] >= $img_original["h"]){
			return $img_original["url"];
		}
		return false;
	}

	/**
	 * Get size of Format thumbnail requested
	 *
	 * @param string $format Format image (large, medium, etc...)
	 * @return array Size of thumbnail requested
	 */
	private function get_thumbnail_size($format){

		$size = array();

		$w_thumb = get_option($format."_size_w");
		$h_thumb = get_option($format."_size_h");

		if(empty($w_thumb)){
			$size = explode("x", self::$size);
			$size["w"] = $size[0] ?? 0;
			$size["h"] = $size[1] ?? 0;
		}
		else{
			$size["w"] = $w_thumb ?? 0;
			$size["h"] = $h_thumb ?? 0;
		}

		return $size;
	}

	/**
	 * Get size from thumbnail_id
	 *
	 * @param string $thumbnail_id Thumbnail ID for get original size
	 * @return array Original IMG (w,h and url)
	 */
	private function get_original_size($thumbnail_id){

		$img = array();
		$img_ori = wp_get_attachment_image_src($thumbnail_id, "full");

		$img["url"] = $img_ori[0];
		$img["w"]   = $img_ori[1];
		$img["h"]   = $img_ori[2];

		return $img;
	}

	/**
	 * Regenerate thumbnails for the current post.
	 */
	private function regenerate_thumbnail($attachment_id)
	{
		if (!function_exists('wp_generate_attachment_metadata')) {
			include(ABSPATH . 'wp-admin/includes/image.php');
		}

		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata(
				$attachment_id,
				get_attached_file($attachment_id)
			)
		);
	}
}
