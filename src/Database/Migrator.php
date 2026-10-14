<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Database;

use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Migrator
 *
 * Runs repeatable, versioned database schema upgrades safely.
 */
final class Migrator
{
    /**
     * Check if a database migration is needed and run it if necessary.
     */
    public static function maybeMigrate(): void
    {
        $installedVersion = (string) get_option(Schema::SCHEMA_VERSION_OPTION, '0.0.0');

        if (version_compare($installedVersion, WPCALIBRATE_HTML_BRIDGE_DB_VERSION, '<')) {
            self::migrate($installedVersion);
        }
    }

    /**
     * Execute migrations.
     *
     * @param string $fromVersion Version migrating from.
     */
    public static function migrate(string $fromVersion): void
    {
        try {
            Logger::info('Starting database migration', [
                'from_version' => $fromVersion,
                'to_version'   => WPCALIBRATE_HTML_BRIDGE_DB_VERSION,
            ]);

            Schema::createTables();

            update_option(Schema::SCHEMA_VERSION_OPTION, WPCALIBRATE_HTML_BRIDGE_DB_VERSION, false);

            Logger::info('Database migration completed successfully');
        } catch (\Throwable $e) {
            Logger::critical('Database migration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
