<?php
/**
 * Uninstall file for Maldives Packages Booking.
 *
 * Triggered when the plugin is deleted via the WordPress Admin.
 * Data is removed ONLY when the admin enabled
 * "Settings > General > Delete ALL plugin data when the plugin is deleted".
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Default: keep all bookings, passports and settings.
if ( 1 !== (int) get_option( 'mpk_delete_data_on_uninstall', 0 ) ) {
	return;
}

global $wpdb;

$mpk_table   = $wpdb->prefix . 'mpk_bookings';
$mpk_uploads = wp_upload_dir( null, false );
$mpk_basedir = realpath( $mpk_uploads['basedir'] );

// 1. Delete passport files referenced by bookings (incl. legacy files outside the private folder).
if ( $mpk_basedir && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $mpk_table ) ) === $mpk_table ) {
	$mpk_urls = $wpdb->get_col( "SELECT passport_file_url FROM {$mpk_table} WHERE passport_file_url <> ''" ); // phpcs:ignore
	foreach ( (array) $mpk_urls as $mpk_url ) {
		$mpk_base_url = set_url_scheme( $mpk_uploads['baseurl'] );
		$mpk_url      = set_url_scheme( $mpk_url );
		if ( 0 !== strpos( $mpk_url, $mpk_base_url ) ) {
			continue;
		}
		$mpk_file = realpath( $mpk_uploads['basedir'] . '/' . ltrim( rawurldecode( substr( $mpk_url, strlen( $mpk_base_url ) ) ), '/' ) );
		if ( $mpk_file && 0 === strpos( $mpk_file, $mpk_basedir . DIRECTORY_SEPARATOR ) && is_file( $mpk_file ) ) {
			wp_delete_file( $mpk_file );
		}
	}
}

// 2. Remove the private passport folder completely.
$mpk_pass_dir = $mpk_uploads['basedir'] . '/mpk-passports';
if ( is_dir( $mpk_pass_dir ) ) {
	foreach ( (array) scandir( $mpk_pass_dir ) as $mpk_f ) {
		if ( '.' !== $mpk_f && '..' !== $mpk_f && is_file( $mpk_pass_dir . '/' . $mpk_f ) ) {
			@unlink( $mpk_pass_dir . '/' . $mpk_f ); // phpcs:ignore
		}
	}
	@rmdir( $mpk_pass_dir ); // phpcs:ignore
}

// 3. Drop bookings table.
$wpdb->query( "DROP TABLE IF EXISTS {$mpk_table}" ); // phpcs:ignore

// 4. Delete hotels (and their meta).
$mpk_hotels = get_posts(
	array(
		'post_type'   => array( 'mpk_hotel', 'mpk_booking' ),
		'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private', 'trash', 'auto-draft', 'inherit' ),
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);
foreach ( $mpk_hotels as $mpk_id ) {
	wp_delete_post( $mpk_id, true );
}

// 5. Delete destination terms (taxonomy is not registered during uninstall).
register_taxonomy( 'mpk_destination', 'mpk_hotel' );
$mpk_terms = get_terms(
	array(
		'taxonomy'   => 'mpk_destination',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);
if ( ! is_wp_error( $mpk_terms ) ) {
	foreach ( $mpk_terms as $mpk_term_id ) {
		wp_delete_term( $mpk_term_id, 'mpk_destination' );
	}
}

// 6. Delete options and rate-limit transients.
foreach ( array( 'mpk_settings', 'mpk_locations', 'mpk_hotels', 'mpk_plugin_version', 'mpk_data_seeded', 'mpk_data_seeded_version', 'mpk_db_version', 'mpk_seeded_hotel_ids', 'mpk_seeded_location_ids', 'mpk_delete_data_on_uninstall' ) as $mpk_opt ) {
	delete_option( $mpk_opt );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_mpk\_rl\_%' OR option_name LIKE '\_transient\_timeout\_mpk\_rl\_%'" ); // phpcs:ignore
