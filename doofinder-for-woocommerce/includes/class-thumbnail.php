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
		$intermediate = image_get_intermediate_size($thumbnail_id, self::$size);
		if (FALSE != $intermediate) {
			return $intermediate['url'];
		}

		$thumb_size = $this->get_thumbnail_size(self::$size);

		//Full img size
		$img_ori = wp_get_attachment_image_src($thumbnail_id, "full");
		$img_original["w"]   = $img_ori[1] ?? 0;
		$img_original["h"]   = $img_ori[2] ?? 0;

		//Check if is posible generate a thumb or not
		if($thumb_size["w"] >= $img_original["w"] || $thumb_size["h"] >= $img_original["h"]){
			return $img_ori[0] ?? "";
		}

		$this->regenerate_thumbnail($thumbnail_id);
		$thumbnail = wp_get_attachment_image_src($thumbnail_id, self::$size);

		return $thumbnail[0];
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
