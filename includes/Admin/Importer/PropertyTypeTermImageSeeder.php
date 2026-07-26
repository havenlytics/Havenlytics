<?php
/**
 * Seeds Property Type taxonomy term images during demo import only.
 *
 * @package     Havenlytics
 * @subpackage  Admin/Importer
 * @since       3.1.2
 */

namespace HvnlyNab\Admin\Importer;

use HvnlyNab\Admin\Data\PropertyTypeTermImages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Property type terms use a shared local SVG placeholder (no remote downloads).
 *
 * Demo import no longer sideloads demo.havenlytics.com stock photos — the
 * frontend falls back to PropertyTypeTermImages::placeholder_url() until an
 * admin uploads a custom image. Term meta structure is unchanged.
 */
class PropertyTypeTermImageSeeder {

	public const TAXONOMY = 'hvnly_prop_types';
	public const META_KEY = '_hvnly_term_advanced_image_data';

	/** @var callable(string):void|null */
	private $logger = null;

	/**
	 * No remote seeding — local placeholder is served by the frontend helper.
	 *
	 * Does not write term meta and never overwrites existing user-uploaded images.
	 *
	 * @param int                        $limit  Unused (kept for call-site compatibility).
	 * @param callable(string):void|null $logger Optional logger.
	 * @return int Always 0 (nothing to download).
	 */
	public function run( int $limit = 2, $logger = null ): int {
		unset( $limit );
		$this->logger = is_callable( $logger ) ? $logger : null;

		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return 0;
		}

		$url = PropertyTypeTermImages::placeholder_url();
		if ( '' !== $url ) {
			$this->log( 'Property type terms use local placeholder (no remote image downloads): ' . $url );
		}

		return 0;
	}

	/**
	 * No pending remote property type images under the local-placeholder strategy.
	 *
	 * @param callable(string):void|null $logger Optional logger.
	 * @return void
	 */
	public function finish_pending( $logger = null ): void {
		$this->run( 0, $logger );
	}

	/**
	 * @param string $message Log message.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( is_callable( $this->logger ) ) {
			call_user_func( $this->logger, $message );
		}
	}
}
