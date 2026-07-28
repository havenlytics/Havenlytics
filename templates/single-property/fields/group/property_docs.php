<?php
/**
 * Property Documents Field Template
 * 
 * This template displays the property documents field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/group/property_docs.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/group/property_docs.php
 * 3. Modify the copied file to customize the property documents field display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/fields/group
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_id = $args['property_id'] ?? get_the_ID();
$hvnly_values = $args['values'] ?? [];
$hvnly_group_base_id = $args['group_base_id'] ?? '';
$hvnly_group_id = $args['group_id'] ?? '';
$hvnly_group_name = $args['group_name'] ?? __( 'Property Documents', 'havenlytics' );

// Try multiple ways to get documents
$hvnly_documents = array();

// FIRST: Try using the values array passed from the renderer (most reliable)
if ( ! empty( $hvnly_values['documents'] ) && is_array( $hvnly_values['documents'] ) ) {
    $hvnly_documents = $hvnly_values['documents'];
}

// SECOND: Try using the passed group_base_id
if ( empty( $hvnly_documents ) && ! empty( $hvnly_group_base_id ) ) {
    $hvnly_field_name = $hvnly_group_base_id . '_documents';
    $hvnly_saved_value = get_post_meta( $hvnly_property_id, $hvnly_field_name, true );
    if ( ! empty( $hvnly_saved_value ) ) {
        $hvnly_decoded = json_decode( $hvnly_saved_value, true );
        if ( is_array( $hvnly_decoded ) && ! empty( $hvnly_decoded ) ) {
            $hvnly_documents = $hvnly_decoded;
        }
    }
}

// THIRD: Search for any property_docs_*_documents fields in post meta
if ( empty( $hvnly_documents ) ) {
    $hvnly_all_meta = get_post_meta( $hvnly_property_id );
    foreach ( $hvnly_all_meta as $hvnly_meta_key => $hvnly_meta_value ) {
        if ( preg_match( '/^property_docs_.+_documents$/i', $hvnly_meta_key ) ) {
            $hvnly_saved_value = is_array( $hvnly_meta_value ) ? ( $hvnly_meta_value[0] ?? '' ) : $hvnly_meta_value;
            if ( ! empty( $hvnly_saved_value ) ) {
                $hvnly_decoded = json_decode( $hvnly_saved_value, true );
                if ( is_array( $hvnly_decoded ) && ! empty( $hvnly_decoded ) ) {
                    $hvnly_documents = $hvnly_decoded;
                    break;
                }
            }
        }
    }
}

// FOURTH: BACKWARD COMPATIBILITY - Check for legacy format
if ( empty( $hvnly_documents ) && ! empty( $hvnly_group_base_id ) ) {
    // Try to get from individual fields using the passed base
    $hvnly_icons = get_post_meta( $hvnly_property_id, $hvnly_group_base_id . '_icon', true );
    $hvnly_labels = get_post_meta( $hvnly_property_id, $hvnly_group_base_id . '_label', true );
    $hvnly_urls = get_post_meta( $hvnly_property_id, $hvnly_group_base_id . '_url', true );
    
    if ( ! empty( $hvnly_icons ) || ! empty( $hvnly_labels ) || ! empty( $hvnly_urls ) ) {
        // Handle single values
        if ( ! is_array( $hvnly_icons ) && ! is_array( $hvnly_labels ) && ! is_array( $hvnly_urls ) ) {
            if ( ! empty( $hvnly_labels ) && ! empty( $hvnly_urls ) ) {
                $hvnly_documents[] = array(
                    'icon' => $hvnly_icons ?: 'file-pdf',
                    'label' => $hvnly_labels,
                    'url' => $hvnly_urls
                );
            }
        } 
        // Handle array values
        else {
            $hvnly_icon_array = is_array( $hvnly_icons ) ? $hvnly_icons : ( $hvnly_icons ? array( $hvnly_icons ) : array() );
            $hvnly_label_array = is_array( $hvnly_labels ) ? $hvnly_labels : ( $hvnly_labels ? array( $hvnly_labels ) : array() );
            $hvnly_url_array = is_array( $hvnly_urls ) ? $hvnly_urls : ( $hvnly_urls ? array( $hvnly_urls ) : array() );
            
            $hvnly_count = max( count( $hvnly_label_array ), count( $hvnly_url_array ) );
            
            for ( $hvnly_i = 0; $hvnly_i < $hvnly_count; $hvnly_i++ ) {
                $hvnly_label = $hvnly_label_array[ $hvnly_i ] ?? '';
                $hvnly_url = $hvnly_url_array[ $hvnly_i ] ?? '';
                
                if ( ! empty( $hvnly_label ) && ! empty( $hvnly_url ) ) {
                    $hvnly_documents[] = array(
                        'icon' => $hvnly_icon_array[ $hvnly_i ] ?? 'file-pdf',
                        'label' => $hvnly_label,
                        'url' => $hvnly_url
                    );
                }
            }
        }
    }
}

// FIFTH: Search for any property_docs_*_icon, _label, _url patterns
if ( empty( $hvnly_documents ) ) {
    $hvnly_all_meta = get_post_meta( $hvnly_property_id );
    $hvnly_possible_bases = array();
    
    foreach ( $hvnly_all_meta as $hvnly_meta_key => $hvnly_meta_value ) {
        if ( preg_match( '/^property_docs_(.+)_icon$/i', $hvnly_meta_key, $hvnly_matches ) ) {
            $hvnly_possible_bases[] = 'property_docs_' . $hvnly_matches[1];
        }
        if ( preg_match( '/^property_docs_(.+)_label$/i', $hvnly_meta_key, $hvnly_matches ) ) {
            $hvnly_possible_bases[] = 'property_docs_' . $hvnly_matches[1];
        }
        if ( preg_match( '/^property_docs_(.+)_url$/i', $hvnly_meta_key, $hvnly_matches ) ) {
            $hvnly_possible_bases[] = 'property_docs_' . $hvnly_matches[1];
        }
    }
    
    $hvnly_possible_bases = array_unique( $hvnly_possible_bases );
    
    foreach ( $hvnly_possible_bases as $hvnly_base ) {
        $hvnly_icons = get_post_meta( $hvnly_property_id, $hvnly_base . '_icon', true );
        $hvnly_labels = get_post_meta( $hvnly_property_id, $hvnly_base . '_label', true );
        $hvnly_urls = get_post_meta( $hvnly_property_id, $hvnly_base . '_url', true );
        
        if ( ! empty( $hvnly_icons ) || ! empty( $hvnly_labels ) || ! empty( $hvnly_urls ) ) {
            $hvnly_icon_array = is_array( $hvnly_icons ) ? $hvnly_icons : ( $hvnly_icons ? array( $hvnly_icons ) : array() );
            $hvnly_label_array = is_array( $hvnly_labels ) ? $hvnly_labels : ( $hvnly_labels ? array( $hvnly_labels ) : array() );
            $hvnly_url_array = is_array( $hvnly_urls ) ? $hvnly_urls : ( $hvnly_urls ? array( $hvnly_urls ) : array() );
            
            $hvnly_count = max( count( $hvnly_label_array ), count( $hvnly_url_array ) );
            
            for ( $hvnly_i = 0; $hvnly_i < $hvnly_count; $hvnly_i++ ) {
                $hvnly_label = $hvnly_label_array[ $hvnly_i ] ?? '';
                $hvnly_url = $hvnly_url_array[ $hvnly_i ] ?? '';
                
                if ( ! empty( $hvnly_label ) && ! empty( $hvnly_url ) ) {
                    $hvnly_documents[] = array(
                        'icon' => $hvnly_icon_array[ $hvnly_i ] ?? 'file-pdf',
                        'label' => $hvnly_label,
                        'url' => $hvnly_url
                    );
                }
            }
            
            if ( ! empty( $hvnly_documents ) ) {
                break;
            }
        }
    }
}

// Debug for admin users (only in debug mode)
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) && empty( $hvnly_documents ) ) {
    echo '<!-- Property Documents Debug: No documents found for property ID ' . esc_html( $hvnly_property_id ) . ' -->';
}

if ( empty( $hvnly_documents ) ) {
    return;
}

// Check sidebar status
$hvnly_sidebar_field = $hvnly_group_base_id ? $hvnly_group_base_id . '_show_in_sidebar' : '';
$hvnly_show_in_sidebar = true;
if ( ! empty( $hvnly_sidebar_field ) ) {
    $hvnly_sidebar_value = get_post_meta( $hvnly_property_id, $hvnly_sidebar_field, true );
    if ( $hvnly_sidebar_value !== '' ) {
        $hvnly_show_in_sidebar = (bool) $hvnly_sidebar_value;
    }
}

// For sidebar context
$hvnly_is_sidebar = isset( $args['is_sidebar'] ) ? $args['is_sidebar'] : false;

// If this is being rendered in sidebar but show_in_sidebar is false, don't display
if ( $hvnly_is_sidebar && ! $hvnly_show_in_sidebar ) {
    return;
}
?>

<?php if ( ! $hvnly_is_sidebar ) : ?>
<!-- Property Documents Section (Main Content) -->
<div class="hvnly-property-single__stats">
    <?php foreach ( $hvnly_documents as $hvnly_index => $hvnly_document ) : 
                $hvnly_icon = ! empty( $hvnly_document['icon'] ) ? $hvnly_document['icon'] : 'file-pdf';
                $hvnly_label = $hvnly_document['label'] ?? '';
                $hvnly_url = $hvnly_document['url'] ?? '';
                $hvnly_url_type = $hvnly_document['url_type'] ?? 'custom';
                
                if ( empty( $hvnly_label ) || empty( $hvnly_url ) ) continue;
            ?>
    <a href="<?php echo esc_url( $hvnly_url ); ?>" target="_blank" rel="noopener noreferrer"
        class="hvnly-property-single__stat-item hvnly-property-single__stat-item--link">
        <span class="hvnly-property-single__stat-value">
            <i class="fas fa-<?php echo esc_attr( $hvnly_icon ); ?>"></i>
        </span>
        <span class="hvnly-property-single__stat-label"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_label ) ) : esc_html( $hvnly_label ); ?></span>
    </a>
    <?php endforeach; ?>
</div>

<?php else : ?>
<!-- Property Documents Widget (Sidebar) -->
<div class="hvnly-widget">
    <h3 class="hvnly-widget__title"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_group_name ) ) : esc_html( $hvnly_group_name ); ?></h3>

    <div class="hvnly-property-single__stats hvnly-property-single__stats--vertical">
        <?php foreach ( $hvnly_documents as $hvnly_document ) : 
                $hvnly_icon = ! empty( $hvnly_document['icon'] ) ? $hvnly_document['icon'] : 'file-pdf';
                $hvnly_label = $hvnly_document['label'] ?? '';
                $hvnly_url = $hvnly_document['url'] ?? '';
                $hvnly_url_type = $hvnly_document['url_type'] ?? 'custom';
                
                if ( empty( $hvnly_label ) || empty( $hvnly_url ) ) continue;
            ?>
        <a href="<?php echo esc_url( $hvnly_url ); ?>" target="_blank" rel="noopener noreferrer"
            class="hvnly-property-single__stat-item hvnly-property-single__stat-item--link"
            style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid rgba(0,0,0,0.05);">
            <span class="hvnly-property-single__stat-value" style="font-size: 1.2rem; min-width: 30px;">
                <i class="fas fa-<?php echo esc_attr( $hvnly_icon ); ?>"></i>
            </span>
            <span class="hvnly-property-single__stat-label"
                style="flex: 1;"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_label ) ) : esc_html( $hvnly_label ); ?></span>
            <i class="fas fa-external-link-alt" style="font-size: 0.8rem; opacity: 0.5;"></i>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>