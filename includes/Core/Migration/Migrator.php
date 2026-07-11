<?php

/**

 * Migration Manager

 *

 * Central manager for all plugin migrations.

 *

 * @package     Havenlytics

 * @subpackage  Core\Migration

 * @since       2.1.3

 */



namespace HvnlyNab\Core\Migration;



use HvnlyNab\Core\DataPreservation\BackupManager;



// Prevent direct access.

if (!defined('ABSPATH')) {

    exit;

}



/**

 * Class Migrator

 *

 * Manages the execution of version migrations in order.

 * Uses WordPress options to track completed migrations.

 *

 * @since 2.1.3

 */

class Migrator {



    /**

     * Option key for tracking completed migrations.

     *

     * @var string

     */

    const COMPLETED_KEY = 'hvnly_migrations_completed';



    const FAILED_KEY     = 'hvnly_migration_failed';

    const ERROR_KEY      = 'hvnly_migration_error';

    const LAST_STEP_KEY  = 'hvnly_migration_last_step';



    /**

     * Registered migration handlers.

     *

     * @var array

     */

    private static $migrations = array();



    /**

     * Initialize the migrator and register migrations.

     *

     * @since 2.1.3

     * @return void

     */

    public static function init() {

        MigrationAutoload::load_dependencies();

        if ( function_exists( 'hvnly_load_migration_dependencies' ) ) {
            hvnly_load_migration_dependencies();
        }

        self::register('2.1.3', Handlers\Version213Handler::class);

        self::register('2.2.1', Handlers\Version221Handler::class);  

        self::register('2.2.2', Handlers\Version222Handler::class);

        self::register('2.2.4', Handlers\Version224Handler::class);

        self::register('2.2.5', Handlers\Version225Handler::class);

        self::register('2.3.0', Handlers\Version230Handler::class);

        self::register('2.3.1', Handlers\Version231FixHandler::class);

        self::register('2.3.2', Handlers\Version232Handler::class);

        self::register('2.3.3', Handlers\Version233Handler::class);

        self::register('2.3.4', Handlers\Version234Handler::class);

        self::register('2.3.5', Handlers\Version235Handler::class);

        self::register('2.3.6', Handlers\Version236Handler::class);

        self::register('3.0.2', Handlers\Version302InquiriesHandler::class);

        self::register('3.0.2-inquiry-replies', Handlers\Version302InquiryRepliesHandler::class);

        self::register('3.0.9', Handlers\Version309Handler::class);

        self::register('3.1.9-section-alias-dedupe', Handlers\Version319SectionAliasDedupeHandler::class);

    }



    /**

     * Register a migration handler.

     *

     * @since 2.1.3

     * @param string $version Target version.

     * @param string $class   Handler class.

     * @return void

     */

    public static function register(string $version, string $class) {

        self::$migrations[$version] = $class;

    }



    /**

     * Whether migrations should run on this request (admin / CLI only).

     *

     * @return bool

     */

    public static function should_run_on_request(): bool {

        if ( defined( 'WP_CLI' ) && WP_CLI ) {

            return true;

        }

        if ( is_admin() && ! wp_doing_ajax() ) {

            return true;

        }

        if ( defined( 'HVNLY_FORCE_MIGRATIONS' ) && HVNLY_FORCE_MIGRATIONS ) {

            return true;

        }

        return false;

    }



    /**

     * Whether pending migrations exist for the current plugin version.

     *

     * @return bool

     */

    public static function needs_run(): bool {

        if ( empty( self::$migrations ) ) {

            self::init();

        }

        $completed = get_option( self::COMPLETED_KEY, array() );

        $current   = HVNLYNAB_VERSION;

        ksort( self::$migrations );

        foreach ( self::$migrations as $version => $handler_class ) {

            if ( version_compare( $version, $current, '>' ) ) {

                continue;

            }

            if ( empty( $completed[ $version ] ) ) {

                return true;

            }

        }

        return false;

    }



    /**

     * Clear failure flags after successful run.

     *

     * @return void

     */

    private static function clear_failure_state(): void {

        delete_option( self::FAILED_KEY );

        delete_option( self::ERROR_KEY );

        delete_option( self::LAST_STEP_KEY );

    }



    /**

     * Record migration failure.

     *

     * @param string $version Migration version.

     * @param string $message Error message.

     * @return void

     */

    private static function record_failure( string $version, string $message ): void {

        update_option( self::FAILED_KEY, 1, false );

        update_option( self::ERROR_KEY, $message, false );

        update_option( self::LAST_STEP_KEY, $version, false );

    }



    /**

     * Run all pending migrations.

     *

     * @since 2.1.3

     * @param bool $force Run even on frontend (retry action).

     * @return bool True on success, false on failure.

     */

    public static function run( bool $force = false ): bool {

        if ( ! $force && ! self::should_run_on_request() ) {

            return ! self::needs_run();

        }



        if (empty(self::$migrations)) {

            self::init();

        }



        if ( self::needs_run() ) {

            BackupManager::create_snapshot( 'pre_migration_' . HVNLYNAB_VERSION );

        }



        $completed = get_option(self::COMPLETED_KEY, array());

        $all_success = true;

        $current_version = HVNLYNAB_VERSION;



        ksort(self::$migrations);



        foreach (self::$migrations as $version => $handler_class) {

            if (version_compare($version, $current_version, '>')) {

                continue;

            }



            if (isset($completed[$version]) && $completed[$version] === true) {

                continue;

            }



            $success = self::run_migration($version, $handler_class);



            if ($success) {

                $completed[$version] = true;

                update_option(self::COMPLETED_KEY, $completed);

            } else {

                $all_success = false;

                break;

            }

        }



        if ( $all_success ) {

            self::clear_failure_state();

        }



        return $all_success;

    }



    /**

     * Execute a single migration.

     *

     * @since 2.1.3

     * @param string $version      Target version.

     * @param string $handler_class Handler class.

     * @return bool True on success, false on failure.

     */

    private static function run_migration(string $version, string $handler_class): bool {

        if (!class_exists($handler_class)) {

            $msg = 'Handler class not found: ' . $handler_class;

            if ( function_exists( 'hvnly_debug_log' ) ) {

                hvnly_debug_log( $msg, 'Migration' );

            }

            self::record_failure( $version, $msg );

            return false;

        }



        $handler = new $handler_class();



        if (!($handler instanceof Interfaces\MigrationInterface)) {

            $msg = 'Invalid handler for version ' . $version;

            if ( function_exists( 'hvnly_debug_log' ) ) {

                hvnly_debug_log( $msg, 'Migration' );

            }

            self::record_failure( $version, $msg );

            return false;

        }



        $description = method_exists($handler, 'get_description') ? $handler->get_description() : '';



        if (!$handler->is_needed()) {

            if ( function_exists( 'hvnly_debug_log' ) ) {

                hvnly_debug_log(

                    $version . ' skipped (not needed)' . ( $description ? ': ' . $description : '' ),

                    'Migration'

                );

            }

            return true;

        }



        if ( function_exists( 'hvnly_debug_log' ) ) {

            hvnly_debug_log(

                $version . ' starting' . ( $description ? ': ' . $description : '' ),

                'Migration'

            );

        }



        try {

            $result = $handler->up();

        } catch ( \Throwable $e ) {

            $msg = $e->getMessage();

            self::record_failure( $version, $msg );

            if ( function_exists( 'hvnly_debug_log' ) ) {

                hvnly_debug_log( $version . ' FAILED: ' . $msg, 'Migration' );

            }

            return false;

        }



        if ( function_exists( 'hvnly_debug_log' ) ) {

            hvnly_debug_log(

                $version . ( $result ? ' completed successfully' : ' FAILED' ),

                'Migration'

            );

        }



        if ( ! $result ) {

            self::record_failure( $version, sprintf( 'Migration %s returned false (may resume on next admin load).', $version ) );

        }



        return (bool) $result;

    }



    /**

     * Get migration status.

     *

     * @since 2.1.3

     * @return array Migration status information.

     */

    public static function get_status(): array {

        if (empty(self::$migrations)) {

            self::init();

        }



        $completed = get_option(self::COMPLETED_KEY, array());



        $status = array(

            'total'       => count(self::$migrations),

            'completed'   => 0,

            'pending'     => array(),

            'migrations'  => array(),

            'failed'      => (bool) get_option( self::FAILED_KEY, false ),

            'last_error'  => get_option( self::ERROR_KEY, '' ),

            'last_step'   => get_option( self::LAST_STEP_KEY, '' ),

        );



        foreach (self::$migrations as $version => $handler_class) {

            $is_completed = isset($completed[$version]) && $completed[$version] === true;

            $status['migrations'][$version] = array(

                'version'   => $version,

                'completed' => $is_completed,

                'handler'   => $handler_class,

            );



            if ($is_completed) {

                $status['completed']++;

            } else {

                $status['pending'][] = $version;

            }

        }



        return $status;

    }



    /**

     * Force re-run all migrations (for debugging).

     *

     * @since 2.1.3

     * @return void

     */

    public static function reset() {

        delete_option(self::COMPLETED_KEY);

        self::clear_failure_state();

    }

}


