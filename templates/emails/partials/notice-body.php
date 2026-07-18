<?php
/**
 * Shared email body — intro paragraph, optional detail rows, CTA, footnote.
 *
 * Sprint 24C. Renders inside the cell opened by layout-head.php.
 * Overridable at {theme}/havenlytics/emails/partials/notice-body.php.
 *
 * @package Havenlytics
 * @since   3.2.1
 *
 * @var string                  $intro        Lead paragraph.
 * @var array<string,string>    $detail_rows  Optional label => value rows.
 * @var string                  $cta_url      Optional CTA URL.
 * @var string                  $cta_label    Optional CTA label.
 * @var string                  $footnote     Optional small print below the CTA.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/brand-colors.php';

$hvnly_intro    = isset( $intro ) ? (string) $intro : '';
$hvnly_rows     = isset( $detail_rows ) && is_array( $detail_rows ) ? $detail_rows : array();
$hvnly_cta_url  = isset( $cta_url ) ? (string) $cta_url : '';
$hvnly_cta_text = isset( $cta_label ) ? (string) $cta_label : '';
$hvnly_note     = isset( $footnote ) ? (string) $footnote : '';

$hvnly_ink_muted = '#5b5870';
$hvnly_hairline  = '#e4e4ed';
$hvnly_soft_bg   = '#faf9fc';
?>

<?php if ( '' !== $hvnly_intro ) : ?>
	<p class="hvnly-text" style="margin:0 0 20px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;">
		<?php echo esc_html( $hvnly_intro ); ?>
	</p>
<?php endif; ?>

<?php if ( ! empty( $hvnly_rows ) ) : ?>
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 22px;background:<?php echo esc_attr( $hvnly_soft_bg ); ?>;border:1px solid <?php echo esc_attr( $hvnly_hairline ); ?>;border-radius:8px;" bgcolor="<?php echo esc_attr( $hvnly_soft_bg ); ?>">
		<?php foreach ( $hvnly_rows as $hvnly_label => $hvnly_value ) : ?>
			<?php if ( '' === trim( (string) $hvnly_value ) ) { continue; } ?>
			<tr>
				<td style="padding:10px 16px;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:bold;text-transform:uppercase;letter-spacing:0.05em;color:<?php echo esc_attr( $hvnly_ink_muted ); ?>;white-space:nowrap;vertical-align:top;width:38%;">
					<?php echo esc_html( (string) $hvnly_label ); ?>
				</td>
				<td class="hvnly-text" style="padding:10px 16px 10px 0;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;vertical-align:top;">
					<?php echo nl2br( esc_html( (string) $hvnly_value ) ); ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
<?php endif; ?>

<?php
if ( '' !== $hvnly_cta_url && '' !== $hvnly_cta_text ) {
	hvnly_get_template(
		'emails/partials/button.php',
		array(
			'button_url'   => $hvnly_cta_url,
			'button_label' => $hvnly_cta_text,
		)
	);
}
?>

<?php if ( '' !== $hvnly_note ) : ?>
	<p class="hvnly-muted" style="margin:20px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;color:<?php echo esc_attr( $hvnly_ink_muted ); ?>;">
		<?php echo esc_html( $hvnly_note ); ?>
	</p>
<?php endif; ?>
