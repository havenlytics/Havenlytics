<?php
/**
 * URL Field Handler
 *
 * @package HvnlyNab\Database\FieldTypes
 * @since   3.1.0
 */

namespace HvnlyNab\Database\FieldTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Property URL field (virtual tour, brochure, external listing, etc.).
 */
class UrlField extends BaseFieldType {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'url' );
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

		$html = $this->render_label( $field );
		$html .= sprintf(
			'<input type="url" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s" class="hvnly__dyamic_metabox_tab__input widefat" data-field-type="url" %5$s />',
			esc_attr( $field_id ),
			esc_attr( $field_name ),
			esc_attr( (string) $value ),
			hvnly_esc_attr_ui( (string) ( $field['placeholder'] ?? '' ) ),
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
		update_post_meta( $post_id, $field_name, esc_url_raw( (string) $value ) );
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	public function sanitize( $value ) {
		return esc_url_raw( (string) $value );
	}

	/**
	 * @param mixed $value Value.
	 * @param array $field Field config.
	 * @return bool|\WP_Error
	 */
	public function validate( $value, $field ) {
		$url = trim( (string) $value );

		if ( ! empty( $field['is_required'] ) && '' === $url ) {
			return new \WP_Error(
				'required_url',
				sprintf(
					/* translators: %s: field label */
					__( 'The field "%s" is required.', 'havenlytics' ),
					hvnly_esc_html_ui( (string) ( ! empty( $field['label'] ) ? $field['label'] : 'URL' ) )
				)
			);
		}

		if ( '' !== $url && ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error(
				'invalid_url',
				sprintf(
					/* translators: %s: field label */
					__( 'Please enter a valid URL for "%s".', 'havenlytics' ),
					hvnly_esc_html_ui( (string) ( ! empty( $field['label'] ) ? $field['label'] : 'URL' ) )
				)
			);
		}

		return true;
	}
}
