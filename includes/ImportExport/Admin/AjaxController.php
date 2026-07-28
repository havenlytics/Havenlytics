<?php
/**
 * Admin-ajax transport for HPTP Import / Export jobs.
 *
 * @package HvnlyNab\ImportExport\Admin
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Admin;

use HvnlyNab\ImportExport\Capability\MigrationLimits;
use HvnlyNab\ImportExport\Jobs\ExportBatchRunner;
use HvnlyNab\ImportExport\Jobs\ImportBatchRunner;
use HvnlyNab\ImportExport\Jobs\JobCleanup;
use HvnlyNab\ImportExport\Jobs\JobLock;
use HvnlyNab\ImportExport\Jobs\JobStateStore;
use HvnlyNab\ImportExport\Jobs\JobWorkspace;
use HvnlyNab\ImportExport\Jobs\ReportBuilder;
use HvnlyNab\ImportExport\Package\ManifestSchema;
use HvnlyNab\ImportExport\Package\PackageReader;
use HvnlyNab\ImportExport\Package\TempStorage;
use HvnlyNab\ImportExport\Security\CapabilityChecker;
use HvnlyNab\ImportExport\Security\MimeGuard;

defined( 'ABSPATH' ) || exit;

/**
 * AjaxController — admin-ajax transport for the Settings Import / Export tab.
 *
 * @since 3.6.0
 */
final class AjaxController {

	public const NONCE_ACTION = 'hvnly_ie';

	/**
	 * @var array<string, string>
	 */
	private const ACTIONS = array(
		'hvnly_ie_export_start'    => 'export_start',
		'hvnly_ie_export_batch'    => 'export_batch',
		'hvnly_ie_export_cancel'   => 'export_cancel',
		'hvnly_ie_export_download' => 'export_download',
		'hvnly_ie_import_upload'   => 'import_upload',
		'hvnly_ie_import_validate' => 'import_validate',
		'hvnly_ie_import_start'    => 'import_start',
		'hvnly_ie_import_batch'    => 'import_batch',
		'hvnly_ie_import_cancel'   => 'import_cancel',
		'hvnly_ie_import_report'   => 'import_report',
		'hvnly_ie_job_status'      => 'job_status',
		'hvnly_ie_migration_limits'=> 'migration_limits',
	);

	/**
	 * Register admin-ajax hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		foreach ( self::ACTIONS as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
		}
	}

	/**
	 * Create an export job.
	 *
	 * @return void
	 */
	public static function export_start(): void {
		self::gate();
		if ( JobLock::is_locked() ) {
			self::error( 'hvnly_ie_job_locked', 'Another Import / Export job is already running.', 409 );
		}

		$options = self::export_options_from_request();
		$allowed = MigrationLimits::assert_export_allowed( $options );
		if ( is_wp_error( $allowed ) ) {
			self::error(
				(string) $allowed->get_error_code(),
				(string) $allowed->get_error_message(),
				402,
				(array) $allowed->get_error_data()
			);
		}

		$store = new JobStateStore();
		$job   = JobStateStore::new_job( JobStateStore::TYPE_EXPORT, $options, get_current_user_id() );

		if ( ! JobLock::acquire( $job['id'], get_current_user_id() ) ) {
			self::error( 'hvnly_ie_job_locked', 'Could not acquire job lock.', 409 );
		}

		$job['status'] = JobStateStore::STATUS_QUEUED;
		$job['phase']  = 'prepare';
		$store->save_job( $job );

		self::ok(
			array(
				'job' => JobStateStore::public_view( $job ),
			)
		);
	}

	/**
	 * Public Free / Pro migration limit status for the Settings UI.
	 *
	 * @return void
	 */
	public static function migration_limits(): void {
		self::gate();
		self::ok( array( 'limits' => MigrationLimits::public_status() ) );
	}

	/**
	 * Process one export batch.
	 *
	 * @return void
	 */
	public static function export_batch(): void {
		self::gate();
		$store = new JobStateStore();
		$job   = $store->get_job();
		if ( ! $job || JobStateStore::TYPE_EXPORT !== ( $job['type'] ?? '' ) ) {
			self::error( 'hvnly_ie_job_missing', 'No active export job.', 404 );
		}
		if ( in_array( $job['status'], array( JobStateStore::STATUS_COMPLETED, JobStateStore::STATUS_FAILED, JobStateStore::STATUS_CANCELLED ), true ) ) {
			self::ok( array( 'job' => JobStateStore::public_view( $job ), 'done' => true ) );
		}

		JobLock::heartbeat( (string) $job['id'] );

		try {
			$job = ExportBatchRunner::tick( $job );
		} catch ( \Throwable $e ) {
			$job = self::fail_job_from_exception( $job, $e );
		}

		$job = self::finalize_terminal_job( $job );
		$store->save_job( $job );
		self::ok(
			array(
				'job'  => JobStateStore::public_view( $job ),
				'done' => self::is_terminal( $job ),
			)
		);
	}

	/**
	 * Cancel export.
	 *
	 * @return void
	 */
	public static function export_cancel(): void {
		self::gate();
		$store = new JobStateStore();
		$job   = $store->get_job();
		if ( ! $job || JobStateStore::TYPE_EXPORT !== ( $job['type'] ?? '' ) ) {
			self::error( 'hvnly_ie_job_missing', 'No active export job.', 404 );
		}

		$job = self::cancel_job( $job );
		$job = self::finalize_terminal_job( $job );
		$store->save_job( $job );
		self::ok( array( 'job' => JobStateStore::public_view( $job ) ) );
	}

	/**
	 * Authorized download of completed export ZIP.
	 *
	 * @return void
	 */
	public static function export_download(): void {
		self::gate();
		$store  = new JobStateStore();
		$job    = $store->get_job();
		$job_id = isset( $_REQUEST['job_id'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['job_id'] ) ) : '';
		$token  = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['token'] ) ) : '';

		if ( ! $job || (string) ( $job['id'] ?? '' ) !== $job_id ) {
			self::error( 'hvnly_ie_download_job', 'Download job not found.', 404 );
		}
		if ( JobStateStore::STATUS_COMPLETED !== ( $job['status'] ?? '' ) ) {
			self::error( 'hvnly_ie_download_incomplete', 'Export is not complete.', 409 );
		}
		if ( (string) ( $job['download_token'] ?? '' ) !== $token || '' === $token ) {
			self::error( 'hvnly_ie_download_token', 'Invalid download token.', 403 );
		}
		if ( (int) ( $job['owner_user_id'] ?? 0 ) !== get_current_user_id() ) {
			self::error( 'hvnly_ie_download_owner', 'Download not authorized for this user.', 403 );
		}

		$path = (string) ( $job['zip_path'] ?? '' );
		if ( '' === $path || ! is_file( $path ) ) {
			self::error( 'hvnly_ie_download_missing', 'Package file is missing.', 404 );
		}

		$under = TempStorage::assert_under_base( $path );
		if ( ! $under->ok() ) {
			self::error( 'hvnly_ie_download_path', 'Package path is not allowed.', 403 );
		}

		$filename = ! empty( $job['zip_filename'] ) ? (string) $job['zip_filename'] : wp_basename( $path );
		$filename = sanitize_file_name( $filename );
		$filename = str_replace( array( '"', "'", "\r", "\n" ), '', $filename );
		if ( '' === $filename ) {
			$filename = 'havenlytics-export.zip';
		}

		$size = filesize( $path );
		if ( false === $size ) {
			self::error( 'hvnly_ie_download_missing', 'Package file is missing.', 404 );
		}

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) $size );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );
		exit;
	}

	/**
	 * Accept uploaded ZIP into protected temp storage.
	 *
	 * @return void
	 */
	public static function import_upload(): void {
		self::gate();
		if ( empty( $_FILES['package'] ) || ! is_array( $_FILES['package'] ) ) {
			self::error( 'hvnly_ie_upload_missing', 'No package file uploaded.', 400 );
		}

		$file = $_FILES['package']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $file['error'] ) ) {
			self::error( 'hvnly_ie_upload_error', 'Upload failed.', 400, array( 'php_error' => (int) $file['error'] ) );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size <= 0 || $size > ManifestSchema::MAX_PACKAGE_BYTES ) {
			self::error(
				'hvnly_ie_upload_size',
				'Package exceeds the maximum allowed size.',
				400,
				array( 'max_bytes' => ManifestSchema::MAX_PACKAGE_BYTES )
			);
		}

		$workdir = TempStorage::create_workdir( 'upload' );
		if ( ! $workdir->ok() ) {
			self::error( $workdir->first_code(), $workdir->errors()[0]['message'] ?? 'Temp storage failed.', 500 );
		}
		$dir  = (string) $workdir->data()['dir'];
		$name = sanitize_file_name( (string) ( $file['name'] ?? 'package.zip' ) );
		if ( ! preg_match( '/\.zip$/i', $name ) ) {
			$name .= '.zip';
		}
		$dest = trailingslashit( $dir ) . $name;
		if ( ! move_uploaded_file( (string) $file['tmp_name'], $dest ) ) {
			TempStorage::delete_workdir( $dir );
			self::error( 'hvnly_ie_upload_move_failed', 'Could not store uploaded package.', 500 );
		}

		$mime = MimeGuard::validate_package_file( $dest );
		if ( ! $mime->ok() ) {
			TempStorage::delete_workdir( $dir );
			$err = $mime->errors()[0] ?? array();
			self::error(
				(string) ( $err['code'] ?? 'hvnly_ie_package_invalid' ),
				(string) ( $err['message'] ?? 'Uploaded file is not a valid package.' ),
				400,
				self::safe_context( isset( $err['context'] ) && is_array( $err['context'] ) ? $err['context'] : array() )
			);
		}

		$on_disk = filesize( $dest );
		if ( false === $on_disk || $on_disk > ManifestSchema::MAX_PACKAGE_BYTES ) {
			TempStorage::delete_workdir( $dir );
			self::error( 'hvnly_ie_upload_size', 'Package exceeds the maximum allowed size.', 400 );
		}

		$token = 'up_' . wp_generate_password( 16, false, false );
		set_transient(
			'hvnly_ie_upload_' . $token,
			array(
				'zip_path' => $dest,
				'workdir'  => $dir,
				'user_id'  => get_current_user_id(),
				'created'  => time(),
			),
			HOUR_IN_SECONDS
		);

		self::ok(
			array(
				'upload_token' => $token,
				'filename'     => $name,
				'size'         => (int) $on_disk,
			)
		);
	}

	/**
	 * Validate uploaded package.
	 *
	 * @return void
	 */
	public static function import_validate(): void {
		self::gate();
		$token   = isset( $_POST['upload_token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['upload_token'] ) ) : '';
		$session = get_transient( 'hvnly_ie_upload_' . $token );
		if ( ! is_array( $session ) || empty( $session['zip_path'] ) ) {
			self::error( 'hvnly_ie_upload_token', 'Upload token is invalid or expired.', 400 );
		}
		if ( (int) ( $session['user_id'] ?? 0 ) !== get_current_user_id() ) {
			self::error( 'hvnly_ie_upload_owner', 'Upload token belongs to another user.', 403 );
		}

		$opened = PackageReader::open( (string) $session['zip_path'] );
		if ( ! $opened->ok() ) {
			JobCleanup::dispose_upload_session( $token, array_merge( $session, array( 'delete_workdir' => true ) ) );
			self::error(
				$opened->first_code(),
				$opened->errors()[0]['message'] ?? 'Package validation failed.',
				400,
				self::safe_context( array( 'errors' => $opened->errors() ) )
			);
		}

		$data        = $opened->data();
		$workdir     = (string) ( $data['workdir'] ?? '' );
		$entities    = isset( $data['entities'] ) && is_array( $data['entities'] ) ? $data['entities'] : array();
		$manifest    = isset( $data['manifest'] ) && is_array( $data['manifest'] ) ? $data['manifest'] : array();
		$media_index = isset( $data['media_index'] ) && is_array( $data['media_index'] ) ? $data['media_index'] : null;
		$files       = isset( $data['files'] ) && is_array( $data['files'] ) ? $data['files'] : array();

		// Free edition gate: reject oversized packages before any workspace write / import.
		$allowed = MigrationLimits::assert_import_allowed( $manifest, $entities );
		if ( is_wp_error( $allowed ) ) {
			if ( '' !== $workdir ) {
				TempStorage::delete_workdir( $workdir );
			}
			JobCleanup::dispose_upload_session( $token, array_merge( $session, array( 'delete_workdir' => true ) ) );
			self::error(
				(string) $allowed->get_error_code(),
				(string) $allowed->get_error_message(),
				402,
				(array) $allowed->get_error_data()
			);
		}

		JobWorkspace::write_json( $workdir, 'entities.json', $entities );
		JobWorkspace::write_json( $workdir, 'manifest.json', $manifest );
		JobWorkspace::write_json( $workdir, 'media_index.json', $media_index ? $media_index : array( 'files' => array() ) );
		JobWorkspace::write_json( $workdir, 'files.json', $files );

		set_transient(
			'hvnly_ie_upload_' . $token,
			array_merge(
				$session,
				array(
					'validated'   => true,
					'pkg_workdir' => $workdir,
					'source_zip'  => (string) $session['zip_path'],
				)
			),
			HOUR_IN_SECONDS
		);

		$counts = isset( $manifest['counts'] ) && is_array( $manifest['counts'] ) ? $manifest['counts'] : array(
			'properties' => isset( $entities['properties'] ) && is_array( $entities['properties'] ) ? count( $entities['properties'] ) : 0,
			'agents'     => isset( $entities['agents'] ) && is_array( $entities['agents'] ) ? count( $entities['agents'] ) : 0,
			'agencies'   => isset( $entities['agencies'] ) && is_array( $entities['agencies'] ) ? count( $entities['agencies'] ) : 0,
			'terms'      => isset( $entities['terms'] ) && is_array( $entities['terms'] ) ? count( $entities['terms'] ) : 0,
		);

		self::ok(
			array(
				'upload_token' => $token,
				'manifest'     => $manifest,
				'counts'       => $counts,
				'warnings'     => $opened->warnings(),
				'has_media'    => ! empty( $media_index['files'] ),
			)
		);
	}

	/**
	 * Start import job from validated upload.
	 *
	 * @return void
	 */
	public static function import_start(): void {
		self::gate();
		if ( JobLock::is_locked() ) {
			self::error( 'hvnly_ie_job_locked', 'Another Import / Export job is already running.', 409 );
		}

		$token   = isset( $_POST['upload_token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['upload_token'] ) ) : '';
		$session = get_transient( 'hvnly_ie_upload_' . $token );
		if ( ! is_array( $session ) || empty( $session['validated'] ) || empty( $session['pkg_workdir'] ) ) {
			self::error( 'hvnly_ie_upload_not_validated', 'Validate the package before starting import.', 400 );
		}
		if ( (int) ( $session['user_id'] ?? 0 ) !== get_current_user_id() ) {
			self::error( 'hvnly_ie_upload_owner', 'Upload token belongs to another user.', 403 );
		}

		// Defense in depth: re-assert Free limit from validated workspace before creating a job.
		$pkg_workdir = (string) $session['pkg_workdir'];
		$manifest    = JobWorkspace::read_json( $pkg_workdir, 'manifest.json', null );
		$entities    = JobWorkspace::read_json( $pkg_workdir, 'entities.json', null );
		$allowed     = MigrationLimits::assert_import_allowed(
			is_array( $manifest ) ? $manifest : null,
			is_array( $entities ) ? $entities : null
		);
		if ( is_wp_error( $allowed ) ) {
			self::error(
				(string) $allowed->get_error_code(),
				(string) $allowed->get_error_message(),
				402,
				(array) $allowed->get_error_data()
			);
		}

		$options = self::import_options_from_request();
		$job     = JobStateStore::new_job( JobStateStore::TYPE_IMPORT, $options, get_current_user_id() );
		$job['workdir']        = $pkg_workdir;
		$job['upload_path']    = (string) ( $session['source_zip'] ?? '' );
		$job['upload_workdir'] = (string) ( $session['workdir'] ?? '' );
		$job['phase']          = 'prepare';

		if ( ! JobLock::acquire( $job['id'], get_current_user_id() ) ) {
			self::error( 'hvnly_ie_job_locked', 'Could not acquire job lock.', 409 );
		}

		// Consume upload token; keep dirs until terminal cleanup.
		JobCleanup::dispose_upload_session( $token );

		$store = new JobStateStore();
		$store->save_job( $job );
		self::ok( array( 'job' => JobStateStore::public_view( $job ) ) );
	}

	/**
	 * Process one import batch.
	 *
	 * @return void
	 */
	public static function import_batch(): void {
		self::gate();
		$store = new JobStateStore();
		$job   = $store->get_job();
		if ( ! $job || JobStateStore::TYPE_IMPORT !== ( $job['type'] ?? '' ) ) {
			self::error( 'hvnly_ie_job_missing', 'No active import job.', 404 );
		}
		if ( in_array( $job['status'], array( JobStateStore::STATUS_COMPLETED, JobStateStore::STATUS_FAILED, JobStateStore::STATUS_CANCELLED ), true ) ) {
			self::ok( array( 'job' => JobStateStore::public_view( $job ), 'done' => true ) );
		}

		JobLock::heartbeat( (string) $job['id'] );

		try {
			$job = ImportBatchRunner::tick( $job );
		} catch ( \Throwable $e ) {
			$job = self::fail_job_from_exception( $job, $e );
		}

		$job = self::finalize_terminal_job( $job );
		$store->save_job( $job );
		self::ok(
			array(
				'job'  => JobStateStore::public_view( $job ),
				'done' => self::is_terminal( $job ),
			)
		);
	}

	/**
	 * Cancel import.
	 *
	 * @return void
	 */
	public static function import_cancel(): void {
		self::gate();
		$store = new JobStateStore();
		$job   = $store->get_job();
		if ( ! $job || JobStateStore::TYPE_IMPORT !== ( $job['type'] ?? '' ) ) {
			self::error( 'hvnly_ie_job_missing', 'No active import job.', 404 );
		}
		$job = self::cancel_job( $job );
		$job = self::finalize_terminal_job( $job );
		$store->save_job( $job );
		self::ok( array( 'job' => JobStateStore::public_view( $job ), 'report' => $job['report'] ) );
	}

	/**
	 * Fetch import report.
	 *
	 * @return void
	 */
	public static function import_report(): void {
		self::gate();
		$store = new JobStateStore();
		$job   = $store->get_job();
		if ( ! $job ) {
			self::error( 'hvnly_ie_job_missing', 'No active job.', 404 );
		}
		$report = ! empty( $job['report'] ) && is_array( $job['report'] )
			? $job['report']
			: ReportBuilder::from_job( $job );
		self::ok( array( 'report' => $report, 'job' => JobStateStore::public_view( $job ) ) );
	}

	/**
	 * Poll job status.
	 *
	 * @return void
	 */
	public static function job_status(): void {
		self::gate();
		JobCleanup::maybe_run_maintenance();

		$store = new JobStateStore();
		$job   = $store->get_job();
		if ( ! $job ) {
			self::ok( array( 'job' => null, 'locked' => JobLock::is_locked() ) );
		}
		self::ok(
			array(
				'job'    => JobStateStore::public_view( $job ),
				'locked' => JobLock::is_locked(),
				'lock'   => JobLock::read(),
			)
		);
	}

	/**
	 * @param array $job Job.
	 * @return array
	 */
	private static function cancel_job( array $job ): array {
		$job['status']       = JobStateStore::STATUS_CANCELLED;
		$job['phase']        = 'cancelled';
		$job['completed_at'] = gmdate( 'c' );
		$job['progress']['message'] = 'Cancelled by user.';
		$job['report']       = ReportBuilder::from_job( $job );
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @return bool
	 */
	private static function is_terminal( array $job ): bool {
		return in_array(
			$job['status'] ?? '',
			array( JobStateStore::STATUS_COMPLETED, JobStateStore::STATUS_FAILED, JobStateStore::STATUS_CANCELLED ),
			true
		);
	}

	/**
	 * Release lock, build report, and clean workdirs for terminal jobs.
	 *
	 * @param array $job Job.
	 * @return array
	 */
	private static function finalize_terminal_job( array $job ): array {
		if ( ! self::is_terminal( $job ) ) {
			return $job;
		}

		JobLock::release( (string) $job['id'] );
		if ( empty( $job['report'] ) ) {
			$job['report'] = ReportBuilder::from_job( $job );
		}
		$job = JobCleanup::after_terminal( $job );
		// Rebuild report after path scrubbing so download meta stays accurate.
		$job['report'] = ReportBuilder::from_job( $job );
		return $job;
	}

	/**
	 * @param array      $job Job.
	 * @param \Throwable $e Exception.
	 * @return array
	 */
	private static function fail_job_from_exception( array $job, \Throwable $e ): array {
		$job['status']       = JobStateStore::STATUS_FAILED;
		$job['phase']        = 'failed';
		$job['completed_at'] = gmdate( 'c' );
		$job['progress']     = array(
			'percent' => (int) ( $job['progress']['percent'] ?? 0 ),
			'message' => 'Job failed unexpectedly.',
		);
		$job = JobStateStore::push_error(
			$job,
			array(
				'code'    => 'hvnly_ie_job_exception',
				'message' => 'The Import / Export job failed unexpectedly.',
				'context' => array(
					'type' => get_class( $e ),
				),
			)
		);
		return $job;
	}

	/**
	 * Strip absolute filesystem paths from client-facing error context.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed>
	 */
	private static function safe_context( array $context ): array {
		$safe = array();
		foreach ( $context as $key => $value ) {
			if ( is_array( $value ) ) {
				$safe[ $key ] = self::safe_context( $value );
				continue;
			}
			if ( ! is_string( $value ) ) {
				$safe[ $key ] = $value;
				continue;
			}
			// Drop absolute Windows/Unix paths; keep relative archive entry names.
			if ( preg_match( '#^([a-zA-Z]:\\\\|/|\\\\\\\\)#', $value ) ) {
				$safe[ $key ] = wp_basename( $value );
				continue;
			}
			$safe[ $key ] = $value;
		}
		return $safe;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function export_options_from_request(): array {
		$site = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		return array(
			'package_name'       => isset( $_POST['package_name'] ) ? sanitize_file_name( wp_unslash( (string) $_POST['package_name'] ) ) : ( 'havenlytics-export-' . gmdate( 'Y-m-d-Hi' ) ),
			'description'        => isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['description'] ) ) : '',
			'source_site_label'  => $site,
			'include_builders'   => self::bool_param( 'include_builders', true ),
			'include_taxonomies' => self::bool_param( 'include_taxonomies', true ),
			'include_agencies'   => self::bool_param( 'include_agencies', true ),
			'include_agents'     => self::bool_param( 'include_agents', true ),
			'include_properties' => self::bool_param( 'include_properties', true ),
			'include_media'      => self::bool_param( 'include_media', true ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function import_options_from_request(): array {
		return array(
			'duplicate_policy'     => isset( $_POST['duplicate_policy'] ) ? sanitize_key( wp_unslash( (string) $_POST['duplicate_policy'] ) ) : 'skip',
			'builder_policy'       => isset( $_POST['builder_policy'] ) ? sanitize_key( wp_unslash( (string) $_POST['builder_policy'] ) ) : 'replace',
			'include_terms'        => self::bool_param( 'include_terms', true ),
			'include_agencies'     => self::bool_param( 'include_agencies', true ),
			'include_agents'       => self::bool_param( 'include_agents', true ),
			'include_properties'   => self::bool_param( 'include_properties', true ),
			'include_media'        => self::bool_param( 'include_media', true ),
			'apply_builder_policy' => self::bool_param( 'apply_builder_policy', true ),
		);
	}

	/**
	 * @param string $key Key.
	 * @param bool   $default Default.
	 * @return bool
	 */
	private static function bool_param( string $key, bool $default ): bool {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}
		$val = strtolower( trim( (string) wp_unslash( $_POST[ $key ] ) ) );
		return in_array( $val, array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Capability + nonce gate (JSON errors; no raw -1 die).
	 *
	 * @return void
	 */
	private static function gate(): void {
		if ( ! CapabilityChecker::current_user_can_manage() ) {
			self::error( 'hvnly_ie_capability_denied', 'Current user cannot manage Import / Export.', 403 );
		}
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			self::error( 'hvnly_ie_nonce_invalid', 'Security check failed. Refresh the page and try again.', 403 );
		}
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return void
	 */
	private static function ok( array $data ): void {
		wp_send_json_success( $data );
	}

	/**
	 * @param string               $code Code.
	 * @param string               $message Message.
	 * @param int                  $status HTTP status.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	private static function error( string $code, string $message, int $status = 400, array $context = array() ): void {
		status_header( $status );
		wp_send_json_error(
			array(
				'code'    => $code,
				'title'   => self::title_for_code( $code ),
				'message' => $message,
				'context' => self::safe_context( $context ),
			),
			$status
		);
	}

	/**
	 * @param string $code Code.
	 * @return string
	 */
	private static function title_for_code( string $code ): string {
		$map = array(
			'hvnly_ie_capability_denied' => 'Permission denied',
			'hvnly_ie_nonce_invalid'     => 'Security check failed',
			'hvnly_ie_job_locked'        => 'Job locked',
			'hvnly_ie_job_missing'       => 'Job not found',
			'hvnly_ie_upload_missing'    => 'Upload missing',
			'hvnly_ie_upload_size'       => 'Package too large',
			'hvnly_ie_upload_token'      => 'Upload expired',
			'hvnly_ie_download_token'    => 'Download unauthorized',
			'hvnly_ie_download_owner'    => 'Download unauthorized',
			'hvnly_ie_migration_limit'   => 'Free Edition Limit Reached',
		);
		return $map[ $code ] ?? 'Import / Export error';
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
