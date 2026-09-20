<?php
/**
 * Plugin Name:       Maldives Packages Booking
 * Plugin URI:        https://travelz.local/maldives-packages
 * Description:       Luxury Maldives package booking flow for multi-destination island escapes, hotel selection, real-time pricing, and concierge booking inquiries.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Travel Z Team
 * Author URI:        https://travelz.local
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
define( 'MPK_VERSION', '1.0.0' );
define( 'MPK_PLUGIN_FILE', __FILE__ );
define( 'MPK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MPK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MPK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the core plugin class.
 */
require_once MPK_PLUGIN_DIR . 'includes/class-mpk-plugin.php';

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
