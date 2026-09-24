<?php
/**
 * WooCommerce bridge for Maldives Packages Booking (MPK).
 *
 * When WooCommerce is active (and enabled in Settings -> Payment), every booking
 * gets a WooCommerce order and the traveler is sent to the "Pay for order" page,
 * so payment methods (bank transfer, office / cash, SSLCommerz ...) are handled
 * entirely by WooCommerce gateways.
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
	 * Register hooks.
	 */
	public function __construct() {
		// The helper product only exists to carry booking line items - never let it into a cart.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'block_add_to_cart' ), 10, 2 );
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
			$enabled = ! isset( $s['wc_checkout'] ) || ! empty( $s['wc_checkout'] );
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
	 * @return WC_Order|WP_Error
	 */
	public static function create_order( $booking_id, $reference, $booking, $trip ) {
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

			return $order;
		} catch ( \Throwable $e ) {
			if ( isset( $order ) && $order instanceof WC_Order && $order->get_id() ) {
				$order->delete( true );
			}
			return new WP_Error( 'mpk_wc_order', $e->getMessage() );
		}
	}
}
