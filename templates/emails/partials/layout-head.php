<?php
/**
 * Shared email layout — opening chrome.
 *
 * Sprint 24C. Every Havenlytics email opens with this. Overridable by a theme
 * at {theme}/havenlytics/emails/partials/layout-head.php.
 *
 * Outlook notes:
 *  - Outlook (Word engine) ignores background-image, so the header carries a
 *    real bgcolor attribute AND a solid background-color. The gradient is a
 *    progressive enhancement for clients that support it.
 *  - Outlook ignores border-radius. The card degrades to square corners.
 *
 * Dark mode:
 *  - color-scheme / supported-color-schemes let Apple Mail and iOS render the
 *    light design rather than force-inverting it into mud.
 *
 * @package Havenlytics
 * @since   3.2.1
 *
 * @var string $title             Email heading.
 * @var string $preheader         Hidden inbox preview line.
 * @var string $user_name         Optional greeting name.
 * @var string $brand_logo_url    Logo URL, or '' for the text wordmark.
 * @var string $brand_site_name   Site name.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/brand-colors.php';

$hvnly_title     = isset( $title ) ? (string) $title : '';
$hvnly_preheader = isset( $preheader ) ? (string) $preheader : '';
$hvnly_greeting  = isset( $user_name ) ? (string) $user_name : '';
$hvnly_logo      = isset( $brand_logo_url ) ? (string) $brand_logo_url : '';
$hvnly_site      = isset( $brand_site_name ) ? (string) $brand_site_name : get_bloginfo( 'name' );

$hvnly_ink       = '#1e1e2f';
$hvnly_ink_muted = '#5b5870';
$hvnly_page_bg   = '#f4f3f9';
$hvnly_hairline  = '#e4e4ed';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="color-scheme" content="light dark">
	<meta name="supported-color-schemes" content="light dark">
	<title><?php echo esc_html( $hvnly_title ); ?></title>
	<!--[if mso]>
	<style type="text/css">
		body, table, td, a { font-family: Arial, Helvetica, sans-serif !important; }
	</style>
	<![endif]-->
	<style type="text/css">
		@media only screen and (max-width: 620px) {
			.hvnly-card { width: 100% !important; }
			.hvnly-pad { padding-left: 20px !important; padding-right: 20px !important; }
			.hvnly-h1 { font-size: 22px !important; }
			.hvnly-btn a { display: block !important; }
		}
		@media (prefers-color-scheme: dark) {
			.hvnly-body { background: #16151d !important; }
			.hvnly-card { background: #211f2b !important; }
			.hvnly-text { color: #ecebf2 !important; }
			.hvnly-muted { color: #a09cb0 !important; }
			.hvnly-foot { background: #1a1922 !important; border-color: #2e2c3a !important; }
		}
	</style>
</head>
<body class="hvnly-body" style="margin:0;padding:0;width:100%;background:<?php echo esc_attr( $hvnly_page_bg ); ?>;-webkit-font-smoothing:antialiased;" bgcolor="<?php echo esc_attr( $hvnly_page_bg ); ?>">

	<?php // Inbox preview text — visible in the list view, never in the body. ?>
	<div style="display:none;font-size:1px;color:<?php echo esc_attr( $hvnly_page_bg ); ?>;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
		<?php echo esc_html( $hvnly_preheader ); ?>
		&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;
	</div>

	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:<?php echo esc_attr( $hvnly_page_bg ); ?>;" bgcolor="<?php echo esc_attr( $hvnly_page_bg ); ?>">
		<tr>
			<td align="center" style="padding:32px 12px;">

				<table role="presentation" class="hvnly-card" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;background:<?php echo esc_attr( $hvnly_brand_white ); ?>;border-radius:12px;overflow:hidden;" bgcolor="<?php echo esc_attr( $hvnly_brand_white ); ?>">

					<?php // ---- Brand bar. bgcolor carries Outlook; the gradient is a bonus. ---- ?>
					<tr>
						<td class="hvnly-pad" align="left" bgcolor="<?php echo esc_attr( $hvnly_brand_primary ); ?>" style="padding:24px 32px;background-color:<?php echo esc_attr( $hvnly_brand_primary ); ?>;background-image:linear-gradient(120deg, <?php echo esc_attr( $hvnly_brand_primary ); ?> 0%, <?php echo esc_attr( $hvnly_brand_secondary ); ?> 100%);">
							<?php if ( $hvnly_logo ) : ?>
								<img src="<?php echo esc_url( $hvnly_logo ); ?>" alt="<?php echo esc_attr( $hvnly_site ); ?>" height="28" style="display:block;height:28px;width:auto;border:0;outline:none;text-decoration:none;">
							<?php else : ?>
								<span style="display:inline-block;font-family:Arial,Helvetica,sans-serif;font-size:17px;font-weight:bold;letter-spacing:-0.01em;color:<?php echo esc_attr( $hvnly_brand_white ); ?>;">
									<?php echo esc_html( $hvnly_site ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>

					<?php // ---- Heading + greeting ---- ?>
					<tr>
						<td class="hvnly-pad" align="left" style="padding:32px 32px 0;">
							<h1 class="hvnly-h1 hvnly-text" style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:1.25;font-weight:bold;color:<?php echo esc_attr( $hvnly_ink ); ?>;">
								<?php echo esc_html( $hvnly_title ); ?>
							</h1>
							<?php if ( '' !== $hvnly_greeting ) : ?>
								<p class="hvnly-muted" style="margin:10px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:<?php echo esc_attr( $hvnly_ink_muted ); ?>;">
									<?php
									printf(
										/* translators: %s: recipient first name. */
										esc_html__( 'Hi %s,', 'havenlytics' ),
										esc_html( $hvnly_greeting )
									);
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>

					<?php // ---- Body opens; the calling template writes its own <tr> rows next ---- ?>
					<tr>
						<td class="hvnly-pad hvnly-text" align="left" style="padding:20px 32px 32px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:<?php echo esc_attr( $hvnly_ink ); ?>;">
