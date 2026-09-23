<?php
/**
 * AJAX Handler for Maldives Packages Booking (MPK).
 *
 * Handles secure submission of customer bookings from the frontend wizard.
 *
 * Approved Prefix: MPK / mpk_
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MPK_Ajax_Handler {

	/**
	 * Constructor: register AJAX action hooks for logged-in and guest users.
	 */
	public function __construct() {
		add_action( 'wp_ajax_mpk_submit_booking', array( $this, 'handle_submit_booking' ) );
		add_action( 'wp_ajax_nopriv_mpk_submit_booking', array( $this, 'handle_submit_booking' ) );
		add_action( 'wp_ajax_mpk_delete_booking', array( $this, 'handle_delete_booking' ) );
		add_action( 'wp_ajax_mpk_view_passport', array( $this, 'handle_view_passport' ) );
	}

	/**
	 * Sub-folder (inside uploads) where passport copies are stored.
	 */
	const PASSPORT_SUBDIR = 'mpk-passports';

	/**
	 * Get (and protect) the private passport upload directory.
	 *
	 * @return array{path:string,url:string}
	 */
	public static function get_passport_dir() {
		$uploads = wp_upload_dir( null, false );
		$path    = trailingslashit( $uploads['basedir'] ) . self::PASSPORT_SUBDIR;
		$url     = trailingslashit( $uploads['baseurl'] ) . self::PASSPORT_SUBDIR;

		if ( ! is_dir( $path ) ) {
			wp_mkdir_p( $path );
		}

		// Block direct web access (Apache). For Nginx add: location ~* /uploads/mpk-passports/ { deny all; }
		if ( ! file_exists( $path . '/.htaccess' ) ) {
			@file_put_contents( $path . '/.htaccess', "Options -Indexes\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n" ); // phpcs:ignore
		}
		if ( ! file_exists( $path . '/index.php' ) ) {
			@file_put_contents( $path . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
		}

		return array(
			'path' => $path,
			'url'  => $url,
		);
	}

	/**
	 * upload_dir filter: route the current upload into the private passport folder.
	 *
	 * @param array $dirs Upload dir data.
	 * @return array
	 */
	public static function filter_passport_upload_dir( $dirs ) {
		$dirs['subdir'] = '/' . self::PASSPORT_SUBDIR;
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
		return $dirs;
	}

	/**
	 * Random, non-guessable file name for passport uploads.
	 *
	 * @param string $dir  Directory.
	 * @param string $name Original name.
	 * @param string $ext  Extension incl. dot.
	 * @return string
	 */
	public static function passport_filename( $dir, $name, $ext ) {
		return 'passport-' . strtolower( wp_generate_password( 32, false, false ) ) . strtolower( $ext );
	}

	/**
	 * Resolve a stored passport URL to an absolute file path inside uploads (or false).
	 *
	 * @param string $url Stored passport_file_url.
	 * @return string|false
	 */
	public static function resolve_passport_path( $url ) {
		if ( empty( $url ) ) {
			return false;
		}
		$uploads  = wp_upload_dir( null, false );
		$base_url = set_url_scheme( $uploads['baseurl'] );
		$url      = set_url_scheme( $url );
		if ( 0 !== strpos( $url, $base_url ) ) {
			return false;
		}

		$relative = ltrim( rawurldecode( substr( $url, strlen( $base_url ) ) ), '/' );
		$real     = realpath( trailingslashit( $uploads['basedir'] ) . $relative );
		$base     = realpath( $uploads['basedir'] );
		if ( ! $real || ! $base || 0 !== strpos( $real, $base . DIRECTORY_SEPARATOR ) || ! is_file( $real ) ) {
			return false;
		}

		$ext = strtolower( pathinfo( $real, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'pdf', 'png', 'jpg', 'jpeg', 'jpe', 'webp' ), true ) ) {
			return false;
		}
		return $real;
	}

	/**
	 * Admin-only URL to view a booking's passport copy through the secure proxy.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string
	 */
	public static function get_passport_view_url( $booking_id ) {
		return add_query_arg(
			array(
				'action'     => 'mpk_view_passport',
				'booking_id' => absint( $booking_id ),
				'_wpnonce'   => wp_create_nonce( 'mpk_view_passport_' . absint( $booking_id ) ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * AJAX (admin only): stream a passport file after capability + nonce checks.
	 */
	public function handle_view_passport() {
		$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'maldives-packages' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mpk_view_passport_' . $booking_id );

		$booking = $booking_id ? MPK_Booking_Manager::get_booking_by_id( $booking_id ) : null;
		$path    = $booking ? self::resolve_passport_path( $booking->passport_file_url ) : false;
		if ( ! $path ) {
			wp_die( esc_html__( 'Passport file not found.', 'maldives-packages' ), '', array( 'response' => 404 ) );
		}

		$type = wp_check_filetype( $path );
		$mime = ! empty( $type['type'] ) ? $type['type'] : 'application/octet-stream';

		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $booking->reference_id . '-passport.' . pathinfo( $path, PATHINFO_EXTENSION ) ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Process the booking submission AJAX request.
	 */
	public function handle_submit_booking() {
		// 1. Verify Nonce Security
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'mpk_booking_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security verification failed. Please refresh the page and try again.', 'maldives-packages' ),
				),
				403
			);
		}

		// 2. Extract and sanitize lead traveler data
		$lead_name     = isset( $_POST['lead_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_name'] ) ) : '';
		$lead_email    = isset( $_POST['lead_email'] ) ? sanitize_email( wp_unslash( $_POST['lead_email'] ) ) : '';
		$lead_phone    = isset( $_POST['lead_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_phone'] ) ) : '';
		$lead_country  = isset( $_POST['lead_country'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_country'] ) ) : '';
		$passport_no   = isset( $_POST['passport_no'] ) ? sanitize_text_field( wp_unslash( $_POST['passport_no'] ) ) : '';
		$special_reqs  = isset( $_POST['special_requests'] ) ? sanitize_textarea_field( wp_unslash( $_POST['special_requests'] ) ) : '';
		$payment_method = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '';

		// 3. Validation: Required fields
		if ( empty( $lead_name ) ) {
			wp_send_json_error(
				array(
					'field'   => 'lead_name',
					'message' => __( 'Full name is required.', 'maldives-packages' ),
				),
				400
			);
		}

		if ( empty( $lead_email ) || ! is_email( $lead_email ) ) {
			wp_send_json_error(
				array(
					'field'   => 'lead_email',
					'message' => __( 'A valid email address is required.', 'maldives-packages' ),
				),
				400
			);
		}

		if ( empty( $payment_method ) ) {
			wp_send_json_error(
				array(
					'field'   => 'payment_method',
					'message' => __( 'Please choose a payment method to complete booking.', 'maldives-packages' ),
				),
				400
			);
		}

		// 4. Guest counts (clamped to sane bounds)
		$adults      = isset( $_POST['adults'] ) ? min( 20, max( 1, absint( $_POST['adults'] ) ) ) : 1;
		$children    = isset( $_POST['children'] ) ? min( 20, absint( $_POST['children'] ) ) : 0;
		$infants     = isset( $_POST['infants'] ) ? min( 10, absint( $_POST['infants'] ) ) : 0;
		$rooms_count = isset( $_POST['rooms_count'] ) ? min( 10, max( 1, absint( $_POST['rooms_count'] ) ) ) : 1;

		// 4.1 Rebuild trip details & price server-side (never trust client totals / names)
		$raw_selections = isset( $_POST['selections'] ) ? json_decode( wp_unslash( $_POST['selections'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$trip           = self::build_trip_from_selections( $raw_selections, $adults, $children );
		if ( is_wp_error( $trip ) ) {
			wp_send_json_error(
				array(
					'field'   => 'selections',
					'message' => $trip->get_error_message(),
				),
				400
			);
		}

		$selected_location = $trip['selected_location'];
		$hotel_name        = $trip['hotel_name'];
		$room_name         = $trip['room_name'];
		$check_in          = $trip['check_in'];
		$check_out         = $trip['check_out'];
		$grand_total       = $trip['grand_total'];

		$reference_id = isset( $_POST['reference_id'] ) ? sanitize_text_field( wp_unslash( $_POST['reference_id'] ) ) : '';

		// 5. Handle Secure Passport Copy Upload
		$passport_file_url = '';
		if ( isset( $_FILES['passport_file'] ) && ! empty( $_FILES['passport_file']['name'] ) ) {
			$file = $_FILES['passport_file'];

			// Check for PHP upload error codes
			if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== $file['error'] ) {
				error_log( '[MPK] Passport upload PHP error: ' . $file['error'] );
				wp_send_json_error(
					array(
						'field'   => 'passport_file',
						'message' => __( 'File upload failed on server. Please check file size.', 'maldives-packages' ),
					),
					400
				);
			}

			// Max size 10MB
			if ( $file['size'] > 10 * 1024 * 1024 ) {
				wp_send_json_error(
					array(
						'field'   => 'passport_file',
						'message' => __( 'Passport file size must be less than 10MB.', 'maldives-packages' ),
					),
					400
				);
			}

			// Validate allowed mime types & extensions
			$allowed_mimes = array(
				'pdf'          => 'application/pdf',
				'png'          => 'image/png',
				'jpg|jpeg|jpe' => 'image/jpeg',
				'webp'         => 'image/webp',
			);

			$check_file = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );
			if ( empty( $check_file['ext'] ) || empty( $check_file['type'] ) ) {
				// Fallback to extension check if finfo on Windows/custom setup is ambiguous
				$check_ext = wp_check_filetype( $file['name'], $allowed_mimes );
				if ( empty( $check_ext['ext'] ) || empty( $check_ext['type'] ) ) {
					wp_send_json_error(
						array(
							'field'   => 'passport_file',
							'message' => __( 'Only PDF, PNG, JPG, JPEG, and WEBP formats are allowed for passport copy.', 'maldives-packages' ),
						),
						400
					);
				}
			}

			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			// Only accept genuine HTTP uploads.
			if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
				wp_send_json_error(
					array(
						'field'   => 'passport_file',
						'message' => __( 'Invalid file upload.', 'maldives-packages' ),
					),
					400
				);
			}

			$upload_overrides = array(
				'test_form'                => false,
				'mimes'                    => $allowed_mimes,
				'unique_filename_callback' => array( __CLASS__, 'passport_filename' ),
			);

			// Store in private, web-blocked folder with a random file name.
			self::get_passport_dir();
			add_filter( 'upload_dir', array( __CLASS__, 'filter_passport_upload_dir' ) );
			$movefile = wp_handle_upload( $file, $upload_overrides );
			remove_filter( 'upload_dir', array( __CLASS__, 'filter_passport_upload_dir' ) );

			if ( $movefile && ! isset( $movefile['error'] ) ) {
				$passport_file_url = esc_url_raw( $movefile['url'] );
			} else {
				$upload_err = ! empty( $movefile['error'] ) ? $movefile['error'] : __( 'Failed to upload passport attachment.', 'maldives-packages' );
				wp_send_json_error(
					array(
						'field'   => 'passport_file',
						'message' => $upload_err,
					),
					400
				);
			}
		}

		// 5.1 Build booking record payload
		$booking_data = array(
			'reference_id'      => $reference_id,
			'lead_name'         => $lead_name,
			'lead_email'        => $lead_email,
			'lead_phone'        => $lead_phone,
			'lead_country'      => $lead_country,
			'passport_no'       => $passport_no,
			'passport_file_url' => $passport_file_url,
			'special_requests'  => $special_reqs,
			'selected_location' => $selected_location,
			'hotel_name'        => $hotel_name,
			'room_name'         => $room_name,
			'check_in'          => $check_in,
			'check_out'         => $check_out,
			'adults'            => $adults,
			'children'          => $children,
			'infants'           => $infants,
			'rooms_count'       => $rooms_count,
			'grand_total'       => $grand_total,
			'payment_method'    => $payment_method,
			'status'            => 'Pending',
		);

		// 6. Save booking using Booking Manager
		$result = MPK_Booking_Manager::create_booking( $booking_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				),
				500
			);
		}

		// 6.1 Trigger automated emails (Customer Confirmation & Admin Alert)
		$mail_payload                 = $booking_data;
		$mail_payload['id']           = $result['id'];
		$mail_payload['reference_id'] = $result['reference_id'];

		if ( class_exists( 'MPK_Mailer' ) ) {
			try {
				MPK_Mailer::send_booking_confirmation( $mail_payload );
			} catch ( \Throwable $e ) {
				// Non-blocking: ensure mail error never blocks successful booking response
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'MPK_Mailer Trigger Exception: ' . $e->getMessage() );
				}
			}
		}

		// 7. Successful Response
		wp_send_json_success(
			array(
				'reference_id' => $result['reference_id'],
				'booking_id'   => $result['id'],
				'grand_total'  => round( $grand_total, 2 ),
				'message'      => __( 'Booking saved successfully', 'maldives-packages' ),
			)
		);
	}

	/**
	 * Validate client room selections against stored hotel data and compute the
	 * authoritative booking summary + grand total (mirrors calcPricing() in mpk-main.js).
	 *
	 * @param mixed $selections Decoded selections array from the request.
	 * @param int   $adults     Adult count.
	 * @param int   $children   Children count.
	 * @return array|WP_Error
	 */
	private static function build_trip_from_selections( $selections, $adults, $children ) {
		if ( ! is_array( $selections ) || empty( $selections ) || count( $selections ) > 20 ) {
			return new WP_Error( 'mpk_invalid_selection', __( 'Please select at least one room with valid dates.', 'maldives-packages' ) );
		}

		$today       = current_time( 'Y-m-d' );
		$base        = 0.0;
		$hotel_names = array();
		$room_names  = array();
		$loc_names   = array();
		$min_in      = '';
		$max_out     = '';

		foreach ( $selections as $sel ) {
			if ( ! is_array( $sel ) ) {
				return new WP_Error( 'mpk_invalid_selection', __( 'Invalid room selection.', 'maldives-packages' ) );
			}

			$hotel_id = isset( $sel['hotel_id'] ) ? sanitize_text_field( (string) $sel['hotel_id'] ) : '';
			$room_id  = isset( $sel['room_id'] ) ? sanitize_text_field( (string) $sel['room_id'] ) : '';
			$in       = isset( $sel['check_in'] ) ? sanitize_text_field( (string) $sel['check_in'] ) : '';
			$out      = isset( $sel['check_out'] ) ? sanitize_text_field( (string) $sel['check_out'] ) : '';

			$hotel = MPK_Data_Manager::get_hotel( $hotel_id );
			$room  = null;
			if ( $hotel && ! empty( $hotel['rooms'] ) && is_array( $hotel['rooms'] ) ) {
				foreach ( $hotel['rooms'] as $rm ) {
					if ( isset( $rm['id'] ) && (string) $rm['id'] === $room_id ) {
						$room = $rm;
						break;
					}
				}
			}
			if ( ! $hotel || ! $room ) {
				return new WP_Error( 'mpk_invalid_room', __( 'A selected hotel or room is no longer available. Please refresh and try again.', 'maldives-packages' ) );
			}

			$d_in  = DateTime::createFromFormat( '!Y-m-d', $in );
			$d_out = DateTime::createFromFormat( '!Y-m-d', $out );
			if ( ! $d_in || ! $d_out || $d_in->format( 'Y-m-d' ) !== $in || $d_out->format( 'Y-m-d' ) !== $out ) {
				return new WP_Error( 'mpk_invalid_dates', __( 'Please choose valid check-in and check-out dates.', 'maldives-packages' ) );
			}
			if ( $in < $today ) {
				return new WP_Error( 'mpk_invalid_dates', __( 'Check-in date cannot be in the past.', 'maldives-packages' ) );
			}
			$nights = (int) $d_in->diff( $d_out )->format( '%r%a' );
			if ( $nights < 1 || $nights > 90 ) {
				return new WP_Error( 'mpk_invalid_dates', __( 'Check-out must be after check-in (max 90 nights).', 'maldives-packages' ) );
			}

			$price = isset( $room['price'] ) ? max( 0, (float) $room['price'] ) : 0.0;
			$base += $price * $nights;

			if ( ! in_array( $hotel['name'], $hotel_names, true ) ) {
				$hotel_names[] = $hotel['name'];
			}
			if ( ! in_array( $room['name'], $room_names, true ) ) {
				$room_names[] = $room['name'];
			}
			$loc      = ! empty( $hotel['location'] ) ? MPK_Data_Manager::get_location( $hotel['location'] ) : null;
			$loc_name = $loc && ! empty( $loc['name'] ) ? $loc['name'] : ( isset( $hotel['location'] ) ? $hotel['location'] : '' );
			if ( $loc_name && ! in_array( $loc_name, $loc_names, true ) ) {
				$loc_names[] = $loc_name;
			}

			if ( ! $min_in || $in < $min_in ) {
				$min_in = $in;
			}
			if ( ! $max_out || $out > $max_out ) {
				$max_out = $out;
			}
		}

		// Pricing formula - keep in sync with calcPricing() in assets/js/mpk-main.js.
		$total = 0.0;
		if ( $base > 0 ) {
			$settings = MPK_Data_Manager::get_settings();
			$tax_rate = isset( $settings['tax_rate'] ) && is_numeric( $settings['tax_rate'] ) ? (float) $settings['tax_rate'] : 0.08;
			$extras   = isset( $settings['extras'] ) && is_numeric( $settings['extras'] ) ? (float) $settings['extras'] : 45.0;
			$service  = isset( $settings['service_fee'] ) && is_numeric( $settings['service_fee'] ) ? (float) $settings['service_fee'] : 25.0;

			$child_factor = 0.35;
			$child_disc   = isset( $settings['child_discount_pct'] ) && is_numeric( $settings['child_discount_pct'] ) ? (float) $settings['child_discount_pct'] : 0;
			if ( $child_disc > 0 ) {
				$child_factor = max( 0, 0.55 * ( 1 - ( $child_disc / 100 ) ) );
			}

			$subtotal = ( $base * max( 1, $adults ) * 0.55 ) + ( $base * $children * $child_factor );
			$total    = $subtotal + $extras + ( $subtotal * $tax_rate ) + $service;
		}

		return array(
			'selected_location' => implode( ', ', $loc_names ),
			'hotel_name'        => implode( ', ', $hotel_names ),
			'room_name'         => implode( ', ', $room_names ),
			'check_in'          => $min_in,
			'check_out'         => $max_out,
			'grand_total'       => round( $total, 2 ),
		);
	}

	/**
	 * AJAX Handler: Permanently delete a booking record (Administrator only).
	 */
	public function handle_delete_booking() {
		// 1. Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied. Administrator access required.', 'maldives-packages' ) ),
				403
			);
		}

		// 2. Nonce verification (supports mpk_admin_nonce)
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'mpk_admin_nonce' ) && ! wp_verify_nonce( $nonce, 'mpk_booking_nonce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security verification failed. Please refresh the page and try again.', 'maldives-packages' ) ),
				403
			);
		}

		// 3. Booking ID validation
		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		if ( ! $booking_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid booking ID.', 'maldives-packages' ) ),
				400
			);
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'mpk_bookings';

		// Clean up attached passport copy file (only files inside the private passport folder)
		$passport_url = $wpdb->get_var( $wpdb->prepare( "SELECT passport_file_url FROM {$table_name} WHERE id = %d", $booking_id ) );
		$file_path    = self::resolve_passport_path( $passport_url );
		if ( $file_path && false !== strpos( $file_path, DIRECTORY_SEPARATOR . self::PASSPORT_SUBDIR . DIRECTORY_SEPARATOR ) ) {
			wp_delete_file( $file_path );
		}

		// Delete record from custom table
		$deleted = $wpdb->delete(
			$table_name,
			array( 'id' => $booking_id ),
			array( '%d' )
		);

		if ( false === $deleted || 0 === $deleted ) {
			wp_send_json_error(
				array( 'message' => __( 'Failed to delete booking or record already removed.', 'maldives-packages' ) ),
				500
			);
		}

		// Recalculate summary stats for admin dashboard
		$count_pending   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'Pending'" );
		$count_approved  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status IN ('Approved', 'Confirmed')" );
		$count_cancelled = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'Cancelled'" );
		$count_total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

		wp_send_json_success(
			array(
				'booking_id' => $booking_id,
				'message'    => __( 'Booking record permanently deleted.', 'maldives-packages' ),
				'counts'     => array(
					'total'     => $count_total,
					'pending'   => $count_pending,
					'approved'  => $count_approved,
					'cancelled' => $count_cancelled,
				),
			)
		);
	}
}
