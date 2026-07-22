<?php
/**
 * Featured Properties block presentation.
 *
 * Grid layout reuses the Property Search / Archive card shell:
 *   section.hvnly-property--grid--listings
 *     → templates/archive/property-loop.php
 *       → content-property.php → Property Card Builder
 *
 * Featured owns only section chrome (header / CTA). Carousel layout is unchanged.
 *
 * Expects $args:
 *   query    WP_Query
 *   header   array
 *   layout   string  grid|carousel
 *   columns  int
 *   carousel array
 *
 * @package     Havenlytics
 * @subpackage  Templates/blocks
 * @since       3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_a = ( isset( $args ) && is_array( $args ) ) ? $args : array();
$hvnly_query   = isset( $hvnly_a['query'] ) && $hvnly_a['query'] instanceof WP_Query ? $hvnly_a['query'] : null;

$hvnly_layout  = isset( $hvnly_a['layout'] ) ? sanitize_key( (string) $hvnly_a['layout'] ) : 'grid';
$hvnly_header  = isset( $hvnly_a['header'] ) && is_array( $hvnly_a['header'] ) ? $hvnly_a['header'] : array();

// Spotlight removed — coerce any saved value to grid.
if ( 'spotlight' === $hvnly_layout ) {
	$hvnly_layout = 'grid';
}

if ( ! in_array( $hvnly_layout, array( 'grid', 'carousel' ), true ) ) {
	$hvnly_layout = 'grid';
}
?>
<div class="hvnly-block-featured hvnly-block-featured--<?php echo esc_attr( $hvnly_layout ); ?>">
	<?php hvnly_get_template_part( 'blocks/section-header', null, $hvnly_header ); ?>

	<?php
	if ( ! $hvnly_query instanceof WP_Query || ! $hvnly_query->have_posts() ) {
		echo '<div class="hvnly-block-empty">' . esc_html__( 'No featured properties found.', 'havenlytics' ) . '</div>';
		echo '</div>';
		return;
	}

	if ( 'carousel' === $hvnly_layout ) {
		$hvnly_carousel = isset( $hvnly_a['carousel'] ) && is_array( $hvnly_a['carousel'] ) ? $hvnly_a['carousel'] : array();
		hvnly_get_template_part(
			'blocks/carousel',
			null,
			array_merge(
				array(
					'query'  => $hvnly_query,
					'header' => array( 'show' => false ),
				),
				$hvnly_carousel
			)
		);
	} else {
		/*
		 * ONE implementation with Property Search:
		 * PropertyArchiveBlockRenderer → section.hvnly-property--grid--listings
		 *   → hvnly_get_template_part( 'archive/property', 'loop' )
		 *
		 * Columns use the same tokens as Search/Archive/Elementor:
		 *   data-columns + --hvnly-grid-columns + grid-template-columns: repeat(N, …)
		 * on the listing grid (property-loop only outputs display:grid by default).
		 */
		global $wp_query, $post;
		$hvnly_prev_query = $wp_query;
		$hvnly_prev_post  = $post;

		$hvnly_columns = isset( $hvnly_a['columns'] ) ? (int) $hvnly_a['columns'] : 3;
		if ( $hvnly_columns < 1 || $hvnly_columns > 4 ) {
			$hvnly_columns = 3;
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- same pattern as PropertyArchiveBlockRenderer.
		$wp_query = $hvnly_query;

		echo '<section class="hvnly-property--grid--listings" data-columns="' . esc_attr( (string) $hvnly_columns ) . '" style="--hvnly-grid-columns: ' . esc_attr( (string) $hvnly_columns ) . ';">';

		ob_start();
		hvnly_get_template_part( 'archive/property', 'loop' );
		$hvnly_loop_html = (string) ob_get_clean();

		// Same chrome Elementor puts on .hvnly-property-grid-view for column control.
		$hvnly_grid_chrome = sprintf(
			'display: grid; grid-template-columns: repeat(%1$d, minmax(0, 1fr)); gap: 20px; --hvnly-grid-columns: %1$d;',
			$hvnly_columns
		);

		$hvnly_loop_html = preg_replace_callback(
			'/<div\s+class="hvnly-property-grid-view\b[^"]*"[^>]*>/',
			static function ( $match ) use ( $hvnly_columns, $hvnly_grid_chrome ) {
				$tag = $match[0];

				if ( preg_match( '/\sstyle="/', $tag ) ) {
					$tag = preg_replace( '/\sstyle="[^"]*"/', ' style="' . esc_attr( $hvnly_grid_chrome ) . '"', $tag, 1 );
				} else {
					$tag = rtrim( $tag, '>' ) . ' style="' . esc_attr( $hvnly_grid_chrome ) . '">';
				}

				if ( ! preg_match( '/\sdata-columns="/', $tag ) ) {
					$tag = rtrim( $tag, '>' ) . ' data-columns="' . esc_attr( (string) $hvnly_columns ) . '">';
				}

				return $tag;
			},
			$hvnly_loop_html,
			1
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- loop markup from template part; chrome attrs escaped above.
		echo $hvnly_loop_html;
		echo '</section>';

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_query = $hvnly_prev_query;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post     = $hvnly_prev_post;
		wp_reset_postdata();
	}
	?>
</div>
