<?php
/**
 * Single agency content template.
 *
 * @package     Havenlytics
 * @subpackage  Templates/
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_term = get_queried_object();
if ( ! $hvnly_term instanceof WP_Term ) {
	return;
}

$hvnly_term_id = (int) $hvnly_term->term_id;
$hvnly_agency  = function_exists( 'hvnly_get_agency_archive_profile' )
	? hvnly_get_agency_archive_profile( $hvnly_term_id )
	: ( function_exists( 'hvnly_get_agency_profile' ) ? hvnly_get_agency_profile( $hvnly_term_id ) : array() );

if ( empty( $hvnly_agency['name'] ) ) {
	$hvnly_agency['name'] = $hvnly_term->name;
}

do_action( 'hvnly_before_single_agency', $hvnly_agency, $hvnly_term );
?>
<div class="hvnly-property--archive hvnly-property--agency-single hvnly-agency-single">
	<?php
	hvnly_get_template_part(
		'single-agency/partials/hero',
		null,
		array(
			'agency'  => $hvnly_agency,
			'term'    => $hvnly_term,
			'term_id' => $hvnly_term_id,
		)
	);

	hvnly_get_template_part(
		'single-agency/partials/stats',
		null,
		array(
			'agency'  => $hvnly_agency,
			'term_id' => $hvnly_term_id,
		)
	);

	hvnly_get_template_part(
		'single-agency/partials/agents',
		null,
		array(
			'term_id' => $hvnly_term_id,
		)
	);

	hvnly_get_template_part(
		'single-agency/partials/properties',
		null,
		array(
			'term_id' => $hvnly_term_id,
			'agency'  => $hvnly_agency,
		)
	);
	?>
</div>
<?php
do_action( 'hvnly_after_single_agency', $hvnly_agency, $hvnly_term );
