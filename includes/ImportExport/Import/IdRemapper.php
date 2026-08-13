<?php
/**
 * Logical identity → local ID remap tables for HPTP import.
 *
 * @package HvnlyNab\ImportExport\Import
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Import;

defined( 'ABSPATH' ) || exit;

/**
 * IdRemapper — never treats source-site database IDs as authoritative.
 *
 * @since 3.6.0
 */
final class IdRemapper {

	/**
	 * @var array<string, int> "{taxonomy}:{slug}" => term_id
	 */
	private $terms = array();

	/**
	 * @var array<string, int> agency slug => term_id
	 */
	private $agencies = array();

	/**
	 * @var array<string, int> agent slug => post_id
	 */
	private $agents_by_slug = array();

	/**
	 * @var array<string, int> agent email (lowercase) => post_id
	 */
	private $agents_by_email = array();

	/**
	 * @var array<string, int> unique property id => post_id
	 */
	private $properties_by_unique = array();

	/**
	 * @var array<string, int> property slug => post_id
	 */
	private $properties_by_slug = array();

	/**
	 * @var array<string, int> media export_key => local attachment_id
	 */
	private $media_by_export_key = array();

	/**
	 * @param string $taxonomy Taxonomy.
	 * @param string $slug     Term slug.
	 * @param int    $term_id  Local term ID.
	 * @return void
	 */
	public function set_term( string $taxonomy, string $slug, int $term_id ): void {
		$taxonomy = sanitize_key( $taxonomy );
		$slug     = sanitize_title( $slug );
		if ( '' === $taxonomy || '' === $slug || $term_id <= 0 ) {
			return;
		}
		$this->terms[ $taxonomy . ':' . $slug ] = $term_id;
	}

	/**
	 * @param string $taxonomy Taxonomy.
	 * @param string $slug     Term slug.
	 * @return int Local term ID or 0.
	 */
	public function get_term( string $taxonomy, string $slug ): int {
		$key = sanitize_key( $taxonomy ) . ':' . sanitize_title( $slug );
		return isset( $this->terms[ $key ] ) ? (int) $this->terms[ $key ] : 0;
	}

	/**
	 * @param string $slug    Agency slug.
	 * @param int    $term_id Local term ID.
	 * @return void
	 */
	public function set_agency( string $slug, int $term_id ): void {
		$slug = sanitize_title( $slug );
		if ( '' === $slug || $term_id <= 0 ) {
			return;
		}
		$this->agencies[ $slug ] = $term_id;
	}

	/**
	 * @param string $slug Agency slug.
	 * @return int
	 */
	public function get_agency( string $slug ): int {
		$slug = sanitize_title( $slug );
		return isset( $this->agencies[ $slug ] ) ? (int) $this->agencies[ $slug ] : 0;
	}

	/**
	 * @param string $slug    Agent slug.
	 * @param string $email   Agent email.
	 * @param int    $post_id Local post ID.
	 * @return void
	 */
	public function set_agent( string $slug, string $email, int $post_id ): void {
		if ( $post_id <= 0 ) {
			return;
		}
		$slug  = sanitize_title( $slug );
		$email = strtolower( sanitize_email( $email ) );
		if ( '' !== $slug ) {
			$this->agents_by_slug[ $slug ] = $post_id;
		}
		if ( '' !== $email ) {
			$this->agents_by_email[ $email ] = $post_id;
		}
	}

	/**
	 * Resolve agent by email then slug (match order).
	 *
	 * @param string $email Email.
	 * @param string $slug Slug.
	 * @return int
	 */
	public function get_agent( string $email = '', string $slug = '' ): int {
		$email = strtolower( sanitize_email( $email ) );
		$slug  = sanitize_title( $slug );
		if ( '' !== $email && isset( $this->agents_by_email[ $email ] ) ) {
			return (int) $this->agents_by_email[ $email ];
		}
		if ( '' !== $slug && isset( $this->agents_by_slug[ $slug ] ) ) {
			return (int) $this->agents_by_slug[ $slug ];
		}
		return 0;
	}

	/**
	 * @param string $unique_id Unique property ID.
	 * @param string $slug      Property slug.
	 * @param int    $post_id   Local post ID.
	 * @return void
	 */
	public function set_property( string $unique_id, string $slug, int $post_id ): void {
		if ( $post_id <= 0 ) {
			return;
		}
		$unique_id = trim( $unique_id );
		$slug      = sanitize_title( $slug );
		if ( '' !== $unique_id ) {
			$this->properties_by_unique[ $unique_id ] = $post_id;
		}
		if ( '' !== $slug ) {
			$this->properties_by_slug[ $slug ] = $post_id;
		}
	}

	/**
	 * Resolve property by unique ID then slug.
	 *
	 * @param string $unique_id Unique ID.
	 * @param string $slug      Slug.
	 * @return int
	 */
	public function get_property( string $unique_id = '', string $slug = '' ): int {
		$unique_id = trim( $unique_id );
		$slug      = sanitize_title( $slug );
		if ( '' !== $unique_id && isset( $this->properties_by_unique[ $unique_id ] ) ) {
			return (int) $this->properties_by_unique[ $unique_id ];
		}
		if ( '' !== $slug && isset( $this->properties_by_slug[ $slug ] ) ) {
			return (int) $this->properties_by_slug[ $slug ];
		}
		return 0;
	}

	/**
	 * @param string $export_key Package media export_key.
	 * @param int    $attachment_id Local attachment ID.
	 * @return void
	 */
	public function set_media( string $export_key, int $attachment_id ): void {
		$export_key = trim( $export_key );
		if ( '' === $export_key || $attachment_id <= 0 ) {
			return;
		}
		$this->media_by_export_key[ $export_key ] = $attachment_id;
	}

	/**
	 * @param string $export_key Export key.
	 * @return int
	 */
	public function get_media( string $export_key ): int {
		$export_key = trim( $export_key );
		return isset( $this->media_by_export_key[ $export_key ] )
			? (int) $this->media_by_export_key[ $export_key ]
			: 0;
	}

	/**
	 * Restore maps from a previous job snapshot.
	 *
	 * @param array<string, mixed> $data Snapshot from to_array().
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$mapper = new self();
		if ( ! empty( $data['terms'] ) && is_array( $data['terms'] ) ) {
			foreach ( $data['terms'] as $key => $term_id ) {
				$parts = explode( ':', (string) $key, 2 );
				if ( 2 === count( $parts ) ) {
					$mapper->set_term( $parts[0], $parts[1], (int) $term_id );
				}
			}
		}
		if ( ! empty( $data['agencies'] ) && is_array( $data['agencies'] ) ) {
			foreach ( $data['agencies'] as $slug => $term_id ) {
				$mapper->set_agency( (string) $slug, (int) $term_id );
			}
		}
		$by_slug   = isset( $data['agents_by_slug'] ) && is_array( $data['agents_by_slug'] ) ? $data['agents_by_slug'] : array();
		$by_email  = isset( $data['agents_by_email'] ) && is_array( $data['agents_by_email'] ) ? $data['agents_by_email'] : array();
		$agent_ids = array();
		foreach ( $by_slug as $slug => $post_id ) {
			$agent_ids[ (int) $post_id ]['slug'] = (string) $slug;
		}
		foreach ( $by_email as $email => $post_id ) {
			$agent_ids[ (int) $post_id ]['email'] = (string) $email;
		}
		foreach ( $agent_ids as $post_id => $parts ) {
			$mapper->set_agent(
				isset( $parts['slug'] ) ? $parts['slug'] : '',
				isset( $parts['email'] ) ? $parts['email'] : '',
				(int) $post_id
			);
		}
		$props_u  = isset( $data['properties_by_unique'] ) && is_array( $data['properties_by_unique'] ) ? $data['properties_by_unique'] : array();
		$props_s  = isset( $data['properties_by_slug'] ) && is_array( $data['properties_by_slug'] ) ? $data['properties_by_slug'] : array();
		$prop_ids = array();
		foreach ( $props_u as $unique => $post_id ) {
			$prop_ids[ (int) $post_id ]['unique'] = (string) $unique;
		}
		foreach ( $props_s as $slug => $post_id ) {
			$prop_ids[ (int) $post_id ]['slug'] = (string) $slug;
		}
		foreach ( $prop_ids as $post_id => $parts ) {
			$mapper->set_property(
				isset( $parts['unique'] ) ? $parts['unique'] : '',
				isset( $parts['slug'] ) ? $parts['slug'] : '',
				(int) $post_id
			);
		}
		if ( ! empty( $data['media_by_export_key'] ) && is_array( $data['media_by_export_key'] ) ) {
			foreach ( $data['media_by_export_key'] as $key => $attachment_id ) {
				$mapper->set_media( (string) $key, (int) $attachment_id );
			}
		}
		return $mapper;
	}

	/**
	 * Snapshot for reports / later phases.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'terms'                => $this->terms,
			'agencies'             => $this->agencies,
			'agents_by_slug'       => $this->agents_by_slug,
			'agents_by_email'      => $this->agents_by_email,
			'properties_by_unique' => $this->properties_by_unique,
			'properties_by_slug'   => $this->properties_by_slug,
			'media_by_export_key'  => $this->media_by_export_key,
		);
	}
}
