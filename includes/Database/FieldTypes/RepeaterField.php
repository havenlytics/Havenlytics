<?php
/**
 * Repeater group field handler — JSON rows at {base}_items.
 *
 * @package HvnlyNab\Database\FieldTypes
 * @since   3.1.0
 */

namespace HvnlyNab\Database\FieldTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Title / value / optional icon repeater group field.
 */
class RepeaterField extends BaseFieldType {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'repeater' );
		$this->requires_assets = true;
	}

	/**
	 * @param array $field   Field config.
	 * @param mixed $value   Unused.
	 * @param int   $post_id Post ID.
	 * @return string
	 */
	public function render( $field, $value, $post_id ) {
		unset( $value );

		$field = $this->prepare_group_field( $field, 'RepeaterField' );

		$field_base = $this->get_field_base_name( $field );
		$field_name = $field_base . '_items';
		$items      = $this->get_stored_items( $post_id, $field, $field_name );

		if ( empty( $items ) ) {
			$items = array(
				array(
					'title' => '',
					'value' => '',
					'icon'  => '',
				),
			);
		}

		ob_start();
		?>
		<div class="hvnly-repeater-field-container" data-field-name="<?php echo esc_attr( $field_name ); ?>" data-group-base-id="<?php echo esc_attr( $field_base ); ?>">
			<div class="hvnly-repeater-field-header">
				<label class="hvnly-repeater-field-label"><?php echo esc_html( hvnly_translate_ui( (string) ( ! empty( $field['label'] ) ? $field['label'] : 'Repeater Items' ) ) ); ?></label>
				<p class="description"><?php esc_html_e( 'Add rows with a title and value. Drag to reorder.', 'havenlytics' ); ?></p>
			</div>
			<div class="hvnly-repeater-items">
				<?php foreach ( $items as $index => $item ) : ?>
					<?php $this->render_item_row( $field_name, (int) $index, $item ); ?>
				<?php endforeach; ?>
			</div>
			<div class="hvnly-repeater-actions">
				<button type="button" class="button hvnly-repeater-add-item">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add Row', 'havenlytics' ); ?>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * @param int    $post_id    Post ID.
	 * @param string $field_name Meta key.
	 * @param mixed  $value      Unused.
	 * @param mixed  $extra      Unused.
	 * @return void
	 */
	public function save( $post_id, $field_name, $value = null, $extra = null ) {
		unset( $value, $extra );

		$titles = filter_input( INPUT_POST, $field_name . '_titles', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
		$values = filter_input( INPUT_POST, $field_name . '_values', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
		$icons  = filter_input( INPUT_POST, $field_name . '_icons', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );

		$items = array();
		if ( is_array( $titles ) && is_array( $values ) ) {
			$count = max( count( $titles ), count( $values ) );
			for ( $i = 0; $i < $count; $i++ ) {
				$title = isset( $titles[ $i ] ) ? sanitize_text_field( (string) $titles[ $i ] ) : '';
				$row   = isset( $values[ $i ] ) ? sanitize_text_field( (string) $values[ $i ] ) : '';
				$icon  = ( is_array( $icons ) && isset( $icons[ $i ] ) ) ? sanitize_text_field( (string) $icons[ $i ] ) : '';
				if ( '' === $title && '' === $row ) {
					continue;
				}
				$items[] = array(
					'title' => $title,
					'value' => $row,
					'icon'  => $icon,
				);
			}
		}

		if ( ! empty( $items ) ) {
			update_post_meta( $post_id, $field_name, wp_json_encode( $items ) );
		} else {
			if ( function_exists( 'hvnly_safe_delete_post_meta' ) ) {
				hvnly_safe_delete_post_meta( $post_id, $field_name, 'user_save_empty' );
			} else {
				delete_post_meta( $post_id, $field_name );
			}
		}
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	public function sanitize( $value ) {
		if ( is_array( $value ) ) {
			return wp_json_encode( $value );
		}
		return is_string( $value ) ? $value : '';
	}

	/**
	 * @param mixed $value Value.
	 * @param array $field Field config.
	 * @return bool|\WP_Error
	 */
	public function validate( $value, $field ) {
		if ( empty( $field['is_required'] ) ) {
			return true;
		}

		$field_base = $this->get_field_base_name( $field );
		$input_name = $field_base . '_items';
		$titles     = filter_input( INPUT_POST, $input_name . '_titles', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
		$has_item   = is_array( $titles ) && ! empty( array_filter( array_map( 'trim', array_map( 'strval', $titles ) ) ) );

		if ( ! $has_item ) {
			return new \WP_Error(
				'required_repeater',
				sprintf(
					/* translators: %s: field label */
					__( 'At least one row is required for "%s".', 'havenlytics' ),
					hvnly_esc_html_ui( (string) ( ! empty( $field['label'] ) ? $field['label'] : 'Repeater' ) )
				)
			);
		}

		return true;
	}

	/**
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'hvnly-repeater-field' );
		wp_enqueue_style( 'hvnly-document-field' );
	}

	/**
	 * @param array $field Field config.
	 * @return string
	 */
	private function get_field_base_name( $field ) {
		if ( ! empty( $field['group_base_id'] ) ) {
			return (string) $field['group_base_id'];
		}

		$field_name = $field['name'] ?? $field['id'] ?? '';
        if ( false !== strpos( $field_name, '_items' ) ) {
			return str_replace( '_items', '', $field_name );
		}

		return $field_name ?: 'repeater_' . time();
	}

	/**
	 * @param int    $post_id    Post ID.
	 * @param array  $field      Field config.
	 * @param string $field_name Meta key.
	 * @return array<int, array{title: string, value: string, icon: string}>
	 */
	private function get_stored_items( $post_id, $field, $field_name ) {
		$stored = $this->resolve_group_meta( (int) $post_id, array_merge(
			$field,
			array(
				'group_type' => $field['group_type'] ?? 'repeater',
				'metaKey'    => 'items',
				'name'       => $field_name,
			)
		), $field_name, 'items' );

		if ( '' === $stored || false === $stored || null === $stored ) {
			$stored = get_post_meta( $post_id, $field_name, true );
		}

		return $this->decode_items( $stored );
	}

	/**
	 * @param mixed $stored Raw meta.
	 * @return array<int, array{title: string, value: string, icon: string}>
	 */
	private function decode_items( $stored ) {
		if ( is_array( $stored ) ) {
			return $this->normalize_items( $stored );
		}

		if ( is_string( $stored ) && '' !== $stored ) {
			$decoded = json_decode( $stored, true );
			if ( is_array( $decoded ) ) {
				return $this->normalize_items( $decoded );
			}
		}

		return array();
	}

	/**
	 * @param array $items Raw items.
	 * @return array<int, array{title: string, value: string, icon: string}>
	 */
	private function normalize_items( array $items ) {
		$normalized = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$value = sanitize_text_field( (string) ( $item['value'] ?? '' ) );
			$icon  = sanitize_text_field( (string) ( $item['icon'] ?? '' ) );
			if ( '' === $title && '' === $value ) {
				continue;
			}
			$normalized[] = array(
				'title' => $title,
				'value' => $value,
				'icon'  => $icon,
			);
		}
		return $normalized;
	}

	/**
	 * @param string               $field_name Field name prefix.
	 * @param int                  $index      Row index.
	 * @param array<string, string> $item      Row data.
	 * @return void
	 */
	private function render_item_row( $field_name, $index, $item ) {
		$title = $item['title'] ?? '';
		$value = $item['value'] ?? '';
		$icon  = $item['icon'] ?? '';
		?>
		<div class="hvnly-repeater-item" data-item-index="<?php echo esc_attr( (string) $index ); ?>">
			<div class="hvnly-repeater-item-header">
				<span class="hvnly-repeater-drag-handle dashicons dashicons-menu" aria-hidden="true"></span>
						<strong class="hvnly-repeater-item-title">
					<?php if ( ! empty( $icon ) ) : ?>
						<i class="fas fa-<?php echo esc_attr( $this->normalize_icon_name( (string) $icon ) ); ?>" aria-hidden="true"></i>
					<?php endif; ?>
					<?php echo $title ? esc_html( $title ) : esc_html__( 'New Row', 'havenlytics' ); ?>
				</strong>
			</div>
			<div class="hvnly-repeater-item-body">
				<div class="hvnly-repeater-field-row">
					<label for="<?php echo esc_attr( $field_name . '_title_' . $index ); ?>"><?php esc_html_e( 'Title', 'havenlytics' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $field_name . '_title_' . $index ); ?>" class="widefat hvnly-repeater-title-input" name="<?php echo esc_attr( $field_name ); ?>_titles[]" value="<?php echo esc_attr( $title ); ?>" />
				</div>
				<div class="hvnly-repeater-field-row">
					<label for="<?php echo esc_attr( $field_name . '_value_' . $index ); ?>"><?php esc_html_e( 'Value', 'havenlytics' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $field_name . '_value_' . $index ); ?>" class="widefat hvnly-repeater-value-input" name="<?php echo esc_attr( $field_name ); ?>_values[]" value="<?php echo esc_attr( $value ); ?>" />
				</div>
				<div class="hvnly-repeater-field-row hvnly-repeater-field-row--icon">
					<label><?php esc_html_e( 'Icon', 'havenlytics' ); ?> <span class="description"><?php esc_html_e( '(optional)', 'havenlytics' ); ?></span></label>
					<div class="hvnly-document-icon-field">
						<input type="text" class="hvnly-document-icon-input widefat hvnly-repeater-icon-input" name="<?php echo esc_attr( $field_name ); ?>_icons[]" value="<?php echo esc_attr( $icon ); ?>" placeholder="<?php esc_attr_e( 'school', 'havenlytics' ); ?>" />
						<div class="hvnly-document-icon-preview">
							<?php if ( ! empty( $icon ) ) : ?>
								<i class="fas fa-<?php echo esc_attr( $this->normalize_icon_name( (string) $icon ) ); ?>"></i>
							<?php endif; ?>
						</div>
						<button type="button" class="button hvnly-document-icon-selector">
							<?php esc_html_e( 'Select Icon', 'havenlytics' ); ?>
						</button>
					</div>
				</div>
				<div class="hvnly-repeater-item-footer">
					<button type="button" class="button hvnly-repeater-remove-item"><?php esc_html_e( 'Remove', 'havenlytics' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Normalize a Font Awesome icon name for CSS class output.
	 *
	 * Strips a literal "fa-" prefix only. Do not use ltrim( $icon, 'fa-' ):
	 * PHP treats the 2nd argument as a character mask and corrupts names like "file-pdf".
	 * Stored meta values are never modified.
	 *
	 * @param string $icon Raw icon value from meta/UI.
	 * @return string Bare icon name suitable for "fas fa-{name}".
	 */
	private function normalize_icon_name( $icon ) {
		$icon = trim( (string) $icon );
		if ( '' === $icon ) {
			return '';
		}

		if ( 0 === strpos( $icon, 'fa-' ) ) {
			return substr( $icon, 3 );
		}

		return $icon;
	}
}
