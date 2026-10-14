<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Conversion;

use WPCalibrate\HtmlBridge\Import\ImportManifest;
use WPCalibrate\HtmlBridge\Import\ImportRepository;
use WPCalibrate\HtmlBridge\Media\MediaMap;
use WPCalibrate\HtmlBridge\Scanner\SiteScanner;
use WPCalibrate\HtmlBridge\Security\Sanitizer;
use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PageImporter
 *
 * Imports static HTML pages as hierarchical WordPress Pages using core post APIs.
 */
final class PageImporter
{
    private ImportRepository $repository;

    public function __construct(ImportRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Import pages from an extracted site archive.
     *
     * @param string         $extractedDir Absolute path to extracted folder.
     * @param ImportManifest $manifest
     * @param int            $importId     Database ID of import.
     * @param string         $conversionMode 'preserve_design' or 'gutenberg'.
     * @param string         $postStatus    'draft' or 'publish'.
     * @param MediaMap       $mediaMap      Map of imported media assets.
     * @param string         $staticAssetUrl Base URL for preserved static assets.
     * @return array{
     *     created_pages: array<string, int>,
     *     url_map: array<string, string>,
     *     warnings: array<string>,
     *     errors: array<string>
     * }
     */
    public function importPages(
        string $extractedDir,
        ImportManifest $manifest,
        int $importId,
        string $conversionMode,
        string $postStatus,
        MediaMap $mediaMap,
        string $staticAssetUrl = ''
    ): array {
        $extractedDir = trailingslashit(str_replace('\\', '/', $extractedDir));
        $pages = $manifest->getPages();

        $createdPages = [];
        $urlMap = [];
        $warnings = [];
        $errors = [];
        $parentMap = []; // directory path -> post ID

        $assetRewriter = new AssetRewriter($mediaMap, $staticAssetUrl);
        $preserveConverter = new PreserveDesignConverter();
        $gutenbergConverter = new GutenbergConverter($mediaMap);

        // Sort pages by directory depth so parents are imported before children
        uasort($pages, static function (array $a, array $b): int {
            $depthA = substr_count(str_replace('\\', '/', $a['path']), '/');
            $depthB = substr_count(str_replace('\\', '/', $b['path']), '/');
            if ($depthA === $depthB) {
                return strcmp($a['path'], $b['path']);
            }
            return $depthA <=> $depthB;
        });

        // 1. Create WordPress Pages
        foreach ($pages as $relPath => $pageConfig) {
            if (empty($pageConfig['included'])) {
                continue;
            }

            $sourceFile = $extractedDir . $relPath;
            if (!file_exists($sourceFile)) {
                $errors[] = sprintf('Source file missing: %s', $relPath);
                continue;
            }

            $rawHtml = (string) file_get_contents($sourceFile);
            $sourceHash = hash('sha256', $rawHtml);

            // Determine parent ID from folder path
            $dir = dirname($relPath);
            $parentId = 0;
            if ($dir !== '.' && $dir !== '') {
                $parentId = $parentMap[$dir] ?? 0;
            }

            $title = !empty($pageConfig['title']) ? sanitize_text_field($pageConfig['title']) : 'Imported Page';
            $slug = !empty($pageConfig['slug']) ? sanitize_title($pageConfig['slug']) : sanitize_title(pathinfo($relPath, PATHINFO_FILENAME));

            $customCss = '';
            $stylesheets = [];
            $scripts = [];
            $postContent = '';

            // Extract styles, stylesheets, and scripts from the HTML source
            $preserveConverter = new PreserveDesignConverter();
            $preserved = $preserveConverter->convert($rawHtml, $relPath);
            $customCss = $preserved['custom_css'];
            $stylesheets = $preserved['stylesheets'];
            $scripts = $preserved['scripts'];

            // Read any local stylesheets from disk and inline them directly into custom CSS
            $rewrittenStylesheets = [];
            foreach ($stylesheets as $sheet) {
                $trimmedSheet = trim($sheet);
                if ($trimmedSheet === '') {
                    continue;
                }

                // If remote external URL (e.g., Google Fonts), keep as external link
                if (preg_match('/^(https?:)?\/\//i', $trimmedSheet) || str_starts_with($trimmedSheet, '//')) {
                    $rewrittenStylesheets[] = $trimmedSheet;
                    continue;
                }

                $resolvedRelSheet = SiteScanner::resolveRelativeAsset($trimmedSheet, $relPath);
                $localSheetFile = $extractedDir . $resolvedRelSheet;

                if (file_exists($localSheetFile) && is_file($localSheetFile)) {
                    $sheetContent = (string) file_get_contents($localSheetFile);
                    if (trim($sheetContent) !== '') {
                        // Rewrite relative asset URLs in CSS (fonts, background images)
                        $rewrittenSheetCss = $assetRewriter->rewriteCssUrls($sheetContent, $resolvedRelSheet);
                        $customCss = trim($customCss . "\n/* Preserved from " . esc_attr($resolvedRelSheet) . " */\n" . $rewrittenSheetCss);
                    }
                } else {
                    $rewrittenStylesheets[] = $assetRewriter->resolveAssetUrl($trimmedSheet, $relPath);
                }
            }

            if ($conversionMode === 'gutenberg') {
                $converted = $gutenbergConverter->convert($rawHtml, $relPath);
                $postContent = $converted['content'];
                if (!empty($converted['warnings'])) {
                    $warnings = array_merge($warnings, $converted['warnings']);
                }
            } else {
                // Preserve Design Mode
                $postContent = $preserved['content'];
                if (!empty($preserved['warnings'])) {
                    $warnings = array_merge($warnings, $preserved['warnings']);
                }
            }

            // Rewrite assets (images, media urls) within post content
            $postContent = $assetRewriter->rewriteHtmlAssets($postContent, $relPath);

            // Rewrite relative CSS urls in extracted custom CSS
            if ($customCss !== '') {
                $customCss = $assetRewriter->rewriteCssUrls($customCss, $relPath);
            }

            // Check if this page was already created in a previous batch of this import
            $existingItem = null;
            $items = $this->repository->getItems($importId, 'page');
            foreach ($items as $item) {
                if ($item['source_path'] === $relPath && !empty($item['destination_id'])) {
                    $existingItem = $item;
                    break;
                }
            }

            $postData = [
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => $postContent,
                'post_status'  => in_array($postStatus, ['draft', 'publish'], true) ? $postStatus : 'draft',
                'post_type'    => 'page',
                'post_parent'  => $parentId,
            ];

            if ($existingItem && get_post((int) $existingItem['destination_id'])) {
                $postId = (int) $existingItem['destination_id'];
                $postData['ID'] = $postId;
                $updated = wp_update_post($postData);
                if (is_wp_error($updated) || $updated === 0) {
                    $errors[] = sprintf('Failed to update page for %s', $relPath);
                    continue;
                }
            } else {
                $postId = wp_insert_post($postData);
                if (is_wp_error($postId) || $postId === 0) {
                    $errors[] = sprintf('Failed to insert page for %s', $relPath);
                    continue;
                }
            }

            // Store metadata (CSS and stylesheets saved for both modes so custom styling is never lost)
            update_post_meta($postId, '_wpcalibrate_html_bridge_import_id', $importId);
            update_post_meta($postId, '_wpcalibrate_html_bridge_source_path', $relPath);
            update_post_meta($postId, '_wpcalibrate_html_bridge_conversion_mode', $conversionMode);
            update_post_meta($postId, '_wpcalibrate_html_bridge_original_html', $rawHtml);
            update_post_meta($postId, '_wpcalibrate_html_bridge_custom_css', $customCss);
            update_post_meta($postId, '_wpcalibrate_html_bridge_stylesheets', $rewrittenStylesheets);
            update_post_meta($postId, '_wpcalibrate_html_bridge_scripts', $scripts);

            if ($conversionMode === 'preserve_design') {
                update_post_meta($postId, '_wpcalibrate_html_bridge_canvas_template', 1);
            }

            $permalink = (string) get_permalink($postId);

            $createdPages[$relPath] = $postId;
            $urlMap[$relPath] = $permalink;

            // Track directory parent
            $dirKey = trim(dirname($relPath), '.');
            if ($dirKey !== '') {
                $parentMap[$dirKey] = $postId;
            }

            // Record into custom items table
            $this->repository->recordItem(
                $importId,
                $relPath,
                $sourceHash,
                'page',
                'post',
                $postId,
                $permalink,
                'completed',
                [
                    'conversion_mode' => $conversionMode,
                    'status'          => $postStatus,
                ]
            );
        }

        // 2. Perform second pass to rewrite internal links now that all page URLs exist!
        if (!empty($urlMap)) {
            $linkRewriter = new LinkRewriter($urlMap);

            foreach ($createdPages as $relPath => $postId) {
                $post = get_post($postId);
                if (!$post) {
                    continue;
                }

                $rewrittenContent = $linkRewriter->rewriteHtmlLinks($post->post_content, $relPath);

                if ($rewrittenContent !== $post->post_content) {
                    wp_update_post([
                        'ID'           => $postId,
                        'post_content' => $rewrittenContent,
                    ]);
                }
            }
        }

        return [
            'created_pages' => $createdPages,
            'url_map'       => $urlMap,
            'warnings'      => $warnings,
            'errors'        => $errors,
        ];
    }
}
