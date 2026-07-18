<?php
/**
 * Shared email layout — closing chrome.
 *
 * Sprint 24C. Closes the body cell opened by layout-head.php and renders the
 * footer: support link, documentation link, site identity, plugin version and
 * the Powered by line. Overridable at
 * {theme}/havenlytics/emails/partials/layout-foot.php.
 *
 * @package Havenlytics
 * @since   3.2.1
 *
 * @var string $brand_support_url Support URL, or ''.
 * @var string $brand_docs_url    Documentation URL, or ''.
 * @var string $brand_version     Plugin version, or ''.
 * @var string $brand_site_name   Site name.
 * @var string $brand_site_url    Site URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/brand-colors.php';

$hvnly_support = isset( $brand_support_url ) ? (string) $brand_support_url : '';
$hvnly_docs    = isset( $brand_docs_url ) ? (string) $brand_docs_url : '';
$hvnly_version = isset( $brand_version ) ? (string) $brand_version : '';
$hvnly_site    = isset( $brand_site_name ) ? (string) $brand_site_name : get_bloginfo( 'name' );
$hvnly_home    = isset( $brand_site_url ) ? (string) $brand_site_url : home_url( '/' );

$hvnly_ink_muted = '#5b5870';
$hvnly_foot_bg   = '#faf9fc';
$hvnly_hairline  = '#e4e4ed';
?>
						</td>
					</tr>

					<?php // ---- Footer ---- ?>
					<tr>
						<td class="hvnly-pad hvnly-foot" align="center" bgcolor="<?php echo esc_attr( $hvnly_foot_bg ); ?>" style="padding:24px 32px;background:<?php echo esc_attr( $hvnly_foot_bg ); ?>;border-top:1px solid <?php echo esc_attr( $hvnly_hairline ); ?>;">

							<?php if ( $hvnly_support || $hvnly_docs ) : ?>
								<p class="hvnly-muted" style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5;color:<?php echo esc_attr( $hvnly_ink_muted ); ?>;">
									<?php if ( $hvnly_support ) : ?>
										<a href="<?php echo esc_url( $hvnly_support ); ?>" style="color:<?php echo esc_attr( $hvnly_brand_primary ); ?>;text-decoration:none;font-weight:bold;">
											<?php esc_html_e( 'Get support', 'havenlytics' ); ?>
										</a>
									<?php endif; ?>
									<?php if ( $hvnly_support && $hvnly_docs ) : ?>
										<span style="color:<?php echo esc_attr( $hvnly_hairline ); ?>;">&nbsp;&bull;&nbsp;</span>
									<?php endif; ?>
									<?php if ( $hvnly_docs ) : ?>
										<a href="<?php echo esc_url( $hvnly_docs ); ?>" style="color:<?php echo esc_attr( $hvnly_brand_primary ); ?>;text-decoration:none;font-weight:bold;">
											<?php esc_html_e( 'Documentation', 'havenlytics' ); ?>
										</a>
									<?php endif; ?>
								</p>
							<?php endif; ?>

							<p class="hvnly-muted" style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:<?php echo esc_attr( $hvnly_ink_muted ); ?>;">
								<?php esc_html_e( 'You are receiving this email because you have an account on', 'havenlytics' ); ?>
								<a href="<?php echo esc_url( $hvnly_home ); ?>" style="color:<?php echo esc_attr( $hvnly_ink_muted ); ?>;text-decoration:underline;"><?php echo esc_html( $hvnly_site ); ?></a>.
							</p>

							<p class="hvnly-muted" style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.5;color:<?php echo esc_attr( $hvnly_ink_muted ); ?>;">
								<?php esc_html_e( 'Powered by Havenlytics', 'havenlytics' ); ?><?php
								if ( $hvnly_version ) {
									echo ' &bull; ' . esc_html( sprintf( /* translators: %s: plugin version. */ __( 'v%s', 'havenlytics' ), $hvnly_version ) );
								}
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
