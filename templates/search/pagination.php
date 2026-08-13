<?php
/**
 * Pagination Template
 *
 * This template displays pagination links for the property search.
 *
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/search/pagination.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/search/pagination.php
 * 3. Modify the copied file to customize the pagination display
 *
 * @package     Havenlytics
 * @subpackage  Templates/search
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wp_query;

// $args is injected by load_template(); copy to a prefixed local immediately.
$hvnly_args = ( isset( $args ) && is_array( $args ) ) ? $args : array();

$hvnly_current_page = max( 1, (int) get_query_var( 'paged' ) );
if ( isset( $hvnly_args['current_page'] ) ) {
    $hvnly_current_page = max( 1, (int) $hvnly_args['current_page'] );
}

$hvnly_instance_id = '';
if ( ! empty( $hvnly_args['instance_id'] ) ) {
    $hvnly_instance_id = (string) $hvnly_args['instance_id'];
}

$hvnly_total_pages    = (int) $wp_query->max_num_pages;
$hvnly_found_posts    = (int) $wp_query->found_posts;
$hvnly_posts_per_page = (int) $wp_query->get( 'posts_per_page' );

if ( isset( $hvnly_args['max_pages'] ) ) {
    $hvnly_total_pages = max( 0, (int) $hvnly_args['max_pages'] );
}
if ( isset( $hvnly_args['found_posts'] ) ) {
    $hvnly_found_posts = max( 0, (int) $hvnly_args['found_posts'] );
}
if ( isset( $hvnly_args['per_page'] ) && (int) $hvnly_args['per_page'] > 0 ) {
    $hvnly_posts_per_page = (int) $hvnly_args['per_page'];
}

$hvnly_actual_max_pages = $hvnly_total_pages > 0
    ? $hvnly_total_pages
    : (int) ceil( $hvnly_found_posts / max( 1, $hvnly_posts_per_page ) );

if ( $hvnly_actual_max_pages <= 1 ) {
    return;
}

$hvnly_start_page = max( 1, $hvnly_current_page - 2 );
$hvnly_end_page   = min( $hvnly_actual_max_pages, $hvnly_current_page + 2 );

if ( $hvnly_current_page <= 2 ) {
    $hvnly_end_page = min( 5, $hvnly_actual_max_pages );
}

if ( $hvnly_current_page >= $hvnly_actual_max_pages - 1 ) {
    $hvnly_start_page = max( 1, $hvnly_actual_max_pages - 4 );
}
?>

<!-- NO outer div wrapper - parent provides the container -->
<div class="hvnly-property-pagination">
    <div class="hvnly-property-pagination-info">
        <?php
        $hvnly_start = ( ( $hvnly_current_page - 1 ) * $hvnly_posts_per_page ) + 1;
        $hvnly_end   = min( $hvnly_current_page * $hvnly_posts_per_page, $hvnly_found_posts );

        $hvnly_start_span = '<span>' . esc_html( $hvnly_start ) . '</span>';
        $hvnly_end_span   = '<span>' . esc_html( $hvnly_end ) . '</span>';
        $hvnly_total_span = '<span>' . esc_html( $hvnly_found_posts ) . '</span>';

        $hvnly_pagination_text = sprintf(
            /* translators: %1$s: start, %2$s: end, %3$s: total properties */
            __( 'Showing %1$s-%2$s of %3$s properties', 'havenlytics' ),
            $hvnly_start_span,
            $hvnly_end_span,
            $hvnly_total_span
        );

        echo wp_kses_post( $hvnly_pagination_text );
        ?>
    </div>

    <?php
    // Sprint 31D: pagination items were <div>s — unreachable by keyboard,
    // invisible to assistive tech. Real <button>s keep the same classes and
    // data attributes, so the existing delegated click handlers still fire.
    ?>
    <div class="hvnly-property-pagination-list" role="navigation" aria-label="<?php esc_attr_e( 'Properties pagination', 'havenlytics' ); ?>">
        <?php if ( $hvnly_current_page > 1 ) : ?>
        <button type="button" class="hvnly-ui-control hvnly-property-pagination-item hvnly-property-pagination-prev"
            data-page="<?php echo esc_attr( $hvnly_current_page - 1 ); ?>"
            aria-label="<?php esc_attr_e( 'Previous page', 'havenlytics' ); ?>"
            <?php if ( $hvnly_instance_id ) : ?>
            data-instance-id="<?php echo esc_attr( $hvnly_instance_id ); ?>"
            <?php endif; ?>>
            <i class="fas fa-chevron-left" aria-hidden="true"></i>
        </button>
        <?php endif; ?>

        <?php for ( $hvnly_i = $hvnly_start_page; $hvnly_i <= $hvnly_end_page; $hvnly_i++ ) :
            $hvnly_active = ( $hvnly_i === $hvnly_current_page ) ? 'active' : '';
            ?>
        <button type="button" class="hvnly-ui-control hvnly-property-pagination-item <?php echo esc_attr( $hvnly_active ); ?>"
            data-page="<?php echo esc_attr( $hvnly_i ); ?>"
            <?php if ( $hvnly_active ) : ?>
            aria-current="page"
            <?php endif; ?>
            <?php if ( $hvnly_instance_id ) : ?>
            data-instance-id="<?php echo esc_attr( $hvnly_instance_id ); ?>"
            <?php endif; ?>>
            <?php echo esc_html( $hvnly_i ); ?>
        </button>
        <?php endfor; ?>

        <?php if ( $hvnly_current_page < $hvnly_actual_max_pages ) : ?>
        <button type="button" class="hvnly-ui-control hvnly-property-pagination-item hvnly-property-pagination-next"
            data-page="<?php echo esc_attr( $hvnly_current_page + 1 ); ?>"
            aria-label="<?php esc_attr_e( 'Next page', 'havenlytics' ); ?>"
            <?php if ( $hvnly_instance_id ) : ?>
            data-instance-id="<?php echo esc_attr( $hvnly_instance_id ); ?>"
            <?php endif; ?>>
            <i class="fas fa-chevron-right" aria-hidden="true"></i>
        </button>
        <?php endif; ?>
    </div>
</div>
