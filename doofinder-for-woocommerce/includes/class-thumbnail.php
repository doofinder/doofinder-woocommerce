<?php

namespace Doofinder\WP;

class Thumbnail {

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

	public function __construct( \WP_Post $post ) {
		$this->post = $post;
	}

	public static function get_size(){
		return static::$size;
	}

	/**
	 * Retrieve the address to the thumbnail of the post.
	 *
	 * If the thumbnail does not exist it will be generated.
	 *
	 * @return string
	 */
	public function get() {
		if ( ! has_post_thumbnail( $this->post ) ) {
			return null;
		}

		if ( ! $this->has_thumbnail() ) {
			$this->regenerate_thumbnail();
		}

		$thumbnail = wp_get_attachment_image_src( get_post_thumbnail_id( $this->post ), self::$size );

		return $thumbnail[0];
	}

	/**
	 * Check if the featured image resized to the size we're interested
	 * in actually physically exists on the hard drive.
	 *
	 * @see Thumbnail::$size
	 *
	 * @return bool
	 */
	private function has_thumbnail() {
		$image_meta = wp_get_attachment_metadata( get_post_thumbnail_id( $this->post ) );
		if ( ! isset( $image_meta['sizes'][ self::$size ] ) ) {
			return false;
		}

		/*
		 * How process below works:
		 * WP only gives us partial information about uploaded images.
		 * $image_meta contains only file names (with the exception of the
		 * full size file - that's a path relative to the uploads directory).
		 * So we need to build the full absolute path to the resized file ourselves.
		 *
		 * We take path to the uploads dir, append path to the full sized image
		 * to it, and then replace the file name with the resized file name.
		 */

		// File name of the unscaled image.
		$base_file_name = wp_basename( $image_meta['file'] );

		// Name of the file scaled down to our size.
		$scaled_image_name = $image_meta['sizes'][ self::$size ]['file'];

		// Path to uploads directory.
		$uploads_path = wp_upload_dir()['basedir'];

		// Path to the full size file.
		$base_file_path = $uploads_path . DIRECTORY_SEPARATOR . $image_meta['file'];

		// Replace the name of the file in above path
		// with the name of the scaled file.
		$scaled_image_path = str_replace( $base_file_name, $scaled_image_name, $base_file_path );

		return file_exists( $scaled_image_path );
	}

	/**
	 * Regenerate thumbnails for the current post.
	 */
	private function regenerate_thumbnail() {
		if (!function_exists('wp_generate_attachment_metadata')) {
			include(ABSPATH . 'wp-admin/includes/image.php');
		}
		
		$attachment_id = get_post_thumbnail_id( $this->post );

		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata(
				$attachment_id,
				get_attached_file( $attachment_id )
			)
		);
	}
}
