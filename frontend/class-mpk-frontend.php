<?php
/**
 * Frontend Controller for Maldives Packages Booking.
 *
 * Handles shortcode registration, asset enqueueing, and rendering
 * the complete 5-step booking wizard layout.
 *
 * Approved Prefix: MPK / mpk_ / mpk-
 * Strictly forbids TZ / tz prefix.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MPK_Frontend {

	/**
	 * Constructor: register shortcode and asset hooks.
	 */
	public function __construct() {
		// Master Shortcode: [maldives_packages_wizard]
		add_shortcode( 'maldives_packages_wizard', array( $this, 'render_shortcode' ) );
		// Backward-compatible alias: [maldives_packages]
		add_shortcode( 'maldives_packages', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register frontend scripts and styles.
	 */
	public function register_assets() {
		wp_register_style(
			'mpk-frontend-css',
			MPK_PLUGIN_URL . 'assets/css/mpk-frontend.css',
			array(),
			MPK_VERSION
		);

		wp_register_script(
			'mpk-main-js',
			MPK_PLUGIN_URL . 'assets/js/mpk-main.js',
			array(),
			MPK_VERSION,
			true
		);

		// Note: heavy package data is localized only in render_shortcode(), not on every page.
	}

	/**
	 * Localize package data once, only on pages that actually render the wizard.
	 */
	private function localize_package_data() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		wp_localize_script(
			'mpk-main-js',
			'MPK_INITIAL_DATA',
			MPK_Data_Manager::get_package_data()
		);
	}

	/**
	 * Render SVG icon helper.
	 *
	 * @param string $name Icon name.
	 * @param int    $width Width.
	 * @param int    $height Height.
	 * @param string $class Extra CSS class.
	 * @return string SVG markup.
	 */
	public static function get_icon( $name, $width = 16, $height = 16, $class = '' ) {
		$class_attr = 'mpk-icon' . ( $class ? ' ' . esc_attr( $class ) : '' );
		switch ( $name ) {
			case 'map-pin':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>';
			case 'sparkles':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>';
			case 'users':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
			case 'user':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>';
			case 'baby':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h.01"/><path d="M15 12h.01"/><path d="M10 16c.5.5 1.5.5 2 0"/><path d="M19 6.3a9 9 0 0 1 1.8 3.9 2 2 0 0 1 0 3.6 9 9 0 0 1-17.6 0 2 2 0 0 1 0-3.6A9 9 0 0 1 12 3c2 0 3.5 1.1 3.5 2.5s-.9 2.5-2 2.5c-.8 0-1.5-.4-1.5-1"/></svg>';
			case 'bed-double':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20v-8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8"/><path d="M4 10V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4"/><path d="M12 4v6"/><path d="M2 18h20"/></svg>';
			case 'info':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>';
			case 'search':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>';
			case 'star':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="#facc15" stroke="#facc15" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
			case 'moon':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>';
			case 'calendar':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>';
			case 'check':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
			case 'shield-check':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-8 9-8 9s-8-4-8-9V5l8-3 8 3v8z"/><polyline points="9 12 11 14 15 10"/></svg>';
			case 'phone':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
			case 'mail':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>';
			case 'globe':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" x2="22" y1="12" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
			case 'file-text':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg>';
			case 'upload':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>';
			case 'message-square':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
			case 'building-2':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>';
			case 'landmark':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="2" x2="22" y1="22" y2="22"/><line x1="3" x2="21" y1="6" y2="6"/><path d="M4 18v-8"/><path d="M8 18v-8"/><path d="M12 18v-8"/><path d="M16 18v-8"/><path d="M20 18v-8"/><polygon points="12 2 20 6 4 6 12 2"/></svg>';
			case 'credit-card':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>';
			case 'check-circle-2':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
			case 'copy':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>';
			case 'clock':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
			case 'arrow-left':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>';
			case 'arrow-right':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';
			case 'chevron-down':
				return '<svg class="' . $class_attr . '" width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
			default:
				return '';
		}
	}

	/**
	 * Render the mounting shortcode container and 5-step wizard.
	 *
	 * @return string HTML output.
	 */
	public function render_shortcode() {
		// Ensure assets are registered (for Elementor / dynamic AJAX render contexts)
		if ( ! wp_style_is( 'mpk-frontend-css', 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( 'mpk-frontend-css' );
		$this->localize_package_data();
		wp_enqueue_script( 'mpk-main-js' );

		$locations = MPK_Data_Manager::get_locations();
		$hero_img  = MPK_PLUGIN_URL . 'assets/images/maldives-hero.jpg';

		ob_start();
		?>
		<div id="mpk-booking-app" class="mpk-booking-app" data-plugin="maldives-packages">

			<!-- HERO SECTION -->
			<header class="mpk-hero">
				<div class="mpk-hero-bg-img" style="background-image: url('<?php echo esc_url( $hero_img ); ?>');"></div>
				<div class="mpk-hero-content">
					<div class="mpk-pill-badge">
						<?php echo self::get_icon( 'sparkles', 14, 14 ); ?>
						<span>Premium Island Escapes</span>
					</div>
					<h1 class="mpk-hero-title">Maldives Package</h1>
					<p class="mpk-hero-desc">
						Crystal lagoons, overwater villas and curated luxury &mdash; design a Maldives getaway that's unmistakably yours.
					</p>
				</div>
			</header>

			<!-- WIZARD CONTAINER OVERLAP -->
			<div class="mpk-wizard-container">

				<!-- STEPPER CARD -->
				<div class="mpk-stepper-card">
					<div class="mpk-stepper-track">

						<!-- Step 1 -->
						<div class="mpk-step-column active" data-step="1">
							<button type="button" class="mpk-step-item">
								<div class="mpk-step-circle">1</div>
								<div class="mpk-step-text-wrap">
									<span class="mpk-step-label">Package Info</span>
									<span class="mpk-step-sub">Travelers &amp; locations</span>
								</div>
							</button>
							<div class="mpk-step-connector">
								<div class="mpk-step-connector-fill"></div>
							</div>
						</div>

						<!-- Step 2 -->
						<div class="mpk-step-column" data-step="2">
							<button type="button" class="mpk-step-item">
								<div class="mpk-step-circle">2</div>
								<div class="mpk-step-text-wrap">
									<span class="mpk-step-label">Hotel Selection</span>
									<span class="mpk-step-sub">Pick your stay</span>
								</div>
							</button>
							<div class="mpk-step-connector">
								<div class="mpk-step-connector-fill"></div>
							</div>
						</div>

						<!-- Step 3 -->
						<div class="mpk-step-column" data-step="3">
							<button type="button" class="mpk-step-item">
								<div class="mpk-step-circle">3</div>
								<div class="mpk-step-text-wrap">
									<span class="mpk-step-label">Review</span>
									<span class="mpk-step-sub">Complete review</span>
								</div>
							</button>
							<div class="mpk-step-connector">
								<div class="mpk-step-connector-fill"></div>
							</div>
						</div>

						<!-- Step 4 -->
						<div class="mpk-step-column" data-step="4">
							<button type="button" class="mpk-step-item">
								<div class="mpk-step-circle">4</div>
								<div class="mpk-step-text-wrap">
									<span class="mpk-step-label">Booking Process</span>
									<span class="mpk-step-sub">Payment method</span>
								</div>
							</button>
							<div class="mpk-step-connector">
								<div class="mpk-step-connector-fill"></div>
							</div>
						</div>

						<!-- Step 5 -->
						<div class="mpk-step-column" data-step="5">
							<button type="button" class="mpk-step-item">
								<div class="mpk-step-circle">5</div>
								<div class="mpk-step-text-wrap">
									<span class="mpk-step-label">Confirmation</span>
									<span class="mpk-step-sub">Payment</span>
								</div>
							</button>
						</div>

					</div>
				</div>

				<!-- MAIN CARD CONTENT -->
				<main class="mpk-wizard-card mpk-main-card">

					<!-- STEP 1: PACKAGE INFO -->
					<section class="mpk-step-pane active" id="mpk-pane-1" data-step="1">
						<div class="mpk-section-header">
							<span class="mpk-section-icon">
								<?php echo self::get_icon( 'map-pin', 20, 20 ); ?>
							</span>
							<h3>Where would you prefer to stay?</h3>
							<span class="mpk-badge-hint">Select one or more</span>
						</div>

						<!-- Location Cards Grid -->
						<div class="mpk-locations-grid">
							<?php foreach ( $locations as $loc ) : ?>
								<div class="mpk-location-card" data-location-id="<?php echo esc_attr( $loc['id'] ); ?>">
									<img src="<?php echo esc_url( $loc['image'] ); ?>" alt="<?php echo esc_attr( $loc['name'] ); ?>" loading="lazy" />
									<div class="mpk-location-overlay"></div>
									<div class="mpk-location-check">
										<?php echo self::get_icon( 'check', 18, 18 ); ?>
									</div>
									<div class="mpk-location-info">
										<p class="mpk-location-title"><?php echo esc_html( $loc['name'] ); ?></p>
										<p class="mpk-location-tagline"><?php echo esc_html( $loc['tagline'] ); ?></p>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Note Info Box -->
						<div class="mpk-info-box">
							<span class="mpk-info-icon">
								<?php echo self::get_icon( 'info', 18, 18 ); ?>
							</span>
							<p style="margin: 0;">
								<strong>Note:</strong> select your stay location in order, staying with your first destination.
							</p>
						</div>

						<!-- Quantity Selectors (Step 1) -->
						<div class="mpk-quantities-grid">
							<!-- Adults -->
							<div class="mpk-quantity-card">
								<div class="mpk-quantity-meta">
									<div class="mpk-quantity-icon-badge">
										<?php echo self::get_icon( 'users', 18, 18 ); ?>
									</div>
									<div>
										<p class="mpk-quantity-label">Adults</p>
										<p class="mpk-quantity-hint">Age 12+</p>
									</div>
								</div>
								<div class="mpk-quantity-controls">
									<button type="button" class="mpk-btn-qty-minus" data-target="adults" aria-label="Decrease Adults">&minus;</button>
									<span class="mpk-quantity-value" id="mpk-val-adults">2</span>
									<button type="button" class="mpk-btn-qty-plus" data-target="adults" aria-label="Increase Adults">&plus;</button>
								</div>
							</div>

							<!-- Children -->
							<div class="mpk-quantity-card">
								<div class="mpk-quantity-meta">
									<div class="mpk-quantity-icon-badge">
										<?php echo self::get_icon( 'user', 18, 18 ); ?>
									</div>
									<div>
										<p class="mpk-quantity-label">Children</p>
										<p class="mpk-quantity-hint">Age 2-11</p>
									</div>
								</div>
								<div class="mpk-quantity-controls">
									<button type="button" class="mpk-btn-qty-minus" data-target="children" disabled aria-label="Decrease Children">&minus;</button>
									<span class="mpk-quantity-value" id="mpk-val-children">0</span>
									<button type="button" class="mpk-btn-qty-plus" data-target="children">&plus;</button>
								</div>
							</div>

							<!-- Infants -->
							<div class="mpk-quantity-card">
								<div class="mpk-quantity-meta">
									<div class="mpk-quantity-icon-badge">
										<?php echo self::get_icon( 'baby', 18, 18 ); ?>
									</div>
									<div>
										<p class="mpk-quantity-label">Infants</p>
										<p class="mpk-quantity-hint">Under 2</p>
									</div>
								</div>
								<div class="mpk-quantity-controls">
									<button type="button" class="mpk-btn-qty-minus" data-target="infants" disabled aria-label="Decrease Infants">&minus;</button>
									<span class="mpk-quantity-value" id="mpk-val-infants">0</span>
									<button type="button" class="mpk-btn-qty-plus" data-target="infants">&plus;</button>
								</div>
							</div>

							<!-- Rooms -->
							<div class="mpk-quantity-card">
								<div class="mpk-quantity-meta">
									<div class="mpk-quantity-icon-badge">
										<?php echo self::get_icon( 'bed-double', 18, 18 ); ?>
									</div>
									<div>
										<p class="mpk-quantity-label">Rooms</p>
										<p class="mpk-quantity-hint">Total rooms</p>
									</div>
								</div>
								<div class="mpk-quantity-controls">
									<button type="button" class="mpk-btn-qty-minus" data-target="rooms" aria-label="Decrease Rooms">&minus;</button>
									<span class="mpk-quantity-value" id="mpk-val-rooms">1</span>
									<button type="button" class="mpk-btn-qty-plus" data-target="rooms">&plus;</button>
								</div>
							</div>
						</div>

						<!-- Total Travelers Banner -->
						<div class="mpk-travelers-total">
							<span>Total travelers</span>
							<span class="mpk-travelers-count" id="mpk-total-travelers">2</span>
						</div>
						<p class="mpk-occupancy-hint" id="mpk-occupancy-hint"></p>
					</section>

					<!-- STEP 2: HOTEL SELECTION -->
					<section class="mpk-step-pane" id="mpk-pane-2" data-step="2">
						<div class="mpk-step2-layout">
							<!-- Filters Sidebar -->
							<aside class="mpk-filter-sidebar">
								<div class="mpk-search-box">
									<span class="mpk-search-icon"><?php echo self::get_icon( 'search', 16, 16 ); ?></span>
									<input type="text" id="mpk-hotel-search" class="mpk-search-input" placeholder="Search hotel..." />
								</div>

								<!-- Star Rating with Circular Radio-Dot Controls -->
								<div class="mpk-filter-accordion-item" data-filter="stars">
									<div class="mpk-filter-title">
										<span>Star Rating</span>
										<?php echo self::get_icon( 'chevron-down', 16, 16 ); ?>
									</div>
									<div class="mpk-filter-accordion-content">
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_stars" value="3" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">3 Star</span>
										</label>
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_stars" value="4" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">4 Star</span>
										</label>
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_stars" value="5" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">5 Star</span>
										</label>
									</div>
								</div>

								<!-- Meals with Circular Radio-Dot Controls -->
								<div class="mpk-filter-accordion-item" data-filter="meals">
									<div class="mpk-filter-title">
										<span>Meals</span>
										<?php echo self::get_icon( 'chevron-down', 16, 16 ); ?>
									</div>
									<div class="mpk-filter-accordion-content">
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_meals" value="Breakfast" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">Breakfast</span>
										</label>
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_meals" value="Breakfast & Dinner" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">Breakfast &amp; Dinner</span>
										</label>
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_meals" value="Breakfast Lunch & Dinner" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">Breakfast Lunch &amp; Dinner</span>
										</label>
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_meals" value="All Inclusive" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">All Inclusive</span>
										</label>
									</div>
								</div>

								<!-- Bed Type with Circular Radio-Dot Controls -->
								<div class="mpk-filter-accordion-item collapsed" data-filter="beds">
									<div class="mpk-filter-title">
										<span>Bed Type</span>
										<?php echo self::get_icon( 'chevron-down', 16, 16 ); ?>
									</div>
									<div class="mpk-filter-accordion-content">
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_beds" value="Single" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">Single</span>
										</label>
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_beds" value="Double" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">Double</span>
										</label>
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_beds" value="Twin" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">Twin</span>
										</label>
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_beds" value="Triple" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">Triple</span>
										</label>
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_beds" value="King" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">King</span>
										</label>
									</div>
								</div>

								<!-- Room Type with Circular Radio-Dot Controls -->
								<div class="mpk-filter-accordion-item collapsed" data-filter="rooms">
									<div class="mpk-filter-title">
										<span>Room Type</span>
										<?php echo self::get_icon( 'chevron-down', 16, 16 ); ?>
									</div>
									<div class="mpk-filter-accordion-content">
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_rooms" value="Balcony" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">Balcony</span>
										</label>
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_rooms" value="Sea View" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">Sea View</span>
										</label>
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_rooms" value="Island View" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">Island View</span>
										</label>
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_rooms" value="With Pool" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">With Pool</span>
										</label>
										<label class="mpk-filter-item mpk-filter-radio-item">
											<input type="checkbox" name="mpk_rooms" value="Water Villa" />
											<span class="mpk-radio-dot"></span>
											<span class="mpk-filter-label">Water Villa</span>
										</label>
									</div>
								</div>

								<!-- Price Range -->
								<div class="mpk-filter-accordion-item" data-filter="price">
									<div class="mpk-filter-title">
										<span>Price Range</span>
										<?php echo self::get_icon( 'chevron-down', 16, 16 ); ?>
									</div>
									<div class="mpk-filter-accordion-content">
										<div class="mpk-price-slider-wrap">
											<input type="range" id="mpk-price-range" class="mpk-price-slider" min="50" max="1000" step="10" value="1000" />
											<div class="mpk-price-range-label">
												<span class="mpk-price-pill">$50</span>
												<span style="color: var(--mpk-text-muted);">per night</span>
												<span class="mpk-price-pill" id="mpk-price-max-label">$1000</span>
											</div>
										</div>
									</div>
								</div>
							</aside>

							<!-- Hotels Dynamic Container -->
							<div id="mpk-hotels-container">
								<!-- Rendered dynamically via JavaScript -->
							</div>
						</div>
					</section>

					<!-- STEP 3: REVIEW & DETAILS -->
					<section class="mpk-step-pane" id="mpk-pane-3" data-step="3">
						<div class="mpk-section-header mpk-section-header-stacked mpk-section-header-sm">
							<h3>Review your booking</h3>
							<p class="mpk-section-sub">Please confirm all the details below before booking.</p>
						</div>

						<!-- Overview Stats -->
						<div class="mpk-stats-row">
							<div class="mpk-stat-card">
								<p class="mpk-stat-label"><?php echo self::get_icon( 'users', 14, 14 ); ?> Travelers</p>
								<p class="mpk-stat-val" id="mpk-review-stat-travelers">2A &middot; 0C &middot; 0I</p>
							</div>
							<div class="mpk-stat-card">
								<p class="mpk-stat-label"><?php echo self::get_icon( 'moon', 14, 14 ); ?> Total Nights</p>
								<p class="mpk-stat-val" id="mpk-review-stat-nights">&mdash;</p>
							</div>
						</div>

						<!-- Selected Stays Container -->
						<div class="mpk-review-block">
							<h4 class="mpk-review-heading">Selected Stays</h4>
							<div id="mpk-review-stays-list">
								<!-- Rendered dynamically -->
							</div>
						</div>

						<!-- Lead Traveler Form (clean, placeholder-only fields) -->
						<div class="mpk-review-block">
							<h4 class="mpk-review-heading">Lead Traveler Details</h4>
							<p class="mpk-review-subtext">We'll send your booking confirmation here.</p>
							<div class="mpk-form-grid">
								<div class="mpk-form-field">
									<label class="mpk-label" for="mpk-lead-name">
										<?php echo self::get_icon( 'user', 14, 14 ); ?> Full Name
									</label>
									<input type="text" id="mpk-lead-name" class="mpk-input" placeholder="John Doe" value="" required />
								</div>

								<div class="mpk-form-field">
									<label class="mpk-label" for="mpk-lead-mobile">
										<?php echo self::get_icon( 'phone', 14, 14 ); ?> Mobile Number
									</label>
									<input type="tel" id="mpk-lead-mobile" class="mpk-input" placeholder="+960 ..." value="" />
								</div>

								<div class="mpk-form-field">
									<label class="mpk-label" for="mpk-lead-email">
										<?php echo self::get_icon( 'mail', 14, 14 ); ?> Email Address
									</label>
									<input type="email" id="mpk-lead-email" class="mpk-input" placeholder="you@email.com" value="" required />
								</div>

								<div class="mpk-form-field">
									<label class="mpk-label" for="mpk-lead-country">
										<?php echo self::get_icon( 'globe', 14, 14 ); ?> Country
									</label>
									<input type="text" id="mpk-lead-country" class="mpk-input" placeholder="Country" value="" />
								</div>

								<div class="mpk-form-field">
									<label class="mpk-label" for="mpk-lead-passport">
										<?php echo self::get_icon( 'file-text', 14, 14 ); ?> Passport Number
									</label>
									<input type="text" id="mpk-lead-passport" class="mpk-input" placeholder="A12345678" value="" />
								</div>

								<!-- Anti-spam honeypot: hidden from humans, bots tend to fill it -->
								<div aria-hidden="true" style="position:absolute !important; left:-10000px !important; top:auto; width:1px; height:1px; overflow:hidden;">
									<label for="mpk-hp-website">Website</label>
									<input type="text" id="mpk-hp-website" name="mpk_website" value="" tabindex="-1" autocomplete="off" />
								</div>

								<div class="mpk-form-field">
									<label class="mpk-label">
										<?php echo self::get_icon( 'upload', 14, 14 ); ?> Passport Attachment
									</label>
									<div class="mpk-file-drop" id="mpk-passport-drop">
										<input type="file" id="mpk-passport-file" accept="application/pdf,image/png,image/jpeg,image/jpg,image/webp" style="display: none;" />
										<div id="mpk-passport-drop-label" class="mpk-file-drop-label">
											<?php echo self::get_icon( 'upload', 16, 16 ); ?>
											<span>Upload passport</span>
										</div>
										<div id="mpk-passport-drop-fileinfo" style="display: none; align-items: center; justify-content: space-between; width: 100%;">
											<span id="mpk-passport-filename" class="mpk-file-name"></span>
											<button type="button" id="mpk-passport-remove" class="mpk-btn-qty-minus mpk-file-remove">&times;</button>
										</div>
									</div>
									<p class="mpk-file-note">Note: Please upload as a <span>single PDF</span> or a <span>single image</span> (PNG / JPG / WEBP), max 10 MB.</p>
									<p class="mpk-file-note mpk-file-note-2">All travellers passport copies must be attached.</p>
								</div>

								<div class="mpk-form-field mpk-form-full">
									<label class="mpk-label" for="mpk-lead-request">
										<?php echo self::get_icon( 'message-square', 14, 14 ); ?> Special Request
									</label>
									<textarea id="mpk-lead-request" class="mpk-textarea" rows="3" placeholder="Honeymoon, dietary needs, late check-in..."></textarea>
								</div>
							</div>
						</div>

						<!-- Compact Booking Summary Card (reference-matched, no redundant tables) -->
						<div class="mpk-pricing-summary-card" id="mpk-review-pricing-breakdown">
							<!-- Rendered dynamically via JavaScript -->
						</div>

						<!-- Inclusions/Exclusions & Policy Accordions -->
						<div class="mpk-accordion" id="mpk-accordions">
							<!-- Package Accordion Item -->
							<div class="mpk-accordion-item open" id="mpk-acc-package">
								<div class="mpk-accordion-header">
									<span>Package</span>
									<span class="mpk-icon-chevron"><?php echo self::get_icon( 'chevron-down', 16, 16 ); ?></span>
								</div>
								<div class="mpk-accordion-body" id="mpk-acc-package-body">
									<!-- Rendered dynamically based on selected hotels -->
								</div>
							</div>

							<!-- Cancellation Policy -->
							<div class="mpk-accordion-item">
								<div class="mpk-accordion-header">
									<span>Cancellation Policy</span>
									<span class="mpk-icon-chevron"><?php echo self::get_icon( 'chevron-down', 16, 16 ); ?></span>
								</div>
								<div class="mpk-accordion-body">
									Free cancellation up to 30 days before travel. 50% refund up to 14 days. No refund within 7 days of arrival.
								</div>
							</div>

							<!-- Terms & Conditions -->
							<div class="mpk-accordion-item">
								<div class="mpk-accordion-header">
									<span>Terms &amp; Conditions</span>
									<span class="mpk-icon-chevron"><?php echo self::get_icon( 'chevron-down', 16, 16 ); ?></span>
								</div>
								<div class="mpk-accordion-body">
									Bookings are subject to availability and confirmation. Prices are per package and may vary by season.
								</div>
							</div>

							<!-- Important Notes -->
							<div class="mpk-accordion-item">
								<div class="mpk-accordion-header">
									<span>Important Notes</span>
									<span class="mpk-icon-chevron"><?php echo self::get_icon( 'chevron-down', 16, 16 ); ?></span>
								</div>
								<div class="mpk-accordion-body">
									Valid passport (6+ months) required. Visa-free for most nationalities. Pack light, breathable clothing.
								</div>
							</div>
						</div>

						<!-- Terms Checkbox with Circular Bubble Control -->
						<label class="mpk-terms-card" for="mpk-terms-agree">
							<div class="mpk-terms-bubble-wrap">
								<input type="checkbox" id="mpk-terms-agree" />
								<span class="mpk-terms-bubble">
									<?php echo self::get_icon( 'check', 12, 12 ); ?>
								</span>
							</div>
							<span class="mpk-terms-text">
								<span style="color: var(--mpk-primary); margin-right: 6px; display: inline-flex; vertical-align: middle;">
									<?php echo self::get_icon( 'shield-check', 16, 16 ); ?>
								</span>
								I agree to the booking terms, cancellation policy and travel conditions.
							</span>
						</label>
					</section>

					<!-- STEP 4: PAYMENT SELECTION -->
					<section class="mpk-step-pane" id="mpk-pane-4" data-step="4">
						<div class="mpk-section-header mpk-section-header-stacked">
							<h3>Choose your payment method</h3>
							<p class="mpk-section-sub">Select how you'd like to complete your booking payment.</p>
						</div>

						<div class="mpk-payment-options">
							<!-- Office Payment -->
							<div class="mpk-payment-card" data-payment-method="office">
								<div class="mpk-payment-icon">
									<?php echo self::get_icon( 'building-2', 24, 24 ); ?>
								</div>
								<div style="flex: 1;">
									<p class="mpk-payment-title">Office Visit Payment</p>
									<p class="mpk-payment-desc">Pay in person at our office. Our team will assist you.</p>
								</div>
								<div class="mpk-payment-check-badge">
									<?php echo self::get_icon( 'check', 16, 16 ); ?>
								</div>
							</div>

							<!-- Bank Transfer -->
							<div class="mpk-payment-card" data-payment-method="bank">
								<div class="mpk-payment-icon">
									<?php echo self::get_icon( 'landmark', 24, 24 ); ?>
								</div>
								<div style="flex: 1;">
									<p class="mpk-payment-title">Bank Transfer</p>
									<p class="mpk-payment-desc">Transfer to our bank account. Details sent after confirmation.</p>
								</div>
								<div class="mpk-payment-check-badge">
									<?php echo self::get_icon( 'check', 16, 16 ); ?>
								</div>
							</div>

							<!-- Card Payment (Disabled) -->
							<div class="mpk-payment-card disabled" data-payment-method="card">
								<div class="mpk-payment-icon">
									<?php echo self::get_icon( 'credit-card', 24, 24 ); ?>
								</div>
								<div style="flex: 1;">
									<div style="display: flex; align-items: center; gap: 8px;">
										<p class="mpk-payment-title">Card Payment</p>
										<span class="mpk-pill mpk-pill-unavailable">Unavailable for now</span>
									</div>
									<p class="mpk-payment-desc">Pay securely with credit or debit card.</p>
								</div>
							</div>
						</div>
					</section>

					<!-- STEP 5: CONFIRMATION -->
					<section class="mpk-step-pane" id="mpk-pane-5" data-step="5">
						<div class="mpk-confirm-card">
							<div class="mpk-confirm-banner">
								<div class="mpk-confirm-icon">
									<?php echo self::get_icon( 'check-circle-2', 36, 36 ); ?>
								</div>
								<h3 class="mpk-confirm-title">Booking Confirmation</h3>
								<p class="mpk-confirm-sub">Thank you for choosing Travel Z</p>
							</div>

							<div class="mpk-confirm-body">
								<div class="mpk-confirm-code-card">
									<p class="mpk-confirm-code-label">Booking Confirmation Number</p>
									<div class="mpk-confirm-code-row">
										<span class="mpk-confirm-code-text" id="mpk-confirm-code-display">MPK-PENDING</span>
										<button type="button" id="mpk-btn-copy-code" class="mpk-btn-copy" aria-label="Copy confirmation number">
											<?php echo self::get_icon( 'copy', 14, 14 ); ?>
											<span class="mpk-btn-copy-text">Copy</span>
										</button>
									</div>
								</div>

								<p>Thank you for choosing <strong>Travel Z</strong>.</p>
								<p>Your booking request has been successfully received.</p>
								<p>
									A confirmation email has been sent to
									<a href="#" class="mpk-confirm-email-link" id="mpk-confirm-email-display">your email</a>.
								</p>
								<p>
									Our Travel Concierge will contact you within <strong>24 hours</strong> to review your booking and provide further assistance.
								</p>

								<div class="mpk-confirm-status">
									<?php echo self::get_icon( 'clock', 16, 16 ); ?>
									<p><strong>Status:</strong> Pending Travel Z Approval</p>
								</div>

								<p class="mpk-confirm-note">
									<strong>Please note:</strong> Your booking is <strong>not yet confirmed</strong>. It will be confirmed only after it has been reviewed and approved by the Travel Z team.
								</p>

								<div class="mpk-confirm-amount-box">
									<p class="mpk-confirm-amount-label">Amount to pay</p>
									<p class="mpk-confirm-amount-val" id="mpk-confirm-amount-display">$0.00</p>
								</div>
							</div>
						</div>

						<!-- Payment Instructions Card -->
						<div class="mpk-review-block mpk-instructions-card">
							<h4 class="mpk-review-heading">Payment Instructions</h4>
							<div id="mpk-payment-instructions-body">
								<!-- Dynamic based on selected payment method -->
							</div>
						</div>
					</section>

					<!-- BOTTOM STEP NAVIGATION (FLOATING FOOTER BAR) -->
					<div class="mpk-step-nav" id="mpk-step-nav">
						<div class="mpk-step-nav-left">
							<button type="button" class="mpk-btn mpk-btn-outline mpk-btn-back" style="visibility: hidden;">
								<?php echo self::get_icon( 'arrow-left', 16, 16 ); ?>
								<span>Back</span>
							</button>
						</div>
						<div class="mpk-step-nav-center">
							<span class="mpk-validation-hint" id="mpk-validation-hint">Select at least one location</span>
						</div>
						<div class="mpk-step-nav-right">
							<button type="button" class="mpk-btn mpk-btn-primary mpk-btn-next" disabled>
								<span id="mpk-btn-next-label">Continue</span>
								<?php echo self::get_icon( 'arrow-right', 16, 16 ); ?>
							</button>
						</div>
					</div>

				</main>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}
}
