<?php
/**
 * Havenlytics email settings reader.
 *
 * @package HvnlyNab\Email
 * @since   3.0.2
 */

namespace HvnlyNab\Email;

use HvnlyNab\Api\Type\Settings\DefaultSettingsData;

defined( 'ABSPATH' ) || exit;

/**
 * Reads email settings from the plugin settings API storage.
 *
 * @since 3.0.2
 */
final class EmailSettings {

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
		if ( null !== $group && EmailConstants::SETTINGS_GROUP !== $group ) {
			return;
		}

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

		if ( is_array( $stored ) && isset( $stored[ EmailConstants::SETTINGS_GROUP ] ) && is_array( $stored[ EmailConstants::SETTINGS_GROUP ] ) ) {
			$group = $stored[ EmailConstants::SETTINGS_GROUP ];
		}

		self::$cache = array_merge( $defaults, $group );

		return self::$cache;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		if ( class_exists( DefaultSettingsData::class ) ) {
			return DefaultSettingsData::get_default_email_settings();
		}

		return array(
			'hvnly_email_from_name'                    => '',
			'hvnly_email_from_address'                 => '',
			'hvnly_email_import_success_enabled'       => true,
			'hvnly_email_import_wizard_notify_default' => true,
			'hvnly_email_import_success_subject'       => '',
			'hvnly_email_import_success_intro'         => '',
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
	public static function import_success_enabled(): bool {
		return self::to_bool( self::get( 'hvnly_email_import_success_enabled', true ) );
	}

	/**
	 * Default checked state for import wizard email toggle.
	 *
	 * @return bool
	 */
	public static function import_wizard_notify_default(): bool {
		return self::to_bool( self::get( 'hvnly_email_import_wizard_notify_default', true ) );
	}

	/**
	 * @return string
	 */
	public static function get_from_name(): string {
		$name = sanitize_text_field( (string) self::get( 'hvnly_email_from_name', '' ) );

		if ( '' !== trim( $name ) ) {
			return $name;
		}

		return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * @return string
	 */
	public static function get_from_address(): string {
		$email = sanitize_email( (string) self::get( 'hvnly_email_from_address', '' ) );

		if ( is_email( $email ) ) {
			return $email;
		}

		return sanitize_email( (string) get_option( 'admin_email' ) );
	}

	/**
	 * @return string
	 */
	public static function get_import_success_subject(): string {
		return sanitize_text_field( (string) self::get( 'hvnly_email_import_success_subject', '' ) );
	}

	/**
	 * @return string
	 */
	public static function get_import_success_intro(): string {
		return sanitize_textarea_field( (string) self::get( 'hvnly_email_import_success_intro', '' ) );
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
