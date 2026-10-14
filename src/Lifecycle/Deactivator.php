<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Lifecycle;

use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Deactivator
 *
 * Runs during plugin deactivation.
 */
final class Deactivator
{
    /**
     * Handle plugin deactivation.
     *
     * @param bool $networkWide Whether deactivated across multisite network.
     */
    public static function deactivate(bool $networkWide = false): void
    {
        // Clear scheduled cron events
        $timestamp = wp_next_scheduled('wpcalibrate_html_bridge_daily_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wpcalibrate_html_bridge_daily_cleanup');
        }

        // Release any lingering stale import locks (transients)
        delete_transient('wpcalibrate_html_bridge_global_import_lock');

        Logger::info('Plugin deactivated successfully', [
            'network' => $networkWide,
        ]);
    }
}
