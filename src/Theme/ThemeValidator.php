<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Theme;

use WPCalibrate\HtmlBridge\Archive\FilePolicy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ThemeValidator
 *
 * Validates a generated theme's structure and template integrity prior to installation.
 */
final class ThemeValidator
{
    /**
     * Validate generated theme files.
     *
     * @param string $themeDir Absolute path to the theme directory.
     * @return array{valid: bool, errors: array<string>, warnings: array<string>}
     */
    public static function validate(string $themeDir): array
    {
        $themeDir = trailingslashit(str_replace('\\', '/', $themeDir));
        $errors = [];
        $warnings = [];

        if (!is_dir($themeDir)) {
            $errors[] = __('Generated theme directory does not exist.', 'wpcalibrate-html-bridge');
            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        // Required WordPress theme files
        if (!file_exists($themeDir . 'style.css')) {
            $errors[] = __('style.css is missing in generated theme.', 'wpcalibrate-html-bridge');
        } else {
            $styleHeader = file_get_contents($themeDir . 'style.css');
            if ($styleHeader === false || !str_contains($styleHeader, 'Theme Name:')) {
                $errors[] = __('style.css missing required "Theme Name:" header.', 'wpcalibrate-html-bridge');
            }
        }

        if (!file_exists($themeDir . 'index.php')) {
            $errors[] = __('index.php is missing in generated theme.', 'wpcalibrate-html-bridge');
        }

        if (!file_exists($themeDir . 'functions.php')) {
            $warnings[] = __('functions.php is missing; theme may lack asset registration.', 'wpcalibrate-html-bridge');
        }

        // Check for disallowed or dangerous files inside the theme
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($themeDir, \FilesystemIterator::SKIP_DOTS)
        );

        $allowedThemeExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'php', 'txt', 'md'];

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                continue;
            }

            $ext = strtolower($item->getExtension());
            if (!in_array($ext, $allowedThemeExtensions, true)) {
                $errors[] = sprintf(
                    /* translators: %s: file path */
                    __('Theme contains disallowed file: %s', 'wpcalibrate-html-bridge'),
                    $item->getFilename()
                );
            }
        }

        return [
            'valid'    => empty($errors),
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }
}
