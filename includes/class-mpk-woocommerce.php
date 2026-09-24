<?php
/**
 * WooCommerce bridge for Maldives Packages Booking (MPK).
 *
 * When WooCommerce is active (and enabled in Settings -> Payment), the wizard's
 * payment cards are built from the enabled WooCommerce gateways and every booking
 * gets a WooCommerce order:
 *  - offline gateways (Bank transfer, Cash / office visit, Cheque) -> order "On hold",
 *    traveler sees the plugin's own confirmation step;
 *  - online gateways (SSLCommerz, cards ...) -> traveler is sent to the
 *    "Pay for order" page with only the chosen gateway shown.
 *
 * Approved Prefix: MPK / mpk_
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MPK_WooCommerce {

	/**
	 * Option that stores the hidden "Maldives Package Booking" product ID.
	 */
	const PRODUCT_OPTION = 'mpk_wc_product_id';

	/**
	 * Order meta keys.
	 */
	const META_BOOKING_ID = '_mpk_booking_id';
	const META_REFERENCE  = '_mpk_reference_id';

	/**
	 * Set while the plugin itself changes an order status (prevents sync loops).
	 *
	 * @var bool
	 */
	private static $syncing = false;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		// The helper product only exists to carry booking line items - never let it into a cart.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'block_add_to_cart' ), 10, 2 );

		// On "Pay for order" for a booking, only show the gateway chosen in the wizard.
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'restrict_order_pay_gateways' ), 100 );

		// Order status -> booking status.
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 4 );

		// Booking summary on WooCommerce's "Order received" page (after online payment).
		add_action( 'woocommerce_before_thankyou', array( $this, 'render_thankyou_booking' ) );
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'thankyou_text' ), 10, 2 );

		// Avoid duplicate emails for offline booking orders: the plugin's booking email
		// already carries the payment instructions and the admin already got a booking alert.
		add_filter( 'woocommerce_email_enabled_customer_on_hold_order', array( $this, 'skip_duplicate_email' ), 10, 2 );
		add_filter( 'woocommerce_email_enabled_new_order', array( $this, 'skip_duplicate_email' ), 10, 2 );

		// Booking details box on the WooCommerce order screen (legacy + HPOS).
		add_action( 'add_meta_boxes', array( $this, 'register_order_meta_box' ), 10, 2 );

		// Cancel online booking orders that were never paid.
		add_action( 'mpk_cancel_unpaid_orders', array( __CLASS__, 'cancel_unpaid_orders' ) );
		add_action( 'init', array( __CLASS__, 'schedule_cleanup' ) );
	}

	/**
	 * Disable WooCommerce's "On hold" (customer) and "New order" (admin) emails while a
	 * booking order is on hold - the plugin sends its own booking emails for that moment.
	 * Once an online payment completes (Processing), WooCommerce's emails go out as usual.
	 *
	 * @param bool          $enabled Email enabled.
	 * @param WC_Order|null $order   Order the email is for.
	 * @return bool
	 */
	public function skip_duplicate_email( $enabled, $order = null ) {
		if ( $enabled && $order instanceof WC_Order && $order->get_meta( self::META_BOOKING_ID ) && $order->has_status( 'on-hold' ) ) {
			return (bool) apply_filters( 'mpk_send_wc_on_hold_emails', false, $order );
		}
		return $enabled;
	}

	/**
	 * WooCommerce is available and "Collect booking payments through WooCommerce" is on.
	 *
	 * @return bool
	 */
	public static function wc_mode() {
		if ( ! self::is_available() ) {
			return false;
		}
		$s = get_option( 'mpk_settings', array() );
		return ! is_array( $s ) || ! isset( $s['wc_checkout'] ) || ! empty( $s['wc_checkout'] );
	}

	/**
	 * First WooCommerce "Direct bank transfer" account, mapped to the plugin's bank fields.
	 *
	 * @return array Empty when no account is configured in WooCommerce.
	 */
	public static function bacs_account() {
		if ( ! self::wc_mode() ) {
			return array();
		}
		foreach ( (array) get_option( 'woocommerce_bacs_accounts', array() ) as $acc ) {
			if ( ! is_array( $acc ) || empty( $acc['account_number'] ) && empty( $acc['iban'] ) ) {
				continue;
			}
			$routing = array_filter( array( isset( $acc['bic'] ) ? $acc['bic'] : '', isset( $acc['sort_code'] ) ? $acc['sort_code'] : '' ) );
			return array(
				'bank_name'         => isset( $acc['bank_name'] ) ? (string) $acc['bank_name'] : '',
				'bank_account_name' => isset( $acc['account_name'] ) ? (string) $acc['account_name'] : '',
				'bank_account_no'   => ! empty( $acc['account_number'] ) ? (string) $acc['account_number'] : (string) $acc['iban'],
				'bank_swift'        => implode( ' / ', $routing ),
			);
		}
		return array();
	}

	/**
	 * Hours after which an unpaid online booking order is cancelled (0 = never).
	 *
	 * @return int
	 */
	public static function unpaid_cancel_hours() {
		$s = get_option( 'mpk_settings', array() );
		return isset( $s['unpaid_cancel_hours'] ) && '' !== $s['unpaid_cancel_hours'] ? max( 0, (int) $s['unpaid_cancel_hours'] ) : 24;
	}

	/**
	 * Ensure the hourly cleanup event exists.
	 */
	public static function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'mpk_cancel_unpaid_orders' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'mpk_cancel_unpaid_orders' );
		}
	}

	/**
	 * Cron: cancel online booking orders still unpaid after the configured time.
	 * The status sync then cancels the booking and emails the traveler.
	 */
	public static function cancel_unpaid_orders() {
		$hours = self::unpaid_cancel_hours();
		if ( ! self::wc_mode() || $hours < 1 || ! function_exists( 'wc_get_orders' ) ) {
			return;
		}
		$orders = wc_get_orders(
			array(
				'status'       => array( 'pending' ),
				'created_via'  => 'mpk_booking',
				'date_created' => '<' . ( time() - $hours * HOUR_IN_SECONDS ),
				'limit'        => 50,
			)
		);
		foreach ( $orders as $order ) {
			if ( ! $order->get_meta( self::META_BOOKING_ID ) || ! $order->needs_payment() ) {
				continue;
			}
			/* translators: %d: hours */
			$order->update_status( 'cancelled', sprintf( __( 'Unpaid booking order cancelled automatically after %d hours.', 'maldives-packages' ), $hours ) );
		}
	}

	/**
	 * A booking is being deleted: cancel its unpaid order, keep paid orders as records.
	 *
	 * @param int $booking_id Booking ID.
	 */
	public static function detach_order( $booking_id ) {
		if ( ! self::is_available() ) {
			return;
		}
		$booking = MPK_Booking_Manager::get_booking_by_id( $booking_id );
		$order   = ( $booking && ! empty( $booking->order_id ) ) ? wc_get_order( (int) $booking->order_id ) : null;
		if ( ! $order ) {
			return;
		}
		self::$syncing = true;
		try {
			if ( $order->has_status( array( 'pending', 'on-hold' ) ) ) {
				/* translators: %s: booking reference */
				$order->update_status( 'cancelled', sprintf( __( 'Linked booking %s was deleted - unpaid order cancelled.', 'maldives-packages' ), $booking->reference_id ) );
			} else {
				/* translators: %s: booking reference */
				$order->add_order_note( sprintf( __( 'Linked booking %s was deleted from the bookings dashboard.', 'maldives-packages' ), $booking->reference_id ) );
			}
		} finally {
			self::$syncing = false;
		}
	}

	/**
	 * Add the "Maldives Booking" box to booking orders only.
	 *
	 * @param string $screen_id     Current screen / post type.
	 * @param mixed  $post_or_order WP_Post (legacy) or WC_Order (HPOS).
	 */
	public function register_order_meta_box( $screen_id, $post_or_order = null ) {
		$order = $post_or_order instanceof WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		if ( ! $order instanceof WC_Order || ! $order->get_meta( self::META_BOOKING_ID ) ) {
			return;
		}
		add_meta_box( 'mpk-order-booking', __( 'Maldives Booking', 'maldives-packages' ), array( $this, 'render_order_meta_box' ), $screen_id, 'side', 'high' );
	}

	/**
	 * Order screen box content.
	 *
	 * @param mixed $post_or_order WP_Post (legacy) or WC_Order (HPOS).
	 */
	public function render_order_meta_box( $post_or_order ) {
		$order   = $post_or_order instanceof WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		$booking = $order ? self::booking_for_order( $order ) : null;
		if ( ! $booking ) {
			echo '<p>' . esc_html__( 'The linked booking no longer exists.', 'maldives-packages' ) . '</p>';
			return;
		}
		$date  = function ( $d ) {
			return $d ? date_i18n( 'd M Y', strtotime( $d ) ) : '—';
		};
		$rows  = array(
			__( 'Reference', 'maldives-packages' ) => '<strong>' . esc_html( $booking->reference_id ) . '</strong>',
			__( 'Status', 'maldives-packages' )    => class_exists( 'MPK_Admin' ) ? MPK_Admin::get_status_badge( $booking->status ) : esc_html( $booking->status ),
			__( 'Stay', 'maldives-packages' )      => esc_html( $date( $booking->check_in ) . ' → ' . $date( $booking->check_out ) ),
			__( 'Guests', 'maldives-packages' )    => esc_html( sprintf( '%d A · %d C · %d I · %d R', $booking->adults, $booking->children, $booking->infants, $booking->rooms_count ) ),
			__( 'Passport', 'maldives-packages' )  => esc_html( $booking->passport_no ? $booking->passport_no : '—' ),
		);
		echo '<table style="width:100%; font-size:12px; border-collapse:collapse;">';
		foreach ( $rows as $label => $val ) {
			echo '<tr><td style="padding:4px 0; color:#646970; width:38%; vertical-align:top;">' . esc_html( $label ) . '</td><td style="padding:4px 0;">' . $val . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- values escaped above.
		}
		echo '</table>';

		if ( ! empty( $booking->passport_file_url ) && class_exists( 'MPK_Ajax_Handler' ) ) {
			echo '<p style="margin:10px 0 0;"><a class="button" target="_blank" rel="noopener noreferrer" href="' . esc_url( MPK_Ajax_Handler::get_passport_view_url( $booking->id ) ) . '">' . esc_html__( 'View passport copy', 'maldives-packages' ) . '</a></p>';
		}
		$dash = add_query_arg(
			array(
				'page' => 'maldives-packages',
				's'    => $booking->reference_id,
			),
			admin_url( 'admin.php' )
		);
		echo '<p style="margin:10px 0 0;"><a href="' . esc_url( $dash ) . '">' . esc_html__( 'Open in bookings dashboard →', 'maldives-packages' ) . '</a></p>';
	}

	/**
	 * Does WooCommerce's currency drive the plugin (so order amounts and shown prices match)?
	 *
	 * @return bool
	 */
	public static function controls_currency() {
		return function_exists( 'get_woocommerce_currency' ) && self::wc_mode();
	}

	/**
	 * WooCommerce currency in the plugin's currency format.
	 *
	 * @return array{code:string,symbol:string,position:string,decimals:int}
	 */
	public static function get_wc_currency() {
		$code = get_woocommerce_currency();
		$pos  = get_option( 'woocommerce_currency_pos', 'left' );
		return array(
			'code'     => $code,
			'symbol'   => html_entity_decode( get_woocommerce_currency_symbol( $code ), ENT_QUOTES, 'UTF-8' ),
			'position' => in_array( $pos, array( 'left', 'left_space', 'right', 'right_space' ), true ) ? $pos : 'left',
			'decimals' => min( 4, max( 0, (int) wc_get_price_decimals() ) ),
		);
	}

	/**
	 * Booking status for a WooCommerce order status (null = leave unchanged).
	 *
	 * @param string $wc_status Order status without "wc-".
	 * @return string|null
	 */
	public static function booking_status_for( $wc_status ) {
		$map = (array) apply_filters(
			'mpk_order_to_booking_status',
			array(
				'pending'    => 'Pending',
				'on-hold'    => 'Pending',
				'processing' => 'Approved',
				'completed'  => 'Approved',
				'cancelled'  => 'Cancelled',
				'refunded'   => 'Cancelled',
			)
		);
		return isset( $map[ $wc_status ] ) ? $map[ $wc_status ] : null;
	}

	/**
	 * WooCommerce -> booking: keep the booking status in step with its order.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Old status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order.
	 */
	public function on_order_status_changed( $order_id, $from, $to, $order ) {
		if ( self::$syncing || ! $order instanceof WC_Order ) {
			return;
		}
		$booking_id = (int) $order->get_meta( self::META_BOOKING_ID );
		$new        = self::booking_status_for( $to );
		if ( ! $booking_id || ! $new ) {
			return;
		}
		$booking = MPK_Booking_Manager::get_booking_by_id( $booking_id );
		if ( ! $booking ) {
			return;
		}
		$current = 'Confirmed' === $booking->status ? 'Approved' : $booking->status;
		if ( $current === $new ) {
			return;
		}
		MPK_Booking_Manager::update_status( $booking_id, $new );

		// Booking email from the plugin (payment emails come from WooCommerce).
		if ( in_array( $new, array( 'Approved', 'Cancelled' ), true ) && class_exists( 'MPK_Mailer' ) ) {
			try {
				MPK_Mailer::send_status_update_email( $booking_id, $new );
			} catch ( \Throwable $e ) {
				error_log( '[MPK] Status email failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
		}
		do_action( 'mpk_booking_status_synced_from_order', $booking_id, $new, $order );
	}

	/**
	 * Booking -> WooCommerce: mirror a status change made on the bookings dashboard.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status Pending | Approved | Cancelled.
	 */
	public static function sync_order_from_booking( $booking_id, $new_status ) {
		if ( ! self::is_available() ) {
			return;
		}
		$booking = MPK_Booking_Manager::get_booking_by_id( $booking_id );
		$order   = ( $booking && ! empty( $booking->order_id ) ) ? wc_get_order( (int) $booking->order_id ) : null;
		if ( ! $order ) {
			return;
		}

		$current = $order->get_status();
		$target  = '';
		if ( 'Approved' === $new_status && in_array( $current, array( 'pending', 'on-hold', 'failed' ), true ) ) {
			$target = 'processing';
		} elseif ( 'Cancelled' === $new_status && ! in_array( $current, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			$target = 'cancelled';
		} elseif ( 'Pending' === $new_status && in_array( $current, array( 'processing', 'completed', 'cancelled' ), true ) ) {
			$target = 'on-hold';
		}
		if ( ! $target ) {
			return;
		}

		self::$syncing = true;
		try {
			/* translators: %s: booking status */
			$order->update_status( $target, sprintf( __( 'Booking marked %s on the Maldives bookings dashboard.', 'maldives-packages' ), $new_status ) );
		} finally {
			self::$syncing = false;
		}
	}

	/**
	 * Booking row for an order created by this plugin.
	 *
	 * @param WC_Order|int $order Order or ID.
	 * @return object|null
	 */
	private static function booking_for_order( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order || ! $order->get_meta( self::META_BOOKING_ID ) ) {
			return null;
		}
		return MPK_Booking_Manager::get_booking_by_id( (int) $order->get_meta( self::META_BOOKING_ID ) );
	}

	/**
	 * Heading text on the "Order received" page for booking orders.
	 *
	 * @param string   $text  Default text.
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public function thankyou_text( $text, $order ) {
		if ( ! $order instanceof WC_Order || ! $order->get_meta( self::META_BOOKING_ID ) ) {
			return $text;
		}
		if ( $order->is_paid() ) {
			return __( 'Thank you! Your payment has been received and your Maldives booking is confirmed.', 'maldives-packages' );
		}
		return __( 'Thank you! Your Maldives booking has been received.', 'maldives-packages' );
	}

	/**
	 * Booking summary card on the "Order received" page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function render_thankyou_booking( $order_id ) {
		$order   = wc_get_order( $order_id );
		$booking = $order ? self::booking_for_order( $order ) : null;
		if ( ! $booking ) {
			return;
		}

		$items = json_decode( (string) $booking->booking_items, true );
		$items = is_array( $items ) ? $items : array();

		if ( $order->is_paid() ) {
			$state = array( '#ecfdf5', '#a7f3d0', '#065f46', __( 'Payment received - booking confirmed. Our Travel Concierge will contact you within 24 hours with your itinerary.', 'maldives-packages' ) );
		} elseif ( $order->has_status( array( 'failed', 'cancelled' ) ) ) {
			$state = array( '#fef2f2', '#fecaca', '#991b1b', __( 'The payment was not completed. You can try again using the button below.', 'maldives-packages' ) );
		} else {
			$state = array( '#fffbeb', '#fde68a', '#92400e', __( 'We are waiting for your payment to be confirmed. You will receive an email as soon as it is.', 'maldives-packages' ) );
		}
		$date = function ( $d ) {
			return $d ? date_i18n( 'd M Y', strtotime( $d ) ) : '';
		};
		?>
		<section class="mpk-thankyou-booking" style="border:1px solid #e2e8f0; border-radius:16px; padding:24px; margin:0 0 28px; background:#ffffff;">
			<p style="margin:0 0 4px; font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#64748b;"><?php esc_html_e( 'Booking reference', 'maldives-packages' ); ?></p>
			<p style="margin:0 0 16px; font-size:24px; font-weight:800; color:#0f172a;"><?php echo esc_html( $booking->reference_id ); ?></p>

			<div style="background:<?php echo esc_attr( $state[0] ); ?>; border:1px solid <?php echo esc_attr( $state[1] ); ?>; color:<?php echo esc_attr( $state[2] ); ?>; border-radius:10px; padding:12px 16px; margin-bottom:18px; font-size:14px; line-height:1.5;">
				<?php echo esc_html( $state[3] ); ?>
			</div>

			<?php if ( $items ) : ?>
				<table style="width:100%; border-collapse:collapse; margin-bottom:14px; font-size:14px;">
					<?php foreach ( $items as $it ) : ?>
						<tr style="border-bottom:1px solid #f1f5f9;">
							<td style="padding:10px 0; color:#0f172a;">
								<strong><?php echo esc_html( isset( $it['hotel'] ) ? $it['hotel'] : '' ); ?></strong>
								<?php if ( ! empty( $it['room'] ) ) : ?>
									<br><span style="color:#64748b;"><?php echo esc_html( $it['room'] ); ?><?php echo ! empty( $it['location'] ) ? ' &middot; ' . esc_html( $it['location'] ) : ''; ?></span>
								<?php endif; ?>
							</td>
							<td style="padding:10px 0; text-align:right; color:#334155; white-space:nowrap;">
								<?php echo esc_html( $date( isset( $it['check_in'] ) ? $it['check_in'] : '' ) . ' - ' . $date( isset( $it['check_out'] ) ? $it['check_out'] : '' ) ); ?>
								<br><span style="color:#64748b;">
								<?php
								/* translators: %d: nights */
								echo esc_html( sprintf( _n( '%d night', '%d nights', (int) $it['nights'], 'maldives-packages' ), (int) $it['nights'] ) );
								?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endif; ?>

			<p style="margin:0; font-size:14px; color:#334155;">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: adults, 2: children, 3: infants, 4: rooms */
						__( 'Guests: %1$d adults, %2$d children, %3$d infants · Rooms: %4$d', 'maldives-packages' ),
						(int) $booking->adults,
						(int) $booking->children,
						(int) $booking->infants,
						(int) $booking->rooms_count
					)
				);
				?>
			</p>

			<?php if ( $order->needs_payment() ) : ?>
				<p style="margin:16px 0 0;">
					<a class="button" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Pay now', 'maldives-packages' ); ?></a>
				</p>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * How a gateway behaves in the wizard: 'bank', 'office', 'offline' or 'online'.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @return string
	 */
	public static function gateway_kind( $gateway_id ) {
		$map = (array) apply_filters(
			'mpk_offline_gateway_kinds',
			array(
				'bacs'   => 'bank',
				'cod'    => 'office',
				'cheque' => 'offline',
			)
		);
		return isset( $map[ $gateway_id ] ) ? $map[ $gateway_id ] : 'online';
	}

	/**
	 * Enabled WooCommerce gateways, shaped for the wizard's payment cards.
	 *
	 * @return array[] Keyed by gateway ID: id, title, desc, kind.
	 */
	public static function get_gateways() {
		if ( ! self::is_available() || ! WC()->payment_gateways() ) {
			return array();
		}
		$out = array();
		foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $id => $gw ) {
			$kind  = self::gateway_kind( $id );
			$title = wp_strip_all_tags( $gw->get_title() );
			$desc  = wp_strip_all_tags( (string) $gw->get_description() );

			// Friendlier defaults for the stock offline gateways (admins can rename them in WooCommerce).
			if ( 'office' === $kind && in_array( $title, array( 'Cash on delivery', __( 'Cash on delivery', 'woocommerce' ) ), true ) ) {
				$title = __( 'Office Visit Payment', 'maldives-packages' );
				$desc  = __( 'Pay in person at our office. Our team will assist you.', 'maldives-packages' );
			}
			if ( 'bank' === $kind && in_array( $title, array( 'Direct bank transfer', __( 'Direct bank transfer', 'woocommerce' ) ), true ) ) {
				$title = __( 'Bank Transfer', 'maldives-packages' );
				$desc  = __( 'Transfer to our bank account. Details sent after confirmation.', 'maldives-packages' );
			}

			$out[ $id ] = array(
				'id'    => $id,
				'title' => $title,
				'desc'  => $desc,
				'kind'  => $kind,
			);
		}
		return $out;
	}

	/**
	 * Keep only the wizard-chosen gateway on a booking's "Pay for order" page.
	 *
	 * @param array $gateways Available gateways.
	 * @return array
	 */
	public function restrict_order_pay_gateways( $gateways ) {
		if ( ! is_array( $gateways ) || ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-pay' ) ) {
			return $gateways;
		}
		$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
		if ( ! $order || ! $order->get_meta( self::META_BOOKING_ID ) ) {
			return $gateways;
		}
		$chosen = $order->get_payment_method();
		if ( $chosen && isset( $gateways[ $chosen ] ) ) {
			return array( $chosen => $gateways[ $chosen ] );
		}
		return $gateways;
	}

	/**
	 * Is WooCommerce installed, active and ready to take payments?
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( 'WooCommerce' )
			&& function_exists( 'wc_create_order' )
			&& function_exists( 'wc_get_page_id' )
			&& wc_get_page_id( 'checkout' ) > 0;
	}

	/**
	 * Should bookings be paid through WooCommerce?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$enabled = false;
		if ( self::is_available() ) {
			$s       = MPK_Data_Manager::get_settings();
			$enabled = ( ! isset( $s['wc_checkout'] ) || ! empty( $s['wc_checkout'] ) ) && ! empty( self::get_gateways() );
		}
		return (bool) apply_filters( 'mpk_use_woocommerce_checkout', $enabled );
	}

	/**
	 * Prevent the helper product from being added to a cart.
	 *
	 * @param bool $passed     Validation result.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public function block_add_to_cart( $passed, $product_id ) {
		if ( (int) $product_id === (int) get_option( self::PRODUCT_OPTION ) ) {
			return false;
		}
		return $passed;
	}

	/**
	 * Get (or lazily create) the private, virtual product used for booking line items.
	 * Using a real product keeps gateways that call $item->get_product() happy.
	 *
	 * @return WC_Product|null
	 */
	public static function get_product() {
		$id      = (int) get_option( self::PRODUCT_OPTION );
		$product = $id ? wc_get_product( $id ) : null;
		if ( $product && 'trash' !== $product->get_status() ) {
			return $product;
		}

		$product = new WC_Product_Simple();
		$product->set_name( __( 'Maldives Package Booking', 'maldives-packages' ) );
		$product->set_status( 'private' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( true );
		$product->set_tax_status( 'none' );
		$product->set_reviews_allowed( false );
		$product->set_regular_price( '0' );
		$product->set_short_description( __( 'Used internally by the Maldives Packages Booking plugin. Do not delete.', 'maldives-packages' ) );
		$new_id = $product->save();

		if ( ! $new_id ) {
			return null;
		}
		update_option( self::PRODUCT_OPTION, $new_id, false );
		return $product;
	}

	/**
	 * Map a country name (as typed in the wizard) to a WooCommerce country code.
	 *
	 * @param string $name Country name or code.
	 * @return string Two-letter code or ''.
	 */
	private static function country_code( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name || ! function_exists( 'WC' ) || ! WC()->countries ) {
			return '';
		}
		$countries = WC()->countries->get_countries();
		$upper     = strtoupper( $name );
		if ( isset( $countries[ $upper ] ) ) {
			return $upper;
		}
		foreach ( $countries as $code => $label ) {
			if ( 0 === strcasecmp( wp_strip_all_tags( html_entity_decode( $label ) ), $name ) ) {
				return $code;
			}
		}
		return '';
	}

	/**
	 * Add a non-taxable fee line to an order.
	 *
	 * @param WC_Order $order  Order.
	 * @param string   $label  Fee label.
	 * @param float    $amount Amount.
	 */
	private static function add_fee( $order, $label, $amount ) {
		$amount = round( (float) $amount, 2 );
		if ( $amount <= 0 ) {
			return;
		}
		$fee = new WC_Order_Item_Fee();
		$fee->set_name( $label );
		$fee->set_amount( $amount );
		$fee->set_total( $amount );
		$fee->set_tax_status( 'none' );
		$order->add_item( $fee );
	}

	/**
	 * Create a pending WooCommerce order for a saved booking.
	 * Totals come from the server-side price calculation only.
	 *
	 * @param int   $booking_id   Booking row ID.
	 * @param string $reference   Booking reference (MPK-...).
	 * @param array $booking      Sanitized booking data (lead_*, special_requests, grand_total ...).
	 * @param array $trip         Result of the server-side trip builder (items, pricing).
	 * @param string $gateway_id  WooCommerce gateway chosen in the wizard.
	 * @return WC_Order|WP_Error
	 */
	public static function create_order( $booking_id, $reference, $booking, $trip, $gateway_id = '' ) {
		$product = self::get_product();
		if ( ! $product ) {
			return new WP_Error( 'mpk_wc_product', 'Could not create the WooCommerce booking product.' );
		}

		try {
			$order = wc_create_order(
				array(
					'status'      => 'pending',
					'customer_id' => get_current_user_id(),
					'created_via' => 'mpk_booking',
				)
			);
			if ( is_wp_error( $order ) ) {
				return $order;
			}

			// Stay line items (one per hotel / room / date range).
			foreach ( (array) $trip['items'] as $it ) {
				$line_total = round( (float) $it['price'] * (int) $it['nights'] * max( 1, (int) $it['rooms'] ), 2 );

				$item = new WC_Order_Item_Product();
				$item->set_product( $product );
				$item->set_name( sprintf( '%1$s - %2$s', $it['hotel'], $it['room'] ) );
				$item->set_quantity( 1 );
				$item->set_subtotal( $line_total );
				$item->set_total( $line_total );
				$item->set_tax_class( '' );
				if ( ! empty( $it['location'] ) ) {
					$item->add_meta_data( __( 'Location', 'maldives-packages' ), $it['location'], true );
				}
				$item->add_meta_data( __( 'Check-in', 'maldives-packages' ), $it['check_in'], true );
				$item->add_meta_data( __( 'Check-out', 'maldives-packages' ), $it['check_out'], true );
				$item->add_meta_data( __( 'Nights', 'maldives-packages' ), (int) $it['nights'], true );
				$item->add_meta_data( __( 'Rooms', 'maldives-packages' ), max( 1, (int) $it['rooms'] ), true );
				$item->add_meta_data( __( 'Rate / night', 'maldives-packages' ), MPK_Data_Manager::format_price( $it['price'] ), true );
				$order->add_item( $item );
			}

			// Other price components as non-taxable fees (the plugin already calculated tax).
			$p = (array) $trip['pricing'];
			if ( ! empty( $p['extra_adult_cost'] ) ) {
				/* translators: %d: number of extra adults */
				self::add_fee( $order, sprintf( __( 'Extra adults (%d)', 'maldives-packages' ), (int) $p['extra_adults'] ), $p['extra_adult_cost'] );
			}
			if ( ! empty( $p['child_cost'] ) ) {
				self::add_fee( $order, __( 'Children', 'maldives-packages' ), $p['child_cost'] );
			}
			if ( ! empty( $p['tax'] ) ) {
				self::add_fee( $order, __( 'Tax', 'maldives-packages' ), $p['tax'] );
			}
			if ( ! empty( $p['extras'] ) ) {
				self::add_fee( $order, __( 'Extra / Transfer charge', 'maldives-packages' ), $p['extras'] );
			}
			if ( ! empty( $p['service_fee'] ) ) {
				self::add_fee( $order, __( 'Service fee', 'maldives-packages' ), $p['service_fee'] );
			}

			// Billing details from the wizard.
			$name  = trim( (string) $booking['lead_name'] );
			$parts = preg_split( '/\s+/', $name, 2 );
			$order->set_billing_first_name( isset( $parts[0] ) ? $parts[0] : $name );
			$order->set_billing_last_name( isset( $parts[1] ) ? $parts[1] : '' );
			$order->set_billing_email( $booking['lead_email'] );
			$order->set_billing_phone( isset( $booking['lead_phone'] ) ? $booking['lead_phone'] : '' );
			$country = self::country_code( isset( $booking['lead_country'] ) ? $booking['lead_country'] : '' );
			if ( $country ) {
				$order->set_billing_country( $country );
			}
			if ( ! empty( $booking['special_requests'] ) ) {
				$order->set_customer_note( $booking['special_requests'] );
			}

			$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
			if ( $gateway_id && isset( $gateways[ $gateway_id ] ) ) {
				$order->set_payment_method( $gateways[ $gateway_id ] );
			}

			$order->update_meta_data( self::META_BOOKING_ID, (int) $booking_id );
			$order->update_meta_data( self::META_REFERENCE, $reference );

			$order->calculate_totals( false );

			// Guard against cent-level rounding drift: the booking total is authoritative.
			$grand_total = round( (float) $booking['grand_total'], 2 );
			if ( abs( (float) $order->get_total() - $grand_total ) >= 0.01 ) {
				$order->set_total( $grand_total );
			}

			/* translators: %s: booking reference */
			$order->add_order_note( sprintf( __( 'Created from Maldives package booking %s.', 'maldives-packages' ), $reference ) );
			$order->save();

			// Offline methods: nothing to pay online - hold the order until payment is received.
			if ( $gateway_id && 'online' !== self::gateway_kind( $gateway_id ) ) {
				/* translators: %s: payment method title */
				$order->update_status( 'on-hold', sprintf( __( 'Awaiting %s payment.', 'maldives-packages' ), $order->get_payment_method_title() ) );
			}

			return $order;
		} catch ( \Throwable $e ) {
			if ( isset( $order ) && $order instanceof WC_Order && $order->get_id() ) {
				$order->delete( true );
			}
			return new WP_Error( 'mpk_wc_order', $e->getMessage() );
		}
	}
}
