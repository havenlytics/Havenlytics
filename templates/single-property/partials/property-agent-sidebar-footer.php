<?php
/**
 * Sidebar agent footer — Call, WhatsApp, and social icons.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-property/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_instance     = isset( $args['instance'] ) && is_array( $args['instance'] ) ? $args['instance'] : array();
$hvnly_agent        = isset( $args['agent'] ) && is_array( $args['agent'] ) ? $args['agent'] : array();
$hvnly_show_phone   = ! empty( $args['show_phone'] );
$hvnly_show_whatsapp = ! empty( $args['show_whatsapp'] );
$hvnly_phone        = isset( $args['phone'] ) ? (string) $args['phone'] : '';
$hvnly_whatsapp     = isset( $args['whatsapp'] ) ? (string) $args['whatsapp'] : '';
$hvnly_social_links = isset( $args['social_links'] ) && is_array( $args['social_links'] ) ? $args['social_links'] : array();
$hvnly_show_social  = ! empty( $args['show_social'] ) && ! empty( $hvnly_social_links );
$hvnly_has_actions  = $hvnly_show_phone || $hvnly_show_whatsapp;
$hvnly_has_footer   = $hvnly_has_actions || $hvnly_show_social;

if ( ! $hvnly_has_footer ) {
	return;
}

$hvnly_all_platforms = class_exists( '\HvnlyNab\Agent\PropertyAgentWidgetRenderer' )
	? \HvnlyNab\Agent\PropertyAgentWidgetRenderer::social_platforms()
	: array();
?>
<div class="hvnly-agent-sidebar__footer">
	<?php if ( $hvnly_has_actions ) : ?>
		<div class="hvnly-agent-sidebar__quick-actions hvnly-agent-sidebar__quick-actions--footer">
			<?php if ( $hvnly_show_phone ) : ?>
				<a class="hvnly-btn hvnly-btn--primary hvnly-agent-sidebar__btn hvnly-agent-sidebar__btn--call js-hvnly-sidebar-call-btn" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $hvnly_phone ) ); ?>">
					<i class="fas fa-phone-alt" aria-hidden="true"></i>
					<?php esc_html_e( 'Call Us', 'havenlytics' ); ?>
				</a>
			<?php endif; ?>
			<?php if ( $hvnly_show_whatsapp ) : ?>
				<a class="hvnly-btn hvnly-btn--outline hvnly-agent-sidebar__btn hvnly-agent-sidebar__btn--whatsapp js-hvnly-sidebar-whatsapp-btn" href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $hvnly_whatsapp ) ); ?>" target="_blank" rel="noopener noreferrer">
					<i class="fab fa-whatsapp" aria-hidden="true"></i>
					<?php esc_html_e( 'WhatsApp', 'havenlytics' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $hvnly_all_platforms ) ) : ?>
		<div class="hvnly-agent-sidebar__social js-hvnly-sidebar-social" <?php echo $hvnly_show_social ? '' : 'hidden'; ?>>
			<?php foreach ( $hvnly_all_platforms as $hvnly_platform => $hvnly_meta ) : ?>
				<?php
				$hvnly_url = isset( $hvnly_social_links[ $hvnly_platform ]['url'] )
					? (string) $hvnly_social_links[ $hvnly_platform ]['url']
					: '';
				?>
				<a
					href="<?php echo $hvnly_url ? esc_url( $hvnly_url ) : '#'; ?>"
					class="hvnly-agent-sidebar__social-link hvnly-agent-sidebar__social-link--<?php echo esc_attr( $hvnly_platform ); ?> js-hvnly-sidebar-social-link"
					data-platform="<?php echo esc_attr( $hvnly_platform ); ?>"
					target="_blank"
					rel="noopener noreferrer"
					title="<?php echo esc_attr( (string) $hvnly_meta['label'] ); ?>"
					<?php echo $hvnly_url ? '' : 'hidden'; ?>
				>
					<i class="<?php echo esc_attr( (string) $hvnly_meta['icon'] ); ?>" aria-hidden="true"></i>
					<span class="screen-reader-text"><?php echo esc_html( (string) $hvnly_meta['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
