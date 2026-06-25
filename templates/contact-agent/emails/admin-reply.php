<?php
/**
 * Admin reply email template (Contact Agent inbox).
 *
 * @package     Havenlytics
 * @subpackage  Templates/contact-agent/emails
 * @since       3.0.2
 *
 * @var int                  $inquiry_id
 * @var array<string, mixed> $inquiry
 * @var string               $property_title
 * @var string               $property_url
 * @var string               $property_image
 * @var string               $property_price
 * @var string               $property_address
 * @var string               $property_location
 * @var string               $sender_name
 * @var string               $reply_message
 * @var string               $admin_name
 * @var string               $original_message
 * @var string               $site_name
 * @var string               $site_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/partials/brand-colors.php';

$hvnly_sender_name      = isset( $sender_name ) ? (string) $sender_name : '';
$hvnly_reply_message    = isset( $reply_message ) ? (string) $reply_message : '';
$hvnly_admin_name       = isset( $admin_name ) ? (string) $admin_name : '';
$hvnly_original_message = isset( $original_message ) ? (string) $original_message : '';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Reply to your property inquiry', 'havenlytics' ); ?></title>
</head>
<body style="margin:0;padding:0;background:<?php echo esc_attr( $hvnly_brand_primary_light ); ?>;font-family:Arial,Helvetica,sans-serif;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:<?php echo esc_attr( $hvnly_brand_primary_light ); ?>;padding:28px 12px;">
		<tr>
			<td align="center">
				<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:<?php echo esc_attr( $hvnly_brand_white ); ?>;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(108,96,254,0.15);">
					<tr>
						<td style="padding:28px 28px 24px;background:linear-gradient(135deg, <?php echo esc_attr( $hvnly_brand_primary ); ?> 0%, <?php echo esc_attr( $hvnly_brand_secondary ); ?> 100%);">
							<h1 style="margin:0;font-size:24px;line-height:1.25;font-weight:bold;color:<?php echo esc_attr( $hvnly_brand_white ); ?>;">
								<?php esc_html_e( 'Reply to Your Inquiry', 'havenlytics' ); ?>
							</h1>
							<?php if ( $hvnly_sender_name ) : ?>
								<p style="margin:10px 0 0;font-size:15px;line-height:1.5;color:<?php echo esc_attr( $hvnly_brand_white ); ?>;opacity:0.95;">
									<?php
									printf(
										/* translators: %s: sender name */
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
							<?php if ( function_exists( 'hvnly_get_template' ) && ! empty( $property_title ) ) : ?>
								<?php
								hvnly_get_template(
									'contact-agent/emails/partials/property-card.php',
									array(
										'property_image'     => $property_image ?? '',
										'property_title'     => $property_title ?? '',
										'property_url'       => $property_url ?? '',
										'property_price'     => $property_price ?? '',
										'property_address'   => $property_address ?? '',
										'property_location'  => $property_location ?? '',
										'property_bedrooms'  => $property_bedrooms ?? '',
										'property_bathrooms' => $property_bathrooms ?? '',
										'property_area'      => $property_area ?? '',
									)
								);
								?>
							<?php endif; ?>

							<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 22px;border-radius:12px;border:2px solid <?php echo esc_attr( $hvnly_brand_primary ); ?>;overflow:hidden;">
								<tr>
									<td style="padding:18px 22px;background:linear-gradient(135deg, <?php echo esc_attr( $hvnly_brand_primary ); ?> 0%, <?php echo esc_attr( $hvnly_brand_secondary ); ?> 100%);">
										<h2 style="margin:0;font-size:17px;line-height:1.3;font-weight:bold;color:<?php echo esc_attr( $hvnly_brand_white ); ?>;">
											<?php
											if ( $hvnly_admin_name ) {
												printf(
													/* translators: %s: admin display name */
													esc_html__( 'Message from %s', 'havenlytics' ),
													esc_html( $hvnly_admin_name )
												);
											} else {
												esc_html_e( 'Our team replied', 'havenlytics' );
											}
											?>
										</h2>
									</td>
								</tr>
								<tr>
									<td style="padding:22px 24px;background:<?php echo esc_attr( $hvnly_brand_white ); ?>;">
										<p style="margin:0;font-size:16px;line-height:1.75;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;white-space:pre-wrap;"><?php echo esc_html( $hvnly_reply_message ); ?></p>
									</td>
								</tr>
							</table>

							<?php if ( '' !== trim( $hvnly_original_message ) ) : ?>
								<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 22px;border-left:4px solid <?php echo esc_attr( $hvnly_brand_secondary ); ?>;background:<?php echo esc_attr( $hvnly_brand_primary_light ); ?>;border-radius:0 8px 8px 0;">
									<tr>
										<td style="padding:16px 18px;">
											<p style="margin:0 0 8px;font-size:12px;line-height:1.4;font-weight:bold;letter-spacing:0.05em;text-transform:uppercase;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
												<?php esc_html_e( 'Your original message', 'havenlytics' ); ?>
											</p>
											<p style="margin:0;font-size:14px;line-height:1.6;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;white-space:pre-wrap;opacity:0.9;"><?php echo esc_html( $hvnly_original_message ); ?></p>
										</td>
									</tr>
								</table>
							<?php endif; ?>

							<p style="margin:0;font-size:13px;line-height:1.6;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;opacity:0.85;text-align:center;">
								<?php
								printf(
									/* translators: %s: site name */
									esc_html__( 'You can reply directly to this email to continue the conversation with %s.', 'havenlytics' ),
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
