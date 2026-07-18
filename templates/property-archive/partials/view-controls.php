<?php
/**
 * Grid / list controls for agent and agency property archives.
 *
 * @package     Havenlytics
 * @subpackage  Templates/property-archive/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_args = ( isset( $args ) && is_array( $args ) ) ? $args : array();

$hvnly_total       = isset( $hvnly_args['total'] ) ? absint( $hvnly_args['total'] ) : 0;
$hvnly_start       = isset( $hvnly_args['start'] ) ? absint( $hvnly_args['start'] ) : 0;
$hvnly_end         = isset( $hvnly_args['end'] ) ? absint( $hvnly_args['end'] ) : 0;
$hvnly_label       = isset( $hvnly_args['entity_label'] ) ? (string) $hvnly_args['entity_label'] : __( 'results', 'havenlytics' );
$hvnly_view_type   = function_exists( 'hvnly_get_property_archive_view_type' ) ? hvnly_get_property_archive_view_type() : 'grid';
$hvnly_search_url  = isset( $hvnly_args['search_action'] ) ? esc_url( (string) $hvnly_args['search_action'] ) : '';
$hvnly_show_search = ! isset( $hvnly_args['show_search'] ) || ! empty( $hvnly_args['show_search'] );
$hvnly_search_term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="hvnly-property--archive__toolbar">
	<div class="hvnly-property--archive__summary">
		<?php if ( $hvnly_total > 0 ) : ?>
			<p class="hvnly-property--archive__count">
				<?php
				printf(
					/* translators: 1: start number, 2: end number, 3: total, 4: entity label */
					esc_html__( 'Showing %1$d–%2$d of %3$d %4$s', 'havenlytics' ),
					absint( $hvnly_start ),
					absint( $hvnly_end ),
					absint( $hvnly_total ),
					esc_html( $hvnly_label )
				);
				?>
			</p>
		<?php else : ?>
			<p class="hvnly-property--archive__count"><?php esc_html_e( 'No results found', 'havenlytics' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="hvnly-property--archive__actions">
		<?php if ( $hvnly_show_search && $hvnly_search_url ) : ?>
		<form class="hvnly-property--archive__search" method="get" action="<?php echo esc_url( $hvnly_search_url ); ?>">
			<label class="screen-reader-text" for="hvnly-property-archive-search"><?php esc_html_e( 'Search archive', 'havenlytics' ); ?></label>
			<input
				type="search"
				id="hvnly-property-archive-search"
				name="s"
				value="<?php echo esc_attr( $hvnly_search_term ); ?>"
				placeholder="<?php esc_attr_e( 'Search…', 'havenlytics' ); ?>"
				class="hvnly-property--archive__search-input"
			/>
			<?php if ( 'list' === $hvnly_view_type ) : ?>
				<input type="hidden" name="view" value="list" />
			<?php endif; ?>
			<button type="submit" class="hvnly-property--archive__search-btn">
				<i class="fas fa-search" aria-hidden="true"></i>
				<span><?php esc_html_e( 'Search', 'havenlytics' ); ?></span>
			</button>
		</form>
		<?php endif; ?>

		<div class="hvnly-property--archive__view-buttons" role="group" aria-label="<?php esc_attr_e( 'View layout options', 'havenlytics' ); ?>">
			<button type="button" class="hvnly-property--archive__view-btn <?php echo esc_attr( 'list' === $hvnly_view_type ? 'active' : '' ); ?>" data-view="list" aria-pressed="<?php echo esc_attr( 'list' === $hvnly_view_type ? 'true' : 'false' ); ?>" aria-label="<?php esc_attr_e( 'List view', 'havenlytics' ); ?>">
				<i class="fas fa-list" aria-hidden="true"></i>
			</button>
			<button type="button" class="hvnly-property--archive__view-btn <?php echo esc_attr( 'grid' === $hvnly_view_type ? 'active' : '' ); ?>" data-view="grid" aria-pressed="<?php echo esc_attr( 'grid' === $hvnly_view_type ? 'true' : 'false' ); ?>" aria-label="<?php esc_attr_e( 'Grid view', 'havenlytics' ); ?>">
				<i class="fas fa-th" aria-hidden="true"></i>
			</button>
		</div>
	</div>
</div>
