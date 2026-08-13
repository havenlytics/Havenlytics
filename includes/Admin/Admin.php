<?php

namespace HvnlyNab\Admin;

if ( ! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Main Admin Service Loader.
 *
 * Bootstraps all admin-related classes for Havenlytics.
 *
 * @package HvnlyNab\Admin
 */
final class Admin {

    /**
     * Constructor.
     */
    public function __construct() {
        static::register_services();
    }

    /**
     * Get all registered services.
     *
     * @return class-string[] List of service class names.
     */
    public static function get_services(): array {
        $services = array(
            Menu::class,
            Assets::class,
            DocumentationPage::class,
            PropertyImportWizard::class,
            OnboardingWizard::class,
            SetupWizardNotice::class,
            ThemeRecommendationNotice::class,
            \HvnlyNab\ContactAgent\Admin\InquiryAdminPage::class,
        );

        /**
         * Filter the list of admin services.
         *
         * @param array $services List of service classes.
         */
        return apply_filters('hvnly_admin_services', $services);
    }

    /**
     * Register all services.
     *
     * @return void
     */
    public static function register_services(): void {
        array_map(
            static function ( string $class ): void {
                $instance = static::instantiate($class);

                if ($instance && method_exists($instance, 'register')) {
                    $instance->register();
                }
            },
            static::get_services()
        );
    }

    /**
     * Instantiate a service class.
     *
     * @param string $class Class name.
     * @return object|null Instance of the class, or null if class does not exist.
     */
    private static function instantiate( string $class ): ?object {
        if (class_exists($class)) {
            return new $class();
        }

        return null;
    }
}
