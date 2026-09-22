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

		// Dynamically synchronize with mpk_destination taxonomy terms
		$terms = get_terms(
			array(
				'taxonomy'   => 'mpk_destination',
				'hide_empty' => false,
			)
		);

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$loc_by_id = array();
			foreach ( $locations as $loc ) {
				if ( isset( $loc['id'] ) ) {
					$loc_by_id[ $loc['id'] ] = $loc;
				}
			}

			// Fallback location images
			$fallback_images = array(
				MPK_PLUGIN_URL . 'assets/images/loc-hulhumale.jpg',
				MPK_PLUGIN_URL . 'assets/images/loc-maafushi.jpg',
				MPK_PLUGIN_URL . 'assets/images/loc-resort.jpg',
			);
			$img_idx = 0;

			foreach ( $terms as $term ) {
				$slug = $term->slug;
				if ( ! isset( $loc_by_id[ $slug ] ) ) {
					// New destination created in admin
					$loc_by_id[ $slug ] = array(
						'id'      => $slug,
						'name'    => $term->name,
						'tagline' => ! empty( $term->description ) ? $term->description : __( 'Pristine island getaway', 'maldives-packages' ),
						'image'   => $fallback_images[ $img_idx % count( $fallback_images ) ],
						'nights'  => 2,
					);
					$img_idx++;
				} else {
					$loc_by_id[ $slug ]['name'] = $term->name;
					if ( ! empty( $term->description ) ) {
						$loc_by_id[ $slug ]['tagline'] = $term->description;
					}
				}
			}

			$locations = array_values( $loc_by_id );
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
	 * Get all hotels, dynamically queried from published mpk_hotel posts.
	 *
	 * @param string|null $location_id Optional location filter.
	 * @return array List of hotels.
	 */
	public static function get_hotels( $location_id = null ) {
		$posts = get_posts(
			array(
				'post_type'      => 'mpk_hotel',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);

		$hotels = array();

		if ( ! empty( $posts ) ) {
			foreach ( $posts as $p ) {
				$post_id = $p->ID;

				// Hotel identifier
				$hotel_id = get_post_meta( $post_id, '_mpk_hotel_id', true );
				if ( empty( $hotel_id ) ) {
					$hotel_id = ! empty( $p->post_name ) ? $p->post_name : 'h-' . $post_id;
				}

				// Destination / Location
				$loc_slug = '';
				$terms = wp_get_post_terms( $post_id, 'mpk_destination' );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					$loc_slug = $terms[0]->slug;
				}
				if ( empty( $loc_slug ) ) {
					$loc_slug = get_post_meta( $post_id, '_mpk_location', true );
				}
				if ( empty( $loc_slug ) ) {
					$loc_slug = 'resort';
				}

				// Star Rating (1 to 5)
				$stars = get_post_meta( $post_id, '_mpk_stars', true );
				$stars = ! empty( $stars ) ? absint( $stars ) : 4;
				if ( $stars < 1 || $stars > 5 ) {
					$stars = 4;
				}

				// Review Score & Label
				$review = get_post_meta( $post_id, '_mpk_review', true );
				$review = ! empty( $review ) ? floatval( $review ) : 4.7;

				$review_label = get_post_meta( $post_id, '_mpk_review_label', true );
				if ( empty( $review_label ) ) {
					if ( $review >= 4.8 ) {
						$review_label = 'Exceptional';
					} elseif ( $review >= 4.5 ) {
						$review_label = 'Excellent';
					} else {
						$review_label = 'Very Good';
					}
				}

				// Area / Neighborhood
				$area = get_post_meta( $post_id, '_mpk_area', true );
				if ( empty( $area ) ) {
					if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						$area = $terms[0]->name;
					} else {
						$area = 'Maldives';
					}
				}

				// Featured Image URL
				$image = '';
				if ( has_post_thumbnail( $post_id ) ) {
					$image = get_the_post_thumbnail_url( $post_id, 'large' );
				}
				if ( empty( $image ) ) {
					$image = get_post_meta( $post_id, '_mpk_image_url', true );
				}
				if ( empty( $image ) ) {
					if ( 'hulhumale' === $loc_slug ) {
						$image = MPK_PLUGIN_URL . 'assets/images/hotel-3.jpg';
					} elseif ( 'maafushi' === $loc_slug ) {
						$image = MPK_PLUGIN_URL . 'assets/images/hotel-1.jpg';
					} else {
						$image = MPK_PLUGIN_URL . 'assets/images/hotel-2.jpg';
					}
				}

				// Amenities / Highlights
				$amenities = get_post_meta( $post_id, '_mpk_amenities', true );
				if ( empty( $amenities ) || ! is_array( $amenities ) ) {
					$amenities = array( 'Pool', 'Wifi', 'Restaurant', 'Airport Transfer' );
				}

				// Rooms from dynamic repeater
				$rooms = get_post_meta( $post_id, '_mpk_hotel_rooms', true );
				if ( empty( $rooms ) || ! is_array( $rooms ) ) {
					$rooms = get_post_meta( $post_id, '_mpk_rooms', true );
				}

				// Normalize rooms to ensure room_type and bed_type are always present
				$normalized_rooms = array();
				foreach ( $rooms as $rm ) {
					$rm_name        = isset( $rm['name'] ) ? $rm['name'] : '';
					$rm_amenities   = isset( $rm['amenities'] ) && is_array( $rm['amenities'] ) ? $rm['amenities'] : array();
					$amenities_text = strtolower( $rm_name . ' ' . implode( ' ', $rm_amenities ) );

					// Room type normalization
					$rm_type = isset( $rm['room_type'] ) && ! empty( $rm['room_type'] ) ? $rm['room_type'] : '';
					if ( empty( $rm_type ) ) {
						if ( strpos( $amenities_text, 'water villa' ) !== false || strpos( $amenities_text, 'overwater' ) !== false ) {
							$rm_type = 'Water Villa';
						} elseif ( strpos( $amenities_text, 'pool' ) !== false ) {
							$rm_type = 'With Pool';
						} elseif ( strpos( $amenities_text, 'sea view' ) !== false || strpos( $amenities_text, 'ocean' ) !== false ) {
							$rm_type = 'Sea View';
						} elseif ( strpos( $amenities_text, 'island view' ) !== false || strpos( $amenities_text, 'garden' ) !== false || strpos( $amenities_text, 'city' ) !== false ) {
							$rm_type = 'Island View';
						} else {
							$rm_type = 'Balcony';
						}
					}

					// Bed type normalization
					$rm_bed = isset( $rm['bed_type'] ) && ! empty( $rm['bed_type'] ) ? $rm['bed_type'] : ( isset( $rm['bed'] ) ? $rm['bed'] : '' );
					if ( empty( $rm_bed ) ) {
						if ( strpos( $amenities_text, 'single' ) !== false ) {
							$rm_bed = 'Single';
						} elseif ( strpos( $amenities_text, 'twin' ) !== false ) {
							$rm_bed = 'Twin';
						} elseif ( strpos( $amenities_text, 'triple' ) !== false ) {
							$rm_bed = 'Triple';
						} elseif ( strpos( $amenities_text, 'king' ) !== false || strpos( $amenities_text, 'master' ) !== false ) {
							$rm_bed = 'King';
						} else {
							$rm_bed = 'Double';
						}
					}

					// Ensure amenities include bed and room type for search resilience
					if ( ! in_array( $rm_bed, $rm_amenities, true ) ) {
						$rm_amenities[] = $rm_bed;
					}
					if ( ! in_array( $rm_type, $rm_amenities, true ) ) {
						$rm_amenities[] = $rm_type;
					}

					$normalized_rooms[] = array(
						'id'        => isset( $rm['id'] ) ? $rm['id'] : 'r1',
						'name'      => $rm_name,
						'meal'      => isset( $rm['meal'] ) ? $rm['meal'] : 'Breakfast',
						'price'     => isset( $rm['price'] ) ? floatval( $rm['price'] ) : 100.0,
						'room_type' => $rm_type,
						'bed_type'  => $rm_bed,
						'amenities' => $rm_amenities,
					);
				}
				$rooms = $normalized_rooms;

				$hotels[] = array(
					'id'          => $hotel_id,
					'name'        => $p->post_title,
					'location'    => $loc_slug,
					'stars'       => $stars,
					'review'      => $review,
					'reviewLabel' => $review_label,
					'area'        => $area,
					'image'       => $image,
					'amenities'   => $amenities,
					'rooms'       => $rooms,
				);
			}
		}

		// Fallback to seeder hotels if no published posts exist
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
			if ( isset( $h['id'] ) && ( $h['id'] === $hotel_id || (string) $h['id'] === (string) $hotel_id ) ) {
				return $h;
			}
		}
		if ( is_numeric( $hotel_id ) ) {
			foreach ( $hotels as $h ) {
				if ( isset( $h['id'] ) && 'h-' . $hotel_id === $h['id'] ) {
					return $h;
				}
			}
		}
		return null;
	}

	/**
	 * Get package settings (fees, taxes, policies, concierge).
	 *
	 * @return array Settings dictionary.
	 */
	public static function get_settings() {
		$settings = get_option( 'mpk_settings', array() );
		$defaults = class_exists( 'MPK_Settings' ) ? MPK_Settings::get_defaults() : array();
		$seed_defaults = class_exists( 'MPK_Seeder' ) ? MPK_Seeder::get_default_settings() : array();

		$merged = wp_parse_args( $settings, wp_parse_args( $defaults, $seed_defaults ) );

		// Ensure tax_rate is decimal (0.08)
		if ( isset( $merged['tax_rate'] ) ) {
			$tax_val = floatval( $merged['tax_rate'] );
			if ( $tax_val > 1.0 ) {
				$merged['tax_rate'] = $tax_val / 100.0;
			}
		}

		// Sync inclusions/exclusions arrays
		if ( ! empty( $merged['package_inclusions'] ) && is_string( $merged['package_inclusions'] ) ) {
			$inc_lines = array_filter( array_map( 'trim', explode( "\n", $merged['package_inclusions'] ) ) );
			$merged['hotel_includes_base'] = array_values( $inc_lines );
		}
		if ( ! empty( $merged['package_excludes'] ) && is_string( $merged['package_excludes'] ) ) {
			$exc_lines = array_filter( array_map( 'trim', explode( "\n", $merged['package_excludes'] ) ) );
			$merged['base_excludes'] = array_values( $exc_lines );
		}

		if ( ! isset( $merged['policies'] ) || ! is_array( $merged['policies'] ) ) {
			$merged['policies'] = array();
		}
		if ( ! empty( $merged['cancellation_policy'] ) ) {
			$merged['policies']['cancellation'] = $merged['cancellation_policy'];
		}
		if ( ! empty( $merged['emergency_terms'] ) ) {
			$merged['policies']['terms'] = $merged['emergency_terms'];
		}
		if ( ! empty( $merged['passport_notice'] ) ) {
			$merged['policies']['notes'] = $merged['passport_notice'];
		}

		return $merged;
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
