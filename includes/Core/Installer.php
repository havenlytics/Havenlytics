<?php
/**
 * Plugin Installer - Handles plugin installation and setup procedures
 *
 * Coordinates the initial setup and installation of the Havenlytics plugin.
 * Delegates to specialized setup classes and ensures all required components
 * are properly initialized during plugin activation.
 *
 * @package     Havenlytics
 * @subpackage  Core
 * @author      Havenlytics
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 * @version     2.1.3
 */

namespace HvnlyNab\Core;

// Prevent direct access to WordPress files.
if (!defined('ABSPATH')) {
    exit;
}

// Import required classes.
use HvnlyNab\Setup\Installer as SetupInstaller;
use HvnlyNab\Core\Migration\Migrator;

/**
 * Installer Class
 *
 * Main installation controller that coordinates the plugin setup process.
 * Serves as a facade that delegates to specialized setup classes and
 * ensures all installation tasks are completed in the correct order.
 *
 * @since 2.0.0
 */
class Installer
{
    /**
     * Database version option key for tracking schema versions.
     *
     * @since 2.1.3
     * @var string
     */
    const DB_VERSION_KEY = 'hvnly_db_version';

    /**
     * Execute plugin installation procedures.
     *
     * Main installation method called during plugin activation.
     * Coordinates all setup tasks including database schema, default options,
     * page creation, and scheduled event registration.
     *
     * Installation process:
     * 1. Run specialized setup installer (if available)
     * 2. Create required pages
     * 3. Set initial database version
     * 4. Schedule recurring events
     * 5. Initialize default builder configurations
     * 6. Run database migrations
     *
     * @since 2.0.0
     * @return void
     */
    public static function install() {
        // Step 1: Run specialized setup installer for core configuration.
        if (class_exists(SetupInstaller::class)) {
            $installer = new SetupInstaller();
            $installer->run();
        }
        
        // Step 2: Create required plugin pages with shortcodes.
        self::create_required_pages();
        
        // Step 3: Schedule recurring cron events for maintenance.
        Scheduler::schedule_events();
        
        // Step 4: Initialize default builder configurations if not exist.
        self::initialize_builders();
        
        // Step 5: Run database migrations; bump DB version only when all succeed.
        $migrations_ok = Migrator::run( true );
        if ( $migrations_ok ) {
            update_option( self::DB_VERSION_KEY, HVNLYNAB_VERSION );
        }

        // Step 6: Seed default Property Agent sidebar widget on fresh installs.
        if ( class_exists( '\HvnlyNab\Setup\SidebarWidgetInstaller' ) ) {
            \HvnlyNab\Setup\SidebarWidgetInstaller::maybe_install();
        }

        // Step 7: Schedule rewrite flush after full bootstrap (CPT registered on init).
        if ( class_exists( '\HvnlyNab\Core\RewriteRulesManager' ) ) {
            \HvnlyNab\Core\RewriteRulesManager::schedule_flush();
        }
    }

    /**
     * Plugin activation handler — safe for updates on sites with existing data.
     *
     * WordPress runs activation on every plugin update (deactivate + reactivate).
     * Existing sites must only run pending migrations, never re-seed builders or pages.
     *
     * @since 3.0.2
     * @return void
     */
    public static function activate() {
        $existing_db_version = get_option( self::DB_VERSION_KEY, '' );
        $is_existing_site    = is_string( $existing_db_version )
            && $existing_db_version !== ''
            && $existing_db_version !== '0.0.0';

        if ( $is_existing_site ) {
            self::clear_stale_activation_redirect_flags();

            $migrations_ok = Migrator::run( true );
            if ( $migrations_ok ) {
                update_option( self::DB_VERSION_KEY, HVNLYNAB_VERSION );
            }

            if ( class_exists( '\HvnlyNab\Core\PermalinkSettings' ) ) {
                \HvnlyNab\Core\PermalinkSettings::prepare_upgrade_from_pre_split_archives();
            }

            if ( class_exists( '\HvnlyNab\Core\RewriteRulesManager' ) ) {
                \HvnlyNab\Core\RewriteRulesManager::schedule_flush();
            }

            // Create any pages added after the site was first installed (e.g. Agents).
            self::create_required_pages();

            return;
        }

        self::install();
    }

    /**
     * Remove post-update activation redirect flags so admin navigation is not hijacked.
     *
     * @since 3.0.2
     * @return void
     */
    public static function clear_stale_activation_redirect_flags() {
        delete_option( 'hvnly_activation_redirect' );
        delete_transient( 'hvnly_activation_redirect' );
    }
    
    /**
     * Plugin deactivation handler.
     *
     * Cleans up shortcode caches and scheduled events.
     *
     * @since 2.0.0
     * @return void
     */
    public static function deactivate() {
        // Clear all shortcode caches to prevent stale content.
        if (class_exists('HvnlyNab\Frontend\Shortcodes\Registry')) {
            HvnlyNab\Frontend\Shortcodes\Registry::clear_all_caches();
        }
        
        // Clear scheduled cron events.
        Scheduler::clear_scheduled_events();
        
        // Flush rewrite rules to remove custom post type routes.
        flush_rewrite_rules();
    }

    /**
     * Create required pages on plugin activation.
     *
     * Creates or updates the necessary WordPress pages for plugin functionality:
     * - Property Search page
     * - Property Grid page
     * - Property Lists page
     * - Property Agents page
     * - Property Agencies page
     *
     * @since 2.0.0
     * @return void
     */
    private static function create_required_pages()
    {
        $pages = [
            [
                'title'      => __('Property Search', 'havenlytics'),
                'full_title' => 'Property Search -- Havenlytics',
                'shortcode'  => '[hvnly_property_search]',
                'option_key' => 'hvnly_property_search_page_id',
                'slug'       => 'property-search',
            ],
            [
                'title'      => __('Property Grid', 'havenlytics'),
                'full_title' => 'Property Grid -- Havenlytics',
                'shortcode'  => '[hvnly_property_grid]',
                'option_key' => 'hvnly_property_grid_page_id',
                'slug'       => 'property-grid',
            ],
            [
                'title'      => __('Property Lists', 'havenlytics'),
                'full_title' => 'Property Lists -- Havenlytics',
                'shortcode'  => '[hvnly_property_lists]',
                'option_key' => 'hvnly_property_list_page_id',
                'slug'       => 'property-lists',
            ],
            [
                'title'      => __('Agents', 'havenlytics'),
                'full_title' => 'Agents -- Havenlytics',
                'shortcode'  => '[hvnly_property_agents]',
                'option_key' => 'hvnly_property_agents_page_id',
                'slug'       => 'property-agents',
            ],
            [
                'title'      => __('Agency', 'havenlytics'),
                'full_title' => 'Agency -- Havenlytics',
                'shortcode'  => '[hvnly_property_agencies]',
                'option_key' => 'hvnly_property_agencies_page_id',
                'slug'       => 'property-agencies',
            ],
        ];

        foreach ($pages as $page) {
            $existing_id = get_option($page['option_key']);

            // Update existing page if found.
            if ($existing_id && get_post($existing_id)) {
                $post = get_post($existing_id);
                if ($post && $post->post_name !== $page['slug']) {
                    wp_update_post([
                        'ID'         => $existing_id,
                        'post_name'  => $page['slug'],
                    ]);
                }
                self::sync_plugin_page_shortcode( (int) $existing_id, $page['shortcode'] );
                continue;
            }
            
            // Check if page exists by full title.
            $query = new \WP_Query([
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'title'          => $page['full_title'],
                'fields'         => 'ids',
            ]);

            if ($query->have_posts()) {
                $found_id = $query->posts[0];
                update_option($page['option_key'], $found_id);
                update_post_meta($found_id, '_hvnly_property_auto_created', true);
                update_post_meta($found_id, '_hvnly_plugin_page', '1');
                
                // Update slug for existing page.
                wp_update_post([
                    'ID'         => $found_id,
                    'post_name'  => $page['slug'],
                ]);
                self::sync_plugin_page_shortcode( (int) $found_id, $page['shortcode'] );
                continue;
            }
            
            // Get current user ID or fallback to admin.
            $current_user_id = get_current_user_id();
            if (!$current_user_id) {
                $admin_user = get_users(['role' => 'administrator', 'number' => 1]);
                $current_user_id = !empty($admin_user) ? $admin_user[0]->ID : 1;
            }
            
            // Insert new page with custom slug.
            $page_id = wp_insert_post([
                'post_title'   => $page['full_title'],
                'post_name'    => $page['slug'],
                'post_content' => $page['shortcode'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => $current_user_id,
            ]);

            if (!is_wp_error($page_id) && $page_id > 0) {
                update_option($page['option_key'], $page_id);
                update_post_meta($page_id, '_hvnly_property_auto_created', true);
                update_post_meta($page_id, '_hvnly_plugin_page', '1');
            }
        }
    }
    
    /**
     * Ensure a plugin page contains its shortcode (skip Elementor-built pages).
     *
     * @since 3.0.3
     * @param int    $page_id   Page ID.
     * @param string $shortcode Expected shortcode, e.g. [hvnly_property_agencies].
     * @return void
     */
    private static function sync_plugin_page_shortcode( int $page_id, string $shortcode ): void {
        $page_id = absint( $page_id );
        if ( $page_id <= 0 || ! preg_match( '/\[(\w+)/', $shortcode, $matches ) ) {
            return;
        }

        $post = get_post( $page_id );
        if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type ) {
            return;
        }

        if ( get_post_meta( $page_id, '_elementor_edit_mode', true ) ) {
            return;
        }

        $tag = $matches[1];
        if ( has_shortcode( (string) $post->post_content, $tag ) ) {
            return;
        }

        wp_update_post(
            array(
                'ID'           => $page_id,
                'post_content' => $shortcode,
            )
        );
    }

    /**
     * Add filter to display plugin state in admin pages list.
     *
     * This method should be called during plugin initialization.
     *
     * @since 2.0.0
     * @return void
     */
    public static function add_admin_filters() {
        add_filter('display_post_states', [__CLASS__, 'add_plugin_page_state'], 10, 2);
    }
    
    /**
     * Add plugin page state to show in admin pages list.
     *
     * Creates the " — Havenlytics" text after the page title to identify
     * plugin-generated pages in the WordPress admin.
     *
     * @since 2.0.0
     * @param array   $post_states Existing post states.
     * @param WP_Post $post        Current post object.
     * @return array Modified post states.
     */
    public static function add_plugin_page_state($post_states, $post) {
        // Only add state for pages with our plugin meta.
        if ($post->post_type === 'page') {
            $is_plugin_page = get_post_meta($post->ID, '_hvnly_plugin_page', true);
            
            if ($is_plugin_page) {
                $post_states['hvnly_plugin_page'] = __('Havenlytics', 'havenlytics');
            }
        }
        
        return $post_states;
    }
    
    /**
     * Run on plugin update.
     *
     * Handles version upgrades by running necessary migrations
     * and updating the database version.
     *
     * @since 2.0.0
     * @param string $old_version The old version number.
     * @param string $new_version The new version number.
     * @return void
     */
    public static function update($old_version, $new_version) {
        // Database version is bumped by Bootstrap only after Migrator::run() succeeds.
        // Clear all caches to ensure fresh data after successful upgrade.
        if (function_exists('HVNLY_NAB') && HVNLY_NAB()->engine()) {
            HVNLY_NAB()->engine()->clear_all_cache();
        }
    }
    
    /**
     * Initialize default builder configurations.
     *
     * Sets up default property and card builder configurations
     * if they don't already exist in the database.
     *
     * @since 2.0.0
     * @return void
     */
    private static function initialize_builders() {
        // Initialize property builder if not exists.
        if (get_option('hvnly_property_builder.sections') === false) {
            if (class_exists('\HvnlyNab\Core\UnifiedFieldGenerator')) {
                $unified = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
                update_option('hvnly_property_builder.sections', $unified->get_unified_configuration());
            } else {
                update_option('hvnly_property_builder.sections', self::get_default_property_builder());
            }
        }
        
        // Initialize card builder if not exists.
        if (get_option('hvnly_property_card.sections') === false) {
            if (class_exists('\HvnlyNab\Api\Type\Builders\DnDCardBuilder')) {
                update_option(
                    'hvnly_property_card.sections',
                    \HvnlyNab\Api\Type\Builders\DnDCardBuilder::get_default_sections_for_storage()
                );
            }
        }
    }
    
    /**
     * Get default property builder configuration.
     *
     * Returns the default configuration for the property builder
     * with basic info section and price field.
     *
     * @since 2.0.0
     * @return array Default property builder configuration.
     */
    private static function get_default_property_builder(): array {
        return [
            [
                'id' => 'basic_info',
                'title' => 'Basic Info',
                'icon' => 'fas fa-home',
                'fields' => [
                    [
                        'id' => 'property_price',
                        'name' => '_hvnly_property_price',
                        'type' => 'price_label',
                        'label' => 'Property Price',
                        'required' => true,
                        'order' => 0
                    ]
                ],
                'order' => 0
            ]
        ];
    }
}