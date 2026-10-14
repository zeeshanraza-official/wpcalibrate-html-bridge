<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\License;

use WPCalibrate\HtmlBridge\Security\Capabilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LicenseManager
 *
 * Manages license settings, key masking, status reporting, and provider adapter delegation.
 */
final class LicenseManager
{
    public const OPTION_KEY = 'wpcalibrate_html_bridge_license';

    private LicenseProviderInterface $provider;

    public function __construct(?LicenseProviderInterface $provider = null)
    {
        $this->provider = $provider ?? new NullLicenseProvider();
    }

    /**
     * Get stored license state.
     *
     * @return array{
     *     key_masked: string,
     *     status: string,
     *     expires_at: string,
     *     provider_configured: bool
     * }
     */
    public function getLicenseInfo(): array
    {
        $data = get_option(self::OPTION_KEY, []);
        if (!is_array($data)) {
            $data = [];
        }

        $rawKey = (string) ($data['key'] ?? '');
        $status = (string) ($data['status'] ?? 'inactive');
        $expiresAt = (string) ($data['expires_at'] ?? '');

        return [
            'key_masked'          => self::maskKey($rawKey),
            'status'              => $this->provider->isConfigured() ? $status : 'unconfigured',
            'expires_at'          => $expiresAt,
            'provider_configured' => $this->provider->isConfigured(),
        ];
    }

    /**
     * Handle license key save or activation request.
     *
     * @param string $licenseKey
     * @return array{success: bool, message: string}
     */
    public function saveKey(string $licenseKey): array
    {
        if (!Capabilities::canManage()) {
            return ['success' => false, 'message' => __('Unauthorized action.', 'wpcalibrate-html-bridge')];
        }

        $trimmed = trim(sanitize_text_field($licenseKey));

        if ($trimmed === '') {
            delete_option(self::OPTION_KEY);
            return ['success' => true, 'message' => __('License key removed.', 'wpcalibrate-html-bridge')];
        }

        // Check provider activation
        if (!$this->provider->isConfigured()) {
            // Save key locally without reporting active
            update_option(self::OPTION_KEY, [
                'key'        => $trimmed,
                'status'     => 'unconfigured',
                'expires_at' => '',
            ], false);

            return [
                'success' => false,
                'message' => __('Key saved. Remote validation is disabled until a licensing provider endpoint is configured.', 'wpcalibrate-html-bridge'),
            ];
        }

        $result = $this->provider->activate($trimmed);

        update_option(self::OPTION_KEY, [
            'key'        => $trimmed,
            'status'     => $result['status'],
            'expires_at' => $result['expires_at'] ?? '',
        ], false);

        return [
            'success' => $result['success'],
            'message' => $result['message'],
        ];
    }

    /**
     * Deactivate currently saved license key.
     *
     * @return array{success: bool, message: string}
     */
    public function deactivate(): array
    {
        if (!Capabilities::canManage()) {
            return ['success' => false, 'message' => __('Unauthorized action.', 'wpcalibrate-html-bridge')];
        }

        $info = get_option(self::OPTION_KEY, []);
        $rawKey = is_array($info) ? (string) ($info['key'] ?? '') : '';

        if ($rawKey !== '' && $this->provider->isConfigured()) {
            $this->provider->deactivate($rawKey);
        }

        delete_option(self::OPTION_KEY);

        return [
            'success' => true,
            'message' => __('License deactivated.', 'wpcalibrate-html-bridge'),
        ];
    }

    /**
     * Mask a license key string for safe UI presentation.
     *
     * @param string $key
     * @return string
     */
    public static function maskKey(string $key): string
    {
        $len = strlen($key);
        if ($len <= 4) {
            return str_repeat('•', $len);
        }

        $visible = substr($key, -4);
        return str_repeat('•', max(4, $len - 4)) . '-' . $visible;
    }
}
