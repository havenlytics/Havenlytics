<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Translate a Havenlytics default UI string for the current locale.
 *
 * Used for stored builder labels/section titles that ship as English msgids.
 * If a translated string was incorrectly persisted, it is canonicalized back to
 * the English msgid first so locale switches remain recoverable.
 * Custom user-authored labels that are not in the catalog pass through unchanged.
 *
 * @param string $text English UI label or section title (or a known translation thereof).
 * @return string
 */
function hvnly_translate_ui( $text ) {
	$text = is_string( $text ) ? $text : '';
	if ( $text === '' ) {
		return '';
	}

	if ( function_exists( 'hvnly_canonicalize_ui_label' ) ) {
		$text = hvnly_canonicalize_ui_label( $text );
	}

	// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Dynamic catalog lookup for default UI labels.
	return __( $text, 'havenlytics' );
}

/**
 * Escape HTML and translate a Havenlytics default UI string.
 *
 * WPCS / Plugin Check escaping wrapper: return value is safe for echo.
 * Registered via phpcs.xml.dist and phpcs-rulesets/havenlytics-plugin-check-escape.xml.
 *
 * @param string $text English UI label or section title.
 * @return string Escaped HTML-safe string.
 */
function hvnly_esc_html_ui( $text ) {
	return esc_html( hvnly_translate_ui( $text ) );
}

/**
 * Escape attribute and translate a Havenlytics default UI string.
 *
 * WPCS / Plugin Check escaping wrapper: return value is safe for attributes.
 * Registered via phpcs.xml.dist and phpcs-rulesets/havenlytics-plugin-check-escape.xml.
 *
 * @param string $text English UI label or section title.
 * @return string Escaped attribute-safe string.
 */
function hvnly_esc_attr_ui( $text ) {
	return esc_attr( hvnly_translate_ui( $text ) );
}

/**
 * Resolve an archive card feature label from builder config.
 *
 * Prefers the saved builder label (translated when it is still a default English
 * msgid). Falls back to pluralized gettext strings when no custom label exists.
 *
 * @param array  $field    Builder field config.
 * @param string $singular Default singular msgid.
 * @param string $plural   Default plural msgid.
 * @param int    $count    Quantity for pluralization.
 * @return string
 */
function hvnly_archive_feature_label( array $field, $singular, $plural, $count ) {
	$label = isset( $field['label'] ) ? trim( (string) $field['label'] ) : '';
	if ( '' !== $label ) {
		return hvnly_translate_ui( $label );
	}

	$count = max( 1, (int) $count );

	// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralSingle,WordPress.WP.I18n.NonSingularStringLiteralSingular,WordPress.WP.I18n.NonSingularStringLiteralPlural
	return _n( $singular, $plural, $count, 'havenlytics' );
}

/**
 * Whether Havenlytics should write debug messages to the PHP error log.
 *
 * Requires both WP_DEBUG and WP_DEBUG_LOG per WordPress best practice.
 *
 * @return bool
 */
function hvnly_is_debug_logging_enabled() {
    return defined( 'HVNLYNAB_DEBUG' ) && HVNLYNAB_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
}

/**
 * Fast pre-flight check for outbound HTTP to the demo media hosts.
 *
 * The demo importer sideloads images from remote hosts. On WordPress Playground,
 * offline dev, and fresh installs those hosts are unreachable — and, worse, hosts
 * that accept-but-stall can burn each download's full timeout and blow past the
 * request's execution budget. This runs ONE short-timeout probe (cached per request
 * and, via a transient, per import run) so the importer can skip remote media
 * entirely when there is no egress, completing instantly with image-less demo
 * content instead of hanging. Image failure must never break the import.
 *
 * @return bool True when remote demo media appears reachable.
 */
function hvnly_import_remote_media_available() {
    static $checked = null;
    if ( null !== $checked ) {
        return $checked;
    }

    // Allow forcing the result (tests / known-offline environments).
    $override = apply_filters( 'hvnly_import_remote_media_available', null );
    if ( null !== $override ) {
        $checked = (bool) $override;
        return $checked;
    }

    $cached = get_transient( 'hvnly_import_remote_media_ok' );
    if ( false !== $cached ) {
        $checked = ( 'yes' === $cached );
        return $checked;
    }

    $probe = wp_remote_head(
        'https://demo.havenlytics.com/wp-content/uploads/2026/03/1.jpg',
        array(
            'timeout'     => 5,
            'redirection' => 1,
            'sslverify'   => true,
        )
    );

    $checked = ! is_wp_error( $probe )
        && (int) wp_remote_retrieve_response_code( $probe ) > 0
        && (int) wp_remote_retrieve_response_code( $probe ) < 400;

    set_transient( 'hvnly_import_remote_media_ok', $checked ? 'yes' : 'no', 10 * MINUTE_IN_SECONDS );

    return $checked;
}

/**
 * Whether the current request is a system / maintenance context in which property
 * workflow notifications must be suppressed.
 *
 * Sprint 28E. The email + in-app notification system must fire ONLY for real
 * human workflow events (an agent submitting, an administrator publishing /
 * rejecting a listing). System events must never notify:
 *
 *   - WP core import (`WP_IMPORTING`) or a WP-CLI / cron bulk pass (`WP_CLI`).
 *   - The Havenlytics demo / bulk property importer, which flags each
 *     programmatic insert with `$GLOBALS['hvnly_bulk_importing']` (the same
 *     signal {@see \HvnlyNab\Database\Custom_Metabox\Havenlytics_Type} already
 *     uses to skip its metabox save).
 *   - Any other maintenance routine (migration, repair, setup wizard,
 *     sample-content seeding) that opts in by raising
 *     `$GLOBALS['hvnly_suppress_workflow_notifications']` or returning true from
 *     the `hvnly_is_system_notification_context` filter.
 *
 * This is the single source of truth shared by the three listeners that can turn
 * a listing-status change into a message:
 * {@see \HvnlyNab\Workspace\Api\PropertyListingStatusBridge},
 * {@see \HvnlyNab\Email\Notifiers\PropertyWorkflowNotifier} and
 * {@see \HvnlyNab\Workspace\Notifications\NotificationEventListener}.
 *
 * @since 3.2.3
 *
 * @return bool True when property workflow notifications must be suppressed.
 */
function hvnly_is_system_notification_context() {
    if ( ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
        return true;
    }

    if ( ! empty( $GLOBALS['hvnly_bulk_importing'] ) ) {
        return true;
    }

    if ( ! empty( $GLOBALS['hvnly_suppress_workflow_notifications'] ) ) {
        return true;
    }

    /**
     * Filter the system-context decision for property workflow notifications.
     *
     * Return true from a bulk / migration / repair routine to suppress lifecycle
     * emails and in-app rows for the remainder of the request.
     *
     * @since 3.2.3
     *
     * @param bool $is_system Whether this is a non-human system context.
     */
    return (bool) apply_filters( 'hvnly_is_system_notification_context', false );
}

/**
 * Return a validated plugin-owned custom table name for direct SQL queries.
 *
 * Only allows {$wpdb->prefix}hvnly_* identifiers used by Havenlytics custom tables.
 *
 * @param string $table_name Full table name including prefix.
 * @return string Empty string when the table name is not a valid Havenlytics table.
 */
function hvnly_get_validated_custom_table( $table_name ) {
    global $wpdb;

    $table_name = (string) $table_name;
    if ( '' === $table_name ) {
        return '';
    }

    $pattern = '/^' . preg_quote( (string) $wpdb->prefix, '/' ) . 'hvnly_[a-z0-9_]+$/';

    return 1 === preg_match( $pattern, $table_name ) ? $table_name : '';
}

/**
 * Write a debug message to the PHP error log when debug logging is enabled.
 *
 * @param string $message Log message.
 * @param string $context Optional context prefix.
 * @return void
 */
function hvnly_debug_log( $message, $context = 'Havenlytics' ) {
    if ( ! hvnly_is_debug_logging_enabled() ) {
        return;
    }
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated debug helper.
    error_log( sprintf( '[%s] %s', $context, $message ) );
}

/**
 * Resolve property field meta via MetaResolver (legacy + remapped keys).
 *
 * @param int    $post_id Post ID.
 * @param array  $field   Builder field config.
 * @param string $primary Optional primary meta key.
 * @return mixed
 */
function hvnly_resolve_field_meta( int $post_id, array $field, string $primary = '' ) {
    if ( class_exists( '\HvnlyNab\Core\DataPreservation\MetaResolver' ) ) {
        return \HvnlyNab\Core\DataPreservation\MetaResolver::get_field_value( $post_id, $field, $primary );
    }
    $key = $primary !== '' ? $primary : (string) ( $field['name'] ?? $field['id'] ?? '' );
    if ( $key === '' ) {
        return '';
    }
    return get_post_meta( $post_id, $key, true );
}

/**
 * Get search bar title from settings
 *
 * @return string
 * @since 2.0.3
 */
function hvnly_get_search_bar_title() {
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        $search_settings = $settings_manager->get_search_settings();
        
        if (isset($search_settings['hvnly_searchBarTitle']) && !empty($search_settings['hvnly_searchBarTitle'])) {
            return $search_settings['hvnly_searchBarTitle'];
        }
    }
    return '';
}

/**
 * Check if top search title should be hidden
 *
 * @return bool
 * @since 2.0.3
 */
function hvnly_is_top_search_title_hidden() {
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        $search_settings = $settings_manager->get_search_settings();
        
        if (isset($search_settings['hvnly_hideTopSearchTitle'])) {
            $is_hidden = $search_settings['hvnly_hideTopSearchTitle'];
            // Convert to boolean properly
            return filter_var($is_hidden, FILTER_VALIDATE_BOOLEAN);
        }
    }
    return false;
}

/**
 * Check if AJAX Load More is enabled from settings
 *
 * @return bool
 * @since 2.0.3
 */
function hvnly_is_ajax_load_more_enabled()
{
    if (!class_exists('HvnlyNab\Core\SettingsManager')) {
        return true;
    }

    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $search_settings  = $settings_manager->get_search_settings();

    if (isset($search_settings['hvnly_enableAjaxLoadMore'])) {
        return (bool) $search_settings['hvnly_enableAjaxLoadMore'];
    }

    return true;
}
/**
 * Check if breadcrumbs are enabled from settings
 *
 * @return bool
 * @since 2.0.3
 */
function hvnly_is_breadcrumb_enabled()
{
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        return $settings_manager->is_breadcrumb_enabled();
    }
    return true;
}

/**
 * Check if back to top button is enabled from settings
 *
 * @return bool
 * @since 2.0.3
 */
function hvnly_is_back_to_top_enabled()
{
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        return $settings_manager->is_back_to_top_enabled();
    }
    return false;
}

/**
 * Check if print button is enabled from settings
 *
 * @return bool
 * @since 2.0.3
 */
function hvnly_is_print_button_enabled()
{
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        return $settings_manager->is_print_button_enabled();
    }
    return false;
}
/**
 * Check if save property button is enabled from settings
 *
 * @return bool
 * @since 2.0.3
 */
function hvnly_is_save_property_enabled()
{
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        return $settings_manager->is_save_property_enabled();
    }
    return false;
}

/**
 * Get design setting with backward compatibility
 *
 * @param string $key     Setting key (with or without hvnly_ prefix)
 * @param mixed  $default Default value
 * @return mixed
 */
function hvnly_get_design_setting($key, $default = null)
{
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        return $settings_manager->get_design_setting($key, $default);
    }
    return $default;
}

/**
 * Get all design settings
 *
 * @return array
 */
function hvnly_get_design_settings()
{
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        return $settings_manager->get_design_settings();
    }
    return [];
}

/**
 * Get brand color
 *
 * @return string
 */
function hvnly_get_brand_color()
{
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        return $settings_manager->get_brand_color();
    }
    return '#6C60FE';
}

/**
 * Default badge/taxonomy color — follows Global Colors.
 *
 * @return string
 */
function hvnly_get_default_badge_color()
{
    return hvnly_get_brand_color();
}

/**
 * Term badge background for frontend inline styles.
 *
 * Returns a hex color only when the term has a meaningful custom color.
 * Empty string defers to CSS: background-color: var(--hvnly-primary-color).
 *
 * @param int $term_id Term ID.
 * @return string Hex color or empty.
 */
function hvnly_get_term_badge_background_color( $term_id ) {
    $term_id = absint( $term_id );
    if ( ! $term_id ) {
        return '';
    }

    $color = get_term_meta( $term_id, 'hvnly_badge_background_color', true );
    if ( empty( $color ) ) {
        return '';
    }

    $color = sanitize_hex_color( $color );
    if ( empty( $color ) ) {
        return '';
    }

    $brand = hvnly_get_brand_color();

    // Matches Global Color — let CSS tokens update dynamically.
    if ( strcasecmp( $color, $brand ) === 0 ) {
        return '';
    }

    // Factory default saved by older plugin versions when Global Color changed.
    if ( strcasecmp( $color, '#6C60FE' ) === 0 && strcasecmp( $brand, '#6C60FE' ) !== 0 ) {
        return '';
    }

    return $color;
}

/**
 * Term advanced image URL for taxonomy term image fields.
 *
 * @param int    $term_id Term ID.
 * @param string $size    Image size.
 * @return string Image URL or empty string.
 */
function hvnly_get_term_advanced_image_url( $term_id, $size = 'thumbnail' ) {
    $term_id = absint( $term_id );
    if ( ! $term_id ) {
        return '';
    }

    $raw = get_term_meta( $term_id, '_hvnly_term_advanced_image_data', true );
    if ( is_array( $raw ) ) {
        $attachment_id = absint( $raw['id'] ?? 0 );
        if ( $attachment_id > 0 ) {
            $url = wp_get_attachment_image_url( $attachment_id, $size );
            if ( is_string( $url ) && '' !== $url ) {
                return $url;
            }
        }
    }

    // Shared local SVG until a custom image is uploaded (same pattern as locations).
    $term = get_term( $term_id );
    if ( $term instanceof WP_Term ) {
        if ( 'hvnly_prop_locations' === $term->taxonomy
            && class_exists( '\HvnlyNab\Admin\Data\UkLocationTermImages' ) ) {
            return \HvnlyNab\Admin\Data\UkLocationTermImages::placeholder_url();
        }

        if ( 'hvnly_prop_types' === $term->taxonomy
            && class_exists( '\HvnlyNab\Admin\Data\PropertyTypeTermImages' ) ) {
            return \HvnlyNab\Admin\Data\PropertyTypeTermImages::placeholder_url();
        }
    }

    return '';
}

/**
 *
 * @return bool
 */
function hvnly_marker_color_is_custom()
{
    static $is_custom = null;

    if ($is_custom !== null) {
        return $is_custom;
    }

    if (!class_exists('HvnlyNab\Core\SettingsManager')) {
        $is_custom = false;
        return false;
    }

    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    $brand            = hvnly_get_brand_color();
    $marker           = isset($map_settings['hvnly_marker_color']) ? trim((string) $map_settings['hvnly_marker_color']) : '';

    if ($marker === '' || !preg_match('/^#[a-f0-9]{6}$/i', $marker)) {
        $is_custom = false;
        return false;
    }

    if (strcasecmp($marker, $brand) === 0) {
        $is_custom = false;
        return false;
    }

    // Stale factory default in DB while Global Color has changed.
    if (strcasecmp($marker, '#6C60FE') === 0 && strcasecmp($brand, '#6C60FE') !== 0) {
        $is_custom = false;
        return false;
    }

    $is_custom = true;
    return true;
}

/**
 * CSS value for map marker custom property (token when brand-linked).
 *
 * @return string
 */
function hvnly_get_marker_color_css()
{
    if (!hvnly_marker_color_is_custom()) {
        return 'var(--hvnly-primary-color)';
    }

    return hvnly_get_marker_color();
}

/**
 * Get secondary color
 *
 * @return string
 */
function hvnly_get_secondary_color()
{
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        return $settings_manager->get_secondary_color();
    }
    return '#764ba2';
}

/**
 * Get link color
 *
 * @return string
 */
function hvnly_get_link_color()
{
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        return $settings_manager->get_link_color();
    }
    return '#1E1E2F';
}

/**
 * Get title color
 *
 * @return string
 */
function hvnly_get_title_color()
{
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        return $settings_manager->get_title_color();
    }
    return '#1E1E2F';
}

/**
 * Get body font size
 *
 * @return int
 */
function hvnly_get_body_font_size()
{
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        return $settings_manager->get_body_font_size();
    }
    return 16;
}

/**
 * Get body color
 *
 * @return string
 */
function hvnly_get_body_color()
{
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        return $settings_manager->get_body_color();
    }
    return '#ffffff';
}

/**
 * Get cached sidebar filter data
 *
 * @return array
 */
if (!function_exists('hvnly_get_cached_sidebar_filters')) {
    function hvnly_get_cached_sidebar_filters()
    {
        if (!class_exists('HvnlyNab\Frontend\ViewModels\SidebarSearchFilters')) {
            // Fallback to direct query if ViewModel not available
            return hvnly_get_current_filters();
        }

        $sidebar_filters = new HvnlyNab\Frontend\ViewModels\SidebarSearchFilters();
        return $sidebar_filters->get_sidebar_filter_data();
    }
}

/**
 * Clear sidebar cache
 *
 * @return void
 */
if (!function_exists('hvnly_clear_sidebar_cache')) {
    function hvnly_clear_sidebar_cache()
    {
        if (!class_exists('HvnlyNab\Frontend\ViewModels\SidebarSearchFilters')) {
            return;
        }

        $sidebar_filters = new HvnlyNab\Frontend\ViewModels\SidebarSearchFilters();
        $sidebar_filters->clear_all_sidebar_cache();

        do_action('hvnly_sidebar_cache_cleared');
    }
}





/**
 * Get cached taxonomy terms with fallback
 *
 * @param string $taxonomy Taxonomy name
 * @param array  $args     Get terms arguments
 * @return array
 */
if (!function_exists('hvnly_get_cached_terms')) {
    function hvnly_get_cached_terms($taxonomy, $args = [])
    {
        if (!class_exists('HvnlyNab\Frontend\ViewModels\SearchFilters')) {
            // Fallback to direct query if ViewModel not available
            return get_terms(wp_parse_args($args, [
                'taxonomy' => $taxonomy,
                'hide_empty' => true,
            ]));
        }

        $search_filters = new HvnlyNab\Frontend\ViewModels\SearchFilters();
        return $search_filters->get_cached_terms($taxonomy, $args);
    }
}

/**
 * Global cache gate — single source of truth for listing cache behavior.
 *
 * @return bool
 */
if (!function_exists('hvnly_is_cache_enabled')) {
    function hvnly_is_cache_enabled()
    {
        return (bool) get_option('hvnly_cache_enabled', 0);
    }
}

/**
 * Persist cache enabled state to the canonical option and performance settings group.
 *
 * @param bool $enabled Whether cache is enabled.
 * @return bool Normalized enabled state.
 */
if (!function_exists('hvnly_sync_cache_enabled_setting')) {
    function hvnly_sync_cache_enabled_setting($enabled)
    {
        $enabled = ! empty($enabled);
        update_option('hvnly_cache_enabled', $enabled ? 1 : 0, false);

        $all_settings = get_option('hvnly_plugin_settings', array());
        if (! is_array($all_settings)) {
            $all_settings = array();
        }
        if (! isset($all_settings['performance']) || ! is_array($all_settings['performance'])) {
            $all_settings['performance'] = array();
        }
        $all_settings['performance']['hvnly_cache_enabled'] = $enabled;
        update_option('hvnly_plugin_settings', $all_settings);

        return $enabled;
    }
}

/**
 * Load plugin settings with legacy hvnly_settings merged (canonical keys win).
 *
 * @return array<string, mixed>
 */
if ( ! function_exists( 'hvnly_get_plugin_settings' ) ) {
    function hvnly_get_plugin_settings(): array {
        if ( function_exists( 'hvnly_maybe_migrate_legacy_settings' ) ) {
            return hvnly_maybe_migrate_legacy_settings();
        }

        $settings = get_option( 'hvnly_plugin_settings', array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        $legacy = get_option( 'hvnly_settings', array() );
        if ( is_array( $legacy ) && ! empty( $legacy ) ) {
            if ( function_exists( 'hvnly_deep_merge_settings' ) ) {
                $settings = hvnly_deep_merge_settings( $legacy, $settings );
            } else {
                $settings = array_replace_recursive( $legacy, $settings );
            }
        }
        return $settings;
    }
}

/**
 * Map flat setting keys to their settings group (from schema defaults).
 *
 * @return array<string, string>
 */
if ( ! function_exists( 'hvnly_settings_key_group_index' ) ) {
    function hvnly_settings_key_group_index(): array {
        static $index = null;
        if ( null !== $index ) {
            return $index;
        }

        $index = array();
        if ( class_exists( '\HvnlyNab\Api\Type\Settings\DefaultSettingsData' ) ) {
            $defaults = \HvnlyNab\Api\Type\Settings\DefaultSettingsData::get_default_settings();
            foreach ( $defaults as $group => $keys ) {
                if ( ! is_array( $keys ) ) {
                    continue;
                }
                foreach ( array_keys( $keys ) as $setting_key ) {
                    $index[ $setting_key ] = $group;
                }
            }
        }

        return $index;
    }
}

/**
 * Normalize legacy hvnly_settings into grouped hvnly_plugin_settings shape.
 *
 * @param array<string, mixed> $legacy Legacy option value.
 * @return array<string, mixed>
 */
if ( ! function_exists( 'hvnly_normalize_legacy_settings' ) ) {
    function hvnly_normalize_legacy_settings( array $legacy ): array {
        $valid_groups = array(
            'general',
            'design',
            'search',
            'properties',
            'shortcodes',
            'property-details',
            'preloader',
            'map',
            'performance',
        );

        foreach ( $valid_groups as $group ) {
            if ( isset( $legacy[ $group ] ) && is_array( $legacy[ $group ] ) ) {
                return $legacy;
            }
        }

        $grouped = array();
        $index   = hvnly_settings_key_group_index();

        foreach ( $legacy as $key => $value ) {
            if ( ! is_string( $key ) ) {
                continue;
            }
            if ( in_array( $key, $valid_groups, true ) && is_array( $value ) ) {
                $grouped[ $key ] = $value;
                continue;
            }
            $group = $index[ $key ] ?? 'general';
            if ( ! isset( $grouped[ $group ] ) || ! is_array( $grouped[ $group ] ) ) {
                $grouped[ $group ] = array();
            }
            $grouped[ $group ][ $key ] = $value;
        }

        return $grouped;
    }
}

/**
 * One-time migration: hvnly_settings → hvnly_plugin_settings.
 *
 * Runs before defaults are persisted so existing user configuration is preserved.
 *
 * @return array<string, mixed> Canonical settings after migration.
 */
if ( ! function_exists( 'hvnly_maybe_migrate_legacy_settings' ) ) {
    function hvnly_maybe_migrate_legacy_settings(): array {
        static $resolved = null;
        if ( null !== $resolved ) {
            return $resolved;
        }

        $canonical = get_option( 'hvnly_plugin_settings', array() );
        if ( ! is_array( $canonical ) ) {
            $canonical = array();
        }

        if ( get_option( 'hvnly_legacy_settings_migrated', false ) ) {
            $resolved = $canonical;
            return $resolved;
        }

        $legacy = get_option( 'hvnly_settings', array() );
        if ( ! is_array( $legacy ) || empty( $legacy ) ) {
            update_option( 'hvnly_legacy_settings_migrated', 1, false );
            $resolved = $canonical;
            return $resolved;
        }

        $normalized = hvnly_normalize_legacy_settings( $legacy );

        if ( empty( $canonical ) ) {
            $merged = $normalized;
        } elseif ( function_exists( 'hvnly_deep_merge_settings' ) ) {
            $merged = hvnly_deep_merge_settings( $normalized, $canonical );
        } else {
            $merged = array_replace_recursive( $normalized, $canonical );
        }

        update_option( 'hvnly_plugin_settings', $merged, false );
        update_option( 'hvnly_legacy_settings_migrated', 1, false );

        if ( function_exists( 'hvnly_debug_log' ) ) {
            hvnly_debug_log( 'Migrated legacy hvnly_settings into hvnly_plugin_settings', 'Settings' );
        }

        $resolved = $merged;
        return $resolved;
    }
}

add_action(
    'init',
    static function () {
        if ( function_exists( 'hvnly_maybe_migrate_legacy_settings' ) ) {
            hvnly_maybe_migrate_legacy_settings();
        }
    },
    1
);

/**
 * Clear all Havenlytics cache
 *
 * @return void
 */
function hvnly_clear_all_cache()
{
    if (!class_exists('HvnlyNab\Frontend\ViewModels\SearchFilters')) {
        return;
    }

    $search_filters = new HvnlyNab\Frontend\ViewModels\SearchFilters();
    $search_filters->clear_all_search_cache();

    do_action('hvnly_cache_cleared');
}

/**
 * Check if transient caching is available
 *
 * @return bool
 */
if (!function_exists('hvnly_transients_available')) {
    function hvnly_transients_available()
    {
        return HVNLY_NAB()->transients_available();
    }
}

/**
 * Utility function to get cache statistics
 *
 * @since 2.0.0
 * @return array
 */
if (!function_exists('hvnly_get_cache_stats')) {
    function hvnly_get_cache_stats()
    {
        return HVNLY_NAB()->get_cache_stats();
    }
}
/**
 * Utility function to clear Havenlytics cache
 *
 * @since 2.0.0
 * @return void
 */
if (!function_exists('hvnly_clear_cache')) {
    function hvnly_clear_cache()
    {
        HVNLY_NAB()->clear_cache();
    }
}
/**
 * Check if it's a property archive
 */
if (!function_exists('hvnly_is_property_archive')) {
    function hvnly_is_property_archive()
    {
        $post_type = 'hvnly_property';

        // Get the queried object
        $queried_object = get_queried_object();

        // Check if it's a post type archive
        if (is_post_type_archive($post_type)) {
            return true;
        }

        // Check if it's a taxonomy archive (dynamically)
        if (is_tax() && isset($queried_object->taxonomy)) {
            $taxonomy = get_taxonomy($queried_object->taxonomy);
            $allowed_taxonomies = ['hvnly_prop_types', 'hvnly_prop_depts', 'hvnly_prop_locations'];

            // Allow for translated taxonomy slugs
            if (in_array($taxonomy->name, $allowed_taxonomies, true)) {
                return true;
            }
        }

        // Check if it's an author property archive
        if (function_exists('hvnly_is_author_property_archive') && hvnly_is_author_property_archive()) {
            return true;
        }

        return false;
    }
}

/**
 * Check if it's an author property archive
 */
if (!function_exists('hvnly_is_author_property_archive')) {
    function hvnly_is_author_property_archive()
    {
        $is_properties = isset( $_GET['properties'] )
            ? sanitize_text_field( wp_unslash( $_GET['properties'] ) )
            : '';

        if (is_author() && (($is_properties === 'yes') || (get_post_type() === 'hvnly_property'))) {
            return true;
        }
        return false;
    }
}

/**
 * Get common property meta
 */
if (!function_exists('hvnly_get_common_property_meta')) {
    function hvnly_get_common_property_meta($post_id, $metakey, $single = true)
    {
        return get_post_meta($post_id, $metakey, $single);
    }
}

/**
 * Get property type info
 */
if (!function_exists('hvnly_get_property_type_info')) {
    function hvnly_get_property_type_info($post_id, $entity)
    {
        $property_type = get_post_meta($post_id, 'hvnly_property_type', true);
        $property_type_name = get_term((int) $property_type)->name;

        if ($entity === 'name') {
            return $property_type_name;
        } else {
            $property_type_icon = get_term_meta((int) $property_type, 'hvnly_term_icon', true);
            return $property_type_icon;
        }
    }
}

/**
 * Render repeated taxonomy terms
 */
if (!function_exists('hvnly_render_repeated_tax')) {
    function hvnly_render_repeated_tax($post_id, $tax = 'hvnly_prop_locations', $display_item = 2)
    {
        $terms = get_the_terms($post_id, $tax) ? get_the_terms($post_id, $tax) : array();
        return array_slice($terms, 0, $display_item);
    }
}

/**
 * Get property feature image
 */
if (!function_exists('hvnly_get_property_feature')) {
    function hvnly_get_property_feature($post_id)
    {
        $feature_image = get_the_post_thumbnail_url($post_id);
        if ($feature_image) {
            return $feature_image;
        }
    }
}

/**
 * Get terms with arguments
 */
if (!function_exists('hvnly_get_terms')) {
    function hvnly_get_terms($taxonomy, $args = array())
    {
        return get_terms(
            array_merge(
                array(
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => true,
                ),
                $args
            )
        );
    }
}

/**
 * Property type navigation URL
 */
if (!function_exists('hvnly_property_type_nav_url')) {
    function hvnly_property_type_nav_url($type = 'all', $base_url = null)
    {
        if (empty($base_url)) {
            $base_url = remove_query_arg(array('page', 'paged'));
            $base_url = preg_replace('~/page/(\d+)/?~', '', $base_url);
            $base_url = preg_replace('~/paged/(\d+)/?~', '', $base_url);
        }

        $url = add_query_arg(array('property_type' => $type), $base_url);

        return $url;
    }
}



/**
 * Get search page URL
 */
if (!function_exists('hvnly_get_search_page_url')) {
    function hvnly_get_search_page_url()
    {
        return get_post_type_archive_link('hvnly_property');
    }
}





/**
 * Get property author URL
 */
if (!function_exists('hvnly_property_author_url')) {
    function hvnly_property_author_url($author_id, $post_type = 'hvnly_property')
    {
        // You can customize this based on your URL structure
        return get_author_posts_url($author_id);
    }
}



/**
 * Get AJAX URL
 */
if (!function_exists('hvnly_get_ajax_url')) {
    function hvnly_get_ajax_url()
    {
        return admin_url('admin-ajax.php');
    }
}


/**
 * Check if top search bar should be displayed
 *
 * @return bool
 * @since 2.0.3
 */
if (!function_exists('hvnly_should_show_top_search_bar')) {
    function hvnly_should_show_top_search_bar() {
        // Only check on property archive
        if (!is_post_type_archive('hvnly_property') && !function_exists('hvnly_page_has_property_shortcode')) {
            return true;
        }
        
        if (function_exists('hvnly_page_has_property_shortcode') && !is_post_type_archive('hvnly_property') && !hvnly_page_has_property_shortcode()) {
            return true;
        }
        
        if (class_exists('HvnlyNab\Core\SettingsManager')) {
            $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
            $search_settings = $settings_manager->get_search_settings();
            
            if (isset($search_settings['hvnly_hideTopSearchBar'])) {
                $is_hidden = $search_settings['hvnly_hideTopSearchBar'];
                $is_hidden = filter_var($is_hidden, FILTER_VALIDATE_BOOLEAN);
                return !$is_hidden;
            }
        }
        
        return true;
    }
}
/**
 * Check if left filter sidebar should be displayed
 *
 * @return bool
 * @since 2.0.3
 */
function hvnly_should_show_filter_sidebar() {
    // Only check on property archive
    if (!is_post_type_archive('hvnly_property') && !hvnly_page_has_property_shortcode()) {
        return true;
    }
    
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        $search_settings = $settings_manager->get_search_settings();
        
        // Check if hide left search bar is enabled (true means hide, so we return false)
        if (isset($search_settings['hvnly_hideLeftSearchBar'])) {
            $is_hidden = $search_settings['hvnly_hideLeftSearchBar'];
            // Convert to boolean properly
            $is_hidden = filter_var($is_hidden, FILTER_VALIDATE_BOOLEAN);
            return !$is_hidden;
        }
    }
    
    return true;
}

/**
 * Get search button text from settings
 *
 * @return string
 * @since 2.0.3
 */
function hvnly_get_search_button_text() {
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        $search_settings = $settings_manager->get_search_settings();
        
        if (isset($search_settings['hvnly_searchButtonText']) && !empty($search_settings['hvnly_searchButtonText'])) {
            return $search_settings['hvnly_searchButtonText'];
        }
    }
    return __('Filter', 'havenlytics');
}


/**
 * Get search filter fields from settings
 *
 * @return array
 * @since 2.0.3
 */
function hvnly_get_search_filter_fields() {
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        $search_settings = $settings_manager->get_search_settings();
        
        // Check both possible keys
        if (isset($search_settings['hvnly_search_fields']) && !empty($search_settings['hvnly_search_fields'])) {
            return $search_settings['hvnly_search_fields'];
        }
        if (isset($search_settings['search_fields']) && !empty($search_settings['search_fields'])) {
            return $search_settings['search_fields'];
        }
    }
    return hvnly_get_default_search_filter_fields();
}

/**
 * Get default search filter fields
 *
 * @return array
 * @since 2.0.3
 */
function hvnly_get_default_search_filter_fields() {
    return [
        ['id' => 'price', 'title' => __('Price', 'havenlytics'), 'type' => 'range', 'enabled' => true, 'order' => 1, 'is_default' => true],
        ['id' => 'status', 'title' => __('Status', 'havenlytics'), 'type' => 'dropdown', 'enabled' => true, 'order' => 2, 'is_default' => true],
        ['id' => 'bedrooms_bathrooms', 'title' => __('Bedrooms & Bathrooms', 'havenlytics'), 'type' => 'group', 'enabled' => true, 'order' => 3, 'is_default' => true],
        ['id' => 'reception_rooms', 'title' => __('Reception Rooms', 'havenlytics'), 'type' => 'number', 'enabled' => true, 'order' => 4, 'is_default' => true],
        ['id' => 'property_types', 'title' => __('Property Types', 'havenlytics'), 'type' => 'checkbox', 'enabled' => true, 'order' => 5, 'is_default' => true],
        ['id' => 'locations', 'title' => __('Locations', 'havenlytics'), 'type' => 'dropdown', 'enabled' => true, 'order' => 6, 'is_default' => true],
        ['id' => 'features', 'title' => __('Features', 'havenlytics'), 'type' => 'checkbox', 'enabled' => true, 'order' => 7, 'is_default' => true],
        ['id' => 'tags', 'title' => __('Tags', 'havenlytics'), 'type' => 'dropdown', 'enabled' => true, 'order' => 8, 'is_default' => true],
        ['id' => 'badges', 'title' => __('Badges', 'havenlytics'), 'type' => 'checkbox', 'enabled' => true, 'order' => 9, 'is_default' => true],
        ['id' => 'property_id', 'title' => __('Property ID', 'havenlytics'), 'type' => 'text', 'enabled' => true, 'order' => 10, 'is_default' => true]
    ];
}

/**
 * Get unique property IDs for filter
 *
 * @return array
 * @since 2.0.3
 */
function hvnly_get_unique_property_ids() {
    global $wpdb;
    
    $cache_key = 'hvnly_unique_property_ids';
    $property_ids = get_transient($cache_key);
    
    if (false === $property_ids) {
        $property_ids = $wpdb->get_col(
            "SELECT DISTINCT pm.meta_value 
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_hvnly_unique_property_id'
             AND p.post_type = 'hvnly_property'
             AND p.post_status = 'publish'
             AND pm.meta_value IS NOT NULL
             AND pm.meta_value != ''
             ORDER BY CAST(pm.meta_value AS UNSIGNED)"
        );
        
        set_transient($cache_key, $property_ids, 12 * HOUR_IN_SECONDS);
    }
    
    return $property_ids;
}

/**
 * Get default sort option from settings
 *
 * @return string
 * @since 2.0.3
 */
function hvnly_get_default_sort_option() {
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        $search_settings = $settings_manager->get_search_settings();
        
        if (isset($search_settings['hvnly_propertySortByType']) && !empty($search_settings['hvnly_propertySortByType'])) {
            return $search_settings['hvnly_propertySortByType'];
        }
    }
    return 'date'; // Default to Newest
}

/**
 * Get default view option from settings
 *
 * @return string
 * @since 2.0.3
 */
function hvnly_get_default_view_option() {
    global $hvnly_elementor_widget_active, $hvnly_elementor_widget_settings;

    if (
        ! empty( $hvnly_elementor_widget_active )
        && ! empty( $hvnly_elementor_widget_settings['default_view'] )
    ) {
        $widget_view = strtolower( (string) $hvnly_elementor_widget_settings['default_view'] );
        if ( in_array( $widget_view, array( 'grid', 'list', 'map' ), true ) ) {
            return $widget_view;
        }
    }

    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        $search_settings = $settings_manager->get_search_settings();
        
        if (isset($search_settings['hvnly_propertyViewType']) && !empty($search_settings['hvnly_propertyViewType'])) {
            $view = strtolower($search_settings['hvnly_propertyViewType']);
            // Map the values to match frontend
            if ($view === 'grid') return 'grid';
            if ($view === 'list') return 'list';
            if ($view === 'map') return 'map';
            return 'grid';
        }
    }
    return 'grid'; // Default to Grid View
}

/**
 * Get AJAX Load More button text from settings
 *
 * @return string
 * @since 2.0.3
 */
function hvnly_get_ajax_load_more_button_text() {
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        $search_settings = $settings_manager->get_search_settings();
        
        if (isset($search_settings['hvnly_AjaxLoadMoreButtonText']) && !empty($search_settings['hvnly_AjaxLoadMoreButtonText'])) {
            return $search_settings['hvnly_AjaxLoadMoreButtonText'];
        }
    }
    return __('Load More Properties', 'havenlytics');
}

/**
 * Get number of properties to display per page from settings
 *
 * @return int
 * @since 2.0.3
 */
function hvnly_get_properties_per_page() {
    if (class_exists('HvnlyNab\Core\SettingsManager')) {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        return $settings_manager->get_properties_per_page();
    }
    return 12;
}

/**
 * CSS class for search/archive filter sidebar position (LEFT default, RIGHT optional).
 *
 * @return string
 * @since 3.1.1
 */
function hvnly_get_search_sidebar_layout_class() {
    if ( ! class_exists( 'HvnlyNab\Core\SettingsManager' ) ) {
        return '';
    }

    $layout = \HvnlyNab\Core\SettingsManager::get_instance()->get_search_sidebar_layout();

    return 'RIGHT' === $layout ? 'hvnly-search-sidebar-right' : '';
}


/**
 * Modify main query to use custom properties per page setting
 *
 * @param WP_Query $query The WordPress query object.
 * @return void
 */
function hvnly_modify_property_archive_posts_per_page($query) {
    if (!is_admin() && $query->is_main_query() && function_exists('hvnly_is_property_archive')) {
        if (hvnly_is_property_archive()) {
            $properties_per_page = function_exists('hvnly_get_properties_per_page') 
                ? hvnly_get_properties_per_page() 
                : 12;
            
            $query->set('posts_per_page', $properties_per_page);
        }
    }
}
add_action('pre_get_posts', 'hvnly_modify_property_archive_posts_per_page', 20);



/**
 * Check if page has property shortcode (fallback for Elementor)
 * 
 * @since 2.2.1
 * @return bool
 */
if (!function_exists('hvnly_page_has_property_shortcode')) {
    function hvnly_page_has_property_shortcode() {
        global $post;
        
        if (!$post || !isset($post->post_content)) {
            return false;
        }
        
        $property_shortcodes = [
            'hvnly_property_grid',
            'hvnly_property_list', 
            'hvnly_property_search',
            'hvnly_featured_properties',
            'hvnly_properties'
        ];
        
        foreach ($property_shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }
        
        return false;
    }
}

/**
 * Whether the current user has saved a property.
 *
 * Backed by one cached query per request, so calling this once per card on a
 * full archive page costs a single database round trip — not one per card.
 *
 * @since 3.4.0
 *
 * @param int|null $property_id Property id. Defaults to the current post.
 * @return bool
 */
if (!function_exists('hvnly_is_property_favorited')) {
    function hvnly_is_property_favorited($property_id = null) {
        if (!is_user_logged_in()) {
            return false;
        }

        $property_id = $property_id ? absint($property_id) : (int) get_the_ID();
        if ($property_id <= 0) {
            return false;
        }

        if (!class_exists('\HvnlyNab\Favorites\FavoritesSchema')
            || !\HvnlyNab\Favorites\FavoritesSchema::table_exists()) {
            return false;
        }

        static $repository = null;
        if (null === $repository) {
            $repository = new \HvnlyNab\Favorites\FavoritesRepository();
        }

        return $repository->exists(get_current_user_id(), $property_id);
    }
}

/**
 * Whether saved properties (favorites) are available on this site.
 *
 * @since 3.4.0
 *
 * @return bool
 */
if (!function_exists('hvnly_is_favorites_enabled')) {
    function hvnly_is_favorites_enabled() {
        $enabled = class_exists('\HvnlyNab\Favorites\FavoritesSchema');

        /**
         * Filter whether the favorites feature is active.
         *
         * @since 3.4.0
         *
         * @param bool $enabled Whether favorites are enabled.
         */
        return (bool) apply_filters('hvnly_favorites_enabled', $enabled);
    }
}

/**
 * Presentation data the Favorite toast needs for a property.
 *
 * Rendered into the favorite button as data attributes so the toast can show
 * the thumbnail and title with no extra request — and so it works for guests,
 * who never call the REST API at all.
 *
 * @since 3.4.0
 *
 * @param int|null $property_id Property id. Defaults to the current post.
 * @return array{title:string,thumb:string}
 */
if (!function_exists('hvnly_get_favorite_toast_data')) {
    function hvnly_get_favorite_toast_data($property_id = null) {
        $property_id = $property_id ? absint($property_id) : (int) get_the_ID();

        if ($property_id <= 0) {
            return array('title' => '', 'thumb' => '');
        }

        $thumb = get_the_post_thumbnail_url($property_id, 'thumbnail');

        $data = array(
            'title' => (string) get_the_title($property_id),
            'thumb' => is_string($thumb) ? $thumb : '',
        );

        /**
         * Filter the Favorite toast presentation data.
         *
         * @since 3.4.0
         *
         * @param array $data        Title + thumbnail URL.
         * @param int   $property_id Property id.
         */
        return (array) apply_filters('hvnly_favorite_toast_data', $data, $property_id);
    }
}
