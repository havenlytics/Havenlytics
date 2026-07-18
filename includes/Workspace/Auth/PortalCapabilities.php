<?php
/**
 * Portal capability and role constants.
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Capability catalog for Workspace authorization.
 *
 * Caps may not be assigned to custom roles yet; PortalAuthorization
 * soft-grants based on identity until role registration lands.
 *
 * @since 3.2.0
 */
final class PortalCapabilities {

	public const PORTAL_ACCESS           = 'hvnly_portal_access';
	public const VIEW_OWN_PROFILE        = 'hvnly_view_own_profile';
	public const EDIT_OWN_PROFILE        = 'hvnly_edit_own_profile';
	public const VIEW_ASSIGNED_PROPERTIES = 'hvnly_view_assigned_properties';
	public const CREATE_PROPERTIES       = 'hvnly_create_properties';
	public const EDIT_ASSIGNED_PROPERTIES = 'hvnly_edit_assigned_properties';
	public const EDIT_PUBLISHED_PROPERTIES = 'hvnly_edit_published_properties';
	public const SUBMIT_PROPERTIES       = 'hvnly_submit_properties';
	public const APPROVE_LISTINGS        = 'hvnly_approve_listings';
	public const VIEW_OWN_INQUIRIES      = 'hvnly_view_own_inquiries';
	public const REPLY_INQUIRIES         = 'hvnly_reply_inquiries';
	public const VIEW_AGENCY_INQUIRIES   = 'hvnly_view_agency_inquiries';
	public const VIEW_AGENCY_PROPERTIES  = 'hvnly_view_agency_properties';
	public const VIEW_ANALYTICS          = 'hvnly_view_analytics';

	/** @var string Workspace role slug: administrator */
	public const ROLE_ADMINISTRATOR = 'administrator';

	/** @var string */
	public const ROLE_AGENCY_OWNER = 'agency_owner';

	/** @var string */
	public const ROLE_AGENCY_MANAGER = 'agency_manager';

	/** @var string */
	public const ROLE_AGENT = 'agent';

	/** @var string Future buyer portal persona */
	public const ROLE_BUYER = 'buyer';

	/** @var string Logged-in without linked agent */
	public const ROLE_UNLINKED = 'unlinked';

	/** @var string */
	public const ROLE_GUEST = 'guest';

	/**
	 * WordPress role slug for Workspace agents (display name: Agent).
	 *
	 * @var string
	 */
	public const WP_ROLE_AGENT = 'hvnly_agent';

	/**
	 * All portal capabilities (for admin priming).
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::PORTAL_ACCESS,
			self::VIEW_OWN_PROFILE,
			self::EDIT_OWN_PROFILE,
			self::VIEW_ASSIGNED_PROPERTIES,
			self::CREATE_PROPERTIES,
			self::EDIT_ASSIGNED_PROPERTIES,
			self::EDIT_PUBLISHED_PROPERTIES,
			self::SUBMIT_PROPERTIES,
			self::APPROVE_LISTINGS,
			self::VIEW_OWN_INQUIRIES,
			self::REPLY_INQUIRIES,
			self::VIEW_AGENCY_INQUIRIES,
			self::VIEW_AGENCY_PROPERTIES,
			self::VIEW_ANALYTICS,
		);
	}

	/**
	 * Caps granted to the WordPress Agent role (no admin / approve / agency-wide).
	 *
	 * @return string[]
	 */
	public static function agent_role_caps(): array {
		return array(
			'read',
			'upload_files',
			self::PORTAL_ACCESS,
			self::VIEW_OWN_PROFILE,
			self::EDIT_OWN_PROFILE,
			self::VIEW_ASSIGNED_PROPERTIES,
			self::CREATE_PROPERTIES,
			self::EDIT_ASSIGNED_PROPERTIES,
			self::SUBMIT_PROPERTIES,
			self::VIEW_OWN_INQUIRIES,
			self::REPLY_INQUIRIES,
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
