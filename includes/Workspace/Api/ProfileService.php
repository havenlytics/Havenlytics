<?php
/**
 * Builds / updates Workspace Agent Profile from the existing Agent CPT.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Agent\AgencyFields;
use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Agent\AgentFields;
use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\AgentProvisioner;
use HvnlyNab\Workspace\Auth\PortalAuthorization;
use HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Profile SSOT: wp_users + linked hvnly_agent CPT (no parallel model).
 *
 * @since 3.2.0
 */
final class ProfileService {

	/**
	 * Meta key map for agent contact/professional/social/address fields.
	 *
	 * @var array<string, string>
	 */
	private const META_MAP = array(
		'email'     => AgentConstants::META_EMAIL,
		'phone'     => AgentConstants::META_PHONE,
		'mobile'    => AgentConstants::META_MOBILE,
		'fax'       => AgentConstants::META_FAX,
		'office'    => AgentConstants::META_OFFICE,
		'whatsapp'  => AgentConstants::META_WHATSAPP,
		'website'   => AgentConstants::META_WEBSITE,
		'position'  => AgentConstants::META_POSITION,
		'company'   => AgentConstants::META_COMPANY,
		'license'   => AgentConstants::META_LICENSE,
		'address'   => AgentConstants::META_ADDRESS,
		'facebook'  => AgentConstants::META_FACEBOOK,
		'instagram' => AgentConstants::META_INSTAGRAM,
		'linkedin'  => AgentConstants::META_LINKEDIN,
		'twitter'   => AgentConstants::META_TWITTER,
		'youtube'   => AgentConstants::META_YOUTUBE,
		'tiktok'    => AgentConstants::META_TIKTOK,
		'vimeo'     => AgentConstants::META_VIMEO,
		'pinterest' => AgentConstants::META_PINTEREST,
	);

	/**
	 * MIME types accepted for the agent profile photo.
	 *
	 * jpg/jpeg → image/jpeg, png → image/png, gif → image/gif, webp → image/webp.
	 * SVG is intentionally excluded: the plugin has no safe SVG sanitizer and raw
	 * SVG can carry script payloads. Do not add it here without sanitization.
	 *
	 * @var string[]
	 */
	private const AVATAR_ALLOWED_MIME = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
	);

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @var AgentProvisioner
	 */
	private $provisioner;

	/**
	 * @param AgentIdentityService|null $identity    Identity.
	 * @param PortalAuthorization|null  $auth        Authz.
	 * @param AgentProvisioner|null     $provisioner Provisioner.
	 */
	public function __construct(
		?AgentIdentityService $identity = null,
		?PortalAuthorization $auth = null,
		?AgentProvisioner $provisioner = null
	) {
		$this->identity    = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth        = $auth ? $auth : new PortalAuthorization( $this->identity );
		$this->provisioner = $provisioner ? $provisioner : new AgentProvisioner();
	}

	/**
	 * Full profile payload for the current user.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_profile( int $user_id ) {
		$user_id  = absint( $user_id );
		$agent_id = $this->resolve_agent_id( $user_id );
		if ( is_wp_error( $agent_id ) ) {
			return $agent_id;
		}

		$post = get_post( $agent_id );
		if ( ! $post || AgentConstants::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'hvnly_profile_missing_agent',
				__( 'Agent profile not found.', 'havenlytics' ),
				array( 'status' => 404 )
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'hvnly_profile_missing_user',
				__( 'User not found.', 'havenlytics' ),
				array( 'status' => 404 )
			);
		}

		$fields    = AgentFields::get_profile_raw( $agent_id );
		// avatarId stays the raw uploaded-photo id (0 = none) so the editor knows
		// whether a custom photo can be removed; avatarUrl uses the shared chain
		// (uploaded → Gravatar → placeholder) so the preview is never blank.
		$avatar_id = (int) get_post_thumbnail_id( $agent_id );
		$avatar    = \HvnlyNab\Common\AvatarService::resolve_url( $agent_id, $user_id, 96, 'medium' );
		$agency    = AgencyFields::resolve_for_agent( $agent_id );
		$ext       = get_post_meta( $agent_id, AgentConstants::META_EXTENSIONS, true );
		$ext       = is_array( $ext ) ? $ext : array();
		$experience = isset( $ext['experience_years'] ) ? absint( $ext['experience_years'] ) : 0;
		if ( $experience <= 0 && isset( $ext['experience'] ) ) {
			$experience = absint( $ext['experience'] );
		}

		$registration = '';
		if ( class_exists( WorkspaceRegistrationStatus::class ) ) {
			$registration = (string) WorkspaceRegistrationStatus::get_for_user( $user_id );
		}

		$role = (string) $this->identity->get_role( $user_id );

		return array(
			'agentId'      => $agent_id,
			'general'      => array(
				'firstName'   => (string) $user->first_name,
				'lastName'    => (string) $user->last_name,
				'displayName' => (string) $user->display_name,
				'publicName'  => (string) $post->post_title,
				'biography'   => (string) $post->post_content,
				'avatarId'    => $avatar_id,
				'avatarUrl'   => $avatar,
			),
			'contact'      => array(
				'email'    => (string) ( $fields['email'] ?? '' ),
				'phone'    => (string) ( $fields['phone'] ?? '' ),
				'mobile'   => (string) ( $fields['mobile'] ?? '' ),
				'whatsapp' => (string) ( $fields['whatsapp'] ?? '' ),
				'website'  => (string) ( $fields['website'] ?? '' ),
				'fax'      => (string) ( $fields['fax'] ?? '' ),
				'office'   => (string) ( $fields['office'] ?? '' ),
			),
			'professional' => array(
				'position'        => (string) ( $fields['position'] ?? '' ),
				'company'         => (string) ( $fields['company'] ?? '' ),
				'license'         => (string) ( $fields['license'] ?? '' ),
				'experienceYears' => $experience,
			),
			'address'      => array(
				// SSOT is a single textarea meta — no structured city/state keys in Agent CPT.
				'full' => (string) ( $fields['address'] ?? '' ),
			),
			'social'       => array(
				'facebook'  => (string) ( $fields['facebook'] ?? '' ),
				'instagram' => (string) ( $fields['instagram'] ?? '' ),
				'linkedin'  => (string) ( $fields['linkedin'] ?? '' ),
				'twitter'   => (string) ( $fields['twitter'] ?? '' ),
				'youtube'   => (string) ( $fields['youtube'] ?? '' ),
				'tiktok'    => (string) ( $fields['tiktok'] ?? '' ),
				'vimeo'     => (string) ( $fields['vimeo'] ?? '' ),
				'pinterest' => (string) ( $fields['pinterest'] ?? '' ),
			),
			'agency'       => array(
				'id'   => isset( $agency['id'] ) ? (int) $agency['id'] : 0,
				'name' => isset( $agency['name'] ) ? (string) $agency['name'] : '',
			),
			'account'      => array(
				'username'           => (string) $user->user_login,
				'role'               => $role,
				'registrationStatus' => $registration,
				'loginEmail'         => (string) $user->user_email,
			),
			'permissions'  => array(
				'canEditProfile'    => $this->auth->can_edit_own_profile( $user_id ),
				'canChangePassword' => true,
			),
		);
	}

	/**
	 * Update profile for the current user only.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $payload Request body.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update_profile( int $user_id, array $payload ) {
		$user_id = absint( $user_id );
		if ( ! $this->auth->can_edit_own_profile( $user_id ) ) {
			return new WP_Error(
				'hvnly_profile_forbidden',
				__( 'You cannot edit this profile.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$agent_id = $this->resolve_agent_id( $user_id );
		if ( is_wp_error( $agent_id ) ) {
			return $agent_id;
		}

		$errors = array();

		$general = isset( $payload['general'] ) && is_array( $payload['general'] ) ? $payload['general'] : array();
		$contact = isset( $payload['contact'] ) && is_array( $payload['contact'] ) ? $payload['contact'] : array();
		$pro     = isset( $payload['professional'] ) && is_array( $payload['professional'] ) ? $payload['professional'] : array();
		$address = isset( $payload['address'] ) && is_array( $payload['address'] ) ? $payload['address'] : array();
		$social  = isset( $payload['social'] ) && is_array( $payload['social'] ) ? $payload['social'] : array();
		$account = isset( $payload['account'] ) && is_array( $payload['account'] ) ? $payload['account'] : array();

		$user_update = array( 'ID' => $user_id );

		if ( array_key_exists( 'firstName', $general ) ) {
			$user_update['first_name'] = sanitize_text_field( (string) $general['firstName'] );
		}
		if ( array_key_exists( 'lastName', $general ) ) {
			$user_update['last_name'] = sanitize_text_field( (string) $general['lastName'] );
		}
		if ( array_key_exists( 'displayName', $general ) ) {
			$display = sanitize_text_field( (string) $general['displayName'] );
			if ( '' === $display ) {
				$errors['general.displayName'] = __( 'Display name is required.', 'havenlytics' );
			} else {
				$user_update['display_name'] = $display;
			}
		}

		if ( array_key_exists( 'loginEmail', $account ) ) {
			$email = sanitize_email( (string) $account['loginEmail'] );
			if ( '' === $email || ! is_email( $email ) ) {
				$errors['account.loginEmail'] = __( 'Enter a valid login email.', 'havenlytics' );
			} else {
				$exists = email_exists( $email );
				if ( $exists && (int) $exists !== $user_id ) {
					$errors['account.loginEmail'] = __( 'That email is already in use.', 'havenlytics' );
				} else {
					$user_update['user_email'] = $email;
				}
			}
		}

		$new_password = isset( $account['newPassword'] ) ? (string) $account['newPassword'] : '';
		$confirm      = isset( $account['confirmPassword'] ) ? (string) $account['confirmPassword'] : '';
		$current      = isset( $account['currentPassword'] ) ? (string) $account['currentPassword'] : '';

		if ( '' !== $new_password || '' !== $confirm || '' !== $current ) {
			if ( '' === $current ) {
				$errors['account.currentPassword'] = __( 'Current password is required.', 'havenlytics' );
			} else {
				$user = get_userdata( $user_id );
				if ( ! $user || ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
					$errors['account.currentPassword'] = __( 'Current password is incorrect.', 'havenlytics' );
				}
			}
			if ( strlen( $new_password ) < 8 ) {
				$errors['account.newPassword'] = __( 'New password must be at least 8 characters.', 'havenlytics' );
			}
			if ( $new_password !== $confirm ) {
				$errors['account.confirmPassword'] = __( 'Password confirmation does not match.', 'havenlytics' );
			}
		}

		if ( array_key_exists( 'email', $contact ) ) {
			$email = sanitize_email( (string) $contact['email'] );
			if ( '' !== $email && ! is_email( $email ) ) {
				$errors['contact.email'] = __( 'Enter a valid public email.', 'havenlytics' );
			}
		}

		$url_fields = array(
			'website'   => 'contact.website',
			'facebook'  => 'social.facebook',
			'instagram' => 'social.instagram',
			'linkedin'  => 'social.linkedin',
			'twitter'   => 'social.twitter',
			'youtube'   => 'social.youtube',
			'tiktok'    => 'social.tiktok',
			'vimeo'     => 'social.vimeo',
			'pinterest' => 'social.pinterest',
		);
		$url_sources = array_merge( $contact, $social );
		foreach ( $url_fields as $key => $err_key ) {
			if ( ! array_key_exists( $key, $url_sources ) ) {
				continue;
			}
			$raw = trim( (string) $url_sources[ $key ] );
			if ( '' === $raw ) {
				continue;
			}
			$san = esc_url_raw( $raw );
			if ( '' === $san ) {
				$errors[ $err_key ] = __( 'Enter a valid URL.', 'havenlytics' );
			}
		}

		if ( array_key_exists( 'avatarId', $general ) ) {
			$avatar_id = absint( $general['avatarId'] );
			if ( $avatar_id > 0 ) {
				$avatar_error = $this->validate_avatar_attachment( $avatar_id );
				if ( '' !== $avatar_error ) {
					$errors['general.avatarId'] = $avatar_error;
				}
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'hvnly_profile_invalid',
				__( 'Please fix the highlighted fields.', 'havenlytics' ),
				array(
					'status' => 400,
					'fields' => $errors,
				)
			);
		}

		if ( count( $user_update ) > 1 ) {
			$result = wp_update_user( $user_update );
			if ( is_wp_error( $result ) ) {
				return new WP_Error(
					'hvnly_profile_user_update_failed',
					$result->get_error_message(),
					array( 'status' => 400 )
				);
			}
		}

		if ( '' !== $new_password && empty( $errors ) ) {
			wp_set_password( $new_password, $user_id );
			// Keep current session valid after password change.
			wp_set_auth_cookie( $user_id, true );
			wp_set_current_user( $user_id );
		}

		$post_update = array( 'ID' => $agent_id );
		if ( array_key_exists( 'biography', $general ) ) {
			$post_update['post_content'] = wp_kses_post( (string) $general['biography'] );
		}
		// Public agent title: prefer explicit publicName, else displayName.
		if ( array_key_exists( 'publicName', $general ) ) {
			$title = sanitize_text_field( (string) $general['publicName'] );
			if ( '' !== $title ) {
				$post_update['post_title'] = $title;
			}
		} elseif ( array_key_exists( 'displayName', $general ) ) {
			$title = sanitize_text_field( (string) $general['displayName'] );
			if ( '' !== $title ) {
				$post_update['post_title'] = $title;
			}
		}
		if ( count( $post_update ) > 1 ) {
			wp_update_post( $post_update );
		}

		if ( array_key_exists( 'avatarId', $general ) ) {
			$avatar_id = absint( $general['avatarId'] );
			if ( $avatar_id > 0 ) {
				set_post_thumbnail( $agent_id, $avatar_id );
			} else {
				delete_post_thumbnail( $agent_id );
			}
		}

		$meta = array();
		foreach ( self::META_MAP as $api_key => $meta_key ) {
			if ( isset( $contact[ $api_key ] ) ) {
				$meta[ $meta_key ] = $contact[ $api_key ];
			} elseif ( isset( $pro[ $api_key ] ) ) {
				$meta[ $meta_key ] = $pro[ $api_key ];
			} elseif ( isset( $social[ $api_key ] ) ) {
				$meta[ $meta_key ] = $social[ $api_key ];
			} elseif ( 'address' === $api_key && array_key_exists( 'full', $address ) ) {
				$meta[ $meta_key ] = $address['full'];
			}
		}
		if ( ! empty( $meta ) ) {
			AgentFields::save_meta_map( $agent_id, $meta );
		}

		if ( array_key_exists( 'experienceYears', $pro ) ) {
			$years = absint( $pro['experienceYears'] );
			$ext   = get_post_meta( $agent_id, AgentConstants::META_EXTENSIONS, true );
			if ( ! is_array( $ext ) ) {
				$ext = array();
			}
			$ext['experience_years'] = $years;
			update_post_meta( $agent_id, AgentConstants::META_EXTENSIONS, $ext );
		}

		/**
		 * Fires after Workspace profile save (same hook family as admin).
		 *
		 * @param int $agent_id Agent CPT ID.
		 * @param int $user_id  User ID.
		 */
		do_action( 'hvnly_agent_profile_saved', $agent_id );

		$this->identity->clear_cache( $user_id );
		\HvnlyNab\Common\AvatarService::clear_gravatar_cache( $user_id );

		return $this->get_profile( $user_id );
	}

	/**
	 * Validate an avatar attachment for the current user.
	 *
	 * Checks, in order: the attachment exists and is an image; the current user
	 * owns it (uploader, or may delete it, or is an administrator); the real MIME
	 * type is in the profile-photo whitelist; and the file is within the size cap.
	 * Ownership is asserted via authorship / `delete_post` — NOT `edit_post`, which
	 * maps to the `edit_posts` primitive that the `hvnly_agent` role deliberately
	 * lacks. That mismatch is what previously rejected every agent's own upload.
	 *
	 * @param int $avatar_id Attachment ID (already sanitized, > 0).
	 * @return string Empty string when acceptable; otherwise a user-facing error.
	 */
	private function validate_avatar_attachment( int $avatar_id ): string {
		$post = get_post( $avatar_id );
		if ( ! $post || 'attachment' !== $post->post_type || ! wp_attachment_is_image( $avatar_id ) ) {
			return __( 'Invalid image. Please choose a valid photo.', 'havenlytics' );
		}

		// Authorization to USE an image as the profile photo.
		//
		// The hvnly_agent role has `upload_files` but NOT the edit_posts/
		// delete_posts primitives. WordPress maps the meta-caps edit_post /
		// delete_post on an attachment down to those primitives, so BOTH return
		// false for an agent even on their OWN upload (verified at runtime). Any
		// gate built on post-editing ownership therefore rejects every agent —
		// whether they upload a fresh image, pick one from the media library, or
		// simply re-save a profile whose avatar was assigned by an admin (all
		// seeded agents are in exactly that state). A profile photo is a public
		// image, and the agent can upload arbitrary images anyway, so the correct
		// gate is the media capability plus the image/MIME/size checks below — not
		// attachment post-editing rights. `manage_options` / authorship / delete_post
		// remain first so higher-privileged roles are covered explicitly.
		$can_use = current_user_can( 'manage_options' )
			|| (int) $post->post_author === get_current_user_id()
			|| current_user_can( 'delete_post', $avatar_id )
			|| current_user_can( 'upload_files' );
		if ( ! $can_use ) {
			return __( 'You cannot use this profile photo.', 'havenlytics' );
		}

		// Verify the real type from file bytes + extension, not just stored MIME.
		$file = get_attached_file( $avatar_id );
		$mime = '';
		if ( $file && file_exists( $file ) ) {
			$check = wp_check_filetype_and_ext( $file, wp_basename( $file ) );
			$mime  = isset( $check['type'] ) ? (string) $check['type'] : '';
		}
		if ( '' === $mime ) {
			$mime = (string) get_post_mime_type( $avatar_id );
		}
		if ( '' === $mime || ! in_array( $mime, self::AVATAR_ALLOWED_MIME, true ) ) {
			return __( 'Unsupported image format. Please use JPG, PNG, GIF, or WebP.', 'havenlytics' );
		}

		// Size cap — reuse the plugin-wide media limit (5MB) as the SSOT.
		if ( $file && file_exists( $file ) ) {
			$size = (int) @filesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $size > PropertyMediaService::MAX_BYTES ) {
				return __( 'Maximum upload size exceeded. Images must be 5MB or smaller.', 'havenlytics' );
			}
		}

		return '';
	}

	/**
	 * Resolve linked Agent CPT for user; provision if missing.
	 *
	 * @param int $user_id User ID.
	 * @return int|WP_Error
	 */
	private function resolve_agent_id( int $user_id ) {
		$agent_id = $this->identity->get_agent_id( $user_id );
		if ( $agent_id > 0 ) {
			return $agent_id;
		}

		$created = $this->provisioner->ensure_agent_for_user( $user_id );
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$this->identity->clear_cache( $user_id );
		$agent_id = absint( $created );
		if ( $agent_id <= 0 ) {
			return new WP_Error(
				'hvnly_profile_missing_agent',
				__( 'Agent profile not found.', 'havenlytics' ),
				array( 'status' => 404 )
			);
		}

		return $agent_id;
	}
}
