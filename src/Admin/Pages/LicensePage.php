<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Admin\Pages;

use WPCalibrate\HtmlBridge\Admin\Notices;
use WPCalibrate\HtmlBridge\License\LicenseManager;
use WPCalibrate\HtmlBridge\Security\Capabilities;
use WPCalibrate\HtmlBridge\Security\Nonces;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LicensePage
 *
 * Renders the license management interface with masked keys, explicit status, and provider requirement notices.
 */
final class LicensePage
{
    private LicenseManager $licenseManager;

    public function __construct(?LicenseManager $licenseManager = null)
    {
        $this->licenseManager = $licenseManager ?? new LicenseManager();
    }

    public function render(): void
    {
        if (!Capabilities::canManage()) {
            wp_die(esc_html__('Unauthorized.', 'wpcalibrate-html-bridge'));
        }

        // Handle POST save or deactivate
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer(Nonces::ADMIN_ACTION, 'wpcalibrate_license_nonce');

            if (isset($_POST['wpcalibrate_deactivate_license'])) {
                $result = $this->licenseManager->deactivate();
                Notices::add($result['message'], 'info');
            } elseif (isset($_POST['wpcalibrate_save_license'])) {
                $key = sanitize_text_field((string) ($_POST['license_key'] ?? ''));
                $result = $this->licenseManager->saveKey($key);
                Notices::add($result['message'], $result['success'] ? 'success' : 'warning');
            }
        }

        $info = $this->licenseManager->getLicenseInfo();
        ?>
        <div class="wpcalibrate-card">
            <h2><?php esc_html_e('License Management', 'wpcalibrate-html-bridge'); ?></h2>

            <div class="notice notice-info inline" style="margin: 15px 0 25px 0;">
                <p>
                    <strong><?php esc_html_e('Provider Integration Notice:', 'wpcalibrate-html-bridge'); ?></strong>
                    <?php esc_html_e('No remote licensing provider API details have been configured. Remote validation endpoints are kept securely inactive. The interface below allows entering license keys for upcoming provider integrations.', 'wpcalibrate-html-bridge'); ?>
                </p>
            </div>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('License Status', 'wpcalibrate-html-bridge'); ?></th>
                    <td>
                        <span class="wpcalibrate-badge wpcalibrate-badge-secondary">
                            <?php echo esc_html(strtoupper($info['status'])); ?>
                        </span>
                        <p class="description">
                            <?php esc_html_e('Status will update automatically once a live licensing service endpoint is integrated.', 'wpcalibrate-html-bridge'); ?>
                        </p>
                    </td>
                </tr>

                <?php if ($info['key_masked'] !== '') : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Active Masked Key', 'wpcalibrate-html-bridge'); ?></th>
                        <td>
                            <code><?php echo esc_html($info['key_masked']); ?></code>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php if ($info['expires_at'] !== '') : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Expiration Date', 'wpcalibrate-html-bridge'); ?></th>
                        <td><?php echo esc_html($info['expires_at']); ?></td>
                    </tr>
                <?php endif; ?>
            </table>

            <form method="post" action="">
                <?php wp_nonce_field(Nonces::ADMIN_ACTION, 'wpcalibrate_license_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="license_key"><?php esc_html_e('Enter License Key', 'wpcalibrate-html-bridge'); ?></label></th>
                        <td>
                            <input type="password" id="license_key" name="license_key" class="regular-text" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off" />
                            <p class="description"><?php esc_html_e('Your license key is stored securely and never logged in plain text.', 'wpcalibrate-html-bridge'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="wpcalibrate_save_license" class="button button-primary" value="<?php esc_attr_e('Save License Key', 'wpcalibrate-html-bridge'); ?>" />
                    <?php if ($info['key_masked'] !== '') : ?>
                        <input type="submit" name="wpcalibrate_deactivate_license" class="button button-secondary" value="<?php esc_attr_e('Deactivate / Remove Key', 'wpcalibrate-html-bridge'); ?>" style="margin-left: 10px;" />
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <?php
    }
}
