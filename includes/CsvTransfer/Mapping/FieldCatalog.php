<?php
/**
 * CSV mapping target catalog — Property Editor as source of truth.
 *
 * @package HvnlyNab\CsvTransfer\Mapping
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Mapping;

use HvnlyNab\Workspace\Api\PropertyBuilderSchemaService;

defined( 'ABSPATH' ) || exit;

/**
 * FieldCatalog — Mapping dropdown fields.
 *
 * CSV catalog is the explicit {@see CsvSupportedSchema} allowlist:
 * default Havenlytics property fields + Gallery / Video / Location groups.
 * Custom Builder fields and unsupported groups are excluded from the catalog
 * (legacy import targets remain resolvable via MappingResolver).
 *
 * @since 3.7.0
 */
final class FieldCatalog {

	/**
	 * Group keys used by the Mapping dropdown (stable API).
	 *
	 * @return array<string, string> group => translated label
	 */
	public static function group_labels(): array {
		return array(
			'property'   => __( 'Property Information', 'havenlytics' ),
			'pricing'    => __( 'Pricing', 'havenlytics' ),
			'location'   => __( 'Location', 'havenlytics' ),
			'contact'    => __( 'Contact', 'havenlytics' ),
			'media'      => __( 'Media', 'havenlytics' ),
			'details'    => __( 'Property Details', 'havenlytics' ),
			'taxonomies' => __( 'Taxonomies', 'havenlytics' ),
			'agents'     => __( 'Agents', 'havenlytics' ),
			'seo'        => __( 'SEO', 'havenlytics' ),
			'advanced'   => __( 'Advanced', 'havenlytics' ),
			'builder'    => __( 'Custom Builder Fields', 'havenlytics' ),
			// Legacy group keys kept for saved profiles / older responses.
			'custom'     => __( 'Custom Builder Fields', 'havenlytics' ),
			'content'    => __( 'Property Information', 'havenlytics' ),
			'taxonomy'   => __( 'Taxonomies', 'havenlytics' ),
			'people'     => __( 'Agents', 'havenlytics' ),
			'dynamic'    => __( 'Custom Builder Fields', 'havenlytics' ),
		);
	}

	/**
	 * Headers used by the Download Sample CSV helper.
	 *
	 * Labels match MappingResolver / Havenlytics preset aliases so auto-map works.
	 * Values are filled from DemoData::get_demo_properties_data() (+ demo images / agents).
	 *
	 * @return array<int, string>
	 */
	public static function sample_headers(): array {
		return array(
			'Title',
			'Description',
			'Excerpt',
			'Slug',
			'Publish Status',
			'Price',
			'Department',
			'Property Type',
			'Property Status',
			'Location Setting',
			'City',
			'State',
			'ZIP',
			'Country',
			'Address Line 1',
			'Address Line 2',
			'Map Address',
			'Latitude',
			'Longitude',
			'Bedrooms',
			'Bathrooms',
			'Half Baths',
			'Area (sq ft)',
			'Lot Size',
			'Year Built',
			'Garage Square Footage',
			'Reception Rooms',
			'Total Rooms',
			'Kitchen',
			'Floors',
			'Heating',
			'Cooling',
			'Water',
			'Tax Amount',
			'Tags',
			'Features',
			'Amenities',
			'Badges',
			'Featured Property',
			'Featured Image',
			'Gallery Title',
			'Gallery',
			'Video Title',
			'Video URL',
			'Video Thumbnail',
			'Reference Number',
			'MLS',
		);
	}

	/**
	 * Sample CSV data rows from the first N Havenlytics onboarding demo properties.
	 *
	 * Single source of truth: DemoData::get_demo_properties_data().
	 * Media URLs: DemoData::get_property_images().
	 * Agent emails: DemoAgentAgencyData assignments (same index as onboarding).
	 *
	 * @param int $limit Max properties (default 10).
	 * @return array<int, array<int, string>>
	 */
	public static function sample_rows_from_demo( int $limit = 10 ): array {
		if ( ! class_exists( '\HvnlyNab\Admin\Data\DemoData' ) ) {
			return array();
		}

		$properties = \HvnlyNab\Admin\Data\DemoData::get_demo_properties_data();
		if ( ! is_array( $properties ) || empty( $properties ) ) {
			return array();
		}

		$limit  = max( 1, min( 10, $limit ) );
		$slice  = array_slice( $properties, 0, $limit );
		$images = \HvnlyNab\Admin\Data\DemoData::get_property_images();
		$images = is_array( $images ) ? array_values( array_filter( array_map( 'strval', $images ) ) ) : array();
		$rows   = array();
		$index  = 0;

		foreach ( $slice as $property ) {
			if ( ! is_array( $property ) ) {
				continue;
			}
			$rows[] = self::demo_property_to_sample_row( $property, $images, $index );
			++$index;
		}

		return $rows;
	}

	/**
	 * Map one DemoData property into a sample CSV row (same column order as headers).
	 *
	 * @param array<string, mixed> $property Demo property record.
	 * @param array<int, string>   $images   Demo property image pool.
	 * @param int                  $index    Zero-based property index in the sample.
	 * @return array<int, string>
	 */
	private static function demo_property_to_sample_row( array $property, array $images, int $index ): array {
		$department = self::humanize_demo_slug( (string) ( $property['department'] ?? '' ) );
		$type       = self::humanize_demo_slug( (string) ( $property['type'] ?? '' ) );
		$status     = self::humanize_demo_slug( (string) ( $property['status'] ?? '' ) );

		if ( empty( $type ) && ! empty( $property['property_types'] ) && is_array( $property['property_types'] ) ) {
			$type = self::humanize_demo_slug( (string) reset( $property['property_types'] ) );
		}
		if ( empty( $status ) && ! empty( $property['property_status'] ) && is_array( $property['property_status'] ) ) {
			$status = self::humanize_demo_slug( (string) reset( $property['property_status'] ) );
		}

		$featured = ! empty( $property['featured'] ) ? '1' : '';

		return array(
			(string) ( $property['title'] ?? '' ),
			(string) ( $property['content'] ?? '' ),
			(string) ( $property['excerpt'] ?? '' ),
			'', // Slug — demo records use titles; WP generates on import.
			'publish',
			(string) ( $property['price'] ?? '' ),
			$department,
			$type,
			$status,
			self::humanize_demo_slug( (string) ( $property['location'] ?? '' ) ),
			(string) ( $property['city'] ?? '' ),
			(string) ( $property['state'] ?? '' ),
			(string) ( $property['zip'] ?? '' ),
			(string) ( $property['country'] ?? '' ),
			(string) ( $property['address'] ?? '' ),
			'', // Address Line 2.
			(string) ( $property['full_address'] ?? '' ),
			(string) ( $property['latitude'] ?? '' ),
			(string) ( $property['longitude'] ?? '' ),
			(string) ( $property['bedrooms'] ?? '' ),
			(string) ( $property['bathrooms'] ?? '' ),
			(string) ( $property['half_bathrooms'] ?? '' ),
			(string) ( $property['sqft'] ?? '' ),
			(string) ( $property['lot_size'] ?? '' ),
			(string) ( $property['year_built'] ?? '' ),
			(string) ( $property['garage_sqft'] ?? '' ),
			(string) ( $property['reception_rooms'] ?? '' ),
			(string) ( $property['total_rooms'] ?? '' ),
			(string) ( $property['kitchens'] ?? '' ),
			(string) ( $property['floors'] ?? '' ),
			self::humanize_demo_slug( (string) ( $property['heating'] ?? '' ) ),
			self::humanize_demo_slug( (string) ( $property['cooling'] ?? '' ) ),
			self::humanize_demo_slug( (string) ( $property['water'] ?? '' ) ),
			(string) ( $property['tax_amount'] ?? '' ),
			self::join_demo_list( $property['tags'] ?? array() ),
			self::join_demo_list( $property['features'] ?? array() ),
			'', // Amenities — not present on demo records.
			self::join_demo_list( $property['badges'] ?? array() ),
			$featured,
			self::demo_featured_image_url( $images, $index ),
			'', // Gallery Title.
			self::demo_gallery_image_urls( $images, $index, 3 ),
			'', // Video Title.
			'', // Video URL.
			'', // Video Thumbnail.
			'', // Reference Number.
			'', // MLS.
		);
	}

	/**
	 * Primary + co-agent emails for a demo property index (onboarding assignments).
	 *
	 * @param int $index Property template index.
	 * @return array{primary:string,co_agents:string}
	 */
	private static function demo_agent_emails_for_index( int $index ): array {
		$empty = array(
			'primary'   => '',
			'co_agents' => '',
		);

		if ( ! class_exists( '\HvnlyNab\Admin\Data\DemoAgentAgencyData' ) ) {
			return $empty;
		}

		$slugs = \HvnlyNab\Admin\Data\DemoAgentAgencyData::get_agent_slugs_for_property_index( $index );
		if ( empty( $slugs ) ) {
			return $empty;
		}

		$by_slug = array();
		foreach ( \HvnlyNab\Admin\Data\DemoAgentAgencyData::get_demo_agents() as $agent ) {
			if ( ! is_array( $agent ) || empty( $agent['slug'] ) ) {
				continue;
			}
			$by_slug[ (string) $agent['slug'] ] = $agent;
		}

		$emails = array();
		foreach ( $slugs as $slug ) {
			$email = isset( $by_slug[ $slug ]['email'] ) ? trim( (string) $by_slug[ $slug ]['email'] ) : '';
			if ( '' !== $email ) {
				$emails[] = $email;
			}
		}

		if ( empty( $emails ) ) {
			return $empty;
		}

		return array(
			'primary'   => $emails[0],
			'co_agents' => implode( '|', array_slice( $emails, 1 ) ),
		);
	}

	/**
	 * One featured image from the demo image pool (cycles by property index).
	 *
	 * @param array<int, string> $images Demo image URLs.
	 * @param int                $index  Property index.
	 * @return string
	 */
	private static function demo_featured_image_url( array $images, int $index ): string {
		$total = count( $images );
		if ( $total < 1 ) {
			return '';
		}
		return $images[ $index % $total ];
	}

	/**
	 * Pipe-separated gallery URLs cycling through the demo image pool.
	 *
	 * @param array<int, string> $images Demo image URLs.
	 * @param int                $index  Property index (gallery start offset).
	 * @param int                $count  Number of gallery images.
	 * @return string
	 */
	private static function demo_gallery_image_urls( array $images, int $index, int $count = 3 ): string {
		$total = count( $images );
		if ( $total < 1 || $count < 1 ) {
			return '';
		}

		$urls = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$urls[] = $images[ ( $index + $i ) % $total ];
		}

		return implode( '|', $urls );
	}

	/**
	 * @param mixed $list Tags/features list.
	 * @return string Pipe-separated values.
	 */
	private static function join_demo_list( $list ): string {
		if ( ! is_array( $list ) ) {
			return '';
		}
		$parts = array();
		foreach ( $list as $item ) {
			$item = trim( (string) $item );
			if ( '' !== $item ) {
				$parts[] = str_replace( '-', ' ', $item );
			}
		}
		return implode( '|', $parts );
	}

	/**
	 * @param string $slug Demo slug (e.g. for-sale, single-family).
	 * @return string
	 */
	private static function humanize_demo_slug( string $slug ): string {
		$slug = trim( $slug );
		if ( '' === $slug ) {
			return '';
		}

		$known = array(
			'sale'           => 'Sale',
			'rent'           => 'Rent',
			'commercial'     => 'Commercial',
			'let'            => 'Let',
			'for-sale'       => 'For Sale',
			'for-rent'       => 'For Rent',
			'for-lease'      => 'For Lease',
			'single-family'  => 'Single Family',
		);

		if ( isset( $known[ $slug ] ) ) {
			return $known[ $slug ];
		}

		return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
	}

	/**
	 * Supported CSV fields from SchemaTargets, ordered by CsvSupportedSchema.
	 *
	 * Does not scan the Property Builder schema (performance). Gallery / Video /
	 * Location group fields are always included via legacy + builder keys.
	 *
	 * @return array<int, array{id:string,label:string,group:string,required:bool,keywords?:string,source:string}>
	 */
	private static function default_fields(): array {
		$defs = SchemaTargets::logical_defs();
		$out  = array();

		foreach ( CsvSupportedSchema::field_ids() as $id ) {
			if ( ! isset( $defs[ $id ] ) ) {
				continue;
			}
			$def = $defs[ $id ];
			$row = array(
				'id'       => $id,
				'label'    => (string) ( $def['label'] ?? $id ),
				'group'    => (string) ( $def['group'] ?? 'advanced' ),
				'required' => ! empty( $def['required'] ),
				'source'   => 'default',
			);
			if ( ! empty( $def['keywords'] ) ) {
				$row['keywords'] = (string) $def['keywords'];
			}
			if ( ! empty( $def['section'] ) ) {
				$row['section'] = (string) $def['section'];
			}
			if ( ! empty( $def['section_label'] ) ) {
				$row['section_label'] = (string) $def['section_label'];
			}
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Permanent Builder preset fields (utils/presetFields.js).
	 *
	 * Semantic duplicates reuse existing catalog ids (bedrooms, bathrooms, area, city, zip)
	 * and are intentionally omitted here.
	 *
	 * @return array<int, array{id:string,label:string,group:string,required:bool,keywords?:string,source:string}>
	 */
	private static function permanent_preset_fields(): array {
		$f = static function ( string $id, string $label, string $group, string $keywords = '' ): array {
			$row = array(
				'id'       => $id,
				'label'    => $label,
				'group'    => $group,
				'required' => false,
				'source'   => 'preset',
			);
			if ( '' !== $keywords ) {
				$row['keywords'] = $keywords;
			}
			return $row;
		};

		// IDs match presetFields.js storage names exactly (stable for profiles).
		return array(
			$f( 'preset_hvnly_property_field_fullname', __( 'Full Name', 'havenlytics' ), 'contact', 'full name contact person' ),
			$f( 'preset_hvnly_property_field_email', __( 'Email Address', 'havenlytics' ), 'contact', 'email address contact' ),
			$f( 'preset_hvnly_property_field_phone', __( 'Phone Number', 'havenlytics' ), 'contact', 'phone telephone' ),
			$f( 'preset_hvnly_property_field_company', __( 'Company Name', 'havenlytics' ), 'contact', 'company brokerage' ),
			$f( 'preset_hvnly_property_field_website', __( 'Website', 'havenlytics' ), 'contact', 'website url homepage' ),
			// Bedrooms / Bathrooms / Square Feet / City / ZIP → reuse bedrooms, bathrooms, area, city, zip.
			$f( 'preset_hvnly_property_field_number_garage', __( 'Garage Spaces', 'havenlytics' ), 'details', 'garage spaces parking' ),
			$f( 'preset_hvnly_property_field_checkbox_pool', __( 'Swimming Pool', 'havenlytics' ), 'details', 'pool swimming amenities' ),
			$f( 'preset_hvnly_property_field_checkbox_garden', __( 'Garden', 'havenlytics' ), 'details', 'garden amenities' ),
			$f( 'preset_hvnly_property_field_checkbox_garage', __( 'Garage', 'havenlytics' ), 'details', 'garage amenity checkbox' ),
		);
	}

	/**
	 * Storage ids claimed by permanent presets (exclude from Builder · duplicates).
	 *
	 * @return array<string, true>
	 */
	private static function permanent_preset_ids(): array {
		static $ids = null;
		if ( null === $ids ) {
			$ids = array();
			foreach ( self::permanent_preset_fields() as $field ) {
				$ids[ $field['id'] ] = true;
			}
			// Preset ids that reuse existing catalog fields (never expose as separate targets).
			$ids['preset_hvnly_property_field_number_bedrooms']   = true;
			$ids['preset_hvnly_property_field_number_bathrooms']  = true;
			$ids['preset_hvnly_property_field_number_squarefeet'] = true;
			$ids['preset_hvnly_property_field_text_city']         = true;
			$ids['preset_hvnly_property_field_text_zipcode']      = true;
		}
		return $ids;
	}

	/**
	 * Custom Builder fields not already covered by the default catalog.
	 *
	 * @return array<int, array{id:string,label:string,group:string,required:bool,keywords?:string,source:string}>
	 */
	private static function builder_fields(): array {
		if ( ! class_exists( PropertyBuilderSchemaService::class ) ) {
			return array();
		}

		$reserved = SchemaTargets::reserved_storage_names();
		$presets  = self::permanent_preset_ids();
		$seen     = array();
		$out      = array();

		foreach ( PropertyBuilderSchemaService::collect_storage_fields() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( SchemaTargets::is_internal_storage_row( $row ) ) {
				continue;
			}

			$name = isset( $row['name'] ) ? (string) $row['name'] : '';
			if ( '' === $name || isset( $seen[ $name ] ) || isset( $reserved[ $name ] ) || isset( $presets[ $name ] ) ) {
				continue;
			}

			$seen[ $name ] = true;
			$label         = isset( $row['label'] ) && '' !== trim( (string) $row['label'] )
				? sanitize_text_field( (string) $row['label'] )
				: $name;

			$out[] = array(
				'id'       => $name,
				'label'    => sprintf(
					/* translators: %s: Builder field label */
					__( 'Builder · %s', 'havenlytics' ),
					$label
				),
				'group'    => 'builder',
				'required' => false,
				'source'   => 'builder',
				'keywords' => strtolower( trim( $label . ' ' . $name . ' builder' ) ),
			);
		}

		return $out;
	}

	/**
	 * Full target field catalog for the Mapping step / export column picker.
	 *
	 * Explicit supported schema only — no Property Builder scan.
	 *
	 * @return array<int, array{id:string,label:string,group:string,required:bool,keywords?:string}>
	 */
	public static function get_fields(): array {
		$fields = self::default_fields();

		/**
		 * Filter the CSV Transfer field catalog.
		 *
		 * @since 3.7.0
		 *
		 * @param array $fields Field catalog rows.
		 */
		return (array) apply_filters( 'hvnly_csv_field_catalog', $fields );
	}

	/**
	 * Field ids still accepted on import for backward compatibility.
	 *
	 * Includes the supported catalog plus legacy out-of-scope targets
	 * (documents, agents, FAQ, …) so old CSVs and saved profiles keep working.
	 *
	 * @return array<int, string>
	 */
	public static function resolvable_field_ids(): array {
		return array_values(
			array_unique(
				array_merge(
					self::field_ids(),
					CsvSupportedSchema::legacy_import_field_ids()
				)
			)
		);
	}

	/**
	 * @return array<int, string> Field ids only.
	 */
	public static function field_ids(): array {
		return array_column( self::get_fields(), 'id' );
	}

	/**
	 * @return array<int, string> Required field ids.
	 */
	public static function required_field_ids(): array {
		$out = array();
		foreach ( self::get_fields() as $field ) {
			if ( ! empty( $field['required'] ) ) {
				$out[] = $field['id'];
			}
		}
		return $out;
	}

	/**
	 * Whether a catalog field id is a default (non-custom-Builder) field.
	 *
	 * @param string $field_id Field id.
	 * @return bool
	 */
	public static function is_core_field_id( string $field_id ): bool {
		static $ids = null;
		if ( null === $ids ) {
			$ids = array();
			foreach ( SchemaTargets::logical_defs() as $id => $_def ) {
				$ids[ $id ] = true;
			}
			foreach ( self::permanent_preset_fields() as $field ) {
				$ids[ $field['id'] ] = true;
			}
		}
		return isset( $ids[ $field_id ] );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
