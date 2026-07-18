<?php
/**
 * Shared email CTA button — "bulletproof" (renders in Outlook).
 *
 * Sprint 24C. Outlook's Word rendering engine ignores padding and border-radius
 * on anchors, so a plain styled <a> collapses to bare underlined text. The MSO
 * conditional below draws a real VML rounded rectangle for Outlook only; every
 * other client gets the normal anchor.
 *
 * Overridable at {theme}/havenlytics/emails/partials/button.php.
 *
 * @package Havenlytics
 * @since   3.2.1
 *
 * @var string $button_url   Destination URL.
 * @var string $button_label Button text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/brand-colors.php';

$hvnly_btn_url   = isset( $button_url ) ? (string) $button_url : '';
$hvnly_btn_label = isset( $button_label ) ? (string) $button_label : '';

if ( '' === $hvnly_btn_url || '' === $hvnly_btn_label ) {
	return;
}
?>
<table role="presentation" class="hvnly-btn" cellspacing="0" cellpadding="0" border="0" style="margin:8px 0 4px;">
	<tr>
		<td align="left">
			<!--[if mso]>
			<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word"
				href="<?php echo esc_url( $hvnly_btn_url ); ?>"
				style="height:44px;v-text-anchor:middle;width:260px;" arcsize="14%"
				stroke="f" fillcolor="<?php echo esc_attr( $hvnly_brand_primary ); ?>">
				<w:anchorlock/>
				<center style="color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;">
					<?php echo esc_html( $hvnly_btn_label ); ?>
				</center>
			</v:roundrect>
			<![endif]-->
			<!--[if !mso]><!-- -->
			<a href="<?php echo esc_url( $hvnly_btn_url ); ?>"
				style="display:inline-block;padding:13px 26px;border-radius:6px;background:<?php echo esc_attr( $hvnly_brand_primary ); ?>;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;line-height:1.2;text-decoration:none;mso-hide:all;">
				<?php echo esc_html( $hvnly_btn_label ); ?>
			</a>
			<!--<![endif]-->
		</td>
	</tr>
</table>
