<?php
/**
 * Targeted cache invalidation after HPTP import/export.
 *
 * @package HvnlyNab\ImportExport\Cache
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Cache;

use HvnlyNab\Core\CacheManager;
use HvnlyNab\Core\EnterpriseCache;
use HvnlyNab\Frontend\Query\PropertyQueryCache;
use HvnlyNab\Frontend\ViewModels\SearchFilters;
use HvnlyNab\Frontend\ViewModels\SidebarSearchFilters;

defined( 'ABSPATH' ) || exit;

/**
 * ImportExportCacheCoordinator — never flushes the entire object cache.
 *
 * Forbidden: wp_cache_flush(), CacheManager::clear_all_cache(), engine clear_all_cache().
 *
 * @since 3.6.0
 */
final class ImportExportCacheCoordinator {

	/**
	 * After a successful import that changed listings / builders.
	 *
	 * @param array<string, mixed> $context Context (builder_replaced, counts, …).
	 * @return void
	 */
	public static function after_import_success( array $context = array() ): void {
		if ( function_exists( 'hvnly_is_cache_enabled' ) && ! hvnly_is_cache_enabled() ) {
			return;
		}

		try {
			if ( class_exists( SearchFilters::class ) ) {
				( new SearchFilters() )->clear_all_search_cache();
			}
			if ( class_exists( SidebarSearchFilters::class ) ) {
				( new SidebarSearchFilters() )->clear_sidebar_cache();
			}
			if ( class_exists( PropertyQueryCache::class ) ) {
				PropertyQueryCache::invalidate_all();
			}
			if ( class_exists( CacheManager::class ) ) {
				CacheManager::clear_transients_by_pattern( 'search_' );
				CacheManager::clear_transients_by_pattern( 'sidebar_' );
				CacheManager::clear_transients_by_pattern( 'pquery_' );
			}

			if ( ! empty( $context['builder_replaced'] ) && class_exists( EnterpriseCache::class ) && function_exists( 'HVNLY_NAB' ) ) {
				$cache = EnterpriseCache::get_instance();
				if ( method_exists( $cache, 'invalidate_sections' ) ) {
					$cache->invalidate_sections();
				}
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Best-effort; never fail the import job for cache cleanup.
		}
	}

	/**
	 * After a successful export (no listing mutation — no cache clear required).
	 *
	 * @return void
	 */
	public static function after_export_success(): void {
		// Export does not mutate site content.
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
