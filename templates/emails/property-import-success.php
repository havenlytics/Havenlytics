<?php
/**
 * Property import success email template.
 *
 * @package     Havenlytics
 * @subpackage  Templates/emails
 * @since       3.0.2
 *
 * @var string $user_name
 * @var string $user_email
 * @var string $import_count
 * @var string $properties_url
 * @var string $site_name
 * @var string $site_url
 * @var string $intro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/partials/brand-colors.php';

$hvnly_user_name    = isset( $user_name ) ? (string) $user_name : '';
$hvnly_intro        = isset( $intro ) ? (string) $intro : '';
$hvnly_import_count = isset( $import_count ) ? (string) $import_count : '0';
$hvnly_properties   = isset( $properties_url ) ? (string) $properties_url : '';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Property import complete', 'havenlytics' ); ?></title>
</head>
<body style="margin:0;padding:0;background:<?php echo esc_attr( $hvnly_brand_primary_light ); ?>;font-family:Arial,Helvetica,sans-serif;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:<?php echo esc_attr( $hvnly_brand_primary_light ); ?>;padding:28px 12px;">
		<tr>
			<td align="center">
				<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:<?php echo esc_attr( $hvnly_brand_white ); ?>;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(108,96,254,0.15);">
					<tr>
						<td style="padding:28px 28px 24px;background:linear-gradient(135deg, <?php echo esc_attr( $hvnly_brand_primary ); ?> 0%, <?php echo esc_attr( $hvnly_brand_secondary ); ?> 100%);">
							<h1 style="margin:0;font-size:26px;line-height:1.25;font-weight:bold;color:<?php echo esc_attr( $hvnly_brand_white ); ?>;">
								<?php esc_html_e( 'Import Complete', 'havenlytics' ); ?>
							</h1>
							<?php if ( $hvnly_user_name ) : ?>
								<p style="margin:10px 0 0;font-size:15px;line-height:1.5;color:<?php echo esc_attr( $hvnly_brand_white ); ?>;opacity:0.95;">
									<?php
									printf(
										/* translators: %s: user display name */
										esc_html__( 'Hello %s,', 'havenlytics' ),
										esc_html( $hvnly_user_name )
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

							<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 22px;border-radius:12px;border:2px solid <?php echo esc_attr( $hvnly_brand_primary ); ?>;overflow:hidden;">
								<tr>
									<td style="padding:20px 22px;background:<?php echo esc_attr( $hvnly_brand_primary_light ); ?>;">
										<p style="margin:0 0 6px;font-size:12px;line-height:1.4;font-weight:bold;letter-spacing:0.06em;text-transform:uppercase;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
											<?php esc_html_e( 'Import Summary', 'havenlytics' ); ?>
										</p>
										<p style="margin:0;font-size:28px;line-height:1.2;font-weight:bold;color:<?php echo esc_attr( $hvnly_brand_primary ); ?>;">
											<?php
											printf(
												/* translators: %s: number of imported properties */
												esc_html( _n( '%s Property', '%s Properties', (int) $hvnly_import_count, 'havenlytics' ) ),
												esc_html( $hvnly_import_count )
											);
											?>
										</p>
										<p style="margin:10px 0 0;font-size:14px;line-height:1.6;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
											<?php esc_html_e( 'Your demo properties are ready to review and customize.', 'havenlytics' ); ?>
										</p>
									</td>
								</tr>
							</table>

							<?php if ( $hvnly_properties ) : ?>
								<table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 22px;">
									<tr>
										<td style="border-radius:8px;background:<?php echo esc_attr( $hvnly_brand_primary ); ?>;">
											<a href="<?php echo esc_url( $hvnly_properties ); ?>" style="display:inline-block;padding:12px 22px;font-size:14px;line-height:1;font-weight:bold;color:<?php echo esc_attr( $hvnly_brand_white ); ?>;text-decoration:none;">
												<?php esc_html_e( 'View Properties', 'havenlytics' ); ?>
											</a>
										</td>
									</tr>
								</table>
							<?php endif; ?>

							<p style="margin:0;font-size:13px;line-height:1.6;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;opacity:0.85;text-align:center;">
								<?php
								printf(
									/* translators: %s: site name */
									esc_html__( 'Sent by %s', 'havenlytics' ),
									esc_html( (string) ( $site_name ?? get_bloginfo( 'name' ) ) )
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
