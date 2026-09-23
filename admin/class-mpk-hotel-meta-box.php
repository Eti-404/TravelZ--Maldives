<?php
/**
 * Dynamic Room Repeater Meta Box for Hotels & Stays (mpk_hotel)
 *
 * Allows administrators to add, edit, and remove room inventories
 * with meal plans, bed types, pricing, and highlights dynamically.
 *
 * Approved Prefix: MPK / mpk_
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MPK_Hotel_Meta_Box {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_mpk_hotel', array( $this, 'save_rooms' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin styles for repeater cards if needed.
	 *
	 * @param string $hook Admin hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$current_post_type = $screen && ! empty( $screen->post_type ) ? $screen->post_type : ( isset( $GLOBALS['post_type'] ) ? $GLOBALS['post_type'] : '' );
		if ( empty( $current_post_type ) && isset( $_GET['post_type'] ) ) {
			$current_post_type = sanitize_key( $_GET['post_type'] );
		}

		if ( 'mpk_hotel' !== $current_post_type ) {
			return;
		}

		wp_add_inline_style(
			'common',
			'
			.mpk-repeater-wrap { margin-top: 10px; }
			.mpk-room-card {
				background: #ffffff;
				border: 1px solid #cbd5e1;
				border-radius: 8px;
				padding: 16px 20px;
				margin-bottom: 16px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.05);
				transition: border-color 0.2s ease, box-shadow 0.2s ease;
			}
			.mpk-room-card:hover { border-color: #94a3b8; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
			.mpk-room-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding-bottom: 12px;
				margin-bottom: 14px;
				border-bottom: 1px solid #f1f5f9;
			}
			.mpk-room-title { font-size: 14px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px; }
			.mpk-room-grid {
				display: grid;
				grid-template-columns: 2fr 1.3fr 1.3fr 1.2fr 1fr;
				gap: 14px;
				margin-bottom: 12px;
			}
			.mpk-room-grid-full {
				display: grid;
				grid-template-columns: 1fr;
				gap: 8px;
			}
			.mpk-field-group label {
				display: block;
				font-size: 11px;
				font-weight: 600;
				color: #475569;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				margin-bottom: 6px;
			}
			.mpk-field-group input,
			.mpk-field-group select {
				width: 100%;
				height: 38px;
				border-radius: 6px;
				border: 1px solid #cbd5e1;
				padding: 0 10px;
				font-size: 13px;
			}
			.mpk-field-group input:focus,
			.mpk-field-group select:focus {
				border-color: #0284c7;
				outline: none;
				box-shadow: 0 0 0 2px rgba(2,132,199,0.2);
			}
			.mpk-btn-add-room {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				background: #f0fdf4;
				color: #166534;
				border: 1px solid #bbf7d0;
				padding: 8px 18px;
				border-radius: 6px;
				font-size: 13px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.15s ease;
			}
			.mpk-btn-add-room:hover { background: #dcfce7; border-color: #86efac; color: #14532d; }
			.mpk-btn-remove-room {
				background: #fef2f2;
				color: #b91c1c;
				border: 1px solid #fecaca;
				padding: 4px 12px;
				border-radius: 4px;
				font-size: 12px;
				cursor: pointer;
				transition: background 0.15s ease;
			}
			.mpk-btn-remove-room:hover { background: #fee2e2; border-color: #fca5a5; }
			@media (max-width: 782px) {
				.mpk-room-grid { grid-template-columns: 1fr; }
			}
			'
		);
	}

	/**
	 * Register the meta boxes.
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'mpk_hotel_details_meta_box',
			__( 'Hotel Details & Star Rating', 'maldives-packages' ),
			array( $this, 'render_hotel_details_meta_box' ),
			'mpk_hotel',
			'normal',
			'high'
		);

		add_meta_box(
			'mpk_hotel_rooms_metabox',
			__( 'Hotel Rooms & Pricing Configuration', 'maldives-packages' ),
			array( $this, 'render_rooms_metabox' ),
			'mpk_hotel',
			'normal',
			'high'
		);
	}

	/**
	 * Render rooms metabox (Dynamic Room Repeater).
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_rooms_metabox( $post ) {
		$this->render_meta_box( $post );
	}

	/**
	 * Render Hotel Details and Star Rating meta box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_hotel_details_meta_box( $post ) {
		wp_nonce_field( 'mpk_save_hotel_details', 'mpk_hotel_details_nonce' );

		$stars        = get_post_meta( $post->ID, '_mpk_stars', true );
		$stars        = ! empty( $stars ) ? absint( $stars ) : 4;
		$review       = get_post_meta( $post->ID, '_mpk_review', true );
		$review       = ! empty( $review ) ? floatval( $review ) : 4.7;
		$review_label = get_post_meta( $post->ID, '_mpk_review_label', true );
		$review_label = ! empty( $review_label ) ? $review_label : 'Excellent';
		$area         = get_post_meta( $post->ID, '_mpk_area', true );
		$amenities    = get_post_meta( $post->ID, '_mpk_amenities', true );
		if ( is_array( $amenities ) ) {
			$amenities = implode( ', ', $amenities );
		}
		?>
		<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px;">
			<div>
				<label for="mpk_hotel_stars" style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13px;">
					<?php esc_html_e( 'Star Rating', 'maldives-packages' ); ?>
				</label>
				<select name="mpk_hotel_stars" id="mpk_hotel_stars" style="width: 100%; height: 38px; border-radius: 6px;">
					<option value="5" <?php selected( $stars, 5 ); ?>><?php esc_html_e( '5 Stars (Luxury / Overwater)', 'maldives-packages' ); ?></option>
					<option value="4" <?php selected( $stars, 4 ); ?>><?php esc_html_e( '4 Stars (Premium Resort / City)', 'maldives-packages' ); ?></option>
					<option value="3" <?php selected( $stars, 3 ); ?>><?php esc_html_e( '3 Stars (Comfort / Boutique)', 'maldives-packages' ); ?></option>
					<option value="2" <?php selected( $stars, 2 ); ?>><?php esc_html_e( '2 Stars (Budget)', 'maldives-packages' ); ?></option>
					<option value="1" <?php selected( $stars, 1 ); ?>><?php esc_html_e( '1 Star', 'maldives-packages' ); ?></option>
				</select>
			</div>
			<div>
				<label for="mpk_hotel_area" style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13px;">
					<?php esc_html_e( 'Area / Neighborhood', 'maldives-packages' ); ?>
				</label>
				<input type="text" name="mpk_hotel_area" id="mpk_hotel_area" value="<?php echo esc_attr( $area ); ?>" placeholder="<?php esc_attr_e( 'e.g. South Male Atoll, Beachfront, Sunset Side', 'maldives-packages' ); ?>" style="width: 100%; height: 38px; border-radius: 6px;" />
			</div>
		</div>

		<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px;">
			<div>
				<label for="mpk_hotel_review" style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13px;">
					<?php esc_html_e( 'Review Rating (1.0 to 5.0)', 'maldives-packages' ); ?>
				</label>
				<input type="number" step="0.1" min="1.0" max="5.0" name="mpk_hotel_review" id="mpk_hotel_review" value="<?php echo esc_attr( $review ); ?>" style="width: 100%; height: 38px; border-radius: 6px;" />
			</div>
			<div>
				<label for="mpk_hotel_review_label" style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13px;">
					<?php esc_html_e( 'Review Label', 'maldives-packages' ); ?>
				</label>
				<input type="text" name="mpk_hotel_review_label" id="mpk_hotel_review_label" value="<?php echo esc_attr( $review_label ); ?>" placeholder="<?php esc_attr_e( 'e.g. Exceptional, Excellent, Superb', 'maldives-packages' ); ?>" style="width: 100%; height: 38px; border-radius: 6px;" />
			</div>
		</div>

		<div>
			<label for="mpk_hotel_amenities" style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13px;">
				<?php esc_html_e( 'Property Amenities / Highlights (Comma-separated)', 'maldives-packages' ); ?>
			</label>
			<input type="text" name="mpk_hotel_amenities" id="mpk_hotel_amenities" value="<?php echo esc_attr( $amenities ); ?>" placeholder="<?php esc_attr_e( 'e.g. Pool, Wifi, Spa, Restaurant, Snorkeling, Airport Transfer', 'maldives-packages' ); ?>" style="width: 100%; height: 38px; border-radius: 6px;" />
			<p class="description" style="color: #64748b; font-size: 12px; margin-top: 4px;">
				<?php esc_html_e( 'These highlights will display as badges on the hotel card in Step 2 of the customer booking wizard.', 'maldives-packages' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the room repeater meta box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'mpk_save_hotel_rooms', 'mpk_hotel_rooms_nonce' );

		// Load existing rooms from post meta
		$rooms = get_post_meta( $post->ID, '_mpk_hotel_rooms', true );
		if ( empty( $rooms ) || ! is_array( $rooms ) ) {
			$rooms = get_post_meta( $post->ID, '_mpk_rooms', true );
		}

		// If new post or empty meta, try to match seeded hotel rooms by hotel ID or slug
		if ( empty( $rooms ) || ! is_array( $rooms ) ) {
			$hotel_id = get_post_meta( $post->ID, '_mpk_hotel_id', true );
			if ( empty( $hotel_id ) ) {
				$hotel_id = $post->post_name;
			}

			if ( ! empty( $hotel_id ) && class_exists( 'MPK_Data_Manager' ) ) {
				$seed_hotel = MPK_Data_Manager::get_hotel( $hotel_id );
				if ( ! empty( $seed_hotel['rooms'] ) && is_array( $seed_hotel['rooms'] ) ) {
					$rooms = $seed_hotel['rooms'];
				}
			}
		}

		if ( empty( $rooms ) || ! is_array( $rooms ) ) {
			$rooms = array(
				array(
					'id'        => 'r1',
					'name'      => '',
					'meal'      => 'Breakfast',
					'price'     => '',
					'room_type' => 'Balcony',
					'bed_type'  => 'Double',
					'amenities' => array(),
				),
			);
		}
		?>
		<div class="mpk-repeater-wrap" id="mpk-rooms-repeater-wrap">
			<p class="description" style="margin-bottom: 14px;">
				<?php esc_html_e( 'Configure room types, meal plans, bed configurations, and nightly pricing for this property. Changes will immediately sync to the frontend booking wizard.', 'maldives-packages' ); ?>
			</p>

			<div id="mpk-room-cards-container">
				<?php
				if ( ! empty( $rooms ) ) {
					foreach ( $rooms as $index => $room ) {
						$this->render_room_card( $index, $room );
					}
				}
				?>
			</div>

			<div style="margin-top: 16px;">
				<button type="button" class="mpk-btn-add-room" id="mpk-btn-add-room">
					<span class="dashicons dashicons-plus-alt2" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span>
					<?php esc_html_e( 'Add Another Room', 'maldives-packages' ); ?>
				</button>
			</div>
		</div>

		<!-- Template for dynamically adding new rooms -->
		<template id="mpk-room-card-template">
			<?php
			$this->render_room_card(
				'{{INDEX}}',
				array(
					'id'        => '',
					'name'      => '',
					'meal'      => 'Breakfast',
					'price'     => '',
					'room_type' => 'Balcony',
					'bed_type'  => 'Double',
					'amenities' => array(),
				)
			);
			?>
		</template>

		<script>
		(function() {
			var container = document.getElementById('mpk-room-cards-container');
			var addBtn = document.getElementById('mpk-btn-add-room');
			var template = document.getElementById('mpk-room-card-template');

			function updateIndexes() {
				var cards = container.querySelectorAll('.mpk-room-card');
				for (var i = 0; i < cards.length; i++) {
					var numBadge = cards[i].querySelector('.mpk-room-num');
					if (numBadge) numBadge.textContent = (i + 1);
				}
			}

			if (addBtn && template && container) {
				addBtn.addEventListener('click', function() {
					var nextIdx = Date.now();
					var html = template.innerHTML.replace(/{{INDEX}}/g, nextIdx);
					var tempDiv = document.createElement('div');
					tempDiv.innerHTML = html;
					var newCard = tempDiv.firstElementChild;
					container.appendChild(newCard);
					updateIndexes();

					// Bind remove event on new card
					bindRemoveEvent(newCard);

					// Focus name input
					var nameInput = newCard.querySelector('input[type="text"]');
					if (nameInput) nameInput.focus();
				});
			}

			function bindRemoveEvent(card) {
				var removeBtn = card.querySelector('.mpk-btn-remove-room');
				if (removeBtn) {
					removeBtn.addEventListener('click', function() {
						var roomNameInput = card.querySelector('.mpk-room-name-input');
						var val = roomNameInput ? roomNameInput.value.trim() : '';
						if (val && !confirm('Are you sure you want to remove "' + val + '"?')) {
							return;
						}
						card.remove();
						updateIndexes();
					});
				}

				// Live title update
				var nameInput = card.querySelector('.mpk-room-name-input');
				var headerTitle = card.querySelector('.mpk-room-title-text');
				if (nameInput && headerTitle) {
					nameInput.addEventListener('input', function() {
						headerTitle.textContent = this.value.trim() ? this.value.trim() : 'New Room';
					});
				}
			}

			// Bind remove on existing cards
			var existingCards = container.querySelectorAll('.mpk-room-card');
			for (var j = 0; j < existingCards.length; j++) {
				bindRemoveEvent(existingCards[j]);
			}
		})();
		</script>
		<?php
	}

	/**
	 * Render single room card markup.
	 *
	 * @param string|int $index Card index.
	 * @param array      $room  Room data array.
	 */
	private function render_room_card( $index, $room ) {
		$name      = isset( $room['name'] ) ? $room['name'] : '';
		$meal      = isset( $room['meal'] ) ? $room['meal'] : 'Breakfast';
		$price     = isset( $room['price'] ) ? $room['price'] : '';
		$room_id   = isset( $room['id'] ) ? $room['id'] : '';
		$amenities = isset( $room['amenities'] ) && is_array( $room['amenities'] ) ? implode( ', ', $room['amenities'] ) : ( isset( $room['amenities'] ) ? $room['amenities'] : '' );

		// Room Type detection / fallback
		$room_type = isset( $room['room_type'] ) ? $room['room_type'] : '';
		if ( empty( $room_type ) ) {
			$amenities_lower = strtolower( $amenities . ' ' . $name );
			if ( strpos( $amenities_lower, 'water villa' ) !== false || strpos( $amenities_lower, 'overwater' ) !== false ) {
				$room_type = 'Water Villa';
			} elseif ( strpos( $amenities_lower, 'pool' ) !== false ) {
				$room_type = 'With Pool';
			} elseif ( strpos( $amenities_lower, 'sea view' ) !== false || strpos( $amenities_lower, 'ocean' ) !== false ) {
				$room_type = 'Sea View';
			} elseif ( strpos( $amenities_lower, 'island view' ) !== false || strpos( $amenities_lower, 'garden' ) !== false || strpos( $amenities_lower, 'city' ) !== false ) {
				$room_type = 'Island View';
			} else {
				$room_type = 'Balcony';
			}
		}

		// Detect Bed Type from amenities or room data
		$bed_type = isset( $room['bed_type'] ) ? $room['bed_type'] : ( isset( $room['bed'] ) ? $room['bed'] : '' );
		if ( empty( $bed_type ) ) {
			$amenities_lower = strtolower( $amenities . ' ' . $name );
			if ( strpos( $amenities_lower, 'single' ) !== false ) {
				$bed_type = 'Single';
			} elseif ( strpos( $amenities_lower, 'twin' ) !== false ) {
				$bed_type = 'Twin';
			} elseif ( strpos( $amenities_lower, 'triple' ) !== false ) {
				$bed_type = 'Triple';
			} elseif ( strpos( $amenities_lower, 'king' ) !== false || strpos( $amenities_lower, 'master' ) !== false ) {
				$bed_type = 'King';
			} else {
				$bed_type = 'Double';
			}
		}

		$room_types = array(
			'Balcony'     => __( 'Balcony View', 'maldives-packages' ),
			'Sea View'    => __( 'Sea View', 'maldives-packages' ),
			'Island View' => __( 'Island View', 'maldives-packages' ),
			'With Pool'   => __( 'With Pool / Villa', 'maldives-packages' ),
			'Water Villa' => __( 'Water Villa', 'maldives-packages' ),
		);

		$meal_plans = array(
			'Breakfast'          => __( 'Breakfast Included', 'maldives-packages' ),
			'Breakfast & Dinner' => __( 'Breakfast & Dinner (Half Board)', 'maldives-packages' ),
			'Full Board'         => __( 'Full Board (All Meals)', 'maldives-packages' ),
			'All Inclusive'      => __( 'All Inclusive (Meals & Drinks)', 'maldives-packages' ),
			'Room Only'          => __( 'Room Only (No Meals)', 'maldives-packages' ),
		);

		$bed_types = array(
			'Double' => __( 'Double Bed', 'maldives-packages' ),
			'Twin'   => __( 'Twin Beds (2 Separate)', 'maldives-packages' ),
			'Single' => __( 'Single Bed', 'maldives-packages' ),
			'Triple' => __( 'Triple Beds', 'maldives-packages' ),
			'King'   => __( 'King Master Bed', 'maldives-packages' ),
		);
		?>
		<div class="mpk-room-card" data-index="<?php echo esc_attr( $index ); ?>">
			<input type="hidden" name="mpk_rooms[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $room_id ); ?>" />

			<div class="mpk-room-header">
				<div class="mpk-room-title">
					<span class="dashicons dashicons-admin-home" style="color: #0284c7;"></span>
					<span>Room #<span class="mpk-room-num"></span>:</span>
					<span class="mpk-room-title-text" style="color: #0284c7;"><?php echo esc_html( ! empty( $name ) ? $name : __( 'New Room', 'maldives-packages' ) ); ?></span>
				</div>
				<button type="button" class="mpk-btn-remove-room">
					&times; <?php esc_html_e( 'Remove Room', 'maldives-packages' ); ?>
				</button>
			</div>

			<div class="mpk-room-grid">
				<div class="mpk-field-group">
					<label><?php esc_html_e( 'Room / Villa Name *', 'maldives-packages' ); ?></label>
					<input type="text" name="mpk_rooms[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'e.g. Deluxe Sea View Villa', 'maldives-packages' ); ?>" class="mpk-room-name-input widefat" required />
				</div>

				<div class="mpk-field-group">
					<label><?php esc_html_e( 'Room Type', 'maldives-packages' ); ?></label>
					<select name="mpk_rooms[<?php echo esc_attr( $index ); ?>][room_type]">
						<?php foreach ( $room_types as $rt_val => $rt_label ) : ?>
							<option value="<?php echo esc_attr( $rt_val ); ?>" <?php selected( $room_type, $rt_val ); ?>><?php echo esc_html( $rt_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="mpk-field-group">
					<label><?php esc_html_e( 'Meal Plan', 'maldives-packages' ); ?></label>
					<select name="mpk_rooms[<?php echo esc_attr( $index ); ?>][meal]">
						<?php foreach ( $meal_plans as $m_val => $m_label ) : ?>
							<option value="<?php echo esc_attr( $m_val ); ?>" <?php selected( $meal, $m_val ); ?>><?php echo esc_html( $m_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="mpk-field-group">
					<label><?php esc_html_e( 'Bed Configuration', 'maldives-packages' ); ?></label>
					<select name="mpk_rooms[<?php echo esc_attr( $index ); ?>][bed_type]">
						<?php foreach ( $bed_types as $b_val => $b_label ) : ?>
							<option value="<?php echo esc_attr( $b_val ); ?>" <?php selected( $bed_type, $b_val ); ?>><?php echo esc_html( $b_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="mpk-field-group">
					<label><?php esc_html_e( 'Price / Night ($ USD) *', 'maldives-packages' ); ?></label>
					<input type="number" step="1" min="0" name="mpk_rooms[<?php echo esc_attr( $index ); ?>][price]" value="<?php echo esc_attr( $price ); ?>" placeholder="150" required />
				</div>
			</div>

			<div class="mpk-room-grid-full">
				<div class="mpk-field-group">
					<label><?php esc_html_e( 'Room Highlights / Amenities (Comma Separated)', 'maldives-packages' ); ?></label>
					<input type="text" name="mpk_rooms[<?php echo esc_attr( $index ); ?>][amenities]" value="<?php echo esc_attr( $amenities ); ?>" placeholder="<?php esc_attr_e( 'e.g. Balcony, Sea View, Double, Free Wifi', 'maldives-packages' ); ?>" class="widefat" />
					<span class="description" style="font-size: 11px; color: #64748b;"><?php esc_html_e( 'Keywords appear as badges in Step 2 of the customer booking wizard.', 'maldives-packages' ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save room repeater data when post is saved.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_rooms( $post_id, $post ) {
		// Autosave check
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// User permission check
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// 1. Save Hotel Overview & Star Rating if nonce present
		if ( isset( $_POST['mpk_hotel_details_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['mpk_hotel_details_nonce'] ), 'mpk_save_hotel_details' ) ) {
			$stars = isset( $_POST['mpk_hotel_stars'] ) ? absint( $_POST['mpk_hotel_stars'] ) : 4;
			if ( $stars < 1 || $stars > 5 ) {
				$stars = 4;
			}
			update_post_meta( $post_id, '_mpk_stars', $stars );

			$area = isset( $_POST['mpk_hotel_area'] ) ? sanitize_text_field( wp_unslash( $_POST['mpk_hotel_area'] ) ) : '';
			update_post_meta( $post_id, '_mpk_area', $area );

			$review = isset( $_POST['mpk_hotel_review'] ) ? floatval( $_POST['mpk_hotel_review'] ) : 4.7;
			update_post_meta( $post_id, '_mpk_review', $review );

			$review_label = isset( $_POST['mpk_hotel_review_label'] ) ? sanitize_text_field( wp_unslash( $_POST['mpk_hotel_review_label'] ) ) : 'Excellent';
			update_post_meta( $post_id, '_mpk_review_label', $review_label );

			$amenities_raw = isset( $_POST['mpk_hotel_amenities'] ) ? sanitize_text_field( wp_unslash( $_POST['mpk_hotel_amenities'] ) ) : '';
			$amenities_arr = array();
			if ( ! empty( $amenities_raw ) ) {
				$parts = explode( ',', $amenities_raw );
				foreach ( $parts as $p ) {
					$trimmed = trim( $p );
					if ( ! empty( $trimmed ) && ! in_array( $trimmed, $amenities_arr, true ) ) {
						$amenities_arr[] = $trimmed;
					}
				}
			}
			if ( empty( $amenities_arr ) ) {
				$amenities_arr = array( 'Pool', 'Wifi', 'Restaurant', 'Airport Transfer' );
			}
			update_post_meta( $post_id, '_mpk_amenities', $amenities_arr );
		}

		// 2. Save Room Repeater Data
		$sanitized_rooms = array();
		if ( isset( $_POST['mpk_hotel_rooms_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['mpk_hotel_rooms_nonce'] ), 'mpk_save_hotel_rooms' ) ) {
			if ( isset( $_POST['mpk_rooms'] ) && is_array( $_POST['mpk_rooms'] ) ) {
				$raw_rooms = wp_unslash( $_POST['mpk_rooms'] );
				$room_counter = 1;

				foreach ( $raw_rooms as $r ) {
					$name = isset( $r['name'] ) ? sanitize_text_field( trim( $r['name'] ) ) : '';
					if ( empty( $name ) ) {
						continue;
					}

					$price     = isset( $r['price'] ) ? floatval( $r['price'] ) : 0.0;
					$meal      = isset( $r['meal'] ) ? sanitize_text_field( $r['meal'] ) : 'Breakfast';
					$room_type = isset( $r['room_type'] ) ? sanitize_text_field( $r['room_type'] ) : 'Balcony';
					$bed_type  = isset( $r['bed_type'] ) ? sanitize_text_field( $r['bed_type'] ) : ( isset( $r['bed'] ) ? sanitize_text_field( $r['bed'] ) : 'Double' );

					$room_id = ! empty( $r['id'] ) ? sanitize_key( $r['id'] ) : 'r' . $room_counter;

					$amenities_raw = isset( $r['amenities'] ) ? sanitize_text_field( $r['amenities'] ) : '';
					$amenities_arr = array();
					if ( ! empty( $amenities_raw ) ) {
						$parts = explode( ',', $amenities_raw );
						foreach ( $parts as $p ) {
							$trimmed = trim( $p );
							if ( ! empty( $trimmed ) && ! in_array( $trimmed, $amenities_arr, true ) ) {
								$amenities_arr[] = $trimmed;
							}
						}
					}

					// Ensure bed_type and room_type are included in amenities array for backwards compatibility
					if ( ! empty( $bed_type ) && ! in_array( $bed_type, $amenities_arr, true ) ) {
						$amenities_arr[] = $bed_type;
					}
					if ( ! empty( $room_type ) && ! in_array( $room_type, $amenities_arr, true ) ) {
						$amenities_arr[] = $room_type;
					}

					$sanitized_rooms[] = array(
						'id'        => $room_id,
						'name'      => $name,
						'meal'      => $meal,
						'price'     => $price,
						'room_type' => $room_type,
						'bed_type'  => $bed_type,
						'amenities' => $amenities_arr,
					);

					$room_counter++;
				}
			}

			// Fallback standard room if no rooms configured by administrator
			if ( empty( $sanitized_rooms ) ) {
				$existing_rooms = get_post_meta( $post_id, '_mpk_hotel_rooms', true );
				if ( ! empty( $existing_rooms ) && is_array( $existing_rooms ) ) {
					$sanitized_rooms = $existing_rooms;
				} else {
					$sanitized_rooms = array(
						array(
							'id'        => 'r1',
							'name'      => __( 'Standard Deluxe Room', 'maldives-packages' ),
							'meal'      => 'Breakfast Included',
							'price'     => 140.00,
							'room_type' => 'Balcony',
							'bed_type'  => 'Double',
							'amenities' => array( 'Double', 'Balcony', 'Air Conditioning', 'Free Wifi' ),
						),
					);
				}
			}

			update_post_meta( $post_id, '_mpk_hotel_rooms', $sanitized_rooms );
			update_post_meta( $post_id, '_mpk_rooms', $sanitized_rooms );
		}

		// Ensure unique hotel ID meta
		$hotel_code = get_post_meta( $post_id, '_mpk_hotel_id', true );
		if ( empty( $hotel_code ) ) {
			$hotel_code = ! empty( $post->post_name ) ? $post->post_name : 'h-' . $post_id;
			update_post_meta( $post_id, '_mpk_hotel_id', $hotel_code );
		}

		// Always keep mpk_hotels option synchronized with the dynamic database state
		if ( class_exists( 'MPK_Data_Manager' ) ) {
			$all_hotels = MPK_Data_Manager::get_hotels();
			update_option( 'mpk_hotels', $all_hotels );
		}
	}
}
