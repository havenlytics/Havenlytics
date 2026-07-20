<?php

namespace HvnlyNab\Setup;

defined( 'ABSPATH' ) || exit;

use HvnlyNab\Common\Keys;

/**
 * Class Installer.
 *
 * Install necessary database tables and options for the plugin.
 */
class Installer
{

    /**
     * Run the installer.
     *
     * @since 2.0.0
     *
     * @return void
     */
    public function run(): void
    {
        // Update the installed version.
        $this->add_version();
    }


    /**
     * Add time and version on DB.
     *
     * @since 2.0.0
     *
     * @return void
     */
    public function add_version(): void
    {
        // Flush rewrite rules
        // flush_rewrite_rules();

        $installed = get_option(Keys::HVNLYNAB_INSTALLED);

        if (! $installed) {
            update_option(Keys::HVNLYNAB_INSTALLED, time());
        }

        update_option(Keys::HVNLYNAB_VERSION, HVNLYNAB_VERSION);
    }
}
