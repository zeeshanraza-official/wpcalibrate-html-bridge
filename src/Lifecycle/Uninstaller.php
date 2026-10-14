<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Lifecycle;

use WPCalibrate\HtmlBridge\Database\Schema;
use WPCalibrate\HtmlBridge\Settings\Settings;
use WPCalibrate\HtmlBridge\Support\Filesystem;
use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Uninstaller
 *
 * Runs during plugin uninstallation.
 */
final class Uninstaller
{
    /**
     * Execute cleanup upon uninstallation if data removal setting is explicitly enabled.
     */
    public static function uninstall(): void
    {
        $settings = Settings::getAll();
        $removeData = !empty($settings['remove_data_on_uninstall']);

        if (!$removeData) {
            // By default, preserve all data and tables unless explicitly opted in
            return;
        }

        Logger::info('Executing uninstallation cleanup per administrator settings');

        // 1. Drop plugin custom tables
        Schema::dropTables();

        // 2. Delete plugin options
        delete_option('wpcalibrate_html_bridge_settings');
        delete_option('wpcalibrate_html_bridge_schema_version');
        delete_option('wpcalibrate_html_bridge_license');

        // 3. Clear plugin transients
        delete_transient('wpcalibrate_html_bridge_global_import_lock');

        // 4. Clear scheduled events
        $timestamp = wp_next_scheduled('wpcalibrate_html_bridge_daily_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wpcalibrate_html_bridge_daily_cleanup');
        }

        // 5. Delete importer temporary files directory
        Filesystem::deleteDirectory(Filesystem::getStorageDir());
    }
}
