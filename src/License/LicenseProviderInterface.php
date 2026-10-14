<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\License;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface LicenseProviderInterface
 *
 * Defines contract for real licensing validation providers.
 */
interface LicenseProviderInterface
{
    /**
     * Get the provider's human-readable name.
     */
    public function getName(): string;

    /**
     * Check if this licensing provider is configured and available.
     */
    public function isConfigured(): bool;

    /**
     * Attempt to activate a license key.
     *
     * @param string $licenseKey
     * @return array{success: bool, status: string, message: string, expires_at?: string}
     */
    public function activate(string $licenseKey): array;

    /**
     * Attempt to deactivate an active license key.
     *
     * @param string $licenseKey
     * @return array{success: bool, status: string, message: string}
     */
    public function deactivate(string $licenseKey): array;

    /**
     * Verify the current status of an activated license key.
     *
     * @param string $licenseKey
     * @return array{valid: bool, status: string, expires_at?: string}
     */
    public function verify(string $licenseKey): array;
}
