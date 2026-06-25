<?php
/**
 * FAQ group field handler — JSON repeater at {base}_faqs.
 *
 * @package HvnlyNab\Database\FieldTypes
 * @since   3.1.0
 */

namespace HvnlyNab\Database\FieldTypes;

defined( 'ABSPATH' ) || exit;

/**
 * FAQ accordion group field.
 */
class FAQField extends BaseFieldType {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'faq' );
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

		$field = $this->prepare_group_field( $field, 'FAQField' );

		$field_base = $this->get_field_base_name( $field );
		$field_name = $field_base . '_faqs';
		$items      = $this->get_stored_items( $post_id, $field, $field_name );

		if ( empty( $items ) ) {
			$items = array(
				array(
					'question' => '',
					'answer'   => '',
				),
			);
		}

		ob_start();
		?>
		<div class="hvnly-faq-field-container" data-field-name="<?php echo esc_attr( $field_name ); ?>" data-group-base-id="<?php echo esc_attr( $field_base ); ?>">
			<div class="hvnly-faq-field-header">
				<label class="hvnly-faq-field-label"><?php echo esc_html( $field['label'] ?? __( 'FAQ Items', 'havenlytics' ) ); ?></label>
				<p class="description"><?php esc_html_e( 'Add questions and answers. Drag to reorder.', 'havenlytics' ); ?></p>
			</div>
			<div class="hvnly-faq-repeater-items">
				<?php foreach ( $items as $index => $item ) : ?>
					<?php $this->render_item_row( $field_name, (int) $index, $item ); ?>
				<?php endforeach; ?>
			</div>
			<div class="hvnly-faq-repeater-actions">
				<button type="button" class="button hvnly-faq-add-item">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add FAQ', 'havenlytics' ); ?>
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

		$questions = filter_input( INPUT_POST, $field_name . '_questions', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
		$answers   = filter_input( INPUT_POST, $field_name . '_answers', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );

		$items = array();
		if ( is_array( $questions ) && is_array( $answers ) ) {
			$count = max( count( $questions ), count( $answers ) );
			for ( $i = 0; $i < $count; $i++ ) {
				$question = isset( $questions[ $i ] ) ? sanitize_text_field( (string) $questions[ $i ] ) : '';
				$answer   = isset( $answers[ $i ] ) ? sanitize_textarea_field( (string) $answers[ $i ] ) : '';
				if ( '' === $question && '' === $answer ) {
					continue;
				}
				$items[] = array(
					'question' => $question,
					'answer'   => $answer,
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
		$input_name = $field_base . '_faqs';
		$questions  = filter_input( INPUT_POST, $input_name . '_questions', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
		$has_item   = is_array( $questions ) && ! empty( array_filter( array_map( 'trim', array_map( 'strval', $questions ) ) ) );

		if ( ! $has_item ) {
			return new \WP_Error(
				'required_faq',
				sprintf(
					/* translators: %s: field label */
					__( 'At least one FAQ item is required for "%s".', 'havenlytics' ),
					esc_html( $field['label'] ?? __( 'FAQ', 'havenlytics' ) )
				)
			);
		}

		return true;
	}

	/**
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'hvnly-faq-field' );
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
        if ( false !== strpos( $field_name, '_faqs' ) ) {
			return str_replace( '_faqs', '', $field_name );
		}

		return $field_name ?: 'faq_' . time();
	}

	/**
	 * @param int    $post_id    Post ID.
	 * @param array  $field      Field config.
	 * @param string $field_name Meta key.
	 * @return array<int, array{question: string, answer: string}>
	 */
	private function get_stored_items( $post_id, $field, $field_name ) {
		$stored = $this->resolve_group_meta( (int) $post_id, array_merge(
			$field,
			array(
				'group_type' => $field['group_type'] ?? 'faq',
				'metaKey'    => 'faqs',
				'name'       => $field_name,
			)
		), $field_name, 'faqs' );

		if ( '' === $stored || false === $stored || null === $stored ) {
			$stored = get_post_meta( $post_id, $field_name, true );
		}

		return $this->decode_items( $stored );
	}

	/**
	 * @param mixed $stored Raw meta.
	 * @return array<int, array{question: string, answer: string}>
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
	 * @return array<int, array{question: string, answer: string}>
	 */
	private function normalize_items( array $items ) {
		$normalized = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$question = sanitize_text_field( (string) ( $item['question'] ?? '' ) );
			$answer   = sanitize_textarea_field( (string) ( $item['answer'] ?? '' ) );
			if ( '' === $question && '' === $answer ) {
				continue;
			}
			$normalized[] = array(
				'question' => $question,
				'answer'   => $answer,
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
		$question = $item['question'] ?? '';
		$answer   = $item['answer'] ?? '';
		?>
		<div class="hvnly-faq-repeater-item" data-item-index="<?php echo esc_attr( (string) $index ); ?>">
			<div class="hvnly-faq-item-header">
				<span class="hvnly-faq-drag-handle dashicons dashicons-menu" aria-hidden="true"></span>
				<strong class="hvnly-faq-item-title"><?php echo $question ? esc_html( $question ) : esc_html__( 'New FAQ', 'havenlytics' ); ?></strong>
			</div>
			<div class="hvnly-faq-item-body">
				<div class="hvnly-faq-field-row">
					<label for="<?php echo esc_attr( $field_name . '_question_' . $index ); ?>"><?php esc_html_e( 'Question', 'havenlytics' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $field_name . '_question_' . $index ); ?>" class="widefat hvnly-faq-question-input" name="<?php echo esc_attr( $field_name ); ?>_questions[]" value="<?php echo esc_attr( $question ); ?>" />
				</div>
				<div class="hvnly-faq-field-row">
					<label for="<?php echo esc_attr( $field_name . '_answer_' . $index ); ?>"><?php esc_html_e( 'Answer', 'havenlytics' ); ?></label>
					<textarea id="<?php echo esc_attr( $field_name . '_answer_' . $index ); ?>" class="widefat hvnly-faq-answer-input" rows="4" name="<?php echo esc_attr( $field_name ); ?>_answers[]"><?php echo esc_textarea( $answer ); ?></textarea>
				</div>
				<div class="hvnly-faq-item-footer">
					<button type="button" class="button hvnly-faq-remove-item"><?php esc_html_e( 'Remove', 'havenlytics' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}
}
