<?php
/**
 * Data Seeder for Maldives Packages Booking.
 *
 * Populates default locations, hotels, rooms, and package settings from source data.
 * Includes strict duplicate prevention.
 *
 * Approved Prefix: MPK / mpk_
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MPK_Seeder {

	/**
	 * Seed version to control upgrades without duplicates.
	 */
	const SEED_VERSION = '1.0.0';

	/**
	 * Run the seeding process.
	 *
	 * @param bool $force Force re-seed even if already seeded.
	 * @return bool True on success.
	 */
	public static function seed( $force = false ) {
		$seeded_version = get_option( 'mpk_data_seeded_version', false );
		if ( ! $force && self::SEED_VERSION === $seeded_version ) {
			return false; // Already seeded and up-to-date.
		}

		// 1. Seed Options Data
		self::seed_options();

		// 2. Seed Taxonomies (mpk_destination)
		self::seed_taxonomies();

		// 3. Seed Custom Post Types (mpk_hotel)
		self::seed_hotels_cpt();

		// Mark as seeded
		update_option( 'mpk_data_seeded_version', self::SEED_VERSION );
		update_option( 'mpk_data_seeded', true );

		return true;
	}

	/**
	 * Seed Options Store.
	 */
	public static function seed_options() {
		update_option( 'mpk_locations', self::get_default_locations() );
		update_option( 'mpk_hotels', self::get_default_hotels() );
		update_option( 'mpk_settings', self::get_default_settings() );
	}

	/**
	 * Seed Taxonomy Terms (Destinations).
	 */
	public static function seed_taxonomies() {
		$locations = self::get_default_locations();
		foreach ( $locations as $loc ) {
			$term = term_exists( $loc['id'], 'mpk_destination' );
			if ( ! $term ) {
				wp_insert_term(
					$loc['name'],
					'mpk_destination',
					array(
						'slug'        => $loc['id'],
						'description' => $loc['tagline'],
					)
				);
			}
		}
	}

	/**
	 * Seed Hotels Custom Post Type.
	 */
	public static function seed_hotels_cpt() {
		$hotels = self::get_default_hotels();
		foreach ( $hotels as $h ) {
			// Query existing post by unique meta _mpk_hotel_id
			$existing = get_posts(
				array(
					'post_type'   => 'mpk_hotel',
					'post_status' => 'any',
					'numberposts' => 1,
					'meta_key'    => '_mpk_hotel_id',
					'meta_value'  => $h['id'],
					'fields'      => 'ids',
				)
			);

			$post_data = array(
				'post_title'   => $h['name'],
				'post_name'    => $h['id'],
				'post_status'  => 'publish',
				'post_type'    => 'mpk_hotel',
			);

			if ( ! empty( $existing ) ) {
				$post_id = $existing[0];
				$post_data['ID'] = $post_id;
				wp_update_post( $post_data );
			} else {
				$post_id = wp_insert_post( $post_data );
			}

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				// Assign taxonomy
				wp_set_object_terms( $post_id, $h['location'], 'mpk_destination' );

				// Update metadata
				update_post_meta( $post_id, '_mpk_hotel_id', $h['id'] );
				update_post_meta( $post_id, '_mpk_location', $h['location'] );
				update_post_meta( $post_id, '_mpk_stars', $h['stars'] );
				update_post_meta( $post_id, '_mpk_review', $h['review'] );
				update_post_meta( $post_id, '_mpk_review_label', $h['reviewLabel'] );
				update_post_meta( $post_id, '_mpk_area', $h['area'] );
				update_post_meta( $post_id, '_mpk_image_url', $h['image'] );
				update_post_meta( $post_id, '_mpk_amenities', $h['amenities'] );
				update_post_meta( $post_id, '_mpk_rooms', $h['rooms'] );
			}
		}
	}

	/**
	 * Get default location structures.
	 *
	 * @return array Default locations.
	 */
	public static function get_default_locations() {
		return array(
			array(
				'id'      => 'hulhumale',
				'name'    => 'Hulhumale',
				'tagline' => 'Vibrant beachfront city',
				'image'   => MPK_PLUGIN_URL . 'assets/images/loc-hulhumale.jpg',
				'nights'  => 2,
			),
			array(
				'id'      => 'maafushi',
				'name'    => 'Maafushi Island',
				'tagline' => 'Local island charm',
				'image'   => MPK_PLUGIN_URL . 'assets/images/loc-maafushi.jpg',
				'nights'  => 2,
			),
			array(
				'id'      => 'resort',
				'name'    => 'Resort / Private Island',
				'tagline' => 'Pure overwater luxury',
				'image'   => MPK_PLUGIN_URL . 'assets/images/loc-resort.jpg',
				'nights'  => 3,
			),
		);
	}

	/**
	 * Get default hotel structures.
	 *
	 * @return array Default hotels with rooms and pricing.
	 */
	public static function get_default_hotels() {
		return array(
			array(
				'id'          => 'h-azure',
				'name'        => 'Azure Bay Residence',
				'location'    => 'hulhumale',
				'stars'       => 4,
				'review'      => 4.7,
				'reviewLabel' => 'Excellent',
				'area'        => 'Hulhumale Beachfront',
				'image'       => MPK_PLUGIN_URL . 'assets/images/hotel-3.jpg',
				'amenities'   => array( 'Pool', 'Wifi', 'Spa', 'Restaurant' ),
				'rooms'       => array(
					array(
						'id'        => 'r1',
						'name'      => 'Standard Room with Balcony',
						'meal'      => 'Breakfast',
						'price'     => 110,
						'amenities' => array( 'Balcony' ),
					),
					array(
						'id'        => 'r2',
						'name'      => 'Deluxe Sea View',
						'meal'      => 'Breakfast & Dinner',
						'price'     => 165,
						'amenities' => array( 'Sea View' ),
					),
				),
			),
			array(
				'id'          => 'h-coral',
				'name'        => 'Coral Sands Boutique',
				'location'    => 'hulhumale',
				'stars'       => 3,
				'review'      => 4.4,
				'reviewLabel' => 'Very Good',
				'area'        => 'Central Hulhumale',
				'image'       => MPK_PLUGIN_URL . 'assets/images/hotel-1.jpg',
				'amenities'   => array( 'Wifi', 'Restaurant', 'Airport Transfer' ),
				'rooms'       => array(
					array(
						'id'        => 'r1',
						'name'      => 'Standard Twin',
						'meal'      => 'Breakfast',
						'price'     => 78,
						'amenities' => array( 'Twin' ),
					),
					array(
						'id'        => 'r2',
						'name'      => 'Deluxe Double',
						'meal'      => 'Breakfast & Dinner',
						'price'     => 115,
						'amenities' => array( 'Double' ),
					),
				),
			),
			array(
				'id'          => 'h-pearl',
				'name'        => 'Pearl Lagoon Inn',
				'location'    => 'maafushi',
				'stars'       => 3,
				'review'      => 4.5,
				'reviewLabel' => 'Very Good',
				'area'        => 'Maafushi Beach',
				'image'       => MPK_PLUGIN_URL . 'assets/images/hotel-3.jpg',
				'amenities'   => array( 'Wifi', 'Snorkeling', 'Restaurant' ),
				'rooms'       => array(
					array(
						'id'        => 'r1',
						'name'      => 'Standard Room',
						'meal'      => 'Breakfast',
						'price'     => 85,
						'amenities' => array( 'Double' ),
					),
					array(
						'id'        => 'r2',
						'name'      => 'Sea View Deluxe',
						'meal'      => 'All Inclusive',
						'price'     => 175,
						'amenities' => array( 'Sea View' ),
					),
				),
			),
			array(
				'id'          => 'h-island',
				'name'        => 'Island Breeze Hotel',
				'location'    => 'maafushi',
				'stars'       => 4,
				'review'      => 4.6,
				'reviewLabel' => 'Excellent',
				'area'        => 'Maafushi Sunset Side',
				'image'       => MPK_PLUGIN_URL . 'assets/images/hotel-1.jpg',
				'amenities'   => array( 'Pool', 'Wifi', 'Excursions' ),
				'rooms'       => array(
					array(
						'id'        => 'r1',
						'name'      => 'Island View Room',
						'meal'      => 'Breakfast',
						'price'     => 120,
						'amenities' => array( 'Island View' ),
					),
					array(
						'id'        => 'r2',
						'name'      => 'Super Deluxe Suite',
						'meal'      => 'Breakfast & Dinner',
						'price'     => 220,
						'amenities' => array( 'Suite' ),
					),
				),
			),
			array(
				'id'          => 'h-paradise',
				'name'        => 'Paradise Overwater Resort',
				'location'    => 'resort',
				'stars'       => 5,
				'review'      => 4.9,
				'reviewLabel' => 'Exceptional',
				'area'        => 'Private Atoll',
				'image'       => MPK_PLUGIN_URL . 'assets/images/hotel-2.jpg',
				'amenities'   => array( 'Overwater', 'Spa', 'All Inclusive', 'Diving' ),
				'rooms'       => array(
					array(
						'id'        => 'r1',
						'name'      => 'Beach Villa with Pool',
						'meal'      => 'All Inclusive',
						'price'     => 480,
						'amenities' => array( 'With Pool' ),
					),
					array(
						'id'        => 'r2',
						'name'      => 'Water Villa',
						'meal'      => 'All Inclusive',
						'price'     => 720,
						'amenities' => array( 'Water Villa' ),
					),
				),
			),
			array(
				'id'          => 'h-lagoon',
				'name'        => 'Lagoon Crystal Resort',
				'location'    => 'resort',
				'stars'       => 5,
				'review'      => 4.8,
				'reviewLabel' => 'Exceptional',
				'area'        => 'South Atoll',
				'image'       => MPK_PLUGIN_URL . 'assets/images/hotel-2.jpg',
				'amenities'   => array( 'Overwater', 'Spa', 'Fine Dining' ),
				'rooms'       => array(
					array(
						'id'        => 'r1',
						'name'      => 'Sunset Water Villa',
						'meal'      => 'Breakfast & Dinner',
						'price'     => 560,
						'amenities' => array( 'Sea View' ),
					),
					array(
						'id'        => 'r2',
						'name'      => 'Royal Suite with Pool',
						'meal'      => 'All Inclusive',
						'price'     => 890,
						'amenities' => array( 'With Pool' ),
					),
				),
			),
		);
	}

	/**
	 * Get default package settings and policies.
	 *
	 * @return array Settings dictionary.
	 */
	public static function get_default_settings() {
		return array(
			'tax_rate'            => 0.08, // 8%
			'extras'              => 45.00, // $45
			'service_fee'         => 25.00, // $25
			'base_excludes'       => array(
				'International flights',
				'Travel insurance',
				'Personal expenses',
				'Tips & gratuities',
				'Optional excursions',
			),
			'hotel_includes_base' => array(
				'Return airport / speedboat transfers',
				'Daily housekeeping',
				'Welcome drink on arrival',
				'24/7 concierge support',
			),
			'policies'            => array(
				'cancellation' => 'Free cancellation up to 30 days before travel. 50% refund up to 14 days. No refund within 7 days of arrival.',
				'terms'        => 'Bookings are subject to availability and confirmation. Prices are per package and may vary by season.',
				'notes'        => 'Valid passport (6+ months) required. Visa-free for most nationalities. Pack light, breathable clothing.',
			),
		);
	}
}
