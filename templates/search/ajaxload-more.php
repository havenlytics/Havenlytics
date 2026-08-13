<?php
/**
 * Ajax Load More Template
 *
 * This template displays a "Load More" button for loading additional properties via AJAX.
 * When clicked, it loads more properties without refreshing the page.
 *
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/search/ajaxload-more.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/search/ajaxload-more.php
 * 3. Modify the copied file to customize the load more button display
 *
 * @package     Havenlytics
 * @subpackage  Templates/search
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// $args is injected by load_template(); copy to a prefixed local immediately.
$hvnly_args = ( isset( $args ) && is_array( $args ) ) ? $args : array();

global $wp_query;

$hvnly_current_page = max( 1, (int) get_query_var( 'paged' ) );
if ( isset( $hvnly_args['current_page'] ) ) {
    $hvnly_current_page = max( 1, (int) $hvnly_args['current_page'] );
}

$hvnly_per_page = (int) $wp_query->get( 'posts_per_page' );
if ( $hvnly_per_page < 1 ) {
    $hvnly_per_page = function_exists( 'hvnly_get_properties_per_page' ) ? hvnly_get_properties_per_page() : 12;
}
if ( isset( $hvnly_args['per_page'] ) && (int) $hvnly_args['per_page'] > 0 ) {
    $hvnly_per_page = (int) $hvnly_args['per_page'];
}

$hvnly_found_posts = (int) $wp_query->found_posts;
if ( isset( $hvnly_args['found_posts'] ) ) {
    $hvnly_found_posts = (int) $hvnly_args['found_posts'];
}

$hvnly_max_pages = (int) $wp_query->max_num_pages;
if ( isset( $hvnly_args['max_pages'] ) ) {
    $hvnly_max_pages = max( 0, (int) $hvnly_args['max_pages'] );
}
if ( $hvnly_max_pages <= 1 && $hvnly_found_posts > $hvnly_per_page && $hvnly_per_page > 0 ) {
    $hvnly_max_pages = (int) ceil( $hvnly_found_posts / $hvnly_per_page );
}

$hvnly_loaded_count   = min( $hvnly_current_page * $hvnly_per_page, $hvnly_found_posts );
$hvnly_show_load_more = $hvnly_max_pages > 1;

$hvnly_load_more_text = __( 'Load More Properties', 'havenlytics' );
if ( function_exists( 'hvnly_get_ajax_load_more_button_text' ) ) {
    $hvnly_load_more_text = hvnly_get_ajax_load_more_button_text();
}

$hvnly_loading_text    = __( 'Loading...', 'havenlytics' );
$hvnly_load_more_style = ! $hvnly_show_load_more ? 'display: none;' : '';

$hvnly_instance_id = '';
if ( ! empty( $hvnly_args['instance_id'] ) ) {
    $hvnly_instance_id = sanitize_text_field( $hvnly_args['instance_id'] );
}

$hvnly_unique_container_id = ! empty( $hvnly_instance_id ) ? 'hvnly-load-more-' . $hvnly_instance_id : 'hvnly-load-more';
$hvnly_unique_button_id    = ! empty( $hvnly_instance_id ) ? 'hvnlyLoadMorePropertyBtn-' . $hvnly_instance_id : 'hvnlyLoadMorePropertyBtn';
$hvnly_loaded_count_id     = ! empty( $hvnly_instance_id ) ? 'loadedCount-' . $hvnly_instance_id : 'loadedCount';
$hvnly_total_count_id      = ! empty( $hvnly_instance_id ) ? 'totalCount-' . $hvnly_instance_id : 'totalCount';

$hvnly_data_attributes = array(
    'data-instance-id="' . esc_attr( $hvnly_instance_id ) . '"',
    'data-current-page="' . esc_attr( $hvnly_current_page ) . '"',
    'data-max-pages="' . esc_attr( $hvnly_max_pages ) . '"',
    'data-posts-per-page="' . esc_attr( $hvnly_per_page ) . '"',
    'data-found-posts="' . esc_attr( $hvnly_found_posts ) . '"',
    'data-load-more-text="' . esc_attr( $hvnly_load_more_text ) . '"',
    'data-loading-text="' . esc_attr( $hvnly_loading_text ) . '"',
);

$hvnly_agent_id = isset( $hvnly_args['agent_id'] ) ? absint( $hvnly_args['agent_id'] ) : 0;
if ( $hvnly_agent_id > 0 ) {
    $hvnly_data_attributes[] = 'data-agent-id="' . esc_attr( (string) $hvnly_agent_id ) . '"';
}

$hvnly_agency_term_id = isset( $hvnly_args['agency_term_id'] ) ? absint( $hvnly_args['agency_term_id'] ) : 0;
if ( $hvnly_agency_term_id > 0 ) {
    $hvnly_data_attributes[] = 'data-agency-term-id="' . esc_attr( (string) $hvnly_agency_term_id ) . '"';
}
?>

<div id="<?php echo esc_attr( $hvnly_unique_container_id ); ?>"
    class="hvnly-property-load-more-container <?php echo ! empty( $hvnly_instance_id ) ? 'hvnly-instance-container' : ''; ?>"
    <?php
    foreach ( $hvnly_data_attributes as $hvnly_attribute ) {
        echo ' ' . $hvnly_attribute; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_attr() above.
    }
    ?>
    style="<?php echo esc_attr( $hvnly_load_more_style ); ?>">

    <button id="<?php echo esc_attr( $hvnly_unique_button_id ); ?>" class="hvnly-ui-control hvnly-property-load-more-btn"
        data-instance-id="<?php echo esc_attr( $hvnly_instance_id ); ?>">
        <span class="hvnly-property-load-more-text"><?php echo esc_html( $hvnly_load_more_text ); ?></span>
        <i class="fas fa-plus"></i>
    </button>

    <div class="hvnly-property-load-more-info" data-instance-id="<?php echo esc_attr( $hvnly_instance_id ); ?>">
        <span id="<?php echo esc_attr( $hvnly_loaded_count_id ); ?>"><?php echo esc_html( $hvnly_loaded_count ); ?></span>
        <?php esc_html_e( 'of', 'havenlytics' ); ?>
        <span id="<?php echo esc_attr( $hvnly_total_count_id ); ?>"><?php echo esc_html( $hvnly_found_posts ); ?></span>
        <?php esc_html_e( 'properties loaded', 'havenlytics' ); ?>
    </div>
</div>
