<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Scanner;

use WPCalibrate\HtmlBridge\Archive\FilePolicy;
use WPCalibrate\HtmlBridge\Import\ImportManifest;
use WPCalibrate\HtmlBridge\Security\Sanitizer;
use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SiteScanner
 *
 * Traverses extracted static site files to assemble a comprehensive, durable ImportManifest.
 */
final class SiteScanner
{
    /**
     * Scan an extracted website directory.
     *
     * @param string        $extractedDir     Absolute path to extracted folder.
     * @param string        $uuid             Import UUID.
     * @param string        $originalFilename Original ZIP filename.
     * @param int           $userId           Admin user ID.
     * @param array<string> $blockedFiles     Files blocked during extraction.
     * @param int           $compressedSize   Compressed size in bytes.
     * @return ImportManifest
     */
    public static function scan(
        string $extractedDir,
        string $uuid,
        string $originalFilename,
        int $userId,
        array $blockedFiles = [],
        int $compressedSize = 0
    ): ImportManifest {
        $extractedDir = trailingslashit(str_replace('\\', '/', $extractedDir));

        do_action('wpcalibrate_html_bridge_before_scan', $extractedDir, $uuid);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractedDir, \FilesystemIterator::SKIP_DOTS)
        );

        $allFiles = [];
        $htmlFiles = [];
        $cssFiles = [];
        $jsFiles = [];
        $imageFiles = [];
        $fontFiles = [];
        $otherAssets = [];
        $totalExtractedSize = 0;

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if ($fileInfo->isDir()) {
                continue;
            }

            $realPath = str_replace('\\', '/', $fileInfo->getRealPath());
            $relPath = ltrim(substr($realPath, strlen($extractedDir)), '/');

            // Skip internal or hidden system files
            if (str_starts_with($relPath, '.') || str_contains($relPath, '/.') || str_starts_with($relPath, '__MACOSX')) {
                continue;
            }

            $fileSize = $fileInfo->getSize();
            $totalExtractedSize += $fileSize;
            $allFiles[$relPath] = true;

            if (FilePolicy::isHtml($relPath)) {
                $htmlFiles[$relPath] = $realPath;
            } elseif (FilePolicy::isCss($relPath)) {
                $cssFiles[] = $relPath;
            } elseif (FilePolicy::isJs($relPath)) {
                $jsFiles[] = $relPath;
            } elseif (FilePolicy::isImage($relPath)) {
                $imageFiles[] = $relPath;
            } elseif (FilePolicy::isFont($relPath)) {
                $fontFiles[] = $relPath;
            } else {
                $otherAssets[] = $relPath;
            }
        }

        $pages = [];
        $internalLinks = [];
        $externalLinks = [];
        $brokenReferences = [];
        $allForms = [];
        $allIframes = [];
        $navCandidates = [];
        $externalScripts = [];
        $totalInlineScripts = 0;
        $faviconCandidate = '';
        $homepageCandidate = '';

        // Priority homepage search
        $homepagePriorities = ['index.html', 'index.htm', 'home.html', 'default.html'];
        foreach ($homepagePriorities as $candidate) {
            if (isset($htmlFiles[$candidate])) {
                $homepageCandidate = $candidate;
                break;
            }
        }

        // If no root index found, pick the first HTML file
        if ($homepageCandidate === '' && !empty($htmlFiles)) {
            $homepageCandidate = (string) array_key_first($htmlFiles);
        }

        // Parse each HTML document
        foreach ($htmlFiles as $relPath => $absolutePath) {
            $content = (string) file_get_contents($absolutePath);
            $parsed = HtmlParser::parse($content, $relPath);

            $isHomepage = ($relPath === $homepageCandidate);

            // Suggested title
            $title = $parsed['title'];
            if ($title === '') {
                $cleanName = pathinfo($relPath, PATHINFO_FILENAME);
                $title = ucwords(str_replace(['-', '_'], ' ', $cleanName));
            }

            // Suggested slug
            $suggestedSlug = Sanitizer::pathToSlug($relPath);
            if ($suggestedSlug === '') {
                $suggestedSlug = $isHomepage ? 'home' : 'page';
            }

            // Favicon candidate
            if (empty($faviconCandidate) && !empty($parsed['favicon'])) {
                $resolvedFav = self::resolveRelativeAsset($parsed['favicon'], $relPath);
                if (isset($allFiles[$resolvedFav])) {
                    $faviconCandidate = $resolvedFav;
                }
            }

            $totalInlineScripts += $parsed['scripts']['inline_count'];

            foreach ($parsed['scripts']['external'] as $extScript) {
                $externalScripts[$extScript] = true;
            }

            // Process links
            foreach ($parsed['links'] as $link) {
                $href = $link['href'];
                if ($link['is_external']) {
                    $externalLinks[] = $href;
                } else {
                    $internalLinks[] = $href;
                    // Check for broken local reference
                    $resolvedTarget = self::resolveRelativeAsset($href, $relPath);
                    $targetWithoutFragment = preg_replace('/[#\?].*$/', '', $resolvedTarget) ?? $resolvedTarget;
                    if ($targetWithoutFragment !== '' && !isset($allFiles[$targetWithoutFragment])) {
                        $brokenReferences[] = [
                            'source_page' => $relPath,
                            'target_url'  => $href,
                        ];
                    }
                }
            }

            // Forms
            foreach ($parsed['forms'] as $form) {
                $allForms[] = array_merge($form, ['page' => $relPath]);
            }

            // Iframes
            foreach ($parsed['iframes'] as $iframe) {
                $allIframes[] = [
                    'src'  => $iframe,
                    'page' => $relPath,
                ];
            }

            // Navigation candidates
            if (!empty($parsed['nav_candidates'])) {
                foreach ($parsed['nav_candidates'] as $nav) {
                    $navCandidates[] = array_merge($nav, ['page' => $relPath]);
                }
            }

            $pages[$relPath] = [
                'path'             => $relPath,
                'title'            => $title,
                'slug'             => $suggestedSlug,
                'meta_description' => $parsed['meta_description'],
                'canonical_url'    => $parsed['canonical_url'],
                'og_title'         => $parsed['og_title'],
                'og_description'   => $parsed['og_description'],
                'og_image'         => $parsed['og_image'],
                'is_homepage'      => $isHomepage,
                'included'         => true,
            ];
        }

        // Warnings list
        $warnings = [];

        if (!empty($blockedFiles)) {
            $warnings[] = sprintf(
                /* translators: %d: count */
                __('%d unsupported or potentially dangerous files were quarantined from the archive.', 'wpcalibrate-html-bridge'),
                count($blockedFiles)
            );
        }

        if (!empty($allForms)) {
            $warnings[] = sprintf(
                /* translators: %d: count */
                __('Form Integration Advisory: %d static HTML form(s) detected. Form layouts and input fields are fully preserved; connect to your preferred WordPress form plugin (e.g. Contact Form 7, WPForms) or webhook endpoint to collect submissions.', 'wpcalibrate-html-bridge'),
                count($allForms)
            );
        }

        if (!empty($externalScripts)) {
            $warnings[] = sprintf(
                /* translators: %d: count */
                __('%d external JavaScript dependencies detected. For security, external scripts will not load automatically without configuration.', 'wpcalibrate-html-bridge'),
                count($externalScripts)
            );
        }

        if ($totalInlineScripts > 0) {
            $warnings[] = sprintf(
                /* translators: %d: count */
                __('%d inline JavaScript snippets detected. Complex client-side scripts may require adjustments after import.', 'wpcalibrate-html-bridge'),
                $totalInlineScripts
            );
        }

        if (!empty($brokenReferences)) {
            $warnings[] = sprintf(
                /* translators: %d: count */
                __('%d internal references to missing local files detected.', 'wpcalibrate-html-bridge'),
                count($brokenReferences)
            );
        }

        $manifestData = [
            'uuid'                  => $uuid,
            'original_filename'     => $originalFilename,
            'import_date'           => gmdate('Y-m-d H:i:s'),
            'user_id'               => $userId,
            'source_root'           => '',
            'homepage'              => $homepageCandidate,
            'pages'                 => $pages,
            'css_files'             => $cssFiles,
            'js_files'              => $jsFiles,
            'images'                => $imageFiles,
            'fonts'                 => $fontFiles,
            'other_assets'          => $otherAssets,
            'favicon_candidate'     => $faviconCandidate,
            'internal_links_count'  => count($internalLinks),
            'external_links_count'  => count(array_unique($externalLinks)),
            'navigation_candidates' => $navCandidates,
            'forms'                 => $allForms,
            'iframes'               => $allIframes,
            'scripts_summary'       => [
                'inline_count'     => $totalInlineScripts,
                'local_count'      => count($jsFiles),
                'external_domains' => array_keys($externalScripts),
            ],
            'broken_references'     => array_slice($brokenReferences, 0, 50),
            'blocked_files'         => $blockedFiles,
            'warnings'              => $warnings,
            'total_files'           => count($allFiles),
            'compressed_size'       => $compressedSize,
            'extracted_size'        => $totalExtractedSize,
        ];

        $manifest = new ImportManifest($manifestData);

        do_action('wpcalibrate_html_bridge_after_scan', $manifest, $uuid);

        return $manifest;
    }

    /**
     * Resolve a relative asset link with respect to its referring HTML page path.
     *
     * @param string $href
     * @param string $sourcePage
     * @return string Normalized relative path.
     */
    public static function resolveRelativeAsset(string $href, string $sourcePage): string
    {
        // Strip query strings and hash anchors
        $clean = preg_replace('/[#\?].*$/', '', $href) ?? $href;
        $clean = str_replace('\\', '/', $clean);

        if (str_starts_with($clean, '/')) {
            return ltrim($clean, '/');
        }

        $sourceDir = dirname($sourcePage);
        if ($sourceDir === '.' || $sourceDir === '') {
            $combined = $clean;
        } else {
            $combined = $sourceDir . '/' . $clean;
        }

        // Normalize . and ..
        $parts = [];
        foreach (explode('/', $combined) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }
}
