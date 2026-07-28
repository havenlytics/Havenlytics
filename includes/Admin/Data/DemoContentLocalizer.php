<?php
/**
 * Localize bundled Havenlytics demo content at display time.
 *
 * Storage may keep English msgids from an English-locale import. Only strings
 * that exactly match the bundled demo catalog are translated. User-authored
 * content never matches and is left unchanged.
 *
 * @package     Havenlytics
 * @subpackage  Admin/Data
 * @since       3.6.0
 */

namespace HvnlyNab\Admin\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Demo content localizer.
 */
final class DemoContentLocalizer {

	public const META_FLAG = '_hvnly_is_demo';

	/**
	 * @var array<string, true>|null
	 */
	private static $catalog = null;

	/**
	 * @var list<string>|null
	 */
	private static $address_parts = null;

	/**
	 * Whether a string is a known bundled demo msgid.
	 *
	 * @param string $text Candidate text.
	 * @return bool
	 */
	public static function is_bundled_demo_string( string $text ): bool {
		$text = trim( $text );
		if ( '' === $text ) {
			return false;
		}
		$catalog = self::catalog();
		return isset( $catalog[ $text ] );
	}

	/**
	 * Translate a string only when it is an exact bundled demo msgid.
	 *
	 * @param string $text Stored text.
	 * @return string
	 */
	public static function translate( string $text ): string {
		$text = (string) $text;
		if ( '' === $text || ! self::is_bundled_demo_string( $text ) ) {
			return $text;
		}

		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Demo msgid catalog lookup.
		return __( $text, 'havenlytics' );
	}

	/**
	 * Translate composed demo addresses by replacing known street/city/county tokens.
	 *
	 * @param string $address Full or partial address.
	 * @return string
	 */
	public static function translate_address( string $address ): string {
		$address = (string) $address;
		if ( '' === $address ) {
			return $address;
		}

		$exact = self::translate( $address );
		if ( $exact !== $address ) {
			return $exact;
		}

		$parts = self::address_parts();
		foreach ( $parts as $english ) {
			if ( '' === $english || false === strpos( $address, $english ) ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			$translated = __( $english, 'havenlytics' );
			if ( $translated !== $english ) {
				$address = str_replace( $english, $translated, $address );
			}
		}

		return $address;
	}

	/**
	 * Whether a post was created by the Havenlytics demo importer.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_demo_post( int $post_id ): bool {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return false;
		}
		return '1' === (string) get_post_meta( $post_id, self::META_FLAG, true );
	}

	/**
	 * Mark a post as bundled demo content.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function mark_demo_post( int $post_id ): void {
		$post_id = absint( $post_id );
		if ( $post_id > 0 ) {
			update_post_meta( $post_id, self::META_FLAG, '1' );
		}
	}

	/**
	 * Register frontend filters (idempotent).
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;

		add_filter( 'the_title', array( self::class, 'filter_title' ), 9, 2 );
		add_filter( 'get_the_excerpt', array( self::class, 'filter_excerpt' ), 9, 2 );
		add_filter( 'the_content', array( self::class, 'filter_content' ), 9 );
		add_filter( 'get_post_metadata', array( self::class, 'filter_post_metadata' ), 9, 4 );
		add_filter( 'get_term', array( self::class, 'filter_term' ), 9, 2 );
	}

	/**
	 * Localize demo agency term names/descriptions on the frontend.
	 *
	 * @param \WP_Term|mixed $term     Term.
	 * @param string         $taxonomy Taxonomy.
	 * @return \WP_Term|mixed
	 */
	public static function filter_term( $term, $taxonomy = '' ) {
		if ( ! ( $term instanceof \WP_Term ) ) {
			return $term;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $term;
		}
		$taxonomy = $taxonomy ? (string) $taxonomy : (string) $term->taxonomy;
		if ( 'hvnly_agent_agency' !== $taxonomy ) {
			return $term;
		}
		if ( isset( $term->name ) ) {
			$term->name = self::translate( (string) $term->name );
		}
		if ( isset( $term->description ) && is_string( $term->description ) && '' !== $term->description ) {
			$term->description = self::translate( $term->description );
		}
		return $term;
	}

	/**
	 * @param string $title   Title.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public static function filter_title( $title, $post_id = 0 ): string {
		$title   = (string) $title;
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! self::should_localize_post( $post_id, $title ) ) {
			return $title;
		}
		return self::translate( $title );
	}

	/**
	 * @param string       $excerpt Excerpt.
	 * @param \WP_Post|null $post   Post.
	 * @return string
	 */
	public static function filter_excerpt( $excerpt, $post = null ): string {
		$excerpt = (string) $excerpt;
		$post_id = ( $post instanceof \WP_Post ) ? (int) $post->ID : 0;
		if ( $post_id <= 0 || ! self::should_localize_post( $post_id, $excerpt ) ) {
			return $excerpt;
		}
		return self::translate( $excerpt );
	}

	/**
	 * @param string $content Content.
	 * @return string
	 */
	public static function filter_content( $content ): string {
		$content = (string) $content;
		if ( ! is_singular() && ! is_admin() ) {
			// Loop/archive cards may call the_content rarely; still guard by queried post.
		}
		$post_id = (int) get_the_ID();
		if ( $post_id <= 0 || ! self::should_localize_post( $post_id, $content ) ) {
			return $content;
		}
		return self::translate( $content );
	}

	/**
	 * Localize address / video / document demo meta for demo posts only.
	 *
	 * @param mixed  $value     Current filter value.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Single flag.
	 * @return mixed
	 */
	public static function filter_post_metadata( $value, $object_id, $meta_key, $single ) {
		if ( null !== $value ) {
			return $value;
		}
		$object_id = absint( $object_id );
		$meta_key  = (string) $meta_key;
		if ( $object_id <= 0 || '' === $meta_key || self::META_FLAG === $meta_key ) {
			return $value;
		}
		if ( ! self::is_demo_post( $object_id ) && ! self::title_matches_demo( $object_id ) ) {
			return $value;
		}

		$address_keys = array(
			'_hvnly_property_address',
			'_hvnly_property_address_line_1',
			'_hvnly_property_street',
			'_hvnly_property_town_city',
			'_hvnly_property_full_address',
			'_hvnly_property_map_address',
			'_hvnly_property_country_state',
			'preset_hvnly_property_field_address',
		);

		$is_address = in_array( $meta_key, $address_keys, true )
			|| str_ends_with( $meta_key, '_address' )
			|| str_ends_with( $meta_key, '_address_line_1' )
			|| str_ends_with( $meta_key, '_street' )
			|| str_ends_with( $meta_key, '_town_city' )
			|| str_ends_with( $meta_key, '_map_address' )
			|| str_ends_with( $meta_key, '_full_address' )
			|| str_ends_with( $meta_key, '_country_state' );

		$is_title_meta = str_ends_with( $meta_key, '_title' )
			|| str_ends_with( $meta_key, '_label' );

		if ( ! $is_address && ! $is_title_meta && ! str_contains( $meta_key, '_documents' ) && ! str_contains( $meta_key, '_faqs' ) && ! str_contains( $meta_key, 'repeater' ) ) {
			return $value;
		}

		// Avoid recursion: remove filter while reading raw meta.
		remove_filter( 'get_post_metadata', array( self::class, 'filter_post_metadata' ), 9 );
		$raw = get_post_meta( $object_id, $meta_key, $single );
		add_filter( 'get_post_metadata', array( self::class, 'filter_post_metadata' ), 9, 4 );

		if ( is_string( $raw ) ) {
			if ( $is_address ) {
				return self::translate_address( $raw );
			}
			if ( str_contains( $meta_key, '_documents' ) || str_contains( $meta_key, '_faqs' ) || str_contains( $meta_key, 'repeater' ) ) {
				return self::translate_json_blob( $raw );
			}
			return self::translate( $raw );
		}

		if ( is_array( $raw ) ) {
			foreach ( $raw as $i => $item ) {
				if ( ! is_string( $item ) ) {
					continue;
				}
				if ( $is_address ) {
					$raw[ $i ] = self::translate_address( $item );
				} elseif ( str_contains( $meta_key, '_documents' ) || str_contains( $meta_key, '_faqs' ) || str_contains( $meta_key, 'repeater' ) ) {
					$raw[ $i ] = self::translate_json_blob( $item );
				} else {
					$raw[ $i ] = self::translate( $item );
				}
			}
		}

		return $raw;
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $text    Candidate text (title/excerpt/content).
	 * @return bool
	 */
	private static function should_localize_post( int $post_id, string $text ): bool {
		$post_type = get_post_type( $post_id );
		$allowed   = array( 'hvnly_property', 'hvnly_agent', 'hvnly_agency' );
		if ( ! in_array( (string) $post_type, $allowed, true ) ) {
			return false;
		}
		if ( self::is_demo_post( $post_id ) ) {
			return true;
		}
		// Legacy demo imports without the meta flag: exact catalog match only.
		return self::is_bundled_demo_string( $text );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function title_matches_demo( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}
		return self::is_bundled_demo_string( (string) $post->post_title );
	}

	/**
	 * Translate string fields inside a JSON-encoded demo meta blob.
	 *
	 * @param string $json JSON string.
	 * @return string
	 */
	private static function translate_json_blob( string $json ): string {
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			return self::translate( $json );
		}
		$decoded = self::walk_translate( $decoded );
		$encoded = wp_json_encode( $decoded );
		return is_string( $encoded ) ? $encoded : $json;
	}

	/**
	 * @param mixed $data Data.
	 * @return mixed
	 */
	private static function walk_translate( $data ) {
		if ( is_string( $data ) ) {
			return self::translate( $data );
		}
		if ( ! is_array( $data ) ) {
			return $data;
		}
		foreach ( $data as $k => $v ) {
			$data[ $k ] = self::walk_translate( $v );
		}
		return $data;
	}

	/**
	 * @return array<string, true>
	 */
	private static function catalog(): array {
		if ( null !== self::$catalog ) {
			return self::$catalog;
		}

		$path = HVNLYNAB_INCLUDES . '/Admin/Data/demo-content-catalog.php';
		$list = is_file( $path ) ? include $path : array();
		self::$catalog = is_array( $list ) ? $list : array();

		foreach ( self::uk_location_english_parts() as $part ) {
			self::$catalog[ $part ] = true;
		}

		return self::$catalog;
	}

	/**
	 * @return list<string>
	 */
	private static function address_parts(): array {
		if ( null !== self::$address_parts ) {
			return self::$address_parts;
		}
		$parts = self::uk_location_english_parts();
		// Longer tokens first to avoid partial collisions.
		usort(
			$parts,
			static function ( $a, $b ) {
				return strlen( (string) $b ) <=> strlen( (string) $a );
			}
		);
		self::$address_parts = $parts;
		return self::$address_parts;
	}

	/**
	 * @return list<string>
	 */
	private static function uk_location_english_parts(): array {
		$parts = array(
			'High Street',
			'Church Road',
			'Victoria Road',
			'Station Road',
			'Park Lane',
			'Queens Road',
			'London Road',
			'King Street',
			'Market Street',
			'Bridge Street',
			'Mill Lane',
			'Green Lane',
			'Manor Road',
			'Church Lane',
			'New Road',
			'London, United Kingdom',
			'United Kingdom',
			'Greater London',
			'Greater Manchester',
			'West Midlands',
			'West Yorkshire',
			'South Yorkshire',
			'Tyne and Wear',
			'East Yorkshire',
			'North Yorkshire',
			'City of Edinburgh',
			'Glasgow City',
			'County Antrim',
		);

		if ( class_exists( UkImportLocations::class ) ) {
			foreach ( UkImportLocations::get_locations() as $loc ) {
				if ( ! empty( $loc['city'] ) ) {
					$parts[] = (string) $loc['city'];
				}
				if ( ! empty( $loc['county'] ) ) {
					$parts[] = (string) $loc['county'];
				}
			}
		}

		return array_values( array_unique( $parts ) );
	}
}
