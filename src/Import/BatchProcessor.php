<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Import;

use WPCalibrate\HtmlBridge\Conversion\PageImporter;
use WPCalibrate\HtmlBridge\Media\MediaImporter;
use WPCalibrate\HtmlBridge\Media\MediaMap;
use WPCalibrate\HtmlBridge\Navigation\NavigationImporter;
use WPCalibrate\HtmlBridge\Settings\Settings;
use WPCalibrate\HtmlBridge\Support\Filesystem;
use WPCalibrate\HtmlBridge\Support\Logger;
use WPCalibrate\HtmlBridge\Theme\ThemeGenerator;
use WPCalibrate\HtmlBridge\Theme\ThemeInstaller;
use WPCalibrate\HtmlBridge\Theme\ThemeValidator;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BatchProcessor
 *
 * Executes resumable, chunked import batches with deterministic checkpoints and lock safety.
 */
final class BatchProcessor
{
    private const CHUNK_SIZE = 10;

    private ImportRepository $repository;

    public function __construct(ImportRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Process the next resumable step/chunk for an import.
     *
     * @param int $importId
     * @return array{
     *     success: bool,
     *     is_complete: bool,
     *     stage: string,
     *     progress: int,
     *     message: string,
     *     warnings: array<string>,
     *     errors: array<string>
     * }
     */
    public function processBatch(int $importId): array
    {
        $import = $this->repository->getImportById($importId);
        if (!$import) {
            return [
                'success'     => false,
                'is_complete' => false,
                'stage'       => 'error',
                'progress'    => 0,
                'message'     => __('Import not found.', 'wpcalibrate-html-bridge'),
                'warnings'    => [],
                'errors'      => ['Import not found'],
            ];
        }

        $uuid = (string) $import['uuid'];

        if (!ImportLock::acquire($uuid)) {
            return [
                'success'     => false,
                'is_complete' => false,
                'stage'       => 'locked',
                'progress'    => 0,
                'message'     => __('Import is currently being processed by another worker.', 'wpcalibrate-html-bridge'),
                'warnings'    => [],
                'errors'      => ['Import lock active'],
            ];
        }

        try {
            $manifest = ImportManifest::fromJson((string) $import['manifest']);
            $settings = json_decode((string) $import['settings'], true) ?: [];
            $mode = (string) ($import['import_mode'] ?? 'pages');
            $conversionMode = (string) ($import['conversion_mode'] ?? 'preserve_design');
            $postStatus = (string) ($settings['post_status'] ?? 'draft');

            $extractedDir = Filesystem::getImportTempDir($uuid) . 'extracted/';

            // Checkpoint tracking
            $checkpoint = $settings['checkpoint'] ?? [
                'stage'             => 'assets',
                'assets_offset'     => 0,
                'pages_offset'      => 0,
                'processed_assets'  => 0,
                'processed_pages'   => 0,
                'total_assets'      => count($manifest->getImages()),
                'total_pages'       => count($manifest->getPages()),
            ];

            // Rehydrate existing MediaMap
            $mediaMap = new MediaMap();
            $existingMediaItems = $this->repository->getItems($importId, 'attachment');
            foreach ($existingMediaItems as $item) {
                $mediaMap->add(
                    $item['source_path'],
                    (int) $item['destination_id'],
                    (string) $item['destination_url'],
                    $item['source_hash']
                );
            }

            // Route based on mode
            if ($mode === 'theme') {
                return $this->processThemeMode($importId, $uuid, $extractedDir, $manifest, $settings);
            }

            return $this->processPagesMode(
                $importId,
                $uuid,
                $extractedDir,
                $manifest,
                $settings,
                $checkpoint,
                $conversionMode,
                $postStatus,
                $mediaMap
            );
        } finally {
            ImportLock::release($uuid);
        }
    }

    /**
     * Process pages import mode steps.
     *
     * @param int                  $importId
     * @param string               $uuid
     * @param string               $extractedDir
     * @param ImportManifest       $manifest
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $checkpoint
     * @param string               $conversionMode
     * @param string               $postStatus
     * @param MediaMap             $mediaMap
     * @return array<string, mixed>
     */
    private function processPagesMode(
        int $importId,
        string $uuid,
        string $extractedDir,
        ImportManifest $manifest,
        array $settings,
        array $checkpoint,
        string $conversionMode,
        string $postStatus,
        MediaMap $mediaMap
    ): array {
        $stage = $checkpoint['stage'] ?? 'assets';
        $shouldImportMedia = !empty($settings['import_media_library'] ?? true);

        // 1. Stage: Assets
        if ($stage === 'assets') {
            $images = $manifest->getImages();
            $totalImages = count($images);

            if (!$shouldImportMedia || empty($images) || $checkpoint['assets_offset'] >= $totalImages) {
                // Move to pages stage
                $checkpoint['stage'] = 'pages';
                $checkpoint['assets_offset'] = $totalImages;
                $settings['checkpoint'] = $checkpoint;
                $this->repository->updateStatus($importId, ImportState::IMPORTING);
                $this->repository->updateManifest($importId, $manifest, $settings);

                return [
                    'success'     => true,
                    'is_complete' => false,
                    'stage'       => 'assets_complete',
                    'progress'    => 40,
                    'message'     => __('Assets processed. Starting page generation...', 'wpcalibrate-html-bridge'),
                    'warnings'    => [],
                    'errors'      => [],
                ];
            }

            $mediaImporter = new MediaImporter($this->repository);
            $slice = array_slice($images, $checkpoint['assets_offset'], self::CHUNK_SIZE);

            foreach ($slice as $imgRel) {
                $absPath = $extractedDir . $imgRel;
                $mediaImporter->importAsset($absPath, $imgRel, $importId, '', $mediaMap);
                $checkpoint['processed_assets']++;
            }

            $checkpoint['assets_offset'] += count($slice);
            $settings['checkpoint'] = $checkpoint;
            $this->repository->updateManifest($importId, $manifest, $settings);

            $progress = (int) round(($checkpoint['assets_offset'] / max(1, $totalImages)) * 35);

            return [
                'success'     => true,
                'is_complete' => false,
                'stage'       => 'assets',
                'progress'    => max(5, $progress),
                'message'     => sprintf(
                    /* translators: 1: processed count, 2: total count */
                    __('Importing assets (%1$d of %2$d)...', 'wpcalibrate-html-bridge'),
                    $checkpoint['assets_offset'],
                    $totalImages
                ),
                'warnings'    => [],
                'errors'      => [],
            ];
        }

        // 2. Stage: Pages
        if ($stage === 'pages') {
            $pageImporter = new PageImporter($this->repository);
            $staticAssetUrl = Filesystem::getImportTempUrl($uuid) . 'extracted';

            $result = $pageImporter->importPages(
                $extractedDir,
                $manifest,
                $importId,
                $conversionMode,
                $postStatus,
                $mediaMap,
                $staticAssetUrl
            );

            // 3. Stage: Navigation (if enabled)
            if (!empty($settings['create_navigation'])) {
                $navCandidates = $manifest->getNavigationCandidates();
                if (!empty($navCandidates)) {
                    $navImporter = new NavigationImporter($this->repository);
                    $navImporter->importMenu(
                        $navCandidates[0],
                        $result['created_pages'],
                        $result['url_map'],
                        $importId,
                        'Imported Site Navigation'
                    );
                }
            }

            // 4. Stage: Homepage assignment (if explicitly selected)
            if (!empty($settings['set_front_page']) && !empty($manifest->getHomepage())) {
                $homePath = $manifest->getHomepage();
                $homePostId = $result['created_pages'][$homePath] ?? 0;
                if ($homePostId > 0) {
                    $settings['previous_show_on_front'] = get_option('show_on_front');
                    $settings['previous_page_on_front'] = get_option('page_on_front');
                    update_option('show_on_front', 'page');
                    update_option('page_on_front', $homePostId);
                }
            }

            // 5. Cleanup and completion
            $checkpoint['stage'] = 'completed';
            $settings['checkpoint'] = $checkpoint;
            $this->repository->updateManifest($importId, $manifest, $settings);

            $finalState = !empty($result['warnings']) ? ImportState::COMPLETED_WITH_WARNINGS : ImportState::COMPLETED;
            $this->repository->updateStatus($importId, $finalState);

            return [
                'success'     => true,
                'is_complete' => true,
                'stage'       => 'completed',
                'progress'    => 100,
                'message'     => __('Import completed successfully!', 'wpcalibrate-html-bridge'),
                'warnings'    => $result['warnings'],
                'errors'      => $result['errors'],
            ];
        }

        return [
            'success'     => true,
            'is_complete' => true,
            'stage'       => 'completed',
            'progress'    => 100,
            'message'     => __('Import already completed.', 'wpcalibrate-html-bridge'),
            'warnings'    => [],
            'errors'      => [],
        ];
    }

    /**
     * Process standalone theme generation and installation.
     *
     * @param int                  $importId
     * @param string               $uuid
     * @param string               $extractedDir
     * @param ImportManifest       $manifest
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function processThemeMode(
        int $importId,
        string $uuid,
        string $extractedDir,
        ImportManifest $manifest,
        array $settings
    ): array {
        $themeGenerator = new ThemeGenerator();
        $stagingDir = Filesystem::getImportTempDir($uuid) . 'theme_staging/';

        $themeSlug = sanitize_key($settings['theme_slug'] ?? '');
        $themeName = sanitize_text_field($settings['theme_name'] ?? '');

        $genResult = $themeGenerator->generate($extractedDir, $manifest, $stagingDir, $themeSlug, $themeName);
        if (!$genResult['success']) {
            $this->repository->updateStatus($importId, ImportState::FAILED, 'Theme generation failed');
            return [
                'success'     => false,
                'is_complete' => false,
                'stage'       => 'failed',
                'progress'    => 0,
                'message'     => __('Theme generation failed.', 'wpcalibrate-html-bridge'),
                'warnings'    => $genResult['warnings'],
                'errors'      => $genResult['errors'],
            ];
        }

        // Install generated theme to themes directory (without auto-activation!)
        $installResult = ThemeInstaller::install($genResult['theme_dir'], $genResult['theme_slug']);
        if (!$installResult['success']) {
            $this->repository->updateStatus($importId, ImportState::FAILED, $installResult['error'] ?? 'Installation failed');
            return [
                'success'     => false,
                'is_complete' => false,
                'stage'       => 'failed',
                'progress'    => 0,
                'message'     => $installResult['error'] ?? __('Theme installation failed.', 'wpcalibrate-html-bridge'),
                'warnings'    => [],
                'errors'      => [$installResult['error'] ?? 'Install error'],
            ];
        }

        // Record in custom items table
        $this->repository->recordItem(
            $importId,
            'theme',
            hash('sha256', $genResult['theme_slug']),
            'theme',
            'theme',
            null,
            null,
            'completed',
            [
                'theme_slug' => $genResult['theme_slug'],
                'path'       => $installResult['installed_path'],
            ]
        );

        $this->repository->updateStatus($importId, ImportState::COMPLETED);

        return [
            'success'     => true,
            'is_complete' => true,
            'stage'       => 'completed',
            'progress'    => 100,
            'message'     => sprintf(
                /* translators: %s: theme slug */
                __('Standalone WordPress theme "%s" successfully generated and installed. Please visit Appearance > Themes to inspect and activate.', 'wpcalibrate-html-bridge'),
                $genResult['theme_slug']
            ),
            'warnings'    => $genResult['warnings'],
            'errors'      => [],
        ];
    }
}
