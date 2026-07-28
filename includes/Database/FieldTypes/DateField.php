<?php
/**
 * Date Field Handler
 *
 * @package HvnlyNab\Database\FieldTypes
 * @since   3.1.0
 */

namespace HvnlyNab\Database\FieldTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Property date field stored as Y-m-d.
 */
class DateField extends BaseFieldType {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'date' );
	}

	/**
	 * @param array $field   Field config.
	 * @param mixed $value   Current value.
	 * @param int   $post_id Post ID.
	 * @return string
	 */
	public function render( $field, $value, $post_id ) {
		unset( $post_id );

		$field_name  = $field['name'] ?? $field['fieldid'] ?? '';
		$field_id    = $field['fieldid'] ?? $field_name;
		$is_required = ! empty( $field['is_required'] );
		$stored      = $this->normalize_date_value( $value );

		$html = $this->render_label( $field );
		$html .= sprintf(
			'<input type="date" id="%1$s" name="%2$s" value="%3$s" class="hvnly__dyamic_metabox_tab__input widefat" data-field-type="date" %4$s />',
			esc_attr( $field_id ),
			esc_attr( $field_name ),
			esc_attr( $stored ),
			$is_required ? 'required' : ''
		);
		$html .= $this->render_description( $field );

		return $html;
	}

	/**
	 * @param int    $post_id    Post ID.
	 * @param string $field_name Meta key.
	 * @param mixed  $value      Raw value.
	 * @param mixed  $extra      Unused.
	 * @return void
	 */
	public function save( $post_id, $field_name, $value, $extra = null ) {
		unset( $extra );
		update_post_meta( $post_id, $field_name, $this->normalize_date_value( $value ) );
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	public function sanitize( $value ) {
		return $this->normalize_date_value( $value );
	}

	/**
	 * @param mixed $value Value.
	 * @param array $field Field config.
	 * @return bool|\WP_Error
	 */
	public function validate( $value, $field ) {
		$date = $this->normalize_date_value( $value );

		if ( ! empty( $field['is_required'] ) && '' === $date ) {
			return new \WP_Error(
				'required_date',
				sprintf(
					/* translators: %s: field label */
					__( 'The field "%s" is required.', 'havenlytics' ),
					hvnly_esc_html_ui( (string) ( ! empty( $field['label'] ) ? $field['label'] : 'Date' ) )
				)
			);
		}

		if ( '' !== $date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new \WP_Error(
				'invalid_date',
				sprintf(
					/* translators: %s: field label */
					__( 'Please enter a valid date for "%s".', 'havenlytics' ),
					hvnly_esc_html_ui( (string) ( ! empty( $field['label'] ) ? $field['label'] : 'Date' ) )
				)
			);
		}

		return true;
	}

	/**
	 * Normalize to Y-m-d or empty string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function normalize_date_value( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d', $timestamp );
	}
}
