<?php
/**
 * Property sidebar agent + contact card.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-property/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use HvnlyNab\Agent\PropertyAgentWidgetRenderer;

$hvnly_property_id = isset( $args['property_id'] ) ? absint( $args['property_id'] ) : get_the_ID();
$hvnly_instance    = isset( $args['instance'] ) && is_array( $args['instance'] ) ? $args['instance'] : array();
$hvnly_agents      = isset( $args['agents'] ) && is_array( $args['agents'] ) ? $args['agents'] : array();
$hvnly_widget_id   = isset( $args['widget_id'] ) ? sanitize_html_class( (string) $args['widget_id'] ) : wp_unique_id( 'hvnly-agent-sidebar-' );

if ( ! $hvnly_property_id || empty( $hvnly_agents ) ) {
	return;
}

$hvnly_primary       = $hvnly_agents[0];
$hvnly_is_fallback   = ! empty( $hvnly_primary['is_fallback'] );
$hvnly_is_multi      = ! $hvnly_is_fallback && count( $hvnly_agents ) > 1;
$hvnly_show_form     = function_exists( 'hvnly_is_contact_agent_enabled' ) && hvnly_is_contact_agent_enabled();
$hvnly_phone         = isset( $hvnly_primary['phone'] ) ? (string) $hvnly_primary['phone'] : '';
$hvnly_email         = isset( $hvnly_primary['email'] ) ? (string) $hvnly_primary['email'] : '';
$hvnly_whatsapp      = isset( $hvnly_primary['whatsapp'] ) ? (string) $hvnly_primary['whatsapp'] : '';
$hvnly_name          = isset( $hvnly_primary['name'] ) ? (string) $hvnly_primary['name'] : '';
$hvnly_position      = isset( $hvnly_primary['position'] ) ? (string) $hvnly_primary['position'] : '';
$hvnly_avatar        = isset( $hvnly_primary['avatar'] ) ? (string) $hvnly_primary['avatar'] : '';
$hvnly_agent_id      = isset( $hvnly_primary['id'] ) ? absint( $hvnly_primary['id'] ) : 0;
if ( ! $hvnly_agent_id && ! empty( $hvnly_primary['user_id'] ) ) {
	$hvnly_agent_id = absint( $hvnly_primary['user_id'] );
}

if ( $hvnly_is_fallback ) {
	$hvnly_show_phone    = false;
	$hvnly_show_whatsapp = false;
	$hvnly_show_social   = false;
	$hvnly_show_email    = ! empty( $hvnly_email );
	$hvnly_show_position = ! $hvnly_show_email && ! empty( $hvnly_position );
} else {
	$hvnly_show_phone    = ! empty( $hvnly_instance['show_phone'] );
	$hvnly_show_email    = ! empty( $hvnly_instance['show_email'] );
	$hvnly_show_whatsapp = ! empty( $hvnly_instance['show_whatsapp'] ) && ! empty( $hvnly_whatsapp );
	$hvnly_social_links  = PropertyAgentWidgetRenderer::resolve_agent_social_links( $hvnly_primary );
	$hvnly_show_social   = ! empty( $hvnly_instance['show_social'] ) && ! empty( $hvnly_social_links );
	$hvnly_show_position = ! empty( $hvnly_position );
}
?>
<aside
	class="hvnly-agent-sidebar<?php echo $hvnly_is_fallback ? ' hvnly-agent-sidebar--fallback' : ''; ?>"
	id="<?php echo esc_attr( $hvnly_widget_id ); ?>"
	data-agent-count="<?php echo esc_attr( (string) count( $hvnly_agents ) ); ?>"
>
	<div class="hvnly-agent-sidebar__card">
		<div class="hvnly-agent-sidebar__header">
			<?php if ( $hvnly_avatar ) : ?>
				<img class="hvnly-agent-sidebar__avatar" src="<?php echo esc_url( $hvnly_avatar ); ?>" alt="" width="96" height="96" loading="lazy" decoding="async" />
			<?php else : ?>
				<div class="hvnly-agent-sidebar__avatar hvnly-agent-sidebar__avatar--placeholder" aria-hidden="true"><i class="fas fa-user-circle"></i></div>
			<?php endif; ?>

			<div class="hvnly-agent-sidebar__meta">
				<?php if ( $hvnly_is_multi ) : ?>
					<label class="screen-reader-text" for="<?php echo esc_attr( $hvnly_widget_id ); ?>-agent-select"><?php esc_html_e( 'Select agent', 'havenlytics' ); ?></label>
					<select id="<?php echo esc_attr( $hvnly_widget_id ); ?>-agent-select" class="hvnly-agent-sidebar__agent-select js-hvnly-sidebar-agent-select">
						<?php foreach ( $hvnly_agents as $hvnly_option ) : ?>
							<?php
							if ( empty( $hvnly_option['name'] ) ) {
								continue;
							}
							$hvnly_opt_id = isset( $hvnly_option['id'] ) ? absint( $hvnly_option['id'] ) : 0;
							if ( ! $hvnly_opt_id && ! empty( $hvnly_option['user_id'] ) ) {
								$hvnly_opt_id = absint( $hvnly_option['user_id'] );
							}
							$hvnly_opt_type       = isset( $hvnly_option['type'] ) ? (string) $hvnly_option['type'] : 'user';
							$hvnly_opt_social_map = PropertyAgentWidgetRenderer::resolve_agent_social_url_map( $hvnly_option );
							?>
							<option
								value="<?php echo esc_attr( (string) $hvnly_opt_id ); ?>"
								data-agent-type="<?php echo esc_attr( $hvnly_opt_type ); ?>"
								data-agent-name="<?php echo esc_attr( (string) $hvnly_option['name'] ); ?>"
								data-agent-email="<?php echo esc_attr( (string) ( $hvnly_option['email'] ?? '' ) ); ?>"
								data-agent-phone="<?php echo esc_attr( (string) ( $hvnly_option['phone'] ?? '' ) ); ?>"
								data-agent-whatsapp="<?php echo esc_attr( (string) ( $hvnly_option['whatsapp'] ?? '' ) ); ?>"
								data-agent-avatar="<?php echo esc_attr( (string) ( $hvnly_option['avatar'] ?? '' ) ); ?>"
								data-agent-position="<?php echo esc_attr( (string) ( $hvnly_option['position'] ?? '' ) ); ?>"
								data-agent-social="<?php echo esc_attr( wp_json_encode( $hvnly_opt_social_map ) ); ?>"
								<?php selected( $hvnly_opt_id, $hvnly_agent_id ); ?>
							><?php echo esc_html( (string) $hvnly_option['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<h3 class="hvnly-agent-sidebar__name js-hvnly-sidebar-agent-name"><?php echo esc_html( $hvnly_name ); ?></h3>
				<?php endif; ?>

				<?php if ( $hvnly_show_position ) : ?>
					<p class="hvnly-agent-sidebar__position js-hvnly-sidebar-agent-position"><?php echo esc_html( $hvnly_position ); ?></p>
				<?php endif; ?>

				<?php if ( ! $hvnly_is_fallback && $hvnly_show_phone ) : ?>
					<a class="hvnly-agent-sidebar__phone js-hvnly-sidebar-agent-phone" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $hvnly_phone ) ); ?>"<?php echo $hvnly_phone ? '' : ' hidden'; ?>>
						<i class="fas fa-phone-alt" aria-hidden="true"></i>
						<span><?php echo esc_html( $hvnly_phone ); ?></span>
					</a>
				<?php endif; ?>

				<?php if ( $hvnly_show_email ) : ?>
					<a class="hvnly-agent-sidebar__email js-hvnly-sidebar-agent-email" href="mailto:<?php echo esc_attr( $hvnly_email ); ?>"<?php echo $hvnly_email ? '' : ' hidden'; ?>>
						<i class="fas fa-envelope" aria-hidden="true"></i>
						<span><?php echo esc_html( $hvnly_email ); ?></span>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $hvnly_show_form && function_exists( 'hvnly_get_template_part' ) ) : ?>
			<div class="hvnly-agent-sidebar__form-wrap">
				<h4 class="hvnly-agent-sidebar__form-title"><?php esc_html_e( 'Send a Message', 'havenlytics' ); ?></h4>
				<?php
				hvnly_get_template_part(
					'single-property/partials/property-agent-sidebar-form',
					null,
					array(
						'property_id'    => $hvnly_property_id,
						'property_title' => get_the_title( $hvnly_property_id ),
						'agent'          => $hvnly_primary,
						'agents'         => $hvnly_agents,
						'form_id'        => $hvnly_widget_id . '-form',
					)
				);
				?>
			</div>
		<?php endif; ?>

		<?php
		if ( ! $hvnly_is_fallback && function_exists( 'hvnly_get_template_part' ) ) {
			if ( ! isset( $hvnly_social_links ) ) {
				$hvnly_social_links = PropertyAgentWidgetRenderer::resolve_agent_social_links( $hvnly_primary );
			}
			hvnly_get_template_part(
				'single-property/partials/property-agent-sidebar-footer',
				null,
				array(
					'instance'     => $hvnly_instance,
					'agent'        => $hvnly_primary,
					'show_phone'   => $hvnly_show_phone && ! empty( $hvnly_phone ),
					'show_whatsapp'=> $hvnly_show_whatsapp,
					'phone'        => $hvnly_phone,
					'whatsapp'     => $hvnly_whatsapp,
					'social_links' => $hvnly_social_links,
					'show_social'  => $hvnly_show_social,
				)
			);
		}
		?>
	</div>
</aside>
