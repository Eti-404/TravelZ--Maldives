<?php
/**
 * Booking Manager for Maldives Packages Booking (MPK).
 *
 * Handles custom database schema creation, table initialization,
 * booking record creation, reference ID generation, and queries.
 *
 * Approved Prefix: MPK / mpk_
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MPK_Booking_Manager {

	/**
	 * Table name without prefix.
	 */
	const TABLE_NAME = 'mpk_bookings';

	/**
	 * Schema version. Bump whenever the CREATE TABLE definition changes.
	 */
	const DB_VERSION = '1.3.0';

	/**
	 * Max lengths of varchar columns (keeps inserts from failing in MySQL strict mode).
	 */
	const FIELD_LIMITS = array(
		'reference_id'   => 50,
		'lead_name'      => 150,
		'lead_email'     => 254,
		'lead_phone'     => 50,
		'lead_country'   => 100,
		'passport_no'    => 50,
		'payment_method' => 50,
		'status'         => 20,
	);

	/**
	 * Get the fully prefixed table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create or upgrade the custom bookings database table using dbDelta.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// Two spaces between PRIMARY KEY and definition required by dbDelta.
		// Each field must be on its own line.
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			reference_id varchar(50) NOT NULL,
			lead_name varchar(150) NOT NULL,
			lead_email varchar(254) NOT NULL,
			lead_phone varchar(50) DEFAULT '' NOT NULL,
			lead_country varchar(100) DEFAULT '' NOT NULL,
			passport_no varchar(50) DEFAULT '' NOT NULL,
			passport_file_url varchar(500) DEFAULT '' NOT NULL,
			special_requests text,
			selected_location text,
			hotel_name text,
			room_name text,
			booking_items longtext,
			pricing_breakdown longtext,
			check_in date DEFAULT NULL,
			check_out date DEFAULT NULL,
			adults int(11) DEFAULT 1 NOT NULL,
			children int(11) DEFAULT 0 NOT NULL,
			infants int(11) DEFAULT 0 NOT NULL,
			rooms_count int(11) DEFAULT 1 NOT NULL,
			grand_total decimal(10,2) DEFAULT 0.00 NOT NULL,
			payment_method varchar(50) DEFAULT '' NOT NULL,
			status varchar(20) DEFAULT 'Pending' NOT NULL,
			order_id bigint(20) DEFAULT 0 NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY reference_id (reference_id),
			KEY order_id (order_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Self-heal: ensure passport_file_url column exists in previously created tables
		$col_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'passport_file_url'" );
		if ( empty( $col_exists ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN passport_file_url varchar(500) DEFAULT '' NOT NULL AFTER passport_no" );
		}

		update_option( 'mpk_db_version', self::DB_VERSION );
	}

	/**
	 * Run create_table() only when the stored schema version is outdated
	 * (avoids dbDelta + SHOW COLUMNS on every request).
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( self::DB_VERSION !== get_option( 'mpk_db_version' ) ) {
			self::create_table();
		}
	}

	/**
	 * Trim a string to a column's max length (multibyte safe).
	 *
	 * @param string $field Column name.
	 * @param string $value Value.
	 * @return string
	 */
	private static function fit( $field, $value ) {
		$value = (string) $value;
		if ( ! isset( self::FIELD_LIMITS[ $field ] ) ) {
			return $value;
		}
		$max = self::FIELD_LIMITS[ $field ];
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	/**
	 * Generate a unique reference ID matching MPK-YYYYMMDD-XXXXX.
	 *
	 * @return string Unique reference code.
	 */
	public static function generate_reference_id() {
		global $wpdb;
		$table_name = self::get_table_name();

		$prefix    = 'MPK-' . gmdate( 'Ymd' ) . '-';
		$max_tries = 10;

		for ( $i = 0; $i < $max_tries; $i++ ) {
			// Generate 5 uppercase alphanumeric chars.
			$rand = strtoupper( wp_generate_password( 5, false, false ) );
			$ref  = $prefix . $rand;

			// Verify uniqueness.
			$exists = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$table_name} WHERE reference_id = %s LIMIT 1", $ref )
			);

			if ( ! $exists ) {
				return $ref;
			}
		}

		// Fallback timestamp based.
		return $prefix . strtoupper( substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 5 ) );
	}

	/**
	 * Insert a new booking record into the database.
	 *
	 * @param array $data Booking parameters.
	 * @return array|WP_Error Array with 'id' and 'reference_id' on success, WP_Error on failure.
	 */
	public static function create_booking( $data ) {
		global $wpdb;
		$table_name = self::get_table_name();

		// Self-heal table if not yet created.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			self::create_table();
		}

		// Reference IDs are always generated server-side (never accepted from the client).
		$reference_id = self::generate_reference_id();

		$insert_data = array(
			'reference_id'      => $reference_id,
			'lead_name'         => isset( $data['lead_name'] ) ? sanitize_text_field( $data['lead_name'] ) : '',
			'lead_email'        => isset( $data['lead_email'] ) ? sanitize_email( $data['lead_email'] ) : '',
			'lead_phone'        => isset( $data['lead_phone'] ) ? sanitize_text_field( $data['lead_phone'] ) : '',
			'lead_country'      => isset( $data['lead_country'] ) ? sanitize_text_field( $data['lead_country'] ) : '',
			'passport_no'       => isset( $data['passport_no'] ) ? sanitize_text_field( $data['passport_no'] ) : '',
			'passport_file_url' => isset( $data['passport_file_url'] ) ? esc_url_raw( $data['passport_file_url'] ) : '',
			'special_requests'  => isset( $data['special_requests'] ) ? sanitize_textarea_field( $data['special_requests'] ) : '',
			'selected_location' => isset( $data['selected_location'] ) ? sanitize_text_field( $data['selected_location'] ) : '',
			'hotel_name'        => isset( $data['hotel_name'] ) ? sanitize_text_field( $data['hotel_name'] ) : '',
			'room_name'         => isset( $data['room_name'] ) ? sanitize_text_field( $data['room_name'] ) : '',
			'check_in'          => ! empty( $data['check_in'] ) ? sanitize_text_field( $data['check_in'] ) : null,
			'check_out'         => ! empty( $data['check_out'] ) ? sanitize_text_field( $data['check_out'] ) : null,
			'adults'            => isset( $data['adults'] ) ? absint( $data['adults'] ) : 1,
			'children'          => isset( $data['children'] ) ? absint( $data['children'] ) : 0,
			'infants'           => isset( $data['infants'] ) ? absint( $data['infants'] ) : 0,
			'rooms_count'       => isset( $data['rooms_count'] ) ? absint( $data['rooms_count'] ) : 1,
			'grand_total'       => isset( $data['grand_total'] ) ? floatval( $data['grand_total'] ) : 0.00,
			'payment_method'    => isset( $data['payment_method'] ) ? sanitize_text_field( $data['payment_method'] ) : 'office',
			'status'            => ! empty( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'Pending',
			'created_at'        => current_time( 'mysql' ),
			'booking_items'     => ! empty( $data['booking_items'] ) && is_array( $data['booking_items'] ) ? wp_json_encode( $data['booking_items'] ) : '',
			'pricing_breakdown' => ! empty( $data['pricing_breakdown'] ) && is_array( $data['pricing_breakdown'] ) ? wp_json_encode( $data['pricing_breakdown'] ) : '',
		);

		// Keep varchar fields within column limits so strict-mode inserts never fail.
		foreach ( self::FIELD_LIMITS as $field => $max ) {
			if ( isset( $insert_data[ $field ] ) && is_string( $insert_data[ $field ] ) ) {
				$insert_data[ $field ] = self::fit( $field, $insert_data[ $field ] );
			}
		}

		$formats = array(
			'%s', // reference_id
			'%s', // lead_name
			'%s', // lead_email
			'%s', // lead_phone
			'%s', // lead_country
			'%s', // passport_no
			'%s', // passport_file_url
			'%s', // special_requests
			'%s', // selected_location
			'%s', // hotel_name
			'%s', // room_name
			'%s', // check_in
			'%s', // check_out
			'%d', // adults
			'%d', // children
			'%d', // infants
			'%d', // rooms_count
			'%f', // grand_total
			'%s', // payment_method
			'%s', // status
			'%s', // created_at
			'%s', // booking_items
			'%s', // pricing_breakdown
		);

		$result = $wpdb->insert( $table_name, $insert_data, $formats );

		// Rare race: another request took the same reference in the meantime - retry once.
		if ( false === $result && false !== stripos( (string) $wpdb->last_error, 'duplicate' ) ) {
			$reference_id                = self::generate_reference_id();
			$insert_data['reference_id'] = $reference_id;
			$result                      = $wpdb->insert( $table_name, $insert_data, $formats );
		}

		if ( false === $result ) {
			// Detailed DB error is for server logs only; callers must not show it to visitors.
			return new WP_Error( 'db_insert_error', $wpdb->last_error ? $wpdb->last_error : __( 'Could not save booking to database.', 'maldives-packages' ) );
		}

		return array(
			'id'           => $wpdb->insert_id,
			'reference_id' => $reference_id,
		);
	}

	/**
	 * Link a booking to its WooCommerce order.
	 *
	 * @param int $id       Booking record ID.
	 * @param int $order_id WooCommerce order ID.
	 * @return bool
	 */
	public static function set_order_id( $id, $order_id ) {
		global $wpdb;
		return false !== $wpdb->update(
			self::get_table_name(),
			array( 'order_id' => absint( $order_id ) ),
			array( 'id' => absint( $id ) ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Update a booking's status.
	 *
	 * @param int    $id     Booking record ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;
		return false !== $wpdb->update(
			self::get_table_name(),
			array( 'status' => self::fit( 'status', sanitize_text_field( $status ) ) ),
			array( 'id' => absint( $id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get a booking record by its WooCommerce order ID.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return object|null
	 */
	public static function get_booking_by_order_id( $order_id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE order_id = %d LIMIT 1", absint( $order_id ) )
		);
	}

	/**
	 * Get a booking record by ID.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public static function get_booking_by_id( $id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", absint( $id ) )
		);
	}

	public static function get_booking_by_reference( $reference_id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE reference_id = %s LIMIT 1", $reference_id )
		);
	}

	/**
	 * Permanently delete a booking record by ID.
	 *
	 * @param int $id Booking record ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_booking( $id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$deleted = $wpdb->delete(
			$table_name,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);

		return ( false !== $deleted && $deleted > 0 );
	}
}
