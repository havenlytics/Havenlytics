<?php
/**
 * Setup Wizard Notice - Admin notice for property setup wizard
 *
 * Displays a friendly notice in the WordPress admin dashboard when no properties
 * have been created/imported yet, encouraging users to run the setup wizard.
 *
 * @package     Havenlytics
 * @subpackage  Admin
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.5
 */

namespace HvnlyNab\Admin;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Setup Wizard Notice Class
 *
 * Manages the admin notice that appears when no properties exist,
 * prompting users to run the setup wizard or skip setup.
 *
 * @since 2.0.5
 */
class SetupWizardNotice {

    /**
     * Option name for hiding the notice
     *
     * @var string
     */
    private const HIDE_NOTICE_OPTION = 'hvnly_setup_notice_hidden';

    /**
     * Nonce action for notice dismissal
     *
     * @var string
     */
    private const NONCE_ACTION = 'hvnly_setup_notice_nonce';

    /**
     * Nonce name for notice dismissal
     *
     * @var string
     */
    private const NONCE_NAME = 'hvnly_setup_notice_nonce';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array( $this, 'handle_notice_dismissal' ));
        add_action('admin_notices', array( $this, 'display_notice' ));
    }

    /**
     * Check if the notice should be displayed
     *
     * Conditions for display:
     * - User is in admin area
     * - User has manage_options capability
     * - Notice hasn't been dismissed
     * - There are no published properties
     *
     * @return bool True if notice should be displayed, false otherwise
     */
    private function should_display_notice(): bool {
        // Don't show on the import wizard page itself
        if ($this->is_import_wizard_page()) {
            return false;
        }

        // Don't show if notice was dismissed
        if (get_option(self::HIDE_NOTICE_OPTION, false)) {
            return false;
        }

        // Check if there are any published properties
        $property_count  = wp_count_posts('hvnly_property');
        $published_count = isset($property_count->publish) ? absint($property_count->publish) : 0;

        // Only show if no properties exist
        if ($published_count > 0) {
            return false;
        }

        // Only show to users who can manage options
        return current_user_can('manage_options');
    }

    /**
     * Check if current page is the import wizard page
     *
     * @return bool True if on import wizard page, false otherwise
     */
    private function is_import_wizard_page(): bool {
        global $current_screen;

        if ($current_screen && 'hvnly_property_page_hvnly-property-onboarding' === $current_screen->id) {
            return true;
        }

        if (isset($_GET['page']) && 'hvnly-property-onboarding' === $_GET['page'] && isset($_GET['post_type']) && 'hvnly_property' === $_GET['post_type']) {
            return true;
        }

        return false;
    }

    /**
     * Handle notice dismissal via GET request
     *
     * @return void
     */
    public function handle_notice_dismissal(): void {
        // Check if this is a dismissal request
        if ( ! isset($_GET['hvnly-hide-setup-notice'])) {
            return;
        }

        // Verify nonce
        if ( ! isset($_GET[ self::NONCE_NAME ]) || ! wp_verify_nonce($_GET[ self::NONCE_NAME ], self::NONCE_ACTION)) {
            return;
        }

        // Check user capability
        if ( ! current_user_can('manage_options')) {
            return;
        }

        // Set the option to hide the notice
        update_option(self::HIDE_NOTICE_OPTION, true);

        // Redirect back to the current page without the query parameter
        $redirect_url = remove_query_arg(array( 'hvnly-hide-setup-notice', self::NONCE_NAME ));
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Display the setup wizard notice
     *
     * @return void
     */
    public function display_notice(): void {
        if ( ! $this->should_display_notice()) {
            return;
        }

        // Generate nonce URL for dismissal
        $dismiss_url = add_query_arg(
            array(
                'hvnly-hide-setup-notice' => 1,
                self::NONCE_NAME => wp_create_nonce(self::NONCE_ACTION),
            )
        );

        // Onboarding ("Get Started") URL
        $setup_wizard_url = admin_url('edit.php?post_type=hvnly_property&page=hvnly-property-onboarding');

        // Properties list URL
        $properties_url = admin_url('edit.php?post_type=hvnly_property');

        ?>
<div id="message" class="notice notice-success is-dismissible hvnly-setup-notice">
    <p>
        <strong><?php esc_html_e('Welcome to Havenlytics', 'havenlytics'); ?></strong> –
        <?php esc_html_e('You\'re almost ready to start your property directory!', 'havenlytics'); ?>
    </p>
    <p class="submit">
        <a href="<?php echo esc_url($setup_wizard_url); ?>" class="button-primary">
            <?php esc_html_e('Get Started', 'havenlytics'); ?>
        </a>
        <a href="<?php echo esc_url($dismiss_url); ?>" class="button-secondary skip">
            <?php esc_html_e('Skip setup', 'havenlytics'); ?>
        </a>
    </p>
</div>

<style>
.hvnly-setup-notice {
    border-left-color: #6C60FE;
}

.hvnly-setup-notice .button-primary {
    background: #6C60FE;
    border-color: #5a4ee0;
}

.hvnly-setup-notice .button-primary:hover {
    background: #5a4ee0;
    border-color: #4a3ec0;
}

.hvnly-setup-notice .skip {
    margin-left: 10px;
}
</style>
		<?php
    }

    /**
     * Reset the notice (show it again)
     * Useful when properties are deleted and we want to show the notice again
     *
     * @return void
     */
    public static function reset_notice(): void {
        delete_option(self::HIDE_NOTICE_OPTION);
    }

    /**
     * Check if the notice is currently hidden
     *
     * @return bool
     */
    public static function is_notice_hidden(): bool {
        return (bool) get_option(self::HIDE_NOTICE_OPTION, false);
    }
}