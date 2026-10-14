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
 * Class Activator
 *
 * Runs during plugin activation.
 */
final class Activator
{
    /**
     * Handle plugin activation.
     *
     * @param bool $networkWide Whether activated across multisite network.
     */
    public static function activate(bool $networkWide = false): void
    {
        // Enforce PHP version requirement
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            deactivate_plugins(WPCALIBRATE_HTML_BRIDGE_BASENAME);
            wp_die(
                esc_html__(
                    'WPCalibrate HTML Bridge requires PHP 8.2 or higher. Activation stopped.',
                    'wpcalibrate-html-bridge'
                ),
                esc_html__('Plugin Activation Error', 'wpcalibrate-html-bridge'),
                ['back_link' => true]
            );
        }

        try {
            // Create / update database schema
            Schema::createTables();

            // Initialize default options without overwriting existing settings
            Settings::initializeDefaults();

            // Ensure isolated uploads directory and protective files exist
            Filesystem::getTempDir();

            // Schedule recurring cron event for temp directory cleanup if not already scheduled
            if (!wp_next_scheduled('wpcalibrate_html_bridge_daily_cleanup')) {
                wp_schedule_event(time() + 3600, 'daily', 'wpcalibrate_html_bridge_daily_cleanup');
            }

            Logger::info('Plugin activated successfully', [
                'version' => WPCALIBRATE_HTML_BRIDGE_VERSION,
                'network' => $networkWide,
            ]);
        } catch (\Throwable $e) {
            Logger::critical('Plugin activation failed', [
                'error' => $e->getMessage(),
            ]);
            wp_die(
                esc_html__(
                    'WPCalibrate HTML Bridge could not be activated due to an error: ' . $e->getMessage(),
                    'wpcalibrate-html-bridge'
                ),
                esc_html__('Plugin Activation Error', 'wpcalibrate-html-bridge'),
                ['back_link' => true]
            );
        }
    }
}
