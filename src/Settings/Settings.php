<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Settings
 *
 * Manages plugin configuration retrieval and default options.
 */
final class Settings
{
    public const OPTION_KEY = 'wpcalibrate_html_bridge_settings';

    /**
     * Default plugin options.
     */
    public const DEFAULTS = [
        'default_import_mode'       => 'pages',
        'default_conversion_mode'   => 'preserve_design',
        'default_post_status'       => 'draft',
        'max_zip_size_mb'           => 64,
        'max_extracted_size_mb'     => 256,
        'max_file_count'            => 2000,
        'import_media_library'      => true,
        'import_site_icon'          => true,
        'create_navigation_default' => true,
        'set_front_page_default'    => false,
        'preserve_frontend_js'      => true,
        'allow_remote_scripts'      => false,
        'temp_retention_hours'      => 24,
        'manifest_retention_days'   => 90,
        'remove_data_on_uninstall'  => false,
        'enable_debug_logging'      => false,
    ];

    /**
     * Initialize default options without overwriting existing settings.
     */
    public static function initializeDefaults(): void
    {
        $existing = get_option(self::OPTION_KEY, null);
        if ($existing === null) {
            update_option(self::OPTION_KEY, self::DEFAULTS, false);
        }
    }

    /**
     * Get all plugin settings merged with defaults.
     *
     * @return array<string, mixed>
     */
    public static function getAll(): array
    {
        $options = get_option(self::OPTION_KEY, []);
        if (!is_array($options)) {
            $options = [];
        }

        return array_merge(self::DEFAULTS, $options);
    }

    /**
     * Get a specific setting value.
     *
     * @param string $key     Setting key name.
     * @param mixed  $default Optional fallback default.
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::getAll();
        return $all[$key] ?? ($default ?? self::DEFAULTS[$key] ?? null);
    }

    /**
     * Update settings.
     *
     * @param array<string, mixed> $newSettings
     * @return bool
     */
    public static function update(array $newSettings): bool
    {
        $sanitized = SettingsSanitizer::sanitize($newSettings);
        return update_option(self::OPTION_KEY, $sanitized, false);
    }
}
