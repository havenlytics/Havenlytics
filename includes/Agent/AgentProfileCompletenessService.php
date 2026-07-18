<?php
/**
 * Reusable Agent profile completeness scoring.
 *
 * @package HvnlyNab\Agent
 * @since   3.2.0
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Weighted checklist → 0–100%. Does not mutate data.
 *
 * @since 3.2.0
 */
final class AgentProfileCompletenessService {

	/**
	 * Checklist weights (sum = 100).
	 *
	 * @return array<string, int>
	 */
	public static function weights(): array {
		return array(
			'photo'     => 15,
			'title'     => 10,
			'biography' => 10,
			'email'     => 12,
			'phone'     => 10,
			'agency'    => 10,
			'address'   => 8,
			'license'   => 8,
			'position'  => 7,
			'social'    => 10,
		);
	}

	/**
	 * @param int $agent_id Agent CPT ID.
	 * @return array{percent:int,score:int,max:int,missing:string[],checks:array<string,bool>}
	 */
	public function evaluate( int $agent_id ): array {
		$agent_id = absint( $agent_id );
		$weights  = self::weights();
		$checks   = array();
		$missing  = array();
		$score    = 0;
		$max      = array_sum( $weights );

		if ( $agent_id <= 0 || AgentConstants::POST_TYPE !== get_post_type( $agent_id ) ) {
			return array(
				'percent' => 0,
				'score'   => 0,
				'max'     => $max,
				'missing' => array_keys( $weights ),
				'checks'  => array_fill_keys( array_keys( $weights ), false ),
			);
		}

		$post = get_post( $agent_id );
		$email = (string) get_post_meta( $agent_id, AgentConstants::META_EMAIL, true );
		$phone = (string) get_post_meta( $agent_id, AgentConstants::META_PHONE, true );
		if ( '' === $phone ) {
			$phone = (string) get_post_meta( $agent_id, AgentConstants::META_MOBILE, true );
		}
		$address  = (string) get_post_meta( $agent_id, AgentConstants::META_ADDRESS, true );
		$license  = (string) get_post_meta( $agent_id, AgentConstants::META_LICENSE, true );
		$position = (string) get_post_meta( $agent_id, AgentConstants::META_POSITION, true );
		$agencies = wp_get_post_terms( $agent_id, AgentConstants::TAXONOMY_AGENCY, array( 'fields' => 'ids' ) );
		$social   = array(
			(string) get_post_meta( $agent_id, AgentConstants::META_FACEBOOK, true ),
			(string) get_post_meta( $agent_id, AgentConstants::META_LINKEDIN, true ),
			(string) get_post_meta( $agent_id, AgentConstants::META_INSTAGRAM, true ),
			(string) get_post_meta( $agent_id, AgentConstants::META_TWITTER, true ),
			(string) get_post_meta( $agent_id, AgentConstants::META_YOUTUBE, true ),
			(string) get_post_meta( $agent_id, AgentConstants::META_WEBSITE, true ),
		);

		$checks['photo']     = has_post_thumbnail( $agent_id );
		$checks['title']     = $post && '' !== trim( (string) $post->post_title );
		$checks['biography'] = $post && '' !== trim( wp_strip_all_tags( (string) $post->post_content ) );
		$checks['email']     = is_email( $email );
		$checks['phone']     = '' !== trim( $phone );
		$checks['agency']    = is_array( $agencies ) && ! empty( $agencies ) && ! is_wp_error( $agencies );
		$checks['address']   = '' !== trim( $address );
		$checks['license']   = '' !== trim( $license );
		$checks['position']  = '' !== trim( $position );
		$checks['social']    = (bool) array_filter( array_map( 'trim', $social ) );

		foreach ( $weights as $key => $weight ) {
			if ( ! empty( $checks[ $key ] ) ) {
				$score += (int) $weight;
			} else {
				$missing[] = $key;
			}
		}

		$percent = $max > 0 ? (int) round( ( $score / $max ) * 100 ) : 0;

		return array(
			'percent' => $percent,
			'score'   => $score,
			'max'     => $max,
			'missing' => $missing,
			'checks'  => $checks,
		);
	}

	/**
	 * @param int $agent_id Agent ID.
	 * @return int
	 */
	public function percent( int $agent_id ): int {
		return (int) $this->evaluate( $agent_id )['percent'];
	}
}
