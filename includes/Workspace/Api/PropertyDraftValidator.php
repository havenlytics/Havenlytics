<?php
/**
 * Server-side Property Form validation (Builder schema + core listing fields).
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Workspace\Ux\WorkspaceValidationService;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Draft property form validator.
 *
 * @since 3.2.0
 */
final class PropertyDraftValidator {

	/**
	 * Validate form values against Property Builder required flags + core fields.
	 *
	 * @param array<string, mixed> $values Form values.
	 * @return true|WP_Error
	 */
	public static function validate( array $values ) {
		$errors = array();

		$title = isset( $values['title'] ) ? trim( (string) $values['title'] ) : '';
		if ( '' === $title ) {
			$errors['title'] = __( 'Title is required.', 'havenlytics' );
		}

		$type = isset( $values['propertyType'] ) ? trim( (string) $values['propertyType'] ) : '';
		if ( '' === $type ) {
			$errors['propertyType'] = __( 'Property type is required.', 'havenlytics' );
		}

		$fields = isset( $values['fields'] ) && is_array( $values['fields'] ) ? $values['fields'] : array();
		$schema = PropertyBuilderSchemaService::get_portal_schema();

		foreach ( PropertyBuilderSchemaService::required_field_map( $schema ) as $name => $label ) {
			$raw   = array_key_exists( $name, $fields ) ? $fields[ $name ] : null;
			$empty = self::is_empty_value( $raw );
			if ( $empty ) {
				$detail                      = self::describe_field( $schema, $name, $label, $raw );
				$errors[ 'fields.' . $name ] = sprintf(
					/* translators: %s: field label */
					__( '%s is required.', 'havenlytics' ),
					$label
				);
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log(
						sprintf(
							'[hvnly_property_validation] field=%s meta=%s section=%s group=%s required=1 value=%s reason=empty',
							$detail['label'],
							$detail['metaKey'],
							$detail['section'],
							$detail['group'],
							wp_json_encode( $raw )
						)
					);
				}
			}
		}

		// Soft check numeric price when present as plain number.
		if ( isset( $fields['_hvnly_property_price'] ) ) {
			$price = $fields['_hvnly_property_price'];
			if ( is_string( $price ) && '' !== trim( $price ) && '{' !== $price[0] ) {
				$normalized = str_replace( ',', '', $price );
				if ( is_numeric( $normalized ) && (float) $normalized < 0 ) {
					$errors['fields._hvnly_property_price'] = __( 'Enter a valid price.', 'havenlytics' );
				}
			}
		}

		if ( empty( $errors ) ) {
			return true;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[hvnly_property_validation] failed fields=' . wp_json_encode( $errors ) );
		}

		return new WP_Error(
			'hvnly_property_validation_failed',
			WorkspaceValidationService::summary_message( count( $errors ) ),
			array(
				'status' => 400,
				'fields' => $errors,
			)
		);
	}

	/**
	 * Locate builder context for a failing field (debug / API detail).
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @param string               $name   Meta / field name.
	 * @param string               $label  Label.
	 * @param mixed                $raw    Current value.
	 * @return array<string, string>
	 */
	private static function describe_field( array $schema, string $name, string $label, $raw ): array {
		$out = array(
			'label'   => $label,
			'metaKey' => $name,
			'section' => '',
			'group'   => '',
		);

		foreach ( (array) ( $schema['tabs'] ?? array() ) as $tab ) {
			$section = (string) ( $tab['label'] ?? $tab['id'] ?? '' );
			foreach ( (array) ( $tab['items'] ?? array() ) as $item ) {
				if ( ( $item['kind'] ?? '' ) === 'field' && (string) ( $item['field']['name'] ?? '' ) === $name ) {
					$out['section'] = $section;
					return $out;
				}
				if ( ( $item['kind'] ?? '' ) === 'group' ) {
					foreach ( (array) ( $item['members'] ?? array() ) as $member ) {
						if ( (string) ( $member['name'] ?? '' ) === $name ) {
							$out['section'] = $section;
							$out['group']   = (string) ( $item['groupName'] ?? $item['groupType'] ?? '' );
							return $out;
						}
					}
				}
			}
		}

		return $out;
	}

	/**
	 * @param mixed $raw Value.
	 * @return bool
	 */
	private static function is_empty_value( $raw ): bool {
		if ( null === $raw ) {
			return true;
		}
		if ( is_string( $raw ) ) {
			return '' === trim( $raw );
		}
		if ( is_array( $raw ) ) {
			return empty( $raw );
		}
		if ( is_bool( $raw ) ) {
			return false;
		}
		return '' === (string) $raw;
	}
}
