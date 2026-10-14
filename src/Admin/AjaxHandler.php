<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Admin;

use WPCalibrate\HtmlBridge\Import\ImportManager;
use WPCalibrate\HtmlBridge\Import\ImportManifest;
use WPCalibrate\HtmlBridge\Security\Capabilities;
use WPCalibrate\HtmlBridge\Security\Nonces;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AjaxHandler
 *
 * Secure AJAX endpoints for upload processing, chunked batches, rollback, and re-imports.
 */
final class AjaxHandler
{
    private ImportManager $importManager;

    public function __construct(ImportManager $importManager)
    {
        $this->importManager = $importManager;
    }

    /**
     * Register AJAX action hooks.
     */
    public function register(): void
    {
        add_action('wp_ajax_wpcalibrate_html_bridge_upload', [$this, 'handleUpload']);
        add_action('wp_ajax_wpcalibrate_html_bridge_start_import', [$this, 'handleStartImport']);
        add_action('wp_ajax_wpcalibrate_html_bridge_batch', [$this, 'handleBatch']);
        add_action('wp_ajax_wpcalibrate_html_bridge_rollback', [$this, 'handleRollback']);
        add_action('wp_ajax_wpcalibrate_html_bridge_delete_import', [$this, 'handleDeleteImport']);
        add_action('wp_ajax_wpcalibrate_html_bridge_reimport', [$this, 'handleReimport']);
        add_action('wp_ajax_wpcalibrate_html_bridge_demo_import', [$this, 'handleDemoImport']);
    }

    /**
     * Handle ZIP upload and initial scan.
     */
    public function handleUpload(): void
    {
        check_ajax_referer(Nonces::ADMIN_ACTION, 'nonce');

        if (!Capabilities::canManage()) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'wpcalibrate-html-bridge')], 403);
        }

        if (empty($_FILES['package'])) {
            wp_send_json_error(['message' => __('No archive package received.', 'wpcalibrate-html-bridge')], 400);
        }

        $result = $this->importManager->handleUpload($_FILES['package']);

        if (!$result['success']) {
            wp_send_json_error([
                'message'  => !empty($result['errors']) ? implode(' ', $result['errors']) : __('Upload rejected.', 'wpcalibrate-html-bridge'),
                'errors'   => $result['errors'] ?? [],
                'warnings' => $result['warnings'] ?? [],
            ], 400);
        }

        wp_send_json_success($result);
    }

    /**
     * Start/configure an import before batch processing.
     */
    public function handleStartImport(): void
    {
        check_ajax_referer(Nonces::ADMIN_ACTION, 'nonce');

        if (!Capabilities::canManage()) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'wpcalibrate-html-bridge')], 403);
        }

        $importId = (int) ($_POST['import_id'] ?? 0);
        $import = $this->importManager->getRepository()->getImportById($importId);

        if (!$import) {
            wp_send_json_error(['message' => __('Import record not found.', 'wpcalibrate-html-bridge')], 404);
        }

        $importMode = in_array($_POST['import_mode'] ?? '', ['pages', 'theme'], true) ? (string) $_POST['import_mode'] : 'pages';
        $conversionMode = in_array($_POST['conversion_mode'] ?? '', ['preserve_design', 'gutenberg'], true) ? (string) $_POST['conversion_mode'] : 'preserve_design';
        $postStatus = in_array($_POST['post_status'] ?? '', ['draft', 'publish'], true) ? (string) $_POST['post_status'] : 'draft';

        $settings = [
            'import_mode'           => $importMode,
            'conversion_mode'       => $conversionMode,
            'post_status'           => $postStatus,
            'import_media_library'  => !empty($_POST['import_media_library']),
            'create_navigation'     => !empty($_POST['create_navigation']),
            'set_front_page'        => !empty($_POST['set_front_page']),
            'theme_slug'            => sanitize_key((string) ($_POST['theme_slug'] ?? '')),
            'theme_name'            => sanitize_text_field((string) ($_POST['theme_name'] ?? '')),
        ];

        // Update pages inclusion if sent
        if (!empty($_POST['pages']) && is_array($_POST['pages'])) {
            $manifest = ImportManifest::fromJson((string) $import['manifest']);
            foreach ($_POST['pages'] as $path => $pageUpdates) {
                if (is_array($pageUpdates)) {
                    $manifest->updatePage(sanitize_text_field((string) $path), [
                        'title'    => sanitize_text_field((string) ($pageUpdates['title'] ?? '')),
                        'slug'     => sanitize_title((string) ($pageUpdates['slug'] ?? '')),
                        'included' => !empty($pageUpdates['included']),
                    ]);
                }
            }
            if (!empty($_POST['homepage'])) {
                $manifest->setHomepage(sanitize_text_field((string) $_POST['homepage']));
            }
            $this->importManager->getRepository()->updateManifest($importId, $manifest, $settings);
        } else {
            $manifest = ImportManifest::fromJson((string) $import['manifest']);
            $this->importManager->getRepository()->updateManifest($importId, $manifest, $settings);
        }

        wp_send_json_success(['import_id' => $importId, 'message' => __('Import initialized.', 'wpcalibrate-html-bridge')]);
    }

    /**
     * Process next batch chunk.
     */
    public function handleBatch(): void
    {
        check_ajax_referer(Nonces::ADMIN_ACTION, 'nonce');

        if (!Capabilities::canManage()) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'wpcalibrate-html-bridge')], 403);
        }

        $importId = (int) ($_POST['import_id'] ?? 0);
        $result = $this->importManager->getBatchProcessor()->processBatch($importId);

        if (!$result['success']) {
            wp_send_json_error($result, 500);
        }

        wp_send_json_success($result);
    }

    /**
     * Rollback an import.
     */
    public function handleRollback(): void
    {
        check_ajax_referer(Nonces::ADMIN_ACTION, 'nonce');

        if (!Capabilities::canManage()) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'wpcalibrate-html-bridge')], 403);
        }

        $importId = (int) ($_POST['import_id'] ?? 0);
        $deleteMedia = !empty($_POST['delete_media']);

        $result = $this->importManager->getRollbackManager()->rollback($importId, $deleteMedia);

        if (!$result['success']) {
            wp_send_json_error($result, 500);
        }

        wp_send_json_success($result);
    }

    /**
     * Delete an import history record.
     */
    public function handleDeleteImport(): void
    {
        check_ajax_referer(Nonces::ADMIN_ACTION, 'nonce');

        if (!Capabilities::canManage()) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'wpcalibrate-html-bridge')], 403);
        }

        $importId = (int) ($_POST['import_id'] ?? 0);
        $deleted = $this->importManager->getRepository()->deleteImport($importId);

        if (!$deleted) {
            wp_send_json_error(['message' => __('Failed to delete record.', 'wpcalibrate-html-bridge')], 500);
        }

        wp_send_json_success(['message' => __('Import history record deleted.', 'wpcalibrate-html-bridge')]);
    }

    /**
     * Prepare a previous import for re-execution.
     */
    public function handleReimport(): void
    {
        check_ajax_referer(Nonces::ADMIN_ACTION, 'nonce');

        if (!Capabilities::canManage()) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'wpcalibrate-html-bridge')], 403);
        }

        $importId = (int) ($_POST['import_id'] ?? 0);
        $result = $this->importManager->getReimportManager()->prepareForUpdate($importId);

        if (!$result['success']) {
            wp_send_json_error($result, 400);
        }

        wp_send_json_success($result);
    }

    /**
     * Handle one-click demo template import.
     */
    public function handleDemoImport(): void
    {
        check_ajax_referer(Nonces::ADMIN_ACTION, 'nonce');

        if (!Capabilities::canManage()) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'wpcalibrate-html-bridge')], 403);
        }

        $templateId = sanitize_key((string) ($_POST['template_id'] ?? ''));
        $allowedTemplates = [
            'business-landing' => 'business-landing.zip',
            'agency-portfolio' => 'agency-portfolio.zip',
            'saas-showcase'    => 'saas-showcase.zip',
            'minimal-blog'     => 'minimal-blog.zip',
        ];

        if (!isset($allowedTemplates[$templateId])) {
            wp_send_json_error(['message' => __('Invalid demo template requested.', 'wpcalibrate-html-bridge')], 400);
        }

        $zipPath = WPCALIBRATE_HTML_BRIDGE_PATH . 'assets/demo-templates/' . $allowedTemplates[$templateId];
        if (!file_exists($zipPath)) {
            wp_send_json_error(['message' => __('Demo template package not found on server.', 'wpcalibrate-html-bridge')], 404);
        }

        $result = $this->importManager->handleLocalZip($zipPath, $allowedTemplates[$templateId]);

        if (!$result['success']) {
            wp_send_json_error([
                'message'  => !empty($result['errors']) ? implode(' ', $result['errors']) : __('Demo import failed.', 'wpcalibrate-html-bridge'),
                'errors'   => $result['errors'] ?? [],
                'warnings' => $result['warnings'] ?? [],
            ], 400);
        }

        wp_send_json_success($result);
    }
}
