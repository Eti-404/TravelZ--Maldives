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

			$synced = array();
			foreach ( $terms as $term ) {
				$slug = $term->slug;
				if ( ! isset( $loc_by_id[ $slug ] ) ) {
					// New destination created in admin
					$loc = array(
						'id'      => $slug,
						'name'    => $term->name,
						'tagline' => ! empty( $term->description ) ? $term->description : __( 'Pristine island getaway', 'maldives-packages' ),
						'image'   => $fallback_images[ $img_idx % count( $fallback_images ) ],
						'nights'  => 2,
					);
					$img_idx++;
				} else {
					$loc         = $loc_by_id[ $slug ];
					$loc['name'] = $term->name;
					if ( ! empty( $term->description ) ) {
						$loc['tagline'] = $term->description;
					}
				}

				// Image chosen in admin (Destinations > Destination Image) wins over defaults.
				$term_image_id = (int) get_term_meta( $term->term_id, '_mpk_destination_image_id', true );
				if ( $term_image_id ) {
					$term_image = wp_get_attachment_image_url( $term_image_id, 'large' );
					if ( $term_image ) {
						$loc['image'] = $term_image;
					}
				}

				$synced[ $slug ] = $loc;
			}

			// Only destinations that still exist in admin are offered in the wizard.
			$loc_by_id = $synced;

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

		// Default display order of seeded hotels (matches the reference design).
		$default_orders = array();
		foreach ( MPK_Seeder::get_default_hotels() as $dh ) {
			if ( ! empty( $dh['id'] ) && ! empty( $dh['menu_order'] ) ) {
				$default_orders[ $dh['id'] ] = (int) $dh['menu_order'];
			}
		}

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

				// Safely convert string or serialized data into array
				if ( is_string( $rooms ) ) {
					$rooms = maybe_unserialize( $rooms );
					if ( is_string( $rooms ) ) {
						$decoded = json_decode( $rooms, true );
						$rooms   = is_array( $decoded ) ? $decoded : array();
					}
				}
				if ( ! is_array( $rooms ) && ! is_object( $rooms ) ) {
					$rooms = array();
				}

				// Fallback standard room if no rooms added by admin
				if ( empty( $rooms ) ) {
					$rooms = array(
						array(
							'id'        => 'r1',
							'name'      => __( 'Standard Deluxe Room', 'maldives-packages' ),
							'meal'      => 'Breakfast Included',
							'price'     => 140.00,
							'room_type' => 'Balcony',
							'bed_type'  => 'Double',
							'amenities' => array( 'Double', 'Air Conditioning', 'Free Wifi' ),
						),
					);
				}

				// Normalize rooms to ensure room_type and bed_type are always present
				$normalized_rooms = array();
				foreach ( $rooms as $rm ) {
					if ( ! is_array( $rm ) ) {
						continue;
					}
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

				$menu_order = isset( $p->menu_order ) && $p->menu_order > 0 ? (int) $p->menu_order : (int) get_post_meta( $post_id, '_mpk_menu_order', true );
				if ( $menu_order <= 0 && isset( $default_orders[ $hotel_id ] ) ) {
					// Older installs were seeded without an order: fall back to the reference order.
					$menu_order = $default_orders[ $hotel_id ];
				}

				$hotels[] = array(
					'id'          => $hotel_id,
					'name'        => $p->post_title,
					'location'    => $loc_slug,
					'menu_order'  => $menu_order,
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

		// Sort hotels by menu_order ASC, then fallback to title
		usort( $hotels, function( $a, $b ) {
			$order_a = isset( $a['menu_order'] ) && $a['menu_order'] > 0 ? (int) $a['menu_order'] : 99;
			$order_b = isset( $b['menu_order'] ) && $b['menu_order'] > 0 ? (int) $b['menu_order'] : 99;
			if ( $order_a === $order_b ) {
				return strcmp( $a['name'], $b['name'] );
			}
			return $order_a - $order_b;
		} );

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
		if ( ! class_exists( 'MPK_Settings' ) ) {
			// Defaults (bank / office / support details) are needed on the frontend too.
			require_once MPK_PLUGIN_DIR . 'admin/class-mpk-settings.php';
		}
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

		// WooCommerce checkout: bank details come from WooCommerce -> Payments -> Direct bank transfer.
		if ( class_exists( 'MPK_WooCommerce' ) ) {
			$bacs = MPK_WooCommerce::bacs_account();
			if ( $bacs ) {
				$merged = array_merge( $merged, $bacs );
			}
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
	 * Supported currencies: code => [label, symbol, default decimals].
	 *
	 * @return array
	 */
	public static function get_currency_list() {
		return (array) apply_filters(
			'mpk_currency_list',
			array(
				'BDT' => array( 'Bangladeshi Taka', '৳', 0 ),
				'USD' => array( 'US Dollar', '$', 2 ),
				'EUR' => array( 'Euro', '€', 2 ),
				'GBP' => array( 'British Pound', '£', 2 ),
				'INR' => array( 'Indian Rupee', '₹', 0 ),
				'MVR' => array( 'Maldivian Rufiyaa', 'Rf', 2 ),
				'AED' => array( 'UAE Dirham', 'AED', 2 ),
				'SAR' => array( 'Saudi Riyal', 'SAR', 2 ),
				'MYR' => array( 'Malaysian Ringgit', 'RM', 2 ),
				'SGD' => array( 'Singapore Dollar', 'S$', 2 ),
				'THB' => array( 'Thai Baht', '฿', 2 ),
				'LKR' => array( 'Sri Lankan Rupee', 'Rs', 0 ),
				'PKR' => array( 'Pakistani Rupee', '₨', 0 ),
				'NPR' => array( 'Nepalese Rupee', 'Rs', 0 ),
				'CNY' => array( 'Chinese Yuan', '¥', 2 ),
				'JPY' => array( 'Japanese Yen', '¥', 0 ),
				'AUD' => array( 'Australian Dollar', 'A$', 2 ),
				'CAD' => array( 'Canadian Dollar', 'C$', 2 ),
			)
		);
	}

	/**
	 * Active currency settings (one place for every price shown anywhere).
	 *
	 * @return array{code:string,symbol:string,position:string,decimals:int}
	 */
	public static function get_currency() {
		// WooCommerce checkout: WooCommerce's currency is the single source of truth.
		if ( class_exists( 'MPK_WooCommerce' ) && MPK_WooCommerce::controls_currency() ) {
			return MPK_WooCommerce::get_wc_currency();
		}

		$s    = get_option( 'mpk_settings', array() );
		$list = self::get_currency_list();

		$code = isset( $s['currency_code'] ) ? strtoupper( (string) $s['currency_code'] ) : '';
		$sym  = isset( $s['currency_symbol'] ) ? (string) $s['currency_symbol'] : '$';

		// Older installs only stored a symbol: map it back to a known currency.
		if ( '' === $code ) {
			$code = 'CUSTOM';
			foreach ( $list as $c => $row ) {
				if ( $row[1] === $sym ) {
					$code = $c;
					break;
				}
			}
			if ( '$' === $sym ) {
				$code = 'USD';
			}
		}

		if ( 'CUSTOM' !== $code && isset( $list[ $code ] ) ) {
			$sym              = $list[ $code ][1];
			$default_decimals = (int) $list[ $code ][2];
		} else {
			$code             = 'CUSTOM';
			$sym              = '' !== trim( $sym ) ? $sym : '$';
			$default_decimals = 2;
		}

		$position = isset( $s['currency_position'] ) && in_array( $s['currency_position'], array( 'left', 'left_space', 'right', 'right_space' ), true ) ? $s['currency_position'] : 'left';
		$decimals = isset( $s['currency_decimals'] ) && '' !== $s['currency_decimals'] ? min( 2, max( 0, (int) $s['currency_decimals'] ) ) : $default_decimals;

		return array(
			'code'     => $code,
			'symbol'   => $sym,
			'position' => $position,
			'decimals' => $decimals,
		);
	}

	/**
	 * Format an amount with the active currency, e.g. "৳12,500" or "12,500.00 €".
	 *
	 * @param float    $amount   Amount.
	 * @param int|null $decimals Decimals (null = currency default).
	 * @return string
	 */
	public static function format_price( $amount, $decimals = null ) {
		$c        = self::get_currency();
		$decimals = null === $decimals ? $c['decimals'] : (int) $decimals;
		$num      = number_format( (float) $amount, $decimals, '.', ',' );
		switch ( $c['position'] ) {
			case 'left_space':
				return $c['symbol'] . ' ' . $num;
			case 'right':
				return $num . $c['symbol'];
			case 'right_space':
				return $num . ' ' . $c['symbol'];
			default:
				return $c['symbol'] . $num;
		}
	}

	/**
	 * Short label for admin field labels, e.g. "৳ BDT" or "€".
	 *
	 * @return string
	 */
	public static function currency_label() {
		$c = self::get_currency();
		return 'CUSTOM' === $c['code'] ? $c['symbol'] : $c['symbol'] . ' ' . $c['code'];
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
			'currency'   => self::get_currency(),
			'wc_checkout' => class_exists( 'MPK_WooCommerce' ) && MPK_WooCommerce::is_enabled(),
			'occupancy'  => class_exists( 'MPK_Ajax_Handler' ) ? MPK_Ajax_Handler::get_occupancy_rules() : array( 'max_adults' => 3, 'max_guests' => 4, 'max_infants' => 2 ),
		);
	}
}
