<?php
/**
 * Agent module constants.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Central constants for the Agent Management System.
 *
 * @since 3.0.2
 */
final class AgentConstants {

	/** @var string Agent custom post type slug. */
	public const POST_TYPE = 'hvnly_agent';

	/** @var string Future agency taxonomy slug (Phase 6 — not registered yet). */
	public const TAXONOMY_AGENCY = 'hvnly_agent_agency';

	/** @var string Agent profile meta prefix. */
	public const META_PREFIX = '_hvnly_agent_';

	/** @var string Contact email. */
	public const META_EMAIL = '_hvnly_agent_email';

	/** @var string Phone number. */
	public const META_PHONE = '_hvnly_agent_phone';

	/** @var string WhatsApp number. */
	public const META_WHATSAPP = '_hvnly_agent_whatsapp';

	/** @var string Job title / position. */
	public const META_POSITION = '_hvnly_agent_position';

	/** @var string Company / brokerage name. */
	public const META_COMPANY = '_hvnly_agent_company';

	/** @var string Website URL. */
	public const META_WEBSITE = '_hvnly_agent_website';

	/** @var string Facebook profile URL. */
	public const META_FACEBOOK = '_hvnly_agent_facebook';

	/** @var string LinkedIn profile URL. */
	public const META_LINKEDIN = '_hvnly_agent_linkedin';

	/** @var string Instagram profile URL. */
	public const META_INSTAGRAM = '_hvnly_agent_instagram';

	/** @var string Mobile phone number. */
	public const META_MOBILE = '_hvnly_agent_mobile';

	/** @var string Fax number. */
	public const META_FAX = '_hvnly_agent_fax';

	/** @var string Office phone number. */
	public const META_OFFICE = '_hvnly_agent_office';

	/** @var string Business address. */
	public const META_ADDRESS = '_hvnly_agent_address';

	/** @var string Real estate license number. */
	public const META_LICENSE = '_hvnly_agent_license';

	/** @var string Vimeo profile URL. */
	public const META_VIMEO = '_hvnly_agent_vimeo';

	/** @var string Twitter / X profile URL. */
	public const META_TWITTER = '_hvnly_agent_twitter';

	/** @var string Pinterest profile URL. */
	public const META_PINTEREST = '_hvnly_agent_pinterest';

	/** @var string YouTube channel URL. */
	public const META_YOUTUBE = '_hvnly_agent_youtube';

	/** @var string TikTok profile URL. */
	public const META_TIKTOK = '_hvnly_agent_tiktok';

	/** @var string Optional linked WordPress user ID (legacy bridge). */
	public const META_LINKED_USER_ID = '_hvnly_agent_linked_user_id';

	/** @var string Extension meta bucket for future CRM / webhook fields. */
	public const META_EXTENSIONS = '_hvnly_agent_extensions';

	/** @var string Availability status stored in extensions.availability. */
	public const AVAILABILITY_AVAILABLE = 'available';

	/** @var string */
	public const AVAILABILITY_BUSY = 'busy';

	/** @var string */
	public const AVAILABILITY_AWAY = 'away';

	/** @var string */
	public const AVAILABILITY_OFFLINE = 'offline';

	/**
	 * Registered agent availability status slugs.
	 *
	 * @return string[]
	 */
	public static function availability_statuses(): array {
		return array(
			self::AVAILABILITY_AVAILABLE,
			self::AVAILABILITY_BUSY,
			self::AVAILABILITY_AWAY,
			self::AVAILABILITY_OFFLINE,
		);
	}

	/** @var string Property meta: assigned Agent CPT post IDs (ordered array). */
	public const META_PROPERTY_AGENTS = '_hvnly_property_agents';

	/** @var string Legacy property meta: single WordPress user ID. */
	public const META_PROPERTY_AGENT_LEGACY = '_hvnly_property_agent';

	/** @var string Hide sidebar Property Agent widget when using metabox agents section. */
	public const META_HIDE_SIDEBAR_AGENT_WIDGET = '_hvnly_hide_sidebar_agent_widget';

	/** @var string Property post type slug. */
	public const PROPERTY_POST_TYPE = 'hvnly_property';

	/**
	 * Registered profile meta keys (excluding post title, content, thumbnail).
	 *
	 * @return string[]
	 */
	public static function profile_meta_keys(): array {
		return array(
			self::META_EMAIL,
			self::META_PHONE,
			self::META_MOBILE,
			self::META_FAX,
			self::META_OFFICE,
			self::META_WHATSAPP,
			self::META_POSITION,
			self::META_COMPANY,
			self::META_ADDRESS,
			self::META_LICENSE,
			self::META_WEBSITE,
			self::META_VIMEO,
			self::META_FACEBOOK,
			self::META_TWITTER,
			self::META_PINTEREST,
			self::META_INSTAGRAM,
			self::META_YOUTUBE,
			self::META_LINKEDIN,
			self::META_TIKTOK,
			self::META_LINKED_USER_ID,
			self::META_EXTENSIONS,
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
