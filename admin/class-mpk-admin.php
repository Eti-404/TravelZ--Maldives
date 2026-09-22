<?php
/**
 * Admin Controller for Maldives Packages Booking (MPK).
 *
 * Manages the WordPress Admin dashboard menu, booking list table,
 * stats overview, AJAX status management, and customer details modal.
 *
 * Approved Prefix: MPK / mpk_
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MPK_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var MPK_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return MPK_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor: register hooks.
	 */
	public function __construct() {
		self::$instance = $this;

		add_action( 'admin_menu', array( $this, 'register_unified_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_mpk_update_booking_status', array( $this, 'ajax_update_booking_status' ) );
		add_action( 'admin_init', array( $this, 'handle_admin_redirects' ) );
		add_filter( 'parent_file', array( $this, 'filter_parent_file' ) );
		add_filter( 'submenu_file', array( $this, 'filter_submenu_file' ) );
	}

	/**
	 * Register the unified Maldives Packages admin menu and all submenus.
	 */
	public function register_unified_admin_menu() {
		// 1. Top-level Menu: Maldives Packages
		add_menu_page(
			__( 'Maldives Packages', 'maldives-packages' ),
			__( 'Maldives Packages', 'maldives-packages' ),
			'manage_options',
			'maldives-packages',
			array( $this, 'render_bookings_page' ),
			'dashicons-palmtree',
			26
		);

		// Submenu 1: Customer Bookings (renaming top-level default submenu)
		add_submenu_page(
			'maldives-packages',
			__( 'Customer Bookings', 'maldives-packages' ),
			__( 'Customer Bookings', 'maldives-packages' ),
			'manage_options',
			'maldives-packages',
			array( $this, 'render_bookings_page' )
		);

		// Submenu 2: Hotels & Stays (mpk_hotel CPT)
		add_submenu_page(
			'maldives-packages',
			__( 'Hotels & Stays', 'maldives-packages' ),
			__( 'Hotels & Stays', 'maldives-packages' ),
			'manage_options',
			'edit.php?post_type=mpk_hotel'
		);

		// Submenu 3: Destinations (mpk_destination Taxonomy)
		add_submenu_page(
			'maldives-packages',
			__( 'Destinations', 'maldives-packages' ),
			__( 'Destinations', 'maldives-packages' ),
			'manage_options',
			'edit-tags.php?taxonomy=mpk_destination&post_type=mpk_hotel'
		);

		// Submenu 4: Settings (4-tab config panel)
		add_submenu_page(
			'maldives-packages',
			__( 'Package Settings', 'maldives-packages' ),
			__( 'Settings', 'maldives-packages' ),
			'manage_options',
			'mpk-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Backward compatibility alias for register_admin_menu.
	 */
	public function register_admin_menu() {
		$this->register_unified_admin_menu();
	}

	/**
	 * Render settings page callback.
	 */
	public function render_settings_page() {
		if ( ! class_exists( 'MPK_Settings' ) ) {
			require_once MPK_PLUGIN_DIR . 'admin/class-mpk-settings.php';
		}
		MPK_Settings::render_page();
	}

	/**
	 * Enqueue admin scripts and styles conditionally for this page.
	 *
	 * @param string $hook Admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_maldives-packages' !== $hook && 'maldives-packages_page_mpk-bookings' !== $hook ) {
			return;
		}

		// Enqueue dashicons if not already loaded.
		wp_enqueue_style( 'dashicons' );
	}

	/**
	 * Backward compatibility: Redirect legacy menu page requests to unified menu.
	 */
	public function handle_admin_redirects() {
		global $pagenow;

		if ( 'admin.php' !== $pagenow ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'mpk-bookings' === $page || 'mpk-packages' === $page ) {
			$query_args = $_GET;
			$query_args['page'] = 'maldives-packages';
			wp_safe_redirect( add_query_arg( $query_args, admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	/**
	 * Keep 'Maldives Packages' menu open when editing CPT or taxonomy.
	 *
	 * @param string $parent_file Current parent file.
	 * @return string
	 */
	public function filter_parent_file( $parent_file ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = $screen ? $screen->post_type : ( isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '' );
		$taxonomy  = $screen ? $screen->taxonomy : ( isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '' );

		if ( 'mpk_hotel' === $post_type || 'mpk_destination' === $taxonomy ) {
			return 'maldives-packages';
		}

		return $parent_file;
	}

	/**
	 * Highlight corresponding submenu item when editing CPT or taxonomy.
	 *
	 * @param string $submenu_file Current submenu file.
	 * @return string
	 */
	public function filter_submenu_file( $submenu_file ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = $screen ? $screen->post_type : ( isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '' );
		$taxonomy  = $screen ? $screen->taxonomy : ( isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '' );

		if ( 'mpk_destination' === $taxonomy ) {
			return 'edit-tags.php?taxonomy=mpk_destination&post_type=mpk_hotel';
		}

		if ( 'mpk_hotel' === $post_type ) {
			return 'edit.php?post_type=mpk_hotel';
		}

		return $submenu_file;
	}

	/**
	 * Generate status badge HTML.
	 *
	 * @param string $status Booking status.
	 * @return string
	 */
	public static function get_status_badge( $status ) {
		$status_clean = ucfirst( strtolower( trim( $status ) ) );
		$class = 'mpk-badge-pending';
		$dot_color = '#d97706';

		if ( 'Approved' === $status_clean || 'Confirmed' === $status_clean ) {
			$status_clean = 'Approved';
			$class = 'mpk-badge-approved';
			$dot_color = '#16a34a';
		} elseif ( 'Cancelled' === $status_clean ) {
			$class = 'mpk-badge-cancelled';
			$dot_color = '#dc2626';
		}

		return sprintf(
			'<span class="mpk-badge %s"><span class="mpk-badge-dot" style="background:%s;"></span>%s</span>',
			esc_attr( $class ),
			esc_attr( $dot_color ),
			esc_html( $status_clean )
		);
	}

	/**
	 * AJAX Handler: Update booking status.
	 */
	public function ajax_update_booking_status() {
		// Capability check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied. Administrator access required.', 'maldives-packages' ) ), 403 );
		}

		// Nonce check
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'mpk_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page.', 'maldives-packages' ) ), 403 );
		}

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		$new_status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		$allowed_statuses = array( 'Pending', 'Approved', 'Cancelled' );
		if ( ! $booking_id || ! in_array( $new_status, $allowed_statuses, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid booking ID or status value.', 'maldives-packages' ) ), 400 );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'mpk_bookings';

		$updated = $wpdb->update(
			$table_name,
			array( 'status' => $new_status ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update database record.', 'maldives-packages' ) ), 500 );
		}

		// Trigger Automated Customer Status-Update Email
		if ( class_exists( 'MPK_Mailer' ) ) {
			try {
				MPK_Mailer::send_status_update_email( $booking_id, $new_status );
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'MPK_Mailer Status Update Trigger Exception: ' . $e->getMessage() );
				}
			}
		}

		// Calculate updated counts
		$count_pending   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'Pending'" );
		$count_approved  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status IN ('Approved', 'Confirmed')" );
		$count_cancelled = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'Cancelled'" );
		$count_total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

		wp_send_json_success(
			array(
				'booking_id'      => $booking_id,
				'status'          => $new_status,
				'badge_html'      => self::get_status_badge( $new_status ),
				'message'         => sprintf( __( 'Booking status updated to %s.', 'maldives-packages' ), $new_status ),
				'counts'          => array(
					'total'     => $count_total,
					'pending'   => $count_pending,
					'approved'  => $count_approved,
					'cancelled' => $count_cancelled,
				),
			)
		);
	}

	/**
	 * Render the main Maldives Bookings admin page.
	 */
	public function render_bookings_page() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mpk_bookings';

		// Handle Filters & Search
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';
		$search_query  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		// Base SQL
		$where_clauses = array( '1=1' );
		$query_params  = array();

		if ( ! empty( $status_filter ) && 'all' !== $status_filter ) {
			if ( 'approved' === strtolower( $status_filter ) ) {
				$where_clauses[] = "status IN ('Approved', 'Confirmed')";
			} else {
				$where_clauses[] = 'status = %s';
				$query_params[]  = ucfirst( $status_filter );
			}
		}

		if ( ! empty( $search_query ) ) {
			$like = '%' . $wpdb->esc_like( $search_query ) . '%';
			$where_clauses[] = '(reference_id LIKE %s OR lead_name LIKE %s OR lead_email LIKE %s OR hotel_name LIKE %s)';
			$query_params[]  = $like;
			$query_params[]  = $like;
			$query_params[]  = $like;
			$query_params[]  = $like;
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$query = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY id DESC";

		if ( ! empty( $query_params ) ) {
			$safe_query = $wpdb->prepare( $query, $query_params );
			$bookings = $wpdb->get_results( $safe_query );
		} else {
			$bookings = $wpdb->get_results( $query );
		}

		// Summary Stats
		$total_count     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		$pending_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'Pending'" );
		$approved_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status IN ('Approved', 'Confirmed')" );
		$cancelled_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'Cancelled'" );
		$total_revenue   = (float) $wpdb->get_var( "SELECT SUM(grand_total) FROM {$table_name} WHERE status IN ('Approved', 'Confirmed')" );

		$admin_nonce = wp_create_nonce( 'mpk_admin_nonce' );
		?>
		<div class="wrap mpk-admin-wrap">
			<!-- Header -->
			<div class="mpk-admin-header">
				<div class="mpk-header-left">
					<span class="dashicons dashicons-tickets-alt mpk-header-icon"></span>
					<div>
						<h1 class="wp-heading-inline"><?php esc_html_e( 'Customer Booking Management', 'maldives-packages' ); ?></h1>
						<p class="mpk-header-subtitle"><?php esc_html_e( 'Manage luxury customer itineraries, booking inquiries, and payment approvals.', 'maldives-packages' ); ?></p>
					</div>
				</div>
				<div class="mpk-header-right">
					<span class="mpk-live-badge"><span class="mpk-pulse"></span> Live System</span>
				</div>
			</div>

			<!-- Stats Overview Cards -->
			<div class="mpk-stats-grid">
				<div class="mpk-stat-card mpk-stat-total">
					<div class="mpk-stat-meta">
						<span class="mpk-stat-label"><?php esc_html_e( 'Total Inquiries', 'maldives-packages' ); ?></span>
						<span class="mpk-stat-value" id="mpk-stat-count-total"><?php echo esc_html( $total_count ); ?></span>
					</div>
					<div class="mpk-stat-icon-wrap"><span class="dashicons dashicons-tickets-alt"></span></div>
				</div>

				<div class="mpk-stat-card mpk-stat-pending">
					<div class="mpk-stat-meta">
						<span class="mpk-stat-label"><?php esc_html_e( 'Pending Review', 'maldives-packages' ); ?></span>
						<span class="mpk-stat-value" id="mpk-stat-count-pending"><?php echo esc_html( $pending_count ); ?></span>
					</div>
					<div class="mpk-stat-icon-wrap"><span class="dashicons dashicons-clock"></span></div>
				</div>

				<div class="mpk-stat-card mpk-stat-approved">
					<div class="mpk-stat-meta">
						<span class="mpk-stat-label"><?php esc_html_e( 'Approved & Confirmed', 'maldives-packages' ); ?></span>
						<span class="mpk-stat-value" id="mpk-stat-count-approved"><?php echo esc_html( $approved_count ); ?></span>
					</div>
					<div class="mpk-stat-icon-wrap"><span class="dashicons dashicons-yes-alt"></span></div>
				</div>

				<div class="mpk-stat-card mpk-stat-cancelled">
					<div class="mpk-stat-meta">
						<span class="mpk-stat-label"><?php esc_html_e( 'Cancelled', 'maldives-packages' ); ?></span>
						<span class="mpk-stat-value" id="mpk-stat-count-cancelled"><?php echo esc_html( $cancelled_count ); ?></span>
					</div>
					<div class="mpk-stat-icon-wrap"><span class="dashicons dashicons-dismiss"></span></div>
				</div>

				<div class="mpk-stat-card mpk-stat-revenue">
					<div class="mpk-stat-meta">
						<span class="mpk-stat-label"><?php esc_html_e( 'Approved Bookings Value', 'maldives-packages' ); ?></span>
						<span class="mpk-stat-value">$<?php echo esc_html( number_format( $total_revenue, 2 ) ); ?></span>
					</div>
					<div class="mpk-stat-icon-wrap"><span class="dashicons dashicons-money-alt"></span></div>
				</div>
			</div>

			<!-- Filter Bar & Search -->
			<div class="mpk-filter-bar">
				<ul class="subsubsub mpk-status-tabs">
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=maldives-packages' ) ); ?>" class="<?php echo ( 'all' === $status_filter ) ? 'current' : ''; ?>">
							<?php esc_html_e( 'All', 'maldives-packages' ); ?> <span class="count">(<?php echo esc_html( $total_count ); ?>)</span>
						</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=maldives-packages&status=pending' ) ); ?>" class="<?php echo ( 'pending' === $status_filter ) ? 'current' : ''; ?>">
							<?php esc_html_e( 'Pending', 'maldives-packages' ); ?> <span class="count">(<?php echo esc_html( $pending_count ); ?>)</span>
						</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=maldives-packages&status=approved' ) ); ?>" class="<?php echo ( 'approved' === $status_filter ) ? 'current' : ''; ?>">
							<?php esc_html_e( 'Approved', 'maldives-packages' ); ?> <span class="count">(<?php echo esc_html( $approved_count ); ?>)</span>
						</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=maldives-packages&status=cancelled' ) ); ?>" class="<?php echo ( 'cancelled' === $status_filter ) ? 'current' : ''; ?>">
							<?php esc_html_e( 'Cancelled', 'maldives-packages' ); ?> <span class="count">(<?php echo esc_html( $cancelled_count ); ?>)</span>
						</a>
					</li>
				</ul>

				<form method="get" class="mpk-search-box">
					<input type="hidden" name="page" value="maldives-packages" />
					<?php if ( ! empty( $status_filter ) && 'all' !== $status_filter ) : ?>
						<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>" />
					<?php endif; ?>
					<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search by guest, email or reference...', 'maldives-packages' ); ?>" />
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'maldives-packages' ); ?></button>
					<?php if ( ! empty( $search_query ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=maldives-packages' ) ); ?>" class="button button-link"><?php esc_html_e( 'Clear', 'maldives-packages' ); ?></a>
					<?php endif; ?>
				</form>
			</div>

			<!-- Notice Banner -->
			<div id="mpk-admin-toast" class="mpk-toast" style="display:none;"></div>

			<!-- Bookings List Table -->
			<div class="mpk-table-card">
				<table class="wp-list-table widefat fixed striped mpk-bookings-table">
					<thead>
						<tr>
							<th style="width: 140px;"><?php esc_html_e( 'Ref Code / Date', 'maldives-packages' ); ?></th>
							<th style="width: 180px;"><?php esc_html_e( 'Guest Details', 'maldives-packages' ); ?></th>
							<th><?php esc_html_e( 'Resort & Room', 'maldives-packages' ); ?></th>
							<th style="width: 170px;"><?php esc_html_e( 'Travel Dates', 'maldives-packages' ); ?></th>
							<th style="width: 130px;"><?php esc_html_e( 'Guests & Rooms', 'maldives-packages' ); ?></th>
							<th style="width: 140px;"><?php esc_html_e( 'Amount & Payment', 'maldives-packages' ); ?></th>
							<th style="width: 110px;"><?php esc_html_e( 'Status', 'maldives-packages' ); ?></th>
							<th style="width: 170px; text-align: right;"><?php esc_html_e( 'Actions', 'maldives-packages' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $bookings ) ) : ?>
							<tr>
								<td colspan="8" style="text-align: center; padding: 36px 12px; color: #64748b;">
									<span class="dashicons dashicons-info" style="font-size: 32px; width: 32px; height: 32px; opacity: 0.5; margin-bottom: 8px;"></span>
									<p style="font-size: 14px; margin: 0;"><?php esc_html_e( 'No booking records found.', 'maldives-packages' ); ?></p>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $bookings as $b ) : ?>
								<?php
								// Calculate Nights
								$nights_count = 0;
								if ( ! empty( $b->check_in ) && ! empty( $b->check_out ) ) {
									$d1 = new DateTime( $b->check_in );
									$d2 = new DateTime( $b->check_out );
									$diff = $d1->diff( $d2 );
									$nights_count = max( 0, (int) $diff->days );
								}

								// Payment Method label
								$payment_label = 'Office Visit';
								if ( 'bank' === strtolower( $b->payment_method ) ) {
									$payment_label = 'Bank Transfer';
								} elseif ( ! empty( $b->payment_method ) ) {
									$payment_label = ucfirst( $b->payment_method );
								}

								// JSON Data for Modal
								$modal_data = array(
									'id'                => (int) $b->id,
									'reference_id'      => $b->reference_id,
									'lead_name'         => $b->lead_name,
									'lead_email'        => $b->lead_email,
									'lead_phone'        => $b->lead_phone,
									'lead_country'      => $b->lead_country,
									'passport_no'       => $b->passport_no,
									'passport_file_url' => ! empty( $b->passport_file_url ) ? $b->passport_file_url : '',
									'special_requests'  => $b->special_requests,
									'selected_location' => $b->selected_location,
									'hotel_name'        => $b->hotel_name,
									'room_name'         => $b->room_name,
									'check_in'          => $b->check_in ? gmdate( 'd M Y', strtotime( $b->check_in ) ) : '—',
									'check_out'         => $b->check_out ? gmdate( 'd M Y', strtotime( $b->check_out ) ) : '—',
									'nights'            => $nights_count,
									'adults'            => (int) $b->adults,
									'children'          => (int) $b->children,
									'infants'           => (int) $b->infants,
									'rooms_count'       => (int) $b->rooms_count,
									'grand_total'       => number_format( (float) $b->grand_total, 2 ),
									'payment_method'    => $payment_label,
									'status'            => $b->status,
									'created_at'        => gmdate( 'd M Y, H:i', strtotime( $b->created_at ) ),
								);
								?>
								<tr id="mpk-booking-row-<?php echo esc_attr( $b->id ); ?>">
									<!-- Ref Code & Date -->
									<td>
										<strong class="mpk-ref-code"><?php echo esc_html( $b->reference_id ); ?></strong>
										<div class="mpk-cell-sub">
											<?php echo esc_html( gmdate( 'd M Y, H:i', strtotime( $b->created_at ) ) ); ?>
										</div>
									</td>

									<!-- Guest Details -->
									<td>
										<div class="mpk-guest-name"><?php echo esc_html( $b->lead_name ); ?></div>
										<div class="mpk-cell-sub">
											<span class="dashicons dashicons-email-alt"></span> <?php echo esc_html( $b->lead_email ); ?>
										</div>
										<?php if ( ! empty( $b->lead_phone ) ) : ?>
											<div class="mpk-cell-sub">
												<span class="dashicons dashicons-phone"></span> <?php echo esc_html( $b->lead_phone ); ?>
											</div>
										<?php endif; ?>
										<?php if ( ! empty( $b->lead_country ) ) : ?>
											<div class="mpk-cell-sub" style="color: #64748b;">
												<span class="dashicons dashicons-admin-site"></span> <?php echo esc_html( $b->lead_country ); ?>
											</div>
										<?php endif; ?>
										<?php if ( ! empty( $b->passport_file_url ) ) : ?>
											<div class="mpk-cell-sub" style="margin-top: 4px;">
												<a href="<?php echo esc_url( $b->passport_file_url ); ?>" target="_blank" style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; color:#0284c7; text-decoration:none;" title="<?php esc_attr_e( 'View Attached Passport', 'maldives-packages' ); ?>">
													<span class="dashicons dashicons-paperclip" style="font-size:13px; width:13px; height:13px;"></span> <?php esc_html_e( 'Passport Copy', 'maldives-packages' ); ?>
												</a>
											</div>
										<?php endif; ?>
									</td>

									<!-- Resort & Room -->
									<td>
										<div class="mpk-resort-title"><?php echo esc_html( $b->hotel_name ?: '—' ); ?></div>
										<div class="mpk-cell-sub">
											<?php echo esc_html( $b->room_name ?: 'Standard Room' ); ?>
										</div>
										<?php if ( ! empty( $b->selected_location ) ) : ?>
											<span class="mpk-loc-tag"><?php echo esc_html( $b->selected_location ); ?></span>
										<?php endif; ?>
									</td>

									<!-- Travel Dates -->
									<td>
										<div style="font-weight: 500;">
											<?php echo esc_html( $b->check_in ? gmdate( 'd M Y', strtotime( $b->check_in ) ) : '—' ); ?>
										</div>
										<div style="color: #64748b; font-size: 11px;">to</div>
										<div style="font-weight: 500;">
											<?php echo esc_html( $b->check_out ? gmdate( 'd M Y', strtotime( $b->check_out ) ) : '—' ); ?>
										</div>
										<?php if ( $nights_count > 0 ) : ?>
											<div class="mpk-nights-badge">
												🌙 <?php echo esc_html( $nights_count ); ?> <?php echo ( 1 === $nights_count ) ? 'night' : 'nights'; ?>
											</div>
										<?php endif; ?>
									</td>

									<!-- Guests & Rooms -->
									<td>
										<div><strong><?php echo esc_html( $b->adults ); ?></strong> Adults</div>
										<?php if ( $b->children > 0 ) : ?>
											<div class="mpk-cell-sub"><?php echo esc_html( $b->children ); ?> Children</div>
										<?php endif; ?>
										<?php if ( $b->infants > 0 ) : ?>
											<div class="mpk-cell-sub"><?php echo esc_html( $b->infants ); ?> Infants</div>
										<?php endif; ?>
										<div class="mpk-cell-sub" style="margin-top: 4px; color: #475569;">
											<strong><?php echo esc_html( $b->rooms_count ); ?></strong> <?php echo ( 1 === (int) $b->rooms_count ) ? 'Room' : 'Rooms'; ?>
										</div>
									</td>

									<!-- Amount & Payment -->
									<td>
										<div class="mpk-amount-val">$<?php echo esc_html( number_format( (float) $b->grand_total, 2 ) ); ?></div>
										<span class="mpk-payment-pill mpk-pay-<?php echo esc_attr( strtolower( $b->payment_method ) ); ?>">
											<?php echo esc_html( $payment_label ); ?>
										</span>
									</td>

									<!-- Status -->
									<td id="mpk-status-badge-cell-<?php echo esc_attr( $b->id ); ?>">
										<?php echo self::get_status_badge( $b->status ); ?>
									</td>

									<!-- Actions -->
									<td style="text-align: right;">
										<div class="mpk-action-group">
											<select class="mpk-quick-status-select" data-booking-id="<?php echo esc_attr( $b->id ); ?>">
												<option value="Pending" <?php selected( 'Pending', $b->status ); ?>><?php esc_html_e( 'Pending', 'maldives-packages' ); ?></option>
												<option value="Approved" <?php selected( in_array( $b->status, array( 'Approved', 'Confirmed' ), true ), true ); ?>><?php esc_html_e( 'Approved', 'maldives-packages' ); ?></option>
												<option value="Cancelled" <?php selected( 'Cancelled', $b->status ); ?>><?php esc_html_e( 'Cancelled', 'maldives-packages' ); ?></option>
											</select>

											<button type="button" class="button button-small mpk-btn-open-modal" data-details="<?php echo esc_attr( wp_json_encode( $modal_data ) ); ?>" title="<?php esc_attr_e( 'View Details', 'maldives-packages' ); ?>">
												<span class="dashicons dashicons-visibility"></span>
											</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Booking Details Modal -->
		<div id="mpk-details-modal" class="mpk-modal-backdrop" style="display: none;">
			<div class="mpk-modal-dialog">
				<div class="mpk-modal-header">
					<div class="mpk-modal-header-left">
						<span class="dashicons dashicons-portfolio mpk-modal-icon"></span>
						<div>
							<h3 class="mpk-modal-title" id="mpk-modal-ref">MPK-PENDING</h3>
							<p class="mpk-modal-date" id="mpk-modal-created">Created on ...</p>
						</div>
					</div>
					<button type="button" class="mpk-modal-close" id="mpk-modal-close-btn">&times;</button>
				</div>

				<div class="mpk-modal-body">
					<!-- Guest Card -->
					<div class="mpk-modal-section">
						<h4 class="mpk-modal-section-title"><span class="dashicons dashicons-admin-users"></span> Primary Guest & Passport</h4>
						<div class="mpk-modal-grid-2">
							<div class="mpk-modal-info-item">
								<span class="mpk-modal-label">Guest Name</span>
								<span class="mpk-modal-val" id="mpk-modal-guest-name">—</span>
							</div>
							<div class="mpk-modal-info-item">
								<span class="mpk-modal-label">Email Address</span>
								<span class="mpk-modal-val" id="mpk-modal-guest-email">—</span>
							</div>
							<div class="mpk-modal-info-item">
								<span class="mpk-modal-label">Phone Number</span>
								<span class="mpk-modal-val" id="mpk-modal-guest-phone">—</span>
							</div>
							<div class="mpk-modal-info-item">
								<span class="mpk-modal-label">Country / Nationality</span>
								<span class="mpk-modal-val" id="mpk-modal-guest-country">—</span>
							</div>
							<div class="mpk-modal-info-item">
								<span class="mpk-modal-label">Passport Number / ID</span>
								<span class="mpk-modal-val" id="mpk-modal-passport" style="font-family: monospace; font-size: 14px; font-weight: 700;">—</span>
							</div>
							<div class="mpk-modal-info-item">
								<span class="mpk-modal-label">Current Status</span>
								<div id="mpk-modal-status-badge">—</div>
							</div>
							<div class="mpk-modal-info-item" id="mpk-modal-passport-wrap" style="grid-column: 1 / -1; display: none; margin-top: 6px; padding-top: 10px; border-top: 1px dashed #e2e8f0;">
								<span class="mpk-modal-label">Passport Attachment Document</span>
								<div style="margin-top: 6px;">
									<a href="#" id="mpk-modal-passport-link" target="_blank" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: #0284c7;">
										<span class="dashicons dashicons-media-document"></span> <?php esc_html_e( 'View / Download Passport Copy', 'maldives-packages' ); ?> &rarr;
									</a>
								</div>
							</div>
						</div>
					</div>

					<!-- Resort & Itinerary Card -->
					<div class="mpk-modal-section">
						<h4 class="mpk-modal-section-title"><span class="dashicons dashicons-location-alt"></span> Itinerary & Accommodation</h4>
						<div class="mpk-modal-grid-2">
							<div class="mpk-modal-info-item">
								<span class="mpk-modal-label">Resort / Hotel</span>
								<span class="mpk-modal-val" id="mpk-modal-hotel">—</span>
							</div>
							<div class="mpk-modal-info-item">
								<span class="mpk-modal-label">Room Type & Inclusions</span>
								<span class="mpk-modal-val" id="mpk-modal-room">—</span>
							</div>
							<div class="mpk-modal-info-item">
								<span class="mpk-modal-label">Destination Island</span>
								<span class="mpk-modal-val" id="mpk-modal-location">—</span>
							</div>
							<div class="mpk-modal-info-item">
								<span class="mpk-modal-label">Travel Duration</span>
								<span class="mpk-modal-val" id="mpk-modal-dates">—</span>
							</div>
							<div class="mpk-modal-info-item">
								<span class="mpk-modal-label">Guest Configuration</span>
								<span class="mpk-modal-val" id="mpk-modal-guests">—</span>
							</div>
							<div class="mpk-modal-info-item">
								<span class="mpk-modal-label">Grand Total & Payment Method</span>
								<span class="mpk-modal-val" id="mpk-modal-pricing" style="color: #0284c7; font-weight: 700;">—</span>
							</div>
						</div>
					</div>

					<!-- Special Requests Box -->
					<div class="mpk-modal-section">
						<h4 class="mpk-modal-section-title"><span class="dashicons dashicons-editor-quote"></span> Special Requests & Concierge Notes</h4>
						<div class="mpk-modal-notes" id="mpk-modal-notes">
							No special requests noted by customer.
						</div>
					</div>
				</div>

				<div class="mpk-modal-footer">
					<div class="mpk-modal-footer-status">
						<span style="font-weight: 600; font-size: 13px; margin-right: 8px;">Quick Update Status:</span>
						<button type="button" class="button button-secondary mpk-modal-action-btn" data-set-status="Pending">Mark Pending</button>
						<button type="button" class="button button-primary mpk-modal-action-btn" data-set-status="Approved" style="background:#16a34a; border-color:#15803d;">Mark Approved</button>
						<button type="button" class="button button-link-delete mpk-modal-action-btn" data-set-status="Cancelled" style="margin-left: 8px;">Mark Cancelled</button>
					</div>
					<button type="button" class="button button-large" id="mpk-modal-dismiss-btn">Close</button>
				</div>
			</div>
		</div>

		<!-- Scoped Admin Stylesheet & Javascript -->
		<style>
			.mpk-admin-wrap {
				max-width: 1300px;
				margin: 20px 20px 40px 0;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}
			.mpk-admin-header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				margin-bottom: 24px;
				background: #ffffff;
				padding: 20px 24px;
				border-radius: 12px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.05);
				border: 1px solid #e2e8f0;
			}
			.mpk-header-left {
				display: flex;
				align-items: center;
				gap: 16px;
			}
			.mpk-header-icon {
				font-size: 38px;
				width: 38px;
				height: 38px;
				color: #0284c7;
			}
			.mpk-admin-header h1 {
				margin: 0;
				font-size: 22px;
				font-weight: 700;
				color: #0f172a;
				line-height: 1.2;
			}
			.mpk-header-subtitle {
				margin: 4px 0 0;
				font-size: 13px;
				color: #64748b;
			}
			.mpk-live-badge {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				background: #f0fdf4;
				color: #16a34a;
				font-size: 12px;
				font-weight: 600;
				padding: 5px 12px;
				border-radius: 9999px;
				border: 1px solid #bbf7d0;
			}
			.mpk-pulse {
				width: 8px;
				height: 8px;
				border-radius: 50%;
				background: #22c55e;
				box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
				animation: mpkPulse 2s infinite;
			}
			@keyframes mpkPulse {
				0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.6); }
				70% { box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
				100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
			}
			.mpk-stats-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 16px;
				margin-bottom: 24px;
			}
			.mpk-stat-card {
				background: #ffffff;
				border: 1px solid #e2e8f0;
				border-radius: 12px;
				padding: 16px 20px;
				display: flex;
				align-items: center;
				justify-content: space-between;
				box-shadow: 0 1px 2px rgba(0,0,0,0.03);
			}
			.mpk-stat-label {
				display: block;
				font-size: 12px;
				text-transform: uppercase;
				color: #64748b;
				font-weight: 600;
				letter-spacing: 0.02em;
			}
			.mpk-stat-value {
				display: block;
				font-size: 24px;
				font-weight: 700;
				color: #0f172a;
				margin-top: 4px;
				font-variant-numeric: tabular-nums;
			}
			.mpk-stat-icon-wrap {
				width: 44px;
				height: 44px;
				border-radius: 10px;
				display: flex;
				align-items: center;
				justify-content: center;
			}
			.mpk-stat-icon-wrap .dashicons {
				font-size: 24px;
				width: 24px;
				height: 24px;
			}
			.mpk-stat-total .mpk-stat-icon-wrap { background: #e0f2fe; color: #0369a1; }
			.mpk-stat-pending .mpk-stat-icon-wrap { background: #fef3c7; color: #b45309; }
			.mpk-stat-approved .mpk-stat-icon-wrap { background: #dcfce7; color: #15803d; }
			.mpk-stat-cancelled .mpk-stat-icon-wrap { background: #fee2e2; color: #b91c1c; }
			.mpk-stat-revenue .mpk-stat-icon-wrap { background: #f0fdf4; color: #047857; }

			.mpk-filter-bar {
				display: flex;
				align-items: center;
				justify-content: space-between;
				flex-wrap: wrap;
				gap: 12px;
				margin-bottom: 14px;
			}
			.mpk-status-tabs {
				margin: 0;
				padding: 0;
			}
			.mpk-status-tabs a.current {
				font-weight: 700;
				color: #0f172a;
			}
			.mpk-search-box {
				display: flex;
				gap: 6px;
			}
			.mpk-search-box input[type="search"] {
				min-width: 260px;
				border-radius: 6px;
				border: 1px solid #cbd5e1;
				padding: 4px 10px;
			}

			.mpk-table-card {
				background: #ffffff;
				border: 1px solid #e2e8f0;
				border-radius: 12px;
				overflow: hidden;
				box-shadow: 0 1px 3px rgba(0,0,0,0.04);
			}
			.mpk-bookings-table {
				border: none;
				border-collapse: collapse;
			}
			.mpk-bookings-table thead th {
				background: #f8fafc;
				border-bottom: 1px solid #e2e8f0;
				color: #475569;
				font-weight: 600;
				padding: 12px 14px;
				font-size: 13px;
			}
			.mpk-bookings-table tbody td {
				padding: 14px;
				vertical-align: middle;
				border-bottom: 1px solid #f1f5f9;
			}
			.mpk-bookings-table tbody tr:hover {
				background: #f8fafc;
			}
			.mpk-ref-code {
				font-family: monospace;
				font-size: 13px;
				color: #0369a1;
				display: block;
			}
			.mpk-cell-sub {
				font-size: 12px;
				color: #64748b;
				display: flex;
				align-items: center;
				gap: 4px;
				margin-top: 2px;
			}
			.mpk-cell-sub .dashicons {
				font-size: 14px;
				width: 14px;
				height: 14px;
				color: #94a3b8;
			}
			.mpk-guest-name {
				font-weight: 600;
				font-size: 14px;
				color: #0f172a;
			}
			.mpk-resort-title {
				font-weight: 600;
				font-size: 13px;
				color: #1e293b;
			}
			.mpk-loc-tag {
				display: inline-block;
				background: #e2e8f0;
				color: #334155;
				font-size: 11px;
				padding: 1px 8px;
				border-radius: 4px;
				margin-top: 4px;
			}
			.mpk-nights-badge {
				display: inline-block;
				font-size: 11px;
				background: #f1f5f9;
				color: #475569;
				padding: 2px 6px;
				border-radius: 4px;
				margin-top: 4px;
			}
			.mpk-amount-val {
				font-weight: 700;
				font-size: 15px;
				color: #0f172a;
				font-variant-numeric: tabular-nums;
			}
			.mpk-payment-pill {
				display: inline-block;
				font-size: 11px;
				padding: 2px 8px;
				border-radius: 9999px;
				margin-top: 4px;
				font-weight: 500;
				background: #f1f5f9;
				color: #475569;
			}
			.mpk-payment-pill.mpk-pay-bank {
				background: #e0f2fe;
				color: #0369a1;
			}
			.mpk-payment-pill.mpk-pay-office {
				background: #fef3c7;
				color: #92400e;
			}

			/* Status Badges */
			.mpk-badge {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 4px 10px;
				border-radius: 9999px;
				font-size: 12px;
				font-weight: 600;
				line-height: 1;
			}
			.mpk-badge-dot {
				width: 6px;
				height: 6px;
				border-radius: 50%;
			}
			.mpk-badge-pending {
				background: #fef3c7;
				color: #92400e;
				border: 1px solid #fde68a;
			}
			.mpk-badge-approved {
				background: #dcfce7;
				color: #166534;
				border: 1px solid #bbf7d0;
			}
			.mpk-badge-cancelled {
				background: #fee2e2;
				color: #991b1b;
				border: 1px solid #fecaca;
			}

			.mpk-action-group {
				display: flex;
				align-items: center;
				justify-content: flex-end;
				gap: 6px;
			}
			.mpk-quick-status-select {
				font-size: 12px;
				height: 28px;
				padding: 0 24px 0 8px;
				border-radius: 6px;
				border: 1px solid #cbd5e1;
				background-color: #ffffff;
				cursor: pointer;
			}
			.mpk-btn-open-modal {
				padding: 0 6px !important;
				height: 28px !important;
				line-height: 26px !important;
				display: inline-flex;
				align-items: center;
				justify-content: center;
			}

			/* Modal Styles */
			.mpk-modal-backdrop {
				position: fixed;
				top: 0;
				left: 0;
				width: 100vw;
				height: 100vh;
				background: rgba(15, 23, 42, 0.6);
				backdrop-filter: blur(2px);
				z-index: 99999;
				display: flex;
				align-items: center;
				justify-content: center;
				padding: 20px;
				box-sizing: border-box;
			}
			.mpk-modal-dialog {
				background: #ffffff;
				border-radius: 16px;
				width: 100%;
				max-width: 680px;
				max-height: 90vh;
				overflow-y: auto;
				box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
				border: 1px solid #e2e8f0;
			}
			.mpk-modal-header {
				padding: 20px 24px;
				border-bottom: 1px solid #e2e8f0;
				display: flex;
				align-items: center;
				justify-content: space-between;
				background: #f8fafc;
				border-radius: 16px 16px 0 0;
			}
			.mpk-modal-header-left {
				display: flex;
				align-items: center;
				gap: 12px;
			}
			.mpk-modal-icon {
				font-size: 28px;
				width: 28px;
				height: 28px;
				color: #0284c7;
			}
			.mpk-modal-title {
				margin: 0;
				font-size: 18px;
				font-weight: 700;
				color: #0f172a;
				font-family: monospace;
			}
			.mpk-modal-date {
				margin: 2px 0 0;
				font-size: 12px;
				color: #64748b;
			}
			.mpk-modal-close {
				background: transparent;
				border: none;
				font-size: 24px;
				line-height: 1;
				color: #94a3b8;
				cursor: pointer;
				padding: 4px 8px;
				border-radius: 6px;
			}
			.mpk-modal-close:hover {
				color: #0f172a;
				background: #e2e8f0;
			}
			.mpk-modal-body {
				padding: 24px;
			}
			.mpk-modal-section {
				margin-bottom: 20px;
				background: #f8fafc;
				border: 1px solid #e2e8f0;
				border-radius: 12px;
				padding: 16px 18px;
			}
			.mpk-modal-section-title {
				margin: 0 0 12px;
				font-size: 13px;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 0.03em;
				color: #334155;
				display: flex;
				align-items: center;
				gap: 6px;
			}
			.mpk-modal-section-title .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
				color: #0284c7;
			}
			.mpk-modal-grid-2 {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 12px 16px;
			}
			.mpk-modal-info-item {
				display: flex;
				flex-direction: column;
				gap: 2px;
			}
			.mpk-modal-label {
				font-size: 11px;
				text-transform: uppercase;
				color: #64748b;
				font-weight: 600;
			}
			.mpk-modal-val {
				font-size: 13px;
				font-weight: 500;
				color: #0f172a;
				word-break: break-word;
			}
			.mpk-modal-notes {
				background: #ffffff;
				border: 1px solid #e2e8f0;
				border-radius: 8px;
				padding: 12px;
				font-size: 13px;
				color: #334155;
				line-height: 1.5;
				font-style: italic;
			}
			.mpk-modal-footer {
				padding: 16px 24px;
				border-top: 1px solid #e2e8f0;
				display: flex;
				align-items: center;
				justify-content: space-between;
				background: #f8fafc;
				border-radius: 0 0 16px 16px;
			}
			.mpk-toast {
				position: fixed;
				bottom: 24px;
				right: 24px;
				background: #0f172a;
				color: #ffffff;
				padding: 12px 20px;
				border-radius: 8px;
				font-size: 13px;
				font-weight: 500;
				box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
				z-index: 100000;
				transition: opacity 0.2s ease;
			}
		</style>

		<script>
			(function() {
				var adminNonce = <?php echo wp_json_encode( $admin_nonce ); ?>;
				var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
				var currentModalBookingId = null;

				function showToast(msg) {
					var toast = document.getElementById('mpk-admin-toast');
					if (!toast) return;
					toast.textContent = msg;
					toast.style.display = 'block';
					toast.style.opacity = '1';
					setTimeout(function() {
						toast.style.opacity = '0';
						setTimeout(function() { toast.style.display = 'none'; }, 250);
					}, 3000);
				}

				// Handle Quick Status Dropdown Changes
				var statusSelects = document.querySelectorAll('.mpk-quick-status-select');
				for (var i = 0; i < statusSelects.length; i++) {
					statusSelects[i].addEventListener('change', function() {
						var bookingId = this.getAttribute('data-booking-id');
						var newStatus = this.value;
						updateStatus(bookingId, newStatus, this);
					});
				}

				function updateStatus(bookingId, newStatus, selectEl) {
					if (selectEl) selectEl.disabled = true;

					var formData = new FormData();
					formData.append('action', 'mpk_update_booking_status');
					formData.append('nonce', adminNonce);
					formData.append('booking_id', bookingId);
					formData.append('status', newStatus);

					fetch(ajaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(function(res) { return res.json(); })
					.then(function(data) {
						if (selectEl) selectEl.disabled = false;
						if (data && data.success) {
							// Update badge cell in table
							var badgeCell = document.getElementById('mpk-status-badge-cell-' + bookingId);
							if (badgeCell && data.data && data.data.badge_html) {
								badgeCell.innerHTML = data.data.badge_html;
							}
							// Update modal badge if open
							var modalBadge = document.getElementById('mpk-modal-status-badge');
							if (modalBadge && currentModalBookingId == bookingId && data.data.badge_html) {
								modalBadge.innerHTML = data.data.badge_html;
							}
							// Update stat counters
							if (data.data && data.data.counts) {
								var cTotal = document.getElementById('mpk-stat-count-total');
								var cPending = document.getElementById('mpk-stat-count-pending');
								var cApproved = document.getElementById('mpk-stat-count-approved');
								var cCancelled = document.getElementById('mpk-stat-count-cancelled');
								if (cTotal) cTotal.textContent = data.data.counts.total;
								if (cPending) cPending.textContent = data.data.counts.pending;
								if (cApproved) cApproved.textContent = data.data.counts.approved;
								if (cCancelled) cCancelled.textContent = data.data.counts.cancelled;
							}
							showToast(data.data.message || 'Status updated successfully!');
						} else {
							alert((data && data.data && data.data.message) ? data.data.message : 'Error updating status');
						}
					})
					.catch(function(err) {
						if (selectEl) selectEl.disabled = false;
						alert('Network error while updating status.');
					});
				}

				// Modal Handler
				var modal = document.getElementById('mpk-details-modal');
				var closeBtn = document.getElementById('mpk-modal-close-btn');
				var dismissBtn = document.getElementById('mpk-modal-dismiss-btn');
				var viewBtns = document.querySelectorAll('.mpk-btn-open-modal');

				function openModal(data) {
					if (!data) return;
					currentModalBookingId = data.id;

					document.getElementById('mpk-modal-ref').textContent = data.reference_id || 'MPK-RECORD';
					document.getElementById('mpk-modal-created').textContent = 'Booked on: ' + (data.created_at || '—');

					document.getElementById('mpk-modal-guest-name').textContent = data.lead_name || '—';
					document.getElementById('mpk-modal-guest-email').textContent = data.lead_email || '—';
					document.getElementById('mpk-modal-guest-phone').textContent = data.lead_phone || '—';
					document.getElementById('mpk-modal-guest-country').textContent = data.lead_country || '—';
					document.getElementById('mpk-modal-passport').textContent = data.passport_no || 'None Provided';

					var passportWrap = document.getElementById('mpk-modal-passport-wrap');
					var passportLink = document.getElementById('mpk-modal-passport-link');
					if (passportWrap && passportLink) {
						if (data.passport_file_url) {
							passportLink.href = data.passport_file_url;
							passportWrap.style.display = 'block';
						} else {
							passportLink.href = '#';
							passportWrap.style.display = 'none';
						}
					}

					var statusBadgeCell = document.getElementById('mpk-status-badge-cell-' + data.id);
					document.getElementById('mpk-modal-status-badge').innerHTML = statusBadgeCell ? statusBadgeCell.innerHTML : data.status;

					document.getElementById('mpk-modal-hotel').textContent = data.hotel_name || '—';
					document.getElementById('mpk-modal-room').textContent = data.room_name || '—';
					document.getElementById('mpk-modal-location').textContent = data.selected_location || '—';
					document.getElementById('mpk-modal-dates').textContent = data.check_in + ' → ' + data.check_out + ' (' + data.nights + ' nights)';
					document.getElementById('mpk-modal-guests').textContent = data.adults + ' Adults, ' + data.children + ' Children, ' + data.infants + ' Infants (' + data.rooms_count + ' Rooms)';
					document.getElementById('mpk-modal-pricing').textContent = '$' + data.grand_total + ' (' + data.payment_method + ')';

					document.getElementById('mpk-modal-notes').textContent = data.special_requests || 'No special requests submitted by customer.';

					modal.style.display = 'flex';
				}

				function closeModal() {
					modal.style.display = 'none';
					currentModalBookingId = null;
				}

				for (var b = 0; b < viewBtns.length; b++) {
					viewBtns[b].addEventListener('click', function() {
						var raw = this.getAttribute('data-details');
						try {
							var parsed = JSON.parse(raw);
							openModal(parsed);
						} catch(e) {}
					});
				}

				if (closeBtn) closeBtn.addEventListener('click', closeModal);
				if (dismissBtn) dismissBtn.addEventListener('click', closeModal);
				if (modal) {
					modal.addEventListener('click', function(e) {
						if (e.target === modal) closeModal();
					});
				}

				// Modal Quick Action Buttons
				var modalActionBtns = document.querySelectorAll('.mpk-modal-action-btn');
				for (var m = 0; m < modalActionBtns.length; m++) {
					modalActionBtns[m].addEventListener('click', function() {
						if (!currentModalBookingId) return;
						var statusToSet = this.getAttribute('data-set-status');
						var rowSelect = document.querySelector('.mpk-quick-status-select[data-booking-id="' + currentModalBookingId + '"]');
						if (rowSelect) rowSelect.value = statusToSet;
						updateStatus(currentModalBookingId, statusToSet, rowSelect);
					});
				}
			})();
		</script>
		<?php
	}
}
