<?php
/**
 * Contact Agent settings reader.
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

use HvnlyNab\Api\Type\Settings\DefaultSettingsData;

defined( 'ABSPATH' ) || exit;

/**
 * Reads Contact Agent settings from the plugin settings API storage.
 *
 * @since 3.0.2
 */
final class ContactAgentSettings {

	/** @var string Settings group key inside hvnly_plugin_settings. */
	public const GROUP_KEY = 'contact-agent';

	/** @var array<string, mixed>|null */
	private static $cache = null;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'hvnly_settings_updated', array( __CLASS__, 'clear_cache' ), 5, 1 );
	}

	/**
	 * Clear cached settings.
	 *
	 * @param string|null $group Updated settings group.
	 * @return void
	 */
	public static function clear_cache( $group = null ): void {
		unset( $group );
		self::$cache = null;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$defaults = self::get_defaults();
		$stored   = get_option( 'hvnly_plugin_settings', array() );
		$group    = array();

		if ( is_array( $stored ) && isset( $stored[ self::GROUP_KEY ] ) && is_array( $stored[ self::GROUP_KEY ] ) ) {
			$group = $stored[ self::GROUP_KEY ];
		}

		self::$cache = array_merge( $defaults, $group );

		return self::$cache;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		if ( class_exists( DefaultSettingsData::class ) ) {
			return DefaultSettingsData::get_default_contact_agent_settings();
		}

		return array(
			'hvnly_contact_agent_enabled'            => true,
			'hvnly_contact_agent_notify_agent'       => true,
			'hvnly_contact_agent_notify_admin'       => true,
			'hvnly_contact_agent_notify_sender'      => true,
			'hvnly_contact_agent_admin_email'        => '',
			'hvnly_contact_agent_sender_subject'     => '',
			'hvnly_contact_agent_sender_intro'       => '',
			'hvnly_contact_agent_success_message'    => '',
			'hvnly_contact_agent_honeypot_enabled'   => true,
			'hvnly_contact_agent_rate_limit_max'     => ContactAgentConstants::RATE_LIMIT_MAX,
			'hvnly_contact_agent_rate_limit_window'  => ContactAgentConstants::RATE_LIMIT_WINDOW,
			'hvnly_workspace_registration_mode'     => 'open',
			'hvnly_workspace_default_role'          => 'hvnly_agent',
		);
	}

	/**
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$settings = self::get_all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return self::to_bool( self::get( 'hvnly_contact_agent_enabled', true ) );
	}

	/**
	 * @return bool
	 */
	public static function notify_agent_enabled(): bool {
		return self::to_bool( self::get( 'hvnly_contact_agent_notify_agent', true ) );
	}

	/**
	 * @return bool
	 */
	public static function notify_admin_enabled(): bool {
		return self::to_bool( self::get( 'hvnly_contact_agent_notify_admin', true ) );
	}

	/**
	 * @return bool
	 */
	public static function notify_sender_enabled(): bool {
		return self::to_bool( self::get( 'hvnly_contact_agent_notify_sender', true ) );
	}

	/**
	 * @return string
	 */
	public static function get_sender_subject(): string {
		return sanitize_text_field( (string) self::get( 'hvnly_contact_agent_sender_subject', '' ) );
	}

	/**
	 * @return string
	 */
	public static function get_sender_intro(): string {
		return sanitize_textarea_field( (string) self::get( 'hvnly_contact_agent_sender_intro', '' ) );
	}

	/**
	 * @return string
	 */
	public static function get_success_message(): string {
		return sanitize_textarea_field( (string) self::get( 'hvnly_contact_agent_success_message', '' ) );
	}

	/**
	 * @return bool
	 */
	public static function is_honeypot_enabled(): bool {
		return self::to_bool( self::get( 'hvnly_contact_agent_honeypot_enabled', true ) );
	}

	/**
	 * @return string
	 */
	public static function get_admin_email(): string {
		$email = sanitize_email( (string) self::get( 'hvnly_contact_agent_admin_email', '' ) );

		if ( is_email( $email ) ) {
			return $email;
		}

		return sanitize_email( (string) get_option( 'admin_email' ) );
	}

	/**
	 * @return int
	 */
	public static function get_rate_limit_max(): int {
		return max( 1, absint( self::get( 'hvnly_contact_agent_rate_limit_max', ContactAgentConstants::RATE_LIMIT_MAX ) ) );
	}

	/**
	 * @return int
	 */
	public static function get_rate_limit_window(): int {
		return max( 60, absint( self::get( 'hvnly_contact_agent_rate_limit_window', ContactAgentConstants::RATE_LIMIT_WINDOW ) ) );
	}

	/**
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (bool) (int) $value;
		}

		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}
}
