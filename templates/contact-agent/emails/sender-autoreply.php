<?php
/**
 * Sender auto-reply email template (Contact Agent).
 *
 * @package     Havenlytics
 * @subpackage  Templates/contact-agent/emails
 * @since       3.0.2
 *
 * @var int                  $inquiry_id
 * @var array<string, mixed> $inquiry
 * @var array<string, mixed> $agent
 * @var int                  $property_id
 * @var string               $property_title
 * @var string               $property_url
 * @var string               $property_image
 * @var string               $property_price
 * @var string               $property_address
 * @var string               $property_location
 * @var string               $property_bedrooms
 * @var string               $property_bathrooms
 * @var string               $property_area
 * @var string               $site_name
 * @var string               $site_url
 * @var string               $sender_name
 * @var string               $sender_intro
 * @var string               $agent_name
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/partials/brand-colors.php';

$hvnly_sender_name = isset( $sender_name ) ? (string) $sender_name : '';
$hvnly_intro       = isset( $sender_intro ) ? (string) $sender_intro : '';
$hvnly_agent_name  = isset( $agent_name ) ? (string) $agent_name : '';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'We received your inquiry', 'havenlytics' ); ?></title>
</head>
<body style="margin:0;padding:0;background:<?php echo esc_attr( $hvnly_brand_primary_light ); ?>;font-family:Arial,Helvetica,sans-serif;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:<?php echo esc_attr( $hvnly_brand_primary_light ); ?>;padding:28px 12px;">
		<tr>
			<td align="center">
				<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:<?php echo esc_attr( $hvnly_brand_white ); ?>;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(108,96,254,0.15);">
					<tr>
						<td style="padding:28px 28px 24px;background:linear-gradient(135deg, <?php echo esc_attr( $hvnly_brand_primary ); ?> 0%, <?php echo esc_attr( $hvnly_brand_secondary ); ?> 100%);">
							<h1 style="margin:0;font-size:26px;line-height:1.25;font-weight:bold;color:<?php echo esc_attr( $hvnly_brand_white ); ?>;">
								<?php esc_html_e( 'Thank You for Reaching Out', 'havenlytics' ); ?>
							</h1>
							<?php if ( $hvnly_sender_name ) : ?>
								<p style="margin:10px 0 0;font-size:15px;line-height:1.5;color:<?php echo esc_attr( $hvnly_brand_white ); ?>;opacity:0.95;">
									<?php
									printf(
										/* translators: %s: sender first name or display name */
										esc_html__( 'Hello %s,', 'havenlytics' ),
										esc_html( $hvnly_sender_name )
									);
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td style="padding:28px 28px 24px;">
							<p style="margin:0 0 22px;font-size:16px;line-height:1.75;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
								<?php echo esc_html( $hvnly_intro ); ?>
							</p>

							<?php if ( ! empty( $property_title ) && function_exists( 'hvnly_get_template' ) ) : ?>
								<?php
								hvnly_get_template(
									'contact-agent/emails/partials/property-card.php',
									array(
										'property_image'     => $property_image ?? '',
										'property_title'     => $property_title ?? '',
										'property_url'       => $property_url ?? '',
										'property_price'       => $property_price ?? '',
										'property_address'     => $property_address ?? '',
										'property_location'    => $property_location ?? '',
										'property_bedrooms'  => $property_bedrooms ?? '',
										'property_bathrooms' => $property_bathrooms ?? '',
										'property_area'      => $property_area ?? '',
									)
								);
								?>

								<?php if ( $hvnly_agent_name ) : ?>
									<p style="margin:-8px 0 22px;font-size:14px;line-height:1.6;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
										<?php
										printf(
											/* translators: %s: agent name */
											esc_html__( 'Assigned agent: %s', 'havenlytics' ),
											esc_html( $hvnly_agent_name )
										);
										?>
									</p>
								<?php endif; ?>
							<?php endif; ?>

							<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 22px;border-left:4px solid <?php echo esc_attr( $hvnly_brand_primary ); ?>;background:<?php echo esc_attr( $hvnly_brand_primary_light ); ?>;border-radius:0 8px 8px 0;">
								<tr>
									<td style="padding:16px 18px;">
										<p style="margin:0;font-size:15px;line-height:1.7;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
											<?php esc_html_e( 'This is an automated confirmation that we received your message. A member of our team will follow up with you shortly.', 'havenlytics' ); ?>
										</p>
									</td>
								</tr>
							</table>

							<p style="margin:0;font-size:13px;line-height:1.6;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;opacity:0.85;text-align:center;">
								<?php
								printf(
									/* translators: %s: site name */
									esc_html__( 'Sent automatically by %s Contact Agent.', 'havenlytics' ),
									esc_html( $site_name )
								);
								?>
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
