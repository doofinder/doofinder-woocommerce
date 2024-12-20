<?php
/**
 * DooFinder Template_Engine helper methods.
 *
 * @package Doofinder\WP\Helpers
 */

namespace Doofinder\WP\Helpers;

/**
 * Adds helper methods regarding to the Template_Engine.
 */
class Template_Engine {

	/**
	 * Global instance of this class.
	 *
	 * @var $this
	 */
	private static $instances = array();

	/**
	 * Where template files are located.
	 *
	 * @var string
	 */
	private $location;

	/**
	 * Theme_Template_Engine constructor.
	 *
	 * @param string $location Where template files are located.
	 */
	public function __construct( $location ) {
		$this->location = $location;
	}

	/**
	 * Retrieve the global instance of this class. Create it if one
	 * doesn't exist yet.
	 *
	 * @param string $location Where template files are located.
	 *
	 * @return $this
	 */
	public static function create( $location ) {
		if ( isset( self::$instances[ $location ] ) ) {
			return self::$instances[ $location ];
		}

		self::$instances[ $location ] = new self( $location );

		return self::$instances[ $location ];
	}

	/**
	 * Convert a template path to the full path to the .php file on the drive.
	 * The function will first look in the theme folder, but if the file is not found,
	 * it will check if the template is an absolute file path.
	 *
	 * @param string $template Template path to look for.
	 * @param string $folder   Overrides the default location.
	 *
	 * @return mixed|string Full path to the template file.
	 *
	 * @throws \LogicException If the file doesn't exist.
	 */
	private function resolve_file( $template, $folder = '' ) {
		$location = $this->location;
		if ( ! empty( $folder ) ) {
			$location = $folder;
		}

		$file = preg_replace( '/\\|\//', DIRECTORY_SEPARATOR, $template );
		$part = $location . DIRECTORY_SEPARATOR . $file;

		$file = get_template_directory() . DIRECTORY_SEPARATOR . $part . '.php';
		if ( file_exists( $file ) ) {
			return $file;
		}

		$file = "$part.php";
		if ( file_exists( $file ) ) {
			return $file;
		}

		if ( true === WP_DEBUG ) {
			throw new \LogicException(
				sprintf(
					/* translators: %1$s is replaced with the template name and %2$s by the path where the template is located. */
					esc_html__( 'Template "%1$s" cannot be found in "%2$s".' ),
					esc_html( $template ),
					esc_html( $location )
				)
			);
		}

		return false;
	}

	/**
	 * Render the template.
	 * Template to render is relative to the templates location set when
	 * creating the instance of this class.
	 *
	 * @param string $template Template to render.
	 * @param array  $data     Data to pass to the template.
	 * @param string $folder   Overrides the default location.
	 */
	public function render( $template, $data = array(), $folder = '' ) {
		$file = $this->resolve_file( $template, $folder );
		include $file;
	}

	/**
	 * Alias for "render".
	 *
	 * @param string $template Template to render.
	 * @param array  $data Data to pass to the template.
	 */
	public function insert( $template, $data = array() ) {
		$this->render( $template, $data );
	}

	/**
	 * Retrieve part of the template.
	 *
	 * Uses template engine build into theme to grab the file (relative to "parts" directory),
	 * and pass variables to this files local scope.
	 *
	 * @param string $part Template to render.
	 * @param array  $data Data to pass to the template.
	 * @param string $folder Overrides the default location.
	 */
	public static function get_template( $part, $data = array(), $folder = '' ) {

		if ( ! $folder ) {
			$folder = \Doofinder\WP\Doofinder_For_WordPress::plugin_path() . 'views';
		}

		$engine = self::create( $folder );
		$engine->render( $part, $data );
	}

	/**
	 * Clean SVG function - helper for get_svg() function.
	 *
	 * @param string $img (required) - File source;.
	 */
	public static function get_clean_svg( $img ) {
		$img_svg = file_get_contents( $img ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		preg_match( '/<svg[\s\S]*\/svg>/m', $img_svg, $matches );

		if ( isset( $matches[0] ) ) {
			return $matches[0];
		}

		return $img_svg;
	}

	/**
	 * Retrieve an image or an svg to represent an attachment - based on file name or WP Image ID;
	 *
	 * @param string|int   $attachment (required) - File name or WP image ID.
	 * @param string|array $thumbnail (optional) - WP thumbnail size - usable only with image ID and NOT SVG files.
	 * @param string       $path (optional) - Path to file.
	 * @param string       $ext (optional) - File extension.
	 */
	public static function get_svg( $attachment, $thumbnail = 'full-size', $path = '/assets/svg/', $ext = '.svg' ) {

		if ( is_int( $attachment ) ) {
			$src = wp_get_attachment_image_src( $attachment, $thumbnail );

			if ( ! $src ) {
				return '';
			}

			$ext = pathinfo( $src[0], PATHINFO_EXTENSION );

			if ( 'svg' !== $ext ) {
				return wp_get_attachment_image( $attachment, $thumbnail );
			}

			$file = get_attached_file( $attachment, true );
			$img  = realpath( $file );

			if ( $img ) {
				return self::get_clean_svg( $img );
			}
		} else {
			$filename = strpos( $attachment, $ext ) !== false ? $attachment : $attachment . $ext;
			$file     = \Doofinder\WP\Doofinder_For_WordPress::plugin_path() . $path . $filename;

			if ( ! file_exists( $file ) ) {
				return '';
			}

			return self::get_clean_svg( $file );
		}
	}
}
