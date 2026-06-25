<?php
/**
 * Contact Agent modal template.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-property
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_property_id    = isset( $args['property_id'] ) ? absint( $args['property_id'] ) : get_the_ID();
$hvnly_property_title = isset( $args['property_title'] ) ? (string) $args['property_title'] : get_the_title( $hvnly_property_id );
$hvnly_agent          = isset( $args['agent'] ) && is_array( $args['agent'] ) ? $args['agent'] : array();
$hvnly_agents         = isset( $args['agents'] ) && is_array( $args['agents'] ) ? $args['agents'] : array();

if ( empty( $hvnly_agents ) && ! empty( $hvnly_agent ) ) {
	$hvnly_agents = array( $hvnly_agent );
}

$hvnly_primary      = ! empty( $hvnly_agents[0] ) ? $hvnly_agents[0] : $hvnly_agent;
$hvnly_agent_name   = isset( $hvnly_primary['name'] ) ? (string) $hvnly_primary['name'] : __( 'Property Agent', 'havenlytics' );
$hvnly_agent_avatar = isset( $hvnly_primary['avatar'] ) ? (string) $hvnly_primary['avatar'] : '';
$hvnly_agent_role   = isset( $hvnly_primary['position'] ) ? (string) $hvnly_primary['position'] : '';
$hvnly_primary_id   = isset( $hvnly_primary['id'] ) ? absint( $hvnly_primary['id'] ) : 0;
$hvnly_availability = function_exists( 'hvnly_get_agent_availability_status' )
	? hvnly_get_agent_availability_status( $hvnly_primary_id, $hvnly_primary )
	: 'available';

if ( ! $hvnly_property_id ) {
	return;
}

$hvnly_modal_title = sprintf(
	/* translators: %s: agent name */
	__( 'Contact %s', 'havenlytics' ),
	$hvnly_agent_name
);
?>
<div
	class="hvnly-contact-agent__modal hvnly-property-single__fancybox-popup"
	id="hvnlyContactAgentModal"
	role="dialog"
	aria-modal="true"
	aria-labelledby="hvnlyContactAgentModalTitle"
	aria-describedby="hvnlyContactAgentModalDesc"
	hidden
>
	<div class="hvnly-contact-agent__overlay js-hvnly-contact-agent-overlay" tabindex="-1"></div>

	<div class="hvnly-contact-agent__dialog">
		<button
			type="button"
			class="hvnly-contact-agent__close hvnly-property-single__fancybox-close js-hvnly-contact-agent-close"
			aria-label="<?php esc_attr_e( 'Close contact form', 'havenlytics' ); ?>"
		>
			<svg class="hvnly-icon hvnly-icon-thin" aria-hidden="true"><use xlink:href="#hvnly-times"></use></svg>
		</button>

		<header class="hvnly-contact-agent__header">
			<?php if ( ! empty( $hvnly_agent_avatar ) || ! empty( $hvnly_agent_name ) ) : ?>
				<div class="hvnly-contact-agent__agent-chip js-hvnly-contact-agent-chip">
					<?php if ( ! empty( $hvnly_agent_avatar ) ) : ?>
						<img
							class="hvnly-contact-agent__agent-avatar js-hvnly-contact-agent-chip-avatar"
							src="<?php echo esc_url( $hvnly_agent_avatar ); ?>"
							alt=""
							width="48"
							height="48"
							loading="lazy"
							decoding="async"
						/>
					<?php else : ?>
						<img
							class="hvnly-contact-agent__agent-avatar js-hvnly-contact-agent-chip-avatar"
							src=""
							alt=""
							width="48"
							height="48"
							hidden
						/>
					<?php endif; ?>
					<div class="hvnly-contact-agent__agent-meta">
						<span class="hvnly-contact-agent__agent-role js-hvnly-contact-agent-chip-role"><?php echo esc_html( $hvnly_agent_role ); ?></span>
						<span class="hvnly-contact-agent__agent-name js-hvnly-contact-agent-chip-name"><?php echo esc_html( $hvnly_agent_name ); ?></span>
						<?php if ( function_exists( 'hvnly_render_agent_availability_badge' ) ) : ?>
							<?php
							hvnly_render_agent_availability_badge(
								array(
									'agent_id' => $hvnly_primary_id,
									'agent'    => $hvnly_primary,
									'context'  => 'inline',
									'echo'     => true,
								)
							);
							?>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
			<h2 id="hvnlyContactAgentModalTitle" class="hvnly-contact-agent__title js-hvnly-contact-agent-modal-title"><?php echo esc_html( $hvnly_modal_title ); ?></h2>
			<p id="hvnlyContactAgentModalDesc" class="hvnly-contact-agent__subtitle">
				<?php
				if ( $hvnly_property_title ) {
					printf(
						/* translators: %s: property title */
						esc_html__( 'Regarding: %s', 'havenlytics' ),
						esc_html( $hvnly_property_title )
					);
				} else {
					esc_html_e( 'Send a message about this property.', 'havenlytics' );
				}
				?>
			</p>
		</header>

		<div class="hvnly-contact-agent__body">
			<?php
			hvnly_get_template_part(
				'single-property/partials/contact-agent-form',
				null,
				array(
					'property_id'    => $hvnly_property_id,
					'property_title' => $hvnly_property_title,
					'agent'          => $hvnly_primary,
					'agents'         => $hvnly_agents,
				)
			);
			?>
		</div>
	</div>
</div>
