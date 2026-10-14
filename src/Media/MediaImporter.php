<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Media;

use WPCalibrate\HtmlBridge\Archive\FilePolicy;
use WPCalibrate\HtmlBridge\Import\ImportRepository;
use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MediaImporter
 *
 * Imports static local images and assets into the WordPress Media Library with attachment metadata.
 */
final class MediaImporter
{
    private ImportRepository $repository;

    public function __construct(ImportRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Import a local media file into the WordPress Media Library.
     *
     * @param string   $extractedFile Absolute path to extracted file on disk.
     * @param string   $sourcePath    Relative source path within the archive.
     * @param int      $importId      Plugin import database ID.
     * @param string   $altText       Optional alt text.
     * @param MediaMap $mediaMap      Active media map to avoid duplicates.
     * @return array{success: bool, attachment_id: int, url: string, error?: string}
     */
    public function importAsset(
        string $extractedFile,
        string $sourcePath,
        int $importId,
        string $altText,
        MediaMap $mediaMap
    ): array {
        // Prevent duplicate import of the same asset within this run
        if ($mediaMap->has($sourcePath)) {
            $existing = $mediaMap->get($sourcePath);
            return [
                'success'       => true,
                'attachment_id' => $existing['id'],
                'url'           => $existing['url'],
            ];
        }

        if (!file_exists($extractedFile) || !is_readable($extractedFile)) {
            return [
                'success'       => false,
                'attachment_id' => 0,
                'url'           => '',
                'error'         => sprintf('File not found: %s', $sourcePath),
            ];
        }

        $sourceHash = hash_file('sha256', $extractedFile) ?: '';

        // Check if this hash was already imported in this import batch
        foreach ($mediaMap->getAll() as $mapped) {
            if ($mapped['hash'] === $sourceHash && $mapped['hash'] !== '') {
                $mediaMap->add($sourcePath, $mapped['id'], $mapped['url'], $sourceHash);
                return [
                    'success'       => true,
                    'attachment_id' => $mapped['id'],
                    'url'           => $mapped['url'],
                ];
            }
        }

        // Use WordPress upload APIs
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $filename = basename($sourcePath);
        $fileContent = file_get_contents($extractedFile);
        if ($fileContent === false) {
            return [
                'success'       => false,
                'attachment_id' => 0,
                'url'           => '',
                'error'         => 'Failed to read media content',
            ];
        }

        $upload = wp_upload_bits($filename, null, $fileContent);
        if (!empty($upload['error'])) {
            return [
                'success'       => false,
                'attachment_id' => 0,
                'url'           => '',
                'error'         => $upload['error'],
            ];
        }

        $uploadedFile = $upload['file'];
        $fileType = wp_check_filetype($uploadedFile);

        $attachmentData = [
            'post_mime_type' => $fileType['type'] ?? 'image/jpeg',
            'post_title'     => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachmentId = wp_insert_attachment($attachmentData, $uploadedFile);
        if (is_wp_error($attachmentId) || $attachmentId === 0) {
            @unlink($uploadedFile);
            return [
                'success'       => false,
                'attachment_id' => 0,
                'url'           => '',
                'error'         => is_wp_error($attachmentId) ? $attachmentId->get_error_message() : 'Attachment creation failed',
            ];
        }

        // Generate attachment metadata (image sizes, thumbnails)
        $attachMeta = wp_generate_attachment_metadata($attachmentId, $uploadedFile);
        if (!empty($attachMeta)) {
            wp_update_attachment_metadata($attachmentId, $attachMeta);
        }

        // Set Alt text if present
        if (trim($altText) !== '') {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', sanitize_text_field($altText));
        }

        // Mark attachment ownership for safe rollback
        update_post_meta($attachmentId, '_wpcalibrate_html_bridge_import_id', $importId);
        update_post_meta($attachmentId, '_wpcalibrate_html_bridge_source_path', $sourcePath);

        $attachmentUrl = (string) wp_get_attachment_url($attachmentId);

        // Record into media map
        $mediaMap->add($sourcePath, $attachmentId, $attachmentUrl, $sourceHash);

        // Record in plugin items table
        $this->repository->recordItem(
            $importId,
            $sourcePath,
            $sourceHash,
            'attachment',
            'attachment',
            $attachmentId,
            $attachmentUrl,
            'completed'
        );

        return [
            'success'       => true,
            'attachment_id' => $attachmentId,
            'url'           => $attachmentUrl,
        ];
    }
}
