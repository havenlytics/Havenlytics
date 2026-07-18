<?php
/**
 * Agent Portal — Workspace unavailable (feature disabled by administrator).
 *
 * Theme override: your-theme/havenlytics/agent-portal/unavailable.php
 *
 * Server-rendered ONLY. The SPA script is not loaded while Workspace is offline,
 * so no React route (/profile, /settings, /listings, …) can bypass this page.
 *
 * @package Havenlytics
 * @subpackage Templates/agent-portal
 * @since 3.3.0
 */

defined( 'ABSPATH' ) || exit;

$hvnly_mount_id      = isset( $mount_id ) ? (string) $mount_id : 'hvnly-ws-root';
$hvnly_title         = isset( $title ) ? (string) $title : __( 'Agent Workspace is currently unavailable', 'havenlytics' );
$hvnly_desc          = isset( $description ) ? (string) $description : __( 'The site administrator has temporarily disabled the Agent Workspace. If you believe this is an error, please contact the site administrator.', 'havenlytics' );
$hvnly_home          = isset( $home_url ) ? (string) $home_url : home_url( '/' );
$hvnly_home_label    = isset( $home_label ) ? (string) $home_label : __( 'Return to Homepage', 'havenlytics' );
$hvnly_contact_label = isset( $contact_label ) ? (string) $contact_label : __( 'Contact Administrator', 'havenlytics' );
$hvnly_logout_label  = isset( $logout_label ) ? (string) $logout_label : __( 'Sign Out', 'havenlytics' );
$hvnly_support_label = isset( $support_label ) ? (string) $support_label : __( 'Need help?', 'havenlytics' );
$hvnly_email         = isset( $support_email ) ? (string) $support_email : '';
$hvnly_logout        = isset( $logout_url ) ? (string) $logout_url : '';
$hvnly_icon          = isset( $icon_url ) ? (string) $icon_url : '';
$hvnly_brand         = isset( $brand_name ) ? (string) $brand_name : __( 'Havenlytics', 'havenlytics' );
$hvnly_subtitle      = isset( $workspace_label ) ? (string) $workspace_label : __( 'Workspace', 'havenlytics' );
?>
<div
	id="<?php echo esc_attr( $hvnly_mount_id ); ?>"
	class="hvnly-ws-mount"
	data-hvnly-ws-mount="1"
	data-hvnly-ws-unavailable="1"
	aria-live="polite"
>
	<div class="hvnly-ws-auth" data-hvnly-ws-auth="1" data-hvnly-ws-auth-unavailable="1">
		<div class="hvnly-ws-auth__panel">
			<div class="hvnly-ws-auth__brand">
				<div class="hvnly-ws-brand hvnly-ws-brand--lg hvnly-ws-brand--stacked">
					<?php if ( $hvnly_icon ) : ?>
						<img
							class="hvnly-ws-brand__icon"
							src="<?php echo esc_url( $hvnly_icon ); ?>"
							alt=""
							width="40"
							height="40"
							decoding="async"
						/>
					<?php else : ?>
						<span class="hvnly-ws-brand__icon hvnly-ws-brand__icon--fallback" aria-hidden="true"></span>
					<?php endif; ?>
					<span class="hvnly-ws-brand__text">
						<span class="hvnly-ws-brand__name"><?php echo esc_html( $hvnly_brand ); ?></span>
						<span class="hvnly-ws-brand__subtitle"><?php echo esc_html( $hvnly_subtitle ); ?></span>
					</span>
				</div>
			</div>

			<div class="hvnly-ui-card hvnly-ws-auth__card">
				<div class="hvnly-ui-card__header">
					<div class="hvnly-ws-auth__title-focus" tabindex="-1">
						<h1 class="hvnly-ui-card__title"><?php echo esc_html( $hvnly_title ); ?></h1>
					</div>
					<p class="hvnly-ws-auth__subtitle"><?php echo esc_html( $hvnly_desc ); ?></p>
				</div>
				<div class="hvnly-ui-card__body">
					<div class="hvnly-ws-stack">
						<div class="hvnly-ws-unavailable__icon" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 56 56" fill="none" focusable="false">
								<circle cx="28" cy="28" r="26" stroke="currentColor" stroke-width="2" opacity="0.25"/>
								<path d="M18 26h20v16H18V26z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
								<path d="M22 26v-5a6 6 0 0 1 12 0v5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
								<circle cx="28" cy="34" r="2" fill="currentColor"/>
							</svg>
						</div>

						<div class="hvnly-ui-alert hvnly-ui-alert--warning" role="status">
							<?php echo esc_html( $hvnly_desc ); ?>
						</div>

						<?php if ( is_email( $hvnly_email ) ) : ?>
							<p class="hvnly-ui-field__hint">
								<?php echo esc_html( $hvnly_support_label ); ?>
								<a href="<?php echo esc_url( 'mailto:' . $hvnly_email ); ?>"><?php echo esc_html( $hvnly_email ); ?></a>
							</p>
						<?php endif; ?>

						<p>
							<a
								class="hvnly-ui-button hvnly-ui-button--primary hvnly-ui-button--block"
								href="<?php echo esc_url( $hvnly_home ); ?>"
							>
								<?php echo esc_html( $hvnly_home_label ); ?>
							</a>
						</p>

						<?php if ( is_email( $hvnly_email ) ) : ?>
							<p>
								<a
									class="hvnly-ui-button hvnly-ui-button--secondary hvnly-ui-button--block"
									href="<?php echo esc_url( 'mailto:' . $hvnly_email ); ?>"
								>
									<?php echo esc_html( $hvnly_contact_label ); ?>
								</a>
							</p>
						<?php endif; ?>

						<?php if ( $hvnly_logout ) : ?>
							<p>
								<a
									class="hvnly-ui-button hvnly-ui-button--ghost hvnly-ui-button--block"
									href="<?php echo esc_url( $hvnly_logout ); ?>"
								>
									<?php echo esc_html( $hvnly_logout_label ); ?>
								</a>
							</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
// Drop any stale SPA identity cache so a later re-enable starts clean.
?>
<script>
try { sessionStorage.removeItem('hvnly_ws_me_v1'); } catch (e) {}
</script>
