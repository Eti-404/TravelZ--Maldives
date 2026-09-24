<?php
/**
 * Destination (mpk_destination) image field.
 *
 * Adds a "Destination Image" picker (WordPress Media Library) to the
 * Add / Edit Destination screens and an image column to the list table.
 * The image is stored as term meta `_mpk_destination_image_id` and used
 * on the location cards of the booking wizard.
 *
 * Approved Prefix: MPK / mpk_
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MPK_Destination_Meta {

	const TAXONOMY = 'mpk_destination';
	const META_KEY = '_mpk_destination_image_id';

	/**
	 * Constructor: register hooks.
	 */
	public function __construct() {
		add_action( self::TAXONOMY . '_add_form_fields', array( $this, 'render_add_field' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( $this, 'render_edit_field' ) );
		add_action( 'created_' . self::TAXONOMY, array( $this, 'save_image' ) );
		add_action( 'edited_' . self::TAXONOMY, array( $this, 'save_image' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'manage_edit-' . self::TAXONOMY . '_columns', array( $this, 'add_column' ) );
		add_filter( 'manage_' . self::TAXONOMY . '_custom_column', array( $this, 'render_column' ), 10, 3 );
	}

	/**
	 * Get the image URL for a destination term (or empty string).
	 *
	 * @param int    $term_id Term ID.
	 * @param string $size    Image size.
	 * @return string
	 */
	public static function get_image_url( $term_id, $size = 'large' ) {
		$image_id = (int) get_term_meta( $term_id, self::META_KEY, true );
		if ( ! $image_id ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $image_id, $size );
		return $url ? $url : '';
	}

	/**
	 * Load the media library only on destination screens.
	 *
	 * @param string $hook Admin hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || self::TAXONOMY !== $screen->taxonomy ) {
			return;
		}

		wp_enqueue_media();

		$js = <<<'JS'
(function ($) {
	function setImage($wrap, id, url) {
		$wrap.find('.mpk-dest-image-id').val(id || '');
		$wrap.find('.mpk-dest-image-preview').html(url ? '<img src="' + url + '" alt="" />' : '');
		$wrap.find('.mpk-dest-image-remove').toggle(!!id);
		$wrap.find('.mpk-dest-image-select').text(id ? $wrap.data('change') : $wrap.data('select'));
	}
	$(document).on('click', '.mpk-dest-image-select', function (e) {
		e.preventDefault();
		var $wrap = $(this).closest('.mpk-dest-image-field');
		var frame = wp.media({
			title: $wrap.data('title'),
			button: { text: $wrap.data('button') },
			library: { type: 'image' },
			multiple: false
		});
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var url = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
			setImage($wrap, a.id, url);
		});
		frame.open();
	});
	$(document).on('click', '.mpk-dest-image-remove', function (e) {
		e.preventDefault();
		setImage($(this).closest('.mpk-dest-image-field'), '', '');
	});
	// "Add New Destination" is saved via AJAX: reset the picker after a successful add.
	$(document).ajaxComplete(function (event, xhr, settings) {
		if (settings && typeof settings.data === 'string' && settings.data.indexOf('action=add-tag') !== -1 && xhr.responseText && xhr.responseText.indexOf('wp_error') === -1) {
			$('#addtag .mpk-dest-image-field').each(function () { setImage($(this), '', ''); });
		}
	});
})(jQuery);
JS;
		wp_add_inline_script( 'media-editor', $js );

		$css = '.mpk-dest-image-preview img{display:block;max-width:240px;height:auto;border-radius:10px;margin:0 0 10px;border:1px solid #dcdcde;}'
			. '.mpk-dest-image-field .button{margin-right:6px;}'
			. '.column-mpk_image{width:90px;}'
			. '.column-mpk_image img{width:72px;height:54px;object-fit:cover;border-radius:6px;display:block;}';
		wp_add_inline_style( 'common', $css );
	}

	/**
	 * Shared picker markup.
	 *
	 * @param int $image_id Current attachment ID.
	 */
	private function picker( $image_id ) {
		$url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<div class="mpk-dest-image-field"
			data-title="<?php esc_attr_e( 'Select Destination Image', 'maldives-packages' ); ?>"
			data-button="<?php esc_attr_e( 'Use this image', 'maldives-packages' ); ?>"
			data-select="<?php esc_attr_e( 'Select Image', 'maldives-packages' ); ?>"
			data-change="<?php esc_attr_e( 'Change Image', 'maldives-packages' ); ?>">
			<?php wp_nonce_field( 'mpk_save_destination_image', 'mpk_destination_image_nonce' ); ?>
			<input type="hidden" class="mpk-dest-image-id" name="mpk_destination_image_id" value="<?php echo esc_attr( $image_id ? $image_id : '' ); ?>" />
			<div class="mpk-dest-image-preview">
				<?php if ( $url ) : ?>
					<img src="<?php echo esc_url( $url ); ?>" alt="" />
				<?php endif; ?>
			</div>
			<button type="button" class="button mpk-dest-image-select">
				<?php echo $image_id ? esc_html__( 'Change Image', 'maldives-packages' ) : esc_html__( 'Select Image', 'maldives-packages' ); ?>
			</button>
			<button type="button" class="button-link button-link-delete mpk-dest-image-remove" <?php echo $image_id ? '' : 'style="display:none;"'; ?>>
				<?php esc_html_e( 'Remove', 'maldives-packages' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Field on "Add New Destination".
	 */
	public function render_add_field() {
		?>
		<div class="form-field term-mpk-image-wrap">
			<label><?php esc_html_e( 'Destination Image', 'maldives-packages' ); ?></label>
			<?php $this->picker( 0 ); ?>
			<p><?php esc_html_e( 'Shown on the location card in step 1 of the booking wizard. Landscape images (4:3) work best.', 'maldives-packages' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Field on "Edit Destination".
	 *
	 * @param WP_Term $term Term object.
	 */
	public function render_edit_field( $term ) {
		$image_id = (int) get_term_meta( $term->term_id, self::META_KEY, true );
		?>
		<tr class="form-field term-mpk-image-wrap">
			<th scope="row"><label><?php esc_html_e( 'Destination Image', 'maldives-packages' ); ?></label></th>
			<td>
				<?php $this->picker( $image_id ); ?>
				<p class="description"><?php esc_html_e( 'Shown on the location card in step 1 of the booking wizard. Landscape images (4:3) work best.', 'maldives-packages' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save / clear the image when a destination is created or edited.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_image( $term_id ) {
		if ( ! isset( $_POST['mpk_destination_image_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['mpk_destination_image_nonce'] ), 'mpk_save_destination_image' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$image_id = isset( $_POST['mpk_destination_image_id'] ) ? absint( $_POST['mpk_destination_image_id'] ) : 0;
		if ( $image_id && wp_attachment_is_image( $image_id ) ) {
			update_term_meta( $term_id, self::META_KEY, $image_id );
		} else {
			delete_term_meta( $term_id, self::META_KEY );
		}
	}

	/**
	 * Add image column to the destinations list.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'name' === $key ) {
				$new['mpk_image'] = __( 'Image', 'maldives-packages' );
			}
			$new[ $key ] = $label;
		}
		return $new;
	}

	/**
	 * Render image column.
	 *
	 * @param string $content Column content.
	 * @param string $column  Column name.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public function render_column( $content, $column, $term_id ) {
		if ( 'mpk_image' !== $column ) {
			return $content;
		}
		$url = self::get_image_url( $term_id, 'thumbnail' );
		return $url ? '<img src="' . esc_url( $url ) . '" alt="" />' : '<span aria-hidden="true">&mdash;</span>';
	}
}
