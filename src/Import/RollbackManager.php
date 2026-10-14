<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Import;

use WPCalibrate\HtmlBridge\Support\Filesystem;
use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RollbackManager
 *
 * Rolls back an import safely by trashing created pages, cleaning generated menus and themes without touching unrelated site content.
 */
final class RollbackManager
{
    private ImportRepository $repository;

    public function __construct(ImportRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute rollback for an import.
     *
     * @param int  $importId
     * @param bool $deleteAttachments Whether to trash/delete media attachments created by this import (default false).
     * @return array{
     *     success: bool,
     *     trashed_pages: int,
     *     deleted_menus: int,
     *     deleted_themes: int,
     *     errors: array<string>
     * }
     */
    public function rollback(int $importId, bool $deleteAttachments = false): array
    {
        $import = $this->repository->getImportById($importId);
        if (!$import) {
            return [
                'success'        => false,
                'trashed_pages'  => 0,
                'deleted_menus'  => 0,
                'deleted_themes' => 0,
                'errors'         => [__('Import record not found.', 'wpcalibrate-html-bridge')],
            ];
        }

        $this->repository->updateStatus($importId, ImportState::ROLLING_BACK);

        $items = $this->repository->getItems($importId);
        $trashedPages = 0;
        $deletedMenus = 0;
        $deletedThemes = 0;
        $errors = [];

        do_action('wpcalibrate_html_bridge_before_rollback', $importId);

        foreach ($items as $item) {
            $destType = $item['destination_type'] ?? '';
            $destId = (int) ($item['destination_id'] ?? 0);

            // 1. Pages -> Trash
            if ($destType === 'post' && $destId > 0) {
                // Verify ownership meta before modifying
                $ownerImportId = (int) get_post_meta($destId, '_wpcalibrate_html_bridge_import_id', true);
                if ($ownerImportId === $importId) {
                    $trashed = wp_trash_post($destId);
                    if ($trashed) {
                        $trashedPages++;
                    } else {
                        $errors[] = sprintf('Failed to trash page #%d', $destId);
                    }
                }
            }

            // 2. Nav Menus -> Delete
            if ($destType === 'nav_menu' && $destId > 0) {
                wp_delete_nav_menu($destId);
                $deletedMenus++;
            }

            // 3. Optional Attachments -> Trash
            if ($deleteAttachments && $destType === 'attachment' && $destId > 0) {
                $ownerImportId = (int) get_post_meta($destId, '_wpcalibrate_html_bridge_import_id', true);
                if ($ownerImportId === $importId) {
                    wp_delete_attachment($destId, false); // move to trash
                }
            }

            // 4. Staged or Installed Theme
            if ($destType === 'theme' && !empty($item['details'])) {
                $details = json_decode($item['details'], true);
                $themeSlug = $details['theme_slug'] ?? '';
                if ($themeSlug !== '') {
                    $activeTheme = get_stylesheet();
                    if ($activeTheme !== $themeSlug) {
                        $themeDir = trailingslashit(get_theme_root()) . $themeSlug;
                        if (is_dir($themeDir)) {
                            Filesystem::deleteDirectory($themeDir);
                            $deletedThemes++;
                        }
                    } else {
                        $errors[] = __('Generated theme is currently active. Please switch themes before rolling back.', 'wpcalibrate-html-bridge');
                    }
                }
            }
        }

        // Restore homepage settings if modified
        $settings = json_decode((string) $import['settings'], true);
        if (is_array($settings) && isset($settings['previous_show_on_front'], $settings['previous_page_on_front'])) {
            update_option('show_on_front', $settings['previous_show_on_front']);
            update_option('page_on_front', $settings['previous_page_on_front']);
        }

        $finalStatus = empty($errors) ? ImportState::ROLLED_BACK : ImportState::FAILED;
        $this->repository->updateStatus($importId, $finalStatus, !empty($errors) ? implode('; ', $errors) : null);

        do_action('wpcalibrate_html_bridge_after_rollback', $importId, $trashedPages);

        Logger::info('Completed rollback of import', [
            'import_id'     => $importId,
            'trashed_pages' => $trashedPages,
            'deleted_menus' => $deletedMenus,
        ]);

        return [
            'success'        => empty($errors),
            'trashed_pages'  => $trashedPages,
            'deleted_menus'  => $deletedMenus,
            'deleted_themes' => $deletedThemes,
            'errors'         => $errors,
        ];
    }
}
