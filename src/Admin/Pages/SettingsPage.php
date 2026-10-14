<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Admin\Pages;

use WPCalibrate\HtmlBridge\Admin\Notices;
use WPCalibrate\HtmlBridge\Security\Capabilities;
use WPCalibrate\HtmlBridge\Security\Nonces;
use WPCalibrate\HtmlBridge\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SettingsPage
 *
 * Renders and handles settings submission for limits, conversion defaults, JS policies, and data retention.
 */
final class SettingsPage
{
    public function render(): void
    {
        if (!Capabilities::canManage()) {
            wp_die(esc_html__('Unauthorized.', 'wpcalibrate-html-bridge'));
        }

        // Process POST submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpcalibrate_save_settings'])) {
            check_admin_referer(Nonces::SETTINGS_ACTION, 'wpcalibrate_settings_nonce');

            $input = (array) ($_POST['settings'] ?? []);
            Settings::update($input);
            Notices::add(__('Settings saved successfully.', 'wpcalibrate-html-bridge'), 'success');
        }

        $s = Settings::getAll();
        ?>
        <div class="wpcalibrate-card">
            <h2><?php esc_html_e('Importer Settings & Policies', 'wpcalibrate-html-bridge'); ?></h2>

            <form method="post" action="">
                <?php wp_nonce_field(Nonces::SETTINGS_ACTION, 'wpcalibrate_settings_nonce'); ?>

                <h3 class="title"><?php esc_html_e('Import & Conversion Defaults', 'wpcalibrate-html-bridge'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Default Import Mode', 'wpcalibrate-html-bridge'); ?></th>
                        <td>
                            <select name="settings[default_import_mode]">
                                <option value="pages" <?php selected($s['default_import_mode'], 'pages'); ?>><?php esc_html_e('WordPress Pages', 'wpcalibrate-html-bridge'); ?></option>
                                <option value="theme" <?php selected($s['default_import_mode'], 'theme'); ?>><?php esc_html_e('Standalone Theme', 'wpcalibrate-html-bridge'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Default Page Conversion Mode', 'wpcalibrate-html-bridge'); ?></th>
                        <td>
                            <select name="settings[default_conversion_mode]">
                                <option value="preserve_design" <?php selected($s['default_conversion_mode'], 'preserve_design'); ?>><?php esc_html_e('Preserve Design', 'wpcalibrate-html-bridge'); ?></option>
                                <option value="gutenberg" <?php selected($s['default_conversion_mode'], 'gutenberg'); ?>><?php esc_html_e('Gutenberg Conversion', 'wpcalibrate-html-bridge'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Default Publishing Status', 'wpcalibrate-html-bridge'); ?></th>
                        <td>
                            <select name="settings[default_post_status]">
                                <option value="draft" <?php selected($s['default_post_status'], 'draft'); ?>><?php esc_html_e('Draft (Recommended)', 'wpcalibrate-html-bridge'); ?></option>
                                <option value="publish" <?php selected($s['default_post_status'], 'publish'); ?>><?php esc_html_e('Publish', 'wpcalibrate-html-bridge'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h3 class="title"><?php esc_html_e('Archive & Resource Limits', 'wpcalibrate-html-bridge'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="max_zip_size_mb"><?php esc_html_e('Maximum ZIP Archive Size (MB)', 'wpcalibrate-html-bridge'); ?></label></th>
                        <td>
                            <input type="number" id="max_zip_size_mb" name="settings[max_zip_size_mb]" value="<?php echo esc_attr((string) $s['max_zip_size_mb']); ?>" min="1" max="512" class="small-text" />
                            <p class="description"><?php esc_html_e('Default: 64 MB. Conservative size guard against oversized archive uploads.', 'wpcalibrate-html-bridge'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_extracted_size_mb"><?php esc_html_e('Maximum Uncompressed Size (MB)', 'wpcalibrate-html-bridge'); ?></label></th>
                        <td>
                            <input type="number" id="max_extracted_size_mb" name="settings[max_extracted_size_mb]" value="<?php echo esc_attr((string) $s['max_extracted_size_mb']); ?>" min="1" max="1024" class="small-text" />
                            <p class="description"><?php esc_html_e('Default: 256 MB. Guard against decompression bombs.', 'wpcalibrate-html-bridge'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_file_count"><?php esc_html_e('Maximum File Count in Archive', 'wpcalibrate-html-bridge'); ?></label></th>
                        <td>
                            <input type="number" id="max_file_count" name="settings[max_file_count]" value="<?php echo esc_attr((string) $s['max_file_count']); ?>" min="10" max="10000" class="small-text" />
                            <p class="description"><?php esc_html_e('Default: 2,000 files.', 'wpcalibrate-html-bridge'); ?></p>
                        </td>
                    </tr>
                </table>

                <h3 class="title"><?php esc_html_e('JavaScript & Security Policy', 'wpcalibrate-html-bridge'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Frontend JavaScript', 'wpcalibrate-html-bridge'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[preserve_frontend_js]" value="1" <?php checked(!empty($s['preserve_frontend_js'])); ?> />
                                <?php esc_html_e('Preserve allowed frontend JavaScript in Preserve Design mode', 'wpcalibrate-html-bridge'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('JavaScript is isolated to imported pages and never executes in wp-admin.', 'wpcalibrate-html-bridge'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Remote JavaScript', 'wpcalibrate-html-bridge'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[allow_remote_scripts]" value="1" <?php checked(!empty($s['allow_remote_scripts'])); ?> />
                                <?php esc_html_e('Allow remote script loading on imported frontend pages (Default: OFF)', 'wpcalibrate-html-bridge'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <h3 class="title"><?php esc_html_e('Data Retention & Cleanup', 'wpcalibrate-html-bridge'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="temp_retention_hours"><?php esc_html_e('Temporary File Retention (Hours)', 'wpcalibrate-html-bridge'); ?></label></th>
                        <td>
                            <input type="number" id="temp_retention_hours" name="settings[temp_retention_hours]" value="<?php echo esc_attr((string) $s['temp_retention_hours']); ?>" min="1" max="720" class="small-text" />
                            <p class="description"><?php esc_html_e('Temporary extracted working files will be automatically deleted after this period.', 'wpcalibrate-html-bridge'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Uninstallation Cleanup', 'wpcalibrate-html-bridge'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[remove_data_on_uninstall]" value="1" <?php checked(!empty($s['remove_data_on_uninstall'])); ?> />
                                <span class="wpcalibrate-text-danger"><?php esc_html_e('Remove plugin database tables, options, and temporary files when uninstalled (Default: OFF)', 'wpcalibrate-html-bridge'); ?></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Debug Logging', 'wpcalibrate-html-bridge'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[enable_debug_logging]" value="1" <?php checked(!empty($s['enable_debug_logging'])); ?> />
                                <?php esc_html_e('Enable verbose debug logging in error log', 'wpcalibrate-html-bridge'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="wpcalibrate_save_settings" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'wpcalibrate-html-bridge'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }
}
