<?php
/**
 * HVN: Dashboard block markup.
 *
 * Presentation shell for the existing Agent Workspace SPA. The live SPA markup in
 * $hvnly_mount_html comes from the existing [hvnly_agent_dashboard] shortcode — this
 * template only wraps it (for scoped style tokens + section visibility) or shows
 * the sign-in gate / editor preview.
 *
 * @package     Havenlytics
 * @subpackage  Templates/blocks
 * @since       3.5.0
 *
 * @var array $args Provided by DashboardBlockRenderer::render().
 */

if (!defined('ABSPATH')) {
    exit;
}

$hvnly_a = (isset($args) && is_array($args)) ? $args : array();

$hvnly_context    = isset($hvnly_a['context']) ? (string) $hvnly_a['context'] : 'gate';
$hvnly_wrapper    = isset($hvnly_a['wrapper']) ? $hvnly_a['wrapper'] : 'class="hvnly-dashboard-block"';
$hvnly_wrapper_id = isset($hvnly_a['wrapper_id']) ? (string) $hvnly_a['wrapper_id'] : '';
$hvnly_scoped_css = isset($hvnly_a['scoped_css']) ? (string) $hvnly_a['scoped_css'] : '';
$hvnly_mount_html = isset($hvnly_a['mount_html']) ? (string) $hvnly_a['mount_html'] : '';
$hvnly_gate       = isset($hvnly_a['gate']) && is_array($hvnly_a['gate']) ? $hvnly_a['gate'] : array();
$hvnly_min_height = isset($hvnly_a['min_height']) ? (int) $hvnly_a['min_height'] : 600;
?>
<div <?php echo $hvnly_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
    <?php if ($hvnly_scoped_css !== '') : ?>
        <style><?php echo wp_strip_all_tags($hvnly_scoped_css); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
    <?php endif; ?>

    <div id="<?php echo esc_attr($hvnly_wrapper_id); ?>" class="hvnly-dashboard-block__inner">
        <?php if ('editor' === $hvnly_context) : ?>

            <div class="hvnly-dashboard-block__editor" role="note">
                <span class="hvnly-dashboard-block__editor-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                </span>
                <p class="hvnly-dashboard-block__editor-title"><?php echo esc_html__('HVN: Dashboard', 'havenlytics'); ?></p>
                <p class="hvnly-dashboard-block__editor-text">
                    <?php echo esc_html__('The live Agent Workspace dashboard renders here on the frontend for signed-in users. Signed-out visitors see a sign-in panel. Use the block settings to adjust layout, sections and style.', 'havenlytics'); ?>
                </p>
            </div>

        <?php elseif ('gate' === $hvnly_context) : ?>

            <div class="hvnly-dashboard-block__gate">
                <div class="hvnly-dashboard-block__gate-head">
                    <span class="hvnly-dashboard-block__gate-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    </span>
                    <h2 class="hvnly-dashboard-block__gate-title"><?php echo esc_html((string) ($hvnly_gate['heading'] ?? '')); ?></h2>
                    <p class="hvnly-dashboard-block__gate-text"><?php echo esc_html((string) ($hvnly_gate['message'] ?? '')); ?></p>
                </div>

                <?php if ('form' === ($hvnly_gate['mode'] ?? 'button') && !empty($hvnly_gate['auth_html'])) : ?>
                    <div class="hvnly-dashboard-block__gate-auth">
                        <?php echo $hvnly_gate['auth_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by AuthenticationBlockRenderer (already escaped). ?>
                    </div>
                <?php elseif (!empty($hvnly_gate['button_url'])) : ?>
                    <p class="hvnly-dashboard-block__gate-actions">
                        <a class="hvnly-dashboard-block__gate-btn" href="<?php echo esc_url((string) $hvnly_gate['button_url']); ?>">
                            <?php echo esc_html((string) ($hvnly_gate['button_label'] ?? __('Sign in', 'havenlytics'))); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>

        <?php else : ?>

            <?php
            /*
             * Live: existing Workspace SPA mount from the [hvnly_agent_dashboard]
             * shortcode. Not escaped — it is trusted plugin markup (the same output
             * the Workspace page renders), and escaping it would break the mount.
             */
            if ($hvnly_mount_html !== '') {
                echo $hvnly_mount_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                printf(
                    '<div class="hvnly-dashboard-block__notice">%s</div>',
                    esc_html__('The dashboard is unavailable right now.', 'havenlytics')
                );
            }
            ?>

        <?php endif; ?>
    </div>
</div>
