<?php
/**
 * Property Import Wizard Template - Exact Design
 *
 * @package Havenlytics
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$hvnly_demo_imported = get_option('hvnly_demo_properties_imported', false);
$hvnly_demo_import_count = get_option('hvnly_demo_properties_count', 0);
$hvnly_cache_enabled = function_exists('hvnly_is_cache_enabled') ? hvnly_is_cache_enabled() : (bool) get_option('hvnly_cache_enabled', 0);
$hvnly_import_email_notify_default = class_exists( \HvnlyNab\Email\EmailSettings::class )
    ? \HvnlyNab\Email\EmailSettings::import_wizard_notify_default()
    : true;

$hvnly_wizard_step_slugs = array(
    'department'  => 1,
    'location'    => 2,
    'media'       => 3,
    'preferences' => 4,
    'review'      => 4,
);
$hvnly_url_step_slug   = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
$hvnly_initial_step    = 1;
$hvnly_wizard_invalid_step = false;
if ( '' !== $hvnly_url_step_slug ) {
    if ( isset( $hvnly_wizard_step_slugs[ $hvnly_url_step_slug ] ) ) {
        $hvnly_initial_step = (int) $hvnly_wizard_step_slugs[ $hvnly_url_step_slug ];
    } else {
        $hvnly_wizard_invalid_step = true;
    }
}
$hvnly_step_progress_pct = $hvnly_initial_step > 1 ? ( ( ( $hvnly_initial_step - 1 ) / 3 ) * 100 ) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html__('Property Import Wizard | Havenlytics', 'havenlytics'); ?></title>
    <?php wp_head(); ?>
</head>

<body class="hvnly--property--import-page">
    <!-- CSRF Token -->
    <input type="hidden" id="hvnly_csrf_token" value="<?php echo esc_attr(wp_create_nonce('hvnly_import_csrf')); ?>">

    <div class="hvnly--property--import-celebration" id="celebrationModal">
        <div class="hvnly--property--import-celebration-content">
            <h2 class="hvnly--property--import-celebration-title">
                <?php echo esc_html__('Import Complete!', 'havenlytics'); ?></h2>
            <p class="hvnly--property--import-celebration-description">
                <?php echo esc_html__('Your properties, demo agents, and agencies have been successfully imported! Explore agent profiles, agency pages, and Contact Agent on your listings.', 'havenlytics'); ?>
            </p>
            <div class="hvnly--property--import-celebration-actions">
                <a href="#" id="celebration-view-site" class="hvnly--property--import-completion-button primary" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                    <span class="hvnly-celebration-btn-label"><?php echo esc_html__( 'View Properties', 'havenlytics' ); ?></span>
                </a>
                <a href="#" id="celebration-dashboard" class="hvnly--property--import-completion-button secondary">
                    <i class="fas fa-th-list" aria-hidden="true"></i>
                    <span class="hvnly-celebration-btn-label"><?php echo esc_html__( 'Return to Dashboard', 'havenlytics' ); ?></span>
                </a>
            </div>
        </div>
    </div>

    <header class="hvnly--property--import-navbar" role="banner">
        <div class="hvnly--property--import-navbar-left">
            <div class="hvnly--property--import-navbar-brand">
                <span class="hvnly--property--import-navbar-logo" aria-hidden="true">
                    <img style="width: 50px; "
                        src="<?php echo esc_url(HVNLYNAB_ASSETS_URL . '/admin/img/havenlytics-icon20x20.svg'); ?>"
                        alt="<?php echo esc_attr__('Havenlytics Logo', 'havenlytics'); ?>">
                </span>
                <div class="hvnly--property--import-navbar-heading">
                    <h1 class="hvnly--property--import-navbar-title">
                        <?php echo esc_html__('Quick Property Setup', 'havenlytics'); ?>
                    </h1>
                    <p class="hvnly--property--import-navbar-subtitle">
                        <?php echo esc_html__('Import demo properties and configure your listing defaults in a few guided steps.', 'havenlytics'); ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="hvnly--property--import-navbar-right">
            <a href="#" class="hvnly--property--import-doc-button">
                <i class="fas fa-book"></i>
                <?php echo esc_html__('Documentation', 'havenlytics'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=hvnly_property')); ?>"
                class="hvnly--property--import-close-button">
                <i class="fas fa-times"></i>
                <?php echo esc_html__('Close Wizard', 'havenlytics'); ?>
            </a>
        </div>
    </header>

    <main class="hvnly--property--import-layout" id="hvnly-import-main">
        <div class="hvnly--property--import-wrapper hvnly-import-wizard">
            <div id="hvnly-wizard-setup-phase" class="hvnly-wizard-setup-phase">
            <div class="hvnly-wizard-setup-phase__main">
            <div class="hvnly--property--import-step-progress-container hvnly-wizard-stepper-shell">
                <div class="hvnly-wizard-stepper__header">
                    <p class="hvnly--property--import-progress-label">
                        <?php echo esc_html__('Setup progress', 'havenlytics'); ?>
                    </p>
                    <p class="hvnly-wizard-stepper__subtitle">
                        <?php echo esc_html__('Step', 'havenlytics'); ?>
                        <span id="hvnlyWizardStepCounter"><?php echo esc_html( (string) $hvnly_initial_step ); ?></span>
                        <?php echo esc_html__('of 4', 'havenlytics'); ?>
                    </p>
                </div>
                <nav class="hvnly-wizard-stepper" id="hvnlyWizardStepper" data-current-step="<?php echo esc_attr( (string) $hvnly_initial_step ); ?>" aria-label="<?php echo esc_attr__('Import wizard steps', 'havenlytics'); ?>">
                    <div class="hvnly-wizard-stepper__track" aria-hidden="true">
                        <div class="hvnly-wizard-stepper__track-line"></div>
                        <div class="hvnly-wizard-stepper__track-fill hvnly--property--import-step-progress-bar" id="stepProgressBar" style="width: <?php echo esc_attr( (string) $hvnly_step_progress_pct ); ?>%;"></div>
                    </div>
                    <ol class="hvnly-wizard-stepper__list hvnly--property--import-step-progress">
                        <li class="hvnly-wizard-stepper__item hvnly--property--import-step-item<?php echo 1 === $hvnly_initial_step ? ' is-active active' : ( $hvnly_initial_step > 1 ? ' is-completed completed' : '' ); ?>" data-step="1">
                            <span class="hvnly-wizard-stepper__marker hvnly--property--import-step-icon" aria-hidden="true">
                                <span class="hvnly-wizard-stepper__number">1</span>
                                <span class="hvnly-wizard-stepper__check"><i class="fas fa-check"></i></span>
                                <i class="hvnly-wizard-stepper__step-icon fas fa-layer-group"></i>
                            </span>
                            <span class="hvnly-wizard-stepper__label hvnly--property--import-step-title">
                                <?php echo esc_html__('Department & Taxonomies', 'havenlytics'); ?>
                            </span>
                        </li>
                        <li class="hvnly-wizard-stepper__item hvnly--property--import-step-item<?php echo 2 === $hvnly_initial_step ? ' is-active active' : ( $hvnly_initial_step > 2 ? ' is-completed completed' : '' ); ?>" data-step="2">
                            <span class="hvnly-wizard-stepper__marker hvnly--property--import-step-icon" aria-hidden="true">
                                <span class="hvnly-wizard-stepper__number">2</span>
                                <span class="hvnly-wizard-stepper__check"><i class="fas fa-check"></i></span>
                                <i class="hvnly-wizard-stepper__step-icon fas fa-map-marker-alt"></i>
                            </span>
                            <span class="hvnly-wizard-stepper__label hvnly--property--import-step-title">
                                <?php echo esc_html__('Location', 'havenlytics'); ?>
                            </span>
                        </li>
                        <li class="hvnly-wizard-stepper__item hvnly--property--import-step-item<?php echo 3 === $hvnly_initial_step ? ' is-active active' : ( $hvnly_initial_step > 3 ? ' is-completed completed' : '' ); ?>" data-step="3">
                            <span class="hvnly-wizard-stepper__marker hvnly--property--import-step-icon" aria-hidden="true">
                                <span class="hvnly-wizard-stepper__number">3</span>
                                <span class="hvnly-wizard-stepper__check"><i class="fas fa-check"></i></span>
                                <i class="hvnly-wizard-stepper__step-icon fas fa-photo-video"></i>
                            </span>
                            <span class="hvnly-wizard-stepper__label hvnly--property--import-step-title">
                                <?php echo esc_html__('Media', 'havenlytics'); ?>
                            </span>
                        </li>
                        <li class="hvnly-wizard-stepper__item hvnly--property--import-step-item<?php echo 4 === $hvnly_initial_step ? ' is-active active' : ''; ?>" data-step="4">
                            <span class="hvnly-wizard-stepper__marker hvnly--property--import-step-icon" aria-hidden="true">
                                <span class="hvnly-wizard-stepper__number">4</span>
                                <span class="hvnly-wizard-stepper__check"><i class="fas fa-check"></i></span>
                                <i class="hvnly-wizard-stepper__step-icon fas fa-clipboard-check"></i>
                            </span>
                            <span class="hvnly-wizard-stepper__label hvnly--property--import-step-title">
                                <?php echo esc_html__('Review', 'havenlytics'); ?>
                            </span>
                        </li>
                    </ol>
                </nav>
            </div>

            <div class="hvnly--property--import-content-card">
                <div class="hvnly--property--import-step-content" id="step-content" role="region" aria-live="polite">
                    <?php include __DIR__ . '/partials/wizard-steps.php'; ?>
                </div>
            </div>
            </div>

            <div class="hvnly-wizard-sticky-footer">
                <nav class="hvnly--property--import-navigation hvnly--property--import-footer-nav"
                    aria-label="<?php echo esc_attr__('Wizard navigation', 'havenlytics'); ?>">
                    <button class="hvnly--property--import-nav-button prev" id="prevStep">
                        <i class="fas fa-arrow-left"></i>
                        <?php echo esc_html__('Previous', 'havenlytics'); ?>
                    </button>
                    <button class="hvnly--property--import-nav-button skip" id="skipStep">
                        <i class="fas fa-forward"></i>
                        <?php echo esc_html__('Skip Now', 'havenlytics'); ?>
                    </button>
                    <button class="hvnly--property--import-nav-button next" id="nextStep">
                        <?php echo esc_html__('Next', 'havenlytics'); ?>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </nav>
            </div>
            </div>

            <?php include __DIR__ . '/partials/import-phase.php'; ?>
        </div>
    </main>

    <?php wp_footer(); ?>
</body>

</html>