<?php
/**
 * Havenlytics email helper functions.
 *
 * @package Havenlytics
 * @since   3.0.2
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap email settings reader.
 *
 * @return void
 */
function hvnly_email_bootstrap(): void {
	if ( class_exists( \HvnlyNab\Email\EmailSettings::class ) ) {
		\HvnlyNab\Email\EmailSettings::init();
	}
}

add_action( 'plugins_loaded', 'hvnly_email_bootstrap', 6 );

/**
 * Send property import success email to the current admin user.
 *
 * @param int      $import_count       Imported property count.
 * @param int|null $user_id            Optional user ID (defaults to current user).
 * @param bool     $from_import_wizard When true, wizard opt-in overrides the global enable setting.
 * @return bool|\WP_Error
 */
function hvnly_send_property_import_success_email( int $import_count, ?int $user_id = null, bool $from_import_wizard = false ) {
	if ( ! class_exists( \HvnlyNab\Email\Notifiers\PropertyImportSuccessNotifier::class ) ) {
		return new WP_Error(
			'hvnly_email_module_missing',
			__( 'Email module is not available.', 'havenlytics' )
		);
	}

	$user_id = null !== $user_id ? absint( $user_id ) : get_current_user_id();
	$user    = $user_id > 0 ? get_userdata( $user_id ) : false;

	if ( ! $user instanceof WP_User ) {
		return new WP_Error(
			'hvnly_email_import_success_no_user',
			__( 'Unable to determine the email recipient.', 'havenlytics' )
		);
	}

	$notifier = new \HvnlyNab\Email\Notifiers\PropertyImportSuccessNotifier();

	return $notifier->send( $import_count, $user, $from_import_wizard );
}
