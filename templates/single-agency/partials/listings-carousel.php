<?php
/**
 * Agency assigned listings — reuses single-property Similar Properties carousel markup/IDs.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-agency/partials
 * @since       3.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_args = ( isset( $args ) && is_array( $args ) ) ? $args : array();

$hvnly_properties_query = isset( $hvnly_args['query'] ) && $hvnly_args['query'] instanceof WP_Query
	? $hvnly_args['query']
	: null;

$hvnly_property_count = isset( $hvnly_args['property_count'] ) ? absint( $hvnly_args['property_count'] ) : 0;

if ( ! $hvnly_properties_query instanceof WP_Query || ! $hvnly_properties_query->have_posts() ) {
	return;
}
?>
<div class="hvnly-property-single__similar-properties hvnly-agency-single__listings-carousel">
	<div class="hvnly-property-single__similar-header">
		<div class="hvnly-agency-single__section-header">
			<h2 class="hvnly-agency-single__section-title"><?php esc_html_e( 'Agency Listings', 'havenlytics' ); ?></h2>
			<p class="hvnly-agency-single__section-count">
				<?php
				printf(
					/* translators: %d: number of properties */
					esc_html( _n( '%d property listed', '%d properties listed', $hvnly_property_count, 'havenlytics' ) ),
					absint( $hvnly_property_count )
				);
				?>
			</p>
		</div>
		<div class="hvnly-property-single__carousel-controls">
			<button type="button" class="hvnly-ui-control hvnly-property-single__carousel-btn" id="hvnlyPropertySingleCarouselPrev"
				aria-label="<?php esc_attr_e( 'Previous properties', 'havenlytics' ); ?>" disabled>
				<i class="fas fa-chevron-left" aria-hidden="true"></i>
			</button>
			<button type="button" class="hvnly-ui-control hvnly-property-single__carousel-btn" id="hvnlyPropertySingleCarouselNext"
				aria-label="<?php esc_attr_e( 'Next properties', 'havenlytics' ); ?>">
				<i class="fas fa-chevron-right" aria-hidden="true"></i>
			</button>
		</div>
	</div>

	<div class="hvnly-property-single__carousel-container">
		<div class="hvnly-property-single__carousel-track" id="hvnlyPropertySingleCarouselTrack">
			<?php
			$hvnly_slide_index = 0;
			while ( $hvnly_properties_query->have_posts() ) :
				$hvnly_properties_query->the_post();

				$hvnly_property_id = get_the_ID();

				$hvnly_actual_price = get_post_meta( $hvnly_property_id, '_hvnly_property_price', true );
				$hvnly_price        = function_exists( 'hvnly_format_price' ) ? hvnly_format_price( $hvnly_actual_price ) : esc_html( (string) $hvnly_actual_price );

				$hvnly_image_url   = get_the_post_thumbnail_url( $hvnly_property_id, 'large' );
				$hvnly_bedrooms    = get_post_meta( $hvnly_property_id, '_hvnly_property_bedrooms', true );
				$hvnly_bathrooms   = get_post_meta( $hvnly_property_id, '_hvnly_property_bathrooms', true );
				$hvnly_sqft        = get_post_meta( $hvnly_property_id, '_hvnly_property_sqft', true );
				$hvnly_is_featured = get_post_meta( $hvnly_property_id, '_hvnly_property_featured', true );

				$hvnly_property_types    = wp_get_post_terms( $hvnly_property_id, 'hvnly_prop_types', array( 'fields' => 'names' ) );
				$hvnly_property_status   = wp_get_post_terms( $hvnly_property_id, 'hvnly_prop_status', array( 'fields' => 'names' ) );
				$hvnly_property_badges   = wp_get_post_terms( $hvnly_property_id, 'hvnly_prop_badges', array( 'fields' => 'names' ) );
				$hvnly_property_features = wp_get_post_terms( $hvnly_property_id, 'hvnly_prop_features', array( 'fields' => 'names' ) );

				if ( is_wp_error( $hvnly_property_types ) ) {
					$hvnly_property_types = array();
				}
				if ( is_wp_error( $hvnly_property_status ) ) {
					$hvnly_property_status = array();
				}
				if ( is_wp_error( $hvnly_property_badges ) ) {
					$hvnly_property_badges = array();
				}
				if ( is_wp_error( $hvnly_property_features ) ) {
					$hvnly_property_features = array();
				}

				$hvnly_is_featured_property = ( '1' === (string) $hvnly_is_featured || 1 === (int) $hvnly_is_featured );
				?>
				<div class="hvnly-property-single__carousel-slide">
					<div class="hvnly-property-single__similar-card">
						<div class="hvnly-property-single__similar-image" data-index="<?php echo esc_attr( (string) $hvnly_slide_index ); ?>">
							<a href="<?php the_permalink(); ?>">
								<?php if ( $hvnly_image_url ) : ?>
									<img src="<?php echo esc_url( $hvnly_image_url ); ?>"
										alt="<?php the_title_attribute(); ?>"
										loading="lazy"
										decoding="async"
										data-src="<?php echo esc_url( get_the_post_thumbnail_url( $hvnly_property_id, 'full' ) ); ?>"
										data-alt="<?php the_title_attribute(); ?>">
								<?php else : ?>
									<div class="hvnly-property-single__similar-image-placeholder">
										<i class="fas fa-home" style="font-size: 40px; color: var(--hvnly-primary-color);"></i>
									</div>
								<?php endif; ?>

								<div class="hvnly-property-single__image-overlay">
									<?php if ( ! empty( $hvnly_property_types ) ) : ?>
										<div class="hvnly-property-single__overlay-item">
											<i class="fas fa-tag"></i>
											<span><?php echo esc_html( implode( ', ', array_slice( $hvnly_property_types, 0, 2 ) ) ); ?></span>
										</div>
									<?php endif; ?>

									<?php if ( ! empty( $hvnly_property_status ) ) : ?>
										<div class="hvnly-property-single__overlay-item">
											<i class="fas fa-info-circle"></i>
											<span><?php echo esc_html( implode( ', ', array_slice( $hvnly_property_status, 0, 2 ) ) ); ?></span>
										</div>
									<?php endif; ?>

									<?php if ( ! empty( $hvnly_property_badges ) ) : ?>
										<div class="hvnly-property-single__overlay-item">
											<i class="fas fa-star"></i>
											<span><?php echo esc_html( implode( ', ', array_slice( $hvnly_property_badges, 0, 2 ) ) ); ?></span>
										</div>
									<?php endif; ?>

									<?php if ( ! empty( $hvnly_property_features ) ) : ?>
										<div class="hvnly-property-single__overlay-item">
											<i class="fas fa-check-circle"></i>
											<span><?php echo esc_html( implode( ', ', array_slice( $hvnly_property_features, 0, 2 ) ) ); ?></span>
											<?php if ( count( $hvnly_property_features ) > 2 ) : ?>
												<span class="hvnly-property-single__overlay-more">+<?php echo esc_html( (string) ( count( $hvnly_property_features ) - 2 ) ); ?> <?php esc_html_e( 'more', 'havenlytics' ); ?></span>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</div>

								<?php if ( $hvnly_is_featured_property ) : ?>
									<span class="hvnly-property-single__similar-badge hvnly-property-single__similar-badge--featured">
										<?php esc_html_e( 'Featured', 'havenlytics' ); ?>
									</span>
								<?php endif; ?>
							</a>
						</div>

						<div class="hvnly-property-single__similar-content">
							<div class="hvnly-property-single__similar-price"><?php echo wp_kses_post( $hvnly_price ); ?></div>

							<h3 class="hvnly-property-single__similar-title">
								<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</h3>

							<?php if ( $hvnly_bedrooms || $hvnly_bathrooms || $hvnly_sqft ) : ?>
								<div class="hvnly-property-single__similar-meta">
									<?php if ( $hvnly_bedrooms ) : ?>
										<div class="hvnly-property-single__similar-feature">
											<i class="fas fa-bed"></i>
											<span><?php echo esc_html( $hvnly_bedrooms ); ?> <?php esc_html_e( 'Beds', 'havenlytics' ); ?></span>
										</div>
									<?php endif; ?>

									<?php if ( $hvnly_bathrooms ) : ?>
										<div class="hvnly-property-single__similar-feature">
											<i class="fas fa-bath"></i>
											<span><?php echo esc_html( $hvnly_bathrooms ); ?> <?php esc_html_e( 'Baths', 'havenlytics' ); ?></span>
										</div>
									<?php endif; ?>

									<?php if ( $hvnly_sqft ) : ?>
										<div class="hvnly-property-single__similar-feature">
											<i class="fas fa-vector-square"></i>
											<span><?php echo esc_html( $hvnly_sqft ); ?> <?php esc_html_e( 'SqFt', 'havenlytics' ); ?></span>
										</div>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<a href="<?php the_permalink(); ?>" class="hvnly-property-single__btn hvnly-property-single__btn--full" style="margin-top: var(--hvnly-space-md); color: var(--hvnly-color-white);">
								<i class="fas fa-eye"></i>
								<?php esc_html_e( 'View Details', 'havenlytics' ); ?>
							</a>
						</div>
					</div>
				</div>
				<?php
				++$hvnly_slide_index;
			endwhile;
			?>
		</div>
	</div>

	<div class="hvnly-property-single__carousel-dots" id="hvnlyPropertySingleCarouselDots" role="tablist"
		aria-label="<?php esc_attr_e( 'Property carousel navigation', 'havenlytics' ); ?>"></div>
</div>
