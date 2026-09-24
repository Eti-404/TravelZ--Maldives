<?php
/**
 * Plugin Name:       Maldives Packages Booking
 * Plugin URI:        https://github.com/kamrulhasan2/TravelZ--Maldives
 * Description:       Luxury Maldives package booking flow for multi-destination island escapes, hotel selection, real-time pricing, and concierge booking inquiries.
 * Version:           1.2.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Kamrul Hasan & Anika Eti
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       maldives-packages
 * Domain Path:       /languages
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Constants Definition.
 * Approved Project Prefix: MPK / mpk_
 */
define( 'MPK_VERSION', '1.2.3' );
define( 'MPK_PLUGIN_FILE', __FILE__ );
define( 'MPK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MPK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MPK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the core plugin class.
 */
require_once MPK_PLUGIN_DIR . 'includes/class-mpk-plugin.php';

/**
 * Declare WooCommerce HPOS (custom order tables) compatibility - orders are
 * handled through the WooCommerce CRUD API only.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Plugin Activation Callback.
 */
function mpk_activate_plugin() {
	MPK_Plugin::activate();
}
register_activation_hook( __FILE__, 'mpk_activate_plugin' );

/**
 * Plugin Deactivation Callback.
 */
function mpk_deactivate_plugin() {
	MPK_Plugin::deactivate();
}
register_deactivation_hook( __FILE__, 'mpk_deactivate_plugin' );

/**
 * Initialize the plugin instance.
 */
function mpk_init_plugin() {
	return MPK_Plugin::get_instance();
}
add_action( 'plugins_loaded', 'mpk_init_plugin' );

/**
 * Show "Developed by" credits on the Plugins screen.
 *
 * @param string[] $meta Plugin row meta links.
 * @param string   $file Plugin basename.
 * @return string[]
 */
function mpk_plugin_row_meta( $meta, $file ) {
	if ( MPK_PLUGIN_BASENAME !== $file ) {
		return $meta;
	}
	foreach ( $meta as $i => $item ) {
		if ( false !== strpos( $item, 'Kamrul Hasan' ) ) {
			$meta[ $i ] = sprintf(
				/* translators: 1: first developer, 2: second developer. */
				esc_html__( 'Developed by %1$s & %2$s', 'maldives-packages' ),
				'<strong>Kamrul Hasan</strong>',
				'<strong>Anika Eti</strong>'
			);
		}
	}
	return $meta;
}
add_filter( 'plugin_row_meta', 'mpk_plugin_row_meta', 10, 2 );
