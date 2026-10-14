<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Navigation;

use WPCalibrate\HtmlBridge\Import\ImportRepository;
use WPCalibrate\HtmlBridge\Scanner\SiteScanner;
use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NavigationImporter
 *
 * Converts detected static site navigation structures into WordPress nav menus.
 */
final class NavigationImporter
{
    private ImportRepository $repository;

    public function __construct(ImportRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Import a navigation candidate into WordPress.
     *
     * @param array<string, mixed> $navCandidate
     * @param array<string, int>    $createdPages  Map of relative page paths to WordPress page IDs.
     * @param array<string, string> $urlMap        Map of relative page paths to WordPress permalinks.
     * @param int                   $importId      Import database ID.
     * @param string                $menuName      Name for the WordPress menu.
     * @return int Menu term ID or 0 on failure.
     */
    public function importMenu(
        array $navCandidate,
        array $createdPages,
        array $urlMap,
        int $importId,
        string $menuName = 'Imported Site Menu'
    ): int {
        $links = $navCandidate['links'] ?? [];
        if (empty($links) || !is_array($links)) {
            return 0;
        }

        // Avoid duplicate menu creation on re-import
        $existingItems = $this->repository->getItems($importId, 'menu');
        if (!empty($existingItems)) {
            $existingMenuId = (int) $existingItems[0]['destination_id'];
            if (wp_get_nav_menu_object($existingMenuId)) {
                return $existingMenuId;
            }
        }

        $sourcePage = $navCandidate['page'] ?? 'index.html';

        $menuId = wp_create_nav_menu($menuName);
        if (is_wp_error($menuId)) {
            Logger::error('Failed to create nav menu', ['error' => $menuId->get_error_message()]);
            return 0;
        }

        $position = 1;
        foreach ($links as $link) {
            $href = trim((string) ($link['href'] ?? ''));
            $title = trim((string) ($link['text'] ?? ''));
            if ($title === '') {
                $title = 'Menu Item';
            }

            // Check if link points to an internal page
            $resolvedRel = SiteScanner::resolveRelativeAsset($href, $sourcePage);
            $targetPageId = $createdPages[$resolvedRel] ?? null;

            if ($targetPageId !== null && $targetPageId > 0) {
                // Add as Page menu item
                wp_update_nav_menu_item($menuId, 0, [
                    'menu-item-title'     => $title,
                    'menu-item-object'    => 'page',
                    'menu-item-object-id' => $targetPageId,
                    'menu-item-type'      => 'post_type',
                    'menu-item-status'    => 'publish',
                    'menu-item-position'  => $position++,
                ]);
            } else {
                // Add as Custom Link menu item
                $targetUrl = $urlMap[$resolvedRel] ?? $href;
                wp_update_nav_menu_item($menuId, 0, [
                    'menu-item-title'    => $title,
                    'menu-item-url'      => esc_url_raw($targetUrl),
                    'menu-item-type'     => 'custom',
                    'menu-item-status'   => 'publish',
                    'menu-item-position' => $position++,
                ]);
            }
        }

        // Record in plugin database
        $this->repository->recordItem(
            $importId,
            'navigation',
            hash('sha256', wp_json_encode($links)),
            'menu',
            'nav_menu',
            $menuId,
            '',
            'completed'
        );

        return $menuId;
    }
}
