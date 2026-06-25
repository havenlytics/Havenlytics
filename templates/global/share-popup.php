<?php
/**
 * Global property share modal markup (one instance per page).
 *
 * @package Havenlytics
 * @subpackage Templates/global
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'hvnly_should_render_share_popup' ) && ! hvnly_should_render_share_popup() ) {
	return;
}
?>
<div id="hvnly-property-global-share-popup" class="hvnly-property-share-popup-overlay" role="dialog" aria-modal="true"
	aria-labelledby="hvnly-share-popup-title" aria-hidden="true">
	<div class="hvnly-property-share-popup-content" role="document">
		<div class="hvnly-property-popup-header">
			<h4 id="hvnly-share-popup-title" class="hvnly-property-popup-title">
				<?php echo esc_html__( 'Share This Property', 'havenlytics' ); ?>
			</h4>
			<button type="button" class="hvnly-property-popup-close"
				aria-label="<?php esc_attr_e( 'Close share popup', 'havenlytics' ); ?>">
				<i class="fas fa-times" aria-hidden="true"></i>
			</button>
		</div>

		<div class="hvnly-property-share-stats">
			<div class="hvnly-property-auto-close-timer" aria-live="polite">
				<i class="fas fa-clock" aria-hidden="true"></i>
				<span class="hvnly-property-timer-text"><?php echo esc_html__( 'Auto-close in 15s', 'havenlytics' ); ?></span>
			</div>
		</div>

		<div class="hvnly-property-share-platforms-grid" id="hvnly-property-share-platforms-container" role="group"
			aria-label="<?php esc_attr_e( 'Social sharing platforms', 'havenlytics' ); ?>"></div>

		<div class="hvnly-property-copy-link-section hvnly-property-active-section">
			<div class="hvnly-property-copy-link-header">
				<h5 class="hvnly-property-copy-title"><?php echo esc_html__( 'Copy Property Link', 'havenlytics' ); ?></h5>
				<span class="hvnly-property-copy-success" aria-live="polite">
					<i class="fas fa-check" aria-hidden="true"></i>
					<span class="screen-reader-text"><?php esc_html_e( 'Copied successfully', 'havenlytics' ); ?></span>
				</span>
			</div>
			<div class="hvnly-property-copy-link-input">
				<input type="text" class="hvnly-property-link-input" value="" readonly
					aria-label="<?php esc_attr_e( 'Property link to copy', 'havenlytics' ); ?>">
				<button type="button" class="hvnly-property-copy-btn"
					aria-label="<?php esc_attr_e( 'Copy link to clipboard', 'havenlytics' ); ?>">
					<i class="fas fa-copy" aria-hidden="true"></i>
					<span class="hvnly-property-copy-btn-text"><?php echo esc_html__( 'Copy', 'havenlytics' ); ?></span>
				</button>
			</div>
		</div>
	</div>
</div>
