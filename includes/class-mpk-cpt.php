<?php
/**
 * Custom Post Types and Taxonomies for Maldives Packages Booking.
 *
 * Registers:
 * - Post Type: mpk_hotel (Stays & Properties)
 * - Post Type: mpk_booking (Customer Bookings)
 * - Taxonomy:  mpk_destination (Islands / Locations)
 *
 * Approved Prefix: MPK / mpk_
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MPK_CPT {

	/**
	 * Constructor: register hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ), 5 );
		add_action( 'init', array( $this, 'register_taxonomies' ), 5 );
		add_action( 'after_setup_theme', array( $this, 'ensure_thumbnail_support' ), 20 );
		add_action( 'init', array( $this, 'ensure_thumbnail_support' ), 20 );
		// Note: Admin menus are centralized under Maldives Packages in MPK_Admin::register_unified_admin_menu()
	}

	/**
	 * Guarantee post thumbnail (featured image) support is active for mpk_hotel.
	 */
	public function ensure_thumbnail_support() {
		if ( ! current_theme_supports( 'post-thumbnails' ) ) {
			add_theme_support( 'post-thumbnails' );
		} else {
			$thumb_support = get_theme_support( 'post-thumbnails' );
			if ( is_array( $thumb_support ) && isset( $thumb_support[0] ) && is_array( $thumb_support[0] ) ) {
				if ( ! in_array( 'mpk_hotel', $thumb_support[0], true ) ) {
					$thumb_support[0][] = 'mpk_hotel';
					add_theme_support( 'post-thumbnails', $thumb_support[0] );
				}
			}
		}
		add_post_type_support( 'mpk_hotel', 'thumbnail' );
	}

	/**
	 * Register Custom Post Types.
	 */
	public function register_post_types() {
		// 1. mpk_hotel (Hotels & Stays)
		$hotel_labels = array(
			'name'               => _x( 'Hotels', 'post type general name', 'maldives-packages' ),
			'singular_name'      => _x( 'Hotel', 'post type singular name', 'maldives-packages' ),
			'menu_name'          => _x( 'Hotels & Stays', 'admin menu', 'maldives-packages' ),
			'name_admin_bar'     => _x( 'Hotel', 'add new on admin bar', 'maldives-packages' ),
			'add_new'            => _x( 'Add New', 'hotel', 'maldives-packages' ),
			'add_new_item'       => __( 'Add New Hotel', 'maldives-packages' ),
			'new_item'           => __( 'New Hotel', 'maldives-packages' ),
			'edit_item'          => __( 'Edit Hotel', 'maldives-packages' ),
			'view_item'          => __( 'View Hotel', 'maldives-packages' ),
			'all_items'          => __( 'All Hotels', 'maldives-packages' ),
			'search_items'       => __( 'Search Hotels', 'maldives-packages' ),
			'not_found'          => __( 'No hotels found.', 'maldives-packages' ),
			'not_found_in_trash' => __( 'No hotels found in Trash.', 'maldives-packages' ),
		);

		$hotel_args = array(
			'labels'             => $hotel_labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => false, // Managed under Maldives Packages menu
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 27,
			'supports'           => array( 'title', 'thumbnail', 'custom-fields' ),
			'show_in_rest'       => true,
		);
		register_post_type( 'mpk_hotel', $hotel_args );
		add_post_type_support( 'mpk_hotel', 'thumbnail' );

		// 2. mpk_booking (Bookings)
		$booking_labels = array(
			'name'               => _x( 'Bookings', 'post type general name', 'maldives-packages' ),
			'singular_name'      => _x( 'Booking', 'post type singular name', 'maldives-packages' ),
			'menu_name'          => _x( 'Bookings', 'admin menu', 'maldives-packages' ),
			'name_admin_bar'     => _x( 'Booking', 'add new on admin bar', 'maldives-packages' ),
			'all_items'          => __( 'All Bookings', 'maldives-packages' ),
			'edit_item'          => __( 'View Booking', 'maldives-packages' ),
			'search_items'       => __( 'Search Bookings', 'maldives-packages' ),
			'not_found'          => __( 'No bookings found.', 'maldives-packages' ),
			'not_found_in_trash' => __( 'No bookings found in Trash.', 'maldives-packages' ),
		);

		$booking_args = array(
			'labels'             => $booking_labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => false, // Managed under Maldives Packages menu
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'capabilities'       => array(
				'create_posts' => 'do_not_allow', // Bookings created via checkout flow
			),
			'map_meta_cap'       => true,
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'custom-fields' ),
			'show_in_rest'       => false,
		);
		register_post_type( 'mpk_booking', $booking_args );
	}

	/**
	 * Register Custom Taxonomies.
	 */
	public function register_taxonomies() {
		$dest_labels = array(
			'name'              => _x( 'Destinations', 'taxonomy general name', 'maldives-packages' ),
			'singular_name'     => _x( 'Destination', 'taxonomy singular name', 'maldives-packages' ),
			'search_items'      => __( 'Search Destinations', 'maldives-packages' ),
			'all_items'         => __( 'All Destinations', 'maldives-packages' ),
			'parent_item'       => __( 'Parent Destination', 'maldives-packages' ),
			'parent_item_colon' => __( 'Parent Destination:', 'maldives-packages' ),
			'edit_item'         => __( 'Edit Destination', 'maldives-packages' ),
			'update_item'       => __( 'Update Destination', 'maldives-packages' ),
			'add_new_item'      => __( 'Add New Destination', 'maldives-packages' ),
			'new_item_name'     => __( 'New Destination Name', 'maldives-packages' ),
			'menu_name'         => __( 'Destinations', 'maldives-packages' ),
		);

		$dest_args = array(
			'hierarchical'      => true,
			'labels'            => $dest_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => false,
			'rewrite'           => false,
			'show_in_rest'      => true,
		);
		register_taxonomy( 'mpk_destination', array( 'mpk_hotel' ), $dest_args );
	}

	/**
	 * Register Unified Maldives Packages Admin Menu (Deprecated / Centralized in MPK_Admin).
	 */
	public function register_admin_menus() {
		// Centralized under 'Maldives Packages' in MPK_Admin::register_unified_admin_menu()
	}

	/**
	 * Render Main Admin Dashboard overview.
	 */
	public function render_main_admin_page() {
		?>
		<div class="wrap mpk-admin-wrap">
			<h1><?php esc_html_e( 'Maldives Packages Booking Dashboard', 'maldives-packages' ); ?></h1>
			<p><?php esc_html_e( 'Manage destination packages, curated stays, room inventories, and booking inquiries.', 'maldives-packages' ); ?></p>
			<div style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
				<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px;">
					<h2><?php esc_html_e( 'Seeded Stays', 'maldives-packages' ); ?></h2>
					<p><?php esc_html_e( 'Handpicked resort and local island hotels for multi-destination packages.', 'maldives-packages' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mpk_hotel' ) ); ?>" class="button button-primary"><?php esc_html_e( 'View Hotels', 'maldives-packages' ); ?></a>
				</div>
				<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px;">
					<h2><?php esc_html_e( 'Customer Bookings', 'maldives-packages' ); ?></h2>
					<p><?php esc_html_e( 'Review submitted traveler itineraries, lead guest data, and concierge requests.', 'maldives-packages' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mpk-bookings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'View Bookings', 'maldives-packages' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Package Settings page placeholder.
	 */
	public function render_settings_page() {
		$settings = get_option( 'mpk_settings', array() );
		?>
		<div class="wrap mpk-admin-wrap">
			<h1><?php esc_html_e( 'Package Settings', 'maldives-packages' ); ?></h1>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Tax Rate', 'maldives-packages' ); ?></th>
					<td><code><?php echo esc_html( isset( $settings['tax_rate'] ) ? ( $settings['tax_rate'] * 100 ) . '%' : '8%' ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Extra Charges', 'maldives-packages' ); ?></th>
					<td><code>$<?php echo esc_html( isset( $settings['extras'] ) ? $settings['extras'] : 45 ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Service Fee', 'maldives-packages' ); ?></th>
					<td><code>$<?php echo esc_html( isset( $settings['service_fee'] ) ? $settings['service_fee'] : 25 ); ?></code></td>
				</tr>
			</table>
		</div>
		<?php
	}
}
