<?php
/**
 * HVN: Authentication block markup.
 *
 * Frontend UI only. Forms post to the existing Workspace SessionAuthController
 * AJAX actions (hvnly_ws_login / hvnly_ws_register / hvnly_ws_lostpassword /
 * hvnly_ws_logout) via hvnly-block-auth.js. No auth logic lives here.
 *
 * @package     Havenlytics
 * @subpackage  Templates/blocks
 * @since       3.5.0
 *
 * @var array  $args Provided by AuthenticationBlockRenderer::render().
 */

if (!defined('ABSPATH')) {
    exit;
}

$hvnly_a = (isset($args) && is_array($args)) ? $args : array();

$mode              = isset($hvnly_a['mode']) ? (string) $hvnly_a['mode'] : 'auto';
$hvnly_layout            = isset($hvnly_a['layout']) ? (string) $hvnly_a['layout'] : 'card';
$hvnly_default_tab       = isset($hvnly_a['default_tab']) ? (string) $hvnly_a['default_tab'] : 'login';
$hvnly_card_width        = isset($hvnly_a['card_width']) ? (int) $hvnly_a['card_width'] : 460;
$hvnly_is_logged_in      = !empty($hvnly_a['is_logged_in']);
$hvnly_workspace_enabled = !empty($hvnly_a['workspace_enabled']);
$hvnly_registration_open = !empty($hvnly_a['registration_open']);
$hvnly_registration_mode = isset($hvnly_a['registration_mode']) ? (string) $hvnly_a['registration_mode'] : 'disabled';
$hvnly_config            = isset($hvnly_a['config']) && is_array($hvnly_a['config']) ? $hvnly_a['config'] : array();
$hvnly_wrapper           = isset($hvnly_a['wrapper']) ? $hvnly_a['wrapper'] : 'class="hvnly-auth"';
$hvnly_attrs             = isset($hvnly_a['attributes']) && is_array($hvnly_a['attributes']) ? $hvnly_a['attributes'] : array();

$hvnly_show_logo         = !isset($hvnly_attrs['showLogo']) || !empty($hvnly_attrs['showLogo']);
$hvnly_show_heading      = !isset($hvnly_attrs['showHeading']) || !empty($hvnly_attrs['showHeading']);
$hvnly_show_description  = !isset($hvnly_attrs['showDescription']) || !empty($hvnly_attrs['showDescription']);
$hvnly_show_remember     = !isset($hvnly_attrs['showRemember']) || !empty($hvnly_attrs['showRemember']);
$hvnly_show_forgot       = !isset($hvnly_attrs['showForgot']) || !empty($hvnly_attrs['showForgot']);
$hvnly_show_register_lnk = !isset($hvnly_attrs['showRegisterLink']) || !empty($hvnly_attrs['showRegisterLink']);
$hvnly_show_names        = !isset($hvnly_attrs['showNames']) || !empty($hvnly_attrs['showNames']);
$hvnly_show_terms        = !empty($hvnly_attrs['showTerms']);
$hvnly_terms_url         = isset($hvnly_attrs['termsUrl']) ? esc_url((string) $hvnly_attrs['termsUrl']) : '';
$hvnly_show_welcome      = !isset($hvnly_attrs['showWelcomePanel']) || !empty($hvnly_attrs['showWelcomePanel']);
$hvnly_show_avatar       = !isset($hvnly_attrs['showAvatar']) || !empty($hvnly_attrs['showAvatar']);
$hvnly_show_quick_links  = !isset($hvnly_attrs['showQuickLinks']) || !empty($hvnly_attrs['showQuickLinks']);

// Which forms this mode may present (when logged out).
$hvnly_can_login    = in_array($mode, array('auto', 'login', 'tabs'), true);
$hvnly_can_register = in_array($mode, array('auto', 'register', 'tabs'), true) && $hvnly_registration_open;
$hvnly_use_tabs     = ('tabs' === $mode) && $hvnly_can_login && $hvnly_can_register;

// Initial visible view (progressive enhancement — correct without JS).
if ('register' === $mode && $hvnly_can_register) {
    $hvnly_initial_view = 'register';
} elseif ($hvnly_use_tabs && 'register' === $hvnly_default_tab) {
    $hvnly_initial_view = 'register';
} elseif ($hvnly_can_login) {
    $hvnly_initial_view = 'login';
} elseif ($hvnly_can_register) {
    $hvnly_initial_view = 'register';
} else {
    $hvnly_initial_view = 'login';
}

// Server-rendered heading copy per view (JS swaps these on navigation).
$hvnly_view_copy = array(
    'login'    => array(
        'title'    => __('Sign in', 'havenlytics'),
        'subtitle' => __('Access your account.', 'havenlytics'),
    ),
    'register' => array(
        'title'    => __('Create account', 'havenlytics'),
        'subtitle' => __('Create your account to get started.', 'havenlytics'),
    ),
    'forgot'   => array(
        'title'    => __('Forgot password', 'havenlytics'),
        'subtitle' => __('We will email you a link to reset your password.', 'havenlytics'),
    ),
);
$hvnly_initial_copy = isset($hvnly_view_copy[$hvnly_initial_view]) ? $hvnly_view_copy[$hvnly_initial_view] : $hvnly_view_copy['login'];

// Logo markup (custom site logo → real-estate glyph fallback).
$hvnly_logo_html = '';
if ($hvnly_show_logo) {
    $hvnly_logo_id = (int) get_theme_mod('custom_logo');
    if ($hvnly_logo_id > 0) {
        $hvnly_logo_html = wp_get_attachment_image($hvnly_logo_id, 'medium', false, array('class' => 'hvnly-auth__logo-img', 'alt' => ''));
    }
    if ($hvnly_logo_html === '') {
        $hvnly_logo_html = '<span class="hvnly-auth__logo-glyph" aria-hidden="true">'
            . '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9h14v-9"/><path d="M9.5 19v-5h5v5"/></svg>'
            . '</span>';
    }
}

/**
 * Render one labelled input row.
 *
 * @param array $field Field spec.
 * @return void
 */
$hvnly_field = function (array $field) {
    $id          = $field['id'];
    $name        = $field['name'];
    $type        = isset($field['type']) ? $field['type'] : 'text';
    $label       = $field['label'];
    $autocomplete = isset($field['autocomplete']) ? $field['autocomplete'] : '';
    $required    = !empty($field['required']);
    $has_toggle  = !empty($field['toggle']);
    ?>
    <div class="hvnly-auth__field">
        <label class="hvnly-auth__label" for="<?php echo esc_attr($id); ?>">
            <?php echo esc_html($label); ?>
            <?php if ($required) : ?><span class="hvnly-auth__req" aria-hidden="true">*</span><?php endif; ?>
        </label>
        <div class="hvnly-auth__control">
            <input
                class="hvnly-auth__input"
                type="<?php echo esc_attr($type); ?>"
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($name); ?>"
                <?php echo $autocomplete !== '' ? 'autocomplete="' . esc_attr($autocomplete) . '"' : ''; ?>
                <?php echo $required ? 'required' : ''; ?>
                <?php echo !empty($field['minlength']) ? 'minlength="' . esc_attr((string) $field['minlength']) . '"' : ''; ?>
            />
            <?php if ($has_toggle) : ?>
                <button type="button" class="hvnly-ui-control hvnly-auth__toggle" data-hvnly-auth-toggle aria-label="<?php esc_attr_e('Show password', 'havenlytics'); ?>" aria-pressed="false">
                    <span class="hvnly-auth__toggle-icon" aria-hidden="true"></span>
                </button>
            <?php endif; ?>
        </div>
        <?php if (!empty($field['strength'])) : ?>
            <div class="hvnly-auth__strength" data-hvnly-auth-strength hidden>
                <div class="hvnly-auth__strength-bar"><span data-hvnly-auth-strength-fill></span></div>
                <span class="hvnly-auth__strength-label" data-hvnly-auth-strength-label></span>
            </div>
        <?php endif; ?>
        <p class="hvnly-auth__field-error" data-hvnly-auth-field-error hidden></p>
    </div>
    <?php
};
?>
<div <?php echo $hvnly_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
    <div
        class="hvnly-auth__inner"
        style="--hvnly-auth-card-width: <?php echo esc_attr((string) $hvnly_card_width); ?>px;"
        data-hvnly-auth
        data-mode="<?php echo esc_attr($mode); ?>"
        data-default-tab="<?php echo esc_attr($hvnly_default_tab); ?>"
        data-after-login="<?php echo esc_attr((string) ($hvnly_config['afterLogin'] ?? '')); ?>"
        data-after-register="<?php echo esc_attr((string) ($hvnly_config['afterRegister'] ?? '')); ?>"
        data-registration-mode="<?php echo esc_attr($hvnly_registration_mode); ?>"
        data-initial-view="<?php echo esc_attr($hvnly_initial_view); ?>"
    >
        <?php if ($hvnly_is_logged_in) : ?>

            <?php
            $hvnly_display_name = isset($hvnly_a['display_name']) ? (string) $hvnly_a['display_name'] : '';
            $hvnly_avatar_url   = isset($hvnly_a['avatar_url']) ? (string) $hvnly_a['avatar_url'] : '';
            $hvnly_role_label   = isset($hvnly_a['role_label']) ? (string) $hvnly_a['role_label'] : '';
            $hvnly_dashboard_url = isset($hvnly_a['dashboard_url']) ? (string) $hvnly_a['dashboard_url'] : '';
            $hvnly_account_url   = isset($hvnly_a['account_url']) ? (string) $hvnly_a['account_url'] : '';
            $hvnly_properties_url = $hvnly_workspace_enabled && class_exists('\HvnlyNab\Workspace\WorkspaceSettings')
                ? \HvnlyNab\Workspace\WorkspaceSettings::route_url('properties') : '';
            $hvnly_favorites_url = $hvnly_workspace_enabled && class_exists('\HvnlyNab\Workspace\WorkspaceSettings')
                ? \HvnlyNab\Workspace\WorkspaceSettings::route_url('saved-properties') : '';
            $hvnly_logout_action = isset($hvnly_a['logout_action']) ? (string) $hvnly_a['logout_action'] : 'hvnly_ws_logout';
            ?>

            <div class="hvnly-auth__card hvnly-auth__card--account" data-hvnly-auth-view="account">
                <?php if ($hvnly_show_welcome) : ?>
                    <div class="hvnly-auth__account-head">
                        <?php if ($hvnly_show_avatar && $hvnly_avatar_url !== '') : ?>
                            <img class="hvnly-auth__avatar" src="<?php echo esc_url($hvnly_avatar_url); ?>" alt="" width="64" height="64" loading="lazy" />
                        <?php endif; ?>
                        <div class="hvnly-auth__account-meta">
                            <p class="hvnly-auth__welcome"><?php echo esc_html__('Welcome back,', 'havenlytics'); ?></p>
                            <p class="hvnly-auth__account-name"><?php echo esc_html($hvnly_display_name); ?></p>
                            <?php if ($hvnly_role_label !== '') : ?>
                                <span class="hvnly-auth__role-badge"><?php echo esc_html($hvnly_role_label); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($hvnly_show_quick_links && $hvnly_workspace_enabled) : ?>
                    <nav class="hvnly-auth__links" aria-label="<?php esc_attr_e('Account links', 'havenlytics'); ?>">
                        <?php if ($hvnly_dashboard_url !== '') : ?>
                            <a class="hvnly-auth__link" href="<?php echo esc_url($hvnly_dashboard_url); ?>"><?php echo esc_html__('Dashboard', 'havenlytics'); ?></a>
                        <?php endif; ?>
                        <?php if ($hvnly_properties_url !== '') : ?>
                            <a class="hvnly-auth__link" href="<?php echo esc_url($hvnly_properties_url); ?>"><?php echo esc_html__('My Properties', 'havenlytics'); ?></a>
                        <?php endif; ?>
                        <?php if ($hvnly_favorites_url !== '') : ?>
                            <a class="hvnly-auth__link" href="<?php echo esc_url($hvnly_favorites_url); ?>"><?php echo esc_html__('Favorites', 'havenlytics'); ?></a>
                        <?php endif; ?>
                        <?php if ($hvnly_account_url !== '') : ?>
                            <a class="hvnly-auth__link" href="<?php echo esc_url($hvnly_account_url); ?>"><?php echo esc_html__('Profile', 'havenlytics'); ?></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>

                <div class="hvnly-auth__account-actions">
                    <button type="button" class="hvnly-btn hvnly-btn--ghost hvnly-auth__btn hvnly-auth__btn--ghost" data-hvnly-auth-logout data-action="<?php echo esc_attr($hvnly_logout_action); ?>">
                        <span class="hvnly-auth__btn-label"><?php echo esc_html__('Sign out', 'havenlytics'); ?></span>
                        <span class="hvnly-auth__spinner" aria-hidden="true"></span>
                    </button>
                </div>

                <p class="hvnly-auth__message" data-hvnly-auth-message role="status" aria-live="polite" hidden></p>
            </div>

        <?php else : ?>

            <div class="hvnly-auth__card" data-hvnly-auth-view="forms">
                <?php if ($hvnly_logo_html !== '' || $hvnly_show_heading) : ?>
                    <div class="hvnly-auth__header">
                        <?php if ($hvnly_logo_html !== '') : ?>
                            <div class="hvnly-auth__logo"><?php echo $hvnly_logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        <?php endif; ?>
                        <?php if ($hvnly_show_heading) : ?>
                            <h2 class="hvnly-auth__title" data-hvnly-auth-title><?php echo esc_html($hvnly_initial_copy['title']); ?></h2>
                        <?php endif; ?>
                        <?php if ($hvnly_show_description) : ?>
                            <p class="hvnly-auth__subtitle" data-hvnly-auth-subtitle><?php echo esc_html($hvnly_initial_copy['subtitle']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($hvnly_use_tabs) : ?>
                    <div class="hvnly-auth__tabs" role="tablist" aria-label="<?php esc_attr_e('Authentication', 'havenlytics'); ?>">
                        <button type="button" class="hvnly-ui-control hvnly-auth__tab" role="tab" data-hvnly-auth-tab="login" aria-selected="false"><?php echo esc_html__('Sign in', 'havenlytics'); ?></button>
                        <button type="button" class="hvnly-ui-control hvnly-auth__tab" role="tab" data-hvnly-auth-tab="register" aria-selected="false"><?php echo esc_html__('Create account', 'havenlytics'); ?></button>
                    </div>
                <?php endif; ?>

                <p class="hvnly-auth__message" data-hvnly-auth-message role="status" aria-live="polite" hidden></p>

                <?php if ($hvnly_can_login) : ?>
                    <form class="hvnly-auth__form" data-hvnly-auth-form="login" novalidate <?php echo 'login' === $hvnly_initial_view ? '' : 'hidden'; ?>>
                        <?php
                        $hvnly_field(array(
                            'id'           => 'hvnly-auth-login-user',
                            'name'         => 'log',
                            'type'         => 'text',
                            'label'        => __('Email or username', 'havenlytics'),
                            'autocomplete' => 'username',
                            'required'     => true,
                        ));
                        $hvnly_field(array(
                            'id'           => 'hvnly-auth-login-pass',
                            'name'         => 'pwd',
                            'type'         => 'password',
                            'label'        => __('Password', 'havenlytics'),
                            'autocomplete' => 'current-password',
                            'required'     => true,
                            'toggle'       => true,
                        ));
                        ?>
                        <div class="hvnly-auth__row">
                            <?php if ($hvnly_show_remember) : ?>
                                <label class="hvnly-auth__checkbox">
                                    <input type="checkbox" name="rememberme" value="1" checked />
                                    <span><?php echo esc_html__('Remember me', 'havenlytics'); ?></span>
                                </label>
                            <?php endif; ?>
                            <?php if ($hvnly_show_forgot) : ?>
                                <button type="button" class="hvnly-ui-control hvnly-auth__linkbtn" data-hvnly-auth-goto="forgot"><?php echo esc_html__('Forgot password?', 'havenlytics'); ?></button>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="hvnly-btn hvnly-btn--primary hvnly-auth__btn hvnly-auth__btn--primary">
                            <span class="hvnly-auth__btn-label"><?php echo esc_html__('Sign in', 'havenlytics'); ?></span>
                            <span class="hvnly-auth__spinner" aria-hidden="true"></span>
                        </button>
                        <?php if ($hvnly_show_register_lnk && $hvnly_can_register && !$hvnly_use_tabs) : ?>
                            <p class="hvnly-auth__alt">
                                <?php echo esc_html__("Don't have an account?", 'havenlytics'); ?>
                                <button type="button" class="hvnly-ui-control hvnly-auth__linkbtn" data-hvnly-auth-goto="register"><?php echo esc_html__('Create one', 'havenlytics'); ?></button>
                            </p>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <?php if ($hvnly_can_register) : ?>
                    <form class="hvnly-auth__form" data-hvnly-auth-form="register" novalidate <?php echo 'register' === $hvnly_initial_view ? '' : 'hidden'; ?>>
                        <?php if ($hvnly_show_names) : ?>
                            <div class="hvnly-auth__grid-2">
                                <?php
                                $hvnly_field(array(
                                    'id'           => 'hvnly-auth-reg-first',
                                    'name'         => 'first_name',
                                    'type'         => 'text',
                                    'label'        => __('First name', 'havenlytics'),
                                    'autocomplete' => 'given-name',
                                ));
                                $hvnly_field(array(
                                    'id'           => 'hvnly-auth-reg-last',
                                    'name'         => 'last_name',
                                    'type'         => 'text',
                                    'label'        => __('Last name', 'havenlytics'),
                                    'autocomplete' => 'family-name',
                                ));
                                ?>
                            </div>
                        <?php endif; ?>
                        <?php
                        $hvnly_field(array(
                            'id'           => 'hvnly-auth-reg-user',
                            'name'         => 'user_login',
                            'type'         => 'text',
                            'label'        => __('Username', 'havenlytics'),
                            'autocomplete' => 'username',
                            'required'     => true,
                        ));
                        $hvnly_field(array(
                            'id'           => 'hvnly-auth-reg-email',
                            'name'         => 'user_email',
                            'type'         => 'email',
                            'label'        => __('Email', 'havenlytics'),
                            'autocomplete' => 'email',
                            'required'     => true,
                        ));
                        $hvnly_field(array(
                            'id'           => 'hvnly-auth-reg-pass',
                            'name'         => 'pass1',
                            'type'         => 'password',
                            'label'        => __('Password', 'havenlytics'),
                            'autocomplete' => 'new-password',
                            'required'     => true,
                            'toggle'       => true,
                            'minlength'    => 8,
                            'strength'     => true,
                        ));
                        $hvnly_field(array(
                            'id'           => 'hvnly-auth-reg-pass2',
                            'name'         => 'pass2',
                            'type'         => 'password',
                            'label'        => __('Confirm password', 'havenlytics'),
                            'autocomplete' => 'new-password',
                            'required'     => true,
                            'toggle'       => true,
                            'minlength'    => 8,
                        ));
                        ?>
                        <?php if ($hvnly_show_terms) : ?>
                            <label class="hvnly-auth__checkbox hvnly-auth__checkbox--terms">
                                <input type="checkbox" name="hvnly_auth_terms" value="1" required />
                                <span>
                                    <?php
                                    if ($hvnly_terms_url !== '') {
                                        printf(
                                            /* translators: %s: link to terms page. */
                                            esc_html__('I agree to the %s.', 'havenlytics'),
                                            '<a href="' . esc_url($hvnly_terms_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('terms and conditions', 'havenlytics') . '</a>'
                                        );
                                    } else {
                                        echo esc_html__('I agree to the terms and conditions.', 'havenlytics');
                                    }
                                    ?>
                                </span>
                            </label>
                        <?php endif; ?>
                        <?php if ('approval' === $hvnly_registration_mode) : ?>
                            <p class="hvnly-auth__hint"><?php echo esc_html__('New accounts require administrator approval before Workspace access.', 'havenlytics'); ?></p>
                        <?php endif; ?>
                        <button type="submit" class="hvnly-btn hvnly-btn--primary hvnly-auth__btn hvnly-auth__btn--primary">
                            <span class="hvnly-auth__btn-label"><?php echo esc_html__('Create account', 'havenlytics'); ?></span>
                            <span class="hvnly-auth__spinner" aria-hidden="true"></span>
                        </button>
                        <?php if (!$hvnly_use_tabs) : ?>
                            <p class="hvnly-auth__alt">
                                <?php echo esc_html__('Already have an account?', 'havenlytics'); ?>
                                <button type="button" class="hvnly-ui-control hvnly-auth__linkbtn" data-hvnly-auth-goto="login"><?php echo esc_html__('Sign in', 'havenlytics'); ?></button>
                            </p>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <?php if ($hvnly_can_login && $hvnly_show_forgot) : ?>
                    <form class="hvnly-auth__form" data-hvnly-auth-form="forgot" novalidate hidden>
                        <?php
                        $hvnly_field(array(
                            'id'           => 'hvnly-auth-forgot-user',
                            'name'         => 'user_login',
                            'type'         => 'text',
                            'label'        => __('Email or username', 'havenlytics'),
                            'autocomplete' => 'username',
                            'required'     => true,
                        ));
                        ?>
                        <button type="submit" class="hvnly-btn hvnly-btn--primary hvnly-auth__btn hvnly-auth__btn--primary">
                            <span class="hvnly-auth__btn-label"><?php echo esc_html__('Send reset link', 'havenlytics'); ?></span>
                            <span class="hvnly-auth__spinner" aria-hidden="true"></span>
                        </button>
                        <p class="hvnly-auth__alt">
                            <button type="button" class="hvnly-ui-control hvnly-auth__linkbtn" data-hvnly-auth-goto="login"><?php echo esc_html__('Back to sign in', 'havenlytics'); ?></button>
                        </p>
                    </form>
                <?php endif; ?>

                <?php if (!$hvnly_can_login && !$hvnly_can_register) : ?>
                    <p class="hvnly-auth__hint">
                        <?php echo esc_html__('Account access is currently unavailable. Please contact the site administrator.', 'havenlytics'); ?>
                    </p>
                <?php endif; ?>

                <!-- Future sign-in options (2FA, magic link, social) mount here. -->
                <div class="hvnly-auth__extensions" data-hvnly-auth-extensions hidden></div>
            </div>

        <?php endif; ?>
    </div>
</div>
