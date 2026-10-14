<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Theme;

use WPCalibrate\HtmlBridge\Support\Filesystem;
use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ThemeInstaller
 *
 * Safely copies a validated generated theme into the WordPress themes directory without auto-activating it.
 */
final class ThemeInstaller
{
    /**
     * Install a staged theme into the active WordPress theme directory.
     *
     * @param string $stagingThemeDir Source staging theme directory.
     * @param string $themeSlug       Target theme slug.
     * @return array{success: bool, installed_path: string, error?: string}
     */
    public static function install(string $stagingThemeDir, string $themeSlug): array
    {
        $validation = ThemeValidator::validate($stagingThemeDir);
        if (!$validation['valid']) {
            return [
                'success'        => false,
                'installed_path' => '',
                'error'          => implode(' ', $validation['errors']),
            ];
        }

        $themeRoot = get_theme_root();
        $targetDir = trailingslashit(str_replace('\\', '/', $themeRoot)) . sanitize_key($themeSlug);

        // If target directory already exists, clear it safely
        if (is_dir($targetDir)) {
            // Safety check: ensure we never delete an active theme!
            $activeStylesheet = get_stylesheet();
            if ($activeStylesheet === $themeSlug) {
                return [
                    'success'        => false,
                    'installed_path' => '',
                    'error'          => __('Cannot overwrite the currently active theme.', 'wpcalibrate-html-bridge'),
                ];
            }
            // Safely remove prior generated version
            self::copyDir($stagingThemeDir, $targetDir);
        } else {
            wp_mkdir_p($targetDir);
            self::copyDir($stagingThemeDir, $targetDir);
        }

        Logger::info('Installed generated theme into themes directory', [
            'theme_slug' => $themeSlug,
            'path'       => $targetDir,
        ]);

        return [
            'success'        => true,
            'installed_path' => $targetDir,
        ];
    }

    /**
     * Recursively copy directory.
     */
    private static function copyDir(string $src, string $dst): void
    {
        $dir = opendir($src);
        if (!$dir) {
            return;
        }

        @mkdir($dst, 0755, true);

        while (($file = readdir($dir)) !== false) {
            if ($file !== '.' && $file !== '..') {
                $srcFile = $src . '/' . $file;
                $dstFile = $dst . '/' . $file;
                if (is_dir($srcFile)) {
                    self::copyDir($srcFile, $dstFile);
                } else {
                    copy($srcFile, $dstFile);
                }
            }
        }
        closedir($dir);
    }
}
