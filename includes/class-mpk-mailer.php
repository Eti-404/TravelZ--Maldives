<?php
/**
 * Automated Email Notifications Engine for Maldives Packages Booking (MPK).
 *
 * Dispatches responsive, luxury-themed HTML confirmation emails to travelers
 * and instant booking alerts to the site administrator using native wp_mail().
 *
 * Approved Prefix: MPK / mpk_
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MPK_Mailer {

	/**
	 * Send both customer confirmation and administrator alert emails.
	 *
	 * @param array|object $booking Booking record data.
	 * @return array Status of email dispatches ['customer' => bool, 'admin' => bool].
	 */
	public static function send_booking_confirmation( $booking ) {
		$data = is_object( $booking ) ? (array) $booking : $booking;

		// Extract sanitized fields with fallbacks
		$reference_id      = ! empty( $data['reference_id'] ) ? sanitize_text_field( $data['reference_id'] ) : 'MPK-PENDING';
		$lead_name         = ! empty( $data['lead_name'] ) ? sanitize_text_field( $data['lead_name'] ) : 'Valued Guest';
		$lead_email        = ! empty( $data['lead_email'] ) ? sanitize_email( $data['lead_email'] ) : '';
		$lead_phone        = ! empty( $data['lead_phone'] ) ? sanitize_text_field( $data['lead_phone'] ) : 'N/A';
		$lead_country      = ! empty( $data['lead_country'] ) ? sanitize_text_field( $data['lead_country'] ) : 'N/A';
		$passport_no       = ! empty( $data['passport_no'] ) ? sanitize_text_field( $data['passport_no'] ) : 'N/A';
		$special_requests  = ! empty( $data['special_requests'] ) ? sanitize_textarea_field( $data['special_requests'] ) : '';
		$selected_location = ! empty( $data['selected_location'] ) ? sanitize_text_field( $data['selected_location'] ) : 'Maldives';
		$hotel_name        = ! empty( $data['hotel_name'] ) ? sanitize_text_field( $data['hotel_name'] ) : 'Luxury Resort';
		$room_name         = ! empty( $data['room_name'] ) ? sanitize_text_field( $data['room_name'] ) : 'Selected Suite';
		$check_in          = ! empty( $data['check_in'] ) ? sanitize_text_field( $data['check_in'] ) : 'TBD';
		$check_out         = ! empty( $data['check_out'] ) ? sanitize_text_field( $data['check_out'] ) : 'TBD';
		$adults            = isset( $data['adults'] ) ? absint( $data['adults'] ) : 1;
		$children          = isset( $data['children'] ) ? absint( $data['children'] ) : 0;
		$infants           = isset( $data['infants'] ) ? absint( $data['infants'] ) : 0;
		$rooms_count       = isset( $data['rooms_count'] ) ? absint( $data['rooms_count'] ) : 1;
		$grand_total       = isset( $data['grand_total'] ) ? floatval( $data['grand_total'] ) : 0.00;
		$payment_method    = ! empty( $data['payment_method'] ) ? sanitize_text_field( $data['payment_method'] ) : 'Office Visit';
		$status            = ! empty( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'Pending';

		// Calculate nights
		$nights = 1;
		if ( 'TBD' !== $check_in && 'TBD' !== $check_out ) {
			$t_in  = strtotime( $check_in );
			$t_out = strtotime( $check_out );
			if ( $t_in && $t_out && $t_out > $t_in ) {
				$nights = max( 1, (int) round( ( $t_out - $t_in ) / 86400 ) );
			}
		}

		// Format payment label
		$payment_label = 'Office Visit';
		$raw_pay       = strtolower( (string) $payment_method );
		if ( 'online' === $raw_pay ) {
			$payment_label = 'Online Payment';
		} elseif ( false !== strpos( $raw_pay, 'bank' ) ) {
			$payment_label = 'Bank Transfer';
		} elseif ( false !== strpos( $raw_pay, 'office' ) ) {
			$payment_label = 'Office Visit';
		} else {
			$payment_label = ucwords( str_replace( array( '-', '_' ), ' ', (string) $payment_method ) );
		}

		$formatted_total = MPK_Data_Manager::format_price( $grand_total );
		$site_name       = get_bloginfo( 'name' );
		$admin_email     = get_option( 'admin_email' );

		$results = array(
			'customer' => false,
			'admin'    => false,
		);

		// Prepared common view data
		$view_data = array(
			'reference_id'      => $reference_id,
			'lead_name'         => $lead_name,
			'lead_email'        => $lead_email,
			'lead_phone'        => $lead_phone,
			'lead_country'      => $lead_country,
			'passport_no'       => $passport_no,
			'special_requests'  => $special_requests,
			'selected_location' => $selected_location,
			'hotel_name'        => $hotel_name,
			'room_name'         => $room_name,
			'check_in'          => $check_in,
			'check_out'         => $check_out,
			'nights'            => $nights,
			'adults'            => $adults,
			'children'          => $children,
			'infants'           => $infants,
			'rooms_count'       => $rooms_count,
			'grand_total'       => $formatted_total,
			'payment_method'    => $payment_label,
			'status'            => $status,
			'site_name'         => $site_name,
			'items'             => ! empty( $data['booking_items'] ) && is_array( $data['booking_items'] ) ? $data['booking_items'] : array(),
			'pricing'           => ! empty( $data['pricing'] ) && is_array( $data['pricing'] ) ? $data['pricing'] : array(),
		);

		// 1. Dispatch Customer Confirmation Email
		if ( ! empty( $lead_email ) && is_email( $lead_email ) ) {
			$cust_subject = sprintf(
				/* translators: 1: Reference ID, 2: Hotel Name */
				__( 'Booking Confirmation: %1$s - %2$s', 'maldives-packages' ),
				$reference_id,
				$hotel_name
			);

			$cust_headers = array(
				'Content-Type: text/html; charset=UTF-8',
				'From: ' . $site_name . ' <' . $admin_email . '>',
			);

			$cust_html = self::render_customer_email( $view_data );

			try {
				$results['customer'] = wp_mail( $lead_email, $cust_subject, $cust_html, $cust_headers );
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'MPK_Mailer Customer wp_mail Exception: ' . $e->getMessage() );
				}
				$results['customer'] = false;
			}
		}

		// 2. Dispatch Admin Booking Alert Email
		if ( ! empty( $admin_email ) && is_email( $admin_email ) ) {
			$admin_subject = sprintf(
				/* translators: 1: Reference ID, 2: Guest Name, 3: Hotel Name */
				__( '[New Booking Alert] %1$s - %2$s (%3$s)', 'maldives-packages' ),
				$reference_id,
				$lead_name,
				$hotel_name
			);

			$admin_headers = array(
				'Content-Type: text/html; charset=UTF-8',
				'From: ' . $site_name . ' <' . $admin_email . '>',
			);

			if ( ! empty( $lead_email ) && is_email( $lead_email ) ) {
				$admin_headers[] = 'Reply-To: ' . $lead_name . ' <' . $lead_email . '>';
			}

			$admin_html = self::render_admin_email( $view_data );

			try {
				$results['admin'] = wp_mail( $admin_email, $admin_subject, $admin_html, $admin_headers );
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'MPK_Mailer Admin wp_mail Exception: ' . $e->getMessage() );
				}
				$results['admin'] = false;
			}
		}

		return $results;
	}

	/**
	 * Room-by-room itinerary table for emails (all values escaped).
	 *
	 * @param array $items Booking line items.
	 * @return string
	 */
	private static function render_items_table( $items ) {
		if ( empty( $items ) || ! is_array( $items ) ) {
			return '';
		}
		$td = 'padding:8px 10px; font-size:12px; color:#0f172a; border-bottom:1px solid #f1f5f9; vertical-align:top;';
		$th = 'padding:8px 10px; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; text-align:left; border-bottom:1px solid #e2e8f0;';
		ob_start();
		?>
		<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e2e8f0; border-radius:12px; margin-bottom:24px; border-collapse:separate; overflow:hidden;">
			<tr><td colspan="4" style="padding:12px 10px 4px; font-size:14px; font-weight:700; color:#0f172a;">Room-by-Room Itinerary</td></tr>
			<tr>
				<th style="<?php echo esc_attr( $th ); ?>">Stay</th>
				<th style="<?php echo esc_attr( $th ); ?>">Dates</th>
				<th style="<?php echo esc_attr( $th ); ?> text-align:right;">Rate</th>
				<th style="<?php echo esc_attr( $th ); ?> text-align:right;">Total</th>
			</tr>
			<?php foreach ( $items as $it ) : ?>
				<?php
				$n    = isset( $it['nights'] ) ? (int) $it['nights'] : 0;
				$r    = isset( $it['rooms'] ) ? max( 1, (int) $it['rooms'] ) : 1;
				$rate = isset( $it['price'] ) ? (float) $it['price'] : 0;
				?>
				<tr>
					<td style="<?php echo esc_attr( $td ); ?>">
						<strong><?php echo esc_html( isset( $it['hotel'] ) ? $it['hotel'] : '' ); ?></strong><br>
						<?php echo esc_html( isset( $it['room'] ) ? $it['room'] : '' ); ?>
						<?php if ( ! empty( $it['location'] ) ) : ?>
							<br><span style="color:#64748b;"><?php echo esc_html( $it['location'] ); ?></span>
						<?php endif; ?>
					</td>
					<td style="<?php echo esc_attr( $td ); ?>">
						<?php echo esc_html( ( isset( $it['check_in'] ) ? $it['check_in'] : '' ) . ' → ' . ( isset( $it['check_out'] ) ? $it['check_out'] : '' ) ); ?><br>
						<span style="color:#64748b;"><?php echo esc_html( $n . ' ' . ( 1 === $n ? 'night' : 'nights' ) ); ?></span>
					</td>
					<td style="<?php echo esc_attr( $td ); ?> text-align:right;"><?php echo esc_html( MPK_Data_Manager::format_price( $rate ) . ( $r > 1 ? ' × ' . $r : '' ) ); ?></td>
					<td style="<?php echo esc_attr( $td ); ?> text-align:right; font-weight:700;"><?php echo esc_html( MPK_Data_Manager::format_price( $rate * $n * $r ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
		return ob_get_clean();
	}

	/**
	 * Price breakdown rows (<tr>) for the financial summary.
	 *
	 * @param array $p     Pricing breakdown.
	 * @param array $d     View data (children/infants counts).
	 * @return string
	 */
	private static function render_breakdown_rows( $p, $d ) {
		if ( empty( $p ) || ! is_array( $p ) ) {
			return '';
		}
		$rows = array( array( 'Rooms', isset( $p['room_cost'] ) ? $p['room_cost'] : 0 ) );
		if ( ! empty( $p['extra_adult_cost'] ) ) {
			$rows[] = array( 'Extra adults (' . (int) $p['extra_adults'] . ')', $p['extra_adult_cost'] );
		}
		if ( ! empty( $p['child_cost'] ) ) {
			$rows[] = array( 'Children (' . (int) $d['children'] . ')', $p['child_cost'] );
		}
		if ( ! empty( $d['infants'] ) ) {
			$rows[] = array( 'Infants (' . (int) $d['infants'] . ')', null );
		}
		$rows[] = array( 'Tax', isset( $p['tax'] ) ? $p['tax'] : 0 );
		if ( ! empty( $p['extras'] ) ) {
			$rows[] = array( 'Extra charges', $p['extras'] );
		}
		if ( ! empty( $p['service_fee'] ) ) {
			$rows[] = array( 'Service fee', $p['service_fee'] );
		}

		$html = '';
		foreach ( $rows as $row ) {
			$val   = null === $row[1] ? 'Free' : MPK_Data_Manager::format_price( (float) $row[1] );
			$html .= '<tr><td style="font-size:13px; color:#64748b; padding:3px 0;">' . esc_html( $row[0] ) . '</td>'
				. '<td align="right" style="font-size:13px; color:#0f172a; padding:3px 0;">' . esc_html( $val ) . '</td></tr>';
		}
		return $html;
	}

	/**
	 * Render responsive HTML email for the customer.
	 *
	 * @param array $d Booking parameters.
	 * @return string
	 */
	private static function render_customer_email( $d ) {
		$settings = class_exists( 'MPK_Data_Manager' ) ? MPK_Data_Manager::get_settings() : array();
		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $d['reference_id'] ); ?></title>
<style>
	body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
	table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
	img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
	table { border-collapse: collapse !important; }
	body { margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
</style>
</head>
<body style="margin:0; padding:24px 12px; background-color:#0f172a; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1e293b;">

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:620px; margin:0 auto; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.2);">

	<!-- Header Banner -->
	<tr>
		<td style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding:36px 32px; text-align:center; border-bottom:3px solid #0ea5e9;">
			<div style="font-size:12px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#38bdf8; margin-bottom:8px;">
				<?php echo esc_html( $d['site_name'] ); ?> &bull; MALDIVES ESCAPES
			</div>
			<h1 style="margin:0; font-size:24px; font-weight:800; color:#ffffff; letter-spacing:-0.5px;">
				Booking Confirmation
			</h1>
			<p style="margin:8px 0 0 0; font-size:14px; color:#94a3b8;">
				Thank you for choosing us for your island getaway.
			</p>
		</td>
	</tr>

	<!-- Reference ID Highlight Pill -->
	<tr>
		<td style="padding:24px 32px 16px 32px; text-align:center; background-color:#f8fafc; border-bottom:1px solid #e2e8f0;">
			<div style="font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:#64748b; margin-bottom:6px;">
				Booking Reference Number
			</div>
			<div style="display:inline-block; background-color:#0284c7; color:#ffffff; font-family:monospace, 'Courier New', Courier; font-size:20px; font-weight:700; letter-spacing:1.5px; padding:8px 20px; border-radius:8px;">
				<?php echo esc_html( $d['reference_id'] ); ?>
			</div>
			<div style="margin-top:8px; font-size:12px; color:#64748b;">
				Status: <strong style="color:#d97706;"><?php echo esc_html( $d['status'] ); ?></strong> &bull; Please keep this code for concierge inquiries.
			</div>
		</td>
	</tr>

	<!-- Main Content Body -->
	<tr>
		<td style="padding:32px;">

			<!-- Greeting -->
			<p style="margin:0 0 20px 0; font-size:15px; line-height:1.6; color:#334155;">
				Dear <strong><?php echo esc_html( $d['lead_name'] ); ?></strong>,
			</p>
			<p style="margin:0 0 24px 0; font-size:14px; line-height:1.6; color:#475569;">
				We are delighted to confirm that your booking inquiry has been registered with our team. Our luxury Maldives travel specialists are reviewing the arrangements. Here is a summary of your itinerary:
			</p>

			<!-- Resort & Room Card -->
			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f0f9ff; border:1px solid #bae6fd; border-radius:12px; margin-bottom:24px; overflow:hidden;">
				<tr>
					<td style="padding:20px;">
						<div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#0284c7; margin-bottom:4px;">
							Selected Accommodation
						</div>
						<div style="font-size:18px; font-weight:800; color:#0f172a; margin-bottom:4px;">
							<?php echo esc_html( $d['hotel_name'] ); ?>
						</div>
						<div style="font-size:14px; color:#475569; margin-bottom:12px;">
							<?php echo esc_html( $d['room_name'] ); ?> &bull; <em><?php echo esc_html( $d['selected_location'] ); ?></em>
						</div>

						<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top:1px dashed #bae6fd; padding-top:12px;">
							<tr>
								<td width="50%" style="padding-top:8px;">
									<div style="font-size:11px; color:#64748b; text-transform:uppercase; font-weight:600;">Check-In</div>
									<div style="font-size:14px; font-weight:700; color:#0f172a;"><?php echo esc_html( $d['check_in'] ); ?></div>
								</td>
								<td width="50%" style="padding-top:8px;">
									<div style="font-size:11px; color:#64748b; text-transform:uppercase; font-weight:600;">Check-Out</div>
									<div style="font-size:14px; font-weight:700; color:#0f172a;">
										<?php echo esc_html( $d['check_out'] ); ?>
										<span style="font-size:11px; font-weight:600; color:#0284c7;">(<?php echo esc_html( $d['nights'] ); ?> Nights)</span>
									</div>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>

			<!-- Details Breakdown Table -->
			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:24px; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
				<tr style="background-color:#f8fafc; border-bottom:1px solid #e2e8f0;">
					<td colspan="2" style="padding:12px 16px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:#475569;">
						Traveler & Party Details
					</td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td width="40%" style="padding:10px 16px; font-size:13px; color:#64748b;">Primary Traveler</td>
					<td width="60%" style="padding:10px 16px; font-size:13px; font-weight:600; color:#0f172a;">
						<?php echo esc_html( $d['lead_name'] ); ?> (<?php echo esc_html( $d['lead_country'] ); ?>)
					</td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td style="padding:10px 16px; font-size:13px; color:#64748b;">Contact Email</td>
					<td style="padding:10px 16px; font-size:13px; font-weight:600; color:#0f172a;"><?php echo esc_html( $d['lead_email'] ); ?></td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td style="padding:10px 16px; font-size:13px; color:#64748b;">Contact Phone</td>
					<td style="padding:10px 16px; font-size:13px; font-weight:600; color:#0f172a;"><?php echo esc_html( $d['lead_phone'] ); ?></td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td style="padding:10px 16px; font-size:13px; color:#64748b;">Passport Number</td>
					<td style="padding:10px 16px; font-size:13px; font-weight:600; color:#0f172a;"><?php echo esc_html( $d['passport_no'] ); ?></td>
				</tr>
				<tr>
					<td style="padding:10px 16px; font-size:13px; color:#64748b;">Party Configuration</td>
					<td style="padding:10px 16px; font-size:13px; font-weight:600; color:#0f172a;">
						<?php echo esc_html( $d['adults'] ); ?> Adults &bull; <?php echo esc_html( $d['children'] ); ?> Children &bull; <?php echo esc_html( $d['infants'] ); ?> Infants
						<div style="font-size:11px; color:#64748b; font-weight:normal;"><?php echo esc_html( $d['rooms_count'] ); ?> Room(s) reserved</div>
					</td>
				</tr>
			</table>

			<?php echo self::render_items_table( $d['items'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>

			<!-- Financial Summary Card -->
			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:24px;">
				<tr>
					<td style="padding:16px 20px;">
						<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
							<?php echo self::render_breakdown_rows( $d['pricing'], $d ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
							<tr>
								<td style="font-size:14px; color:#64748b; padding-top:6px;">Payment Method</td>
								<td align="right" style="font-size:14px; font-weight:700; color:#0f172a;">
									<?php echo esc_html( $d['payment_method'] ); ?>
								</td>
							</tr>
							<tr>
								<td style="font-size:16px; font-weight:700; color:#0f172a; padding-top:10px; border-top:1px solid #e2e8f0; margin-top:8px;">Grand Total</td>
								<td align="right" style="font-size:22px; font-weight:800; color:#0284c7; padding-top:10px; border-top:1px solid #e2e8f0; margin-top:8px;">
									<?php echo esc_html( $d['grand_total'] ); ?>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>

			<?php if ( false !== stripos( $d['payment_method'], 'bank' ) ) : ?>
			<!-- Wire Transfer Details -->
			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#eff6ff; border:1px solid #bfdbfe; border-radius:12px; margin-bottom:24px;">
				<tr>
					<td style="padding:16px 20px;">
						<div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#1d4ed8; margin-bottom:8px;">
							Wire Transfer Payment Instructions
						</div>
						<div style="font-size:13px; color:#1e40af; line-height:1.6;">
							Beneficiary Bank: <strong><?php echo esc_html( ! empty( $settings['bank_name'] ) ? $settings['bank_name'] : 'Standard Chartered Bank' ); ?></strong><br>
							Account Name: <strong><?php echo esc_html( ! empty( $settings['bank_account_name'] ) ? $settings['bank_account_name'] : 'Maldives Packages Concierge Ltd' ); ?></strong><br>
							Account / IBAN: <strong><?php echo esc_html( ! empty( $settings['bank_account_no'] ) ? $settings['bank_account_no'] : '000-000-0000-00' ); ?></strong><br>
							SWIFT / Branch: <strong><?php echo esc_html( ! empty( $settings['bank_swift'] ) ? $settings['bank_swift'] : 'SCBLBDDX' ); ?></strong>
						</div>
					</td>
				</tr>
			</table>
			<?php endif; ?>

			<?php if ( ! empty( $d['special_requests'] ) ) : ?>
			<!-- Special Requests Note -->
			<div style="background-color:#fffbeb; border-left:4px solid #f59e0b; padding:12px 16px; border-radius:6px; margin-bottom:24px;">
				<div style="font-size:11px; font-weight:700; text-transform:uppercase; color:#b45309; margin-bottom:2px;">Special Requests</div>
				<div style="font-size:13px; color:#92400e; line-height:1.5; font-style:italic;">
					&ldquo;<?php echo nl2br( esc_html( $d['special_requests'] ) ); ?>&rdquo;
				</div>
			</div>
			<?php endif; ?>

			<!-- Next Steps Advice -->
			<div style="background-color:#ecfdf5; border:1px solid #a7f3d0; border-radius:10px; padding:16px; font-size:13px; line-height:1.6; color:#065f46; margin-bottom:16px;">
				<strong style="color:#047857;">What happens next?</strong><br>
				Our concierge representative will contact you via phone or email within 24 hours to confirm resort availability and assist with flight transfers and itinerary personalization.
			</div>

		</td>
	</tr>

	<!-- Footer -->
	<tr>
		<td style="background-color:#f1f5f9; padding:24px 32px; text-align:center; border-top:1px solid #e2e8f0;">
			<div style="font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">
				<?php echo esc_html( $d['site_name'] ); ?> &bull; Maldives Luxury Packages
			</div>
			<div style="font-size:11px; color:#94a3b8; line-height:1.5;">
				<?php if ( ! empty( $settings['support_email'] ) || ! empty( $settings['support_phone'] ) ) : ?>
					Concierge Contact: <strong><?php echo esc_html( ! empty( $settings['support_email'] ) ? $settings['support_email'] : 'concierge@example.com' ); ?></strong> &bull; <strong><?php echo esc_html( ! empty( $settings['support_phone'] ) ? $settings['support_phone'] : '+00 123 456789' ); ?></strong><br>
				<?php endif; ?>
				This is an automated confirmation email regarding your package inquiry. If you have questions, please contact our support team.
			</div>
		</td>
	</tr>

</table>

</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render responsive HTML notification email for the site administrator.
	 *
	 * @param array $d Booking parameters.
	 * @return string
	 */
	private static function render_admin_email( $d ) {
		$admin_bookings_url = admin_url( 'admin.php?page=maldives-packages' );
		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Booking: <?php echo esc_html( $d['reference_id'] ); ?></title>
<style>
	body { margin:0; padding:24px 12px; background-color:#f1f5f9; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1e293b; }
</style>
</head>
<body style="margin:0; padding:24px 12px; background-color:#f1f5f9; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:620px; margin:0 auto; background-color:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #cbd5e1; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">

	<!-- Admin Header -->
	<tr>
		<td style="background-color:#0f172a; padding:28px 32px; border-bottom:3px solid #10b981;">
			<div style="display:inline-block; background-color:#10b981; color:#ffffff; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; padding:4px 10px; border-radius:4px; margin-bottom:8px;">
				New Customer Booking
			</div>
			<h2 style="margin:0; font-size:20px; font-weight:800; color:#ffffff;">
				Ref: <?php echo esc_html( $d['reference_id'] ); ?>
			</h2>
			<div style="margin-top:4px; font-size:13px; color:#94a3b8;">
				Submitted for <?php echo esc_html( $d['hotel_name'] ); ?>
			</div>
		</td>
	</tr>

	<!-- Admin Summary Details -->
	<tr>
		<td style="padding:28px 32px;">

			<p style="margin:0 0 20px 0; font-size:14px; color:#334155; line-height:1.5;">
				A new luxury package booking request has been submitted on <strong><?php echo esc_html( $d['site_name'] ); ?></strong>. Details are recorded below:
			</p>

			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; margin-bottom:24px;">
				<tr style="background-color:#f8fafc; border-bottom:1px solid #e2e8f0;">
					<td colspan="2" style="padding:10px 14px; font-size:11px; font-weight:700; text-transform:uppercase; color:#475569;">
						Customer & Booking Summary
					</td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td width="35%" style="padding:10px 14px; font-size:13px; color:#64748b;">Guest Name:</td>
					<td width="65%" style="padding:10px 14px; font-size:13px; font-weight:600; color:#0f172a;"><?php echo esc_html( $d['lead_name'] ); ?></td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td style="padding:10px 14px; font-size:13px; color:#64748b;">Email:</td>
					<td style="padding:10px 14px; font-size:13px; font-weight:600; color:#0284c7;">
						<a href="mailto:<?php echo esc_attr( $d['lead_email'] ); ?>" style="color:#0284c7; text-decoration:none;"><?php echo esc_html( $d['lead_email'] ); ?></a>
					</td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td style="padding:10px 14px; font-size:13px; color:#64748b;">Phone / Country:</td>
					<td style="padding:10px 14px; font-size:13px; font-weight:600; color:#0f172a;">
						<?php echo esc_html( $d['lead_phone'] ); ?> (<?php echo esc_html( $d['lead_country'] ); ?>)
					</td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td style="padding:10px 14px; font-size:13px; color:#64748b;">Passport No:</td>
					<td style="padding:10px 14px; font-size:13px; font-weight:600; color:#0f172a;"><?php echo esc_html( $d['passport_no'] ); ?></td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td style="padding:10px 14px; font-size:13px; color:#64748b;">Accommodation:</td>
					<td style="padding:10px 14px; font-size:13px; font-weight:600; color:#0f172a;">
						<?php echo esc_html( $d['hotel_name'] ); ?> &bull; <?php echo esc_html( $d['room_name'] ); ?>
					</td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td style="padding:10px 14px; font-size:13px; color:#64748b;">Dates & Nights:</td>
					<td style="padding:10px 14px; font-size:13px; font-weight:600; color:#0f172a;">
						<?php echo esc_html( $d['check_in'] ); ?> &rarr; <?php echo esc_html( $d['check_out'] ); ?> (<?php echo esc_html( $d['nights'] ); ?> Nights)
					</td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td style="padding:10px 14px; font-size:13px; color:#64748b;">Party Configuration:</td>
					<td style="padding:10px 14px; font-size:13px; font-weight:600; color:#0f172a;">
						<?php echo esc_html( $d['adults'] ); ?> Adults, <?php echo esc_html( $d['children'] ); ?> Children, <?php echo esc_html( $d['infants'] ); ?> Infants (<?php echo esc_html( $d['rooms_count'] ); ?> Rooms)
					</td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td style="padding:10px 14px; font-size:13px; color:#64748b;">Payment Method:</td>
					<td style="padding:10px 14px; font-size:13px; font-weight:600; color:#0f172a;"><?php echo esc_html( $d['payment_method'] ); ?></td>
				</tr>
				<tr>
					<td style="padding:12px 14px; font-size:14px; font-weight:700; color:#0f172a;">Grand Total:</td>
					<td style="padding:12px 14px; font-size:18px; font-weight:800; color:#10b981;"><?php echo esc_html( $d['grand_total'] ); ?></td>
				</tr>
			</table>

			<?php echo self::render_items_table( $d['items'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
			<?php if ( ! empty( $d['pricing'] ) ) : ?>
				<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:24px;">
					<tr><td style="padding:14px 18px;">
						<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
							<?php echo self::render_breakdown_rows( $d['pricing'], $d ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside. ?>
						</table>
					</td></tr>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $d['special_requests'] ) ) : ?>
			<div style="background-color:#fffbeb; border:1px solid #fef3c7; border-left:4px solid #f59e0b; padding:12px 16px; border-radius:6px; margin-bottom:24px;">
				<div style="font-size:11px; font-weight:700; text-transform:uppercase; color:#b45309;">Guest Special Requests:</div>
				<div style="font-size:13px; color:#92400e; margin-top:4px;">
					<?php echo nl2br( esc_html( $d['special_requests'] ) ); ?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Admin Action Button -->
			<div style="text-align:center; padding:12px 0 16px 0;">
				<a href="<?php echo esc_url( $admin_bookings_url ); ?>" style="display:inline-block; background-color:#0f172a; color:#ffffff; font-size:14px; font-weight:700; text-decoration:none; padding:14px 28px; border-radius:8px; box-shadow:0 4px 6px -1px rgba(15, 23, 42, 0.2);">
					Manage Booking in WordPress Admin &rarr;
				</a>
			</div>

		</td>
	</tr>

	<!-- Admin Footer -->
	<tr>
		<td style="background-color:#f8fafc; padding:16px 32px; text-align:center; border-top:1px solid #e2e8f0; font-size:11px; color:#94a3b8;">
			Maldives Packages Automated Alert System &bull; <?php echo esc_html( $d['site_name'] ); ?>
		</td>
	</tr>

</table>

</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Send an automated status update email to the customer when an admin changes the booking status.
	 *
	 * @param int    $booking_id Database ID of the booking.
	 * @param string $new_status New status ('Approved', 'Cancelled', etc.).
	 * @return bool Whether the email was successfully handed to wp_mail.
	 */
	public static function send_status_update_email( $booking_id, $new_status ) {
		if ( ! class_exists( 'MPK_Booking_Manager' ) ) {
			require_once MPK_PLUGIN_DIR . 'includes/class-mpk-booking-manager.php';
		}

		$booking = MPK_Booking_Manager::get_booking_by_id( $booking_id );
		if ( ! $booking || empty( $booking->lead_email ) || ! is_email( $booking->lead_email ) ) {
			return false;
		}

		$reference_id   = $booking->reference_id;
		$lead_name      = ! empty( $booking->lead_name ) ? $booking->lead_name : 'Valued Guest';
		$hotel_name     = ! empty( $booking->hotel_name ) ? $booking->hotel_name : 'Maldives Resort';
		$site_name      = get_bloginfo( 'name' );
		$admin_email    = get_option( 'admin_email' );
		$status_clean   = ucfirst( strtolower( trim( $new_status ) ) );

		if ( 'Approved' === $status_clean || 'Confirmed' === $status_clean ) {
			$subject = sprintf(
				/* translators: 1: Reference ID, 2: Hotel Name */
				__( 'Booking Approved: %1$s - %2$s Confirmed', 'maldives-packages' ),
				$reference_id,
				$hotel_name
			);
		} elseif ( 'Cancelled' === $status_clean ) {
			$subject = sprintf(
				/* translators: 1: Reference ID, 2: Hotel Name */
				__( 'Booking Cancelled: %1$s - %2$s', 'maldives-packages' ),
				$reference_id,
				$hotel_name
			);
		} else {
			$subject = sprintf(
				/* translators: 1: Reference ID, 2: Status */
				__( 'Booking Status Update: %1$s (%2$s)', 'maldives-packages' ),
				$reference_id,
				$status_clean
			);
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $site_name . ' <' . $admin_email . '>',
		);

		$view_data = array(
			'booking'      => $booking,
			'reference_id' => $reference_id,
			'lead_name'    => $lead_name,
			'hotel_name'   => $hotel_name,
			'room_name'    => $booking->room_name,
			'check_in'     => $booking->check_in,
			'check_out'    => $booking->check_out,
			'grand_total'  => MPK_Data_Manager::format_price( (float) $booking->grand_total ),
			'new_status'   => $status_clean,
			'site_name'    => $site_name,
			'admin_email'  => $admin_email,
		);

		$html = self::render_status_update_email( $view_data );

		try {
			return wp_mail( $booking->lead_email, $subject, $html, $headers );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'MPK_Mailer Status Update Exception: ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Render responsive HTML status update email for the customer.
	 *
	 * @param array $d Booking status parameters.
	 * @return string
	 */
	private static function render_status_update_email( $d ) {
		$is_approved  = in_array( $d['new_status'], array( 'Approved', 'Confirmed' ), true );
		$is_cancelled = 'Cancelled' === $d['new_status'];
		$banner_bg    = $is_approved ? '#10b981' : ( $is_cancelled ? '#ef4444' : '#0284c7' );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $d['reference_id'] ); ?> - Status Update</title>
</head>
<body style="margin:0; padding:24px 12px; background-color:#0f172a; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1e293b;">

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 20px 25px -5px rgba(0, 0, 0, 0.2);">

	<!-- Header Banner -->
	<tr>
		<td style="background-color:#0f172a; padding:32px 28px; text-align:center; border-bottom:3px solid <?php echo esc_attr( $banner_bg ); ?>;">
			<div style="font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#38bdf8; margin-bottom:6px;">
				<?php echo esc_html( $d['site_name'] ); ?> &bull; MALDIVES ESCAPES
			</div>
			<h1 style="margin:0; font-size:22px; font-weight:800; color:#ffffff;">
				Booking Status Update
			</h1>
			<p style="margin:6px 0 0 0; font-size:14px; color:#94a3b8; font-family:monospace;">
				<?php echo esc_html( $d['reference_id'] ); ?>
			</p>
		</td>
	</tr>

	<!-- Status Announcement Card -->
	<tr>
		<td style="padding:28px 32px;">

			<p style="margin:0 0 16px 0; font-size:16px; color:#1e293b;">
				Dear <strong><?php echo esc_html( $d['lead_name'] ); ?></strong>,
			</p>

			<?php if ( $is_approved ) : ?>
				<div style="background-color:#ecfdf5; border:1px solid #a7f3d0; border-left:4px solid #10b981; border-radius:10px; padding:18px 20px; margin-bottom:24px;">
					<div style="display:inline-block; background-color:#10b981; color:#ffffff; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; padding:3px 10px; border-radius:4px; margin-bottom:8px;">
						Approved & Confirmed
					</div>
					<h3 style="margin:0 0 6px 0; font-size:17px; color:#065f46;">
						Your Maldives booking has been approved!
					</h3>
					<p style="margin:0; font-size:14px; line-height:1.5; color:#047857;">
						We are pleased to inform you that your reservation request has been officially approved. Our luxury concierge specialist is finalizing your itinerary vouchers and transfer schedules.
					</p>
				</div>
			<?php elseif ( $is_cancelled ) : ?>
				<div style="background-color:#fef2f2; border:1px solid #fecaca; border-left:4px solid #ef4444; border-radius:10px; padding:18px 20px; margin-bottom:24px;">
					<div style="display:inline-block; background-color:#ef4444; color:#ffffff; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; padding:3px 10px; border-radius:4px; margin-bottom:8px;">
						Booking Cancelled
					</div>
					<h3 style="margin:0 0 6px 0; font-size:17px; color:#991b1b;">
						Your reservation inquiry has been cancelled.
					</h3>
					<p style="margin:0; font-size:14px; line-height:1.5; color:#b91c1c;">
						This booking has been marked as cancelled. If this was unexpected or if you would like to reschedule or explore other resort availability, please reach out to our team.
					</p>
				</div>
			<?php else : ?>
				<div style="background-color:#f0f9ff; border:1px solid #bae6fd; border-left:4px solid #0284c7; border-radius:10px; padding:18px 20px; margin-bottom:24px;">
					<div style="font-size:13px; color:#0369a1;">
						Status has been updated to: <strong style="text-transform:uppercase; color:#0284c7;"><?php echo esc_html( $d['new_status'] ); ?></strong>
					</div>
				</div>
			<?php endif; ?>

			<!-- Booking Summary Recap -->
			<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; margin-bottom:24px;">
				<tr style="border-bottom:1px solid #e2e8f0;">
					<td colspan="2" style="padding:10px 16px; font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; background-color:#f1f5f9;">
						Reservation Details
					</td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td width="35%" style="padding:10px 16px; font-size:13px; color:#64748b;">Resort:</td>
					<td width="65%" style="padding:10px 16px; font-size:13px; font-weight:600; color:#0f172a;"><?php echo esc_html( $d['hotel_name'] ); ?></td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td style="padding:10px 16px; font-size:13px; color:#64748b;">Room:</td>
					<td style="padding:10px 16px; font-size:13px; font-weight:600; color:#0f172a;"><?php echo esc_html( $d['room_name'] ); ?></td>
				</tr>
				<tr style="border-bottom:1px solid #f1f5f9;">
					<td style="padding:10px 16px; font-size:13px; color:#64748b;">Travel Dates:</td>
					<td style="padding:10px 16px; font-size:13px; font-weight:600; color:#0f172a;"><?php echo esc_html( $d['check_in'] ); ?> &rarr; <?php echo esc_html( $d['check_out'] ); ?></td>
				</tr>
				<tr>
					<td style="padding:10px 16px; font-size:13px; color:#64748b;">Total Amount:</td>
					<td style="padding:10px 16px; font-size:14px; font-weight:700; color:#0f172a;"><?php echo esc_html( $d['grand_total'] ); ?></td>
				</tr>
			</table>

			<p style="margin:0 0 10px 0; font-size:13px; color:#64748b; line-height:1.5;">
				If you have any questions or need to modify your arrangements, please reply directly to this email or contact us at <a href="mailto:<?php echo esc_attr( $d['admin_email'] ); ?>" style="color:#0284c7; text-decoration:none;"><?php echo esc_html( $d['admin_email'] ); ?></a>.
			</p>

		</td>
	</tr>

	<!-- Footer -->
	<tr>
		<td style="background-color:#f1f5f9; padding:20px 32px; text-align:center; border-top:1px solid #e2e8f0; font-size:11px; color:#94a3b8;">
			<?php echo esc_html( $d['site_name'] ); ?> &bull; Maldives Luxury Packages Concierge
		</td>
	</tr>

</table>

</body>
</html>
		<?php
		return ob_get_clean();
	}
}
