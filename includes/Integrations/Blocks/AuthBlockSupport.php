<?php
/**
 * Support wiring for the HVN: Authentication block.
 *
 * Two purely-additive concerns, neither of which modifies existing auth code:
 *
 * 1. Frontend config — builds the localized object the block controller reads
 *    (ajax URL + fresh nonces + action names + i18n). Nonces and action names
 *    come straight from the existing {@see SessionAuthController}.
 *
 * 2. Optional first/last name capture — the existing registration endpoint does
 *    not take name fields, so this listens on WordPress's own `user_register`
 *    extension point and stores the names ONLY for requests that carry this
 *    block's marker. It never alters the existing registration flow.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

use HvnlyNab\Workspace\Auth\SessionAuthController;
use HvnlyNab\Workspace\WorkspaceSettings;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @since 3.5.0
 */
final class AuthBlockSupport {

    /**
     * Register additive hooks (idempotent).
     *
     * @return void
     */
    public static function register(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        add_action('user_register', [self::class, 'persist_optional_names']);
    }

    /**
     * Store first/last name for registrations submitted by this block.
     *
     * Gated by the block marker so it can never affect the Workspace SPA
     * registration or WordPress core registration. The originating request has
     * already been CSRF-verified by SessionAuthController before this fires.
     *
     * @param int $user_id Newly created user ID.
     * @return void
     */
    public static function persist_optional_names($user_id): void {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- endpoint verified the nonce.
        if (!isset($_POST['hvnly_auth_block']) || '1' !== (string) wp_unslash($_POST['hvnly_auth_block'])) {
            return;
        }

        $first = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ('' !== $first) {
            update_user_meta($user_id, 'first_name', $first);
        }
        if ('' !== $last) {
            update_user_meta($user_id, 'last_name', $last);
        }
    }

    /**
     * Localized config for hvnly-block-auth.js.
     *
     * @return array<string, mixed>
     */
    public static function localize_data(): array {
        $nonces  = [];
        $actions = [];

        if (class_exists(SessionAuthController::class)) {
            $controller = new SessionAuthController();
            $nonces     = $controller->create_nonces();
            $actions    = [
                'login'         => SessionAuthController::ACTION_LOGIN,
                'register'      => SessionAuthController::ACTION_REGISTER,
                'lostPassword'  => SessionAuthController::ACTION_LOST_PASSWORD,
                'logout'        => SessionAuthController::ACTION_LOGOUT,
                'refreshNonces' => SessionAuthController::ACTION_REFRESH_NONCES,
            ];
        }

        $registration_enabled = class_exists(WorkspaceSettings::class) && WorkspaceSettings::is_registration_enabled();
        $registration_mode    = class_exists(WorkspaceSettings::class) ? WorkspaceSettings::get_registration_mode() : 'disabled';
        $logout_redirect      = class_exists(WorkspaceSettings::class) ? WorkspaceSettings::get_logout_redirect_url() : '';

        return [
            'ajaxUrl'             => admin_url('admin-ajax.php'),
            'nonces'              => $nonces,
            'actions'             => $actions,
            'registrationEnabled' => $registration_enabled,
            'registrationMode'    => $registration_mode,
            'logoutRedirect'      => $logout_redirect,
            'i18n'                => self::i18n(),
        ];
    }

    /**
     * Client-side strings (kept minimal — the server owns the authoritative
     * auth messages, these are UX labels and client validation copy).
     *
     * @return array<string, string>
     */
    private static function i18n(): array {
        return [
            'genericError'     => __('Something went wrong. Please try again.', 'havenlytics'),
            'networkError'     => __('Network error. Please check your connection and try again.', 'havenlytics'),
            'required'         => __('This field is required.', 'havenlytics'),
            'invalidEmail'     => __('Enter a valid email address.', 'havenlytics'),
            'passwordTooShort' => __('Use a password with at least 8 characters.', 'havenlytics'),
            'passwordMismatch' => __('Passwords do not match.', 'havenlytics'),
            'termsRequired'    => __('Please accept the terms and conditions.', 'havenlytics'),
            'loginTitle'       => __('Sign in', 'havenlytics'),
            'loginSubtitle'    => __('Access your account.', 'havenlytics'),
            'registerTitle'    => __('Create account', 'havenlytics'),
            'registerSubtitle' => __('Create your account to get started.', 'havenlytics'),
            'forgotTitle'      => __('Forgot password', 'havenlytics'),
            'forgotSubtitle'   => __('We will email you a link to reset your password.', 'havenlytics'),
            'loginSuccess'     => __('Signed in. Redirecting…', 'havenlytics'),
            'registerSuccess'  => __('Account created. Redirecting…', 'havenlytics'),
            'registerPending'  => __('Account created and awaiting administrator approval.', 'havenlytics'),
            'forgotSuccess'    => __('Check your email for the reset link.', 'havenlytics'),
            'showPassword'     => __('Show password', 'havenlytics'),
            'hidePassword'     => __('Hide password', 'havenlytics'),
            'strengthWeak'     => __('Weak', 'havenlytics'),
            'strengthFair'     => __('Fair', 'havenlytics'),
            'strengthGood'     => __('Good', 'havenlytics'),
            'strengthStrong'   => __('Strong', 'havenlytics'),
        ];
    }
}
