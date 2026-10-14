<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Nonces
 *
 * Provides nonce verification and creation constants.
 */
final class Nonces
{
    public const ADMIN_ACTION   = 'wpcalibrate_html_bridge_admin_nonce';
    public const UPLOAD_ACTION  = 'wpcalibrate_html_bridge_upload';
    public const IMPORT_ACTION  = 'wpcalibrate_html_bridge_import';
    public const ROLLBACK_ACTION= 'wpcalibrate_html_bridge_rollback';
    public const SETTINGS_ACTION= 'wpcalibrate_html_bridge_settings';

    /**
     * Verify a nonce with standard action.
     *
     * @param string $nonce  Nonce value sent by client.
     * @param string $action Action name.
     * @return bool
     */
    public static function verify(string $nonce, string $action = self::ADMIN_ACTION): bool
    {
        return (bool) wp_verify_nonce($nonce, $action);
    }
}
