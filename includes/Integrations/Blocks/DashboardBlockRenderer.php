<?php
/**
 * Server render callback for the HVN: Dashboard block.
 *
 * PRESENTATION LAYER ONLY. Logged-in users are shown the EXISTING Agent Workspace
 * SPA, mounted through the existing `[hvnly_agent_dashboard]` shortcode — the same
 * mount, assets, boot data, availability guard and permissions the Workspace page
 * uses. This renderer adds NO dashboard logic, data, routing or permission checks.
 *
 * Reused:
 * - SPA mount + assets ... `[hvnly_agent_dashboard]` shortcode → WorkspaceAssets
 * - Availability / gate ... the shortcode's own WorkspaceAvailability handling
 * - Sign-in UI ............ {@see AuthenticationBlockRenderer} (existing block)
 * - Design tokens ......... the SPA's own `--hvnly-*` custom properties
 *
 * Inspector controls are applied as presentation only: style tokens cascade into
 * the SPA via `--hvnly-*` overrides, section visibility via scoped CSS against the
 * SPA's existing stable class names. Nothing about the SPA is modified.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

use HvnlyNab\Workspace\WorkspaceConstants;
use HvnlyNab\Workspace\WorkspaceSettings;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dashboard block renderer.
 *
 * @since 3.5.0
 */
final class DashboardBlockRenderer {

    /**
     * @param array  $attributes Block attributes.
     * @param string $content    Inner content (unused).
     * @param object $block      Block instance (unused).
     * @return string
     */
    public static function render($attributes = [], string $content = '', $block = null): string {
        unset($content, $block);

        if (!function_exists('hvnly_get_template_part')) {
            return '';
        }

        $attributes = is_array($attributes) ? $attributes : [];

        // Same render path on frontend AND in the editor (ServerSideRender/REST):
        // logged-in → the real SPA mount (the editor boots it via the reusable
        // HvnlyWorkspace.mount API); logged-out → the sign-in gate. There is no
        // separate fake editor panel — one renderer, one output.
        $is_logged_in = is_user_logged_in();
        $context      = $is_logged_in ? 'live' : 'gate';

        $wrapper_id = wp_unique_id('hvnly-dash-');

        $layout          = self::choice((string) ($attributes['dashboardLayout'] ?? 'default'), ['default', 'contained', 'full'], 'default');
        $container_style = self::choice((string) ($attributes['containerStyle'] ?? 'default'), ['default', 'flat', 'bordered'], 'default');
        $container_width = max(0, (int) ($attributes['containerWidth'] ?? 0));
        $min_height      = max(0, (int) ($attributes['minHeight'] ?? 600));

        $scoped_css = self::build_scoped_css($wrapper_id, $attributes, $container_style, $container_width, $min_height);

        // Live SPA mount — delegate wholesale to the existing shortcode (handles
        // the disabled → unavailable page too). No markup is reimplemented.
        $mount_html = '';
        if ('live' === $context && shortcode_exists(WorkspaceConstants::SHORTCODE)) {
            $mount_html = do_shortcode('[' . WorkspaceConstants::SHORTCODE . ']');
        }

        // Sign-in gate — reuse the existing Authentication block for real login.
        $gate = ('gate' === $context) ? self::build_gate($attributes) : [];

        $wrapper_class = 'hvnly-dashboard-block hvnly-dashboard-block--layout-' . $layout
            . ' hvnly-dashboard-block--style-' . $container_style
            . ' hvnly-dashboard-block--context-' . $context;

        $wrapper = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes(['class' => $wrapper_class])
            : 'class="' . esc_attr($wrapper_class) . '"';

        $template_args = [
            'attributes'   => $attributes,
            'context'      => $context,
            'wrapper'      => $wrapper,
            'wrapper_id'   => $wrapper_id,
            'scoped_css'   => $scoped_css,
            'mount_html'   => $mount_html,
            'gate'         => $gate,
            'min_height'   => $min_height,
        ];

        ob_start();
        hvnly_get_template_part('blocks/dashboard', null, $template_args);

        return (string) ob_get_clean();
    }

    /**
     * Build the sign-in gate (reuses the Authentication block, or a button).
     *
     * @param array<string, mixed> $attributes Block attributes.
     * @return array<string, mixed>
     */
    private static function build_gate(array $attributes): array {
        $mode = self::choice((string) ($attributes['loggedOutMode'] ?? 'form'), ['form', 'button'], 'form');

        $heading = (string) ($attributes['loggedOutHeading'] ?? '');
        if ('' === $heading) {
            $heading = __('Sign in to your dashboard', 'havenlytics');
        }
        $message = (string) ($attributes['loggedOutMessage'] ?? '');
        if ('' === $message) {
            $message = __('You must sign in to access your dashboard.', 'havenlytics');
        }

        $auth_html = '';
        $button_url = '';
        $button_label = (string) ($attributes['authButtonLabel'] ?? '');
        if ('' === $button_label) {
            $button_label = __('Sign in', 'havenlytics');
        }

        if ('form' === $mode && class_exists(AuthenticationBlockRenderer::class)) {
            // Reuse the Authentication block; after login, reload so the dashboard
            // renders in place (redirect-after-login support).
            $auth_html = AuthenticationBlockRenderer::render([
                'authMode'         => 'login',
                'afterLogin'       => 'current',
                'showRegisterLink' => true,
                'layout'           => 'card',
                'cardAlign'        => 'center',
            ]);
        } else {
            $mode = 'button';
            $custom = (string) ($attributes['authUrl'] ?? '');
            if ('' !== trim($custom)) {
                $button_url = esc_url_raw(trim($custom));
            } elseif (class_exists(WorkspaceSettings::class)) {
                $button_url = WorkspaceSettings::route_url('login');
            } else {
                $button_url = wp_login_url();
            }
        }

        return [
            'mode'         => $mode,
            'heading'      => $heading,
            'message'      => $message,
            'auth_html'    => $auth_html,
            'button_url'   => $button_url,
            'button_label' => $button_label,
        ];
    }

    /**
     * Build a scoped <style> block: token overrides + section visibility.
     *
     * All overrides are scoped to this block instance (its generated id) and, for
     * dashboard-home sections, to the SPA's own `[data-hvnly-ws-view="dashboard"]`
     * so they never affect other Workspace views or other blocks on the page.
     *
     * @param string               $id     Wrapper id.
     * @param array<string, mixed> $a      Attributes.
     * @param string               $style  Container style variant.
     * @param int                  $width  Container width (px, 0 = inherit).
     * @param int                  $min_h  Min height (px).
     * @return string
     */
    private static function build_scoped_css(string $id, array $a, string $style, int $width, int $min_h): string {
        $sel  = '#' . $id;
        $vars = [];

        $radius = (int) ($a['cardRadius'] ?? 0);
        if ($radius > 0) {
            $vars['--hvnly-border-radius-lg'] = $radius . 'px';
        }

        $spacing = (int) ($a['spacing'] ?? 0);
        if ($spacing > 0) {
            $vars['--hvnly-space-lg'] = $spacing . 'px';
        }

        $shadow = self::choice((string) ($a['shadow'] ?? 'default'), ['default', 'none', 'sm', 'md', 'lg'], 'default');
        $shadow_map = [
            'none' => 'none',
            'sm'   => '0 1px 2px rgba(16,24,40,0.06)',
            'md'   => '0 4px 12px rgba(16,24,40,0.10)',
            'lg'   => '0 16px 40px rgba(16,24,40,0.16)',
        ];
        if ('bordered' === $style) {
            $vars['--hvnly-card-shadow'] = 'none';
        } elseif (isset($shadow_map[$shadow])) {
            $vars['--hvnly-card-shadow']  = $shadow_map[$shadow];
            $vars['--hvnly-shadow-card']  = $shadow_map[$shadow];
        }

        $css = '';

        // Token overrides target the SPA roots directly so they beat the SPA's own
        // `.hvnly-ws` re-declarations (higher specificity via the instance id).
        if (!empty($vars)) {
            $decls = '';
            foreach ($vars as $prop => $value) {
                $decls .= $prop . ':' . $value . ';';
            }
            $css .= $sel . ' .hvnly-ws,' . $sel . ' .hvnly-ws-mount{' . $decls . '}';
        }

        if ($width > 0) {
            $css .= $sel . '{max-width:' . $width . 'px;margin-left:auto;margin-right:auto;}';
        }
        if ($min_h > 0) {
            $css .= $sel . ' .hvnly-ws-mount{min-height:' . $min_h . 'px;}';
        }

        // Section visibility (dashboard-home scope). Maps each toggle to the SPA's
        // existing stable class name; hides via display:none (presentation only).
        $dash = $sel . ' [data-hvnly-ws-view="dashboard"] ';
        $hide = [];
        if (empty($a['showWelcome']) && isset($a['showWelcome'])) {
            $hide[] = $dash . '.hvnly-agent-dashboard__welcome';
        }
        if (empty($a['showStatistics']) && isset($a['showStatistics'])) {
            $hide[] = $dash . '.hvnly-agent-statistics__card';
        }
        if (empty($a['showActivity']) && isset($a['showActivity'])) {
            $hide[] = $dash . '.hvnly-agent-activity';
        }
        if (empty($a['showRecentProperties']) && isset($a['showRecentProperties'])) {
            $hide[] = $dash . '.hvnly-agent-properties';
        }
        if (empty($a['showQuickActions']) && isset($a['showQuickActions'])) {
            $hide[] = $dash . '.hvnly-agent-dashboard__quick-actions';
        }
        // Forward-wired hooks (no dedicated dashboard-home section today).
        if (empty($a['showFavorites']) && isset($a['showFavorites'])) {
            $hide[] = $dash . '.hvnly-agent-dashboard__favorites';
        }
        if (empty($a['showProfileSummary']) && isset($a['showProfileSummary'])) {
            $hide[] = $dash . '.hvnly-agent-dashboard__profile-summary';
        }
        // Shell chrome (all views).
        if (empty($a['showHeader']) && isset($a['showHeader'])) {
            $hide[] = $sel . ' .hvnly-ws__header';
        }
        if (empty($a['showSidebar']) && isset($a['showSidebar'])) {
            $hide[] = $sel . ' .hvnly-ws__sidebar';
            $hide[] = $sel . ' .hvnly-ws__sidebar-overlay';
        }

        if (!empty($hide)) {
            // Instance-id + view scoping already outranks the SPA's own class
            // rules, so no !important is needed to hide a section.
            $css .= implode(',', $hide) . '{display:none;}';
        }

        return $css;
    }

    /**
     * Return $value if allowed, else $default.
     *
     * @param string   $value   Candidate.
     * @param string[] $allowed Allowed values.
     * @param string   $default Fallback.
     * @return string
     */
    private static function choice(string $value, array $allowed, string $default): string {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
