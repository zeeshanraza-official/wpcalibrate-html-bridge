<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Import;

use WPCalibrate\HtmlBridge\Archive\SecureExtractor;
use WPCalibrate\HtmlBridge\Archive\ZipValidator;
use WPCalibrate\HtmlBridge\Scanner\SiteScanner;
use WPCalibrate\HtmlBridge\Security\Capabilities;
use WPCalibrate\HtmlBridge\Security\Nonces;
use WPCalibrate\HtmlBridge\Settings\Settings;
use WPCalibrate\HtmlBridge\Support\Filesystem;
use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ImportManager
 *
 * Primary coordinator for package uploads, validation, pre-import scanning, batch execution, and rollback.
 */
final class ImportManager
{
    private ImportRepository $repository;
    private BatchProcessor $batchProcessor;
    private RollbackManager $rollbackManager;
    private ReimportManager $reimportManager;

    public function __construct(?ImportRepository $repository = null)
    {
        $this->repository = $repository ?? new ImportRepository();
        $this->batchProcessor = new BatchProcessor($this->repository);
        $this->rollbackManager = new RollbackManager($this->repository);
        $this->reimportManager = new ReimportManager($this->repository);
    }

    public function getRepository(): ImportRepository
    {
        return $this->repository;
    }

    public function getBatchProcessor(): BatchProcessor
    {
        return $this->batchProcessor;
    }

    public function getRollbackManager(): RollbackManager
    {
        return $this->rollbackManager;
    }

    public function getReimportManager(): ReimportManager
    {
        return $this->reimportManager;
    }

    /**
     * Handle untrusted static site ZIP upload and initial scan.
     *
     * @param array<string, mixed> $uploadedFile Standard $_FILES['package'] entry.
     * @return array{
     *     success: bool,
     *     import_id?: int,
     *     uuid?: string,
     *     manifest?: array<string, mixed>,
     *     warnings?: array<string>,
     *     errors?: array<string>
     * }
     */
    public function handleUpload(array $uploadedFile): array
    {
        if (!Capabilities::canManage()) {
            return [
                'success' => false,
                'errors'  => [__('Unauthorized user action.', 'wpcalibrate-html-bridge')],
            ];
        }

        if (empty($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
            return [
                'success' => false,
                'errors'  => [__('No valid uploaded archive received.', 'wpcalibrate-html-bridge')],
            ];
        }

        $tmpFile = (string) $uploadedFile['tmp_name'];
        $originalFilename = sanitize_file_name((string) ($uploadedFile['name'] ?? 'website.zip'));

        return $this->processZipPackage($tmpFile, $originalFilename);
    }

    /**
     * Handle local static site ZIP (e.g. bundled demo template) and initial scan.
     *
     * @param string $filePath Absolute path to the local ZIP file.
     * @param string $originalFilename Display filename for the archive.
     * @return array{
     *     success: bool,
     *     import_id?: int,
     *     uuid?: string,
     *     manifest?: array<string, mixed>,
     *     warnings?: array<string>,
     *     errors?: array<string>
     * }
     */
    public function handleLocalZip(string $filePath, string $originalFilename = 'demo-template.zip'): array
    {
        if (!Capabilities::canManage()) {
            return [
                'success' => false,
                'errors'  => [__('Unauthorized user action.', 'wpcalibrate-html-bridge')],
            ];
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [
                'success' => false,
                'errors'  => [__('Local template package not found or unreadable.', 'wpcalibrate-html-bridge')],
            ];
        }

        return $this->processZipPackage($filePath, $originalFilename);
    }

    /**
     * Internal handler to validate, extract, scan, and register a ZIP package.
     *
     * @param string $packagePath Absolute path to the ZIP archive.
     * @param string $originalFilename Original or display filename.
     * @return array{
     *     success: bool,
     *     import_id?: int,
     *     uuid?: string,
     *     manifest?: array<string, mixed>,
     *     warnings?: array<string>,
     *     errors?: array<string>
     * }
     */
    private function processZipPackage(string $packagePath, string $originalFilename): array
    {
        Logger::info('Processing package archive', ['filename' => $originalFilename]);

        // 1. Inspect and validate untrusted ZIP before extraction
        $validation = ZipValidator::validate($packagePath);
        if (!$validation['valid']) {
            return [
                'success'  => false,
                'errors'   => $validation['errors'],
                'warnings' => $validation['warnings'],
            ];
        }

        // 2. Generate UUID and isolate temporary working directory
        $uuid = wp_generate_uuid4();
        $importTempDir = Filesystem::getImportTempDir($uuid);
        $extractedDir = $importTempDir . 'extracted/';

        // 3. Securely extract allowed contents only
        $rootPrefix = $validation['suggested_root'] ?? '';
        $extractResult = SecureExtractor::extract($packagePath, $extractedDir, $rootPrefix);
        if (!$extractResult['success']) {
            Filesystem::deleteDirectory($importTempDir);
            return [
                'success' => false,
                'errors'  => $extractResult['errors'],
            ];
        }

        // 4. Run structured site analysis and assemble manifest
        $userId = get_current_user_id();
        $manifest = SiteScanner::scan(
            $extractedDir,
            $uuid,
            $originalFilename,
            $userId,
            $extractResult['blocked_files'],
            $validation['compressed_size']
        );

        // 5. Store import in custom database table
        $defaultMode = (string) Settings::get('default_import_mode', 'pages');
        $defaultConversion = (string) Settings::get('default_conversion_mode', 'preserve_design');

        $importId = $this->repository->createImport(
            $uuid,
            $userId,
            $originalFilename,
            $defaultMode,
            $defaultConversion,
            $manifest
        );

        if ($importId === 0) {
            Filesystem::deleteDirectory($importTempDir);
            return [
                'success' => false,
                'errors'  => [__('Failed to save import record to database.', 'wpcalibrate-html-bridge')],
            ];
        }

        $this->repository->updateStatus($importId, ImportState::PREVIEW_READY);

        return [
            'success'   => true,
            'import_id' => $importId,
            'uuid'      => $uuid,
            'manifest'  => $manifest->toArray(),
            'warnings'  => array_merge($validation['warnings'], $manifest->getWarnings()),
        ];
    }
}
