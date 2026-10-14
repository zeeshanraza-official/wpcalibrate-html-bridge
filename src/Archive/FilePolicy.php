<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Archive;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FilePolicy
 *
 * Enforces file extension allowlists, executable/server-side blocking, and double-extension detection.
 */
final class FilePolicy
{
    /**
     * Dangerous executable or script extensions that are strictly blocked.
     */
    public const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'zsh', 'command',
        'exe', 'dll', 'so', 'bin', 'msi', 'com', 'bat', 'cmd', 'vbs', 'vbe', 'jsb', 'scr',
        'htaccess', 'htpasswd', 'ini', 'user.ini', 'conf', 'config',
        'asp', 'aspx', 'axd', 'asx', 'ashx', 'asmx', 'jsp', 'jspx', 'cfm',
    ];

    /**
     * Default allowed static extensions for import.
     */
    public const DEFAULT_ALLOWED_EXTENSIONS = [
        // Markup
        'html', 'htm', 'xhtml',
        // Styles & Scripts
        'css', 'js', 'json', 'map',
        // Images
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp', 'tiff',
        // Fonts
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        // Media (if present in static sites)
        'mp4', 'webm', 'ogv', 'mp3', 'wav', 'ogg',
        // Documents / static assets
        'pdf', 'txt', 'xml',
    ];

    /**
     * Get the active allowed extensions after applying developer filters.
     *
     * @return array<string>
     */
    public static function getAllowedExtensions(): array
    {
        $allowed = (array) apply_filters(
            'wpcalibrate_html_bridge_allowed_extensions',
            self::DEFAULT_ALLOWED_EXTENSIONS
        );

        return array_values(array_unique(array_map('strtolower', $allowed)));
    }

    /**
     * Check if a filename is allowed under the security policy.
     *
     * @param string $filename
     * @return bool True if allowed, false if rejected.
     */
    public static function isAllowed(string $filename): bool
    {
        $normalized = strtolower(trim(str_replace('\\', '/', $filename)));
        $basename = basename($normalized);

        // Check for hidden or system configuration files (e.g. .htaccess, .user.ini)
        if (str_starts_with($basename, '.')) {
            // Check if it's a known dangerous server config
            $cleanBase = ltrim($basename, '.');
            if (in_array($cleanBase, self::BLOCKED_EXTENSIONS, true)) {
                return false;
            }
        }

        // Check for blocked server config names
        if (in_array($basename, ['web.config', 'nginx.conf', '.htaccess'], true)) {
            return false;
        }

        // Check for double extensions or disguised scripts like file.php.png or file.html.php
        $parts = explode('.', $basename);
        if (count($parts) > 1) {
            // Check all segments to see if any segment is a blocked executable extension
            foreach (array_slice($parts, 1) as $part) {
                if (in_array(strtolower($part), self::BLOCKED_EXTENSIONS, true)) {
                    return false;
                }
            }
        }

        $extension = pathinfo($basename, PATHINFO_EXTENSION);
        if ($extension === '') {
            // Files with no extension are blocked unless explicitly allowed
            return false;
        }

        $extension = strtolower($extension);

        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            return false;
        }

        return in_array($extension, self::getAllowedExtensions(), true);
    }

    /**
     * Check if an extension is an HTML document.
     */
    public static function isHtml(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, ['html', 'htm', 'xhtml'], true);
    }

    /**
     * Check if an extension is a CSS file.
     */
    public static function isCss(string $filename): bool
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'css';
    }

    /**
     * Check if an extension is a JavaScript file.
     */
    public static function isJs(string $filename): bool
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'js';
    }

    /**
     * Check if an extension is an Image.
     */
    public static function isImage(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp', 'tiff'], true);
    }

    /**
     * Check if an extension is a Font.
     */
    public static function isFont(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, ['woff', 'woff2', 'ttf', 'otf', 'eot'], true);
    }
}
