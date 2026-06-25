<?php
/**
 * Renders Havenlytics email templates.
 *
 * @package HvnlyNab\Email
 * @since   3.0.2
 */

namespace HvnlyNab\Email;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
class EmailRenderer {

	/**
	 * Render an email body by registered type.
	 *
	 * @param string               $email_type One of EmailConstants::TYPE_*.
	 * @param array<string, mixed> $context    Template context.
	 * @return string
	 */
	public function render( string $email_type, array $context ): string {
		$templates = EmailConstants::email_templates();
		$template  = isset( $templates[ $email_type ] ) ? (string) $templates[ $email_type ] : '';

		if ( '' === $template || ! function_exists( 'hvnly_get_template_html' ) ) {
			return '';
		}

		/**
		 * Filter email template context before render.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, mixed> $context    Template variables.
		 * @param string               $template   Template slug.
		 * @param string               $email_type Email type slug.
		 */
		$context = apply_filters( 'hvnly_email_render_context', $context, $template, $email_type );

		$html = hvnly_get_template_html( $template, $context );

		/**
		 * Filter rendered Havenlytics email HTML.
		 *
		 * @since 3.0.2
		 *
		 * @param string               $html       Rendered HTML.
		 * @param string               $template   Template slug.
		 * @param array<string, mixed> $context    Template variables.
		 * @param string               $email_type Email type slug.
		 */
		return (string) apply_filters( 'hvnly_email_html', $html, $template, $context, $email_type );
	}
}
