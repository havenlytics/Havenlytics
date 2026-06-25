<?php
/**
 * Select Field Template
 * 
 * This template displays the select field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/select-field.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/select-field.php
 * 3. Modify the copied file to customize the select field display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/fields/
 * @since       2.0.0
 * @deprecated  2.3.0 Legacy fallback template. Prefer single-property/fields/{category}/{type}.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_field       = $args['field'] ?? [];
$hvnly_value       = $args['value'] ?? '';
$hvnly_label       = $args['field_label'] ?? $hvnly_field['label'] ?? '';
$hvnly_name        = $args['field_name'] ?? $hvnly_field['name'] ?? '';
$hvnly_field_options = $args['field_options'] ?? $hvnly_field['options'] ?? [];

if ( empty( $hvnly_value ) ) {
    return;
}

// Initialize display value
$hvnly_display_value = $hvnly_value;

// FIRST PRIORITY: Check if field has options from field configuration
if ( ! empty( $hvnly_field_options ) && is_array( $hvnly_field_options ) ) {
    
    // Direct match with value as key
    if ( isset( $hvnly_field_options[ $hvnly_value ] ) ) {
        $hvnly_display_value = $hvnly_field_options[ $hvnly_value ];
    } 
    // Search through options to find matching value
    else {
        foreach ( $hvnly_field_options as $hvnly_option_value => $hvnly_option_label ) {
            if ( strcasecmp( $hvnly_option_value, $hvnly_value ) === 0 ) {
                $hvnly_display_value = $hvnly_option_label;
                break;
            }
        }
    }
}

// SECOND PRIORITY: Specific handling for known fields based on your debug output
if ( $hvnly_display_value === $hvnly_value ) {
    
    // Handle cooling field
    if ( $hvnly_name === '_hvnly_property_cooling' || strpos( $hvnly_name, 'cooling' ) !== false ) {
        $hvnly_cooling_options = array(
            'central'     => 'Central Air',
            'window'      => 'Window Units',
            'heat_pump'   => 'Heat Pump',
            'baseboard'   => 'Baseboard',
            'none'        => 'None',
        );
        
        if ( isset( $hvnly_cooling_options[ $hvnly_value ] ) ) {
            $hvnly_display_value = $hvnly_cooling_options[ $hvnly_value ];
        }
    }
    
    // Handle heating field
    if ( $hvnly_name === '_hvnly_property_heating' || strpos( $hvnly_name, 'heating' ) !== false ) {
        $hvnly_heating_options = array(
            'forced_air'  => 'Forced Air',
            'radiant'     => 'Radiant Heat',
            'baseboard'   => 'Baseboard',
            'heat_pump'   => 'Heat Pump',
            'geothermal'  => 'Geothermal',
            'none'        => 'None',
        );
        
        if ( isset( $hvnly_heating_options[ $hvnly_value ] ) ) {
            $hvnly_display_value = $hvnly_heating_options[ $hvnly_value ];
        }
    }
    
    // Handle water field
    if ( $hvnly_name === '_hvnly_property_water' || strpos( $hvnly_name, 'water' ) !== false ) {
        $hvnly_water_options = array(
            'city'        => 'City Water',
            'well'        => 'Well Water',
            'shared'      => 'Shared Well',
            'none'        => 'None',
        );
        
        if ( isset( $hvnly_water_options[ $hvnly_value ] ) ) {
            $hvnly_display_value = $hvnly_water_options[ $hvnly_value ];
        }
    }
    
    // Handle location field (convert slug to title)
    if ( $hvnly_name === '_hvnly_property_location' || strpos( $hvnly_name, 'location' ) !== false ) {
        $hvnly_display_value = ucwords( str_replace( array( '-', '_' ), ' ', $hvnly_value ) );
    }
    
    // Handle country field
    if ( $hvnly_name === '_hvnly_property_country_location' || strpos( $hvnly_name, 'country' ) !== false ) {
        $hvnly_country_codes = array(
            'AF' => 'Afghanistan',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'BD' => 'Bangladesh',
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'AE' => 'United Arab Emirates',
            'IN' => 'India',
            'PK' => 'Pakistan',
            'NP' => 'Nepal',
            'LK' => 'Sri Lanka',
            'MY' => 'Malaysia',
            'SG' => 'Singapore',
            'TH' => 'Thailand',
            'VN' => 'Vietnam',
            'PH' => 'Philippines',
            'ID' => 'Indonesia',
            'CN' => 'China',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'SA' => 'Saudi Arabia',
            'QA' => 'Qatar',
            'KW' => 'Kuwait',
            'OM' => 'Oman',
            'BH' => 'Bahrain',
            'EG' => 'Egypt',
            'ZA' => 'South Africa',
            'NG' => 'Nigeria',
            'KE' => 'Kenya',
            'MA' => 'Morocco',
            'TN' => 'Tunisia',
            'DZ' => 'Algeria',
            'LY' => 'Libya',
            'SD' => 'Sudan',
            'ET' => 'Ethiopia',
            'UG' => 'Uganda',
            'TZ' => 'Tanzania',
            'MZ' => 'Mozambique',
            'AO' => 'Angola',
            'NA' => 'Namibia',
            'BW' => 'Botswana',
            'ZW' => 'Zimbabwe',
            'ZM' => 'Zambia',
            'MW' => 'Malawi',
            'MG' => 'Madagascar',
            'MU' => 'Mauritius',
            'SC' => 'Seychelles',
            'KM' => 'Comoros',
            'CV' => 'Cape Verde',
            'ST' => 'Sao Tome and Principe',
            'GW' => 'Guinea-Bissau',
            'GN' => 'Guinea',
            'SL' => 'Sierra Leone',
            'LR' => 'Liberia',
            'CI' => 'Ivory Coast',
            'GH' => 'Ghana',
            'TG' => 'Togo',
            'BJ' => 'Benin',
            'BF' => 'Burkina Faso',
            'ML' => 'Mali',
            'SN' => 'Senegal',
            'GM' => 'Gambia',
            'MR' => 'Mauritania',
            'NE' => 'Niger',
            'TD' => 'Chad',
            'CM' => 'Cameroon',
            'CF' => 'Central African Republic',
            'GQ' => 'Equatorial Guinea',
            'GA' => 'Gabon',
            'CG' => 'Republic of the Congo',
            'CD' => 'Democratic Republic of the Congo',
            'RW' => 'Rwanda',
            'BI' => 'Burundi',
            'DJ' => 'Djibouti',
            'ER' => 'Eritrea',
            'SS' => 'South Sudan',
        );
        
        if ( isset( $hvnly_country_codes[ $hvnly_value ] ) ) {
            $hvnly_display_value = $hvnly_country_codes[ $hvnly_value ];
        } else {
            // If not in list, just make it look nice
            $hvnly_display_value = ucwords( strtolower( $hvnly_value ) );
        }
    }
}

// THIRD PRIORITY: Make the value look presentable
if ( $hvnly_display_value === $hvnly_value ) {
    // Convert underscores and hyphens to spaces, capitalize words
    $hvnly_display_value = ucwords( str_replace( array( '_', '-' ), ' ', $hvnly_value ) );
}

// Debug for admins (optional)
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'administrator' ) ) {
    echo '<!-- Select Field Debug: ' . esc_html( $hvnly_name ) . ' = ' . esc_html( $hvnly_value ) . ' → ' . esc_html( $hvnly_display_value ) . ' -->';
}

/**
 * Filter the display value for select fields
 *
 * @since 2.2.0
 *
 * @param string $display_value The value to display
 * @param mixed  $value         The stored value
 * @param array  $field         The field configuration
 * @param string $name          The field name
 */
$hvnly_display_value = apply_filters( 'hvnly_select_field_display_value', $hvnly_display_value, $hvnly_value, $hvnly_field, $hvnly_name );
?>
<!-- Single Select Field -->
<div class="hvnly-field hvnly-field--select hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_name ) ); ?>">
    <?php if ( ! empty( $hvnly_label ) ) : ?>
        <strong class="hvnly-field__label"><?php echo esc_html( $hvnly_label ); ?>:</strong>
    <?php endif; ?>
    <span class="hvnly-field__value"><?php echo esc_html( $hvnly_display_value ); ?></span>
</div>