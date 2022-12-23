<?php

namespace Doofinder\WC;

use Doofinder\WC\Helpers\Helpers;
use Doofinder\WC\Settings\Settings;

defined('ABSPATH') or die;

class Update_Manager
{
	private static $logger = NULL;

	/**
	 * Checks and performs any pending DB updates.
	 * 
	 * @since 1.5.30
	 */
	public static function check_updates($plugin_version)
	{
		$db_version = Settings::get_plugin_version();
		self::log("Check updates from $db_version to $plugin_version");

		if (empty($plugin_version)) {

			self::log("invalid plugin version: $plugin_version");

			return false;
		}
		$current_normalized_version = self::normalize_plugin_version(Settings::get_plugin_version());
		$plugin_normalized_version = self::normalize_plugin_version($plugin_version);

		for ($version = $current_normalized_version + 1; $version <= $plugin_normalized_version; $version++) {
			$version_number = str_pad($version, 6, '0', STR_PAD_LEFT);
			$update_function = 'update_' . $version_number;

			self::log("check if the update  $update_function exists.");
			if (method_exists(Update_Manager::class, $update_function)) {
				self::log("Executing $update_function update...");
				$result = NULL;
				try {
					$result = call_user_func(array(Update_Manager::class, $update_function));
				} catch (\Exception $ex) {
					self::update_failed($version_number, $ex->getMessage());
					break;
				}

				if ($result) {
					//Remove the current update notice in case it exists
					self::remove_admin_notice($version_number);
				} else {
					self::update_failed($version_number);
					break;
				}

				self::log("The update $update_function Succeeded");
				$formatted_version = self::format_normalized_plugin_version($version_number);

				//Update the database version to the newer one
				Settings::set_plugin_version($formatted_version);
			}
		}
		self::log("Updates ended, plugin db version is: " . Settings::get_plugin_version());
	}

	/**
	 * Formats the plugin version to a normalized version.
	 * Example: 1.5.13 => 010513
	 */
	private static function normalize_plugin_version($version)
	{
		$normalized = "";
		$version = explode(".", $version);
		foreach ($version as $key => $version_part) {
			$normalized .= str_pad($version_part, 2, '0', STR_PAD_LEFT);
		}
		return $normalized;
	}

	/**
	 * Formats a normalized version back to the x.y.z format version.
	 * Example: 010513 => 1.5.13
	 */
	private static function format_normalized_plugin_version($normalized)
	{
		$version = str_split($normalized, 2);
		$version = array_map(function ($vnum) {
			return (int)$vnum;
		}, $version);

		return implode(".", $version);
	}

	/**
	 * Logs the error and adds an admin notice
	 */
	private static function update_failed($version, $message = "")
	{
		self::log("ERROR: The update $version failed with message: " .  $message);
		self::add_admin_notice($version, $message);
	}

	/**
	 * Adds an admin notice using WooCommerce.
	 */
	private static function add_admin_notice($version, $message = "")
	{
		if (class_exists('WC_Admin_Notices')) {
			\WC_Admin_Notices::add_custom_notice($version, self::get_update_error_notice($version, $message));
		} else {
			wp_die("Error While updating the Doofinder For Woocommerce Database to version: $version");
		}
	}

	/**
	 * Removes an admin notice using WooCommerce.
	 */
	private static function remove_admin_notice($version)
	{
		if (class_exists('WC_Admin_Notices')) {
			\WC_Admin_Notices::remove_notice($version);
		}
	}

	/**
	 * Notice html content to show on error
	 */
	private static function get_update_error_notice($version, $message = "")
	{

		$message_intro = sprintf(__('An error occurred while updating the Doofinder Database to the %s version.', 'woocommerce-doofinder'), $version);
		$message_help = sprintf(__('For more details please contact us at our %s support center%s.', 'woocommerce-doofinder'), '<a target="_blank" href="https://support.doofinder.com/pages/contact-us.html">', '</a>');
		ob_start();
?>
		<div id="message" class="woocommerce-message doofinder-notice-update-manager">
			<figure class="logo" style="width:5rem;height:auto;float:left;margin:.5em 0;margin-right:0.75rem;">
				<img src="<?php echo Doofinder_For_WooCommerce::plugin_url() . '/assets/svg/imagotipo1.svg'; ?>" />
			</figure>
			<p class="submit">
				<strong>
					<?php echo $message_intro; ?>
				</strong>
				<?php echo $message_help; ?>

			</p>
			<?php if (!empty($message)) : ?>
				<p class="error-details">
					<?php echo $message ?>
				</p>
			<?php endif; ?>

		</div>
<?php
		return ob_get_clean();
	}

	private static function log($message)
	{
		if (empty(static::$logger)) {
			static::$logger = new Log('updates.log');
		}
		if (Helpers::is_debug_mode()) {
			static::$logger->log($message);
		}
	}

	/*
	Place all updates here
	*/

	/**
	 * Update: 1.5.30
	 * Update the admin_endpoint to the new admin.doofinder.com
	 */
	public static function update_010530()
	{
		$regex = '/(\w*?:\/\/)?(.*-)(\w+)(..*)/';
		$admin_endpoint = preg_replace($regex,  "$1$2admin$4", Settings::get_admin_endpoint());
		Settings::set_admin_endpoint($admin_endpoint);

		return true;
	}
}
