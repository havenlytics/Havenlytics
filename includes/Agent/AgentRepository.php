<?php
/**
 * Agent profile repository.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

use HvnlyNab\Agent\Contracts\AgentRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Reads normalized agent profile data from the Agent CPT.
 *
 * @since 3.0.2
 */
class AgentRepository implements AgentRepositoryInterface {

	/**
	 * {@inheritdoc}
	 */
	public function get( int $agent_id ): ?array {
		if ( ! $this->is_valid_agent( $agent_id ) ) {
			return null;
		}

		$post = get_post( $agent_id );
		if ( ! $post ) {
			return null;
		}

		$avatar_id = (int) get_post_thumbnail_id( $agent_id );
		$avatar    = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'medium' ) : '';

		if ( ! $avatar ) {
			$avatar = includes_url( 'images/media/default.png' );
		}

		$fields = AgentFields::get_profile( $agent_id );

		$data = array(
			'id'           => $agent_id,
			'type'         => 'cpt',
			'name'         => get_the_title( $agent_id ),
			'email'        => $fields['email'],
			'phone'        => $fields['phone'],
			'mobile'       => $fields['mobile'],
			'fax'          => $fields['fax'],
			'office'       => $fields['office'],
			'whatsapp'     => $fields['whatsapp'],
			'position'     => $fields['position'],
			'company'      => $fields['company'],
			'license'      => $fields['license'],
			'address'      => $fields['address'],
			'biography'    => (string) $post->post_content,
			'website'      => $fields['website'],
			'vimeo'        => $fields['vimeo'],
			'facebook'     => $fields['facebook'],
			'twitter'      => $fields['twitter'],
			'pinterest'    => $fields['pinterest'],
			'instagram'    => $fields['instagram'],
			'youtube'      => $fields['youtube'],
			'linkedin'     => $fields['linkedin'],
			'tiktok'       => $fields['tiktok'],
			'avatar'       => (string) $avatar,
			'avatar_id'    => $avatar_id,
			'profile_url'  => (string) get_permalink( $agent_id ),
			'excerpt'      => (string) get_the_excerpt( $post ),
			'linked_user'  => absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) ),
			'agency'       => $this->get_agency_data( $agent_id ),
			'extensions'   => $this->get_extensions( $agent_id ),
			'availability_status' => AgentFields::get_availability( $agent_id ),
			'rating'       => 0.0,
			'review_count' => 0,
		);

		if ( '' === trim( $data['position'] ) ) {
			$data['position'] = __( 'Real Estate Agent', 'havenlytics' );
		}

		if ( '' === trim( $data['company'] ) && ! empty( $data['agency']['name'] ) ) {
			$data['company'] = (string) $data['agency']['name'];
		}

		/**
		 * Filter normalized agent profile data.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, mixed> $data     Agent profile.
		 * @param int                  $agent_id Agent post ID.
		 */
		return apply_filters( 'hvnly_agent_profile', $data, $agent_id );
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_valid_agent( int $agent_id ): bool {
		if ( $agent_id <= 0 ) {
			return false;
		}

		return AgentConstants::POST_TYPE === get_post_type( $agent_id )
			&& 'publish' === get_post_status( $agent_id );
	}

	/**
	 * {@inheritdoc}
	 */
	public function list_agents( array $args = array() ): array {
		$defaults = array(
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$query_args = wp_parse_args( $args, $defaults );
		$query_args['post_type'] = AgentConstants::POST_TYPE;

		$query = new \WP_Query( $query_args );
		$items = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$profile = $this->get( (int) $post->ID );
				if ( is_array( $profile ) ) {
					$items[] = $profile;
				}
			}
		}

		wp_reset_postdata();

		return $items;
	}

	/**
	 * @param int $agent_id Agent post ID.
	 * @return array<string, mixed>
	 */
	private function get_agency_data( int $agent_id ): array {
		return AgencyFields::resolve_for_agent( $agent_id );
	}

	/**
	 * @param int $agent_id Agent post ID.
	 * @return array<string, mixed>
	 */
	private function get_extensions( int $agent_id ): array {
		$raw = get_post_meta( $agent_id, AgentConstants::META_EXTENSIONS, true );

		return is_array( $raw ) ? $raw : array();
	}
}
