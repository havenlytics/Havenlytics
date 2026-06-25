<?php
/**
 * Result Count Template
 *
 * This template displays the count of search results.
 *
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/search/result-count.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/search/result-count.php
 * 3. Modify the copied file to customize the result count display
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

$hvnly_total_properties = isset( $hvnly_args['total_properties'] ) ? (int) $hvnly_args['total_properties'] : 0;
$hvnly_start            = isset( $hvnly_args['start'] ) ? (int) $hvnly_args['start'] : 0;
$hvnly_end              = isset( $hvnly_args['end'] ) ? (int) $hvnly_args['end'] : 0;
$hvnly_current_filters  = isset( $hvnly_args['current_filters'] ) ? (array) $hvnly_args['current_filters'] : array();

if ( empty( $hvnly_current_filters ) && ! empty( $hvnly_args['hvnly_current_filters'] ) ) {
    $hvnly_current_filters = (array) $hvnly_args['hvnly_current_filters'];
}

if ( isset( $hvnly_current_filters['active_labels'] ) ) {
    $hvnly_active_filter_labels = (array) $hvnly_current_filters['active_labels'];
} elseif ( function_exists( 'hvnly_build_ajax_result_count_filters' ) ) {
    $hvnly_built_filters        = hvnly_build_ajax_result_count_filters( $hvnly_current_filters );
    $hvnly_active_filter_labels = isset( $hvnly_built_filters['active_labels'] )
        ? (array) $hvnly_built_filters['active_labels']
        : array();
} else {
    $hvnly_active_filter_labels = array();
}
?>
<div class="hvnly-property-results-header-left">
    <p id="hvnly-results-count">
        <?php if ( $hvnly_total_properties > 0 ) : ?>

        <?php
            $hvnly_start_span = '<strong>' . esc_html( $hvnly_start ) . '</strong>';
            $hvnly_end_span   = '<strong>' . esc_html( $hvnly_end ) . '</strong>';
            $hvnly_total_span = '<strong>' . esc_html( $hvnly_total_properties ) . '</strong>';

            $hvnly_properties_text = sprintf(
                /* translators: 1: start number, 2: end number, 3: total properties */
                __( 'Properties %1$s-%2$s of %3$s Properties', 'havenlytics' ),
                $hvnly_start_span,
                $hvnly_end_span,
                $hvnly_total_span
            );

            echo wp_kses_post( $hvnly_properties_text );

            if ( ! empty( $hvnly_active_filter_labels ) ) {
                $hvnly_filter_labels = '<strong>' . esc_html( implode( ', ', $hvnly_active_filter_labels ) ) . '</strong>';

                /* translators: %s: comma-separated active filter labels */
                echo wp_kses_post( sprintf( __( ' in %s', 'havenlytics' ), $hvnly_filter_labels ) );
            }
            ?>

        <?php else : ?>
        <?php esc_html_e( 'No properties found', 'havenlytics' ); ?>

        <?php
            if ( ! empty( $hvnly_active_filter_labels ) ) {
                $hvnly_filter_labels = '<strong>' . esc_html( implode( ', ', $hvnly_active_filter_labels ) ) . '</strong>';

                /* translators: %s: comma-separated active filter labels */
                echo wp_kses_post( sprintf( __( ' in %s', 'havenlytics' ), $hvnly_filter_labels ) );
            }
            ?>
        <?php endif; ?>
    </p>
</div>
