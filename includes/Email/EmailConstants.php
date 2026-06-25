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

	/**
	 * Registered email types mapped to template slugs under templates/emails/.
	 *
	 * @return array<string, string>
	 */
	public static function email_templates(): array {
		$templates = array(
			self::TYPE_PROPERTY_IMPORT_SUCCESS => 'emails/property-import-success.php',
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
			self::TYPE_PROPERTY_IMPORT_SUCCESS => __( 'Property Import Success', 'havenlytics' ),
			self::TYPE_AGENT_REGISTER          => __( 'Agent Registration', 'havenlytics' ),
			self::TYPE_AGENT_PROFILE_UPDATE    => __( 'Agent Profile Update', 'havenlytics' ),
			self::TYPE_PASSWORD_RESET          => __( 'Password Reset', 'havenlytics' ),
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
