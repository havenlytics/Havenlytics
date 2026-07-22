<?php
/**
 * Mobile floating contact dock — single property (phones / small tablets).
 *
 * Markup only. Visuals live in hvnly-frontend-mobile-contact-dock.css.
 * Behaviour lives in hvnly-frontend-mobile-contact-dock.js.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-property/partials
 * @since       3.5.0
 *
 * @var array $args Template args from MobileContactDock::get_template_args().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_name          = isset( $args['name'] ) ? (string) $args['name'] : '';
$hvnly_role          = isset( $args['role'] ) ? (string) $args['role'] : '';
$hvnly_avatar        = isset( $args['avatar'] ) ? (string) $args['avatar'] : '';
$hvnly_profile       = isset( $args['profile_url'] ) ? (string) $args['profile_url'] : '';
$hvnly_is_verified   = ! empty( $args['is_verified'] );
$hvnly_actions       = isset( $args['actions'] ) && is_array( $args['actions'] ) ? $args['actions'] : array();
$hvnly_settings      = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array();
$hvnly_show_avatar   = ! isset( $hvnly_settings['show_avatar'] ) || ! empty( $hvnly_settings['show_avatar'] );
$hvnly_show_role     = ! empty( $hvnly_settings['show_role'] ) && '' !== trim( $hvnly_role );
$hvnly_max_width     = isset( $args['max_width'] ) ? absint( $args['max_width'] ) : 991;
$hvnly_sticky_offset = isset( $args['sticky_offset'] ) ? absint( $args['sticky_offset'] ) : 12;
$hvnly_accent        = isset( $args['accent_color'] ) ? (string) $args['accent_color'] : '';
$hvnly_property_id   = isset( $args['property_id'] ) ? absint( $args['property_id'] ) : 0;

if ( '' === $hvnly_name || empty( $hvnly_actions ) ) {
	return;
}

$hvnly_style_vars = sprintf(
	'--hvnly-mcd-max:%dpx;--hvnly-mcd-offset:%dpx;',
	$hvnly_max_width,
	$hvnly_sticky_offset
);
if ( '' !== $hvnly_accent && sanitize_hex_color( $hvnly_accent ) ) {
	$hvnly_style_vars .= '--hvnly-mcd-accent:' . sanitize_hex_color( $hvnly_accent ) . ';';
}
?>
<aside
	id="hvnly-mobile-contact-dock"
	class="hvnly-mobile-contact-dock"
	style="<?php echo esc_attr( $hvnly_style_vars ); ?>"
	data-hvnly-mobile-contact-dock
	data-property-id="<?php echo esc_attr( (string) $hvnly_property_id ); ?>"
	data-max-width="<?php echo esc_attr( (string) $hvnly_max_width ); ?>"
	aria-label="<?php esc_attr_e( 'Contact agent', 'havenlytics' ); ?>"
>
	<div class="hvnly-mobile-contact-dock__card">
		<div class="hvnly-mobile-contact-dock__agent">
			<?php if ( $hvnly_show_avatar ) : ?>
				<?php if ( $hvnly_profile ) : ?>
					<a
						class="hvnly-mobile-contact-avatar"
						href="<?php echo esc_url( $hvnly_profile ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: agent name */ __( 'View %s profile', 'havenlytics' ), $hvnly_name ) ); ?>"
					>
				<?php else : ?>
					<span class="hvnly-mobile-contact-avatar">
				<?php endif; ?>

					<?php if ( $hvnly_avatar ) : ?>
						<img
							class="hvnly-mobile-contact-avatar__img"
							src="<?php echo esc_url( $hvnly_avatar ); ?>"
							alt=""
							width="44"
							height="44"
							loading="lazy"
							decoding="async"
						/>
					<?php else : ?>
						<span class="hvnly-mobile-contact-avatar__placeholder" aria-hidden="true">
							<i class="fas fa-user-tie"></i>
						</span>
					<?php endif; ?>

				<?php if ( $hvnly_profile ) : ?>
					</a>
				<?php else : ?>
					</span>
				<?php endif; ?>
			<?php endif; ?>

			<div class="hvnly-mobile-contact-dock__meta">
				<div class="hvnly-mobile-contact-name">
					<span class="hvnly-mobile-contact-name__text"><?php echo esc_html( $hvnly_name ); ?></span>
					<?php if ( $hvnly_is_verified ) : ?>
						<span
							class="hvnly-mobile-contact-name__verified"
							title="<?php esc_attr_e( 'Verified Agent', 'havenlytics' ); ?>"
						>
							<i class="fas fa-check-circle" aria-hidden="true"></i>
							<span class="screen-reader-text"><?php esc_html_e( 'Verified Agent', 'havenlytics' ); ?></span>
						</span>
					<?php endif; ?>
				</div>
				<?php if ( $hvnly_show_role ) : ?>
					<p class="hvnly-mobile-contact-role"><?php echo esc_html( $hvnly_role ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<nav
			class="hvnly-mobile-contact-actions"
			aria-label="<?php esc_attr_e( 'Quick contact actions', 'havenlytics' ); ?>"
		>
			<?php foreach ( $hvnly_actions as $hvnly_action ) : ?>
				<?php
				if ( empty( $hvnly_action['href'] ) || empty( $hvnly_action['id'] ) ) {
					continue;
				}
				$hvnly_action_id     = sanitize_key( (string) $hvnly_action['id'] );
				$hvnly_action_label  = isset( $hvnly_action['label'] ) ? (string) $hvnly_action['label'] : $hvnly_action_id;
				$hvnly_action_icon   = isset( $hvnly_action['icon'] ) ? (string) $hvnly_action['icon'] : 'fas fa-link';
				$hvnly_action_target = isset( $hvnly_action['target'] ) ? (string) $hvnly_action['target'] : '';
				$hvnly_action_rel    = isset( $hvnly_action['rel'] ) ? (string) $hvnly_action['rel'] : '';
				$hvnly_action_href   = (string) $hvnly_action['href'];

				// Match agent-card / sidebar escaping: protocol schemes stay intact.
				if ( 0 === strpos( $hvnly_action_href, 'tel:' ) || 0 === strpos( $hvnly_action_href, 'sms:' ) || 0 === strpos( $hvnly_action_href, 'mailto:' ) ) {
					$hvnly_href_attr = esc_attr( $hvnly_action_href );
				} else {
					$hvnly_href_attr = esc_url( $hvnly_action_href );
				}
				?>
				<a
					class="hvnly-mobile-contact-btn hvnly-mobile-contact-btn--<?php echo esc_attr( $hvnly_action_id ); ?>"
					href="<?php echo $hvnly_href_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>"
					<?php echo $hvnly_action_target ? ' target="' . esc_attr( $hvnly_action_target ) . '"' : ''; ?>
					<?php echo $hvnly_action_rel ? ' rel="' . esc_attr( $hvnly_action_rel ) . '"' : ''; ?>
					aria-label="<?php echo esc_attr( $hvnly_action_label ); ?>"
					title="<?php echo esc_attr( $hvnly_action_label ); ?>"
					data-action="<?php echo esc_attr( $hvnly_action_id ); ?>"
				>
					<span class="hvnly-mobile-contact-btn__icon" aria-hidden="true">
						<i class="<?php echo esc_attr( $hvnly_action_icon ); ?>"></i>
					</span>
					<span class="hvnly-mobile-contact-btn__label"><?php echo esc_html( $hvnly_action_label ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
	</div>
</aside>
