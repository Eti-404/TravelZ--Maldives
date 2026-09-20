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

		// 5. Build booking record payload
		$booking_data = array(
			'reference_id'      => $reference_id,
			'lead_name'         => $lead_name,
			'lead_email'        => $lead_email,
			'lead_phone'        => $lead_phone,
			'lead_country'      => $lead_country,
			'passport_no'       => $passport_no,
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
}
