<?php
/**
 * Property carousel shell — Property Carousel block + Featured carousel layout.
 *
 * Each slide uses hvnly_render_property_card() (Property Card Builder).
 * Bound by hvnly-block-carousel.js via [data-hvnly-block-carousel].
 *
 * @package     Havenlytics
 * @subpackage  Templates/blocks
 * @since       3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_a = ( isset( $args ) && is_array( $args ) ) ? $args : array();
$hvnly_query   = isset( $hvnly_a['query'] ) && $hvnly_a['query'] instanceof WP_Query ? $hvnly_a['query'] : null;

if ( ! $hvnly_query instanceof WP_Query || ! $hvnly_query->have_posts() ) {
	echo '<div class="hvnly-block-empty">' . esc_html__( 'No properties found.', 'havenlytics' ) . '</div>';
	return;
}

$hvnly_visible        = isset( $hvnly_a['visible'] ) ? max( 1, min( 5, absint( $hvnly_a['visible'] ) ) ) : 3;
$hvnly_visible_tablet = isset( $hvnly_a['visible_tablet'] ) ? max( 1, min( 4, absint( $hvnly_a['visible_tablet'] ) ) ) : min( 2, $hvnly_visible );
$hvnly_visible_mobile = isset( $hvnly_a['visible_mobile'] ) ? max( 1, min( 2, absint( $hvnly_a['visible_mobile'] ) ) ) : 1;
$hvnly_autoplay       = ! empty( $hvnly_a['autoplay'] );
$hvnly_autoplay_delay = isset( $hvnly_a['autoplay_delay'] ) ? max( 1500, absint( $hvnly_a['autoplay_delay'] ) ) : 4500;
$hvnly_center         = ! empty( $hvnly_a['center'] );
$hvnly_show_nav       = ! isset( $hvnly_a['show_nav'] ) || ! empty( $hvnly_a['show_nav'] );
$hvnly_show_dots      = ! isset( $hvnly_a['show_dots'] ) || ! empty( $hvnly_a['show_dots'] );
$hvnly_header         = isset( $hvnly_a['header'] ) && is_array( $hvnly_a['header'] ) ? $hvnly_a['header'] : array();

hvnly_get_template_part( 'blocks/section-header', null, $hvnly_header );
?>
<div
	class="hvnly-block-carousel hvnly-property--grid--listings<?php echo $hvnly_center ? ' is-center' : ''; ?>"
	data-hvnly-block-carousel
	data-visible-desktop="<?php echo esc_attr( (string) $hvnly_visible ); ?>"
	data-visible-tablet="<?php echo esc_attr( (string) $hvnly_visible_tablet ); ?>"
	data-visible-mobile="<?php echo esc_attr( (string) $hvnly_visible_mobile ); ?>"
	data-autoplay="<?php echo esc_attr( $hvnly_autoplay ? '1' : '0' ); ?>"
	data-autoplay-delay="<?php echo esc_attr( (string) $hvnly_autoplay_delay ); ?>"
	data-center="<?php echo esc_attr( $hvnly_center ? '1' : '0' ); ?>"
	data-loop="1"
	style="--hvnly-block-visible: <?php echo esc_attr( (string) $hvnly_visible ); ?>;"
>
	<div class="hvnly-block-carousel__stage">
		<?php if ( $hvnly_show_nav ) : ?>
			<button type="button" class="hvnly-block-carousel__arrow hvnly-block-carousel__arrow--prev" data-hvnly-block-carousel-prev aria-label="<?php esc_attr_e( 'Previous', 'havenlytics' ); ?>">
				<span class="hvnly-block-carousel__arrow-icon" aria-hidden="true"></span>
			</button>
		<?php endif; ?>

		<div class="hvnly-block-carousel__viewport" data-hvnly-block-carousel-viewport>
			<div class="hvnly-block-carousel__track" data-hvnly-block-carousel-track>
				<?php
				while ( $hvnly_query->have_posts() ) :
					$hvnly_query->the_post();
					echo '<div class="hvnly-block-carousel__item">';
					hvnly_render_property_card( get_the_ID() );
					echo '</div>';
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>

		<?php if ( $hvnly_show_nav ) : ?>
			<button type="button" class="hvnly-block-carousel__arrow hvnly-block-carousel__arrow--next" data-hvnly-block-carousel-next aria-label="<?php esc_attr_e( 'Next', 'havenlytics' ); ?>">
				<span class="hvnly-block-carousel__arrow-icon" aria-hidden="true"></span>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( $hvnly_show_dots ) : ?>
		<div class="hvnly-block-carousel__dots" data-hvnly-block-carousel-dots role="tablist" aria-label="<?php esc_attr_e( 'Carousel navigation', 'havenlytics' ); ?>"></div>
	<?php endif; ?>
</div>
