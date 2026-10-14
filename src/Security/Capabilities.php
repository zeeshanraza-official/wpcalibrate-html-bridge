<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Capabilities
 *
 * Centralizes capability checks for importer administration and protected actions.
 */
final class Capabilities
{
    /**
     * Default core capability required for managing the HTML Bridge plugin.
     */
    public const REQUIRED_CAPABILITY = 'manage_options';

    /**
     * Check if the current user has permission to manage imports, run scans, or edit settings.
     *
     * @return bool True if authorized, false otherwise.
     */
    public static function canManage(): bool
    {
        $capability = (string) apply_filters('wpcalibrate_html_bridge_capability', self::REQUIRED_CAPABILITY);
        return current_user_can($capability);
    }

    /**
     * Check if the current user can import raw or filtered HTML.
     * Administrators typically have 'unfiltered_html' on standard single-site installs.
     *
     * @return bool True if authorized.
     */
    public static function canImportHtml(): bool
    {
        if (!self::canManage()) {
            return false;
        }

        // On multisite, unfiltered_html is restricted to super admins unless specifically granted.
        if (is_multisite() && !current_user_can('unfiltered_html')) {
            return (bool) apply_filters('wpcalibrate_html_bridge_allow_multisite_html', false);
        }

        return true;
    }
}
