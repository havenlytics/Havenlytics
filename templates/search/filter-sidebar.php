<?php
/**
 * Filter Sidebar Template
 *
 * Displays a dynamic property filter sidebar with configurable search fields.
 * Supports various field types including range sliders, dropdowns, checkboxes,
 * and text inputs for filtering property listings.
 *
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/search/filter-sidebar.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/search/filter-sidebar.php
 * 3. Modify the copied file to customize the filter sidebar display
 *
 * @package     Havenlytics\Templates
 * @version     2.2.0
 * @since       2.0.0
 */

// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if the filter sidebar should be displayed.
 *
 * This conditional check ensures the sidebar only appears on
 * pages where property filtering is needed (e.g., property listings).
 */
if ( ! hvnly_filter_sidebar_should_show() ) {
	return;
}

// =============================================================================
// DATA RETRIEVAL & INITIALIZATION
// =============================================================================

/**
 * Get configured search fields and current filter values.
 *
 * @var array $hvnly_search_fields   Dynamic fields configured in plugin settings.
 * @var array $hvnly_current_filters Currently active filter values from URL/request.
 */
$hvnly_search_fields   = hvnly_filter_sidebar_get_fields();
$hvnly_current_filters = hvnly_filter_sidebar_get_current_values();

/**
 * Initialize filter variables with defaults.
 *
 * Prevents undefined variable warnings by setting default empty values
 * when no filters are currently active.
 */
$hvnly_current_min_price        = isset( $hvnly_current_filters['min_price'] ) ? $hvnly_current_filters['min_price'] : '';
$hvnly_current_max_price        = isset( $hvnly_current_filters['max_price'] ) ? $hvnly_current_filters['max_price'] : '';
$hvnly_current_bedrooms         = isset( $hvnly_current_filters['bedrooms'] ) ? $hvnly_current_filters['bedrooms'] : '';
$hvnly_current_bathrooms        = isset( $hvnly_current_filters['bathrooms'] ) ? $hvnly_current_filters['bathrooms'] : '';
$hvnly_current_reception_rooms  = isset( $hvnly_current_filters['reception_rooms'] ) ? $hvnly_current_filters['reception_rooms'] : '';

/**
 * Fallback to default search fields if none are configured.
 *
 * Uses helper class to provide sensible defaults when plugin settings
 * haven't been saved or are empty.
 */
if ( empty( $hvnly_search_fields ) ) {
	$hvnly_search_fields = HvnlyNab\Helpers::get_instance()->hvnly_get_default_search_filter_fields();
}

// =============================================================================
// FIELD FILTERING & SORTING
// =============================================================================

/**
 * Filter and sort enabled search fields.
 *
 * Only includes fields marked as enabled in settings and sorts them
 * by their configured order (default 999 for unset order values).
 *
 * @var array $hvnly_enabled_fields Sorted array of enabled filter fields.
 */
$hvnly_enabled_fields = array_filter(
	$hvnly_search_fields,
	function( $field ) {
		return isset( $field['enabled'] ) && true === $field['enabled'];
	}
);

usort(
	$hvnly_enabled_fields,
	function( $a, $b ) {
		return ( $a['order'] ?? 999 ) - ( $b['order'] ?? 999 );
	}
);

// =============================================================================
// TAXONOMY TERMS RETRIEVAL
// =============================================================================

/**
 * Resolve taxonomy slug for the Status sidebar field.
 *
 * @return string
 */
$hvnly_status_taxonomy = hvnly_filter_sidebar_get_field_taxonomy( 'status' );
if ( empty( $hvnly_status_taxonomy ) || ! taxonomy_exists( $hvnly_status_taxonomy ) ) {
	$hvnly_status_taxonomy = 'hvnly_prop_status';
}

/**
 * Retrieve all property taxonomy terms.
 *
 * These terms are used to populate filter options for various
 * property classifications including status, type, location, etc.
 *
 * @var array|WP_Error $hvnly_property_status Property status terms.
 * @var array|WP_Error $hvnly_prop_types      Property type terms.
 * @var array|WP_Error $hvnly_locations       Property location terms.
 * @var array|WP_Error $hvnly_features        Property feature terms.
 * @var array|WP_Error $hvnly_tags            Property tag terms.
 * @var array|WP_Error $hvnly_badges          Property badge terms.
 */
$hvnly_property_status = get_terms(
	array(
		'taxonomy'   => $hvnly_status_taxonomy,
		'hide_empty' => true,
	)
);

$hvnly_prop_types = get_terms(
	array(
		'taxonomy'   => 'hvnly_prop_types',
		'hide_empty' => true,
	)
);

$hvnly_locations = get_terms(
	array(
		'taxonomy'   => 'hvnly_prop_locations',
		'hide_empty' => true,
	)
);

$hvnly_features = get_terms(
	array(
		'taxonomy'   => 'hvnly_prop_features',
		'hide_empty' => true,
	)
);

$hvnly_tags = get_terms(
	array(
		'taxonomy'   => 'hvnly_prop_tags',
		'hide_empty' => true,
	)
);

$hvnly_badges = get_terms(
	array(
		'taxonomy'   => 'hvnly_prop_badges',
		'hide_empty' => true,
	)
);

// =============================================================================
// CURRENT FILTER VALUES FOR TAXONOMIES
// =============================================================================

/**
 * Extract currently selected taxonomy filter values.
 *
 * Ensures values are cast to arrays for consistent handling,
 * even when no values are selected (empty array default).
 *
 * @var array $hvnly_current_property_types  Selected property types.
 * @var array $hvnly_current_locations       Selected locations.
 * @var array $hvnly_current_features        Selected features.
 * @var array $hvnly_current_tags            Selected tags.
 * @var array $hvnly_current_badges          Selected badges.
 * @var array $hvnly_current_property_status Selected property statuses.
 * @var array $hvnly_current_property_ids    Selected property IDs.
 */
$hvnly_current_property_types   = isset( $hvnly_current_filters['hvnly_prop_types'] ) ? (array) $hvnly_current_filters['hvnly_prop_types'] : array();
$hvnly_current_locations        = isset( $hvnly_current_filters['hvnly_prop_locations'] ) ? (array) $hvnly_current_filters['hvnly_prop_locations'] : array();
$hvnly_current_features         = isset( $hvnly_current_filters['hvnly_prop_features'] ) ? (array) $hvnly_current_filters['hvnly_prop_features'] : array();
$hvnly_current_tags             = isset( $hvnly_current_filters['hvnly_prop_tags'] ) ? (array) $hvnly_current_filters['hvnly_prop_tags'] : array();
$hvnly_current_badges           = isset( $hvnly_current_filters['hvnly_prop_badges'] ) ? (array) $hvnly_current_filters['hvnly_prop_badges'] : array();
$hvnly_current_property_status  = isset( $hvnly_current_filters[ $hvnly_status_taxonomy ] ) ? (array) $hvnly_current_filters[ $hvnly_status_taxonomy ] : array();
$hvnly_current_property_ids     = isset( $hvnly_current_filters['property_ids'] ) ? (array) $hvnly_current_filters['property_ids'] : array();

/**
 * Get unique property IDs for the property ID filter.
 *
 * Used in the text field type to display available property IDs
 * as selectable checkboxes.
 *
 * @var array $hvnly_property_ids Array of unique property IDs.
 */
$hvnly_property_ids = hvnly_filter_sidebar_get_unique_property_ids();

?>

<!--
  Property Filter Sidebar
  ============================================================================
  Dynamic sidebar component that renders configurable filter fields based on
  plugin settings. Supports the following field types:
  - range: Min/Max price selectors
  - dropdown: Single-select taxonomy filters (as checkboxes for multi-select)
  - checkbox: Multi-select taxonomy filters
  - group: Combined fields (e.g., Bedrooms & Bathrooms)
  - number: Numeric range filters (e.g., Reception Rooms)
  - text: Text-based filters (e.g., Property ID checkboxes)
-->

<aside class="hvnly-property-filter-sidebar" id="hvnly-filter-sidebar">
    <!-- Filter Header -->
    <div class="hvnly-property-filter-header">
        <h3 class="hvnly-property-filter-title"><?php esc_html_e( 'Filters', 'havenlytics' ); ?></h3>
        <button type="button" class="hvnly-property-reset-filters-btn"
            style="font-size: 0.875rem; color: var(--hvnly-brand-primary);">
            <?php esc_html_e( 'Clear All', 'havenlytics' ); ?>
        </button>
    </div>

    <?php
	/**
	 * Render each enabled filter field.
	 *
	 * Iterates through sorted enabled fields and renders the appropriate
	 * HTML based on the field type configuration.
	 *
	 * @var array $hvnly_field Current field configuration.
	 */
	foreach ( $hvnly_enabled_fields as $hvnly_field ) :
		$hvnly_field_id    = $hvnly_field['id'];
		$hvnly_field_title = $hvnly_field['title'];
		$hvnly_field_type  = $hvnly_field['type'];

		/**
		 * Switch between different field types.
		 *
		 * Each case handles a specific field type with its unique
		 * rendering logic and data sources.
		 */
		switch ( $hvnly_field_type ) :
			// =================================================================
			// RANGE FIELD TYPE - Price Range Selectors
			// =================================================================
			case 'range':
				?>
    <div class="hvnly-property-filter-group collapsed">
        <div class="hvnly-property-filter-group-header">
            <h4 class="hvnly-property-filter-group-title"><?php echo esc_html( $hvnly_field_title ); ?></h4>
            <div class="hvnly-property-filter-group-toggle">
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div class="hvnly-property-filter-group-content">
            <div style="display: flex; gap: 1rem;">
                <!-- Minimum Price Selector -->
                <div style="flex: 1;">
                    <label
                        class="hvnly-property-filter-label"><?php esc_html_e( 'Min Price', 'havenlytics' ); ?></label>
                    <select class="hvnly-property-form-select" name="min_price" data-filter="min_price">
                        <option value=""><?php echo esc_html( hvnly_get_search_field_placeholder( 'min_price', __( 'Any Min', 'havenlytics' ) ) ); ?></option>
                        <?php
									$hvnly_min_prices = hvnly_filter_sidebar_get_min_price_options();
									foreach ( $hvnly_min_prices as $hvnly_value => $hvnly_label ) :
										?>
                        <option value="<?php echo esc_attr( $hvnly_value ); ?>"
                            <?php selected( $hvnly_current_min_price, $hvnly_value ); ?>>
                            <?php echo esc_html( $hvnly_label ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Maximum Price Selector -->
                <div style="flex: 1;">
                    <label
                        class="hvnly-property-filter-label"><?php esc_html_e( 'Max Price', 'havenlytics' ); ?></label>
                    <select class="hvnly-property-form-select" name="max_price" data-filter="max_price">
                        <option value=""><?php echo esc_html( hvnly_get_search_field_placeholder( 'max_price', __( 'Any Max', 'havenlytics' ) ) ); ?></option>
                        <?php
									$hvnly_max_prices = hvnly_filter_sidebar_get_max_price_options();
									foreach ( $hvnly_max_prices as $hvnly_value => $hvnly_label ) :
										?>
                        <option value="<?php echo esc_attr( $hvnly_value ); ?>"
                            <?php selected( $hvnly_current_max_price, $hvnly_value ); ?>>
                            <?php echo esc_html( $hvnly_label ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <?php
				break;

			// =================================================================
			// DROPDOWN FIELD TYPE - Taxonomy Filters (Displayed as Checkboxes)
			// =================================================================
			case 'dropdown':
				// Property Status Filter.
				if ( 'status' === $hvnly_field_id && ! empty( $hvnly_property_status ) && ! is_wp_error( $hvnly_property_status ) ) :
					?>
    <div class="hvnly-property-filter-group collapsed">
        <div class="hvnly-property-filter-group-header">
            <h4 class="hvnly-property-filter-group-title"><?php echo esc_html( $hvnly_field_title ); ?></h4>
            <div class="hvnly-property-filter-group-toggle">
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div class="hvnly-property-filter-group-content">
            <div class="hvnly-property-checkbox-group">
                <?php foreach ( $hvnly_property_status as $hvnly_status ) : ?>
                <label class="hvnly-property-checkbox-label">
                    <div
                        class="hvnly-property-filter-checkbox <?php echo in_array( $hvnly_status->slug, (array) ( $hvnly_current_property_status ?? array() ), true ) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="<?php echo esc_attr( $hvnly_status_taxonomy ); ?>[]"
                            value="<?php echo esc_attr( $hvnly_status->slug ); ?>"
                            data-term-id="<?php echo esc_attr( $hvnly_status->term_id ); ?>"
                            data-filter="<?php echo esc_attr( $hvnly_status_taxonomy ); ?>"
                            <?php checked( in_array( $hvnly_status->slug, (array) ( $hvnly_current_property_status ?? array() ), true ) ); ?>>
                        <span><?php echo esc_html( $hvnly_status->name ); ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
				// Property Locations Filter.
				elseif ( 'locations' === $hvnly_field_id && ! empty( $hvnly_locations ) && ! is_wp_error( $hvnly_locations ) ) :
					?>
    <div class="hvnly-property-filter-group collapsed">
        <div class="hvnly-property-filter-group-header">
            <h4 class="hvnly-property-filter-group-title"><?php echo esc_html( $hvnly_field_title ); ?></h4>
            <div class="hvnly-property-filter-group-toggle">
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div class="hvnly-property-filter-group-content">
            <div class="hvnly-property-checkbox-group">
                <?php foreach ( $hvnly_locations as $hvnly_location ) : ?>
                <label class="hvnly-property-checkbox-label">
                    <div
                        class="hvnly-property-filter-checkbox <?php echo in_array( $hvnly_location->slug, (array) ( $hvnly_current_locations ?? array() ), true ) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="hvnly_prop_locations[]"
                            value="<?php echo esc_attr( $hvnly_location->slug ); ?>"
                            data-term-id="<?php echo esc_attr( $hvnly_location->term_id ); ?>"
                            data-filter="hvnly_prop_locations"
                            <?php checked( in_array( $hvnly_location->slug, (array) ( $hvnly_current_locations ?? array() ), true ) ); ?>>
                        <span><?php echo esc_html( $hvnly_location->name ); ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
				// Property Tags Filter.
				elseif ( 'tags' === $hvnly_field_id && ! empty( $hvnly_tags ) && ! is_wp_error( $hvnly_tags ) ) :
					?>
    <div class="hvnly-property-filter-group collapsed">
        <div class="hvnly-property-filter-group-header">
            <h4 class="hvnly-property-filter-group-title"><?php echo esc_html( $hvnly_field_title ); ?></h4>
            <div class="hvnly-property-filter-group-toggle">
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div class="hvnly-property-filter-group-content">
            <div class="hvnly-property-checkbox-group">
                <?php foreach ( $hvnly_tags as $hvnly_tag ) : ?>
                <label class="hvnly-property-checkbox-label">
                    <div
                        class="hvnly-property-filter-checkbox <?php echo in_array( $hvnly_tag->slug, (array) ( $hvnly_current_tags ?? array() ), true ) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="hvnly_prop_tags[]"
                            value="<?php echo esc_attr( $hvnly_tag->slug ); ?>"
                            data-term-id="<?php echo esc_attr( $hvnly_tag->term_id ); ?>" data-filter="hvnly_prop_tags"
                            <?php checked( in_array( $hvnly_tag->slug, (array) ( $hvnly_current_tags ?? array() ), true ) ); ?>>
                        <span><?php echo esc_html( $hvnly_tag->name ); ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
				endif;
				break;

			// =================================================================
			// CHECKBOX FIELD TYPE - Multi-select Taxonomy Filters
			// =================================================================
			case 'checkbox':
				// Property Types Filter.
				if ( 'property_types' === $hvnly_field_id && ! empty( $hvnly_prop_types ) && ! is_wp_error( $hvnly_prop_types ) ) :
					?>
    <div class="hvnly-property-filter-group collapsed">
        <div class="hvnly-property-filter-group-header">
            <h4 class="hvnly-property-filter-group-title"><?php echo esc_html( $hvnly_field_title ); ?></h4>
            <div class="hvnly-property-filter-group-toggle">
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div class="hvnly-property-filter-group-content">
            <div class="hvnly-property-checkbox-group">
                <?php foreach ( $hvnly_prop_types as $hvnly_type ) : ?>
                <label class="hvnly-property-checkbox-label">
                    <div
                        class="hvnly-property-filter-checkbox <?php echo in_array( $hvnly_type->slug, (array) ( $hvnly_current_property_types ?? array() ), true ) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="hvnly_prop_types[]"
                            value="<?php echo esc_attr( $hvnly_type->slug ); ?>"
                            data-term-id="<?php echo esc_attr( $hvnly_type->term_id ); ?>"
                            data-filter="hvnly_prop_types"
                            <?php checked( in_array( $hvnly_type->slug, (array) ( $hvnly_current_property_types ?? array() ), true ) ); ?>>
                        <span><?php echo esc_html( $hvnly_type->name ); ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
				// Property Features Filter.
				elseif ( 'features' === $hvnly_field_id && ! empty( $hvnly_features ) && ! is_wp_error( $hvnly_features ) ) :
					?>
    <div class="hvnly-property-filter-group collapsed">
        <div class="hvnly-property-filter-group-header">
            <h4 class="hvnly-property-filter-group-title"><?php echo esc_html( $hvnly_field_title ); ?></h4>
            <div class="hvnly-property-filter-group-toggle">
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div class="hvnly-property-filter-group-content">
            <div class="hvnly-property-checkbox-group">
                <?php foreach ( $hvnly_features as $hvnly_feature ) : ?>
                <label class="hvnly-property-checkbox-label">
                    <div
                        class="hvnly-property-filter-checkbox <?php echo in_array( $hvnly_feature->slug, (array) ( $hvnly_current_features ?? array() ), true ) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="hvnly_prop_features[]"
                            value="<?php echo esc_attr( $hvnly_feature->slug ); ?>"
                            data-term-id="<?php echo esc_attr( $hvnly_feature->term_id ); ?>"
                            data-filter="hvnly_prop_features"
                            <?php checked( in_array( $hvnly_feature->slug, (array) ( $hvnly_current_features ?? array() ), true ) ); ?>>
                        <span><?php echo esc_html( $hvnly_feature->name ); ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
				// Property Badges Filter.
				elseif ( 'badges' === $hvnly_field_id && ! empty( $hvnly_badges ) && ! is_wp_error( $hvnly_badges ) ) :
					?>
    <div class="hvnly-property-filter-group collapsed">
        <div class="hvnly-property-filter-group-header">
            <h4 class="hvnly-property-filter-group-title"><?php echo esc_html( $hvnly_field_title ); ?></h4>
            <div class="hvnly-property-filter-group-toggle">
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div class="hvnly-property-filter-group-content">
            <div class="hvnly-property-checkbox-group">
                <?php foreach ( $hvnly_badges as $hvnly_badge ) : ?>
                <label class="hvnly-property-checkbox-label">
                    <div
                        class="hvnly-property-filter-checkbox <?php echo in_array( $hvnly_badge->slug, (array) ( $hvnly_current_badges ?? array() ), true ) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="hvnly_prop_badges[]"
                            value="<?php echo esc_attr( $hvnly_badge->slug ); ?>"
                            data-term-id="<?php echo esc_attr( $hvnly_badge->term_id ); ?>"
                            data-filter="hvnly_prop_badges"
                            <?php checked( in_array( $hvnly_badge->slug, (array) ( $hvnly_current_badges ?? array() ), true ) ); ?>>
                        <span><?php echo esc_html( $hvnly_badge->name ); ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
				endif;
				break;

			// =================================================================
			// GROUP FIELD TYPE - Combined Related Filters
			// =================================================================
			case 'group':
				if ( 'bedrooms_bathrooms' === $hvnly_field_id ) :
					?>
    <div class="hvnly-property-filter-group collapsed">
        <div class="hvnly-property-filter-group-header">
            <h4 class="hvnly-property-filter-group-title"><?php echo esc_html( $hvnly_field_title ); ?></h4>
            <div class="hvnly-property-filter-group-toggle">
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div class="hvnly-property-filter-group-content">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <!-- Bedrooms Filter -->
                <div class="hvnly-property-filter-group collapsed">
                    <label class="hvnly-property-filter-label"><?php esc_html_e( 'Bedrooms', 'havenlytics' ); ?></label>
                    <select class="hvnly-property-form-select" name="bedrooms" data-filter="bedrooms">
                        <option value=""><?php echo esc_html( hvnly_get_search_field_placeholder( 'bedrooms', __( 'Any', 'havenlytics' ) ) ); ?></option>
                        <?php
										$hvnly_bedroom_options = hvnly_filter_sidebar_get_bedroom_options();
										foreach ( $hvnly_bedroom_options as $hvnly_option ) :
											?>
                        <option value="<?php echo esc_attr( $hvnly_option ); ?>"
                            <?php selected( $hvnly_current_bedrooms, $hvnly_option ); ?>>
                            <?php echo esc_html( $hvnly_option ); ?>+
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Bathrooms Filter -->
                <div class="hvnly-property-filter-group collapsed">
                    <label
                        class="hvnly-property-filter-label"><?php esc_html_e( 'Bathrooms', 'havenlytics' ); ?></label>
                    <select class="hvnly-property-form-select" name="bathrooms" data-filter="bathrooms">
                        <option value=""><?php echo esc_html( hvnly_get_search_field_placeholder( 'bathrooms', __( 'Any', 'havenlytics' ) ) ); ?></option>
                        <?php
										$hvnly_bathroom_options = hvnly_filter_sidebar_get_bathroom_options();
										foreach ( $hvnly_bathroom_options as $hvnly_option ) :
											?>
                        <option value="<?php echo esc_attr( $hvnly_option ); ?>"
                            <?php selected( $hvnly_current_bathrooms, $hvnly_option ); ?>>
                            <?php echo esc_html( $hvnly_option ); ?>+
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <?php
				endif;
				break;

			// =================================================================
			// NUMBER FIELD TYPE - Numeric Range Filter
			// =================================================================
			case 'number':
				if ( 'reception_rooms' === $hvnly_field_id ) :
					?>
    <div class="hvnly-property-filter-group collapsed">
        <div class="hvnly-property-filter-group-header">
            <h4 class="hvnly-property-filter-group-title"><?php echo esc_html( $hvnly_field_title ); ?></h4>
            <div class="hvnly-property-filter-group-toggle">
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div class="hvnly-property-filter-group-content">
            <div class="hvnly-property-checkbox-group">
                <select class="hvnly-property-form-select" name="reception_rooms" data-filter="reception_rooms">
                    <option value=""><?php echo esc_html( hvnly_get_search_field_placeholder( 'reception_rooms', __( 'Any', 'havenlytics' ) ) ); ?></option>
                    <?php
									$hvnly_reception_options = hvnly_filter_sidebar_get_reception_rooms_options();
									foreach ( $hvnly_reception_options as $hvnly_option ) :
										?>
                    <option value="<?php echo esc_attr( $hvnly_option ); ?>"
                        <?php selected( $hvnly_current_reception_rooms, $hvnly_option ); ?>>
                        <?php echo esc_html( $hvnly_option ); ?>+
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <?php
				endif;
				break;

			// =================================================================
			// TEXT FIELD TYPE - Property ID Checkboxes
			// =================================================================
			case 'text':
				if ( 'property_id' === $hvnly_field_id && ! empty( $hvnly_property_ids ) && ! is_wp_error( $hvnly_property_ids ) && count( $hvnly_property_ids ) > 0 ) :
					?>
    <div class="hvnly-property-filter-group collapsed">
        <div class="hvnly-property-filter-group-header">
            <h4 class="hvnly-property-filter-group-title"><?php echo esc_html( $hvnly_field_title ); ?></h4>
            <div class="hvnly-property-filter-group-toggle">
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        <div class="hvnly-property-filter-group-content">
            <!-- Scrollable container for property IDs -->
            <div class="hvnly-property-checkbox-group" style="max-height: 200px; overflow-y: auto;">
                <?php foreach ( $hvnly_property_ids as $hvnly_property_id ) : ?>
                <label class="hvnly-property-checkbox-label">
                    <div
                        class="hvnly-property-filter-checkbox <?php echo in_array( $hvnly_property_id, (array) ( $hvnly_current_property_ids ?? array() ), true ) ? 'checked' : ''; ?>">
                        <input type="checkbox" name="property_ids[]"
                            value="<?php echo esc_attr( $hvnly_property_id ); ?>" data-filter="property_ids"
                            <?php checked( in_array( $hvnly_property_id, (array) ( $hvnly_current_property_ids ?? array() ), true ) ); ?>>
                        <span><?php echo esc_html( $hvnly_property_id ); ?></span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
				endif;
				break;

		endswitch;
	endforeach;
	?>
</aside>