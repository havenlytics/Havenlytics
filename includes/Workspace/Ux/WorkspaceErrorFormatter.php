<?php
/**
 * Human-readable Workspace validation / error labels.
 *
 * @package HvnlyNab\Workspace\Ux
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Ux;

defined( 'ABSPATH' ) || exit;

/**
 * Converts internal field keys into Builder labels for user-facing messages.
 *
 * @since 3.2.0
 */
final class WorkspaceErrorFormatter {

	/**
	 * Core form keys → labels.
	 *
	 * @return array<string, string>
	 */
	public static function core_labels(): array {
		return array(
			'title'              => __( 'Title', 'havenlytics' ),
			'excerpt'            => __( 'Excerpt', 'havenlytics' ),
			'description'        => __( 'Description', 'havenlytics' ),
			'propertyType'       => __( 'Property type', 'havenlytics' ),
			'propertyDepartment' => __( 'Department', 'havenlytics' ),
			'propertyStatus'     => __( 'Status', 'havenlytics' ),
			'propertyFeaturesTax'=> __( 'Features', 'havenlytics' ),
			'propertyLocations'  => __( 'Locations', 'havenlytics' ),
			'propertyTags'       => __( 'Tags', 'havenlytics' ),
			'propertyBadges'     => __( 'Badges', 'havenlytics' ),
		);
	}

	/**
	 * Build name → label index from portal schema.
	 *
	 * @param array<string, mixed>|null $schema Portal schema.
	 * @return array<string, string>
	 */
	public static function label_index( ?array $schema ): array {
		$index = self::core_labels();
		if ( ! is_array( $schema ) ) {
			return $index;
		}

		foreach ( (array) ( $schema['tabs'] ?? array() ) as $tab ) {
			foreach ( (array) ( $tab['items'] ?? array() ) as $item ) {
				if ( ( $item['kind'] ?? '' ) === 'field' && ! empty( $item['field']['name'] ) ) {
					$name  = (string) $item['field']['name'];
					$label = (string) ( $item['field']['label'] ?? '' );
					if ( '' !== $label ) {
						$index[ $name ]           = $label;
						$index[ 'fields.' . $name ] = $label;
					}
				}
				if ( ( $item['kind'] ?? '' ) !== 'group' ) {
					continue;
				}
				foreach ( (array) ( $item['members'] ?? array() ) as $member ) {
					$name  = (string) ( $member['name'] ?? '' );
					$label = (string) ( $member['label'] ?? '' );
					if ( '' === $name || '' === $label ) {
						continue;
					}
					$index[ $name ]             = $label;
					$index[ 'fields.' . $name ] = $label;
				}
				$storage = (string) ( $item['storageName'] ?? '' );
				$gname   = (string) ( $item['groupName'] ?? '' );
				if ( '' !== $storage && '' !== $gname ) {
					$index[ $storage ]             = $gname;
					$index[ 'fields.' . $storage ] = $gname;
				}
			}
		}

		return $index;
	}

	/**
	 * Resolve a human label for an error key.
	 *
	 * @param string               $key   Error key (title | fields.meta…).
	 * @param array<string, string> $index Label index.
	 * @return string
	 */
	public static function label_for( string $key, array $index = array() ): string {
		if ( isset( $index[ $key ] ) && '' !== $index[ $key ] ) {
			return $index[ $key ];
		}

		$bare = 0 === strpos( $key, 'fields.' ) ? substr( $key, 7 ) : $key;
		if ( isset( $index[ $bare ] ) && '' !== $index[ $bare ] ) {
			return $index[ $bare ];
		}
		if ( isset( $index[ 'fields.' . $bare ] ) && '' !== $index[ 'fields.' . $bare ] ) {
			return $index[ 'fields.' . $bare ];
		}

		// Last resort: title-case trailing segment, never dump full storage id.
		$parts = explode( '_', $bare );
		$tail  = end( $parts );
		if ( is_string( $tail ) && '' !== $tail && ! is_numeric( $tail ) ) {
			return ucwords( str_replace( array( '-', '_' ), ' ', $tail ) );
		}

		return __( 'This field', 'havenlytics' );
	}

	/**
	 * Production-safe required message.
	 *
	 * @param string $label Field label.
	 * @return string
	 */
	public static function required_message( string $label ): string {
		return sprintf(
			/* translators: %s: field label */
			__( '%s is required.', 'havenlytics' ),
			$label
		);
	}

	/**
	 * Strip accidental meta / debug noise from a message.
	 *
	 * @param string $message Raw message.
	 * @return string
	 */
	public static function clean_message( string $message ): string {
		$message = trim( $message );
		if ( '' === $message ) {
			return __( 'This field needs attention.', 'havenlytics' );
		}

		// Drop pipe-delimited debug payloads from older clients.
		if ( false !== strpos( $message, ' | Meta:' ) || false !== strpos( $message, ' | Section:' ) ) {
			$parts = explode( ' | ', $message );
			return trim( (string) $parts[0] );
		}

		// Never echo raw fields.* keys as the whole message.
		if ( 0 === strpos( $message, 'fields.' ) || 0 === strpos( $message, '_hvnly_' ) ) {
			return __( 'This field needs attention.', 'havenlytics' );
		}

		return $message;
	}
}
