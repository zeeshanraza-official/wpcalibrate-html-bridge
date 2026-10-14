<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SettingsSanitizer
 *
 * Validates and sanitizes all plugin settings.
 */
final class SettingsSanitizer
{
    /**
     * Sanitize settings array.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $sanitized = [];

        // Import Defaults
        $sanitized['default_import_mode'] = in_array($input['default_import_mode'] ?? '', ['pages', 'theme'], true)
            ? (string) $input['default_import_mode']
            : 'pages';

        $sanitized['default_conversion_mode'] = in_array($input['default_conversion_mode'] ?? '', ['preserve_design', 'gutenberg'], true)
            ? (string) $input['default_conversion_mode']
            : 'preserve_design';

        $sanitized['default_post_status'] = in_array($input['default_post_status'] ?? '', ['draft', 'publish'], true)
            ? (string) $input['default_post_status']
            : 'draft';

        // Upload Limits
        $sanitized['max_zip_size_mb'] = max(1, min(512, (int) ($input['max_zip_size_mb'] ?? 64)));
        $sanitized['max_extracted_size_mb'] = max(1, min(1024, (int) ($input['max_extracted_size_mb'] ?? 256)));
        $sanitized['max_file_count'] = max(10, min(10000, (int) ($input['max_file_count'] ?? 2000)));

        // Asset Handling
        $sanitized['import_media_library'] = !empty($input['import_media_library']);
        $sanitized['import_site_icon'] = !empty($input['import_site_icon']);

        // Navigation & Front Page
        $sanitized['create_navigation_default'] = !empty($input['create_navigation_default']);
        $sanitized['set_front_page_default'] = !empty($input['set_front_page_default']);

        // JavaScript Policy
        $sanitized['preserve_frontend_js'] = !empty($input['preserve_frontend_js']);
        $sanitized['allow_remote_scripts'] = !empty($input['allow_remote_scripts']);

        // Retention & Data
        $sanitized['temp_retention_hours'] = max(1, min(720, (int) ($input['temp_retention_hours'] ?? 24)));
        $sanitized['manifest_retention_days'] = max(7, min(365, (int) ($input['manifest_retention_days'] ?? 90)));
        $sanitized['remove_data_on_uninstall'] = !empty($input['remove_data_on_uninstall']);

        // Logging & Advanced
        $sanitized['enable_debug_logging'] = !empty($input['enable_debug_logging']);

        return $sanitized;
    }
}
