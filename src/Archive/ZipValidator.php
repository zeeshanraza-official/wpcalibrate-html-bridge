<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Archive;

use WPCalibrate\HtmlBridge\Security\PathGuard;
use WPCalibrate\HtmlBridge\Settings\Settings;
use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipValidator
 *
 * Inspects untrusted ZIP archives before extraction to detect traversal, bombs, symlinks, and prohibited files.
 */
final class ZipValidator
{
    /**
     * Maximum safe compression ratio (uncompressed / compressed) before treating as a ZIP bomb.
     */
    private const MAX_COMPRESSION_RATIO = 100;

    /**
     * Validate an uploaded ZIP file.
     *
     * @param string $zipFilePath Absolute path to uploaded ZIP file.
     * @return array{
     *     valid: bool,
     *     errors: array<string>,
     *     warnings: array<string>,
     *     blocked_files: array<string>,
     *     total_files: int,
     *     uncompressed_size: int,
     *     compressed_size: int,
     *     has_html_entry: bool,
     *     suggested_root: string
     * }
     */
    public static function validate(string $zipFilePath): array
    {
        $result = [
            'valid'             => false,
            'errors'            => [],
            'warnings'          => [],
            'blocked_files'     => [],
            'total_files'       => 0,
            'uncompressed_size' => 0,
            'compressed_size'   => 0,
            'has_html_entry'    => false,
            'suggested_root'    => '',
        ];

        if (!file_exists($zipFilePath) || !is_readable($zipFilePath)) {
            $result['errors'][] = __('Uploaded archive file could not be read.', 'wpcalibrate-html-bridge');
            return $result;
        }

        $fileSize = filesize($zipFilePath);
        if ($fileSize === false || $fileSize === 0) {
            $result['errors'][] = __('The uploaded archive is empty.', 'wpcalibrate-html-bridge');
            return $result;
        }

        $result['compressed_size'] = $fileSize;

        // Verify ZIP magic bytes
        $handle = fopen($zipFilePath, 'rb');
        if ($handle === false) {
            $result['errors'][] = __('Unable to open file header.', 'wpcalibrate-html-bridge');
            return $result;
        }
        $header = fread($handle, 4);
        fclose($handle);

        if ($header !== "PK\x03\x04" && $header !== "PK\x05\x06" && $header !== "PK\x07\x08") {
            $result['errors'][] = __('File is not a valid ZIP archive (invalid header signature).', 'wpcalibrate-html-bridge');
            return $result;
        }

        // Check configured max ZIP size
        $maxZipBytes = Settings::get('max_zip_size_mb', 64) * 1024 * 1024;
        if ($fileSize > $maxZipBytes) {
            $result['errors'][] = sprintf(
                /* translators: %d: maximum size in MB */
                __('Archive size exceeds the maximum allowed limit of %d MB.', 'wpcalibrate-html-bridge'),
                Settings::get('max_zip_size_mb', 64)
            );
            return $result;
        }

        if (!class_exists(\ZipArchive::class)) {
            $result['errors'][] = __('The PHP ZipArchive extension is required on this server.', 'wpcalibrate-html-bridge');
            return $result;
        }

        $zip = new \ZipArchive();
        $openStatus = $zip->open($zipFilePath, \ZipArchive::CHECKCONS);
        if ($openStatus !== true) {
            $result['errors'][] = sprintf(
                /* translators: %d: error code */
                __('Archive integrity check failed or archive is corrupt (error code: %d).', 'wpcalibrate-html-bridge'),
                $openStatus
            );
            return $result;
        }

        $maxExtractedBytes = Settings::get('max_extracted_size_mb', 256) * 1024 * 1024;
        $maxFiles = (int) Settings::get('max_file_count', 2000);

        $totalFiles = 0;
        $totalUncompressed = 0;
        $htmlEntries = [];
        $rootDirs = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) {
                continue;
            }

            $rawName = $stat['name'];
            $normalizedName = str_replace('\\', '/', $rawName);

            // Ignore macOS metadata and internal directories (__MACOSX, .DS_Store)
            if (str_starts_with($normalizedName, '__MACOSX/') || basename($normalizedName) === '.DS_Store') {
                continue;
            }

            // Path safety check: prevent directory traversal, absolute paths, and null bytes
            if (!PathGuard::isPathSafe($normalizedName)) {
                $zip->close();
                $result['errors'][] = sprintf(
                    /* translators: %s: suspicious file path */
                    __('Suspicious path or directory traversal detected: %s', 'wpcalibrate-html-bridge'),
                    esc_html($rawName)
                );
                return $result;
            }

            // Detect symbolic links (POSIX mode 0120000 in external_attributes)
            if (isset($stat['external_attributes'])) {
                $mode = ($stat['external_attributes'] >> 16) & 0170000;
                if ($mode === 0120000) {
                    $zip->close();
                    $result['errors'][] = sprintf(
                        /* translators: %s: symlink name */
                        __('Symbolic links are not permitted in import archives: %s', 'wpcalibrate-html-bridge'),
                        esc_html($rawName)
                    );
                    return $result;
                }
            }

            $isDir = str_ends_with($normalizedName, '/');
            if ($isDir) {
                continue;
            }

            $totalFiles++;
            $uncompressed = (int) $stat['size'];
            $compressed = (int) $stat['comp_size'];
            $totalUncompressed += $uncompressed;

            // Zip bomb ratio check on individual files
            if ($compressed > 0 && ($uncompressed / $compressed) > self::MAX_COMPRESSION_RATIO && $uncompressed > 10 * 1024 * 1024) {
                $zip->close();
                $result['errors'][] = sprintf(
                    /* translators: %s: file name */
                    __('Potential ZIP bomb detected. Abnormally high compression ratio in: %s', 'wpcalibrate-html-bridge'),
                    esc_html($rawName)
                );
                return $result;
            }

            // File policy: Check extension against blocklist and allowlist
            if (!FilePolicy::isAllowed($normalizedName)) {
                $result['blocked_files'][] = $normalizedName;
                Logger::warning('Blocked prohibited or dangerous file in archive', ['file' => $normalizedName]);
            }

            // Detect HTML files
            if (FilePolicy::isHtml($normalizedName)) {
                $htmlEntries[] = $normalizedName;
            }

            // Track top-level directories to determine if archive is wrapped in a single folder
            $parts = explode('/', trim($normalizedName, '/'));
            if (count($parts) > 1) {
                $rootDirs[$parts[0]] = true;
            }
        }

        $zip->close();

        $result['total_files'] = $totalFiles;
        $result['uncompressed_size'] = $totalUncompressed;

        // Check file limits
        if ($totalFiles > $maxFiles) {
            $result['errors'][] = sprintf(
                /* translators: 1: total files, 2: max limit */
                __('Archive contains %1$d files, exceeding the limit of %2$d.', 'wpcalibrate-html-bridge'),
                $totalFiles,
                $maxFiles
            );
            return $result;
        }

        if ($totalUncompressed > $maxExtractedBytes) {
            $result['errors'][] = sprintf(
                /* translators: %d: max size in MB */
                __('Decompressed size exceeds the maximum limit of %d MB.', 'wpcalibrate-html-bridge'),
                Settings::get('max_extracted_size_mb', 256)
            );
            return $result;
        }

        // Verify HTML entry points
        if (empty($htmlEntries)) {
            $result['errors'][] = __('No usable HTML entry points found in the archive.', 'wpcalibrate-html-bridge');
            return $result;
        }

        $result['has_html_entry'] = true;

        // Determine if there is a common single parent directory wrapping the archive
        if (count($rootDirs) === 1) {
            $commonRoot = array_key_first($rootDirs);
            // Verify if all files start with this common root
            $allUnderCommon = true;
            foreach ($htmlEntries as $htmlFile) {
                if (!str_starts_with($htmlFile, $commonRoot . '/')) {
                    $allUnderCommon = false;
                    break;
                }
            }
            if ($allUnderCommon) {
                $result['suggested_root'] = $commonRoot;
            }
        }

        if (!empty($result['blocked_files'])) {
            $result['warnings'][] = sprintf(
                /* translators: %d: number of blocked files */
                __('%d unsupported or potentially dangerous files were detected and will be excluded from import.', 'wpcalibrate-html-bridge'),
                count($result['blocked_files'])
            );
        }

        $result['valid'] = empty($result['errors']);

        return $result;
    }
}
