<?php
/**
 * Server render callback for the HVN: Authentication block.
 *
 * Frontend UI ONLY. Every authentication operation is performed by the existing
 * Workspace {@see \HvnlyNab\Workspace\Auth\SessionAuthController} AJAX actions —
 * this renderer produces markup and reuses existing helpers for URLs, roles and
 * avatars. It defines no new auth logic, endpoints, nonces or redirects.
 *
 * Backend reused:
 * - Auth transport ...... SessionAuthController (hvnly_ws_* AJAX actions + nonces)
 * - Registration policy .. WorkspaceSettings::get_registration_mode()/is_registration_enabled()
 * - Workspace URLs ....... WorkspaceSettings::route_url()/get_logout_redirect_url()
 * - Avatar ............... \HvnlyNab\Common\AvatarService::resolve_for_user()
 * - Roles ................ \HvnlyNab\Workspace\Auth\PortalCapabilities
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

use HvnlyNab\Common\AvatarService;
use HvnlyNab\Workspace\Auth\PortalCapabilities;
use HvnlyNab\Workspace\Auth\SessionAuthController;
use HvnlyNab\Workspace\WorkspaceSettings;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Authentication block renderer.
 *
 * @since 3.5.0
 */
final class AuthenticationBlockRenderer {

    /**
     * Render the block.
     *
     * @param array  $attributes Block attributes.
     * @param string $content    Inner content (unused).
     * @param object $block      Block instance (unused).
     * @return string
     */
    public static function render( $attributes = array(), string $content = '', $block = null ): string {
        unset($content, $block);

        if ( ! function_exists('hvnly_get_template_part')) {
            return '';
        }

        $attributes = is_array($attributes) ? $attributes : array();

        $mode = self::choice(
            (string) ( $attributes['authMode'] ?? 'auto' ),
            array( 'auto', 'login', 'register', 'tabs' ),
            'auto'
        );

        $layout = self::choice(
            (string) ( $attributes['layout'] ?? 'card' ),
            array( 'card', 'modern', 'classic', 'minimal' ),
            'card'
        );

        $align = self::choice(
            (string) ( $attributes['cardAlign'] ?? 'center' ),
            array( 'left', 'center', 'right' ),
            'center'
        );

        $default_tab = self::choice(
            (string) ( $attributes['defaultTab'] ?? 'login' ),
            array( 'login', 'register' ),
            'login'
        );

        $card_width = (int) ( $attributes['cardWidth'] ?? 460 );
        $card_width = max(320, min(720, $card_width));

        $is_logged_in      = is_user_logged_in();
        $workspace_enabled = class_exists(WorkspaceSettings::class) && WorkspaceSettings::is_enabled();
        $registration_open = class_exists(WorkspaceSettings::class) && WorkspaceSettings::is_registration_enabled();
        $registration_mode = class_exists(WorkspaceSettings::class) ? WorkspaceSettings::get_registration_mode() : 'disabled';

        // Resolved, validated redirect targets (JS only navigates to these).
        $dashboard_url = $workspace_enabled ? WorkspaceSettings::route_url('dashboard') : home_url('/');
        $account_url   = $workspace_enabled ? WorkspaceSettings::route_url('profile') : home_url('/');

        $after_login    = self::resolve_redirect(
            (string) ( $attributes['afterLogin'] ?? 'current' ),
            (string) ( $attributes['afterLoginUrl'] ?? '' ),
            $dashboard_url,
            $account_url
        );
        $after_register = self::resolve_redirect(
            (string) ( $attributes['afterRegister'] ?? 'current' ),
            (string) ( $attributes['afterRegisterUrl'] ?? '' ),
            $dashboard_url,
            $account_url
        );

        // Per-instance data attributes consumed by hvnly-block-auth.js.
        $config = array(
            'mode'             => $mode,
            'defaultTab'       => $default_tab,
            'afterLogin'       => $after_login,
            'afterRegister'    => $after_register,
            'registrationMode' => $registration_mode,
        );

        $wrapper_class = 'hvnly-auth hvnly-auth--layout-' . $layout . ' hvnly-auth--align-' . $align;
        if ($is_logged_in) {
            $wrapper_class .= ' hvnly-auth--authenticated';
        }

        $wrapper = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes(array( 'class' => $wrapper_class ))
            : 'class="' . esc_attr($wrapper_class) . '"';

        $template_args = array(
            'attributes'        => $attributes,
            'mode'              => $mode,
            'layout'            => $layout,
            'align'             => $align,
            'default_tab'       => $default_tab,
            'card_width'        => $card_width,
            'is_logged_in'      => $is_logged_in,
            'workspace_enabled' => $workspace_enabled,
            'registration_open' => $registration_open,
            'registration_mode' => $registration_mode,
            'config'            => $config,
            'dashboard_url'     => $dashboard_url,
            'account_url'       => $account_url,
            'wrapper'           => $wrapper,
        );

        if ($is_logged_in) {
            $template_args = array_merge($template_args, self::current_user_panel_data());
        }

        ob_start();
        hvnly_get_template_part('blocks/authentication', null, $template_args);

        return (string) ob_get_clean();
    }

    /**
     * Logged-in account panel data, built from reused helpers only.
     *
     * @return array<string, mixed>
     */
    private static function current_user_panel_data(): array {
        $user = wp_get_current_user();

        $avatar = '';
        if (class_exists(AvatarService::class)) {
            $avatar = AvatarService::resolve_for_user( (int) $user->ID, 96);
        }
        if ($avatar === '') {
            $avatar = get_avatar_url( (int) $user->ID, array( 'size' => 96 ));
        }

        $roles      = is_array($user->roles) ? $user->roles : array();
        $is_admin   = current_user_can('manage_options');
        $agent_role = class_exists(PortalCapabilities::class) ? PortalCapabilities::WP_ROLE_AGENT : 'hvnly_agent';
        $portal_cap = class_exists(PortalCapabilities::class) ? PortalCapabilities::PORTAL_ACCESS : 'hvnly_portal_access';

        $is_agent = in_array($agent_role, $roles, true)
            || ( user_can($user, $portal_cap) && ! user_can($user, 'edit_posts') );

        return array(
            'user'          => $user,
            'display_name'  => $user->display_name !== '' ? $user->display_name : $user->user_login,
            'avatar_url'    => $avatar,
            'role_label'    => self::role_label($roles, $is_admin),
            'is_admin'      => $is_admin,
            'is_agent'      => $is_agent,
            'logout_action' => class_exists(SessionAuthController::class) ? SessionAuthController::ACTION_LOGOUT : 'hvnly_ws_logout',
        );
    }

    /**
     * Human label for the current user's primary role (reuses WP role names).
     *
     * @param string[] $roles    User role slugs.
     * @param bool     $is_admin Whether the user can manage_options.
     * @return string
     */
    private static function role_label( array $roles, bool $is_admin ): string {
        if ($is_admin) {
            return __('Administrator', 'havenlytics');
        }

        $primary = isset($roles[0]) ? (string) $roles[0] : '';
        if ($primary === '') {
            return __('Member', 'havenlytics');
        }

        if (function_exists('wp_roles')) {
            $names = wp_roles()->get_names();
            if (isset($names[ $primary ])) {
                return translate_user_role( (string) $names[ $primary ]);
            }
        }

        return ucwords(str_replace(array( '_', '-' ), ' ', $primary));
    }

    /**
     * Resolve a block redirect setting to a concrete, validated URL.
     *
     * 'current' yields '' so the client reloads the current page in place.
     *
     * @param string $type      current|dashboard|account|custom.
     * @param string $custom    Custom URL (only used when $type === custom).
     * @param string $dashboard Resolved dashboard URL.
     * @param string $account   Resolved account URL.
     * @return string Absolute URL, or '' for "reload current page".
     */
    private static function resolve_redirect( string $type, string $custom, string $dashboard, string $account ): string {
        switch ($type) {
            case 'dashboard':
                return $dashboard;
            case 'account':
                return $account;
            case 'custom':
                $custom = esc_url_raw(trim($custom));
                return $custom !== '' ? wp_validate_redirect($custom, '') : '';
            case 'current':
            default:
                return '';
        }
    }

    /**
     * Return $value if it is in $allowed, else $default.
     *
     * @param string   $value   Candidate.
     * @param string[] $allowed Allowed values.
     * @param string   $default Fallback.
     * @return string
     */
    private static function choice( string $value, array $allowed, string $default ): string {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
