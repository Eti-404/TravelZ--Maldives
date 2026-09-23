<?php
/**
 * Dedicated 4-Tab Settings Panel for Maldives Packages Booking
 *
 * Provides a clean configuration panel for:
 * 1. General / Hero
 * 2. Pricing Rules
 * 3. Inclusions & Policies
 * 4. Payment & Concierge
 *
 * Approved Prefix: MPK / mpk_
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MPK_Settings {

	/**
	 * Singleton instance.
	 *
	 * @var MPK_Settings|null
	 */
	private static $instance = null;

	/**
	 * Guard against duplicate hook initialization.
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Guard against duplicate page rendering on the same request.
	 *
	 * @var bool
	 */
	private static $rendered = false;

	/**
	 * Get singleton instance.
	 *
	 * @return MPK_Settings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Static render helper for unified menu callback.
	 */
	public static function render_page() {
		$instance = self::get_instance();
		$instance->render_settings_page();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		self::$instance = $this;

		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		// Note: Submenu registration is now centralized under Maldives Packages in MPK_Admin
		add_action( 'admin_init', array( $this, 'handle_save_settings' ) );
	}

	/**
	 * Register the Settings submenu (kept for backward compatibility).
	 */
	public function register_settings_submenu() {
		// Centralized in MPK_Admin::register_unified_admin_menu()
	}

	/**
	 * Reset render guard (used in automated testing).
	 */
	public static function reset_render_guard() {
		self::$rendered = false;
	}

	/**
	 * Get default settings values.
	 *
	 * @return array Default settings map.
	 */
	public static function get_defaults() {
		return array(
			// Tab 1: General & Hero
			'hero_badge'          => '✨ Premium Island Escapes',
			'hero_title'          => 'Maldives Package',
			'hero_subtitle'       => "Crystal lagoons, overwater villas and curated luxury \u{2014} design a Maldives getaway that's unmistakably yours.",
			'currency_symbol'     => '$',
			'passport_notice'     => 'Valid passport (6+ months) required. Visa-free for most nationalities. Pack light, breathable clothing.',

			// Tab 2: Pricing Rules
			'tax_rate'            => 0.08,
			'extras'              => 45.00,
			'service_fee'         => 25.00,
			'child_discount_pct'  => 0,
			'infant_discount_pct' => 100,

			// Tab 3: Inclusions & Policies
			'package_inclusions'  => "Return airport / speedboat transfers\nDaily housekeeping\nWelcome drink on arrival\n24/7 concierge support",
			'package_excludes'    => "International flights\nTravel insurance\nPersonal expenses\nTips & gratuities\nOptional excursions",
			'cancellation_policy' => 'Free cancellation up to 30 days before travel. 50% refund up to 14 days. No refund within 7 days of arrival.',
			'emergency_terms'     => 'Bookings are subject to availability and confirmation. Prices are per package and may vary by season.',

			// Tab 4: Payment & Concierge (RFC 2606 Generic Placeholders)
			'bank_name'           => 'Standard Chartered Bank',
			'bank_account_name'   => 'Maldives Packages Concierge Ltd',
			'bank_account_no'     => '000-000-0000-00',
			'bank_swift'          => 'SCBLBDDX',
			'office_address'      => 'Hulhumale Oceanfront Drive, Male Atoll, Maldives',
			'support_email'       => 'concierge@example.com',
			'support_phone'       => '+00 123 456789',
		);
	}

	/**
	 * Handle settings form submission.
	 */
	public function handle_save_settings() {
		if ( ! isset( $_POST['mpk_save_settings'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'mpk_save_settings_action', 'mpk_settings_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'maldives-packages' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user.', 'maldives-packages' ) );
		}

		$current = get_option( 'mpk_settings', array() );
		$defaults = self::get_defaults();
		$settings = wp_parse_args( $current, $defaults );

		$active_tab = isset( $_POST['mpk_active_tab'] ) ? sanitize_key( $_POST['mpk_active_tab'] ) : 'general';

		if ( 'general' === $active_tab ) {
			$settings['hero_badge']      = isset( $_POST['hero_badge'] ) ? sanitize_text_field( wp_unslash( $_POST['hero_badge'] ) ) : $defaults['hero_badge'];
			$settings['hero_title']      = isset( $_POST['hero_title'] ) ? sanitize_text_field( wp_unslash( $_POST['hero_title'] ) ) : $defaults['hero_title'];
			$settings['hero_subtitle']   = isset( $_POST['hero_subtitle'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hero_subtitle'] ) ) : $defaults['hero_subtitle'];
			$settings['currency_symbol'] = isset( $_POST['currency_symbol'] ) ? sanitize_text_field( wp_unslash( $_POST['currency_symbol'] ) ) : '$';
			$settings['passport_notice'] = isset( $_POST['passport_notice'] ) ? sanitize_textarea_field( wp_unslash( $_POST['passport_notice'] ) ) : $defaults['passport_notice'];
		} elseif ( 'pricing' === $active_tab ) {
			$tax_pct = isset( $_POST['tax_rate_pct'] ) ? floatval( $_POST['tax_rate_pct'] ) : 8.0;
			$settings['tax_rate']            = $tax_pct / 100.0;
			$settings['extras']              = isset( $_POST['extras'] ) ? floatval( $_POST['extras'] ) : 45.00;
			$settings['service_fee']         = isset( $_POST['service_fee'] ) ? floatval( $_POST['service_fee'] ) : 25.00;
			$settings['child_discount_pct']  = isset( $_POST['child_discount_pct'] ) ? absint( $_POST['child_discount_pct'] ) : 0;
			$settings['infant_discount_pct'] = isset( $_POST['infant_discount_pct'] ) ? absint( $_POST['infant_discount_pct'] ) : 100;
		} elseif ( 'policies' === $active_tab ) {
			$settings['package_inclusions']  = isset( $_POST['package_inclusions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['package_inclusions'] ) ) : $defaults['package_inclusions'];
			$settings['package_excludes']    = isset( $_POST['package_excludes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['package_excludes'] ) ) : $defaults['package_excludes'];
			$settings['cancellation_policy'] = isset( $_POST['cancellation_policy'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cancellation_policy'] ) ) : $defaults['cancellation_policy'];
			$settings['emergency_terms']     = isset( $_POST['emergency_terms'] ) ? sanitize_textarea_field( wp_unslash( $_POST['emergency_terms'] ) ) : $defaults['emergency_terms'];

			// Also sync structured array for backward compatibility with policies & inclusions
			$inc_lines = array_filter( array_map( 'trim', explode( "\n", $settings['package_inclusions'] ) ) );
			$exc_lines = array_filter( array_map( 'trim', explode( "\n", $settings['package_excludes'] ) ) );
			$settings['hotel_includes_base'] = array_values( $inc_lines );
			$settings['base_excludes']       = array_values( $exc_lines );

			if ( ! isset( $settings['policies'] ) || ! is_array( $settings['policies'] ) ) {
				$settings['policies'] = array();
			}
			$settings['policies']['cancellation'] = $settings['cancellation_policy'];
			$settings['policies']['terms']        = $settings['emergency_terms'];
			$settings['policies']['notes']        = isset( $settings['passport_notice'] ) ? $settings['passport_notice'] : $defaults['passport_notice'];
		} elseif ( 'payment' === $active_tab ) {
			$settings['bank_name']         = isset( $_POST['bank_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_name'] ) ) : $defaults['bank_name'];
			$settings['bank_account_name'] = isset( $_POST['bank_account_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_account_name'] ) ) : $defaults['bank_account_name'];
			$settings['bank_account_no']   = isset( $_POST['bank_account_no'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_account_no'] ) ) : $defaults['bank_account_no'];
			$settings['bank_swift']        = isset( $_POST['bank_swift'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_swift'] ) ) : $defaults['bank_swift'];
			$settings['office_address']    = isset( $_POST['office_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['office_address'] ) ) : $defaults['office_address'];
			$settings['support_email']     = isset( $_POST['support_email'] ) ? sanitize_email( wp_unslash( $_POST['support_email'] ) ) : $defaults['support_email'];
			$settings['support_phone']     = isset( $_POST['support_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['support_phone'] ) ) : $defaults['support_phone'];
		}

		update_option( 'mpk_settings', $settings );

		// Redirect with success notice
		$redirect_url = add_query_arg(
			array(
				'page'    => 'mpk-settings',
				'tab'     => $active_tab,
				'updated' => 'true',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render the 4-tab Settings Panel.
	 */
	public function render_settings_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		$valid_tabs = array( 'general', 'pricing', 'policies', 'payment', 'guide' );
		if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
			$active_tab = 'general';
		}

		$current = get_option( 'mpk_settings', array() );
		$defaults = self::get_defaults();
		$s = wp_parse_args( $current, $defaults );

		// Format tax rate as percentage for display
		$tax_pct = isset( $s['tax_rate'] ) ? ( (float) $s['tax_rate'] * 100 ) : 8.0;

		$is_updated = isset( $_GET['updated'] ) && 'true' === $_GET['updated'];
		?>
		<div class="wrap mpk-admin-wrap" style="max-width: 1000px;">
			<h1 style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
				<span class="dashicons dashicons-admin-settings" style="font-size: 30px; width: 30px; height: 30px; color: #0284c7;"></span>
				<?php esc_html_e( 'Maldives Packages - Configuration Settings', 'maldives-packages' ); ?>
			</h1>
			<p style="color: #64748b; font-size: 14px; margin-top: 0; margin-bottom: 20px;">
				<?php esc_html_e( 'Manage global pricing rules, hero titles, inclusion lists, policies, and luxury concierge contact details.', 'maldives-packages' ); ?>
			</p>

			<?php if ( $is_updated ) : ?>
				<div class="notice notice-success is-dismissible" style="border-left-color: #10b981;">
					<p><strong><?php esc_html_e( 'Settings updated successfully! Changes are live across the booking wizard and email notifications.', 'maldives-packages' ); ?></strong></p>
				</div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper" style="margin-bottom: 24px; border-bottom: 1px solid #cbd5e1;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mpk-settings&tab=general' ) ); ?>" class="nav-tab <?php echo ( 'general' === $active_tab ) ? 'nav-tab-active' : ''; ?>" style="<?php echo ( 'general' === $active_tab ) ? 'font-weight:700; color:#0284c7; border-bottom-color:#ffffff;' : ''; ?>">
					<span class="dashicons dashicons-desktop" style="font-size: 17px; vertical-align: -3px;"></span> <?php esc_html_e( '1. General / Hero', 'maldives-packages' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mpk-settings&tab=pricing' ) ); ?>" class="nav-tab <?php echo ( 'pricing' === $active_tab ) ? 'nav-tab-active' : ''; ?>" style="<?php echo ( 'pricing' === $active_tab ) ? 'font-weight:700; color:#0284c7; border-bottom-color:#ffffff;' : ''; ?>">
					<span class="dashicons dashicons-money-alt" style="font-size: 17px; vertical-align: -3px;"></span> <?php esc_html_e( '2. Pricing Rules', 'maldives-packages' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mpk-settings&tab=policies' ) ); ?>" class="nav-tab <?php echo ( 'policies' === $active_tab ) ? 'nav-tab-active' : ''; ?>" style="<?php echo ( 'policies' === $active_tab ) ? 'font-weight:700; color:#0284c7; border-bottom-color:#ffffff;' : ''; ?>">
					<span class="dashicons dashicons-shield-alt" style="font-size: 17px; vertical-align: -3px;"></span> <?php esc_html_e( '3. Inclusions & Policies', 'maldives-packages' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mpk-settings&tab=payment' ) ); ?>" class="nav-tab <?php echo ( 'payment' === $active_tab ) ? 'nav-tab-active' : ''; ?>" style="<?php echo ( 'payment' === $active_tab ) ? 'font-weight:700; color:#0284c7; border-bottom-color:#ffffff;' : ''; ?>">
					<span class="dashicons dashicons-building" style="font-size: 17px; vertical-align: -3px;"></span> <?php esc_html_e( '4. Payment & Concierge', 'maldives-packages' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mpk-settings&tab=guide' ) ); ?>" class="nav-tab <?php echo ( 'guide' === $active_tab ) ? 'nav-tab-active' : ''; ?>" style="<?php echo ( 'guide' === $active_tab ) ? 'font-weight:700; color:#0284c7; border-bottom-color:#ffffff;' : ''; ?>">
					<span class="dashicons dashicons-book-alt" style="font-size: 17px; vertical-align: -3px;"></span> <?php esc_html_e( '5. Setup & Documentation', 'maldives-packages' ); ?>
				</a>
			</nav>

			<div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 24px 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<form method="post" action="">
					<?php wp_nonce_field( 'mpk_save_settings_action', 'mpk_settings_nonce' ); ?>
					<input type="hidden" name="mpk_active_tab" value="<?php echo esc_attr( $active_tab ); ?>" />

					<?php if ( 'general' === $active_tab ) : ?>
						<!-- TAB 1: GENERAL & HERO -->
						<h3 style="margin-top: 0; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
							<?php esc_html_e( 'Hero Header & Traveler Notices', 'maldives-packages' ); ?>
						</h3>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="hero_badge"><?php esc_html_e( 'Hero Badge Text', 'maldives-packages' ); ?></label></th>
								<td>
									<input name="hero_badge" type="text" id="hero_badge" value="<?php echo esc_attr( isset( $s['hero_badge'] ) ? $s['hero_badge'] : $defaults['hero_badge'] ); ?>" class="regular-text" />
									<p class="description"><?php esc_html_e( 'Pill badge text displayed above the main title (e.g. ✨ Premium Island Escapes).', 'maldives-packages' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="hero_title"><?php esc_html_e( 'Hero Title', 'maldives-packages' ); ?></label></th>
								<td>
									<input name="hero_title" type="text" id="hero_title" value="<?php echo esc_attr( $s['hero_title'] ); ?>" class="regular-text widefat" />
									<p class="description"><?php esc_html_e( 'Main title displayed in the header banner above the wizard.', 'maldives-packages' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="hero_subtitle"><?php esc_html_e( 'Hero Subtitle', 'maldives-packages' ); ?></label></th>
								<td>
									<textarea name="hero_subtitle" id="hero_subtitle" rows="3" class="large-text"><?php echo esc_textarea( $s['hero_subtitle'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Tagline and luxury description under the hero header.', 'maldives-packages' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="currency_symbol"><?php esc_html_e( 'Currency Symbol', 'maldives-packages' ); ?></label></th>
								<td>
									<input name="currency_symbol" type="text" id="currency_symbol" value="<?php echo esc_attr( $s['currency_symbol'] ); ?>" style="width: 80px; text-align: center; font-size: 16px; font-weight: bold;" />
									<p class="description"><?php esc_html_e( 'Default currency symbol (default: $).', 'maldives-packages' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="passport_notice"><?php esc_html_e( 'Passport & Visa Notice', 'maldives-packages' ); ?></label></th>
								<td>
									<textarea name="passport_notice" id="passport_notice" rows="2" class="large-text"><?php echo esc_textarea( $s['passport_notice'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Advisory note shown under Important Notes and Passport Upload in Step 3.', 'maldives-packages' ); ?></p>
								</td>
							</tr>
						</table>

					<?php elseif ( 'pricing' === $active_tab ) : ?>
						<!-- TAB 2: PRICING RULES -->
						<h3 style="margin-top: 0; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
							<?php esc_html_e( 'Taxes, Extra Charges & Discounts', 'maldives-packages' ); ?>
						</h3>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="tax_rate_pct"><?php esc_html_e( 'Tourism Tax Rate (%)', 'maldives-packages' ); ?></label></th>
								<td>
									<input name="tax_rate_pct" type="number" step="0.1" min="0" max="100" id="tax_rate_pct" value="<?php echo esc_attr( $tax_pct ); ?>" style="width: 120px;" /> %
									<p class="description"><?php esc_html_e( 'Percentage tax applied to total room bookings (Default: 8%).', 'maldives-packages' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="extras"><?php esc_html_e( 'Extra / Transfer Charge ($ USD)', 'maldives-packages' ); ?></label></th>
								<td>
									$ <input name="extras" type="number" step="0.5" min="0" id="extras" value="<?php echo esc_attr( $s['extras'] ); ?>" style="width: 120px;" />
									<p class="description"><?php esc_html_e( 'Combined port/environmental surcharge (Default: $45.00).', 'maldives-packages' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="service_fee"><?php esc_html_e( 'Concierge Service Fee ($ USD)', 'maldives-packages' ); ?></label></th>
								<td>
									$ <input name="service_fee" type="number" step="0.5" min="0" id="service_fee" value="<?php echo esc_attr( $s['service_fee'] ); ?>" style="width: 120px;" />
									<p class="description"><?php esc_html_e( 'Standard flat booking concierge assistance fee (Default: $25.00).', 'maldives-packages' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="child_discount_pct"><?php esc_html_e( 'Child Discount (%)', 'maldives-packages' ); ?></label></th>
								<td>
									<input name="child_discount_pct" type="number" step="1" min="0" max="100" id="child_discount_pct" value="<?php echo esc_attr( isset( $s['child_discount_pct'] ) ? $s['child_discount_pct'] : 0 ); ?>" style="width: 120px;" /> %
									<p class="description"><?php esc_html_e( 'Percentage discount on base package for child guests.', 'maldives-packages' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="infant_discount_pct"><?php esc_html_e( 'Infant Discount (%)', 'maldives-packages' ); ?></label></th>
								<td>
									<input name="infant_discount_pct" type="number" step="1" min="0" max="100" id="infant_discount_pct" value="<?php echo esc_attr( isset( $s['infant_discount_pct'] ) ? $s['infant_discount_pct'] : 100 ); ?>" style="width: 120px;" /> %
									<p class="description"><?php esc_html_e( 'Percentage discount for infants (Default: 100% - free of charge).', 'maldives-packages' ); ?></p>
								</td>
							</tr>
						</table>

					<?php elseif ( 'policies' === $active_tab ) : ?>
						<!-- TAB 3: INCLUSIONS & POLICIES -->
						<h3 style="margin-top: 0; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
							<?php esc_html_e( 'Package Inclusions, Exclusions & Legal Policies', 'maldives-packages' ); ?>
						</h3>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="package_inclusions"><?php esc_html_e( 'Base Inclusions (1 per line)', 'maldives-packages' ); ?></label></th>
								<td>
									<textarea name="package_inclusions" id="package_inclusions" rows="5" class="large-text"><?php echo esc_textarea( $s['package_inclusions'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Displayed in Step 3 Package Summary and Confirmation vouchers.', 'maldives-packages' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="package_excludes"><?php esc_html_e( 'Base Exclusions (1 per line)', 'maldives-packages' ); ?></label></th>
								<td>
									<textarea name="package_excludes" id="package_excludes" rows="5" class="large-text"><?php echo esc_textarea( $s['package_excludes'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Noted exclusions (e.g. International flights, tips).', 'maldives-packages' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="cancellation_policy"><?php esc_html_e( 'Cancellation Policy', 'maldives-packages' ); ?></label></th>
								<td>
									<textarea name="cancellation_policy" id="cancellation_policy" rows="3" class="large-text"><?php echo esc_textarea( $s['cancellation_policy'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Accordion item in Step 3 Review and confirmation emails.', 'maldives-packages' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="emergency_terms"><?php esc_html_e( 'Terms & Conditions', 'maldives-packages' ); ?></label></th>
								<td>
									<textarea name="emergency_terms" id="emergency_terms" rows="3" class="large-text"><?php echo esc_textarea( $s['emergency_terms'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Booking acceptance terms shown above the agreement checkbox.', 'maldives-packages' ); ?></p>
								</td>
							</tr>
						</table>

					<?php elseif ( 'payment' === $active_tab ) : ?>
						<!-- TAB 4: PAYMENT & CONCIERGE -->
						<h3 style="margin-top: 0; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
							<?php esc_html_e( 'Bank Details & Luxury Concierge Desk', 'maldives-packages' ); ?>
						</h3>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="bank_name"><?php esc_html_e( 'Beneficiary Bank Name', 'maldives-packages' ); ?></label></th>
								<td>
									<input name="bank_name" type="text" id="bank_name" value="<?php echo esc_attr( $s['bank_name'] ); ?>" class="regular-text" />
									<p class="description"><?php esc_html_e( 'Used in wire transfer instructions sent to traveler.', 'maldives-packages' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="bank_account_name"><?php esc_html_e( 'Account Holder Name', 'maldives-packages' ); ?></label></th>
								<td>
									<input name="bank_account_name" type="text" id="bank_account_name" value="<?php echo esc_attr( $s['bank_account_name'] ); ?>" class="regular-text" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="bank_account_no"><?php esc_html_e( 'Account Number / IBAN', 'maldives-packages' ); ?></label></th>
								<td>
									<input name="bank_account_no" type="text" id="bank_account_no" value="<?php echo esc_attr( $s['bank_account_no'] ); ?>" class="regular-text" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="bank_swift"><?php esc_html_e( 'SWIFT / Routing / Branch', 'maldives-packages' ); ?></label></th>
								<td>
									<input name="bank_swift" type="text" id="bank_swift" value="<?php echo esc_attr( $s['bank_swift'] ); ?>" class="regular-text" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="office_address"><?php esc_html_e( 'Physical Office / Concierge Desk', 'maldives-packages' ); ?></label></th>
								<td>
									<textarea name="office_address" id="office_address" rows="2" class="large-text"><?php echo esc_textarea( $s['office_address'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Physical walk-in reception address for direct payment.', 'maldives-packages' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="support_email"><?php esc_html_e( 'Concierge Support Email', 'maldives-packages' ); ?></label></th>
								<td>
									<input name="support_email" type="email" id="support_email" value="<?php echo esc_attr( $s['support_email'] ); ?>" class="regular-text" />
									<p class="description"><?php esc_html_e( 'Official reply-to and concierge contact email.', 'maldives-packages' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="support_phone"><?php esc_html_e( 'Support Hotline / WhatsApp', 'maldives-packages' ); ?></label></th>
								<td>
									<input name="support_phone" type="text" id="support_phone" value="<?php echo esc_attr( $s['support_phone'] ); ?>" class="regular-text" />
									<p class="description"><?php esc_html_e( '24/7 emergency traveler hotline.', 'maldives-packages' ); ?></p>
								</td>
							</tr>
						</table>

					<?php elseif ( 'guide' === $active_tab ) : ?>
						<!-- TAB 5: SETUP & DOCUMENTATION -->
						<div class="mpk-doc-header" style="margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9;">
							<h2 style="margin: 0 0 6px 0; color: #0f172a; font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-welcome-learn-more" style="color: #0284c7; font-size: 24px; width: 24px; height: 24px;"></span>
								<?php esc_html_e( 'Maldives Packages — Master Setup & Documentation Guide', 'maldives-packages' ); ?>
							</h2>
							<p style="margin: 0; color: #64748b; font-size: 14px;">
								<?php esc_html_e( 'Complete reference for embedding the booking wizard, configuring hotel inventory, and technical architecture.', 'maldives-packages' ); ?>
							</p>
						</div>

						<!-- 1. MASTER SHORTCODE CARD -->
						<div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 12px; padding: 24px; color: #ffffff; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);">
							<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
								<div style="display: flex; align-items: center; gap: 8px;">
									<span class="dashicons dashicons-shortcode" style="color: #38bdf8; font-size: 20px;"></span>
									<span style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8;">
										<?php esc_html_e( 'Master Frontend Shortcode', 'maldives-packages' ); ?>
									</span>
								</div>
								<span style="background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px;">
									<?php esc_html_e( 'Elementor & Gutenberg Ready', 'maldives-packages' ); ?>
								</span>
							</div>

							<div style="background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
								<code id="mpk-master-shortcode" style="color: #38bdf8; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 17px; font-weight: 700; background: transparent; padding: 0;">
									[maldives_packages_wizard]
								</code>
								<button type="button" id="mpk-btn-copy-shortcode" class="button" style="background: #0284c7; color: #ffffff; border: none; font-weight: 600; height: 36px; padding: 0 18px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;">
									<span class="dashicons dashicons-admin-page" style="font-size: 16px; width: 16px; height: 16px; margin-top: -2px;"></span>
									<span id="mpk-copy-btn-text"><?php esc_html_e( 'Copy Shortcode', 'maldives-packages' ); ?></span>
								</button>
							</div>

							<p style="color: #94a3b8; font-size: 13px; margin: 12px 0 0 0; line-height: 1.5;">
								<?php esc_html_e( 'Paste this shortcode anywhere on your site: Elementor Shortcode Widget, Gutenberg Block, Standard Pages, or inside theme templates using:', 'maldives-packages' ); ?>
								<code style="color: #cbd5e1; background: rgba(255,255,255,0.08); padding: 2px 6px; border-radius: 4px; font-size: 12px;">&lt;?php echo do_shortcode('[maldives_packages_wizard]'); ?&gt;</code>.
								<br />
								<span style="font-size: 12px; color: #64748b;">
									<em><?php esc_html_e( 'Legacy alias [maldives_packages] is also fully supported for backward compatibility.', 'maldives-packages' ); ?></em>
								</span>
							</p>
						</div>

						<!-- 2. STEP-BY-STEP SETUP GUIDE -->
						<h3 style="color: #0f172a; font-size: 17px; font-weight: 700; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-flag" style="color: #0284c7;"></span>
							<?php esc_html_e( 'Step-by-Step Setup Walkthrough', 'maldives-packages' ); ?>
						</h3>

						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 30px;">
							<!-- Step 1 -->
							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; border-top: 3px solid #0284c7;">
								<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
									<span style="background: #0284c7; color: #ffffff; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700;">1</span>
									<h4 style="margin: 0; font-size: 14px; font-weight: 700; color: #0f172a;"><?php esc_html_e( 'Add Hotels & Rooms', 'maldives-packages' ); ?></h4>
								</div>
								<p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5;">
									Navigate to <strong>Hotels &amp; Stays</strong> &rarr; <em>Add New</em>. Use the dynamic room repeater to configure room names, price per night, meal plan, and explicit <strong>Room Type</strong> (Balcony, Sea View, Island View, With Pool, Water Villa) and <strong>Bed Type</strong> (Single, Double, Twin, Triple, King).
								</p>
							</div>

							<!-- Step 2 -->
							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; border-top: 3px solid #0284c7;">
								<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
									<span style="background: #0284c7; color: #ffffff; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700;">2</span>
									<h4 style="margin: 0; font-size: 14px; font-weight: 700; color: #0f172a;"><?php esc_html_e( 'Assign Destinations', 'maldives-packages' ); ?></h4>
								</div>
								<p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5;">
									Go to <strong>Destinations</strong> taxonomy and ensure your islands (<em>Hulhumale</em>, <em>Maafushi</em>, <em>Resort Island</em>) are assigned to each hotel so travelers can filter by atoll location in Step 1.
								</p>
							</div>

							<!-- Step 3 -->
							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; border-top: 3px solid #0284c7;">
								<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
									<span style="background: #0284c7; color: #ffffff; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700;">3</span>
									<h4 style="margin: 0; font-size: 14px; font-weight: 700; color: #0f172a;"><?php esc_html_e( 'Configure Settings', 'maldives-packages' ); ?></h4>
								</div>
								<p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5;">
									Use the tabs above to set tax rates (GST), service fees, package inclusions/exclusions, wire transfer bank accounts, and concierge hotline contact numbers.
								</p>
							</div>

							<!-- Step 4 -->
							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; border-top: 3px solid #10b981;">
								<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
									<span style="background: #10b981; color: #ffffff; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700;">4</span>
									<h4 style="margin: 0; font-size: 14px; font-weight: 700; color: #0f172a;"><?php esc_html_e( 'Deploy & Go Live', 'maldives-packages' ); ?></h4>
								</div>
								<p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5;">
									Create a page (e.g. <em>/book-maldives/</em>), add the shortcode <code>[maldives_packages_wizard]</code>, publish the page, and test the 5-step booking flow live!
								</p>
							</div>
						</div>

						<!-- 3. DEVELOPER FILE ARCHITECTURE MAP -->
						<h3 style="color: #0f172a; font-size: 17px; font-weight: 700; margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-category" style="color: #0284c7;"></span>
							<?php esc_html_e( 'Developer File Architecture Map', 'maldives-packages' ); ?>
						</h3>

						<div style="overflow-x: auto; margin-bottom: 20px;">
							<table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
								<thead>
									<tr style="background: #f1f5f9; border-bottom: 2px solid #cbd5e1;">
										<th style="padding: 10px 14px; font-weight: 700; color: #334155; width: 34%;"><?php esc_html_e( 'File Path', 'maldives-packages' ); ?></th>
										<th style="padding: 10px 14px; font-weight: 700; color: #334155; width: 22%;"><?php esc_html_e( 'Component Layer', 'maldives-packages' ); ?></th>
										<th style="padding: 10px 14px; font-weight: 700; color: #334155;"><?php esc_html_e( 'Core Responsibilities', 'maldives-packages' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<tr style="border-bottom: 1px solid #e2e8f0;">
										<td style="padding: 12px 14px; font-family: monospace; font-size: 12px; font-weight: 600; color: #0284c7;">frontend/class-mpk-frontend.php</td>
										<td style="padding: 12px 14px;"><span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Frontend HTML / Shortcodes</span></td>
										<td style="padding: 12px 14px; color: #475569; line-height: 1.4;">Registers <code>[maldives_packages_wizard]</code> and <code>[maldives_packages]</code> shortcodes, renders the 5-step wizard markup, and includes the clean SVG icon system.</td>
									</tr>
									<tr style="border-bottom: 1px solid #e2e8f0; background: #fafafa;">
										<td style="padding: 12px 14px; font-family: monospace; font-size: 12px; font-weight: 600; color: #0284c7;">assets/css/mpk-frontend.css</td>
										<td style="padding: 12px 14px;"><span style="background: #f3e8ff; color: #6b21a8; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Presentation Layer</span></td>
										<td style="padding: 12px 14px; color: #475569; line-height: 1.4;">Contains all responsive CSS styles, hero layouts, typography, stepper navigation cards, payment badges, and media queries matching 100% reference fidelity.</td>
									</tr>
									<tr style="border-bottom: 1px solid #e2e8f0;">
										<td style="padding: 12px 14px; font-family: monospace; font-size: 12px; font-weight: 600; color: #0284c7;">assets/js/mpk-main.js</td>
										<td style="padding: 12px 14px;"><span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Client App Engine</span></td>
										<td style="padding: 12px 14px; color: #475569; line-height: 1.4;">Manages state across all 5 steps, interactive room selection, check-in/out date calculations, stay night computation, and the multi-filter matching engine (<code>isRoomMatchingFilters()</code>).</td>
									</tr>
									<tr style="border-bottom: 1px solid #e2e8f0; background: #fafafa;">
										<td style="padding: 12px 14px; font-family: monospace; font-size: 12px; font-weight: 600; color: #0284c7;">includes/class-mpk-ajax-handler.php</td>
										<td style="padding: 12px 14px;"><span style="background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Backend AJAX &amp; Security</span></td>
										<td style="padding: 12px 14px; color: #475569; line-height: 1.4;">Handles AJAX booking submissions, nonce verification, customer email notifications, and binary passport file upload &amp; validation.</td>
									</tr>
									<tr style="border-bottom: 1px solid #e2e8f0;">
										<td style="padding: 12px 14px; font-family: monospace; font-size: 12px; font-weight: 600; color: #0284c7;">admin/class-mpk-admin.php</td>
										<td style="padding: 12px 14px;"><span style="background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Admin Central Management</span></td>
										<td style="padding: 12px 14px; color: #475569; line-height: 1.4;">Registers the single unified <em>Maldives Packages</em> top-level menu, 4 submenus, KPI metric counters, and the modern interactive Customer Bookings SPA table.</td>
									</tr>
									<tr style="border-bottom: 1px solid #e2e8f0; background: #fafafa;">
										<td style="padding: 12px 14px; font-family: monospace; font-size: 12px; font-weight: 600; color: #0284c7;">admin/class-mpk-hotel-meta-box.php</td>
										<td style="padding: 12px 14px;"><span style="background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Hotel Inventory Repeater</span></td>
										<td style="padding: 12px 14px; color: #475569; line-height: 1.4;">Provides the dynamic hotel room repeater UI with explicit dropdown selectors for Room Types (Balcony, Sea View, etc.) and Bed Configurations (Single, Double, Twin, Triple, King).</td>
									</tr>
									<tr style="border-bottom: 1px solid #e2e8f0;">
										<td style="padding: 12px 14px; font-family: monospace; font-size: 12px; font-weight: 600; color: #0284c7;">includes/class-mpk-data-manager.php</td>
										<td style="padding: 12px 14px;"><span style="background: #f1f5f9; color: #334155; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Database &amp; Normalization</span></td>
										<td style="padding: 12px 14px; color: #475569; line-height: 1.4;">Queries published hotels, handles safe unserialization/sanitization of room inventory meta, and provides the localized <code>MPK_INITIAL_DATA</code> payload.</td>
									</tr>
									<tr>
										<td style="padding: 12px 14px; font-family: monospace; font-size: 12px; font-weight: 600; color: #0284c7;">includes/class-mpk-woocommerce-bridge.php</td>
										<td style="padding: 12px 14px;"><span style="background: #fce7f3; color: #9d174d; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">WooCommerce Core Bridge</span></td>
										<td style="padding: 12px 14px; color: #475569; line-height: 1.4;">Provides dedicated checkout bridging for "Card Payment" into WooCommerce, syncing customer info, order items, and two-way payment statuses (on <code>feature/woocommerce-integration</code>).</td>
									</tr>
								</tbody>
							</table>
						</div>

						<script>
						document.addEventListener('DOMContentLoaded', function () {
							var copyBtn = document.getElementById('mpk-btn-copy-shortcode');
							if (copyBtn) {
								copyBtn.addEventListener('click', function () {
									var codeText = '[maldives_packages_wizard]';
									var btnText = document.getElementById('mpk-copy-btn-text');

									function notifyCopied() {
										copyBtn.style.background = '#10b981';
										if (btnText) btnText.textContent = '✓ Copied!';
										setTimeout(function () {
											copyBtn.style.background = '#0284c7';
											if (btnText) btnText.textContent = 'Copy Shortcode';
										}, 2200);
									}

									if (navigator.clipboard && window.isSecureContext) {
										navigator.clipboard.writeText(codeText).then(notifyCopied).catch(function () {
											fallbackCopy();
										});
									} else {
										fallbackCopy();
									}

									function fallbackCopy() {
										var ta = document.createElement('textarea');
										ta.value = codeText;
										ta.style.position = 'fixed';
										ta.style.left = '-9999px';
										document.body.appendChild(ta);
										ta.focus();
										ta.select();
										try {
											document.execCommand('copy');
											notifyCopied();
										} catch (err) {}
										document.body.removeChild(ta);
									}
								});
							}
						});
						</script>
					<?php endif; ?>

					<?php if ( 'guide' !== $active_tab ) : ?>
						<div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
							<button type="submit" name="mpk_save_settings" class="button button-primary button-hero" style="font-size: 14px; height: 42px; line-height: 40px; padding: 0 24px;">
								<span class="dashicons dashicons-saved" style="vertical-align: -2px;"></span>
								<?php esc_html_e( 'Save Tab Settings', 'maldives-packages' ); ?>
							</button>
							<span style="font-size: 12px; color: #64748b;">
								<?php esc_html_e( 'Maldives Packages Enterprise Engine v1.0.1', 'maldives-packages' ); ?>
							</span>
						</div>
					<?php else : ?>
						<div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
							<span style="font-size: 13px; color: #0284c7; font-weight: 600;">
								<span class="dashicons dashicons-info" style="vertical-align: -2px;"></span>
								<?php esc_html_e( 'Documentation & Developer Reference — No configuration changes to save in this tab.', 'maldives-packages' ); ?>
							</span>
							<span style="font-size: 12px; color: #64748b;">
								<?php esc_html_e( 'Maldives Packages Enterprise Engine v1.0.1', 'maldives-packages' ); ?>
							</span>
						</div>
					<?php endif; ?>
				</form>
			</div>
		</div>
		<?php
	}
}
