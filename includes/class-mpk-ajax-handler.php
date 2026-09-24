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
		add_action( 'wp_ajax_mpk_get_nonce', array( $this, 'handle_get_nonce' ) );
		add_action( 'wp_ajax_nopriv_mpk_get_nonce', array( $this, 'handle_get_nonce' ) );
	}

	/**
	 * AJAX: return a fresh booking nonce.
	 *
	 * Full-page caches can serve an expired nonce embedded in the HTML; the wizard
	 * fetches a fresh one right before submitting. The nonce is bound to the current
	 * user session, and cross-origin pages cannot read this response.
	 */
	public function handle_get_nonce() {
		nocache_headers();
		wp_send_json_success(
			array( 'nonce' => wp_create_nonce( 'mpk_booking_nonce' ) )
		);
	}

	/**
	 * Room occupancy limits (filterable). Infants are not counted as guests.
	 *
	 * @return array{max_adults:int,max_guests:int,max_infants:int}
	 */
	public static function get_occupancy_rules() {
		$rules = (array) apply_filters(
			'mpk_room_occupancy',
			array(
				'max_adults'  => 3, // Adults per room.
				'max_guests'  => 4, // Adults + children per room.
				'max_infants' => 2, // Infants per room.
			)
		);
		return array(
			'max_adults'  => max( 1, (int) $rules['max_adults'] ),
			'max_guests'  => max( 1, (int) $rules['max_guests'] ),
			'max_infants' => max( 0, (int) $rules['max_infants'] ),
		);
	}

	/**
	 * Validate guest counts against room occupancy limits.
	 *
	 * @return true|WP_Error
	 */
	public static function validate_occupancy( $adults, $children, $infants, $rooms ) {
		$o = self::get_occupancy_rules();
		if ( $rooms > $adults ) {
			return new WP_Error( 'mpk_occupancy', __( 'Each room needs at least one adult.', 'maldives-packages' ) );
		}
		if ( $adults > $o['max_adults'] * $rooms || ( $adults + $children ) > $o['max_guests'] * $rooms ) {
			/* translators: 1: max adults per room, 2: max guests per room */
			return new WP_Error( 'mpk_occupancy', sprintf( __( 'Too many guests for the selected rooms (max %1$d adults / %2$d guests per room). Please add another room.', 'maldives-packages' ), $o['max_adults'], $o['max_guests'] ) );
		}
		if ( $infants > $o['max_infants'] * $rooms ) {
			/* translators: %d: max infants per room */
			return new WP_Error( 'mpk_occupancy', sprintf( __( 'Maximum %d infants per room. Please add another room.', 'maldives-packages' ), $o['max_infants'] ) );
		}
		return true;
	}

	/**
	 * Allowed payment methods ("card" is disabled in the UI until a gateway exists).
	 *
	 * @return string[]
	 */
	public static function get_allowed_payment_methods() {
		return (array) apply_filters( 'mpk_allowed_payment_methods', array( 'office', 'bank' ) );
	}

	/**
	 * Per-IP rate limiter key + settings.
	 *
	 * @return array{key:string,max:int,window:int}
	 */
	private static function rate_limit_config() {
		// REMOTE_ADDR only - forwarded headers are client-controlled and spoofable.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return array(
			'key'    => 'mpk_rl_' . md5( $ip ),
			'max'    => (int) apply_filters( 'mpk_booking_rate_limit_max', 10 ),
			'window' => (int) apply_filters( 'mpk_booking_rate_limit_window', 15 * MINUTE_IN_SECONDS ),
		);
	}

	/**
	 * Whether this IP may submit another booking.
	 * Only successful bookings are counted (see record_rate_limit_hit), so
	 * validation errors never lock a real customer out. Administrators are exempt.
	 *
	 * @return int 0 when allowed, otherwise seconds until the next booking is possible.
	 */
	private static function check_rate_limit() {
		if ( current_user_can( 'manage_options' ) ) {
			return 0;
		}
		$c = self::rate_limit_config();
		if ( $c['max'] <= 0 ) {
			return 0;
		}
		$data = get_transient( $c['key'] );
		if ( ! is_array( $data ) || empty( $data['start'] ) ) {
			return 0;
		}
		$elapsed = time() - (int) $data['start'];
		if ( $elapsed > $c['window'] || (int) $data['count'] < $c['max'] ) {
			return 0;
		}
		return max( 1, $c['window'] - $elapsed );
	}

	/**
	 * Count one successful booking for this IP.
	 */
	private static function record_rate_limit_hit() {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		$c = self::rate_limit_config();
		if ( $c['max'] <= 0 ) {
			return;
		}
		$data = get_transient( $c['key'] );
		if ( ! is_array( $data ) || empty( $data['start'] ) || ( time() - (int) $data['start'] ) > $c['window'] ) {
			$data = array(
				'start' => time(),
				'count' => 0,
			);
		}
		$data['count'] = (int) $data['count'] + 1;
		set_transient( $c['key'], $data, $c['window'] );
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
	 * Count bookings whose passport file still lives outside the private folder.
	 *
	 * @return int
	 */
	public static function count_legacy_passports() {
		global $wpdb;
		$table = MPK_Booking_Manager::get_table_name();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE passport_file_url <> '' AND passport_file_url NOT LIKE %s",
				'%/' . $wpdb->esc_like( self::PASSPORT_SUBDIR ) . '/%'
			)
		);
	}

	/**
	 * Move legacy (public) passport files into the private folder with random names
	 * and update each booking's stored URL.
	 *
	 * @param int $limit Max records per run.
	 * @return array{moved:int,missing:int,failed:int}
	 */
	public static function migrate_legacy_passports( $limit = 200 ) {
		global $wpdb;
		$table  = MPK_Booking_Manager::get_table_name();
		$result = array(
			'moved'   => 0,
			'missing' => 0,
			'failed'  => 0,
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, passport_file_url FROM {$table} WHERE passport_file_url <> '' AND passport_file_url NOT LIKE %s ORDER BY id ASC LIMIT %d",
				'%/' . $wpdb->esc_like( self::PASSPORT_SUBDIR ) . '/%',
				absint( $limit )
			)
		);
		if ( empty( $rows ) ) {
			return $result;
		}

		$dir = self::get_passport_dir();
		foreach ( $rows as $row ) {
			$src = self::resolve_passport_path( $row->passport_file_url );
			if ( ! $src ) {
				// File already gone: clear the dead link so it is not retried forever.
				$wpdb->update( $table, array( 'passport_file_url' => '' ), array( 'id' => (int) $row->id ), array( '%s' ), array( '%d' ) );
				$result['missing']++;
				continue;
			}

			$ext  = '.' . strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
			$name = self::passport_filename( $dir['path'], basename( $src ), $ext );
			$dest = trailingslashit( $dir['path'] ) . $name;

			$ok = @rename( $src, $dest ); // phpcs:ignore
			if ( ! $ok && @copy( $src, $dest ) ) { // phpcs:ignore
				wp_delete_file( $src );
				$ok = true;
			}
			if ( ! $ok ) {
				$result['failed']++;
				continue;
			}

			$wpdb->update(
				$table,
				array( 'passport_file_url' => esc_url_raw( trailingslashit( $dir['url'] ) . $name ) ),
				array( 'id' => (int) $row->id ),
				array( '%s' ),
				array( '%d' )
			);
			$result['moved']++;
		}

		return $result;
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

		// 1.1 Rate limit per IP (default: 10 successful bookings / 15 minutes)
		$retry_after = self::check_rate_limit();
		if ( $retry_after > 0 ) {
			$c       = self::rate_limit_config();
			$minutes = max( 1, (int) ceil( $retry_after / MINUTE_IN_SECONDS ) );
			wp_send_json_error(
				array(
					'code'        => 'rate_limited',
					'retry_after' => $retry_after,
					'message'     => sprintf(
						/* translators: 1: max bookings, 2: window in minutes, 3: minutes to wait */
						_n(
							'Booking limit reached: only %1$d bookings are allowed from the same network every %2$d minutes. Please try again in %3$d minute.',
							'Booking limit reached: only %1$d bookings are allowed from the same network every %2$d minutes. Please try again in %3$d minutes.',
							$minutes,
							'maldives-packages'
						),
						$c['max'],
						max( 1, (int) round( $c['window'] / MINUTE_IN_SECONDS ) ),
						$minutes
					),
				),
				429
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

		// WooCommerce: the chosen card is a WooCommerce gateway ID.
		$use_wc     = class_exists( 'MPK_WooCommerce' ) && MPK_WooCommerce::is_enabled();
		$gateway_id = '';
		$pay_kind   = '';
		if ( $use_wc ) {
			$gateway_id = sanitize_key( $payment_method );
			$gateways   = MPK_WooCommerce::get_gateways();
			if ( isset( $gateways[ $gateway_id ] ) ) {
				$pay_kind = $gateways[ $gateway_id ]['kind'];
				// Stored label keeps emails / admin readable: bank, office, cheque ..., or online.
				$payment_method = in_array( $pay_kind, array( 'bank', 'office' ), true ) ? $pay_kind : ( 'online' === $pay_kind ? 'online' : $gateway_id );
			} else {
				$payment_method = '';
			}
		} else {
			$payment_method = strtolower( $payment_method );
		}
		if ( $use_wc ? '' === $pay_kind : ! in_array( $payment_method, self::get_allowed_payment_methods(), true ) ) {
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

		$occupancy = self::validate_occupancy( $adults, $children, $infants, $rooms_count );
		if ( is_wp_error( $occupancy ) ) {
			wp_send_json_error(
				array(
					'field'   => 'guests',
					'message' => $occupancy->get_error_message(),
				),
				400
			);
		}

		// 4.1 Rebuild trip details & price server-side (never trust client totals / names)
		$raw_selections = isset( $_POST['selections'] ) ? json_decode( wp_unslash( $_POST['selections'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$trip           = self::build_trip_from_selections( $raw_selections, $adults, $children, $rooms_count );
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
			'booking_items'     => $trip['items'],
			'pricing_breakdown' => $trip['pricing'],
		);

		// 6. Save booking using Booking Manager
		$result = MPK_Booking_Manager::create_booking( $booking_data );

		if ( is_wp_error( $result ) ) {
			error_log( '[MPK] Booking insert failed: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			// Remove orphaned passport upload for the failed booking.
			$orphan = self::resolve_passport_path( $passport_file_url );
			if ( $orphan ) {
				wp_delete_file( $orphan );
			}
			wp_send_json_error(
				array(
					'message' => __( 'We could not save your booking right now. Please try again or contact us.', 'maldives-packages' ),
				),
				500
			);
		}

		// 6.1 WooCommerce: create the order that will collect payment (rolled back on failure)
		$payment_url = '';
		$order_id    = 0;
		if ( $use_wc ) {
			$order = MPK_WooCommerce::create_order( $result['id'], $result['reference_id'], $booking_data, $trip, $gateway_id );
			if ( is_wp_error( $order ) ) {
				error_log( '[MPK] WooCommerce order failed: ' . $order->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
				MPK_Booking_Manager::delete_booking( $result['id'] );
				$orphan = self::resolve_passport_path( $passport_file_url );
				if ( $orphan ) {
					wp_delete_file( $orphan );
				}
				wp_send_json_error(
					array(
						'message' => __( 'We could not start the payment for your booking. Please try again or contact us.', 'maldives-packages' ),
					),
					500
				);
			}
			$order_id = $order->get_id();
			if ( 'online' === $pay_kind ) {
				$payment_url = $order->get_checkout_payment_url();
			}
			MPK_Booking_Manager::set_order_id( $result['id'], $order_id );
		}

		// 6.2 Trigger automated emails (Customer Confirmation & Admin Alert)
		$mail_payload                 = $booking_data;
		$mail_payload['id']           = $result['id'];
		$mail_payload['reference_id'] = $result['reference_id'];
		$mail_payload['pricing']      = $trip['pricing'];
		$mail_payload['payment_url']  = $payment_url;

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
		self::record_rate_limit_hit();
		wp_send_json_success(
			array(
				'reference_id' => $result['reference_id'],
				'booking_id'   => $result['id'],
				'grand_total'  => round( $grand_total, 2 ),
				'order_id'     => $order_id,
				'payment_url'  => $payment_url,
				'message'      => __( 'Booking saved successfully', 'maldives-packages' ),
			)
		);
	}

	/**
	 * Pricing rules from Settings with safe defaults (shared by booking validation).
	 *
	 * @return array{tax_rate:float,extras:float,service_fee:float,child_discount_pct:float,markup_pct:float}
	 */
	public static function get_pricing_rules() {
		$s   = MPK_Data_Manager::get_settings();
		$num = function ( $key, $default ) use ( $s ) {
			return isset( $s[ $key ] ) && is_numeric( $s[ $key ] ) ? (float) $s[ $key ] : $default;
		};
		return array(
			'tax_rate'           => max( 0, $num( 'tax_rate', 0.08 ) ),
			'extras'             => max( 0, $num( 'extras', 45.0 ) ),
			'service_fee'        => max( 0, $num( 'service_fee', 25.0 ) ),
			'child_discount_pct' => min( 100, max( 0, $num( 'child_discount_pct', 30 ) ) ),
			'markup_pct'         => min( 100, max( 0, $num( 'markup_pct', 0 ) ) ),
		);
	}

	/**
	 * Room rate shown to and charged from customers (admin rate + package markup).
	 *
	 * @param mixed $price      Admin room rate per night.
	 * @param float $markup_pct Markup percentage.
	 * @return float
	 */
	public static function effective_rate( $price, $markup_pct ) {
		return round( max( 0, (float) $price ) * ( 1 + ( (float) $markup_pct / 100 ) ), 2 );
	}

	/**
	 * Validate client room selections against stored hotel data and compute the
	 * authoritative booking summary + grand total (mirrors calcPricing() in mpk-main.js).
	 *
	 * @param mixed $selections Decoded selections array from the request.
	 * @param int   $adults     Adult count.
	 * @param int   $children   Children count.
	 * @param int   $rooms      Rooms booked per stay.
	 * @return array|WP_Error
	 */
	private static function build_trip_from_selections( $selections, $adults, $children, $rooms = 1 ) {
		if ( ! is_array( $selections ) || empty( $selections ) || count( $selections ) > 20 ) {
			return new WP_Error( 'mpk_invalid_selection', __( 'Please select at least one room with valid dates.', 'maldives-packages' ) );
		}

		$today       = current_time( 'Y-m-d' );
		$rules       = self::get_pricing_rules();
		$rooms       = max( 1, (int) $rooms );
		$adults      = max( 1, (int) $adults );
		$children    = max( 0, (int) $children );
		$extra_adult = max( 0, $adults - ( 2 * $rooms ) ); // Each room includes 2 adults.
		$room_cost   = 0.0;
		$extra_cost  = 0.0;
		$child_cost  = 0.0;
		$hotel_names = array();
		$room_names  = array();
		$loc_names   = array();
		$min_in      = '';
		$max_out     = '';
		$items       = array();

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

			$price = self::effective_rate( isset( $room['price'] ) ? $room['price'] : 0, $rules['markup_pct'] );
			$share = $price / 2; // One adult's share of the room rate.

			$room_cost  += $price * $nights * $rooms;
			$extra_cost += $extra_adult * $share * $nights;
			$child_cost += $children * $share * ( 1 - ( $rules['child_discount_pct'] / 100 ) ) * $nights;

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

			$items[] = array(
				'hotel_id'  => (string) $hotel['id'],
				'hotel'     => $hotel['name'],
				'room_id'   => (string) $room['id'],
				'room'      => $room['name'],
				'location'  => $loc_name,
				'check_in'  => $in,
				'check_out' => $out,
				'nights'    => $nights,
				'rooms'     => $rooms,
				'price'     => $price,
			);

			if ( ! $min_in || $in < $min_in ) {
				$min_in = $in;
			}
			if ( ! $max_out || $out > $max_out ) {
				$max_out = $out;
			}
		}

		// Pricing formula - keep in sync with calcPricing() in assets/js/mpk-main.js.
		// Rooms (2 adults incl.) + extra adults + children (infants free) -> tax -> + extras + service fee.
		$subtotal = $room_cost + $extra_cost + $child_cost;
		$total    = 0.0;
		if ( $subtotal > 0 ) {
			$total = $subtotal + ( $subtotal * $rules['tax_rate'] ) + $rules['extras'] + $rules['service_fee'];
		}

		return array(
			'selected_location' => implode( ', ', $loc_names ),
			'hotel_name'        => implode( ', ', $hotel_names ),
			'room_name'         => implode( ', ', $room_names ),
			'check_in'          => $min_in,
			'check_out'         => $max_out,
			'grand_total'       => round( $total, 2 ),
			'items'             => $items,
			'pricing'           => array(
				'room_cost'        => round( $room_cost, 2 ),
				'extra_adult_cost' => round( $extra_cost, 2 ),
				'extra_adults'     => $extra_adult,
				'child_cost'       => round( $child_cost, 2 ),
				'tax'              => $subtotal > 0 ? round( $subtotal * $rules['tax_rate'], 2 ) : 0,
				'extras'           => $subtotal > 0 ? $rules['extras'] : 0,
				'service_fee'      => $subtotal > 0 ? $rules['service_fee'] : 0,
			),
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

		// 2. Nonce verification (admin nonce only)
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'mpk_admin_nonce' ) ) {
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

		// Cancel the linked unpaid WooCommerce order (paid orders are kept as records)
		if ( class_exists( 'MPK_WooCommerce' ) ) {
			try {
				MPK_WooCommerce::detach_order( $booking_id );
			} catch ( \Throwable $e ) {
				error_log( '[MPK] Order detach failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
		}

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
