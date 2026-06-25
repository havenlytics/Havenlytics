<?php
/**
 * Traditional pagination for agent/agency property archives (native + Elementor).
 *
 * Same visual pattern as property search pagination, with real page links.
 *
 * @package     Havenlytics
 * @subpackage  Templates/property-archive/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_args = ( isset( $args ) && is_array( $args ) ) ? $args : array();

$hvnly_current_page = isset( $hvnly_args['current_page'] ) ? max( 1, (int) $hvnly_args['current_page'] ) : max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$hvnly_max_pages    = isset( $hvnly_args['max_pages'] ) ? max( 0, (int) $hvnly_args['max_pages'] ) : 1;
$hvnly_per_page     = isset( $hvnly_args['per_page'] ) ? max( 1, (int) $hvnly_args['per_page'] ) : 12;
$hvnly_found_posts  = isset( $hvnly_args['found_posts'] ) ? max( 0, (int) $hvnly_args['found_posts'] ) : 0;
$hvnly_base_url     = isset( $hvnly_args['base_url'] ) ? (string) $hvnly_args['base_url'] : '';
$hvnly_entity_label = isset( $hvnly_args['entity_label'] ) ? (string) $hvnly_args['entity_label'] : __( 'results', 'havenlytics' );

if ( $hvnly_max_pages <= 1 ) {
	return;
}

$hvnly_start_page = max( 1, $hvnly_current_page - 2 );
$hvnly_end_page   = min( $hvnly_max_pages, $hvnly_current_page + 2 );

if ( $hvnly_current_page <= 2 ) {
	$hvnly_end_page = min( 5, $hvnly_max_pages );
}

if ( $hvnly_current_page >= $hvnly_max_pages - 1 ) {
	$hvnly_start_page = max( 1, $hvnly_max_pages - 4 );
}

$hvnly_start = ( ( $hvnly_current_page - 1 ) * $hvnly_per_page ) + 1;
$hvnly_end   = min( $hvnly_current_page * $hvnly_per_page, $hvnly_found_posts );

if ( '' === $hvnly_base_url ) {
	$hvnly_base_url = get_pagenum_link( 1 );
}

$hvnly_query_args = array();

if ( isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$hvnly_search = sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '' !== $hvnly_search ) {
		$hvnly_query_args['s'] = $hvnly_search;
	}
}

if ( isset( $_GET['view'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$hvnly_view = sanitize_key( wp_unslash( (string) $_GET['view'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'list' === $hvnly_view ) {
		$hvnly_query_args['view'] = 'list';
	}
}

/**
 * Build a pagination URL for archive listings.
 *
 * @param string               $base_url   Base URL without paging.
 * @param int                  $page       Page number.
 * @param array<string, mixed> $query_args Extra query args to preserve.
 * @return string
 */
$hvnly_build_page_url = static function ( string $base_url, int $page, array $query_args = array() ): string {
	$page     = max( 1, $page );
	$base_url = remove_query_arg( array( 'paged', 'page' ), $base_url );

	if ( $page <= 1 ) {
		$url = $base_url;
	} elseif ( get_option( 'permalink_structure' ) ) {
		$url = trailingslashit( untrailingslashit( $base_url ) ) . 'page/' . $page . '/';
	} else {
		$url = add_query_arg( 'paged', $page, $base_url );
	}

	if ( ! empty( $query_args ) ) {
		$url = add_query_arg( $query_args, $url );
	}

	return $url;
};
?>

<nav class="hvnly-property--archive__pagination"
    aria-label="<?php esc_attr_e( 'Archive pagination', 'havenlytics' ); ?>">
    <div class="hvnly-property-pagination">
        <div class="hvnly-property-pagination-info">
            <?php
			printf(
				/* translators: 1: start number, 2: end number, 3: total, 4: entity label */
				esc_html__( 'Showing %1$d–%2$d of %3$d %4$s', 'havenlytics' ),
				absint( $hvnly_start ),
				absint( $hvnly_end ),
				absint( $hvnly_found_posts ),
				esc_html( $hvnly_entity_label )
			);
			?>
        </div>

        <div class="hvnly-property-pagination-list">
            <?php if ( $hvnly_current_page > 1 ) : ?>
            <a class="hvnly-property--archive__pagination-item hvnly-property-pagination-item hvnly-property-pagination-prev"
                href="<?php echo esc_url( $hvnly_build_page_url( $hvnly_base_url, $hvnly_current_page - 1, $hvnly_query_args ) ); ?>"
                aria-label="<?php esc_attr_e( 'Previous page', 'havenlytics' ); ?>">
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
            </a>
            <?php endif; ?>

            <?php for ( $hvnly_i = $hvnly_start_page; $hvnly_i <= $hvnly_end_page; $hvnly_i++ ) : ?>
				<?php if ( $hvnly_i === $hvnly_current_page ) : ?>
            <span class="hvnly-property--archive__pagination-item hvnly-property-pagination-item active"
                aria-current="page">
					<?php echo esc_html( (string) $hvnly_i ); ?>
            </span>
            <?php else : ?>
            <a class="hvnly-property--archive__pagination-item hvnly-property-pagination-item"
                href="<?php echo esc_url( $hvnly_build_page_url( $hvnly_base_url, $hvnly_i, $hvnly_query_args ) ); ?>">
                <?php echo esc_html( (string) $hvnly_i ); ?>
            </a>
            <?php endif; ?>
            <?php endfor; ?>

            <?php if ( $hvnly_current_page < $hvnly_max_pages ) : ?>
            <a class="hvnly-property--archive__pagination-item hvnly-property-pagination-item hvnly-property-pagination-next"
                href="<?php echo esc_url( $hvnly_build_page_url( $hvnly_base_url, $hvnly_current_page + 1, $hvnly_query_args ) ); ?>"
                aria-label="<?php esc_attr_e( 'Next page', 'havenlytics' ); ?>">
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
</nav>
