<?php
/**
 * Admin notification email template.
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
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/partials/brand-colors.php';

$hvnly_sender_name  = isset( $inquiry['sender_name'] ) ? (string) $inquiry['sender_name'] : '';
$hvnly_sender_email = isset( $inquiry['sender_email'] ) ? (string) $inquiry['sender_email'] : '';
$hvnly_sender_phone = isset( $inquiry['sender_phone'] ) ? (string) $inquiry['sender_phone'] : '';
$hvnly_message      = isset( $inquiry['message'] ) ? (string) $inquiry['message'] : '';
$hvnly_agent_name   = isset( $agent['name'] ) ? (string) $agent['name'] : '';
$hvnly_agent_email  = isset( $agent['email'] ) ? (string) $agent['email'] : '';
$hvnly_created_at   = isset( $inquiry['created_at'] ) ? (string) $inquiry['created_at'] : '';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'New Property Inquiry (Admin Copy)', 'havenlytics' ); ?></title>
</head>
<body style="margin:0;padding:0;background:<?php echo esc_attr( $hvnly_brand_primary_light ); ?>;font-family:Arial,Helvetica,sans-serif;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:<?php echo esc_attr( $hvnly_brand_primary_light ); ?>;padding:28px 12px;">
		<tr>
			<td align="center">
				<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:<?php echo esc_attr( $hvnly_brand_white ); ?>;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(108,96,254,0.15);">
					<tr>
						<td style="padding:28px 28px 24px;background:linear-gradient(135deg, <?php echo esc_attr( $hvnly_brand_secondary ); ?> 0%, <?php echo esc_attr( $hvnly_brand_primary ); ?> 100%);">
							<h1 style="margin:0;font-size:26px;line-height:1.25;font-weight:bold;color:<?php echo esc_attr( $hvnly_brand_white ); ?>;">
								<?php esc_html_e( 'New Property Inquiry', 'havenlytics' ); ?>
							</h1>
							<p style="margin:10px 0 0;font-size:14px;line-height:1.5;color:<?php echo esc_attr( $hvnly_brand_white ); ?>;opacity:0.92;">
								<?php
								printf(
									/* translators: %d: inquiry ID */
									esc_html__( 'Inquiry #%d — admin copy', 'havenlytics' ),
									(int) $inquiry_id
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<td style="padding:28px 28px 24px;">
							<?php
							if ( function_exists( 'hvnly_get_template' ) ) {
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
							}

							if ( $hvnly_agent_name || $hvnly_agent_email ) : ?>
								<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;border-radius:10px;border:1px solid <?php echo esc_attr( $hvnly_brand_primary ); ?>;">
									<tr>
										<td style="padding:14px 18px;background:<?php echo esc_attr( $hvnly_brand_primary_light ); ?>;">
											<p style="margin:0;font-size:14px;line-height:1.6;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
												<strong style="color:<?php echo esc_attr( $hvnly_brand_primary ); ?>;"><?php esc_html_e( 'Assigned agent:', 'havenlytics' ); ?></strong>
												<?php echo esc_html( trim( $hvnly_agent_name . ( $hvnly_agent_email ? ' (' . $hvnly_agent_email . ')' : '' ) ) ); ?>
											</p>
										</td>
									</tr>
								</table>
							<?php endif;

							if ( function_exists( 'hvnly_get_template' ) ) {
								hvnly_get_template(
									'contact-agent/emails/partials/inquiry-details.php',
									array(
										'hvnly_sender_name'     => $hvnly_sender_name,
										'hvnly_sender_email'    => $hvnly_sender_email,
										'hvnly_sender_phone'    => $hvnly_sender_phone,
										'hvnly_message'         => $hvnly_message,
										'hvnly_created_at'      => $hvnly_created_at,
										'inquiry_id'            => $inquiry_id,
										'hvnly_show_inquiry_id' => true,
									)
								);
							}
							?>

							<p style="margin:0;font-size:13px;line-height:1.6;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;opacity:0.85;text-align:center;">
								<?php
								printf(
									/* translators: %s: site name */
									esc_html__( 'Admin notification from %s Contact Agent.', 'havenlytics' ),
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
