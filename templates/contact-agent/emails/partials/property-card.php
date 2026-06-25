<?php
/**
 * Property listing card for Contact Agent emails.
 *
 * @package Havenlytics
 * @since   3.0.2
 *
 * @var string $property_image
 * @var string $property_title
 * @var string $property_url
 * @var string $property_price
 * @var string $property_address
 * @var string $property_location
 * @var string $property_bedrooms
 * @var string $property_bathrooms
 * @var string $property_area
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/brand-colors.php';

$hvnly_image     = isset( $property_image ) ? (string) $property_image : '';
$hvnly_title     = isset( $property_title ) ? (string) $property_title : '';
$hvnly_url       = isset( $property_url ) ? (string) $property_url : '';
$hvnly_price     = isset( $property_price ) ? (string) $property_price : '';
$hvnly_address   = isset( $property_address ) ? (string) $property_address : '';
$hvnly_location  = isset( $property_location ) ? (string) $property_location : '';
$hvnly_bedrooms  = isset( $property_bedrooms ) ? (string) $property_bedrooms : '';
$hvnly_bathrooms = isset( $property_bathrooms ) ? (string) $property_bathrooms : '';
$hvnly_area      = isset( $property_area ) ? (string) $property_area : '';

$hvnly_location_line = trim( $hvnly_location );
if ( '' === $hvnly_location_line && '' !== trim( $hvnly_address ) ) {
	$hvnly_location_line = trim( $hvnly_address );
}

$hvnly_stats = array();
if ( '' !== trim( $hvnly_bedrooms ) ) {
	$hvnly_stats[] = sprintf(
		/* translators: %s: number of bedrooms */
		__( '%s Beds', 'havenlytics' ),
		$hvnly_bedrooms
	);
}
if ( '' !== trim( $hvnly_bathrooms ) ) {
	$hvnly_stats[] = sprintf(
		/* translators: %s: number of bathrooms */
		__( '%s Baths', 'havenlytics' ),
		$hvnly_bathrooms
	);
}
if ( '' !== trim( $hvnly_area ) ) {
	$hvnly_stats[] = sprintf(
		/* translators: %s: property area in square feet */
		__( '%s sq ft', 'havenlytics' ),
		$hvnly_area
	);
}

if ( '' === trim( $hvnly_title ) ) {
	return;
}
?>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 28px;border:1px solid <?php echo esc_attr( $hvnly_brand_primary ); ?>;border-radius:12px;overflow:hidden;">
	<?php if ( $hvnly_image ) : ?>
		<tr>
			<td style="padding:0;line-height:0;font-size:0;">
				<?php if ( $hvnly_url ) : ?>
					<a href="<?php echo esc_url( $hvnly_url ); ?>" style="display:block;text-decoration:none;">
						<img src="<?php echo esc_url( $hvnly_image ); ?>" alt="<?php echo esc_attr( $hvnly_title ); ?>" width="544" style="display:block;width:100%;max-width:544px;height:auto;border:0;object-fit:cover;" />
					</a>
				<?php else : ?>
					<img src="<?php echo esc_url( $hvnly_image ); ?>" alt="<?php echo esc_attr( $hvnly_title ); ?>" width="544" style="display:block;width:100%;max-width:544px;height:auto;border:0;object-fit:cover;" />
				<?php endif; ?>
			</td>
		</tr>
	<?php endif; ?>
	<tr>
		<td style="padding:22px 24px;background:<?php echo esc_attr( $hvnly_brand_primary_light ); ?>;">
			<p style="margin:0 0 6px;font-size:12px;line-height:1.4;font-weight:bold;letter-spacing:0.06em;text-transform:uppercase;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
				<?php esc_html_e( 'Listed Property', 'havenlytics' ); ?>
			</p>
			<?php if ( $hvnly_url ) : ?>
				<h2 style="margin:0 0 10px;font-size:22px;line-height:1.35;font-weight:bold;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
					<a href="<?php echo esc_url( $hvnly_url ); ?>" style="color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;text-decoration:none;"><?php echo esc_html( $hvnly_title ); ?></a>
				</h2>
			<?php else : ?>
				<h2 style="margin:0 0 10px;font-size:22px;line-height:1.35;font-weight:bold;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;"><?php echo esc_html( $hvnly_title ); ?></h2>
			<?php endif; ?>

			<?php if ( '' !== trim( $hvnly_price ) ) : ?>
				<p style="margin:0 0 12px;font-size:24px;line-height:1.2;font-weight:bold;color:<?php echo esc_attr( $hvnly_brand_primary ); ?>;">
					<?php echo esc_html( $hvnly_price ); ?>
				</p>
			<?php endif; ?>

			<?php if ( '' !== $hvnly_location_line ) : ?>
				<p style="margin:0 0 14px;font-size:14px;line-height:1.5;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
					<?php echo esc_html( $hvnly_location_line ); ?>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $hvnly_stats ) ) : ?>
				<p style="margin:0 0 18px;font-size:13px;line-height:1.6;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
					<?php echo esc_html( implode( '  ·  ', $hvnly_stats ) ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $hvnly_url ) : ?>
				<table role="presentation" cellspacing="0" cellpadding="0">
					<tr>
						<td style="border-radius:8px;background:<?php echo esc_attr( $hvnly_brand_primary ); ?>;">
							<a href="<?php echo esc_url( $hvnly_url ); ?>" style="display:inline-block;padding:12px 22px;font-size:14px;line-height:1;font-weight:bold;color:<?php echo esc_attr( $hvnly_brand_white ); ?>;text-decoration:none;">
								<?php esc_html_e( 'View Property', 'havenlytics' ); ?>
							</a>
						</td>
					</tr>
				</table>
			<?php endif; ?>
		</td>
	</tr>
</table>
