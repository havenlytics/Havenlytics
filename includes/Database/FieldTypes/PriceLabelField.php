<?php
/**
 * Price Label Field Handler - Custom Price Label with Toggle
 * Uses existing PriceOnCallTextAPI data source for dynamic options
 * 
 * @package HvnlyNab\Database\FieldTypes
 * @since 2.1.3
 */

namespace HvnlyNab\Database\FieldTypes;

use HvnlyNab\Core\SettingsManager;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PriceLabelField
 * 
 * Handles price field with toggle between numeric price and custom label
 */
class PriceLabelField extends BaseFieldType {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct('price_label');
        $this->requires_assets = true;
    }
    
    /**
     * Get price label options from the same source as PriceOnCallTextAPI
     *
     * @return array
     */
    private function get_price_label_options() {
        $options = array();
        
        // Default options (same as PriceOnCallTextAPI)
        $defaults = array(
            array('id' => 1, 'label' => __('None', 'havenlytics'), 'value' => 'priceOnCallNone', 'is_default' => 1),
            array('id' => 2, 'label' => __('Price On Call', 'havenlytics'), 'value' => 'priceOnCall', 'is_default' => 1),
            array('id' => 3, 'label' => __('Fixed Price', 'havenlytics'), 'value' => 'fixedPrice', 'is_default' => 1),
            array('id' => 4, 'label' => __('Guide Price', 'havenlytics'), 'value' => 'guidePrice', 'is_default' => 1),
            array('id' => 5, 'label' => __('Offers Over', 'havenlytics'), 'value' => 'offersOver', 'is_default' => 1),
        );
        
        // Get custom options from the same option key
        $custom_options = get_option('hvnly_price_on_call_custom_options', array());
        
        // Start with defaults
        $all_options = $defaults;
        
        // Add custom options
        if (!empty($custom_options) && is_array($custom_options)) {
            $next_id = 6;
            foreach ($custom_options as $custom_option) {
                if (!isset($custom_option['id'])) {
                    $custom_option['id'] = $next_id++;
                }
                $custom_option['is_default'] = 0;
                // Clean up label if needed
                if (isset($custom_option['label'])) {
                    $custom_option['label'] = trim(preg_replace('/\s+/', ' ', $custom_option['label']));
                }
                $all_options[] = $custom_option;
            }
        }
        
        // Sort by order
        usort($all_options, function($a, $b) {
            $order_a = isset($a['order']) ? $a['order'] : (isset($a['id']) ? $a['id'] : 0);
            $order_b = isset($b['order']) ? $b['order'] : (isset($b['id']) ? $b['id'] : 0);
            return $order_a - $order_b;
        });
        
        // Convert to simple array for dropdown, skipping "None" option
        foreach ($all_options as $option) {
            if ($option['value'] === 'priceOnCallNone') {
                continue;
            }
            
            $options[] = array(
                'value' => $option['value'],
                'label' => $option['label'],
                'is_default' => isset($option['is_default']) ? (bool) $option['is_default'] : false,
                'id' => $option['id']
            );
        }
        
        // Fallback options if nothing exists
        if (empty($options)) {
            $options = array(
                array('value' => 'priceOnCall', 'label' => __('Price on Call', 'havenlytics'), 'is_default' => true),
                array('value' => 'fixedPrice', 'label' => __('Fixed Price', 'havenlytics'), 'is_default' => true),
                array('value' => 'guidePrice', 'label' => __('Guide Price', 'havenlytics'), 'is_default' => true),
                array('value' => 'askingPrice', 'label' => __('Asking Price', 'havenlytics'), 'is_default' => true),
                array('value' => 'offersOver', 'label' => __('Offers Over', 'havenlytics'), 'is_default' => true),
                array('value' => 'offersExcess', 'label' => __('Offers in Excess of', 'havenlytics'), 'is_default' => true),
                array('value' => 'fromPrice', 'label' => __('From', 'havenlytics'), 'is_default' => true),
            );
        }
        
        return $options;
    }
    
    /**
     * Get current currency symbol using existing Helper method
     *
     * @return string
     */
    private function get_currency_symbol() {
        return HVNLY_NAB()->Helper->get_current_currency_symbol();
    }
    
    /**
     * Parse stored value and return mode and selected value
     *
     * @param mixed $value Stored value
     * @return array
     */
    private function parse_stored_value($value) {
        $result = array(
            'mode' => 'standard',
            'value' => '',
            'label' => ''
        );
        
        if (empty($value)) {
            return $result;
        }
        
        if (is_string($value) && strlen($value) > 0 && $value[0] === '{') {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && isset($decoded['__type']) && $decoded['__type'] === 'custom_label') {
                $result['mode'] = 'custom';
                $result['value'] = isset($decoded['value']) ? $decoded['value'] : '';
                $result['label'] = isset($decoded['label']) ? trim(preg_replace('/\s+/', ' ', $decoded['label'])) : '';
                return $result;
            }
        }
        
        // Standard numeric or text price
        $result['mode'] = 'standard';
        $result['value'] = $value;
        return $result;
    }
    
    /**
     * Render the price label field with toggle
     *
     * @param array $field Field configuration
     * @param mixed $value Current field value
     * @param int $post_id Post ID
     * @return string HTML output
     */
    public function render($field, $value, $post_id) {
        $field_name = isset($field['name']) ? $field['name'] : '_hvnly_property_price';
        $field_id = isset($field['id']) ? $field['id'] : $field_name;
        $is_required = isset($field['is_required']) && $field['is_required'];
        
        // Parse stored value
        $parsed = $this->parse_stored_value($value);
        $is_custom_mode = ($parsed['mode'] === 'custom');
        $selected_label = $parsed['value'];
        $price_value = (!$is_custom_mode) ? $parsed['value'] : '';
        
        // Get price label options
        $label_options = $this->get_price_label_options();
        $currency_symbol = $this->get_currency_symbol();
        
        // Get readable display value
        $display_value = '';
        if ($is_custom_mode && !empty($selected_label)) {
            foreach ($label_options as $option) {
                if ($option['value'] === $selected_label) {
                    $display_value = $option['label'];
                    break;
                }
            }
            if (empty($display_value)) {
                $display_value = $parsed['label'];
            }
        } elseif (!empty($price_value) && is_numeric($price_value)) {
            $display_value = HVNLY_NAB()->Helper->format_numeric_price_for_filter( floatval( $price_value ) );
        } elseif (!empty($price_value)) {
            $display_value = $price_value;
        }
        
        // Determine if field has valid value for validation
        $has_valid_value = (!empty($price_value) && $price_value !== '') || (!empty($selected_label) && $selected_label !== '');
        
        // Enqueue assets
        $this->enqueue_assets();
        
        ob_start();
        ?>
<div class="hvnly-price-label-field-wrapper" data-field-name="<?php echo esc_attr($field_name); ?>"
    data-field-id="<?php echo esc_attr($field_id); ?>" data-is-required="<?php echo $is_required ? 'true' : 'false'; ?>"
    data-current-mode="<?php echo $is_custom_mode ? 'custom' : 'standard'; ?>"
    data-current-value="<?php echo esc_attr($is_custom_mode ? $selected_label : $price_value); ?>"
    data-stored-value="<?php echo esc_attr($value); ?>" data-currency-symbol="<?php echo esc_attr($currency_symbol); ?>"
    data-has-valid-value="<?php echo $has_valid_value ? 'true' : 'false'; ?>">

    <!-- Hidden field to store the actual value -->
    <input type="hidden" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name); ?>"
        value="<?php echo esc_attr($value); ?>" class="hvnly-price-label-value" />

    <!-- Toggle Switch -->
    <div class="hvnly-price-label-toggle-container">
        <label class="hvnly-price-label-switch">
            <input type="checkbox" class="hvnly-price-label-mode-toggle" <?php checked($is_custom_mode, true); ?> />
            <span class="hvnly-price-label-slider"></span>
        </label>
        <span class="hvnly-price-label-mode-text">
            <?php if ($is_custom_mode): ?>
            <i class="fas fa-tag"></i> <?php esc_html_e('Custom Price Label Mode', 'havenlytics'); ?>
            <?php else: ?>
            <span class="hvnly-price-label-currency-icon"><?php echo esc_html( $currency_symbol ); ?></span> <?php esc_html_e('Standard Price Mode', 'havenlytics'); ?>
            <?php endif; ?>
        </span>
        <span class="hvnly-price-label-hint">
            <?php if ($is_custom_mode): ?>
            <?php esc_html_e('Toggle OFF to enter a numeric price', 'havenlytics'); ?>
            <?php else: ?>
            <?php esc_html_e('Toggle ON to select a custom price label', 'havenlytics'); ?>
            <?php endif; ?>
        </span>
    </div>

    <!-- Standard Price Input -->
    <div class="hvnly-price-label-standard-mode" style="<?php echo $is_custom_mode ? 'display: none;' : ''; ?>">
        <div class="hvnly-price-label-input-group">
            <span class="hvnly-price-label-currency"><?php echo esc_html($currency_symbol); ?></span>
            <input type="number" step="any" class="hvnly-price-label-price-input widefat"
                value="<?php echo esc_attr($price_value); ?>"
                placeholder="<?php esc_attr_e('Enter property price', 'havenlytics'); ?>"
                <?php echo !$is_custom_mode && $is_required ? 'required' : ''; ?> />
        </div>
        <p class="description">
            <?php esc_html_e('Enter a numeric price (e.g., 250000, 500000, 750000)', 'havenlytics'); ?></p>
    </div>

    <!-- Custom Label Selector -->
    <div class="hvnly-price-label-custom-mode" style="<?php echo !$is_custom_mode ? 'display: none;' : ''; ?>">
        <div class="hvnly-price-label-select-group">
            <select class="hvnly-price-label-select widefat">
                <option value=""><?php esc_html_e('-- Select price label --', 'havenlytics'); ?></option>
                <?php foreach ($label_options as $option): ?>
                <option value="<?php echo esc_attr($option['value']); ?>"
                    <?php selected($selected_label, $option['value']); ?>>
                    <?php echo esc_html($option['label']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <p class="description">
            <?php esc_html_e('Select a custom price label', 'havenlytics'); ?>
            <?php if (current_user_can('manage_options')): ?>
            <br><a
                href="<?php echo esc_url(admin_url('edit.php?post_type=hvnly_property&page=hvnly_property_settings')); ?>"
                target="_blank">
                <?php esc_html_e('Manage custom price labels in Settings', 'havenlytics'); ?>
            </a>
            <?php endif; ?>
        </p>
    </div>

    <!-- Show current selection preview -->
    <div class="hvnly-price-label-preview">
        <strong><?php esc_html_e('Current Value:', 'havenlytics'); ?></strong>
        <span class="hvnly-price-label-preview-value">
            <?php echo !empty($display_value) ? esc_html($display_value) : esc_html__('Not set', 'havenlytics'); ?>
        </span>
    </div>
</div>
<?php
        
        return ob_get_clean();
    }
    
    /**
     * Save price label field data
     *
     * @param int $post_id Post ID
     * @param string $field_name Field name
     * @param mixed $value Field value
     * @param mixed $extra Optional extra parameter for compatibility
     * @return void
     */
    public function save($post_id, $field_name, $value, $extra = null) {
        $sanitized_value = $this->sanitize($value);
        
        if (!empty($sanitized_value)) {
            update_post_meta($post_id, $field_name, $sanitized_value);
        } else {
            hvnly_safe_delete_post_meta($post_id, $field_name, 'user_save_empty');
        }
    }
    
    /**
     * Sanitize price label field value
     *
     * @param mixed $value
     * @return string
     */
    public function sanitize($value) {
        if (empty($value)) {
            return '';
        }
        
        if (is_string($value)) {
            // Check if it's JSON (custom label)
            if (strlen($value) > 0 && $value[0] === '{') {
                $decoded = json_decode($value, true);
                if (is_array($decoded) && isset($decoded['__type'])) {
                    // Clean up label if present
                    if (isset($decoded['label'])) {
                        $decoded['label'] = trim(preg_replace('/\s+/', ' ', $decoded['label']));
                        return wp_json_encode($decoded);
                    }
                    return sanitize_text_field($value);
                }
            }
            // Numeric or text price
            if (is_numeric($value)) {
                return (string) $value;
            }
            return sanitize_text_field($value);
        }
        
        if (is_numeric($value)) {
            return (string) $value;
        }
        
        return sanitize_text_field($value);
    }
    
    /**
     * Validate price label field
     *
     * @param mixed $value
     * @param array $field
     * @return bool|\WP_Error
     */
    public function validate($value, $field) {
        $is_required = isset($field['is_required']) && $field['is_required'];
        
        if (!$is_required) {
            return true;
        }
        
        if (empty($value)) {
            return new \WP_Error('required_field', sprintf(
            /* translators: %s: Field label */
                __('The field "%s" is required.', 'havenlytics'),
                hvnly_esc_html_ui( (string) $field['label'] )
            ));
        }
        
        return true;
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        // Enqueue CSS
        wp_enqueue_style(
            'hvnly-price-label-field',
            HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-price-label-field.css',
            array(),
            defined('HVNLYNAB_VERSION') ? HVNLYNAB_VERSION : '1.0.0'
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'hvnly-price-label-field',
            HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-price-label-field.js',
            array('jquery'),
            defined('HVNLYNAB_VERSION') ? HVNLYNAB_VERSION : '1.0.0',
            true
        );
        
        // Localize script with data
        $currency_settings = HVNLY_NAB()->Helper->get_currency_settings();
        $currency_code       = $currency_settings['hvnly_currencyType'] ?? 'USD';

        wp_localize_script(
            'hvnly-price-label-field',
            'HvnlyPriceLabelField',
            array(
                'strings' => array(
                    'notSet' => __('Not set', 'havenlytics'),
                    'selectLabel' => __('-- Select price label --', 'havenlytics'),
                    'customMode' => __('Custom Price Label Mode', 'havenlytics'),
                    'standardMode' => __('Standard Price Mode', 'havenlytics'),
                    'toggleOff' => __('Toggle OFF to enter a numeric price', 'havenlytics'),
                    'toggleOn' => __('Toggle ON to select a custom price label', 'havenlytics'),
                ),
                'currency' => array(
                    'symbol'             => HVNLY_NAB()->Helper->get_currency_symbol( $currency_code ),
                    'position'           => $currency_settings['hvnly_currencyPositionType'] ?? 'LEFT',
                    'thousandSeparator'  => $currency_settings['hvnly_thousandSeparator'] ?? ',',
                    'decimalSeparator'   => $currency_settings['hvnly_decimalSeparator'] ?? '.',
                    'decimals'           => (int) ( $currency_settings['hvnly_numberOfDecimals'] ?? 0 ),
                    'priceFormat'        => $currency_settings['hvnly_priceFormat'] ?? 'comma',
                    'enableLargeFormat'  => ! empty( $currency_settings['hvnly_EnabledCurrencyFormat'] ),
                    'thousandText'       => $currency_settings['hvnly_thousandText'] ?? 'K',
                    'millionText'        => $currency_settings['hvnly_millionText'] ?? 'M',
                    'billionText'        => $currency_settings['hvnly_billionText'] ?? 'B',
                ),
            )
        );
    }
}