<?php
/**
 * Pagination Shortcode Template
 * 
 * This template displays pagination for shortcodes.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/global/pagination-shortcode.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/global/pagination-shortcode.php
 * 3. Modify the copied file to customize the pagination display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/global
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_query = isset($args['query']) ? $args['query'] : null;
$hvnly_current_page = isset($args['current_page']) ? $args['current_page'] : 1;
$hvnly_base_url = isset($args['base_url']) ? $args['base_url'] : '';
$hvnly_total_pages = isset($args['total_pages']) ? $args['total_pages'] : 1;
$hvnly_context = isset($args['context']) ? $args['context'] : 'shortcode';
$hvnly_show_numbers = isset($args['show_numbers']) ? $args['show_numbers'] : true;
$hvnly_prev_text = isset($args['prev_text']) ? $args['prev_text'] : '&laquo;';
$hvnly_next_text = isset($args['next_text']) ? $args['next_text'] : '&raquo;';
$hvnly_classes = isset($args['classes']) ? ' ' . $args['classes'] : '';

if (!$hvnly_query && $hvnly_total_pages <= 1) {
    return; // No pagination needed
}

// Get total pages from query if not provided
if (!$hvnly_total_pages && $hvnly_query) {
    $hvnly_total_pages = $hvnly_query->max_num_pages;
}

if ($hvnly_total_pages <= 1) {
    return; // No pagination needed
}

// Build pagination links
$hvnly_big = 999999999; // Need an unlikely integer

$hvnly_pagination_args = [
    'base'      => str_replace($hvnly_big, '%#%', esc_url(get_pagenum_link($hvnly_big))),
    'format'    => '?paged=%#%',
    'current'   => max(1, $hvnly_current_page),
    'total'     => $hvnly_total_pages,
    'prev_text' => $hvnly_prev_text,
    'next_text' => $hvnly_next_text,
    'type'      => 'plain',
];

// If custom base URL provided
if (!empty($hvnly_base_url)) {
    $hvnly_pagination_args['base'] = trailingslashit($hvnly_base_url) . '%_%';
    $hvnly_pagination_args['format'] = 'page/%#%/';
}

$hvnly_pagination_links = paginate_links($hvnly_pagination_args);

if (!$hvnly_pagination_links) {
    return;
}

?>
<div class="hvnly-pagination hvnly-pagination--<?php echo esc_attr($hvnly_context); ?> hvnly-pagination--standard<?php echo esc_attr($hvnly_classes); ?>">
    <div class="hvnly-pagination__inner">
        
        <?php if ($hvnly_show_numbers) : ?>
            <div class="hvnly-pagination__links">
                <?php echo wp_kses_post($hvnly_pagination_links); ?>
            </div>
        <?php else : ?>
            <div class="hvnly-pagination__prev-next">
                <?php if ($hvnly_current_page > 1) : ?>
                    <a href="<?php echo esc_url(get_pagenum_link($hvnly_current_page - 1)); ?>" class="hvnly-pagination__prev">
                        <?php echo wp_kses_post($hvnly_prev_text); ?> <?php esc_html_e('Previous', 'havenlytics'); ?>
                    </a>
                <?php endif; ?>
                
                <?php if ($hvnly_current_page < $hvnly_total_pages) : ?>
                    <a href="<?php echo esc_url(get_pagenum_link($hvnly_current_page + 1)); ?>" class="hvnly-pagination__next">
                        <?php esc_html_e('Next', 'havenlytics'); ?> <?php echo wp_kses_post($hvnly_next_text); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    </div>
</div>