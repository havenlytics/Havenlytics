<?php
/**
 * Agent Listings Carousel — sidebar widget template.
 *
 * Dedicated frontend markup for Hvnly_Agent_Listings_Carousel_Widget.
 *
 * @package     Havenlytics
 * @subpackage  Templates/widgets
 * @since       3.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_args = ( isset( $args ) && is_array( $args ) ) ? $args : array();

$hvnly_properties_query = isset( $hvnly_args['query'] ) && $hvnly_args['query'] instanceof WP_Query
	? $hvnly_args['query']
	: null;

if ( ! $hvnly_properties_query instanceof WP_Query || ! $hvnly_properties_query->have_posts() ) {
	return;
}

$hvnly_title           = isset( $hvnly_args['title'] ) ? (string) $hvnly_args['title'] : '';
$hvnly_available_count = isset( $hvnly_args['available_count'] ) ? absint( $hvnly_args['available_count'] ) : 0;
$hvnly_carousel_uid    = isset( $hvnly_args['carousel_uid'] ) ? sanitize_html_class( (string) $hvnly_args['carousel_uid'] ) : 'hvnly-agent-listings-carousel';
$hvnly_show_price      = ! empty( $hvnly_args['show_price'] );
$hvnly_show_location   = ! empty( $hvnly_args['show_location'] );
$hvnly_show_status     = ! empty( $hvnly_args['show_status'] );
$hvnly_autoplay        = ! empty( $hvnly_args['autoplay'] );
$hvnly_show_nav        = ! isset( $hvnly_args['show_nav'] ) || ! empty( $hvnly_args['show_nav'] );
?>
<div class="hvnly-agent-listings-widget" id="<?php echo esc_attr( $hvnly_carousel_uid ); ?>">
	<div
		class="hvnly-agent-listings-carousel"
		data-hvnly-property-carousel
		data-visible-slides="1"
		data-autoplay="<?php echo esc_attr( $hvnly_autoplay ? '1' : '0' ); ?>"
		data-show-nav="<?php echo esc_attr( $hvnly_show_nav ? '1' : '0' ); ?>"
	>
		<div class="hvnly-agent-listings-header">
			<div class="hvnly-agent-listings-heading">
				<?php if ( '' !== $hvnly_title ) : ?>
					<h4 class="hvnly-agent-listings-title"><?php echo esc_html( $hvnly_title ); ?></h4>
				<?php endif; ?>
				<?php if ( $hvnly_available_count > 0 ) : ?>
					<p class="hvnly-agent-listings-subtitle">
						<?php
						printf(
							/* translators: %d: number of properties */
							esc_html( _n( '%d Property Available', '%d Properties Available', $hvnly_available_count, 'havenlytics' ) ),
							absint( $hvnly_available_count )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
			<?php if ( $hvnly_show_nav ) : ?>
				<div class="hvnly-agent-listings-controls">
					<button
						type="button"
						class="hvnly-agent-listings-nav-btn"
						data-hvnly-carousel-prev
						aria-label="<?php esc_attr_e( 'Previous properties', 'havenlytics' ); ?>"
						disabled
					>
						<i class="fas fa-chevron-left" aria-hidden="true"></i>
					</button>
					<button
						type="button"
						class="hvnly-agent-listings-nav-btn"
						data-hvnly-carousel-next
						aria-label="<?php esc_attr_e( 'Next properties', 'havenlytics' ); ?>"
					>
						<i class="fas fa-chevron-right" aria-hidden="true"></i>
					</button>
				</div>
			<?php endif; ?>
		</div>

		<div class="hvnly-agent-listings-container" data-hvnly-carousel-container>
			<div class="hvnly-agent-listings-track" data-hvnly-carousel-track>
				<?php
				while ( $hvnly_properties_query->have_posts() ) :
					$hvnly_properties_query->the_post();

					$hvnly_property_id = get_the_ID();
					$hvnly_image_url   = get_the_post_thumbnail_url( $hvnly_property_id, 'medium_large' );

					$hvnly_price = '';
					if ( $hvnly_show_price ) {
						$hvnly_actual_price = get_post_meta( $hvnly_property_id, '_hvnly_property_price', true );
						$hvnly_price        = function_exists( 'hvnly_format_price' )
							? hvnly_format_price( $hvnly_actual_price )
							: esc_html( (string) $hvnly_actual_price );
					}

					$hvnly_bedrooms  = get_post_meta( $hvnly_property_id, '_hvnly_property_bedrooms', true );
					$hvnly_bathrooms = get_post_meta( $hvnly_property_id, '_hvnly_property_bathrooms', true );
					$hvnly_sqft      = get_post_meta( $hvnly_property_id, '_hvnly_property_sqft', true );

					$hvnly_location_terms = $hvnly_show_location
						? wp_get_post_terms( $hvnly_property_id, 'hvnly_prop_locations', array( 'fields' => 'names' ) )
						: array();
					if ( is_wp_error( $hvnly_location_terms ) ) {
						$hvnly_location_terms = array();
					}

					$hvnly_status_terms = $hvnly_show_status
						? wp_get_post_terms( $hvnly_property_id, 'hvnly_prop_status', array( 'fields' => 'names' ) )
						: array();
					if ( is_wp_error( $hvnly_status_terms ) ) {
						$hvnly_status_terms = array();
					}

					$hvnly_has_meta = ( $hvnly_bedrooms || $hvnly_bathrooms || $hvnly_sqft );
					?>
					<div class="hvnly-agent-listings-slide">
						<article class="hvnly-agent-listings-card">
							<div class="hvnly-agent-listings-image">
								<a href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
									<?php if ( $hvnly_image_url ) : ?>
										<img
											src="<?php echo esc_url( $hvnly_image_url ); ?>"
											alt="<?php the_title_attribute(); ?>"
											loading="lazy"
											decoding="async"
										>
									<?php else : ?>
										<span class="hvnly-agent-listings-image-placeholder" aria-hidden="true">
											<i class="fas fa-home"></i>
										</span>
									<?php endif; ?>
								</a>
								<?php if ( $hvnly_show_status && ! empty( $hvnly_status_terms ) ) : ?>
									<span class="hvnly-agent-listings-status"><?php echo esc_html( $hvnly_status_terms[0] ); ?></span>
								<?php endif; ?>
							</div>

							<div class="hvnly-agent-listings-content">
								<?php if ( $hvnly_show_price && '' !== $hvnly_price ) : ?>
									<div class="hvnly-agent-listings-price"><?php echo wp_kses_post( $hvnly_price ); ?></div>
								<?php endif; ?>

								<h5 class="hvnly-agent-listings-card-title">
									<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
								</h5>

								<?php if ( $hvnly_show_location && ! empty( $hvnly_location_terms ) ) : ?>
									<div class="hvnly-agent-listings-location">
										<i class="fas fa-map-marker-alt" aria-hidden="true"></i>
										<span><?php echo esc_html( implode( ', ', array_slice( $hvnly_location_terms, 0, 2 ) ) ); ?></span>
									</div>
								<?php endif; ?>

								<?php if ( $hvnly_has_meta ) : ?>
									<ul class="hvnly-agent-listings-meta" aria-label="<?php esc_attr_e( 'Property details', 'havenlytics' ); ?>">
										<?php if ( $hvnly_bedrooms ) : ?>
											<li class="hvnly-agent-listings-meta-item">
												<i class="fas fa-bed" aria-hidden="true"></i>
												<span><?php echo esc_html( $hvnly_bedrooms ); ?></span>
												<span class="screen-reader-text"><?php esc_html_e( 'Bedrooms', 'havenlytics' ); ?></span>
											</li>
										<?php endif; ?>
										<?php if ( $hvnly_bathrooms ) : ?>
											<li class="hvnly-agent-listings-meta-item">
												<i class="fas fa-bath" aria-hidden="true"></i>
												<span><?php echo esc_html( $hvnly_bathrooms ); ?></span>
												<span class="screen-reader-text"><?php esc_html_e( 'Bathrooms', 'havenlytics' ); ?></span>
											</li>
										<?php endif; ?>
										<?php if ( $hvnly_sqft ) : ?>
											<li class="hvnly-agent-listings-meta-item">
												<i class="fas fa-vector-square" aria-hidden="true"></i>
												<span><?php echo esc_html( $hvnly_sqft ); ?></span>
												<span class="screen-reader-text"><?php esc_html_e( 'Area', 'havenlytics' ); ?></span>
											</li>
										<?php endif; ?>
									</ul>
								<?php endif; ?>

								<a href="<?php the_permalink(); ?>" class="hvnly-agent-listings-cta">
									<i class="fas fa-eye" aria-hidden="true"></i>
									<?php esc_html_e( 'View Details', 'havenlytics' ); ?>
								</a>
							</div>
						</article>
					</div>
				<?php endwhile; ?>
			</div>
		</div>

		<div
			class="hvnly-agent-listings-dots"
			data-hvnly-carousel-dots
			role="tablist"
			aria-label="<?php esc_attr_e( 'Agent listings carousel navigation', 'havenlytics' ); ?>"
		></div>
	</div>
</div>
