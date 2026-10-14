<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\License;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NullLicenseProvider
 *
 * Safe default licensing adapter when no remote licensing service or provider is configured.
 * Does not make arbitrary remote requests and does not simulate fake activations.
 */
final class NullLicenseProvider implements LicenseProviderInterface
{
    public function getName(): string
    {
        return 'Not Configured';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function activate(string $licenseKey): array
    {
        return [
            'success' => false,
            'status'  => 'unconfigured',
            'message' => __('Licensing provider is not configured. External activation requires provider integration settings.', 'wpcalibrate-html-bridge'),
        ];
    }

    public function deactivate(string $licenseKey): array
    {
        return [
            'success' => true,
            'status'  => 'inactive',
            'message' => __('License removed locally.', 'wpcalibrate-html-bridge'),
        ];
    }

    public function verify(string $licenseKey): array
    {
        return [
            'valid'  => false,
            'status' => 'unconfigured',
        ];
    }
}
