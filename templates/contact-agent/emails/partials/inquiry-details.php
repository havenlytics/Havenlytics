<?php
/**
 * Inquiry contact details block for Contact Agent emails.
 *
 * @package Havenlytics
 * @since   3.0.2
 *
 * @var string $hvnly_sender_name
 * @var string $hvnly_sender_email
 * @var string $hvnly_sender_phone
 * @var string $hvnly_message
 * @var string $hvnly_created_at Optional submission timestamp.
 * @var int    $inquiry_id       Optional inquiry ID.
 * @var bool   $hvnly_show_inquiry_id Whether to show inquiry ID row.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/brand-colors.php';

$hvnly_created_at        = isset( $hvnly_created_at ) ? (string) $hvnly_created_at : '';
$hvnly_show_inquiry_id   = ! empty( $hvnly_show_inquiry_id ) && ! empty( $inquiry_id );
?>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;border-radius:12px;border:2px solid <?php echo esc_attr( $hvnly_brand_primary ); ?>;overflow:hidden;">
	<tr>
		<td style="padding:18px 22px;background:linear-gradient(135deg, <?php echo esc_attr( $hvnly_brand_primary ); ?> 0%, <?php echo esc_attr( $hvnly_brand_secondary ); ?> 100%);">
			<h2 style="margin:0;font-size:18px;line-height:1.3;font-weight:bold;color:<?php echo esc_attr( $hvnly_brand_white ); ?>;">
				<?php esc_html_e( 'Inquiry Details', 'havenlytics' ); ?>
			</h2>
			<p style="margin:6px 0 0;font-size:13px;line-height:1.5;color:<?php echo esc_attr( $hvnly_brand_white ); ?>;opacity:0.92;">
				<?php esc_html_e( 'A visitor submitted this message from your property contact form.', 'havenlytics' ); ?>
			</p>
		</td>
	</tr>
	<tr>
		<td style="padding:22px 24px;background:<?php echo esc_attr( $hvnly_brand_white ); ?>;">
			<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 18px;">
				<?php if ( $hvnly_show_inquiry_id ) : ?>
					<tr>
						<td style="padding:0 0 10px;font-size:14px;line-height:1.5;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
							<strong style="color:<?php echo esc_attr( $hvnly_brand_primary ); ?>;"><?php esc_html_e( 'Inquiry ID:', 'havenlytics' ); ?></strong>
							#<?php echo esc_html( (string) absint( $inquiry_id ) ); ?>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<td style="padding:0 0 10px;font-size:15px;line-height:1.5;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
						<strong style="color:<?php echo esc_attr( $hvnly_brand_primary ); ?>;"><?php esc_html_e( 'From:', 'havenlytics' ); ?></strong>
						<?php echo esc_html( $hvnly_sender_name ); ?>
					</td>
				</tr>
				<tr>
					<td style="padding:0 0 10px;font-size:15px;line-height:1.5;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
						<strong style="color:<?php echo esc_attr( $hvnly_brand_primary ); ?>;"><?php esc_html_e( 'Email:', 'havenlytics' ); ?></strong>
						<a href="mailto:<?php echo esc_attr( $hvnly_sender_email ); ?>" style="color:<?php echo esc_attr( $hvnly_brand_primary ); ?>;text-decoration:none;font-weight:bold;"><?php echo esc_html( $hvnly_sender_email ); ?></a>
					</td>
				</tr>
				<?php if ( '' !== trim( $hvnly_sender_phone ) ) : ?>
					<tr>
						<td style="padding:0 0 10px;font-size:15px;line-height:1.5;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
							<strong style="color:<?php echo esc_attr( $hvnly_brand_primary ); ?>;"><?php esc_html_e( 'Phone:', 'havenlytics' ); ?></strong>
							<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $hvnly_sender_phone ) ); ?>" style="color:<?php echo esc_attr( $hvnly_brand_primary ); ?>;text-decoration:none;"><?php echo esc_html( $hvnly_sender_phone ); ?></a>
						</td>
					</tr>
				<?php endif; ?>
				<?php if ( '' !== trim( $hvnly_created_at ) ) : ?>
					<tr>
						<td style="padding:0 0 10px;font-size:14px;line-height:1.5;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
							<strong style="color:<?php echo esc_attr( $hvnly_brand_primary ); ?>;"><?php esc_html_e( 'Submitted:', 'havenlytics' ); ?></strong>
							<?php echo esc_html( $hvnly_created_at ); ?> UTC
						</td>
					</tr>
				<?php endif; ?>
			</table>

			<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-left:4px solid <?php echo esc_attr( $hvnly_brand_primary ); ?>;background:<?php echo esc_attr( $hvnly_brand_primary_light ); ?>;border-radius:0 8px 8px 0;">
				<tr>
					<td style="padding:16px 18px;">
						<p style="margin:0 0 8px;font-size:12px;line-height:1.4;font-weight:bold;letter-spacing:0.05em;text-transform:uppercase;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;">
							<?php esc_html_e( 'Message', 'havenlytics' ); ?>
						</p>
						<p style="margin:0;font-size:16px;line-height:1.7;color:<?php echo esc_attr( $hvnly_brand_secondary ); ?>;white-space:pre-wrap;"><?php echo esc_html( $hvnly_message ); ?></p>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
