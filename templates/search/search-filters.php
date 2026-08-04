<?php
/**
 * Search Filters Template
 * 
 * This template displays the search filters for the property search.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/search/search-filters.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/search/search-filters.php
 * 3. Modify the copied file to customize the search filters display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/search
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Check if top search bar should be shown
if ( ! hvnly_should_show_top_search_bar() ) {
    return; // Exit early - don't render anything
}

// Get dynamic main search fields from settings
$hvnly_main_search_fields = hvnly_get_main_search_fields();
$hvnly_current_filters    = hvnly_get_current_filters();

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_search_filters = new \HvnlyNab\Frontend\ViewModels\SearchFilters();
$hvnly_data           = $hvnly_search_filters->get_filter_data();

// Extract data for template use
extract( $hvnly_data );

// Get current URL parameters
$hvnly_current_url = get_post_type_archive_link( 'hvnly_property' );
$hvnly_url_params  = array();

// Safely get all GET parameters using filter_input_array
$hvnly_get_params = filter_input_array( INPUT_GET );
if ( is_array( $hvnly_get_params ) ) {
    // Sanitize each value
    foreach ( $hvnly_get_params as $hvnly_key => $hvnly_value ) {
        if ( is_array( $hvnly_value ) ) {
            $hvnly_url_params[ sanitize_key( $hvnly_key ) ] = array_map( 'sanitize_text_field', $hvnly_value );
        } else {
            $hvnly_url_params[ sanitize_key( $hvnly_key ) ] = sanitize_text_field( $hvnly_value );
        }
    }
}

// Remove empty parameters
$hvnly_url_params = array_filter( $hvnly_url_params );

// Get dynamic filter button text from settings
$hvnly_filter_button_text = __( 'Filter', 'havenlytics' );
if ( function_exists( 'hvnly_get_search_button_text' ) ) {
    $hvnly_filter_button_text = hvnly_get_search_button_text();
}

?>

<div class="hvnly-property-search-form">
    <form id="hvnly-property-search-form__box" class="hvnly-property-search-form__box">

        <!-- Department Tabs -->
        <div class="hvnly-property-search-tabs">
            <?php
            // Dynamically generate tabs from departments taxonomy (cached)
            if ( ! empty( $departments ) && ! is_wp_error( $departments ) ) :
                // Add "All" tab first
                $hvnly_all_url_params = $hvnly_url_params;
                unset( $hvnly_all_url_params['department'] );
                unset( $hvnly_all_url_params['paged'] );
                $hvnly_all_url = $hvnly_current_url . ( ! empty( $hvnly_all_url_params ) ? '?' . http_build_query( $hvnly_all_url_params ) : '' );
                $hvnly_is_all_active = empty( $current_department );
                ?>
            <a href="<?php echo esc_url( $hvnly_all_url ); ?>"
                class="hvnly-property-search-tab <?php echo $hvnly_is_all_active ? 'active' : ''; ?>" data-value="">
                <?php esc_html_e( 'All Properties', 'havenlytics' ); ?>
            </a>

            <?php foreach ( $departments as $hvnly_department ) :
                    // Build URL for this tab
                    $hvnly_tab_url_params = $hvnly_url_params;
                    $hvnly_tab_url_params['department'] = $hvnly_department->slug;
                    unset( $hvnly_tab_url_params['paged'] );
                    $hvnly_tab_url = $hvnly_current_url . '?' . http_build_query( $hvnly_tab_url_params );
                    $hvnly_is_active = ( $current_department === $hvnly_department->slug );
                    ?>
            <a href="<?php echo esc_url( $hvnly_tab_url ); ?>"
                class="hvnly-property-search-tab <?php echo $hvnly_is_active ? 'active' : ''; ?>"
                data-value="<?php echo esc_attr( $hvnly_department->slug ); ?>">
                <?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_department->name ) ) : esc_html( $hvnly_department->name ); ?>
            </a>
            <?php endforeach; ?>
            <?php else : ?>
            <!-- Fallback if no departments found -->
            <a href="<?php echo esc_url( $hvnly_current_url ); ?>" class="hvnly-property-search-tab active"
                data-value="">
                <?php esc_html_e( 'All Properties', 'havenlytics' ); ?>
            </a>
            <?php endif; ?>

            <input type="hidden" name="department" id="department"
                value="<?php echo esc_attr( $current_department ); ?>">
        </div>

        <!-- Main Search Fields -->
        <div class="hvnly-property-form-group-fields-wrapper">
            <div class="hvnly-property-form-group-fields-main">
                <?php 
                // Filter only enabled main search fields and sort by order
                $hvnly_enabled_main_fields = array_filter( $hvnly_main_search_fields, function( $field ) {
                    return isset( $field['enabled'] ) && $field['enabled'] === true;
                } );
                
                usort( $hvnly_enabled_main_fields, function( $a, $b ) {
                    return ( $a['order'] ?? 999 ) - ( $b['order'] ?? 999 );
                } );
                
                foreach ( $hvnly_enabled_main_fields as $hvnly_main_field ) :
                    $hvnly_field_id      = $hvnly_main_field['id'];
                    $hvnly_field_title   = $hvnly_main_field['title'];
                    $hvnly_field_type    = $hvnly_main_field['type'];
                    $hvnly_field_config  = isset( $hvnly_main_field['config'] ) ? $hvnly_main_field['config'] : [];
                    $hvnly_placeholder   = function_exists( 'hvnly_get_search_field_placeholder' )
                        ? hvnly_get_search_field_placeholder( $hvnly_field_id, $hvnly_field_title )
                        : ( isset( $hvnly_field_config['placeholder'] ) ? $hvnly_field_config['placeholder'] : $hvnly_field_title );
                    
                    switch ( $hvnly_field_id ) :
                        case 'keyword_search': ?>
                <div class="hvnly-property-form-item hvnly-ajax-search">
                    <input type="text" class="hvnly-property-form-input-multiselect" name="address_keyword"
                        placeholder="<?php echo function_exists( 'hvnly_translate_ui' ) ? esc_attr( hvnly_translate_ui( $hvnly_placeholder ) ) : esc_attr( $hvnly_placeholder ); ?>"
                        value="<?php echo esc_attr( $current_search ); ?>">
                </div>
                <?php break;
                        
                        case 'property_type': ?>
                <?php if ( ! empty( $prop_types ) && ! is_wp_error( $prop_types ) ) : ?>
                <div class="hvnly-property-form-item hvnly-property-tax-multichebox">
                    <input type="text" class="hvnly-property-form-input-multiselect hvnly-tax-search"
                        placeholder="<?php echo function_exists( 'hvnly_translate_ui' ) ? esc_attr( hvnly_translate_ui( $hvnly_placeholder ) ) : esc_attr( $hvnly_placeholder ); ?>" data-taxonomy="hvnly_prop_types">
                    <div class="hvnly-property-taxonomyDropdown-items">
                        <div class="hvnly-property-selected-items-container-tags"></div>
                        <ul>
                            <?php foreach ( $prop_types as $hvnly_prop_type ) : ?>
                            <li id="hvnly_property_type-<?php echo esc_attr( $hvnly_prop_type->term_id ); ?>">
                                <label class="hvnly-property-dropdown-checkbox">
                                    <input type="checkbox" name="property_type[]"
                                        value="<?php echo esc_attr( $hvnly_prop_type->slug ); ?>"
                                        data-term-id="<?php echo esc_attr( $hvnly_prop_type->term_id ); ?>" <?php
                                                    $hvnly_is_checked = in_array( $hvnly_prop_type->slug, (array) $current_types );
                                                    if ( isset( $hvnly_url_params['property_type'] ) ) {
                                                        $hvnly_url_property_types = is_array( $hvnly_url_params['property_type'] ) ? $hvnly_url_params['property_type'] : explode( ',', $hvnly_url_params['property_type'] );
                                                        if ( in_array( $hvnly_prop_type->slug, $hvnly_url_property_types ) ) {
                                                            $hvnly_is_checked = true;
                                                        }
                                                    }
                                                    checked( $hvnly_is_checked );
                                                ?>>
                                    <span class="hvnly-property-custom-mlt-checkbox"></span>
                                    <span
                                        class="hvnly-property-checkbox-mlt-label"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_prop_type->name ) ) : esc_html( $hvnly_prop_type->name ); ?></span>
                                </label>
                            </li>
                            <?php endforeach; ?>
                            <?php if ( ! empty( $prop_types ) ) : ?>
                            <div class="hvnly-property-dropdown-reset"><?php esc_html_e( 'Reset', 'havenlytics' ); ?>
                            </div>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="hvnly-property-dropdown-tags-close"><span class="dashicons dashicons-no-alt"></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php break;
                        
                        case 'location': ?>
                <?php if ( ! empty( $locations ) && ! is_wp_error( $locations ) ) : ?>
                <div class="hvnly-property-form-item hvnly-property-tax-multichebox">
                    <input type="text" class="hvnly-property-form-input-multiselect hvnly-tax-search"
                        placeholder="<?php echo function_exists( 'hvnly_translate_ui' ) ? esc_attr( hvnly_translate_ui( $hvnly_placeholder ) ) : esc_attr( $hvnly_placeholder ); ?>"
                        data-taxonomy="hvnly_prop_locations">
                    <div class="hvnly-property-taxonomyDropdown-items">
                        <div class="hvnly-property-selected-items-container-tags"></div>
                        <ul>
                            <?php foreach ( $locations as $hvnly_location ) : ?>
                            <li id="hvnly_location-<?php echo esc_attr( $hvnly_location->term_id ); ?>">
                                <label class="hvnly-property-dropdown-checkbox">
                                    <input type="checkbox" name="location[]"
                                        value="<?php echo esc_attr( $hvnly_location->slug ); ?>"
                                        data-term-id="<?php echo esc_attr( $hvnly_location->term_id ); ?>" <?php
                                                    $hvnly_is_checked = in_array( $hvnly_location->slug, (array) $current_locations );
                                                    if ( isset( $hvnly_url_params['location'] ) ) {
                                                        $hvnly_url_locations = is_array( $hvnly_url_params['location'] ) ? $hvnly_url_params['location'] : explode( ',', $hvnly_url_params['location'] );
                                                        if ( in_array( $hvnly_location->slug, $hvnly_url_locations ) ) {
                                                            $hvnly_is_checked = true;
                                                        }
                                                    }
                                                    checked( $hvnly_is_checked );
                                                ?>>
                                    <span class="hvnly-property-custom-mlt-checkbox"></span>
                                    <span
                                        class="hvnly-property-checkbox-mlt-label"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_location->name ) ) : esc_html( $hvnly_location->name ); ?></span>
                                </label>
                            </li>
                            <?php endforeach; ?>
                            <?php if ( ! empty( $locations ) ) : ?>
                            <div class="hvnly-property-dropdown-reset"><?php esc_html_e( 'Reset', 'havenlytics' ); ?>
                            </div>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="hvnly-property-dropdown-tags-close"><span class="dashicons dashicons-no-alt"></span>
                    </div>
                </div>
                <?php endif; ?>
                <?php break;
                    endswitch;
                endforeach; ?>
            </div>

            <!-- Filter Toggle Button -->
            <div class="hvnly-property-filter-toggle-wrapper">
                <button type="button" class="hvnly-property-filter-toggle" id="hvnly-property-filter-toggle">
                    <span class="hvnly-filter-toggle-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polygon points="22,3 2,3 10,12.5 10,19 14,21 14,12.5 22,3"></polygon>
                        </svg>
                    </span>
                    <span class="hvnly-filter-toggle-label"><?php echo esc_html( $hvnly_filter_button_text ); ?></span>
                </button>
            </div>
        </div>

        <!-- Additional Filter Fields (Collapsed by default) -->
        <div class="hvnly-property-additional-filters" id="hvnly-property-additional-filters">
            <div class="hvnly-property-additional-filters-grid">
                <?php 
                $hvnly_top_fields = hvnly_get_top_search_fields();
                foreach ( $hvnly_top_fields as $hvnly_top_field ) :
                    $hvnly_field_id    = $hvnly_top_field['id'];
                    $hvnly_field_title = $hvnly_top_field['title'];
                    $hvnly_field_type  = $hvnly_top_field['type'];
                    $hvnly_empty_label = function_exists( 'hvnly_get_search_field_placeholder' )
                        ? hvnly_get_search_field_placeholder( $hvnly_field_id, __( 'Any', 'havenlytics' ) )
                        : __( 'Any', 'havenlytics' );
                    
                    switch ( $hvnly_field_id ) :
                        case 'bedrooms': ?>
                <div class="hvnly-property-additional-field-item">
                    <label for="hvnly-property-search-bedrooms"
                        class="hvnly-property-search-label"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_field_title ) ) : esc_html( $hvnly_field_title ); ?></label>
                    <select id="hvnly-property-search-bedrooms" name="bedrooms">
                        <option value=""><?php echo esc_html( $hvnly_empty_label ); ?></option>
                        <?php 
                                    $hvnly_bedroom_options = hvnly_top_search_get_bedroom_options();
                                    foreach ( $hvnly_bedroom_options as $hvnly_option ) : ?>
                        <option value="<?php echo esc_attr( $hvnly_option ); ?>"
                            <?php selected( isset( $current_filters['bedrooms'] ) && $current_filters['bedrooms'] == $hvnly_option ); ?>>
                            <?php echo esc_html( $hvnly_option ); ?>+
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php break;
                        
                        case 'bathrooms': ?>
                <div class="hvnly-property-additional-field-item">
                    <label for="hvnly-property-search-bathrooms"
                        class="hvnly-property-search-label"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_field_title ) ) : esc_html( $hvnly_field_title ); ?></label>
                    <select id="hvnly-property-search-bathrooms" name="bathrooms">
                        <option value=""><?php echo esc_html( $hvnly_empty_label ); ?></option>
                        <?php 
                                    $hvnly_bathroom_options = hvnly_top_search_get_bathroom_options();
                                    foreach ( $hvnly_bathroom_options as $hvnly_option ) : ?>
                        <option value="<?php echo esc_attr( $hvnly_option ); ?>"
                            <?php selected( isset( $current_filters['bathrooms'] ) && $current_filters['bathrooms'] == $hvnly_option ); ?>>
                            <?php echo esc_html( $hvnly_option ); ?>+
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php break;
                        
                        case 'min_price': ?>
                <div class="hvnly-property-additional-field-item">
                    <label for="hvnly-property-search-min-price"
                        class="hvnly-property-search-label"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_field_title ) ) : esc_html( $hvnly_field_title ); ?></label>
                    <select id="hvnly-property-search-min-price" name="min_price">
                        <option value=""><?php echo esc_html( $hvnly_empty_label ); ?></option>
                        <?php 
                                    $hvnly_min_price_options = hvnly_top_search_get_min_price_options();
                                    foreach ( $hvnly_min_price_options as $hvnly_value => $hvnly_label ) : ?>
                        <option value="<?php echo esc_attr( $hvnly_value ); ?>"
                            <?php selected( isset( $current_filters['min_price'] ) && $current_filters['min_price'] == $hvnly_value ); ?>>
                            <?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_label ) ) : esc_html( $hvnly_label ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php break;
                        
                        case 'max_price': ?>
                <div class="hvnly-property-additional-field-item">
                    <label for="hvnly-property-search-max-price"
                        class="hvnly-property-search-label"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_field_title ) ) : esc_html( $hvnly_field_title ); ?></label>
                    <select id="hvnly-property-search-max-price" name="max_price">
                        <option value=""><?php echo esc_html( $hvnly_empty_label ); ?></option>
                        <?php 
                                    $hvnly_max_price_options = hvnly_top_search_get_max_price_options();
                                    foreach ( $hvnly_max_price_options as $hvnly_value => $hvnly_label ) : ?>
                        <option value="<?php echo esc_attr( $hvnly_value ); ?>"
                            <?php selected( isset( $current_filters['max_price'] ) && $current_filters['max_price'] == $hvnly_value ); ?>>
                            <?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_label ) ) : esc_html( $hvnly_label ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php break;
                    endswitch;
                endforeach; ?>
            </div>
        </div>

        <!-- Hidden fields for pagination and view type -->
        <input type="hidden" name="paged" value="1" id="paged-input">
        <input type="hidden" name="view_type" value="<?php echo esc_attr( $current_filters['view_type'] ?? 'grid' ); ?>"
            id="view-type-input">

        <!-- Preserve other URL parameters -->
        <?php
        foreach ( $hvnly_url_params as $hvnly_key => $hvnly_value ) {
            if ( ! in_array( $hvnly_key, array( 'department', 'address_keyword', 'hvnly_prop_types', 'location', 'paged', 'view_type' ) ) ) {
                if ( is_array( $hvnly_value ) ) {
                    foreach ( $hvnly_value as $hvnly_val ) {
                        echo '<input type="hidden" name="' . esc_attr( $hvnly_key ) . '[]" value="' . esc_attr( $hvnly_val ) . '">';
                    }
                } else {
                    echo '<input type="hidden" name="' . esc_attr( $hvnly_key ) . '" value="' . esc_attr( $hvnly_value ) . '">';
                }
            }
        }
        ?>
    </form>
</div>