<?php

/**

 * Renders Contact Agent email templates.

 *

 * @package HvnlyNab\ContactAgent

 * @since   3.0.2

 */



namespace HvnlyNab\ContactAgent;



defined( 'ABSPATH' ) || exit;



/**

 * Template renderer for all Contact Agent transactional emails.

 *

 * @since 3.0.2

 */

class InquiryEmailRenderer {



	/**

	 * Render an email body by registered type.

	 *

	 * @param string               $email_type One of ContactAgentConstants::EMAIL_TYPE_*.

	 * @param array<string, mixed> $context    Template context.

	 * @return string

	 */

	public function render( string $email_type, array $context ): string {

		$templates = ContactAgentConstants::email_templates();

		$template = isset( $templates[ $email_type ] ) ? (string) $templates[ $email_type ] : '';

		if ( '' === $template ) {

			return '';

		}

		return $this->render_template( $template, $context, $email_type );
	}



	/**

	 * Render the agent notification email body.

	 *

	 * @param array<string, mixed> $context Template context.

	 * @return string

	 */

	public function render_agent_body( array $context ): string {

		return $this->render( ContactAgentConstants::EMAIL_TYPE_AGENT, $context );
	}



	/**

	 * Render the admin notification email body.

	 *

	 * @param array<string, mixed> $context Template context.

	 * @return string

	 */

	public function render_admin_body( array $context ): string {

		return $this->render( ContactAgentConstants::EMAIL_TYPE_ADMIN, $context );
	}



	/**

	 * Render the submitter auto-reply email body.

	 *

	 * @param array<string, mixed> $context Template context.

	 * @return string

	 */

	public function render_sender_body( array $context ): string {

		return $this->render( ContactAgentConstants::EMAIL_TYPE_SENDER, $context );
	}



	/**

	 * @param string               $template  Template path relative to templates/.

	 * @param array<string, mixed> $context   Template variables.

	 * @param string               $email_type Email type slug.

	 * @return string

	 */

	private function render_template( string $template, array $context, string $email_type = '' ): string {

		if ( ! function_exists( 'hvnly_get_template_html' ) ) {

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

		$context = apply_filters( 'hvnly_contact_agent_email_render_context', $context, $template, $email_type );

		$html = hvnly_get_template_html( $template, $context );

		/**

		 * Filter rendered Contact Agent email HTML.

		 *

		 * @since 3.0.2

		 *

		 * @param string               $html       Rendered HTML.

		 * @param string               $template   Template slug.

		 * @param array<string, mixed> $context    Template variables.

		 * @param string               $email_type Email type slug.

		 */

		return (string) apply_filters( 'hvnly_contact_agent_email_html', $html, $template, $context, $email_type );
	}
}
