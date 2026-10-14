<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Support;

use WPCalibrate\HtmlBridge\Security\PathGuard;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Filesystem
 *
 * Isolated filesystem operations with permission safety and path containment.
 */
final class Filesystem
{
    private const BASE_SUBDIR = 'wpcalibrate-html-bridge';

    /**
     * Get the base directory for WPCalibrate HTML Bridge under wp-content/uploads.
     * Creates and locks the directory against PHP execution if it does not exist.
     *
     * @return string Absolute directory path with trailing slash.
     */
    public static function getStorageDir(): string
    {
        $uploadDir = wp_upload_dir();
        $baseDir = trailingslashit($uploadDir['basedir']) . self::BASE_SUBDIR . '/';

        if (!file_exists($baseDir)) {
            wp_mkdir_p($baseDir);
            self::protectDirectory($baseDir);
        }

        return $baseDir;
    }

    /**
     * Get the temporary working directory for extracted archives and staging.
     */
    public static function getTempDir(): string
    {
        $tempDir = self::getStorageDir() . 'temp/';
        if (!file_exists($tempDir)) {
            wp_mkdir_p($tempDir);
            self::protectDirectory($tempDir);
        }

        return $tempDir;
    }

    /**
     * Create an isolated temporary working directory for a specific import UUID.
     */
    public static function getImportTempDir(string $uuid): string
    {
        $sanitizedUuid = preg_replace('/[^a-zA-Z0-9_\-]/', '', $uuid);
        $importDir = self::getTempDir() . $sanitizedUuid . '/';

        if (!file_exists($importDir)) {
            wp_mkdir_p($importDir);
            self::protectDirectory($importDir);
        }

        return $importDir;
    }

    /**
     * Get the base URL for WPCalibrate HTML Bridge under wp-content/uploads.
     *
     * @return string Base storage URL with trailing slash.
     */
    public static function getStorageUrl(): string
    {
        $uploadDir = wp_upload_dir();
        return trailingslashit((string) ($uploadDir['baseurl'] ?? '')) . self::BASE_SUBDIR . '/';
    }

    /**
     * Get the temporary working URL for a specific import UUID.
     *
     * @param string $uuid
     * @return string
     */
    public static function getImportTempUrl(string $uuid): string
    {
        $sanitizedUuid = preg_replace('/[^a-zA-Z0-9_\-]/', '', $uuid);
        return self::getStorageUrl() . 'temp/' . $sanitizedUuid . '/';
    }

    /**
     * Protect a storage directory against direct script execution and directory indexing.
     */
    public static function protectDirectory(string $dir): void
    {
        $dir = trailingslashit($dir);

        // index.php to prevent directory listing
        $indexFile = $dir . 'index.php';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, "<?php\n// Silence is golden.\n");
        }

        // .htaccess to deny script execution on Apache/LiteSpeed
        $htaccessFile = $dir . '.htaccess';
        if (!file_exists($htaccessFile)) {
            $rules = "# WPCalibrate HTML Bridge Security Lock\n"
                   . "<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh|bash)$\">\n"
                   . "Order Allow,Deny\n"
                   . "Deny from all\n"
                   . "</FilesMatch>\n"
                   . "Options -Indexes\n";
            file_put_contents($htaccessFile, $rules);
        }
    }

    /**
     * Safely delete a directory and all its contents recursively, ensuring it stays within temp directory.
     *
     * @param string $dir Path to directory to remove.
     * @return bool True if successfully deleted or directory did not exist.
     */
    public static function deleteDirectory(string $dir): bool
    {
        if (!file_exists($dir)) {
            return true;
        }

        $realDir = realpath($dir);
        $tempBase = realpath(self::getStorageDir());

        // Safety assertion: Never delete anything outside our storage directory
        if ($realDir === false || $tempBase === false || !str_starts_with(str_replace('\\', '/', $realDir), str_replace('\\', '/', $tempBase))) {
            Logger::error('Refused to delete directory outside storage boundaries', ['target' => $dir]);
            return false;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        return rmdir($dir);
    }

    /**
     * Clean up temporary directories older than retention hours.
     *
     * @param int $retentionHours Hours to retain temporary files.
     */
    public static function cleanExpiredTemp(int $retentionHours = 24): void
    {
        $tempDir = self::getTempDir();
        if (!is_dir($tempDir)) {
            return;
        }

        $now = time();
        $cutoff = $now - ($retentionHours * 3600);

        $entries = scandir($tempDir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'index.php' || $entry === '.htaccess') {
                continue;
            }

            $path = $tempDir . $entry;
            if (is_dir($path)) {
                $mtime = filemtime($path);
                if ($mtime !== false && $mtime < $cutoff) {
                    self::deleteDirectory($path);
                    Logger::info('Cleaned expired temporary import directory', ['dir' => $entry]);
                }
            }
        }
    }
}
