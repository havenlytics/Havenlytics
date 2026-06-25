<?php
/**
 * Result bar shortcode Template
 * 
 * This template displays the result bar for shortcodes.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/global/results-bar-shortcode.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/global/results-bar-shortcode.php
 * 3. Modify the copied file to customize the result bar display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/global
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_properties = isset($args['properties']) ? $args['properties'] : null;
$hvnly_total = isset($args['total']) ? (int) $args['total'] : 0;
$hvnly_start = isset($args['start']) ? (int) $args['start'] : 0;
$hvnly_end = isset($args['end']) ? (int) $args['end'] : 0;
$hvnly_per_page = isset($args['per_page']) ? (int) $args['per_page'] : get_option('posts_per_page', 12);
$hvnly_current_page = isset($args['current_page']) ? (int) $args['current_page'] : 1;
$hvnly_total_pages = isset($args['total_pages']) ? (int) $args['total_pages'] : 1;
$hvnly_show_results = isset($args['show_results']) ? $args['show_results'] : true;
$hvnly_show_pagination = isset($args['show_pagination']) ? $args['show_pagination'] : true;
$hvnly_context = isset($args['context']) ? $args['context'] : 'shortcode';
$hvnly_position = isset($args['position']) ? $args['position'] : 'bottom';
$hvnly_base_url = isset($args['base_url']) ? $args['base_url'] : '';
$hvnly_classes = isset($args['classes']) ? ' ' . $args['classes'] : '';

// Don't show if nothing to display
if ((!$hvnly_show_results || $hvnly_total === 0) && (!$hvnly_show_pagination || $hvnly_total_pages <= 1)) {
    return;
}

?>
<div class="hvnly-results-bar hvnly-results-bar--<?php echo esc_attr($hvnly_context); ?> hvnly-results-bar--<?php echo esc_attr($hvnly_position); ?><?php echo esc_attr($hvnly_classes); ?>">
    <div class="hvnly-results-bar__inner">
        
        <!-- Left side: Results count -->
        <?php if ($hvnly_show_results && $hvnly_total > 0) : ?>
            <div class="hvnly-results-bar__info">
                <?php
                hvnly_get_template('global/results-info-shortcode', [
                    'total'    => $hvnly_total,
                    'start'    => $hvnly_start,
                    'end'      => $hvnly_end,
                    'per_page' => $hvnly_per_page,
                    'context'  => $hvnly_context,
                    'classes'  => 'hvnly-results-bar__info-content',
                ]);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Right side: Pagination -->
        <?php if ($hvnly_show_pagination && $hvnly_total_pages > 1) : ?>
            <div class="hvnly-results-bar__pagination">
                <?php
                hvnly_get_template('global/pagination-shortcode', [
                    'query'        => $hvnly_properties,
                    'current_page' => $hvnly_current_page,
                    'total_pages'  => $hvnly_total_pages,
                    'base_url'     => $hvnly_base_url,
                    'context'      => $hvnly_context,
                    'show_numbers' => true,
                    'prev_text'    => '&laquo;',
                    'next_text'    => '&raquo;',
                    'classes'      => 'hvnly-results-bar__pagination-content',
                ]);
                ?>
            </div>
        <?php endif; ?>
        
    </div>
</div>