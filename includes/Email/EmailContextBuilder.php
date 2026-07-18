<?php
/**
 * Builds shared context and merge tags for Havenlytics emails.
 *
 * @package HvnlyNab\Email
 * @since   3.0.2
 */

namespace HvnlyNab\Email;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
final class EmailContextBuilder {

	/**
	 * Build context for property import success email.
	 *
	 * @param int      $import_count Number of properties imported.
	 * @param \WP_User $user         Admin user who ran the import.
	 * @return array<string, mixed>
	 */
	public static function build_import_success( int $import_count, \WP_User $user ): array {
		$properties_url = get_post_type_archive_link( 'hvnly_property' );
		if ( ! $properties_url ) {
			$properties_url = admin_url( 'edit.php?post_type=hvnly_property' );
		}

		$context = array(
			'email_type'     => EmailConstants::TYPE_PROPERTY_IMPORT_SUCCESS,
			'user_name'      => sanitize_text_field( $user->display_name ),
			'user_email'     => sanitize_email( $user->user_email ),
			'import_count'   => (string) max( 0, $import_count ),
			'properties_url' => esc_url_raw( $properties_url ),
			'site_name'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'       => home_url( '/' ),
			'admin_url'      => admin_url(),
		);

		$intro = EmailSettings::get_import_success_intro();
		if ( '' === trim( $intro ) ) {
			$intro = sprintf(
				/* translators: 1: site name, 2: property count */
				__( 'Your property setup on %1$s is complete. %2$s listings are ready to review.', 'havenlytics' ),
				(string) $context['site_name'],
				(string) $context['import_count']
			);
		}

		$context['intro'] = self::replace_merge_tags( $intro, $context );

		/**
		 * Filter property import success email context.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, mixed> $context      Email context.
		 * @param int                  $import_count Import count.
		 * @param \WP_User             $user         Recipient user.
		 */
		return apply_filters( 'hvnly_email_import_success_context', $context, $import_count, $user );
	}

	/**
	 * Replace {{merge_tags}} in a string.
	 *
	 * @param string               $text    Input string.
	 * @param array<string, mixed> $context Context array.
	 * @return string
	 */
	public static function replace_merge_tags( string $text, array $context ): string {
		if ( '' === trim( $text ) ) {
			return '';
		}

		$tags = self::merge_tag_map( $context );

		$replaced = preg_replace_callback(
			'/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
			static function ( array $matches ) use ( $tags ) {
				$key = strtolower( $matches[1] );

				return array_key_exists( $key, $tags ) ? (string) $tags[ $key ] : '';
			},
			$text
		);

		return is_string( $replaced ) ? trim( $replaced ) : trim( $text );
	}

	/**
	 * @param array<string, mixed> $context Email context.
	 * @return array<string, string>
	 */
	public static function merge_tag_map( array $context ): array {
		$map = array(
			'user_name'      => (string) ( $context['user_name'] ?? '' ),
			'user_email'     => (string) ( $context['user_email'] ?? '' ),
			'import_count'   => (string) ( $context['import_count'] ?? '' ),
			'properties_url' => (string) ( $context['properties_url'] ?? '' ),
			'site_name'      => (string) ( $context['site_name'] ?? '' ),
			'site_url'       => (string) ( $context['site_url'] ?? '' ),
			'admin_url'      => (string) ( $context['admin_url'] ?? '' ),
		);

		/**
		 * Filter Havenlytics email merge-tag map.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, string> $map     Tag key => replacement value.
		 * @param array<string, mixed>  $context Email context.
		 */
		return apply_filters( 'hvnly_email_merge_tags', $map, $context );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
