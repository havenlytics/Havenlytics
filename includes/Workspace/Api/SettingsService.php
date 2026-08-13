<?php
/**
 * Workspace Settings — current-user prefs + read-only site policy.
 *
 * Account writes reuse ProfileService / wp_users.
 * UI prefs stored in existing Agent META_EXTENSIONS (no new options table).
 * Site Contact Agent / Workspace policy is read-only (admin owns those keys).
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\ContactAgent\ContactAgentSettings;
use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\AgentProvisioner;
use HvnlyNab\Workspace\Auth\PortalAuthorization;
use HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus;
use HvnlyNab\Workspace\WorkspaceBranding;
use HvnlyNab\Workspace\WorkspaceProCapabilities;
use HvnlyNab\Workspace\WorkspaceSettings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.0
 */
final class SettingsService {

	/** @var string Nested key inside Agent extensions. */
	private const EXT_KEY = 'workspace_settings';

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @var ProfileService
	 */
	private $profile;

	/**
	 * @var AgentProvisioner
	 */
	private $provisioner;

	/**
	 * @param AgentIdentityService|null $identity    Identity.
	 * @param PortalAuthorization|null  $auth        Authz.
	 * @param ProfileService|null       $profile     Profile service (account writes).
	 * @param AgentProvisioner|null     $provisioner Provisioner.
	 */
	public function __construct(
		?AgentIdentityService $identity = null,
		?PortalAuthorization $auth = null,
		?ProfileService $profile = null,
		?AgentProvisioner $provisioner = null
	) {
		$this->identity    = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth        = $auth ? $auth : new PortalAuthorization( $this->identity );
		$this->profile     = $profile ? $profile : new ProfileService( $this->identity, $this->auth );
		$this->provisioner = $provisioner ? $provisioner : new AgentProvisioner();
	}

	/**
	 * @param int $user_id User ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_settings( int $user_id ) {
		$user_id = absint( $user_id );
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'hvnly_settings_missing_user',
				__( 'User not found.', 'havenlytics' ),
				array( 'status' => 404 )
			);
		}

		$prefs = $this->get_user_prefs( $user_id );
		$caps  = $this->auth->all( $user_id );

		$registration = '';
		if ( class_exists( WorkspaceRegistrationStatus::class ) ) {
			$registration = (string) WorkspaceRegistrationStatus::get_for_user( $user_id );
		}

		$site_inquiry_notify = class_exists( ContactAgentSettings::class )
			? ContactAgentSettings::notify_agent_enabled()
			: true;

		$plugin = get_option( 'hvnly_plugin_settings', array() );
		$ca     = ( is_array( $plugin ) && isset( $plugin['contact-agent'] ) && is_array( $plugin['contact-agent'] ) )
			? $plugin['contact-agent']
			: array();

		return array(
			'account'       => array(
				'username'           => (string) $user->user_login,
				'loginEmail'         => (string) $user->user_email,
				'displayName'        => (string) $user->display_name,
				'role'               => (string) $this->identity->get_role( $user_id ),
				'registrationStatus' => $registration,
				'language'           => (string) get_user_locale( $user_id ),
				'languageReady'      => true,
				'profileHref'        => '#/profile',
			),
			'workspace'     => array(
				'timezone'            => (string) ( $prefs['timezone'] ?? '' ),
				'dateFormat'          => (string) ( $prefs['dateFormat'] ?? get_option( 'date_format', 'F j, Y' ) ),
				'itemsPerPage'        => (int) ( $prefs['itemsPerPage'] ?? 10 ),
				'defaultPropertyView' => (string) ( $prefs['defaultPropertyView'] ?? 'list' ),
				'policy'              => array(
					'readOnly'               => true,
					'enabled'                => WorkspaceSettings::is_enabled(),
					'pageSlug'               => WorkspaceSettings::get_page_slug(),
					'registrationMode'       => WorkspaceSettings::get_registration_mode(),
					'agentsCanDirectPublish' => WorkspaceSettings::agents_can_direct_publish(),
				),
				'permissions'         => is_array( $caps ) ? $caps : array(),
			),
			'email'         => array(
				'inquiryEmails'           => (bool) ( $prefs['emailPrefs']['inquiryEmails'] ?? true ),
				'propertyWorkflowEmails'  => (bool) ( $prefs['emailPrefs']['propertyWorkflowEmails'] ?? true ),
				'marketingEmails'         => (bool) ( $prefs['emailPrefs']['marketingEmails'] ?? false ),
				'siteDefaults'            => array(
					'readOnly'              => true,
					'inquiryNotifyAgent'    => $site_inquiry_notify,
					'propertySubmitted'     => ! empty( $ca['hvnly_workspace_email_property_submitted'] ),
					'propertyApproved'      => ! empty( $ca['hvnly_workspace_email_property_approved'] ),
					'propertyRejected'      => ! empty( $ca['hvnly_workspace_email_property_rejected'] ),
					'newInquiryTemplate'    => ! empty( $ca['hvnly_workspace_email_new_inquiry'] ),
				),
			),
			'notifications' => array(
				'email'   => (bool) ( $prefs['notificationEmails'] ?? true ),
				'inApp'   => (bool) ( $prefs['notificationInApp'] ?? true ),
				'desktop' => array(
					'available' => false,
					'future'    => true,
					'enabled'   => false,
				),
				'sound'   => array(
					'available' => false,
					'future'    => true,
					'enabled'   => false,
				),
			),
			'privacy'       => array(
				'privacyPolicyUrl'      => (string) ( get_privacy_policy_url() ?: '' ),
				'deleteAccountRequest'  => array(
					'available' => false,
					'future'    => true,
				),
			),
			'branding'      => WorkspaceBranding::get_public( $user_id ),
			'pro'           => WorkspaceProCapabilities::localize_payload(),
		);
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $payload PUT body.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update_settings( int $user_id, array $payload ) {
		$user_id = absint( $user_id );
		if ( ! $this->auth->can_access_workspace( $user_id ) ) {
			return new WP_Error(
				'hvnly_settings_forbidden',
				__( 'You cannot update settings.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$errors = array();

		$account  = isset( $payload['account'] ) && is_array( $payload['account'] ) ? $payload['account'] : array();
		$ws       = isset( $payload['workspace'] ) && is_array( $payload['workspace'] ) ? $payload['workspace'] : array();
		$email    = isset( $payload['email'] ) && is_array( $payload['email'] ) ? $payload['email'] : array();
		$notif    = isset( $payload['notifications'] ) && is_array( $payload['notifications'] ) ? $payload['notifications'] : array();
		$branding = isset( $payload['branding'] ) && is_array( $payload['branding'] ) ? $payload['branding'] : null;

		// Account → same storage as Profile (wp_users). Never write site plugin settings.
		$profile_payload = array();
		if ( array_key_exists( 'displayName', $account ) || array_key_exists( 'loginEmail', $account )
			|| array_key_exists( 'currentPassword', $account ) || array_key_exists( 'newPassword', $account )
			|| array_key_exists( 'confirmPassword', $account ) ) {
			$profile_payload['general'] = array();
			$profile_payload['account'] = array();
			if ( array_key_exists( 'displayName', $account ) ) {
				$profile_payload['general']['displayName'] = $account['displayName'];
			}
			if ( array_key_exists( 'loginEmail', $account ) ) {
				$profile_payload['account']['loginEmail'] = $account['loginEmail'];
			}
			foreach ( array( 'currentPassword', 'newPassword', 'confirmPassword' ) as $pw_key ) {
				if ( array_key_exists( $pw_key, $account ) ) {
					$profile_payload['account'][ $pw_key ] = (string) $account[ $pw_key ];
				}
			}
			$result = $this->profile->update_profile( $user_id, $profile_payload );
			if ( is_wp_error( $result ) ) {
				$data = $result->get_error_data();
				if ( is_array( $data ) && ! empty( $data['fields'] ) && is_array( $data['fields'] ) ) {
					foreach ( $data['fields'] as $key => $msg ) {
						$mapped            = str_replace(
							array( 'general.displayName', 'account.' ),
							array( 'account.displayName', 'account.' ),
							(string) $key
						);
						$errors[ $mapped ] = $msg;
					}
				} else {
					return $result;
				}
			}
		}

		if ( array_key_exists( 'language', $account ) ) {
			$lang = sanitize_text_field( (string) $account['language'] );
			if ( '' === $lang || 'site-default' === $lang ) {
				delete_user_meta( $user_id, 'locale' );
			} else {
				// WP core user locale (existing meta — not a new option group).
				update_user_meta( $user_id, 'locale', $lang );
			}
		}

		$prefs = $this->get_user_prefs( $user_id );

		if ( array_key_exists( 'timezone', $ws ) ) {
			$prefs['timezone'] = sanitize_text_field( (string) $ws['timezone'] );
		}
		if ( array_key_exists( 'dateFormat', $ws ) ) {
			$prefs['dateFormat'] = sanitize_text_field( (string) $ws['dateFormat'] );
		}
		if ( array_key_exists( 'itemsPerPage', $ws ) ) {
			$n = absint( $ws['itemsPerPage'] );
			if ( $n < 5 || $n > 50 ) {
				$errors['workspace.itemsPerPage'] = __( 'Items per page must be between 5 and 50.', 'havenlytics' );
			} else {
				$prefs['itemsPerPage'] = $n;
			}
		}
		if ( array_key_exists( 'defaultPropertyView', $ws ) ) {
			$view = sanitize_key( (string) $ws['defaultPropertyView'] );
			if ( ! in_array( $view, array( 'list', 'grid' ), true ) ) {
				$errors['workspace.defaultPropertyView'] = __( 'Invalid property view.', 'havenlytics' );
			} else {
				$prefs['defaultPropertyView'] = $view;
			}
		}

		$email_prefs = isset( $prefs['emailPrefs'] ) && is_array( $prefs['emailPrefs'] ) ? $prefs['emailPrefs'] : array();
		foreach ( array( 'inquiryEmails', 'propertyWorkflowEmails', 'marketingEmails' ) as $ek ) {
			if ( array_key_exists( $ek, $email ) ) {
				$email_prefs[ $ek ] = ! empty( $email[ $ek ] );
			}
		}
		$prefs['emailPrefs'] = $email_prefs;

		if ( array_key_exists( 'email', $notif ) ) {
			$prefs['notificationEmails'] = ! empty( $notif['email'] );
			if ( array_key_exists( 'inApp', $notif ) ) {
				$prefs['notificationInApp'] = ! empty( $notif['inApp'] );
			}
		}

		$branding_row = null;
		if ( null !== $branding ) {
			if ( ! WorkspaceBranding::can_edit( $user_id ) ) {
				return new WP_Error(
					'hvnly_branding_forbidden',
					__( 'You cannot update Workspace branding.', 'havenlytics' ),
					array( 'status' => 403 )
				);
			}
			$validated = WorkspaceBranding::validate_payload( $branding );
			if ( is_wp_error( $validated ) ) {
				$data = $validated->get_error_data();
				if ( is_array( $data ) && ! empty( $data['fields'] ) && is_array( $data['fields'] ) ) {
					$errors = array_merge( $errors, $data['fields'] );
				} else {
					return $validated;
				}
			} else {
				$branding_row = $validated;
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'hvnly_settings_invalid',
				__( 'Please fix the highlighted fields.', 'havenlytics' ),
				array(
					'status' => 400,
					'fields' => $errors,
				)
			);
		}

		if ( null !== $branding_row ) {
			WorkspaceBranding::persist( $branding_row, $user_id );
		}

		$saved = $this->save_user_prefs( $user_id, $prefs );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$this->identity->clear_cache( $user_id );

		return $this->get_settings( $user_id );
	}

	/**
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	private function get_user_prefs( int $user_id ): array {
		$defaults = array(
			'timezone'             => '',
			'dateFormat'           => (string) get_option( 'date_format', 'F j, Y' ),
			'itemsPerPage'         => 10,
			'defaultPropertyView'  => 'list',
			'emailPrefs'           => array(
				'inquiryEmails'          => true,
				'propertyWorkflowEmails' => true,
				'marketingEmails'        => false,
			),
			'notificationEmails'   => true,
			'notificationInApp'    => true,
		);

		$agent_id = $this->resolve_agent_id( $user_id );
		if ( $agent_id <= 0 ) {
			return $defaults;
		}

		$ext = get_post_meta( $agent_id, AgentConstants::META_EXTENSIONS, true );
		if ( ! is_array( $ext ) || empty( $ext[ self::EXT_KEY ] ) || ! is_array( $ext[ self::EXT_KEY ] ) ) {
			return $defaults;
		}

		$stored = $ext[ self::EXT_KEY ];
		$merged = array_merge( $defaults, $stored );
		if ( isset( $stored['emailPrefs'] ) && is_array( $stored['emailPrefs'] ) ) {
			$merged['emailPrefs'] = array_merge( $defaults['emailPrefs'], $stored['emailPrefs'] );
		}

		return $merged;
	}

	/**
	 * Persist prefs into existing Agent extensions meta (no new option keys).
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $prefs   Prefs.
	 * @return true|WP_Error
	 */
	private function save_user_prefs( int $user_id, array $prefs ) {
		$agent_id = $this->resolve_agent_id( $user_id );
		if ( $agent_id <= 0 ) {
			$created = $this->provisioner->ensure_agent_for_user( $user_id );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$agent_id = absint( $created );
		}

		if ( $agent_id <= 0 ) {
			return new WP_Error(
				'hvnly_settings_missing_agent',
				__( 'Agent profile is required to save preferences.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$ext = get_post_meta( $agent_id, AgentConstants::META_EXTENSIONS, true );
		if ( ! is_array( $ext ) ) {
			$ext = array();
		}

		$ext[ self::EXT_KEY ] = array(
			'timezone'            => sanitize_text_field( (string) ( $prefs['timezone'] ?? '' ) ),
			'dateFormat'          => sanitize_text_field( (string) ( $prefs['dateFormat'] ?? '' ) ),
			'itemsPerPage'        => min( 50, max( 5, absint( $prefs['itemsPerPage'] ?? 10 ) ) ),
			'defaultPropertyView' => in_array( ( $prefs['defaultPropertyView'] ?? 'list' ), array( 'list', 'grid' ), true )
				? (string) $prefs['defaultPropertyView']
				: 'list',
			'emailPrefs'          => array(
				'inquiryEmails'          => ! empty( $prefs['emailPrefs']['inquiryEmails'] ),
				'propertyWorkflowEmails' => ! empty( $prefs['emailPrefs']['propertyWorkflowEmails'] ),
				'marketingEmails'        => ! empty( $prefs['emailPrefs']['marketingEmails'] ),
			),
			'notificationEmails'  => ! empty( $prefs['notificationEmails'] ),
			'notificationInApp'   => array_key_exists( 'notificationInApp', $prefs ) ? ! empty( $prefs['notificationInApp'] ) : true,
		);

		update_post_meta( $agent_id, AgentConstants::META_EXTENSIONS, $ext );

		return true;
	}

	/**
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function resolve_agent_id( int $user_id ): int {
		return absint( $this->identity->get_agent_id( $user_id ) );
	}
}
