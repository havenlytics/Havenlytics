<?php
/**
 * Workspace validation UX helpers (normalize / group — not rule engine).
 *
 * Does not replace PropertyDraftValidator rules. Formats messages for clients.
 *
 * @package HvnlyNab\Workspace\Ux
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Ux;

use HvnlyNab\Workspace\Security\WorkspaceSecurity;

defined( 'ABSPATH' ) || exit;

/**
 * Normalize validation payloads for Workspace UI.
 *
 * @since 3.2.0
 */
final class WorkspaceValidationService {

	/**
	 * User-facing summary when validation fails.
	 *
	 * @param int $count Error count.
	 * @return string
	 */
	public static function summary_message( int $count ): string {
		if ( $count <= 0 ) {
			return __( 'Please complete the highlighted fields.', 'havenlytics' );
		}
		return sprintf(
			/* translators: %d: number of fields */
			_n(
				'%d required field needs attention.',
				'%d required fields need attention.',
				$count,
				'havenlytics'
			),
			$count
		);
	}

	/**
	 * Normalize a fields map for API / SPA consumption.
	 *
	 * @param array<string, string>     $fields Raw field errors.
	 * @param array<string, mixed>|null $schema Portal schema.
	 * @return array{messages: array<string, string>, diagnostics?: array<string, array<string, mixed>>}
	 */
	public static function normalize_field_errors( array $fields, ?array $schema = null ): array {
		$index    = WorkspaceErrorFormatter::label_index( $schema );
		$messages = array();
		$debug    = array();

		foreach ( $fields as $key => $message ) {
			$key   = (string) $key;
			$label = WorkspaceErrorFormatter::label_for( $key, $index );
			$clean = WorkspaceErrorFormatter::clean_message( (string) $message );
			// Prefer label-based required phrasing when message is generic/noisy.
			if ( self::looks_like_required( $clean ) ) {
				$clean = WorkspaceErrorFormatter::required_message( $label );
			}
			$messages[ $key ] = $clean;

			if ( WorkspaceSecurity::is_debug() ) {
				$debug[ $key ] = array(
					'label'   => $label,
					'key'     => $key,
					'raw'     => (string) $message,
					'message' => $clean,
				);
			}
		}

		$out = array( 'messages' => $messages );
		if ( ! empty( $debug ) ) {
			$out['diagnostics'] = $debug;
		}
		return $out;
	}

	/**
	 * @param string $message Message.
	 * @return bool
	 */
	private static function looks_like_required( string $message ): bool {
		return (bool) preg_match( '/is required\.?$/i', $message );
	}
}
