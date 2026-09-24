<?php
/**
 * Core Plugin Class for Maldives Packages Booking.
 *
 * Coordinates initialization, hooks, and lifecycle management.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MPK_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var MPK_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return MPK_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Plugin activation routine.
	 */
	public static function activate() {
		// Set default version in options if not already set.
		if ( ! get_option( 'mpk_plugin_version' ) ) {
			add_option( 'mpk_plugin_version', MPK_VERSION );
		} else {
			update_option( 'mpk_plugin_version', MPK_VERSION );
		}

		// Ensure CPTs and Taxonomies are registered before seeding.
		require_once MPK_PLUGIN_DIR . 'includes/class-mpk-cpt.php';
		$cpt = new MPK_CPT();
		$cpt->register_post_types();
		$cpt->register_taxonomies();

		// Run Seeder for default data.
		require_once MPK_PLUGIN_DIR . 'includes/class-mpk-data-manager.php';
		require_once MPK_PLUGIN_DIR . 'includes/class-mpk-seeder.php';
		MPK_Seeder::seed();

		// Create or upgrade custom bookings table.
		require_once MPK_PLUGIN_DIR . 'includes/class-mpk-booking-manager.php';
		MPK_Booking_Manager::create_table();

		// Flush rewrite rules safely on activation.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation routine.
	 */
	public static function deactivate() {
		// Flush rewrite rules cleanly on deactivation.
		flush_rewrite_rules();
	}

	/**
	 * Initialize core hooks and dependencies.
	 */
	private function init() {
		add_action( 'init', array( $this, 'on_init' ), 0 );

		// Load CPT and Taxonomies manager.
		require_once MPK_PLUGIN_DIR . 'includes/class-mpk-cpt.php';
		new MPK_CPT();

		// Load Data Manager and Seeder.
		require_once MPK_PLUGIN_DIR . 'includes/class-mpk-data-manager.php';
		require_once MPK_PLUGIN_DIR . 'includes/class-mpk-seeder.php';

		// Load Booking Manager.
		require_once MPK_PLUGIN_DIR . 'includes/class-mpk-booking-manager.php';

		// Load Automated Email Notifications Mailer.
		require_once MPK_PLUGIN_DIR . 'includes/class-mpk-mailer.php';

		// Load WooCommerce payment bridge (inactive unless WooCommerce is available).
		require_once MPK_PLUGIN_DIR . 'includes/class-mpk-woocommerce.php';
		new MPK_WooCommerce();

		// Load AJAX Submission Handler.
		require_once MPK_PLUGIN_DIR . 'includes/class-mpk-ajax-handler.php';
		new MPK_Ajax_Handler();

		// Initialize frontend controller.
		require_once MPK_PLUGIN_DIR . 'frontend/class-mpk-frontend.php';
		new MPK_Frontend();

		// Initialize admin dashboard controller, hotel meta boxes, and settings.
		if ( is_admin() ) {
			require_once MPK_PLUGIN_DIR . 'admin/class-mpk-settings.php';
			MPK_Settings::get_instance();
			require_once MPK_PLUGIN_DIR . 'admin/class-mpk-admin.php';
			new MPK_Admin();
			require_once MPK_PLUGIN_DIR . 'admin/class-mpk-hotel-meta-box.php';
			new MPK_Hotel_Meta_Box();
			require_once MPK_PLUGIN_DIR . 'admin/class-mpk-destination-meta.php';
			new MPK_Destination_Meta();
		}
	}

	/**
	 * WordPress init hook callback.
	 */
	public function on_init() {
		// Load plugin textdomain for internationalization.
		load_plugin_textdomain( 'maldives-packages', false, dirname( MPK_PLUGIN_BASENAME ) . '/languages' );

		// Self-heal: ensure default data is seeded if fresh installation without activation hook.
		if ( ! get_option( 'mpk_data_seeded' ) ) {
			MPK_Seeder::seed();
		}

		// Self-heal: create / upgrade bookings table only when schema version changes.
		MPK_Booking_Manager::maybe_upgrade();
	}
}
