<?php
/**
 * Seeds Property Location taxonomy term images during demo import only.
 *
 * @package     Havenlytics
 * @subpackage  Admin/Importer
 * @since       3.0.5
 */

namespace HvnlyNab\Admin\Importer;

use HvnlyNab\Admin\Data\UkLocationTermImages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Location terms use a shared local SVG placeholder (no remote downloads).
 *
 * Demo import no longer sideloads city photos — the frontend falls back to
 * UkLocationTermImages::placeholder_url() until an admin uploads a custom image.
 */
class PropertyLocationTermImageSeeder {

	public const TAXONOMY = 'hvnly_prop_locations';
	public const META_KEY = '_hvnly_term_advanced_image_data';

	/** @var callable(string):void|null */
	private $logger = null;

	/**
	 * @deprecated 3.0.5 Progress is derived from missing term images.
	 * @return void
	 */
	public static function reset_progress(): void {
		// No-op: seeding always targets terms without images.
	}

	/**
	 * No remote seeding — local placeholder is served by the frontend helper.
	 *
	 * @param int                        $limit  Unused (kept for call-site compatibility).
	 * @param callable(string):void|null $logger Optional logger.
	 * @return int Always 0 (nothing to download).
	 */
	public function run( int $limit = 8, $logger = null ): int {
		unset( $limit );
		$this->logger = is_callable( $logger ) ? $logger : null;

		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return 0;
		}

		$url = UkLocationTermImages::placeholder_url();
		if ( '' !== $url ) {
			$this->log( 'Location terms use local placeholder (no remote image downloads): ' . $url );
		}

		return 0;
	}

	/**
	 * No pending remote location images under the local-placeholder strategy.
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
