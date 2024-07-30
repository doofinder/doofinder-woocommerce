<?php
/**
 * Step 1 of the wizard
 *
 * @package Doofinder\WP\Setup_Wizard
 */

namespace Doofinder\WP;

$df_error = $df_error ?? false;

use Doofinder\WP\Settings;
use Doofinder\WP\Setup_Wizard;

/**
 * What is $this in this context?
 *
 * @var Setup_Wizard $this The Setup Wizard object.
 */

$sectors = array(
	__( 'Pharma & Cosmetics', 'wordpress-doofinder' )    => 'parapharmacy',
	__( 'Tech Products & Electronics', 'wordpress-doofinder' ) => 'technology',
	__( 'Apparel & Accessories', 'wordpress-doofinder' ) => 'fashion',
	__( 'Sport & Fitness', 'wordpress-doofinder' )       => 'sport',
	__( 'Childcare', 'wordpress-doofinder' )             => 'childcare',
	__( 'Pets', 'wordpress-doofinder' )                  => 'pets',
	__( 'Home & Garden', 'wordpress-doofinder' )         => 'home',
	__( 'Food & Beverages', 'wordpress-doofinder' )      => 'food',
	__( 'Toys & Hobbies', 'wordpress-doofinder' )        => 'toys',
	__( 'Auto Parts & Accessories', 'wordpress-doofinder' ) => 'autos',
	__( 'Leisure & Culture', 'wordpress-doofinder' )     => 'leisure',
	__( 'Others', 'wordpress-doofinder' )                => 'others',
);

$selected_sector = Settings::get_sector( '' )

?>
<form action="<?php echo esc_url( Setup_Wizard::get_url( array( 'step' => '1' ) ) ); ?>" method="post">
	<div class="df-setup-step__actions">
		<select id="sector-select" name="sector" required>
			<option value="" selected disabled hidden> - <?php esc_html_e( 'Choose a sector', 'wordpress-doofinder' ); ?> - </option>
			<?php
			foreach ( $sectors as $sector => $key ) {
				$is_selected = false;
				if ( $selected_sector === $key ) {
					$is_selected = true;
				}
				?>
				<option value="<?php echo esc_attr( $key ); ?>"
											<?php
											if ( $is_selected ) :
												?>
					selected="selected"<?php endif; ?>><?php echo esc_html( $sector ); ?></option>
				<?php
			}
			?>
		</select>
		<button type="submit"><?php esc_html_e( 'Next', 'wordpress-doofinder' ); ?></button>
		<input type="hidden" id="process-step-input" name="process-step" value="1" />
		<input type="hidden" id="next-step-input" name="next-step" value="2" />
	</div>
</form>
