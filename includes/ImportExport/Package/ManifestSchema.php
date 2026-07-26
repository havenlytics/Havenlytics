<?php
/**
 * HPTP package schema constants (no I/O).
 *
 * Behavioral contract for Havenlytics Property Transfer Package v1.
 * Readers/writers are introduced in later phases.
 *
 * @package HvnlyNab\ImportExport\Package
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Package;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical format identity and version numbers for package manifests.
 *
 * @since 3.6.0
 */
final class ManifestSchema {

	/**
	 * Manifest `format` field value.
	 *
	 * @var string
	 */
	public const FORMAT = 'havenlytics-property-transfer-package';

	/**
	 * HPTP schema version shipped with Havenlytics 3.6.0.
	 *
	 * Major must match for import; minor may differ with warnings.
	 *
	 * @var string
	 */
	public const SCHEMA_VERSION = '1.0';

	/**
	 * Supported major schema version for this plugin build.
	 *
	 * @var int
	 */
	public const SCHEMA_MAJOR = 1;

	/**
	 * Checksum algorithm declared in manifests.
	 *
	 * @var string
	 */
	public const CHECKSUM_ALGORITHM = 'sha256';

	/**
	 * Required root entry filenames inside an HPTP archive.
	 *
	 * @var string
	 */
	public const FILE_MANIFEST = 'manifest.json';

	/**
	 * Entities payload filename.
	 *
	 * @var string
	 */
	public const FILE_ENTITIES = 'entities.json';

	/**
	 * Media index filename (required when media is included).
	 *
	 * @var string
	 */
	public const FILE_MEDIA_INDEX = 'media-index.json';

	/**
	 * Media binaries directory name inside the archive.
	 *
	 * @var string
	 */
	public const DIR_MEDIA = 'media';

	/**
	 * Subdirectory under uploads for temporary package work.
	 *
	 * @var string
	 */
	public const TEMP_DIR_NAME = 'havenlytics-ie';

	/**
	 * Temp package retention (seconds) — 24 hours.
	 *
	 * @var int
	 */
	public const TEMP_RETENTION_SECONDS = 86400;

	/**
	 * Max compressed package size (512 MiB).
	 *
	 * @var int
	 */
	public const MAX_PACKAGE_BYTES = 536870912;

	/**
	 * Max total uncompressed bytes across entries (768 MiB).
	 *
	 * @var int
	 */
	public const MAX_UNCOMPRESSED_BYTES = 805306368;

	/**
	 * Max single entry uncompressed size (128 MiB).
	 *
	 * @var int
	 */
	public const MAX_SINGLE_ENTRY_BYTES = 134217728;

	/**
	 * Max ZIP entry count.
	 *
	 * @var int
	 */
	public const MAX_ZIP_ENTRIES = 20000;

	/**
	 * Max compression ratio for large entries (bomb heuristic).
	 *
	 * @var int
	 */
	public const MAX_COMPRESSION_RATIO = 100;

	/**
	 * Option key reserved for the single active I/E job state (later phases).
	 *
	 * @var string
	 */
	public const OPTION_JOB_STATE = 'hvnly_ie_job_state';

	/**
	 * Option key reserved for the site-wide one-job lock (later phases).
	 *
	 * @var string
	 */
	public const OPTION_JOB_LOCK = 'hvnly_ie_job_lock';

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
