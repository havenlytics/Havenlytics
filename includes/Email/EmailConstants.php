<?php
/**
 * Havenlytics transactional email constants.
 *
 * @package HvnlyNab\Email
 * @since   3.0.2
 */

namespace HvnlyNab\Email;

defined( 'ABSPATH' ) || exit;

/**
 * Central registry for Havenlytics email types and template paths.
 *
 * @since 3.0.2
 */
final class EmailConstants {

	/** @var string Settings group key inside hvnly_plugin_settings. */
	public const SETTINGS_GROUP = 'email';

	/** @var string Property import wizard success email. */
	public const TYPE_PROPERTY_IMPORT_SUCCESS = 'property_import_success';

	/** @var string Reserved — agent registration welcome email. */
	public const TYPE_AGENT_REGISTER = 'agent_register';

	/** @var string Reserved — agent profile updated notification. */
	public const TYPE_AGENT_PROFILE_UPDATE = 'agent_profile_update';

	/** @var string Reserved — password reset / account email. */
	public const TYPE_PASSWORD_RESET = 'password_reset';

	/** @var string Workspace registration approved. */
	public const TYPE_WS_REG_APPROVED = 'workspace_registration_approved';

	/** @var string Workspace registration rejected. */
	public const TYPE_WS_REG_REJECTED = 'workspace_registration_rejected';

	/** @var string Workspace account suspended. */
	public const TYPE_WS_REG_SUSPENDED = 'workspace_account_suspended';

	/** @var string Workspace account re-activated. */
	public const TYPE_WS_REG_ACTIVATED = 'workspace_account_activated';

	/* ------------------------------------------------------------------ */
	/* Sprint 24C — the gaps the audit found.                              */
	/* TYPE_AGENT_REGISTER and TYPE_PASSWORD_RESET already existed as       */
	/* declared-but-unwired stubs; they are now wired rather than replaced. */
	/* ------------------------------------------------------------------ */

	/** @var string Registration received, awaiting admin approval (to the agent). */
	public const TYPE_WS_REG_PENDING = 'workspace_registration_pending';

	/** @var string A registration is awaiting approval (to the site admin). */
	public const TYPE_WS_REG_ADMIN_ALERT = 'workspace_registration_admin_alert';

	/** @var string Workspace account archived. */
	public const TYPE_WS_REG_ARCHIVED = 'workspace_account_archived';

	/** @var string Password changed confirmation. */
	public const TYPE_PASSWORD_CHANGED = 'workspace_password_changed';

	/** @var string Admin-provisioned account invitation. */
	public const TYPE_WS_INVITATION = 'workspace_invitation';

	/** @var string Listing submitted for review. */
	public const TYPE_PROPERTY_SUBMITTED = 'property_submitted';

	/** @var string Listing approved. */
	public const TYPE_PROPERTY_APPROVED = 'property_approved';

	/** @var string Listing rejected. */
	public const TYPE_PROPERTY_REJECTED = 'property_rejected';

	/** @var string Listing published. */
	public const TYPE_PROPERTY_PUBLISHED = 'property_published';

	/** @var string A new listing submitted for review (to the site admin). */
	public const TYPE_PROPERTY_ADMIN_ALERT = 'property_admin_alert';

	/** @var string Email address verification request (identity layer). @since 3.3.0 */
	public const TYPE_WS_EMAIL_VERIFY = 'workspace_email_verify';

	/** @var string Email address verified confirmation (identity layer). @since 3.3.0 */
	public const TYPE_WS_EMAIL_VERIFIED = 'workspace_email_verified';

	/** @var string Taxonomy request submitted. @since 3.3.0 */
	public const TYPE_TAXONOMY_REQUEST_SUBMITTED = 'taxonomy_request_submitted';

	/** @var string Taxonomy request approved. @since 3.3.0 */
	public const TYPE_TAXONOMY_REQUEST_APPROVED = 'taxonomy_request_approved';

	/** @var string Taxonomy request rejected. @since 3.3.0 */
	public const TYPE_TAXONOMY_REQUEST_REJECTED = 'taxonomy_request_rejected';

	/** @var string Taxonomy request merged. @since 3.3.0 */
	public const TYPE_TAXONOMY_REQUEST_MERGED = 'taxonomy_request_merged';

	/**
	 * Registered email types mapped to template slugs under templates/emails/.
	 *
	 * @return array<string, string>
	 */
	public static function email_templates(): array {
		$templates = array(
			self::TYPE_PROPERTY_IMPORT_SUCCESS => 'emails/property-import-success.php',
			self::TYPE_WS_REG_APPROVED         => 'emails/workspace-registration-approved.php',
			self::TYPE_WS_REG_REJECTED         => 'emails/workspace-registration-rejected.php',
			self::TYPE_WS_REG_SUSPENDED        => 'emails/workspace-registration-suspended.php',
			self::TYPE_WS_REG_ACTIVATED        => 'emails/workspace-registration-activated.php',

			// Sprint 24C. These share one template because they share one shape
			// (heading + message + optional details + one CTA). Map any of them to
			// a bespoke template via the hvnly_email_templates filter if needed.
			self::TYPE_AGENT_REGISTER          => 'emails/workspace-notice.php',
			self::TYPE_WS_REG_PENDING          => 'emails/workspace-notice.php',
			self::TYPE_WS_REG_ADMIN_ALERT      => 'emails/workspace-notice.php',
			self::TYPE_WS_REG_ARCHIVED         => 'emails/workspace-notice.php',
			self::TYPE_PASSWORD_RESET          => 'emails/workspace-notice.php',
			self::TYPE_PASSWORD_CHANGED        => 'emails/workspace-notice.php',
			self::TYPE_WS_INVITATION           => 'emails/workspace-notice.php',

			// Sprint 27D. Profile-updated confirmation reuses the same notice shape.
			self::TYPE_AGENT_PROFILE_UPDATE    => 'emails/workspace-notice.php',

			self::TYPE_PROPERTY_SUBMITTED      => 'emails/property-notice.php',
			self::TYPE_PROPERTY_APPROVED       => 'emails/property-notice.php',
			self::TYPE_PROPERTY_REJECTED       => 'emails/property-notice.php',
			self::TYPE_PROPERTY_PUBLISHED      => 'emails/property-notice.php',

			// Sprint 27D. Admin "new listing awaiting review" alert.
			self::TYPE_PROPERTY_ADMIN_ALERT    => 'emails/property-notice.php',

			// Identity verification (3.3.0) — reuse the workspace notice shape.
			self::TYPE_WS_EMAIL_VERIFY         => 'emails/workspace-notice.php',
			self::TYPE_WS_EMAIL_VERIFIED       => 'emails/workspace-notice.php',
			self::TYPE_TAXONOMY_REQUEST_SUBMITTED => 'emails/workspace-notice.php',
			self::TYPE_TAXONOMY_REQUEST_APPROVED  => 'emails/workspace-notice.php',
			self::TYPE_TAXONOMY_REQUEST_REJECTED  => 'emails/workspace-notice.php',
			self::TYPE_TAXONOMY_REQUEST_MERGED    => 'emails/workspace-notice.php',
		);

		/**
		 * Filter Havenlytics email type → template path map.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, string> $templates Email type to template slug map.
		 */
		return apply_filters( 'hvnly_email_templates', $templates );
	}

	/**
	 * Human-readable labels for settings UI and documentation.
	 *
	 * @return array<string, string>
	 */
	public static function template_labels(): array {
		$labels = array(
			// Keep the legacy type slug for stored settings and integrations; only
			// the user-facing workflow name changed in 3.3.0.
			self::TYPE_PROPERTY_IMPORT_SUCCESS => __( 'Property Setup Success', 'havenlytics' ),
			self::TYPE_AGENT_REGISTER          => __( 'Agent Registration', 'havenlytics' ),
			self::TYPE_AGENT_PROFILE_UPDATE    => __( 'Agent Profile Update', 'havenlytics' ),
			self::TYPE_PASSWORD_RESET          => __( 'Password Reset', 'havenlytics' ),
			self::TYPE_WS_REG_APPROVED         => __( 'Registration Approved', 'havenlytics' ),
			self::TYPE_WS_REG_REJECTED         => __( 'Registration Rejected', 'havenlytics' ),
			self::TYPE_WS_REG_SUSPENDED        => __( 'Account Suspended', 'havenlytics' ),
			self::TYPE_WS_REG_ACTIVATED        => __( 'Account Activated', 'havenlytics' ),
			self::TYPE_WS_REG_PENDING          => __( 'Registration Pending Approval', 'havenlytics' ),
			self::TYPE_WS_REG_ADMIN_ALERT      => __( 'Registration Awaiting Your Approval', 'havenlytics' ),
			self::TYPE_WS_REG_ARCHIVED         => __( 'Account Archived', 'havenlytics' ),
			self::TYPE_PASSWORD_CHANGED        => __( 'Password Changed', 'havenlytics' ),
			self::TYPE_WS_INVITATION           => __( 'Workspace Invitation', 'havenlytics' ),
			self::TYPE_PROPERTY_SUBMITTED      => __( 'Property Submitted', 'havenlytics' ),
			self::TYPE_PROPERTY_APPROVED       => __( 'Property Approved', 'havenlytics' ),
			self::TYPE_PROPERTY_REJECTED       => __( 'Property Rejected', 'havenlytics' ),
			self::TYPE_PROPERTY_PUBLISHED      => __( 'Property Published', 'havenlytics' ),
			self::TYPE_PROPERTY_ADMIN_ALERT    => __( 'New Property Awaiting Review', 'havenlytics' ),
			self::TYPE_WS_EMAIL_VERIFY         => __( 'Verify Your Email', 'havenlytics' ),
			self::TYPE_WS_EMAIL_VERIFIED       => __( 'Email Verified', 'havenlytics' ),
			self::TYPE_TAXONOMY_REQUEST_SUBMITTED => __( 'Taxonomy Request Submitted', 'havenlytics' ),
			self::TYPE_TAXONOMY_REQUEST_APPROVED  => __( 'Taxonomy Request Approved', 'havenlytics' ),
			self::TYPE_TAXONOMY_REQUEST_REJECTED  => __( 'Taxonomy Request Rejected', 'havenlytics' ),
			self::TYPE_TAXONOMY_REQUEST_MERGED    => __( 'Taxonomy Request Merged', 'havenlytics' ),
		);

		/**
		 * Filter Havenlytics email template labels.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, string> $labels Email type labels.
		 */
		return apply_filters( 'hvnly_email_template_labels', $labels );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
