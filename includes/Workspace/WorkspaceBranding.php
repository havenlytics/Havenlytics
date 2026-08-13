<?php
/**
 * Site-wide Workspace white-label branding storage.
 *
 * @package HvnlyNab\Workspace
 * @since   3.7.4
 */

namespace HvnlyNab\Workspace;

defined( 'ABSPATH' ) || exit;

/**
 * Read / write / validate Workspace Branding (Pro-gated in UI).
 *
 * Stored as a dedicated site option — not per-agent prefs.
 *
 * @since 3.7.4
 */
final class WorkspaceBranding {

	/**
	 * Option key.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'hvnly_workspace_branding';

	/**
	 * Default empty branding.
	 *
	 * @return array{
	 *   dashboardName:string,
	 *   dashboardLogoId:int,
	 *   faviconId:int,
	 *   loginLogoId:int,
	 *   footerBranding:string
	 * }
	 */
	public static function defaults(): array {
		return array(
			'dashboardName'   => '',
			'dashboardLogoId' => 0,
			'faviconId'       => 0,
			'loginLogoId'     => 0,
			'footerBranding'  => '',
		);
	}

	/**
	 * Raw stored values (ids + strings only).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_raw(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$out                    = self::defaults();
		$out['dashboardName']   = sanitize_text_field( (string) ( $stored['dashboardName'] ?? '' ) );
		$out['dashboardLogoId'] = absint( $stored['dashboardLogoId'] ?? 0 );
		$out['faviconId']       = absint( $stored['faviconId'] ?? 0 );
		$out['loginLogoId']     = absint( $stored['loginLogoId'] ?? 0 );
		$out['footerBranding']  = sanitize_textarea_field( (string) ( $stored['footerBranding'] ?? '' ) );

		return $out;
	}

	/**
	 * Public payload for REST + localize (includes resolved URLs).
	 *
	 * @param int|null $user_id Current user for canEdit (null = guest/read-only).
	 * @return array<string, mixed>
	 */
	public static function get_public( ?int $user_id = null ): array {
		$raw = self::get_raw();

		$dashboard_logo = self::attachment_payload( (int) $raw['dashboardLogoId'] );
		$favicon        = self::attachment_payload( (int) $raw['faviconId'] );
		$login_logo     = self::attachment_payload( (int) $raw['loginLogoId'] );

		$uid = null !== $user_id ? absint( $user_id ) : get_current_user_id();

		return array(
			'dashboardName'      => (string) $raw['dashboardName'],
			'dashboardLogoId'    => (int) $raw['dashboardLogoId'],
			'dashboardLogoUrl'   => (string) ( $dashboard_logo['url'] ?? '' ),
			'faviconId'          => (int) $raw['faviconId'],
			'faviconUrl'         => (string) ( $favicon['url'] ?? '' ),
			'loginLogoId'        => (int) $raw['loginLogoId'],
			'loginLogoUrl'       => (string) ( $login_logo['url'] ?? '' ),
			'footerBranding'     => (string) $raw['footerBranding'],
			'canEdit'            => self::can_edit( $uid ),
			'maxUploadBytes'     => (int) wp_max_upload_size(),
			'recommended'        => array(
				'dashboardLogo' => array(
					'size'   => '240×64',
					'format' => 'PNG or SVG',
					'hint'   => __( 'Recommended 240×64px. PNG or SVG. Keep under the site upload limit.', 'havenlytics' ),
				),
				'favicon'       => array(
					'size'   => '32×32 or 512×512',
					'format' => 'PNG or ICO',
					'hint'   => __( 'Recommended 32×32 or 512×512. PNG or ICO.', 'havenlytics' ),
				),
				'loginLogo'     => array(
					'size'   => '320×120',
					'format' => 'PNG or SVG',
					'hint'   => __( 'Recommended 320×120px. PNG or SVG.', 'havenlytics' ),
				),
			),
		);
	}

	/**
	 * Whether the user may change site branding.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_edit( int $user_id ): bool {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 || ! WorkspaceProCapabilities::is_branding_enabled() ) {
			return false;
		}

		$allowed = user_can( $user_id, 'manage_options' )
			|| user_can( $user_id, 'upload_files' );

		/**
		 * Filter whether a user may edit Workspace Branding.
		 *
		 * @since 3.7.4
		 *
		 * @param bool $allowed Default capability result.
		 * @param int  $user_id User ID.
		 */
		return (bool) apply_filters( 'hvnly_workspace_can_edit_branding', $allowed, $user_id );
	}

	/**
	 * Validate branding input without writing.
	 *
	 * @param array<string, mixed> $input Branding section.
	 * @return array<string, mixed>|\WP_Error Normalized storage row or error with fields.
	 */
	public static function validate_payload( array $input ) {
		$current = self::get_raw();
		$errors  = array();
		$next    = $current;

		if ( array_key_exists( 'dashboardName', $input ) ) {
			$name = sanitize_text_field( (string) $input['dashboardName'] );
			if ( strlen( $name ) > 80 ) {
				$errors['branding.dashboardName'] = __( 'Dashboard name must be 80 characters or fewer.', 'havenlytics' );
			} else {
				$next['dashboardName'] = $name;
			}
		}

		if ( array_key_exists( 'footerBranding', $input ) ) {
			$footer = sanitize_textarea_field( (string) $input['footerBranding'] );
			if ( strlen( $footer ) > 240 ) {
				$errors['branding.footerBranding'] = __( 'Footer branding must be 240 characters or fewer.', 'havenlytics' );
			} else {
				$next['footerBranding'] = $footer;
			}
		}

		$id_fields = array(
			'dashboardLogoId' => 'branding.dashboardLogoId',
			'faviconId'       => 'branding.faviconId',
			'loginLogoId'     => 'branding.loginLogoId',
		);

		foreach ( $id_fields as $key => $error_key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$id = absint( $input[ $key ] );
			if ( $id <= 0 ) {
				$next[ $key ] = 0;
				continue;
			}
			$check = self::validate_image_attachment( $id, $key );
			if ( is_wp_error( $check ) ) {
				$errors[ $error_key ] = $check->get_error_message();
			} else {
				$next[ $key ] = $id;
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error(
				'hvnly_branding_invalid',
				__( 'Please fix the highlighted branding fields.', 'havenlytics' ),
				array(
					'status' => 400,
					'fields' => $errors,
				)
			);
		}

		return $next;
	}

	/**
	 * Persist a previously validated branding row.
	 *
	 * @param array<string, mixed> $next    Storage row.
	 * @param int                  $user_id Actor.
	 * @return array<string, mixed> Public payload.
	 */
	public static function persist( array $next, int $user_id ): array {
		$defaults = self::defaults();
		$store    = array(
			'dashboardName'   => sanitize_text_field( (string) ( $next['dashboardName'] ?? $defaults['dashboardName'] ) ),
			'dashboardLogoId' => absint( $next['dashboardLogoId'] ?? 0 ),
			'faviconId'       => absint( $next['faviconId'] ?? 0 ),
			'loginLogoId'     => absint( $next['loginLogoId'] ?? 0 ),
			'footerBranding'  => sanitize_textarea_field( (string) ( $next['footerBranding'] ?? '' ) ),
		);

		update_option( self::OPTION_KEY, $store, false );

		/**
		 * Fires after Workspace branding is saved.
		 *
		 * @since 3.7.4
		 *
		 * @param array<string, mixed> $store   Stored branding.
		 * @param int                  $user_id Actor.
		 */
		do_action( 'hvnly_workspace_branding_saved', $store, $user_id );

		return self::get_public( $user_id );
	}

	/**
	 * Validate + persist branding from a settings PUT payload section.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $input   Branding section.
	 * @return array<string, mixed>|WP_Error Public payload or error with fields.
	 */
	public static function save_from_payload( int $user_id, array $input ) {
		if ( ! self::can_edit( $user_id ) ) {
			return new \WP_Error(
				'hvnly_branding_forbidden',
				__( 'You cannot update Workspace branding.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$validated = self::validate_payload( $input );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		return self::persist( $validated, $user_id );
	}

	/**
	 * Resolve attachment URL payload.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{id:int,url:string,mime:string}
	 */
	public static function attachment_payload( int $attachment_id ): array {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return array(
				'id'   => 0,
				'url'  => '',
				'mime' => '',
			);
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'full' );
		if ( ! is_string( $url ) || '' === $url ) {
			$url = (string) wp_get_attachment_url( $attachment_id );
		}

		return array(
			'id'   => $attachment_id,
			'url'  => esc_url_raw( (string) $url ),
			'mime' => (string) get_post_mime_type( $attachment_id ),
		);
	}

	/**
	 * Ensure attachment is a usable image (or ICO for favicon).
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $field         Field key for messaging.
	 * @return true|\WP_Error
	 */
	private static function validate_image_attachment( int $attachment_id, string $field ) {
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return new \WP_Error(
				'hvnly_branding_bad_attachment',
				__( 'Selected media was not found.', 'havenlytics' )
			);
		}

		$mime = (string) get_post_mime_type( $attachment_id );
		$ok   = 0 === strpos( $mime, 'image/' );

		if ( 'faviconId' === $field ) {
			$ok = $ok || in_array(
				$mime,
				array(
					'image/x-icon',
					'image/vnd.microsoft.icon',
					'image/ico',
				),
				true
			);
		}

		if ( ! $ok ) {
			return new \WP_Error(
				'hvnly_branding_bad_mime',
				__( 'Please choose an image file.', 'havenlytics' )
			);
		}

		$file = get_attached_file( $attachment_id );
		if ( is_string( $file ) && is_readable( $file ) ) {
			$size = filesize( $file );
			$max  = (int) wp_max_upload_size();
			if ( false !== $size && $max > 0 && (int) $size > $max ) {
				return new \WP_Error(
					'hvnly_branding_too_large',
					__( 'Selected file exceeds the site upload limit.', 'havenlytics' )
				);
			}
		}

		return true;
	}

	/**
	 * Effective dashboard display name (custom or default product name).
	 *
	 * @return string
	 */
	public static function resolved_dashboard_name(): string {
		$raw  = self::get_raw();
		$name = trim( (string) $raw['dashboardName'] );
		if ( '' !== $name ) {
			return $name;
		}
		return __( 'Havenlytics', 'havenlytics' );
	}
}
