<?php
/**
 * Sends property import success email to the importing user and site administrator.
 *
 * @package HvnlyNab\Email\Notifiers
 * @since   3.0.2
 */

namespace HvnlyNab\Email\Notifiers;

use HvnlyNab\Email\EmailConstants;
use HvnlyNab\Email\EmailContextBuilder;
use HvnlyNab\Email\EmailRenderer;
use HvnlyNab\Email\EmailSettings;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
class PropertyImportSuccessNotifier {

	/**
	 * @var EmailRenderer
	 */
	private $renderer;

	/**
	 * @param EmailRenderer|null $renderer Optional renderer for tests.
	 */
	public function __construct( ?EmailRenderer $renderer = null ) {
		$this->renderer = $renderer ?? new EmailRenderer();
	}

	/**
	 * Send import success email when enabled.
	 *
	 * @param int      $import_count        Imported property count.
	 * @param \WP_User $user                Recipient admin user.
	 * @param bool     $from_import_wizard  When true, wizard opt-in overrides the global enable setting.
	 * @return bool|\WP_Error
	 */
	public function send( int $import_count, \WP_User $user, bool $from_import_wizard = false ) {
		if ( ! $from_import_wizard && ! EmailSettings::import_success_enabled() ) {
			return new \WP_Error(
				'hvnly_email_import_success_disabled',
				__( 'Property setup success emails are disabled in Email settings.', 'havenlytics' )
			);
		}

		$recipients = $this->resolve_import_success_recipients( $user );
		if ( empty( $recipients ) ) {
			return new \WP_Error(
				'hvnly_email_import_success_invalid_recipient',
				__( 'Unable to send the property setup email — invalid user email address.', 'havenlytics' )
			);
		}

		$context = EmailContextBuilder::build_import_success( $import_count, $user );
		$subject = $this->resolve_subject( $context );

		$body = $this->renderer->render( EmailConstants::TYPE_PROPERTY_IMPORT_SUCCESS, $context );

		if ( '' === trim( $body ) ) {
			return new \WP_Error(
				'hvnly_email_import_success_empty_body',
				__( 'Unable to render the import success email.', 'havenlytics' )
			);
		}

		$headers = $this->build_headers();

		foreach ( $recipients as $recipient ) {
			$sent = wp_mail( $recipient, $subject, $body, $headers );

			if ( ! $sent ) {
				return new \WP_Error(
					'hvnly_email_import_success_send_failed',
					__( 'Import completed but the success email could not be sent. Check your site mail settings.', 'havenlytics' )
				);
			}
		}

		/**
		 * Fires after a property import success email is sent.
		 *
		 * @since 3.0.2
		 *
		 * @param int                  $import_count Import count.
		 * @param \WP_User             $user         Recipient user.
		 * @param array<string, mixed> $context      Email context.
		 */
		do_action( 'hvnly_email_import_success_sent', $import_count, $user, $context );

		return true;
	}

	/**
	 * Importing user first, then site admin when different (case-insensitive).
	 *
	 * @param \WP_User $user Importing user.
	 * @return string[]
	 */
	private function resolve_import_success_recipients( \WP_User $user ): array {
		$recipients = array();

		$importing_email = sanitize_email( $user->user_email );
		if ( is_email( $importing_email ) ) {
			$recipients[] = $importing_email;
		}

		$admin_email = sanitize_email( (string) get_option( 'admin_email' ) );
		if ( is_email( $admin_email ) ) {
			$normalized = array_map( 'strtolower', $recipients );
			if ( ! in_array( strtolower( $admin_email ), $normalized, true ) ) {
				$recipients[] = $admin_email;
			}
		}

		return $recipients;
	}

	/**
	 * @param array<string, mixed> $context Email context.
	 * @return string
	 */
	private function resolve_subject( array $context ): string {
		$custom = EmailSettings::get_import_success_subject();
		if ( '' !== trim( $custom ) ) {
			$subject = EmailContextBuilder::replace_merge_tags( $custom, $context );
		} else {
			$subject = sprintf(
				/* translators: 1: site name */
				__( 'Property setup complete — %s', 'havenlytics' ),
				(string) ( $context['site_name'] ?? get_bloginfo( 'name' ) )
			);
		}

		/**
		 * Filter property import success email subject.
		 *
		 * @since 3.0.2
		 *
		 * @param string               $subject Subject line.
		 * @param array<string, mixed> $context Email context.
		 */
		return (string) apply_filters( 'hvnly_email_import_success_subject', $subject, $context );
	}

	/**
	 * @return string[]
	 */
	private function build_headers(): array {
		/*
		 * Sprint 24C: shared header builder. The previous version fell back to
		 * get_option('admin_email') for the From: address, which on most sites is a
		 * @gmail.com / @outlook.com address — forging it from the site's own server
		 * fails DMARC and gets the mail spam-foldered. EmailHeaders only sets From:
		 * when an address has been explicitly configured.
		 */
		$headers = \HvnlyNab\Email\EmailHeaders::base();

		/**
		 * Filter property import success email headers.
		 *
		 * @since 3.0.2
		 *
		 * @param string[] $headers Email headers.
		 */
		return apply_filters( 'hvnly_email_import_success_headers', $headers );
	}
}
