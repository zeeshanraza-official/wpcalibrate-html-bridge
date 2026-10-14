<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Import;

use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ReimportManager
 *
 * Coordinates re-importing a static site package to safely update existing pages or fork as a new import.
 */
final class ReimportManager
{
    private ImportRepository $repository;

    public function __construct(ImportRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Prepare a previous import for re-execution in update mode.
     *
     * @param int $importId
     * @return array{success: bool, error?: string}
     */
    public function prepareForUpdate(int $importId): array
    {
        $import = $this->repository->getImportById($importId);
        if (!$import) {
            return ['success' => false, 'error' => __('Import not found.', 'wpcalibrate-html-bridge')];
        }

        // Validate that owned pages still exist and verify ownership
        $items = $this->repository->getItems($importId, 'page');
        $validItemsCount = 0;

        foreach ($items as $item) {
            $postId = (int) ($item['destination_id'] ?? 0);
            if ($postId > 0) {
                $owner = (int) get_post_meta($postId, '_wpcalibrate_html_bridge_import_id', true);
                if ($owner === $importId) {
                    $validItemsCount++;
                }
            }
        }

        Logger::info('Prepared import for re-import update', [
            'import_id'        => $importId,
            'identified_pages' => $validItemsCount,
        ]);

        $this->repository->updateStatus($importId, ImportState::QUEUED);

        return ['success' => true];
    }
}
