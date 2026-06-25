<?php
/**
 * Agency single statistics section.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-agency/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_agency  = isset( $args['agency'] ) && is_array( $args['agency'] ) ? $args['agency'] : array();
$hvnly_term_id = isset( $args['term_id'] ) ? absint( $args['term_id'] ) : 0;

$hvnly_agent_count    = absint( $hvnly_agency['agent_count'] ?? 0 );
$hvnly_property_count = absint( $hvnly_agency['property_count'] ?? 0 );

if ( $hvnly_term_id > 0 && 0 === $hvnly_agent_count && function_exists( 'hvnly_get_agency_archive_profile' ) ) {
	$hvnly_profile = hvnly_get_agency_archive_profile( $hvnly_term_id );
	$hvnly_agent_count    = absint( $hvnly_profile['agent_count'] ?? 0 );
	$hvnly_property_count = absint( $hvnly_profile['property_count'] ?? 0 );
}
?>
<section class="hvnly-agency-single__overview" aria-label="<?php esc_attr_e( 'Agency statistics', 'havenlytics' ); ?>">
	<div class="hvnly-agency-single__overview-head">
		<h2 class="hvnly-agency-single__overview-title"><?php esc_html_e( 'Agency Overview', 'havenlytics' ); ?></h2>
		<p class="hvnly-agency-single__overview-subtitle"><?php esc_html_e( 'Team performance at a glance', 'havenlytics' ); ?></p>
	</div>

	<div class="hvnly-agency-single__stats">
		<div class="hvnly-agency-single__stat hvnly-agency-single__stat--agents">
			<span class="hvnly-agency-single__stat-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
			<span class="hvnly-agency-single__stat-value"><?php echo esc_html( (string) $hvnly_agent_count ); ?></span>
			<span class="hvnly-agency-single__stat-label">
				<?php echo esc_html( _n( 'Total Agent', 'Total Agents', $hvnly_agent_count, 'havenlytics' ) ); ?>
			</span>
		</div>
		<div class="hvnly-agency-single__stat hvnly-agency-single__stat--properties">
			<span class="hvnly-agency-single__stat-icon" aria-hidden="true"><i class="fas fa-building"></i></span>
			<span class="hvnly-agency-single__stat-value"><?php echo esc_html( (string) $hvnly_property_count ); ?></span>
			<span class="hvnly-agency-single__stat-label">
				<?php echo esc_html( _n( 'Total Property', 'Total Properties', $hvnly_property_count, 'havenlytics' ) ); ?>
			</span>
		</div>
	</div>
</section>
