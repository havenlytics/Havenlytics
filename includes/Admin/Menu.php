<?php

namespace HvnlyNab\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class Menu
{
    private const PARENT_SLUG = 'edit.php?post_type=hvnly_property';
    private const CAPABILITY = 'manage_options';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_submenus']);
        add_action('current_screen', [$this, 'suppress_other_notices']);
        add_filter('admin_body_class', [$this, 'builder_admin_body_class']);
    }

    public function register_submenus(): void
    {
        $capability = apply_filters('hvnly_admin_capability', self::CAPABILITY);

        add_submenu_page(
            self::PARENT_SLUG,
            esc_html__('Settings', 'havenlytics'),
            esc_html__('Settings', 'havenlytics'),
            $capability,
            'hvnly_property_settings',
            [$this, 'render_settings']
        );

        add_submenu_page(
            self::PARENT_SLUG,
            esc_html__('Property Builder', 'havenlytics'),
            esc_html__('Property Builder', 'havenlytics'),
            $capability,
            'hvnly_property_builder',
            [$this, 'render_builder']
        );

        // add_submenu_page(
        //     self::PARENT_SLUG,
        //     esc_html__('Reports & Analytics', 'havenlytics'),
        //     esc_html__('Reports & Analytics', 'havenlytics'),
        //     $capability,
        //     'hvnly_property_reports_analytics',
        //     [$this, 'render_reports']
        // );

        
        // Cache menu item removed - now handled by CacheAdmin.php
    }

    public function render_settings(): void
    {
        ?>
        <div id="HvnlyNab_admin_dashboard_wrap" class="HvnlyNab_admin_dashboard_wrap hvnly-admin-is-loading">
            <?php AdminPreloader::render( 'settings' ); ?>
            <div id="HvnlyNab_admin_dashboard" class="HvnlyNab_admin_dashboard"></div>
        </div>
        <?php
    }

    public function render_builder(): void
    {
        ?>
        <div id="HvnlyNab_property_builder_render" class="HvnlyNab_property_builder_render hvnly-admin-is-loading">
            <?php AdminPreloader::render( 'builder' ); ?>
            <div id="hvnly-builder-app-root" class="hvnly-builder-app-root"></div>
        </div>
        <?php
    }

    public function render_reports(): void
    {
        echo '<div id="HvnlyNab_reports_analytics_render" class="HvnlyNab_reports_analytics_render"></div>';
    }

    public function builder_admin_body_class(string $classes): string
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if ($screen && in_array($screen->id, ['hvnly_property_page_hvnly_property_builder', 'hvnly_property_page_hvnly_property_settings'], true)) {
            $classes .= ' hvnly-admin-page-loading';
        }

        return $classes;
    }

    public function suppress_other_notices(\WP_Screen $screen): void
    {
        if (!$screen) return;

        if (in_array($screen->id, $this->get_plugin_screens(), true)) {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
            add_action('admin_notices', [$this, 'plugin_admin_notices']);
        }
    }

    private function get_plugin_screens(): array
    {
        $screens = ['edit-hvnly_property', 'hvnly_property'];
        foreach (get_object_taxonomies('hvnly_property', 'names') as $tax) {
            $screens[] = "edit-{$tax}";
            $screens[] = $tax;
        }
        $screens[] = 'hvnly_property_page_hvnly_property_settings';
        $screens[] = 'hvnly_property_page_hvnly_property_builder';
        $screens[] = 'hvnly_property_page_hvnly_property_reports_analytics';
        $screens[] = 'hvnly_property_page_hvnly_inquiries';
        $screens[] = 'edit-hvnly_agent';
        $screens[] = 'hvnly_agent';
        $screens[] = 'edit-hvnly_agent_agency';
        $screens[] = 'hvnly_agent_agency';
        // Cache screen removed - now handled by CacheAdmin.php
        return $screens;
    }

    public function plugin_admin_notices(): void
    {
        Assets::render_missing_build_asset_notice();
    }
}