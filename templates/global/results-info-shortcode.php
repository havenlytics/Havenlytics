<?php
/**
 * Result info Shortcode Template
 * 
 * This template displays the result info for shortcodes.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/global/results-info-shortcode.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/global/results-info-shortcode.php
 * 3. Modify the copied file to customize the result info display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/global
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_total = isset($args['total']) ? (int) $args['total'] : 0;
$hvnly_start = isset($args['start']) ? (int) $args['start'] : 0;
$hvnly_end = isset($args['end']) ? (int) $args['end'] : 0;
$hvnly_per_page = isset($args['per_page']) ? (int) $args['per_page'] : get_option('posts_per_page', 12);
$hvnly_context = isset($args['context']) ? $args['context'] : 'shortcode';
$hvnly_classes = isset($args['classes']) ? ' ' . $args['classes'] : '';

if ($hvnly_total === 0) {
    return;
}

$hvnly_showing_all = ($hvnly_total <= $hvnly_per_page);

?>
<div class="hvnly-results-info hvnly-results-info--<?php echo esc_attr($hvnly_context); ?><?php echo esc_attr($hvnly_classes); ?>">
    <?php if ($hvnly_showing_all) : ?>
        <span class="hvnly-results-info__text hvnly-results-info__text--all">
            <?php
            printf(
                /* translators: %1$d: total number of properties */
                esc_html( _n( 'Showing %1$d property', 'Showing all %1$d properties', $hvnly_total, 'havenlytics' ) ),
                intval( $hvnly_total )
            );
            ?>
        </span>
    <?php else : ?>
        <span class="hvnly-results-info__text hvnly-results-info__text--range">
            <?php
            printf(
                /* translators: %1$d: start number, %2$d: end number, %3$d: total number */
                esc_html__( 'Showing %1$d–%2$d of %3$d results', 'havenlytics' ),
                intval( $hvnly_start ),
                intval( $hvnly_end ),
                intval( $hvnly_total )
            );
            ?>
        </span>
    <?php endif; ?>
</div>