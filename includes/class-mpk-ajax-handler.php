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

		// 4. Extract and sanitize booking trip details
		$selected_location = isset( $_POST['selected_location'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_location'] ) ) : '';
		$hotel_name        = isset( $_POST['hotel_name'] ) ? sanitize_text_field( wp_unslash( $_POST['hotel_name'] ) ) : '';
		$room_name         = isset( $_POST['room_name'] ) ? sanitize_text_field( wp_unslash( $_POST['room_name'] ) ) : '';
		$check_in          = isset( $_POST['check_in'] ) ? sanitize_text_field( wp_unslash( $_POST['check_in'] ) ) : null;
		$check_out         = isset( $_POST['check_out'] ) ? sanitize_text_field( wp_unslash( $_POST['check_out'] ) ) : null;

		$adults      = isset( $_POST['adults'] ) ? absint( $_POST['adults'] ) : 1;
		$children    = isset( $_POST['children'] ) ? absint( $_POST['children'] ) : 0;
		$infants     = isset( $_POST['infants'] ) ? absint( $_POST['infants'] ) : 0;
		$rooms_count = isset( $_POST['rooms_count'] ) ? absint( $_POST['rooms_count'] ) : 1;
		$grand_total = isset( $_POST['grand_total'] ) ? floatval( $_POST['grand_total'] ) : 0.00;

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

			$upload_overrides = array(
				'test_form' => false,
				'mimes'     => $allowed_mimes,
			);

			if ( is_uploaded_file( $file['tmp_name'] ) ) {
				$movefile = wp_handle_upload( $file, $upload_overrides );
			} else {
				$movefile = wp_handle_sideload( $file, $upload_overrides );
			}

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
				'message'      => __( 'Booking saved successfully', 'maldives-packages' ),
			)
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

		// Optional: clean up attached passport copy file
		$passport_url = $wpdb->get_var( $wpdb->prepare( "SELECT passport_file_url FROM {$table_name} WHERE id = %d", $booking_id ) );
		if ( ! empty( $passport_url ) ) {
			$upload_dir = wp_upload_dir();
			$file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $passport_url );
			if ( file_exists( $file_path ) && is_file( $file_path ) && strpos( $file_path, 'mpk-passports' ) !== false ) {
				@unlink( $file_path );
			}
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
