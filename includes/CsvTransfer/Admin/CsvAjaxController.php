<?php
/**
 * Admin-ajax transport for the CSV Transfer feature.
 *
 * @package HvnlyNab\CsvTransfer\Admin
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Admin;

use HvnlyNab\CsvTransfer\Export\ColumnSelector;
use HvnlyNab\CsvTransfer\Import\CsvParser;
use HvnlyNab\CsvTransfer\Import\DuplicateMatcher;
use HvnlyNab\CsvTransfer\Import\RowValidator;
use HvnlyNab\CsvTransfer\Jobs\CsvExportBatchRunner;
use HvnlyNab\CsvTransfer\Jobs\CsvImportBatchRunner;
use HvnlyNab\CsvTransfer\Jobs\CsvJobLock;
use HvnlyNab\CsvTransfer\Jobs\CsvJobStateStore;
use HvnlyNab\CsvTransfer\Jobs\CsvTempCleanup;
use HvnlyNab\CsvTransfer\Mapping\FieldCatalog;
use HvnlyNab\CsvTransfer\Mapping\MappingProfileStore;
use HvnlyNab\CsvTransfer\Mapping\MappingResolver;
use HvnlyNab\CsvTransfer\Mapping\PresetRegistry;
use HvnlyNab\CsvTransfer\Support\CsvSafeValue;
use HvnlyNab\CsvTransfer\Support\CsvStream;

defined( 'ABSPATH' ) || exit;

/**
 * CsvAjaxController — admin-ajax transport for the CSV Transfer Settings UI.
 *
 * @since 3.7.0
 */
final class CsvAjaxController {

	public const NONCE_ACTION = 'hvnly_csv';

	private const UPLOAD_TRANSIENT_PREFIX = 'hvnly_csv_upload_';

	/**
	 * @var array<string, string>
	 */
	private const ACTIONS = array(
		'hvnly_csv_fields'          => 'fields',
		'hvnly_csv_upload'          => 'upload',
		'hvnly_csv_preview'         => 'preview',
		'hvnly_csv_import_start'    => 'import_start',
		'hvnly_csv_import_batch'    => 'import_batch',
		'hvnly_csv_import_cancel'   => 'import_cancel',
		'hvnly_csv_export_start'    => 'export_start',
		'hvnly_csv_export_batch'    => 'export_batch',
		'hvnly_csv_export_cancel'   => 'export_cancel',
		'hvnly_csv_export_download' => 'export_download',
		'hvnly_csv_profiles_list'   => 'profiles_list',
		'hvnly_csv_profiles_save'   => 'profiles_save',
		'hvnly_csv_profiles_delete' => 'profiles_delete',
		'hvnly_csv_profiles_export' => 'profiles_export',
		'hvnly_csv_profiles_import' => 'profiles_import',
		'hvnly_csv_presets_list'    => 'presets_list',
		'hvnly_csv_sample_download' => 'sample_download',
		'hvnly_csv_job_status'      => 'job_status',
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
	 * Target field catalog for the Mapping step.
	 *
	 * @return void
	 */
	public static function fields(): void {
		self::gate();
		self::ok( array( 'fields' => FieldCatalog::get_fields() ) );
	}

	/**
	 * Store an uploaded CSV, parse headers/sample rows, and suggest a mapping.
	 *
	 * @return void
	 */
	public static function upload(): void {
		self::gate();

		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			self::error( 'hvnly_csv_upload_missing', __( 'No CSV file uploaded.', 'havenlytics' ), 400 );
		}

		$file   = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$stored = CsvParser::store_upload( $file );
		if ( is_wp_error( $stored ) ) {
			self::error( (string) $stored->get_error_code(), $stored->get_error_message(), 400 );
		}

		$parsed = CsvParser::parse( $stored['path'] );
		if ( is_wp_error( $parsed ) ) {
			@unlink( $stored['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			self::error( (string) $parsed->get_error_code(), $parsed->get_error_message(), 400 );
		}

		$profile_id = isset( $_POST['preset_id'] ) ? sanitize_key( wp_unslash( (string) $_POST['preset_id'] ) ) : '';
		$profile    = '' !== $profile_id ? PresetRegistry::get( $profile_id ) : null;

		$resolved = MappingResolver::resolve( $parsed['headers'], $profile );

		$token = 'csv_' . wp_generate_password( 16, false, false );
		set_transient(
			self::UPLOAD_TRANSIENT_PREFIX . $token,
			array(
				'path'      => $stored['path'],
				'filename'  => $stored['filename'],
				'delimiter' => $parsed['delimiter'],
				'headers'   => $parsed['headers'],
				'user_id'   => get_current_user_id(),
				'created'   => time(),
			),
			HOUR_IN_SECONDS
		);

		self::ok(
			array(
				'upload_token' => $token,
				'filename'     => $stored['filename'],
				'size'         => $stored['size'],
				'delimiter'    => $parsed['delimiter'],
				'headers'      => $parsed['headers'],
				'sample_rows'  => $parsed['sample_rows'],
				'total_rows'   => $parsed['total_rows'],
				'mapping'      => $resolved['mapping'],
				'matched'      => $resolved['matched'],
				'unmatched'    => $resolved['unmatched'],
			)
		);
	}

	/**
	 * Validate a preview slice of rows against a candidate mapping.
	 *
	 * @return void
	 */
	public static function preview(): void {
		self::gate();

		$session = self::require_upload_session();
		$mapping = self::mapping_param();
		$limit   = isset( $_POST['limit'] ) ? max( 1, min( 500, absint( $_POST['limit'] ) ) ) : 100;

		$stream = CsvStream::open( (string) $session['path'], (string) $session['delimiter'] );
		if ( ! $stream ) {
			self::error( 'hvnly_csv_preview_failed', __( 'Could not read the uploaded CSV file.', 'havenlytics' ), 400 );
		}

		$rows    = $stream->read_rows( 0, $limit );
		$summary = RowValidator::validate_batch( $rows, $mapping, 1 );

		self::ok(
			array(
				'sampled_rows'  => count( $rows ),
				'total_rows'    => $stream->count_rows(),
				'valid_count'   => $summary['valid_count'],
				'error_count'   => $summary['error_count'],
				'warning_count' => $summary['warning_count'],
				'results'       => array_slice( $summary['results'], 0, 50 ),
			)
		);
	}

	/**
	 * Start an import job from a validated upload.
	 *
	 * @return void
	 */
	public static function import_start(): void {
		self::gate();
		self::assert_no_active_job();

		$session = self::require_upload_session();
		$mapping = self::mapping_param();

		$options = array(
			'csv_path'             => (string) $session['path'],
			'delimiter'            => (string) $session['delimiter'],
			'mapping'              => $mapping,
			'duplicate_policy'     => DuplicateMatcher::normalize_policy( isset( $_POST['duplicate_policy'] ) ? sanitize_key( wp_unslash( (string) $_POST['duplicate_policy'] ) ) : 'skip' ),
			'fetch_media'          => self::bool_param( 'fetch_media', true ),
			'gallery_as_featured'  => self::bool_param( 'gallery_as_featured', false ),
			'source_filename'      => (string) ( $session['filename'] ?? '' ),
		);

		$new_job = CsvJobStateStore::new_job( CsvJobStateStore::TYPE_IMPORT, $options, get_current_user_id() );
		if ( ! CsvJobLock::acquire( (string) $new_job['id'], get_current_user_id() ) ) {
			self::error( 'hvnly_csv_job_locked', __( 'Another CSV Transfer job is already running.', 'havenlytics' ), 409 );
		}
		CsvJobStateStore::save_job( $new_job );

		// Consume upload session; file ownership moves to the job.
		$token = isset( $_POST['upload_token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['upload_token'] ) ) : '';
		if ( '' !== $token ) {
			delete_transient( self::UPLOAD_TRANSIENT_PREFIX . $token );
		}

		self::ok( array( 'job' => CsvJobStateStore::public_view( $new_job ) ) );
	}

	/**
	 * Process one import batch.
	 *
	 * @return void
	 */
	public static function import_batch(): void {
		self::gate();
		$job = self::require_owned_job( CsvJobStateStore::TYPE_IMPORT );
		if ( CsvJobStateStore::is_terminal( $job ) ) {
			self::ok( array(
				'job' => CsvJobStateStore::public_view( $job ),
				'done' => true,
			) );
		}

		if ( ! CsvJobLock::acquire( (string) $job['id'], get_current_user_id() ) ) {
			self::error( 'hvnly_csv_job_locked', __( 'Another CSV Transfer job is already running.', 'havenlytics' ), 409 );
		}
		CsvJobLock::heartbeat( (string) $job['id'] );

		// Re-read under lock — cancel may have won between gate and acquire.
		$job = CsvJobStateStore::get_job();
		if ( ! $job || CsvJobStateStore::TYPE_IMPORT !== ( $job['type'] ?? '' ) ) {
			self::error( 'hvnly_csv_job_missing', __( 'No active import job.', 'havenlytics' ), 404 );
		}
		self::assert_job_owner( $job );
		if ( CsvJobStateStore::is_terminal( $job ) ) {
			CsvJobLock::release( (string) $job['id'] );
			self::ok( array(
				'job' => CsvJobStateStore::public_view( $job ),
				'done' => true,
			) );
		}

		try {
			$job = CsvImportBatchRunner::tick( $job );
		} catch ( \Throwable $e ) {
			$job = self::fail_job_from_exception( $job, $e );
		}

		$job = self::persist_job( $job );
		self::ok(
			array(
				'job'  => CsvJobStateStore::public_view( $job ),
				'done' => CsvJobStateStore::is_terminal( $job ),
			)
		);
	}

	/**
	 * Cancel the active import job.
	 *
	 * @return void
	 */
	public static function import_cancel(): void {
		self::gate();
		$job = self::require_owned_job( CsvJobStateStore::TYPE_IMPORT );
		$job = self::cancel_job( $job );
		$job = self::persist_job( $job );
		self::ok( array( 'job' => CsvJobStateStore::public_view( $job ) ) );
	}

	/**
	 * Create an export job.
	 *
	 * @return void
	 */
	public static function export_start(): void {
		self::gate();
		self::assert_no_active_job();

		$columns_raw = isset( $_POST['columns'] ) ? json_decode( (string) wp_unslash( $_POST['columns'] ), true ) : array();
		$columns     = ColumnSelector::sanitize( is_array( $columns_raw ) ? $columns_raw : array() );

		$statuses = isset( $_POST['statuses'] ) ? json_decode( (string) wp_unslash( $_POST['statuses'] ), true ) : array();
		$statuses = is_array( $statuses ) ? array_map( 'sanitize_key', $statuses ) : array();

		$options = array(
			'columns' => $columns,
			'filters' => array( 'statuses' => $statuses ),
		);

		$new_job = CsvJobStateStore::new_job( CsvJobStateStore::TYPE_EXPORT, $options, get_current_user_id() );
		if ( ! CsvJobLock::acquire( (string) $new_job['id'], get_current_user_id() ) ) {
			self::error( 'hvnly_csv_job_locked', __( 'Another CSV Transfer job is already running.', 'havenlytics' ), 409 );
		}
		CsvJobStateStore::save_job( $new_job );

		self::ok( array( 'job' => CsvJobStateStore::public_view( $new_job ) ) );
	}

	/**
	 * Process one export batch.
	 *
	 * @return void
	 */
	public static function export_batch(): void {
		self::gate();
		$job = self::require_owned_job( CsvJobStateStore::TYPE_EXPORT );
		if ( CsvJobStateStore::is_terminal( $job ) ) {
			self::ok( array(
				'job' => CsvJobStateStore::public_view( $job ),
				'done' => true,
			) );
		}

		if ( ! CsvJobLock::acquire( (string) $job['id'], get_current_user_id() ) ) {
			self::error( 'hvnly_csv_job_locked', __( 'Another CSV Transfer job is already running.', 'havenlytics' ), 409 );
		}
		CsvJobLock::heartbeat( (string) $job['id'] );

		$job = CsvJobStateStore::get_job();
		if ( ! $job || CsvJobStateStore::TYPE_EXPORT !== ( $job['type'] ?? '' ) ) {
			self::error( 'hvnly_csv_job_missing', __( 'No active export job.', 'havenlytics' ), 404 );
		}
		self::assert_job_owner( $job );
		if ( CsvJobStateStore::is_terminal( $job ) ) {
			CsvJobLock::release( (string) $job['id'] );
			self::ok( array(
				'job' => CsvJobStateStore::public_view( $job ),
				'done' => true,
			) );
		}

		try {
			$job = CsvExportBatchRunner::tick( $job );
		} catch ( \Throwable $e ) {
			$job = self::fail_job_from_exception( $job, $e );
		}

		$job = self::persist_job( $job );
		self::ok(
			array(
				'job'  => CsvJobStateStore::public_view( $job ),
				'done' => CsvJobStateStore::is_terminal( $job ),
			)
		);
	}

	/**
	 * Cancel the active export job.
	 *
	 * @return void
	 */
	public static function export_cancel(): void {
		self::gate();
		$job = self::require_owned_job( CsvJobStateStore::TYPE_EXPORT );
		$job = self::cancel_job( $job );
		$job = self::persist_job( $job );
		self::ok( array( 'job' => CsvJobStateStore::public_view( $job ) ) );
	}

	/**
	 * Authorized download of a completed export CSV.
	 *
	 * @return void
	 */
	public static function export_download(): void {
		self::gate();
		$job    = CsvJobStateStore::get_job();
		$job_id = isset( $_REQUEST['job_id'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['job_id'] ) ) : '';
		$token  = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['token'] ) ) : '';

		if ( ! $job || (string) ( $job['id'] ?? '' ) !== $job_id ) {
			self::error( 'hvnly_csv_download_job', __( 'Download job not found.', 'havenlytics' ), 404 );
		}
		if ( CsvJobStateStore::STATUS_COMPLETED !== ( $job['status'] ?? '' ) ) {
			self::error( 'hvnly_csv_download_incomplete', __( 'Export is not complete.', 'havenlytics' ), 409 );
		}
		if ( (string) ( $job['download_token'] ?? '' ) !== $token || '' === $token ) {
			self::error( 'hvnly_csv_download_token', __( 'Invalid download token.', 'havenlytics' ), 403 );
		}
		if ( (int) ( $job['owner_user_id'] ?? 0 ) !== get_current_user_id() ) {
			self::error( 'hvnly_csv_download_owner', __( 'Download not authorized for this user.', 'havenlytics' ), 403 );
		}

		$path = (string) ( $job['csv_path'] ?? '' );
		if ( '' === $path || ! is_file( $path ) || ! CsvParser::is_under_base( $path ) ) {
			self::error( 'hvnly_csv_download_missing', __( 'CSV file is missing.', 'havenlytics' ), 404 );
		}

		$filename = ! empty( $job['csv_filename'] ) ? (string) $job['csv_filename'] : wp_basename( $path );
		$filename = sanitize_file_name( $filename );
		$filename = str_replace( array( '"', "'", "\r", "\n" ), '', $filename );
		if ( '' === $filename ) {
			$filename = 'havenlytics-export.csv';
		}

		$size = filesize( $path );
		if ( false === $size ) {
			self::error( 'hvnly_csv_download_missing', __( 'CSV file is missing.', 'havenlytics' ), 404 );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) $size );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );

		// File has been delivered; remove temp export and clear the path from job state.
		CsvTempCleanup::delete_managed_file( $path );
		$job['csv_path'] = '';
		CsvJobStateStore::save_job( $job );
		exit;
	}

	/**
	 * List saved mapping profiles.
	 *
	 * @return void
	 */
	public static function profiles_list(): void {
		self::gate();
		self::ok( array( 'profiles' => MappingProfileStore::list() ) );
	}

	/**
	 * Save (create/update) a mapping profile.
	 *
	 * @return void
	 */
	public static function profiles_save(): void {
		self::gate();
		$payload = isset( $_POST['profile'] ) ? json_decode( (string) wp_unslash( $_POST['profile'] ), true ) : null;
		if ( ! is_array( $payload ) ) {
			self::error( 'hvnly_csv_profile_invalid', __( 'Invalid mapping profile payload.', 'havenlytics' ), 400 );
		}
		$saved = MappingProfileStore::save( $payload );
		self::ok( array( 'profile' => $saved ) );
	}

	/**
	 * Delete a mapping profile.
	 *
	 * @return void
	 */
	public static function profiles_delete(): void {
		self::gate();
		$id = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( (string) $_POST['id'] ) ) : '';
		$ok = MappingProfileStore::delete( $id );
		if ( ! $ok ) {
			self::error( 'hvnly_csv_profile_missing', __( 'Mapping profile not found.', 'havenlytics' ), 404 );
		}
		self::ok( array( 'deleted' => true ) );
	}

	/**
	 * Return a single profile for client-side JSON download.
	 *
	 * @return void
	 */
	public static function profiles_export(): void {
		self::gate();
		$id      = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( (string) $_POST['id'] ) ) : '';
		$profile = MappingProfileStore::get( $id );
		if ( ! $profile ) {
			self::error( 'hvnly_csv_profile_missing', __( 'Mapping profile not found.', 'havenlytics' ), 404 );
		}
		self::ok( array( 'profile' => $profile ) );
	}

	/**
	 * Import a previously exported profile JSON payload.
	 *
	 * @return void
	 */
	public static function profiles_import(): void {
		self::gate();
		$payload = isset( $_POST['profile'] ) ? json_decode( (string) wp_unslash( $_POST['profile'] ), true ) : null;
		if ( ! is_array( $payload ) ) {
			self::error( 'hvnly_csv_profile_invalid', __( 'Invalid mapping profile file.', 'havenlytics' ), 400 );
		}
		unset( $payload['id'] ); // Always import as a new profile.
		$saved = MappingProfileStore::save( $payload );
		self::ok( array( 'profile' => $saved ) );
	}

	/**
	 * List bundled source presets (Directorist, PropertyHive, …).
	 *
	 * @return void
	 */
	public static function presets_list(): void {
		self::gate();
		self::ok( array( 'presets' => PresetRegistry::public_list() ) );
	}

	/**
	 * Download a starter sample CSV built from onboarding DemoData (10 properties).
	 *
	 * @return void
	 */
	public static function sample_download(): void {
		self::gate();

		$headers = FieldCatalog::sample_headers();
		$rows    = FieldCatalog::sample_rows_from_demo( 10 );

		$fh = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $fh ) {
			self::error( 'hvnly_csv_sample_failed', __( 'Could not generate the sample CSV.', 'havenlytics' ), 500 );
		}
		fputcsv( $fh, CsvSafeValue::sanitize_row( $headers ) );
		foreach ( $rows as $row ) {
			fputcsv( $fh, CsvSafeValue::sanitize_row( $row ) );
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="havenlytics-sample-import.csv"' );
		header( 'Content-Length: ' . strlen( (string) $csv ) );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Poll active job status (import or export).
	 *
	 * @return void
	 */
	public static function job_status(): void {
		self::gate();
		$job = CsvJobStateStore::get_job();
		if ( ! $job ) {
			self::ok( array( 'job' => null ) );
		}
		self::assert_job_owner( $job );
		self::ok( array( 'job' => CsvJobStateStore::public_view( $job ) ) );
	}

	/**
	 * @param string $preset_id Preset id.
	 * @return array<string, mixed>|null
	 * @deprecated 3.7.0 Use PresetRegistry::get().
	 */
	private static function load_preset( string $preset_id ): ?array {
		return PresetRegistry::get( $preset_id );
	}

	/**
	 * @return array<string, mixed> Upload session transient payload.
	 */
	private static function require_upload_session(): array {
		$token   = isset( $_POST['upload_token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['upload_token'] ) ) : '';
		$session = get_transient( self::UPLOAD_TRANSIENT_PREFIX . $token );
		if ( ! is_array( $session ) || empty( $session['path'] ) || ! is_file( (string) $session['path'] ) ) {
			self::error( 'hvnly_csv_upload_token', __( 'Upload token is invalid or expired.', 'havenlytics' ), 400 );
		}
		if ( (int) ( $session['user_id'] ?? 0 ) !== get_current_user_id() ) {
			self::error( 'hvnly_csv_upload_owner', __( 'Upload token belongs to another user.', 'havenlytics' ), 403 );
		}
		return $session;
	}

	/**
	 * @return array<string, string|null> Header => field id.
	 */
	private static function mapping_param(): array {
		$raw = isset( $_POST['mapping'] ) ? json_decode( (string) wp_unslash( $_POST['mapping'] ), true ) : array();
		if ( ! is_array( $raw ) ) {
			self::error( 'hvnly_csv_mapping_invalid', __( 'Invalid column mapping payload.', 'havenlytics' ), 400 );
		}
		$known   = array_flip( FieldCatalog::field_ids() );
		$mapping = array();
		foreach ( $raw as $header => $field_id ) {
			if ( null === $field_id || '' === $field_id ) {
				$mapping[ (string) $header ] = null;
				continue;
			}
			$field_id                    = sanitize_key( (string) $field_id );
			$mapping[ (string) $header ] = isset( $known[ $field_id ] ) ? $field_id : null;
		}
		return $mapping;
	}

	/**
	 * Load the active job of a given type and verify the current user owns it.
	 *
	 * @param string $type Job type constant.
	 * @return array<string, mixed>
	 */
	private static function require_owned_job( string $type ): array {
		$job = CsvJobStateStore::get_job();
		if ( ! $job || $type !== ( $job['type'] ?? '' ) ) {
			$message = CsvJobStateStore::TYPE_IMPORT === $type
				? __( 'No active import job.', 'havenlytics' )
				: __( 'No active export job.', 'havenlytics' );
			self::error( 'hvnly_csv_job_missing', $message, 404 );
		}
		self::assert_job_owner( $job );
		return $job;
	}

	/**
	 * Reject job actions when the current user is not the job owner.
	 *
	 * @param array<string, mixed> $job Job blob.
	 * @return void
	 */
	private static function assert_job_owner( array $job ): void {
		if ( (int) ( $job['owner_user_id'] ?? 0 ) !== get_current_user_id() ) {
			self::error(
				'hvnly_csv_job_owner',
				__( 'This CSV Transfer job belongs to another administrator.', 'havenlytics' ),
				403
			);
		}
	}

	/**
	 * Persist job state and run terminal cleanup when applicable.
	 *
	 * Uses compare-against-stored-terminal so an in-flight batch cannot overwrite cancel.
	 *
	 * @param array<string, mixed> $job Job blob.
	 * @return array<string, mixed>
	 */
	private static function persist_job( array $job ): array {
		$was_terminal = CsvJobStateStore::is_terminal( $job );
		if ( $was_terminal ) {
			$job = CsvTempCleanup::after_terminal( $job );
		}

		$job = CsvJobStateStore::save_job_unless_overtaken( $job );

		if ( CsvJobStateStore::is_terminal( $job ) ) {
			CsvJobLock::release( (string) ( $job['id'] ?? '' ) );
		}

		return $job;
	}

	/**
	 * Reject starting a new job while another is active (after stale recovery).
	 *
	 * @return void
	 */
	private static function assert_no_active_job(): void {
		$job = CsvJobStateStore::get_job();
		if ( $job && in_array( $job['status'] ?? '', array( CsvJobStateStore::STATUS_QUEUED, CsvJobStateStore::STATUS_RUNNING ), true ) ) {
			self::error( 'hvnly_csv_job_locked', __( 'Another CSV Transfer job is already running.', 'havenlytics' ), 409 );
		}
		if ( CsvJobLock::is_locked() ) {
			self::error( 'hvnly_csv_job_locked', __( 'Another CSV Transfer job is already running.', 'havenlytics' ), 409 );
		}
	}

	/**
	 * Auto-fail abandoned queued/running jobs so they cannot block forever.
	 *
	 * @return void
	 */
	private static function recover_stale_job(): void {
		CsvJobLock::release_if_stale();

		$job = CsvJobStateStore::get_job();
		if ( ! $job || ! CsvJobStateStore::is_stale( $job ) ) {
			return;
		}

		$job['status']       = CsvJobStateStore::STATUS_FAILED;
		$job['phase']        = 'failed';
		$job['completed_at'] = gmdate( 'c' );
		$job['progress']     = array(
			'percent' => (int) ( $job['progress']['percent'] ?? 0 ),
			'message' => __( 'Job timed out due to inactivity.', 'havenlytics' ),
		);
		$job                 = CsvJobStateStore::push_error(
			$job,
			array(
				'code'    => 'hvnly_csv_job_stale',
				'message' => __( 'The CSV Transfer job was abandoned and automatically marked as failed.', 'havenlytics' ),
			)
		);
		$job                 = CsvTempCleanup::after_terminal( $job );
		CsvJobStateStore::save_job( $job );
		CsvJobLock::release( (string) ( $job['id'] ?? '' ) );
	}

	/**
	 * @param array $job Job.
	 * @return array
	 */
	private static function cancel_job( array $job ): array {
		$job['status']              = CsvJobStateStore::STATUS_CANCELLED;
		$job['phase']               = 'cancelled';
		$job['completed_at']        = gmdate( 'c' );
		$job['progress']['message'] = __( 'Cancelled by user.', 'havenlytics' );
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @return bool
	 */
	private static function is_terminal( array $job ): bool {
		return CsvJobStateStore::is_terminal( $job );
	}

	/**
	 * @param array      $job Job.
	 * @param \Throwable $e Exception.
	 * @return array
	 */
	private static function fail_job_from_exception( array $job, \Throwable $e ): array {
		$job['status']       = CsvJobStateStore::STATUS_FAILED;
		$job['phase']        = 'failed';
		$job['completed_at'] = gmdate( 'c' );
		$job['progress']     = array(
			'percent' => (int) ( $job['progress']['percent'] ?? 0 ),
			'message' => __( 'Job failed unexpectedly.', 'havenlytics' ),
		);
		return CsvJobStateStore::push_error(
			$job,
			array(
				'code'    => 'hvnly_csv_job_exception',
				'message' => __( 'The CSV Transfer job failed unexpectedly.', 'havenlytics' ),
				'context' => array( 'type' => get_class( $e ) ),
			)
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
		if ( ! current_user_can( 'manage_options' ) ) {
			self::error( 'hvnly_csv_capability_denied', __( 'Current user cannot manage CSV Transfer.', 'havenlytics' ), 403 );
		}
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			self::error( 'hvnly_csv_nonce_invalid', __( 'Security check failed. Refresh the page and try again.', 'havenlytics' ), 403 );
		}
		self::recover_stale_job();
		CsvTempCleanup::maybe_run_maintenance();
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return void
	 */
	private static function ok( array $data ): void {
		wp_send_json_success( $data );
	}

	/**
	 * @param string $code Code.
	 * @param string $message Message.
	 * @param int    $status HTTP status.
	 * @return void
	 */
	private static function error( string $code, string $message, int $status = 400 ): void {
		status_header( $status );
		wp_send_json_error(
			array(
				'code'    => $code,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
