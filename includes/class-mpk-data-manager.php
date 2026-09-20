<?php
/**
 * Data Manager for Maldives Packages Booking.
 *
 * Provides access to locations, hotels, rooms, and package settings.
 *
 * Approved Prefix: MPK / mpk_
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MPK_Data_Manager {

	/**
	 * Get all seeded locations.
	 *
	 * @return array List of locations.
	 */
	public static function get_locations() {
		$locations = get_option( 'mpk_locations', array() );
		if ( empty( $locations ) ) {
			$locations = MPK_Seeder::get_default_locations();
		}
		return $locations;
	}

	/**
	 * Get a single location by ID.
	 *
	 * @param string $location_id Location ID.
	 * @return array|null Location data or null.
	 */
	public static function get_location( $location_id ) {
		$locations = self::get_locations();
		foreach ( $locations as $loc ) {
			if ( isset( $loc['id'] ) && $loc['id'] === $location_id ) {
				return $loc;
			}
		}
		return null;
	}

	/**
	 * Get all hotels, optionally filtered by location.
	 *
	 * @param string|null $location_id Optional location filter.
	 * @return array List of hotels.
	 */
	public static function get_hotels( $location_id = null ) {
		$hotels = get_option( 'mpk_hotels', array() );
		if ( empty( $hotels ) ) {
			$hotels = MPK_Seeder::get_default_hotels();
		}

		if ( $location_id ) {
			$filtered = array();
			foreach ( $hotels as $h ) {
				if ( isset( $h['location'] ) && $h['location'] === $location_id ) {
					$filtered[] = $h;
				}
			}
			return $filtered;
		}

		return $hotels;
	}

	/**
	 * Get a single hotel by ID.
	 *
	 * @param string $hotel_id Hotel ID.
	 * @return array|null Hotel data or null.
	 */
	public static function get_hotel( $hotel_id ) {
		$hotels = self::get_hotels();
		foreach ( $hotels as $h ) {
			if ( isset( $h['id'] ) && $h['id'] === $hotel_id ) {
				return $h;
			}
		}
		return null;
	}

	/**
	 * Get package settings (fees, taxes, policies).
	 *
	 * @return array Settings dictionary.
	 */
	public static function get_settings() {
		$settings = get_option( 'mpk_settings', array() );
		if ( empty( $settings ) ) {
			$settings = MPK_Seeder::get_default_settings();
		}
		return $settings;
	}

	/**
	 * Get complete unified package data payload for frontend localization.
	 *
	 * @return array Combined data.
	 */
	public static function get_package_data() {
		return array(
			'plugin_url' => MPK_PLUGIN_URL,
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'mpk_booking_nonce' ),
			'locations'  => self::get_locations(),
			'hotels'     => self::get_hotels(),
			'settings'   => self::get_settings(),
		);
	}
}
