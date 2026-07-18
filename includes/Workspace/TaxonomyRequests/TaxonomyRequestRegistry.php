<?php
/**
 * Requestable taxonomy registry and policies.
 *
 * @package HvnlyNab\Workspace\TaxonomyRequests
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\TaxonomyRequests;

defined( 'ABSPATH' ) || exit;

final class TaxonomyRequestRegistry {

	/**
	 * @return array<string, array<string, string>>
	 */
	public function all(): array {
		$items = array(
			'hvnly_prop_depts'    => array(
				'key'    => 'propertyDepartment',
				'label'  => __( 'Property Department', 'havenlytics' ),
				'policy' => TaxonomyRequestConstants::POLICY_APPROVAL,
			),
			'hvnly_prop_types'    => array(
				'key'    => 'propertyType',
				'label'  => __( 'Property Type', 'havenlytics' ),
				'policy' => TaxonomyRequestConstants::POLICY_APPROVAL,
			),
			'hvnly_prop_status'   => array(
				'key'    => 'propertyStatus',
				'label'  => __( 'Property Status', 'havenlytics' ),
				'policy' => TaxonomyRequestConstants::POLICY_APPROVAL,
			),
			'hvnly_prop_features' => array(
				'key'    => 'propertyFeaturesTax',
				'label'  => __( 'Property Feature', 'havenlytics' ),
				'policy' => TaxonomyRequestConstants::POLICY_APPROVAL,
			),
			'hvnly_prop_locations' => array(
				'key'    => 'propertyLocations',
				'label'  => __( 'Property Location', 'havenlytics' ),
				'policy' => TaxonomyRequestConstants::POLICY_APPROVAL,
			),
			'hvnly_prop_tags'     => array(
				'key'    => 'propertyTags',
				'label'  => __( 'Property Tag', 'havenlytics' ),
				'policy' => TaxonomyRequestConstants::POLICY_APPROVAL,
			),
			'hvnly_prop_badges'   => array(
				'key'    => 'propertyBadges',
				'label'  => __( 'Property Badge', 'havenlytics' ),
				'policy' => TaxonomyRequestConstants::POLICY_APPROVAL,
			),
		);

		/**
		 * Filter requestable taxonomies and their policies.
		 *
		 * Policies are approval, auto, and admin_only. The auto policy emits an
		 * action after persistence; agent requests never create terms directly.
		 *
		 * @since 3.3.0
		 *
		 * @param array<string, array<string, string>> $items Registry.
		 */
		$items = (array) apply_filters( 'hvnly_taxonomy_request_taxonomies', $items );

		$valid = array();
		foreach ( $items as $taxonomy => $item ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( '' === $taxonomy || ! is_array( $item ) ) {
				continue;
			}
			$policy = sanitize_key( (string) ( $item['policy'] ?? '' ) );
			if ( ! in_array( $policy, array( TaxonomyRequestConstants::POLICY_APPROVAL, TaxonomyRequestConstants::POLICY_AUTO, TaxonomyRequestConstants::POLICY_ADMIN_ONLY ), true ) ) {
				$policy = TaxonomyRequestConstants::POLICY_APPROVAL;
			}
			$key                = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $item['key'] ?? $taxonomy ) );
			$valid[ $taxonomy ] = array(
				'key'    => is_string( $key ) && '' !== $key ? $key : $taxonomy,
				'label'  => sanitize_text_field( (string) ( $item['label'] ?? $taxonomy ) ),
				'policy' => $policy,
			);
		}

		return $valid;
	}

	/**
	 * @param string $taxonomy Taxonomy.
	 * @return array<string, string>|null
	 */
	public function get( string $taxonomy ): ?array {
		$all      = $this->all();
		$taxonomy = sanitize_key( $taxonomy );
		return isset( $all[ $taxonomy ] ) ? $all[ $taxonomy ] : null;
	}

	/**
	 * Resolve either a taxonomy slug or Workspace schema key.
	 *
	 * @param string $value Slug or key.
	 * @return string
	 */
	public function resolve_taxonomy( string $value ): string {
		$value = sanitize_key( $value );
		foreach ( $this->all() as $taxonomy => $item ) {
			if ( $taxonomy === $value || sanitize_key( (string) $item['key'] ) === $value ) {
				return $taxonomy;
			}
		}
		return '';
	}

	/**
	 * Data safe for Workspace localization.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function for_client(): array {
		$out = array();
		foreach ( $this->all() as $taxonomy => $item ) {
			if ( TaxonomyRequestConstants::POLICY_ADMIN_ONLY === $item['policy'] ) {
				continue;
			}
			$out[] = array(
				'taxonomy' => $taxonomy,
				'key'      => $item['key'],
				'label'    => $item['label'],
				'policy'   => $item['policy'],
			);
		}
		return $out;
	}
}
