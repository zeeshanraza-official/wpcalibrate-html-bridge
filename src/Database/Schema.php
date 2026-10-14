<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Schema
 *
 * Defines and creates custom database tables for imports and import items using dbDelta().
 *
 * Custom Table Justification:
 * Static site imports generate complex relational data: hundreds of asset mappings,
 * link transformation registries, rollback state tracking, and parsed manifests.
 * Storing these in wp_posts or autoloaded wp_options would severely bloat core WordPress
 * tables and degrade overall site performance. Dedicated indexed tables provide
 * high performance, clean rollbacks, isolated data lifecycles, and atomic transactions.
 */
final class Schema
{
    /**
     * Option key storing current schema version.
     */
    public const SCHEMA_VERSION_OPTION = 'wpcalibrate_html_bridge_schema_version';

    /**
     * Get the table name for imports.
     */
    public static function getImportsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wpcalibrate_html_bridge_imports';
    }

    /**
     * Get the table name for individual import items.
     */
    public static function getItemsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wpcalibrate_html_bridge_items';
    }

    /**
     * Create or update database tables via dbDelta().
     */
    public static function createTables(): void
    {
        global $wpdb;

        if (!function_exists('dbDelta') && file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charsetCollate = $wpdb->get_charset_collate();
        $importsTable = self::getImportsTable();
        $itemsTable = self::getItemsTable();

        // Schema for imports table
        $sqlImports = "CREATE TABLE {$importsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            uuid varchar(36) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source_filename varchar(255) NOT NULL DEFAULT '',
            source_root text DEFAULT NULL,
            import_mode varchar(30) NOT NULL DEFAULT 'pages',
            conversion_mode varchar(30) NOT NULL DEFAULT 'preserve_design',
            status varchar(30) NOT NULL DEFAULT 'uploaded',
            manifest longtext DEFAULT NULL,
            settings longtext DEFAULT NULL,
            error_summary longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uuid (uuid),
            KEY status (status),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charsetCollate};";

        // Schema for import items table
        $sqlItems = "CREATE TABLE {$itemsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            import_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source_path text NOT NULL,
            source_hash char(64) NOT NULL DEFAULT '',
            item_type varchar(30) NOT NULL DEFAULT 'page',
            destination_type varchar(30) DEFAULT NULL,
            destination_id bigint(20) unsigned DEFAULT NULL,
            destination_url text DEFAULT NULL,
            status varchar(30) NOT NULL DEFAULT 'pending',
            details longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY import_id (import_id),
            KEY item_type (item_type),
            KEY source_hash (source_hash),
            KEY status (status)
        ) {$charsetCollate};";

        dbDelta($sqlImports);
        dbDelta($sqlItems);

        update_option(self::SCHEMA_VERSION_OPTION, WPCALIBRATE_HTML_BRIDGE_DB_VERSION, false);
    }

    /**
     * Drop plugin custom tables upon explicit uninstall request.
     */
    public static function dropTables(): void
    {
        global $wpdb;

        $importsTable = self::getImportsTable();
        $itemsTable = self::getItemsTable();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$itemsTable}");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$importsTable}");

        delete_option(self::SCHEMA_VERSION_OPTION);
    }
}
