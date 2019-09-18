<?php

namespace Doofinder\WC;

use Doofinder\WC\Settings\Settings;

defined( 'ABSPATH' ) or die;

class Front {

	/**
	 * Singleton of this class.
	 *
	 * @var Front
	 */
	private static $_instance;

	/**
	 * Object handling making the Doofinder search.
	 *
	 * @var Internal_Search
	 */
	private $search;

	/**
	 * Returns the only instance of Front.
	 *
	 * @since 1.0.0
	 * @return Front
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Admin constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->enqueue_script();
		$this->add_doofinder_layer_code();
		$this->add_internal_search();
		$this->handle_banner_redirect();

		add_action( 'woocommerce_before_main_content', array( $this, 'show_top_banner' ), 99 );
		add_action( 'doofinder_for_woocommerce_search_banner_widget', array( $this, 'show_banner' ) );

		// Save logs at the end of the application run.
		add_action( 'shutdown', function() {
			$log = Transient_Log::instance();
			$log->save();
		} );
	}

	/**
	 * Enqueue plugin styles.
	 *
	 * @since 1.3.0
	 */
	public function enqueue_script() {
		add_action( 'wp_enqueue_scripts', function () {
			if ( ! is_shop() ) {
				return;
			}

			wp_enqueue_style(
				'woocommerce-doofinder',
				Doofinder_For_WooCommerce::plugin_url() . 'assets/styles.css'
			);
		} );
	}

	/**
	 * Print the Doofinder Layer code in the page footer.
	 *
	 * @since 1.0.0
	 */
	private function add_doofinder_layer_code() {
		add_action( 'wp_footer', function () {
			$script = Settings::get( 'layer', 'code' );

			if ( 'yes' === Settings::get( 'layer', 'enabled' ) && ! empty( $script ) ) {
				echo stripslashes( $script );
			}
		} );
	}

	/**
	 * Hook into WooCommerce search, call Doofinder and modify WC search
	 * with Doofinder results.
	 *
	 * @since 1.0.0
	 */
	private function add_internal_search() {
		add_filter( 'posts_pre_query', function ( $posts, $query ) {
			$log = Transient_Log::instance();
			$log->log( 'start Internal Search' );

			global $wp_query, $skip_internal_search;

			/*
			 * Only use Internal Search on WooCommerce shop pages.
			 * Only use it for the main product query.git
			 * $skip_internal_search is a flag that prevents firing this filter in nested
			 * queries.
			 */
			if ( ! is_shop() || 'product' !== $query->query['post_type'] || true === $skip_internal_search ) {
				if ( ! is_shop() || 'product' !== $query->query['post_type'] ) {
					$log->log( 'Not a shop search. Aborting.' );
				}

				if ( $skip_internal_search ) {
					$log->log( 'Nested search. Aborting.' );
				}

				return null;
			}

			$this->search = new Internal_Search();

			// Only use Internal Search if it's enabled and keys are present
			if ( ! $this->search->is_enabled() ) {
				$log->log( 'Internal Search is disabled. Aborting.' );

				return null;
			}

			$results = $this->search->search( $wp_query->query_vars );
			if ( ! $results ) {
				$log->log( 'Internal search did not return results. Aborting.' );

				return null;
			}

			/*
			 * Returning custom array of post IDs prevents WP_Query from performing its own
			 * database query, therefore it is unable to figure out pagination parameters on its own,
			 * and we need to set them up manually.
			 */
			$wp_query->found_posts   = $results['found_posts'];
			$wp_query->max_num_pages = $results['max_num_pages'];

			$log->log( sprintf(
				'Internal Search retrieved %d items from Doofinder.',
				$results['found_posts']
			) );
			$log->log( join(', ', $results['ids'] ) );

			return $results['ids'];
		}, 10, 2 );
	}

	/**
	 * Print the search banner, but checking if it's enabled in settings.
	 *
	 * @since 1.3.0
	 */
	public function show_top_banner() {
		$banner_enabled = Settings::get( 'internal_search', 'banner' );
		if ( ! $banner_enabled || 'no' === $banner_enabled ) {
			return;
		}

		$this->show_banner();
	}

	/**
	 * Display the banner on top of search results.
	 *
	 * Banner comes from search API, and is only displayed on some predefined
	 * search result pages.
	 *
	 * @since 1.3.0
	 */
	public function show_banner() {
		if ( ! is_shop() || ! get_query_var( 's' ) ) {
			return;
		}

		if ( ! $this->search ) {
			return;
		}

		$banner = $this->search->getBanner();
		if ( ! $banner ) {
			return;
		}

		// Banner image is required.
		// ID should always be present, but let's check for it just in case.
		if ( ! $banner['id'] || ! $banner['image'] ) {
			return;
		}

		// Track banner impression
		$this->search->trackBannerImpression();

		// We need to track down the banner click, so we can't redirect directly
		// to the specified URL. We redirect to WP to handle tracking.
		$url = add_query_arg(
			'doofinder-for-woocommerce-banner-click',
			$this->obscureBannerInfo( $banner['id'], $banner['link'] ),
			get_bloginfo( 'url' )
		);

		// All data is in place, let's print the image
		?>

        <div class="doofinder-for-woocommerce-search-banner">
			<?php if ( $banner['link'] ): ?>
            <a href="<?php echo $url ?>"

				<?php if ( $banner['blank'] ): ?>
                    target="_blank"
				<?php endif; ?>
            >
				<?php endif; ?>

                <img src="<?php echo $banner['image']; ?>" alt="">

				<?php if ( $banner['link'] ): ?>
            </a>
		<?php endif; ?>
        </div>

		<?php
	}

	/**
	 * Handle banner redirect URLs generated during searching.
	 *
	 * Track the banner click and perform a redirect to the specified URL.
	 *
	 * @since 1.3.0
	 */
	private function handle_banner_redirect() {
		add_action( 'init', function () {
			if ( ! isset( $_GET['doofinder-for-woocommerce-banner-click'] ) ) {
				return;
			}

			$banner_data = $_GET['doofinder-for-woocommerce-banner-click'];
			$banner_data = $this->recoverBannerInfo( $banner_data );
			if ( ! $banner_data || ! $banner_data['id'] || ! $banner_data['url'] ) {
				wp_safe_redirect( get_bloginfo( 'url' ) );
				die();
			}

			$apiWrapper = new Internal_Search();

			// It's either not enabled in options or they keys are not present.
			if ( ! $apiWrapper->is_enabled() ) {
				wp_safe_redirect( get_bloginfo( 'url' ) );
				die();
			}

			$apiWrapper->trackBannerClick( (int) $banner_data['id'] );

			wp_redirect( $banner_data['url'] );
			die();
		} );
	}

	/* Helpers ********************************************************************/

	/**
	 * Obscure banner information, so when it's inserted in URL id or redirect
	 * URL is not clearly visible, and cannot be manipulated just by
	 * manipulating the URL.
	 *
	 * @since 1.3.0
	 *
	 * @see   recoverBannerInfo
	 *
	 * @param int    $id
	 * @param string $redirectUrl
	 *
	 * @return string
	 */
	private function obscureBannerInfo( $id, $redirectUrl ) {
		return urlencode( base64_encode( "$id|$redirectUrl" ) );
	}

	/**
	 * Reverse the process of obscureBannerInfo to retrieve banner ID and redirect URL.
	 *
	 * @since 1.3.0
	 *
	 * @see   obscureBannerInfo
	 *
	 * @param string $banner_info
	 *
	 * @return array|bool
	 */
	private function recoverBannerInfo( $banner_info ) {
		$decoded = base64_decode( urldecode( $banner_info ) );
		$split   = preg_split( '/\|/', $decoded );

		// The obscured string should contain exactly 2 pieces of information:
		// banner id and the redirect URL.
		// If there's something else that's an error.
		if ( ! $split || count( $split ) !== 2 ) {
			return false;
		}

		return array(
			'id'  => $split[0],
			'url' => $split[1],
		);
	}
}
