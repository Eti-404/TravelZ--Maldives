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
		if ( self::$rendered ) {
			return;
		}
		self::$rendered = true;

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		$valid_tabs = array( 'general', 'pricing', 'policies', 'payment' );
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
					<?php endif; ?>

					<div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
						<button type="submit" name="mpk_save_settings" class="button button-primary button-hero" style="font-size: 14px; height: 42px; line-height: 40px; padding: 0 24px;">
							<span class="dashicons dashicons-saved" style="vertical-align: -2px;"></span>
							<?php esc_html_e( 'Save Tab Settings', 'maldives-packages' ); ?>
						</button>
						<span style="font-size: 12px; color: #64748b;">
							<?php esc_html_e( 'Maldives Packages Enterprise Engine v1.0.1', 'maldives-packages' ); ?>
						</span>
					</div>
				</form>
			</div>
		</div>
		<?php
	}
}
