<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Archive;

use WPCalibrate\HtmlBridge\Security\PathGuard;
use WPCalibrate\HtmlBridge\Support\Filesystem;
use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SecureExtractor
 *
 * Securely extracts allowed archive contents into an isolated, protected temporary directory.
 */
final class SecureExtractor
{
    /**
     * Extract allowed files from a validated ZIP file into an isolated temporary folder.
     *
     * @param string $zipFilePath    Path to the uploaded ZIP file.
     * @param string $destinationDir Isolated destination directory for this import.
     * @param string $rootPrefix     Optional prefix folder inside ZIP to strip out (e.g. 'mysite/').
     * @return array{
     *     success: bool,
     *     extracted_files: array<string>,
     *     blocked_files: array<string>,
     *     skipped_files: array<string>,
     *     errors: array<string>
     * }
     */
    public static function extract(string $zipFilePath, string $destinationDir, string $rootPrefix = ''): array
    {
        $result = [
            'success'         => false,
            'extracted_files' => [],
            'blocked_files'   => [],
            'skipped_files'   => [],
            'errors'          => [],
        ];

        if (!file_exists($zipFilePath)) {
            $result['errors'][] = __('ZIP archive file not found.', 'wpcalibrate-html-bridge');
            return $result;
        }

        $realDest = realpath($destinationDir);
        if ($realDest === false) {
            wp_mkdir_p($destinationDir);
            $realDest = realpath($destinationDir);
        }

        if ($realDest === false) {
            $result['errors'][] = __('Failed to initialize target extraction directory.', 'wpcalibrate-html-bridge');
            return $result;
        }

        Filesystem::protectDirectory($realDest);

        $zip = new \ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            $result['errors'][] = __('Could not open archive for extraction.', 'wpcalibrate-html-bridge');
            return $result;
        }

        $rootPrefixNormalized = $rootPrefix !== '' ? rtrim(str_replace('\\', '/', $rootPrefix), '/') . '/' : '';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) {
                continue;
            }

            $rawName = $stat['name'];
            $normalizedName = str_replace('\\', '/', $rawName);

            // Skip macOS metadata or directory entries
            if (str_starts_with($normalizedName, '__MACOSX/') || basename($normalizedName) === '.DS_Store' || str_ends_with($normalizedName, '/')) {
                continue;
            }

            // Path safety check
            if (!PathGuard::isPathSafe($normalizedName)) {
                $result['blocked_files'][] = $normalizedName;
                continue;
            }

            // Strip root prefix if archive was packed in a single directory
            $relPath = $normalizedName;
            if ($rootPrefixNormalized !== '' && str_starts_with($normalizedName, $rootPrefixNormalized)) {
                $relPath = substr($normalizedName, strlen($rootPrefixNormalized));
            }

            // Check file policy
            if (!FilePolicy::isAllowed($relPath)) {
                $result['blocked_files'][] = $relPath;
                continue;
            }

            // Resolve target absolute path and verify boundary containment
            $targetPath = PathGuard::resolveSafePath($realDest, $relPath);
            if ($targetPath === null) {
                $result['blocked_files'][] = $relPath;
                Logger::warning('Path traversal or boundary escape attempted during extraction', ['file' => $relPath]);
                continue;
            }

            // Create parent directories if they don't exist
            $parentDir = dirname($targetPath);
            if (!is_dir($parentDir)) {
                wp_mkdir_p($parentDir);
            }

            // Read stream and write safely
            $stream = $zip->getStream($rawName);
            if (!$stream) {
                $result['skipped_files'][] = $relPath;
                continue;
            }

            $targetHandle = fopen($targetPath, 'wb');
            if (!$targetHandle) {
                fclose($stream);
                $result['skipped_files'][] = $relPath;
                continue;
            }

            while (!feof($stream)) {
                $chunk = fread($stream, 65536);
                if ($chunk === false) {
                    break;
                }
                fwrite($targetHandle, $chunk);
            }

            fclose($stream);
            fclose($targetHandle);

            $result['extracted_files'][] = $relPath;
        }

        $zip->close();

        $result['success'] = !empty($result['extracted_files']);

        return $result;
    }
}
