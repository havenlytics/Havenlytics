<?php
/**
 * Contact Agent module constants.
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

defined( 'ABSPATH' ) || exit;

/**
 * Central constants for the Contact Agent feature (scaffold — no runtime behavior in Phase 1).
 *
 * @since 3.0.2
 */
final class ContactAgentConstants {

	/** @var string Settings option key (Phase 6). */
	public const SETTINGS_OPTION = 'hvnly_contact_agent_settings';

	/** @var string Custom table suffix without prefix (Phase 4). */
	public const INQUIRIES_TABLE = 'hvnly_inquiries';

	/** @var string Custom table suffix for admin reply thread. */
	public const INQUIRY_REPLIES_TABLE = 'hvnly_inquiry_replies';

	/** @var string Migration version that creates inquiries table (Phase 4). */
	public const INQUIRIES_MIGRATION_VERSION = '3.0.2';

	/** @var string Transient key for cached unread inquiry count. */
	public const UNREAD_COUNT_TRANSIENT = 'hvnly_ca_unread_inquiry_count';

	/** @var string Default inquiry status. */
	public const STATUS_NEW = 'new';

	/** @var string Read inquiry status. */
	public const STATUS_READ = 'read';

	/** @var string Replied inquiry status. */
	public const STATUS_REPLIED = 'replied';

	/** @var string Archived inquiry status. */
	public const STATUS_ARCHIVED = 'archived';

	/** @var string[] Allowed inquiry statuses. */
	public const INQUIRY_STATUSES = array(
		self::STATUS_NEW,
		self::STATUS_READ,
		self::STATUS_REPLIED,
		self::STATUS_ARCHIVED,
	);

	/** @var string Outbound admin reply direction. */
	public const REPLY_DIRECTION_OUTBOUND = 'outbound';

	/** @var string Inbound reply direction (reserved for future portal). */
	public const REPLY_DIRECTION_INBOUND = 'inbound';

	/** @var string[] Allowed reply directions. */
	public const REPLY_DIRECTIONS = array(
		self::REPLY_DIRECTION_OUTBOUND,
		self::REPLY_DIRECTION_INBOUND,
	);

	/** @var int Minimum characters for an admin reply. */
	public const REPLY_MESSAGE_MIN_LENGTH = 10;

	/** @var int Maximum characters for an admin reply. */
	public const REPLY_MESSAGE_MAX_LENGTH = 5000;

	/** @var int Max admin replies per user per inquiry per window. */
	public const REPLY_RATE_LIMIT_MAX = 10;

	/** @var int Admin reply rate limit window in seconds. */
	public const REPLY_RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;

	/** @var string Transient prefix for admin reply rate limiting. */
	public const REPLY_RATE_LIMIT_TRANSIENT_PREFIX = 'hvnly_ca_reply_rate_';

	/** @var string AJAX action for inquiry submission (Phase 3). */
	public const AJAX_SUBMIT_ACTION = 'hvnly_submit_contact_agent';

	/** @var string Nonce action — reuses existing frontend AJAX nonce. */
	public const AJAX_NONCE_ACTION = 'hvnly_ajax_request';

	/** @var string Transient prefix for IP rate limiting. */
	public const RATE_LIMIT_TRANSIENT_PREFIX = 'hvnly_ca_rate_';

	/** @var int Default max submissions per IP per window. */
	public const RATE_LIMIT_MAX = 5;

	/** @var int Default rate limit window in seconds. */
	public const RATE_LIMIT_WINDOW = HOUR_IN_SECONDS;

	/** @var string Default inquiry source slug. */
	public const DEFAULT_SOURCE = 'contact_agent_modal';

	/** @var string Inquiry source for agent profile page forms. */
	public const SOURCE_AGENT_PROFILE = 'contact_agent_profile';

	/** @var string Inquiry agent reference — Agent CPT post ID. */
	public const AGENT_TYPE_CPT = 'cpt';

	/** @var string Inquiry agent reference — WordPress user ID. */
	public const AGENT_TYPE_USER = 'user';

	/** @var string Email type: notify assigned agent. */
	public const EMAIL_TYPE_AGENT = 'agent';

	/** @var string Email type: admin copy. */
	public const EMAIL_TYPE_ADMIN = 'admin';

	/** @var string Email type: instant auto-reply to inquiry submitter. */
	public const EMAIL_TYPE_SENDER = 'sender';

	/** @var string Email type: admin reply from inquiry inbox. */
	public const EMAIL_TYPE_ADMIN_REPLY = 'admin_reply';

	/**
	 * Registered inquiry email types mapped to template slugs under templates/contact-agent/emails/.
	 *
	 * @return array<string, string>
	 */
	public static function email_templates(): array {
		$templates = array(
			self::EMAIL_TYPE_AGENT       => 'contact-agent/emails/agent-notification.php',
			self::EMAIL_TYPE_ADMIN       => 'contact-agent/emails/admin-notification.php',
			self::EMAIL_TYPE_SENDER      => 'contact-agent/emails/sender-autoreply.php',
			self::EMAIL_TYPE_ADMIN_REPLY => 'contact-agent/emails/admin-reply.php',
		);

		/**
		 * Filter Contact Agent email type → template path map.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, string> $templates Email type to template slug map.
		 */
		return apply_filters( 'hvnly_contact_agent_email_templates', $templates );
	}

	/**
	 * Human-readable inquiry source label for admin UI.
	 *
	 * @since 3.0.3
	 *
	 * @param string $source Stored source slug.
	 * @return string
	 */
	public static function source_label( string $source ): string {
		$source = sanitize_key( $source );

		$labels = array(
			self::DEFAULT_SOURCE        => __( 'Contact Agent modal', 'havenlytics' ),
			self::SOURCE_AGENT_PROFILE  => __( 'Agent profile page', 'havenlytics' ),
		);

		if ( isset( $labels[ $source ] ) ) {
			return $labels[ $source ];
		}

		if ( '' === $source ) {
			return __( 'Unknown', 'havenlytics' );
		}

		return ucwords( str_replace( array( '_', '-' ), ' ', $source ) );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
