<?php
/**
 * Seeds FAQ, repeater, agents, and property features demo content during Property Setup import.
 *
 * @package     Havenlytics
 * @subpackage  Admin
 * @since       3.1.0
 */

namespace HvnlyNab\Admin;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Agent\PropertyAgentResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Populates FAQ / repeater / agents / features group meta during import using known base IDs.
 */
final class DemoImportContentSeeder {

	/**
	 * @var array<int, array{question: string, answer: string}>|null
	 */
	private static $faq_pool = null;

	/**
	 * @var array<int, array{title: string, value: string, icon: string}>|null
	 */
	private static $repeater_pool = null;

	/**
	 * @var array<int, string>|null
	 */
	private static $features_pool = null;

	/**
	 * Merge FAQ and repeater demo meta before property insert.
	 *
	 * @param array<string, mixed>  $meta_input       Meta input for wp_insert_post.
	 * @param array<string, string> $field_map_groups Group ID => base ID map (by reference).
	 * @param array<string, string> $field_map_legacy Legacy type => base ID map (by reference).
	 * @param array<string, mixed>  $data             Property import payload.
	 * @return void
	 */
	public function enrich_meta_input( array &$meta_input, array &$field_map_groups, array &$field_map_legacy, array $data ): void {
		$property_index = isset( $data['demo_property_index'] ) ? absint( $data['demo_property_index'] ) : 0;

		if ( ! empty( $field_map_legacy['faq'] ) ) {
			$this->seed_faq_for_base( $meta_input, $field_map_legacy['faq'], $property_index );
		}

		if ( ! empty( $field_map_legacy['repeater'] ) ) {
			$this->seed_repeater_for_base( $meta_input, $field_map_legacy['repeater'], $property_index );
		}

		if ( ! empty( $field_map_legacy['agents'] ) ) {
			$this->seed_agents_title_for_base( $meta_input, $field_map_legacy['agents'] );
		}

		if ( ! empty( $field_map_legacy['features'] ) ) {
			$this->seed_features_for_base( $meta_input, $field_map_legacy['features'], $property_index );
		}
	}

	/**
	 * Assign agents group meta after the property exists.
	 *
	 * @param int                  $post_id     Property post ID.
	 * @param array<string, mixed> $data        Property import payload.
	 * @param string               $agents_base Group base ID.
	 * @return void
	 */
	public function seed_agents_group( int $post_id, array $data, string $agents_base = '' ): void {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}

		$agents_base = sanitize_key( $agents_base );
		if ( '' === $agents_base ) {
			$agents_base = $this->resolve_agents_base_from_post( $post_id );
		}

		if ( '' === $agents_base ) {
			return;
		}

		$agents_key = $agents_base . '_agents';
		if ( $this->stored_agents_has_data( get_post_meta( $post_id, $agents_key, true ) ) ) {
			return;
		}

		$agent_ids = PropertyAgentResolver::get_assigned_agent_ids( $post_id );
		if ( empty( $agent_ids ) ) {
			$property_index = isset( $data['demo_property_index'] ) ? absint( $data['demo_property_index'] ) : 0;
			$agent_ids      = $this->resolve_published_agent_ids( $property_index, 1 );
			if ( ! empty( $agent_ids ) ) {
				update_post_meta( $post_id, AgentConstants::META_PROPERTY_AGENTS, $agent_ids );
			}
		}

		if ( empty( $agent_ids ) ) {
			return;
		}

		update_post_meta( $post_id, $agents_key, wp_json_encode( array_values( $agent_ids ) ) );
	}

	/**
	 * @param array<string, mixed> $meta_input     Meta input.
	 * @param string               $base           Group base ID.
	 * @param int                  $property_index Demo template index.
	 * @return void
	 */
	private function seed_faq_for_base( array &$meta_input, string $base, int $property_index ): void {
		$base = sanitize_key( $base );
		if ( '' === $base ) {
			return;
		}

		$meta_key = $base . '_faqs';
		if ( $this->meta_input_has_json_items( $meta_input, $meta_key ) ) {
			return;
		}

		$title_key = $base . '_title';
		if ( ! $this->meta_input_has_scalar( $meta_input, $title_key ) ) {
			$meta_input[ $title_key ] = __( 'Buyer Questions', 'havenlytics' );
		}

		$meta_input[ $meta_key ] = wp_json_encode( $this->build_faq_items( $property_index ) );
	}

	/**
	 * @param array<string, mixed> $meta_input     Meta input.
	 * @param string               $base           Group base ID.
	 * @param int                  $property_index Demo template index.
	 * @return void
	 */
	private function seed_repeater_for_base( array &$meta_input, string $base, int $property_index ): void {
		$base = sanitize_key( $base );
		if ( '' === $base ) {
			return;
		}

		$meta_key = $base . '_items';
		if ( $this->meta_input_has_json_items( $meta_input, $meta_key ) ) {
			return;
		}

		$title_key = $base . '_title';
		if ( ! $this->meta_input_has_scalar( $meta_input, $title_key ) ) {
			$meta_input[ $title_key ] = __( 'Neighborhood Highlights', 'havenlytics' );
		}

		$meta_input[ $meta_key ] = wp_json_encode( $this->build_repeater_items( $property_index ) );
	}

	/**
	 * @param array<string, mixed> $meta_input Meta input.
	 * @param string               $base       Group base ID.
	 * @return void
	 */
	private function seed_agents_title_for_base( array &$meta_input, string $base ): void {
		$base = sanitize_key( $base );
		if ( '' === $base ) {
			return;
		}

		$title_key = $base . '_title';
		if ( ! $this->meta_input_has_scalar( $meta_input, $title_key ) ) {
			$meta_input[ $title_key ] = __( 'Property Agents', 'havenlytics' );
		}
	}

	/**
	 * @param array<string, mixed> $meta_input     Meta input.
	 * @param string               $base           Group base ID.
	 * @param int                  $property_index Demo template index.
	 * @return void
	 */
	private function seed_features_for_base( array &$meta_input, string $base, int $property_index ): void {
		$base = sanitize_key( $base );
		if ( '' === $base ) {
			return;
		}

		$meta_key = $base . '_features';
		if ( $this->meta_input_has_json_items( $meta_input, $meta_key ) ) {
			return;
		}

		$meta_input[ $meta_key ] = wp_json_encode( $this->build_feature_items( $property_index ) );
	}

	/**
	 * @param int $property_index Demo property template index.
	 * @return array<int, string>
	 */
	private function build_feature_items( int $property_index ): array {
		$pool   = $this->get_features_pool();
		$count  = min( 6 + ( $property_index % 5 ), count( $pool ) );
		$offset = $property_index % max( 1, count( $pool ) - $count + 1 );

		return array_values( array_slice( $pool, $offset, $count ) );
	}

	/**
	 * @return array<int, string>
	 */
	private function get_features_pool(): array {
		if ( null !== self::$features_pool ) {
			return self::$features_pool;
		}

		self::$features_pool = array(
			__( 'Air Conditioning', 'havenlytics' ),
			__( 'Swimming Pool', 'havenlytics' ),
			__( 'Garage', 'havenlytics' ),
			__( 'Garden', 'havenlytics' ),
			__( 'Balcony', 'havenlytics' ),
			__( 'Gym', 'havenlytics' ),
			__( 'Security System', 'havenlytics' ),
			__( 'Elevator', 'havenlytics' ),
			__( 'Central Heating', 'havenlytics' ),
			__( 'CCTV Security', 'havenlytics' ),
		);

		return self::$features_pool;
	}

	/**
	 * @param int $post_id Property post ID.
	 * @return string
	 */
	private function resolve_agents_base_from_post( int $post_id ): string {
		$map_json = get_post_meta( $post_id, '_hvnly_field_map', true );
		if ( is_string( $map_json ) && '' !== $map_json ) {
			$decoded = json_decode( $map_json, true );
			if ( is_array( $decoded ) && ! empty( $decoded['legacy']['agents'] ) ) {
				return sanitize_key( (string) $decoded['legacy']['agents'] );
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $meta_input Meta input.
	 * @param string               $meta_key   Meta key.
	 * @return bool
	 */
	private function meta_input_has_json_items( array $meta_input, string $meta_key ): bool {
		if ( ! isset( $meta_input[ $meta_key ] ) ) {
			return false;
		}

		$value = $meta_input[ $meta_key ];
		if ( is_array( $value ) ) {
			return ! empty( $value );
		}

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return false;
		}

		$decoded = json_decode( $value, true );

		return is_array( $decoded ) && ! empty( $decoded );
	}

	/**
	 * @param array<string, mixed> $meta_input Meta input.
	 * @param string               $meta_key   Meta key.
	 * @return bool
	 */
	private function meta_input_has_scalar( array $meta_input, string $meta_key ): bool {
		return isset( $meta_input[ $meta_key ] ) && '' !== trim( (string) $meta_input[ $meta_key ] );
	}

	/**
	 * @param mixed $stored Stored meta value.
	 * @return bool
	 */
	private function stored_agents_has_data( $stored ): bool {
		if ( empty( $stored ) ) {
			return false;
		}

		if ( is_array( $stored ) ) {
			return ! empty( array_filter( array_map( 'absint', $stored ) ) );
		}

		$decoded = json_decode( (string) $stored, true );

		return is_array( $decoded ) && ! empty( array_filter( array_map( 'absint', $decoded ) ) );
	}

	/**
	 * @param int $property_index Demo property template index.
	 * @return array<int, array{question: string, answer: string}>
	 */
	private function build_faq_items( int $property_index ): array {
		$pool = $this->get_faq_pool();
		$pool = array_values( $pool );

		$count = 3 + ( $property_index % 3 );
		$count = min( $count, count( $pool ) );

		$offset = $property_index % max( 1, count( $pool ) - $count + 1 );
		$slice  = array_slice( $pool, $offset, $count );

		if ( count( $slice ) < $count ) {
			$slice = array_merge( $slice, array_slice( $pool, 0, $count - count( $slice ) ) );
		}

		return array_values( $slice );
	}

	/**
	 * @param int $property_index Demo property template index.
	 * @return array<int, array{title: string, value: string, icon: string}>
	 */
	private function build_repeater_items( int $property_index ): array {
		$pool = $this->get_repeater_pool();

		$count  = min( 5, count( $pool ) );
		$offset = $property_index % max( 1, count( $pool ) - $count + 1 );

		return array_values( array_slice( $pool, $offset, $count ) );
	}

	/**
	 * @return array<int, array{question: string, answer: string}>
	 */
	private function get_faq_pool(): array {
		if ( null !== self::$faq_pool ) {
			return self::$faq_pool;
		}

		self::$faq_pool = array(
			array(
				'question' => __( 'Is financing available?', 'havenlytics' ),
				'answer'   => __( 'Yes, financing options may be available through approved lenders.', 'havenlytics' ),
			),
			array(
				'question' => __( 'Can I schedule a property visit?', 'havenlytics' ),
				'answer'   => __( 'Yes, contact the agent to arrange a viewing.', 'havenlytics' ),
			),
			array(
				'question' => __( 'Are pets allowed?', 'havenlytics' ),
				'answer'   => __( 'Please check with the property agent for pet policies.', 'havenlytics' ),
			),
			array(
				'question' => __( 'Is parking included?', 'havenlytics' ),
				'answer'   => __( 'Parking availability varies by property.', 'havenlytics' ),
			),
			array(
				'question' => __( 'What documents are required?', 'havenlytics' ),
				'answer'   => __( 'Standard identity and financial documents may be required.', 'havenlytics' ),
			),
		);

		return self::$faq_pool;
	}

	/**
	 * @return array<int, array{title: string, value: string, icon: string}>
	 */
	private function get_repeater_pool(): array {
		if ( null !== self::$repeater_pool ) {
			return self::$repeater_pool;
		}

		self::$repeater_pool = array(
			array(
				'title' => __( 'School', 'havenlytics' ),
				'value' => '1.2 km',
				'icon'  => 'school',
			),
			array(
				'title' => __( 'Hospital', 'havenlytics' ),
				'value' => '2.5 km',
				'icon'  => 'hospital',
			),
			array(
				'title' => __( 'Shopping Mall', 'havenlytics' ),
				'value' => '3 km',
				'icon'  => 'shopping-bag',
			),
			array(
				'title' => __( 'Airport', 'havenlytics' ),
				'value' => '12 km',
				'icon'  => 'plane',
			),
			array(
				'title' => __( 'Metro Station', 'havenlytics' ),
				'value' => '800 m',
				'icon'  => 'train',
			),
		);

		return self::$repeater_pool;
	}

	/**
	 * @param int $property_index Demo property template index.
	 * @param int $limit          Max agents to return.
	 * @return int[]
	 */
	private function resolve_published_agent_ids( int $property_index, int $limit = 1 ): array {
		$limit = max( 1, $limit );

		if ( ! post_type_exists( AgentConstants::POST_TYPE ) ) {
			return array();
		}

		$agent_ids = get_posts(
			array(
				'post_type'              => AgentConstants::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 50,
				'fields'                 => 'ids',
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $agent_ids ) ) {
			return array();
		}

		$agent_ids = array_values( array_filter( array_map( 'absint', $agent_ids ) ) );
		if ( empty( $agent_ids ) ) {
			return array();
		}

		if ( 1 === count( $agent_ids ) || 0 === $property_index % 2 ) {
			return array_slice( $agent_ids, 0, $limit );
		}

		$pick = $agent_ids[ array_rand( $agent_ids ) ];

		return array( absint( $pick ) );
	}
}
