<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Admin\Pages;

use WPCalibrate\HtmlBridge\Import\ImportManifest;
use WPCalibrate\HtmlBridge\Import\ImportRepository;
use WPCalibrate\HtmlBridge\Security\Nonces;
use WPCalibrate\HtmlBridge\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NewImportPage
 *
 * Renders the multi-step import wizard: Upload, Scan, Preview & Configuration, Live Execution, and Completion Report.
 */
final class NewImportPage
{
    private ImportRepository $repository;

    public function __construct(?ImportRepository $repository = null)
    {
        $this->repository = $repository ?? new ImportRepository();
    }

    public function render(): void
    {
        $resumeId = isset($_GET['resume_import']) ? (int) $_GET['resume_import'] : (isset($_GET['import_id']) ? (int) $_GET['import_id'] : 0);
        $resumeImport = $resumeId > 0 ? $this->repository->getImportById($resumeId) : null;
        $resumeManifest = $resumeImport && !empty($resumeImport['manifest'])
            ? ImportManifest::fromJson((string) $resumeImport['manifest'])
            : null;

        $defaultPostStatus = (string) Settings::get('default_post_status', 'draft');
        $defaultImportMode = (string) Settings::get('default_import_mode', 'pages');
        $defaultConversionMode = (string) Settings::get('default_conversion_mode', 'preserve_design');
        $maxZipMb = (int) Settings::get('max_zip_size_mb', 64);
        ?>
        <div id="wpcalibrate-wizard" class="wpcalibrate-wizard" data-resume-id="<?php echo esc_attr((string) $resumeId); ?>">
            <!-- Wizard Step Navigation -->
            <div class="wpcalibrate-steps-indicator" role="navigation" aria-label="<?php esc_attr_e('Import Progress Steps', 'wpcalibrate-html-bridge'); ?>">
                <div class="wpcalibrate-step active" data-step="1">
                    <span class="step-num">1</span>
                    <span class="step-label"><?php esc_html_e('Upload ZIP', 'wpcalibrate-html-bridge'); ?></span>
                </div>
                <div class="wpcalibrate-step <?php echo $resumeManifest ? 'active' : ''; ?>" data-step="2">
                    <span class="step-num">2</span>
                    <span class="step-label"><?php esc_html_e('Analysis & Preview', 'wpcalibrate-html-bridge'); ?></span>
                </div>
                <div class="wpcalibrate-step" data-step="3">
                    <span class="step-num">3</span>
                    <span class="step-label"><?php esc_html_e('Import Execution', 'wpcalibrate-html-bridge'); ?></span>
                </div>
                <div class="wpcalibrate-step" data-step="4">
                    <span class="step-num">4</span>
                    <span class="step-label"><?php esc_html_e('Report & Verify', 'wpcalibrate-html-bridge'); ?></span>
                </div>
            </div>

            <!-- STEP 1: Upload Archive -->
            <div id="wpcalibrate-step-1" class="wpcalibrate-step-panel <?php echo $resumeManifest ? 'hidden' : ''; ?>">
                <div class="wpcalibrate-card">
                    <h2><?php esc_html_e('Upload Static Website Archive', 'wpcalibrate-html-bridge'); ?></h2>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %d: maximum file size */
                            esc_html__('Select a ZIP archive containing your static website (HTML, CSS, JS, images, fonts). Maximum file size: %d MB.', 'wpcalibrate-html-bridge'),
                            $maxZipMb
                        );
                        ?>
                    </p>

                    <form id="wpcalibrate-upload-form" class="wpcalibrate-upload-zone" enctype="multipart/form-data">
                        <?php wp_nonce_field(Nonces::ADMIN_ACTION, 'nonce'); ?>
                        <div class="wpcalibrate-dropzone-inner">
                            <span class="dashicons dashicons-upload wpcalibrate-upload-icon"></span>
                            <h3><?php esc_html_e('Drag and drop your website .zip file here', 'wpcalibrate-html-bridge'); ?></h3>
                            <p><?php esc_html_e('or', 'wpcalibrate-html-bridge'); ?></p>
                            <label for="wpcalibrate-file-input" class="button button-primary button-hero">
                                <?php esc_html_e('Choose ZIP File', 'wpcalibrate-html-bridge'); ?>
                            </label>
                            <input type="file" id="wpcalibrate-file-input" name="package" accept=".zip" class="hidden-input" />
                            <p id="wpcalibrate-selected-filename" class="wpcalibrate-selected-filename"></p>
                        </div>
                    </form>

                    <div id="wpcalibrate-upload-progress" class="wpcalibrate-progress-bar-container hidden">
                        <div class="wpcalibrate-progress-bar"><div class="wpcalibrate-progress-fill" style="width: 0%;"></div></div>
                        <p class="wpcalibrate-progress-status"><?php esc_html_e('Uploading and running security scan...', 'wpcalibrate-html-bridge'); ?></p>
                    </div>

                    <div id="wpcalibrate-upload-errors" class="notice notice-error hidden"><p></p></div>
                </div>
            </div>

            <!-- STEP 2: Preview & Configure -->
            <div id="wpcalibrate-step-2" class="wpcalibrate-step-panel <?php echo $resumeManifest ? '' : 'hidden'; ?>">
                <form id="wpcalibrate-configure-form">
                    <input type="hidden" name="import_id" id="wpcalibrate-import-id" value="<?php echo esc_attr((string) $resumeId); ?>" />
                    <?php wp_nonce_field(Nonces::ADMIN_ACTION, 'nonce'); ?>

                    <!-- Scan Summary Metrics -->
                    <div class="wpcalibrate-card">
                        <h2><?php esc_html_e('Package Scan & Analysis Summary', 'wpcalibrate-html-bridge'); ?></h2>
                        <div class="wpcalibrate-metrics-grid">
                            <div class="wpcalibrate-metric-box">
                                <span class="metric-val" id="metric-pages">0</span>
                                <span class="metric-label"><?php esc_html_e('HTML Pages', 'wpcalibrate-html-bridge'); ?></span>
                            </div>
                            <div class="wpcalibrate-metric-box">
                                <span class="metric-val" id="metric-assets">0</span>
                                <span class="metric-label"><?php esc_html_e('Total Assets', 'wpcalibrate-html-bridge'); ?></span>
                            </div>
                            <div class="wpcalibrate-metric-box">
                                <span class="metric-val" id="metric-internal-links">0</span>
                                <span class="metric-label"><?php esc_html_e('Internal Links', 'wpcalibrate-html-bridge'); ?></span>
                            </div>
                            <div class="wpcalibrate-metric-box">
                                <span class="metric-val" id="metric-external-links">0</span>
                                <span class="metric-label"><?php esc_html_e('External Links', 'wpcalibrate-html-bridge'); ?></span>
                            </div>
                            <div class="wpcalibrate-metric-box">
                                <span class="metric-val" id="metric-forms">0</span>
                                <span class="metric-label"><?php esc_html_e('Forms Detected', 'wpcalibrate-html-bridge'); ?></span>
                            </div>
                        </div>

                        <!-- Warnings & Advisories -->
                        <div id="wpcalibrate-scan-warnings" class="wpcalibrate-warnings-panel hidden">
                            <h3><span class="dashicons dashicons-info"></span> <?php esc_html_e('Scan Analysis & Integration Notes', 'wpcalibrate-html-bridge'); ?></h3>
                            <ul id="wpcalibrate-warnings-list"></ul>
                        </div>
                    </div>

                    <!-- Import Mode Selection -->
                    <div class="wpcalibrate-card">
                        <h2><?php esc_html_e('Choose Import Mode', 'wpcalibrate-html-bridge'); ?></h2>
                        <div class="wpcalibrate-mode-options">
                            <label class="wpcalibrate-mode-card">
                                <input type="radio" name="import_mode" value="pages" <?php checked($defaultImportMode, 'pages'); ?> />
                                <div class="mode-info">
                                    <strong><?php esc_html_e('Import as WordPress Pages', 'wpcalibrate-html-bridge'); ?></strong>
                                    <p><?php esc_html_e('Converts static HTML pages into editable WordPress Pages while maintaining your active theme.', 'wpcalibrate-html-bridge'); ?></p>
                                </div>
                            </label>

                            <label class="wpcalibrate-mode-card">
                                <input type="radio" name="import_mode" value="theme" <?php checked($defaultImportMode, 'theme'); ?> />
                                <div class="mode-info">
                                    <strong><?php esc_html_e('Convert to Standalone WordPress Theme', 'wpcalibrate-html-bridge'); ?></strong>
                                    <p><?php esc_html_e('Generates a complete, separate classic WordPress theme from the uploaded static design.', 'wpcalibrate-html-bridge'); ?></p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Mode Specific Configuration -->
                    <div id="wpcalibrate-pages-config" class="wpcalibrate-card">
                        <h2><?php esc_html_e('Page Import Options', 'wpcalibrate-html-bridge'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Content Conversion Mode', 'wpcalibrate-html-bridge'); ?></th>
                                <td>
                                    <label>
                                        <input type="radio" name="conversion_mode" value="preserve_design" <?php checked($defaultConversionMode, 'preserve_design'); ?> />
                                        <strong><?php esc_html_e('Preserve Design', 'wpcalibrate-html-bridge'); ?></strong> &mdash;
                                        <?php esc_html_e('Preserves static CSS, structure, and isolated styling as closely as technically practical.', 'wpcalibrate-html-bridge'); ?>
                                    </label>
                                    <br/><br/>
                                    <label>
                                        <input type="radio" name="conversion_mode" value="gutenberg" <?php checked($defaultConversionMode, 'gutenberg'); ?> />
                                        <strong><?php esc_html_e('Gutenberg Conversion', 'wpcalibrate-html-bridge'); ?></strong> &mdash;
                                        <?php esc_html_e('Maps semantic HTML into native WordPress blocks where reliable, falling back gracefully to Custom HTML.', 'wpcalibrate-html-bridge'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Publishing Status', 'wpcalibrate-html-bridge'); ?></th>
                                <td>
                                    <select name="post_status" id="wpcalibrate-post-status">
                                        <option value="draft" <?php selected($defaultPostStatus, 'draft'); ?>><?php esc_html_e('Draft (Recommended: verify before publishing)', 'wpcalibrate-html-bridge'); ?></option>
                                        <option value="publish" <?php selected($defaultPostStatus, 'publish'); ?>><?php esc_html_e('Publish immediately', 'wpcalibrate-html-bridge'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Assets & Navigation', 'wpcalibrate-html-bridge'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="import_media_library" value="1" checked />
                                        <?php esc_html_e('Import local images into WordPress Media Library and rewrite URLs', 'wpcalibrate-html-bridge'); ?>
                                    </label>
                                    <br/>
                                    <label>
                                        <input type="checkbox" name="create_navigation" value="1" checked />
                                        <?php esc_html_e('Create WordPress Navigation Menu from detected navigation elements', 'wpcalibrate-html-bridge'); ?>
                                    </label>
                                    <br/>
                                    <label>
                                        <input type="checkbox" name="set_front_page" value="1" />
                                        <?php esc_html_e('Set detected homepage as WordPress Static Front Page', 'wpcalibrate-html-bridge'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Theme Mode Specific Configuration -->
                    <div id="wpcalibrate-theme-config" class="wpcalibrate-card hidden">
                        <h2><?php esc_html_e('Theme Generation Options', 'wpcalibrate-html-bridge'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="theme_name"><?php esc_html_e('Theme Display Name', 'wpcalibrate-html-bridge'); ?></label></th>
                                <td>
                                    <input type="text" id="theme_name" name="theme_name" class="regular-text" placeholder="WPCalibrate Static Theme" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="theme_slug"><?php esc_html_e('Theme Directory Slug', 'wpcalibrate-html-bridge'); ?></label></th>
                                <td>
                                    <input type="text" id="theme_slug" name="theme_slug" class="regular-text" placeholder="wpcalibrate-static-theme" />
                                    <p class="description"><?php esc_html_e('Unique folder name under wp-content/themes/', 'wpcalibrate-html-bridge'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Editable Pages Table -->
                    <div class="wpcalibrate-card">
                        <h2><?php esc_html_e('Detected Pages & Slug Mapping', 'wpcalibrate-html-bridge'); ?></h2>
                        <p class="description">
                            <?php esc_html_e('Select which pages to import and customize their page titles and WordPress permalink slugs.', 'wpcalibrate-html-bridge'); ?>
                        </p>

                        <div class="table-responsive">
                            <table class="wp-list-table widefat fixed striped" id="wpcalibrate-pages-table">
                                <thead>
                                    <tr>
                                        <th scope="col" style="width: 40px;"><input type="checkbox" id="wpcalibrate-select-all-pages" checked /></th>
                                        <th scope="col"><?php esc_html_e('Source Path', 'wpcalibrate-html-bridge'); ?></th>
                                        <th scope="col"><?php esc_html_e('WordPress Title', 'wpcalibrate-html-bridge'); ?></th>
                                        <th scope="col"><?php esc_html_e('WordPress Slug', 'wpcalibrate-html-bridge'); ?></th>
                                        <th scope="col" style="width: 100px;"><?php esc_html_e('Homepage', 'wpcalibrate-html-bridge'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="wpcalibrate-pages-tbody">
                                    <!-- Populated dynamically via JS from manifest -->
                                </tbody>
                            </table>
                        </div>

                        <div class="wpcalibrate-form-actions" style="margin-top: 20px;">
                            <button type="button" id="wpcalibrate-btn-cancel" class="button button-secondary">
                                <?php esc_html_e('Cancel & Restart', 'wpcalibrate-html-bridge'); ?>
                            </button>
                            <button type="button" id="wpcalibrate-btn-run-import" class="button button-primary button-hero" style="margin-left: 10px;">
                                <?php esc_html_e('Run Import Now', 'wpcalibrate-html-bridge'); ?> &rarr;
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- STEP 3: Execution Progress -->
            <div id="wpcalibrate-step-3" class="wpcalibrate-step-panel hidden">
                <div class="wpcalibrate-card" style="text-align: center; padding: 40px 20px;">
                    <h2><?php esc_html_e('Executing Import...', 'wpcalibrate-html-bridge'); ?></h2>
                    <p class="description" style="max-width: 600px; margin: 0 auto 20px;">
                        <?php esc_html_e('Please do not close this browser window. The import is running in safe, resumable batches.', 'wpcalibrate-html-bridge'); ?>
                    </p>

                    <div class="wpcalibrate-progress-bar-container" style="max-width: 600px; margin: 0 auto;">
                        <div class="wpcalibrate-progress-bar">
                            <div id="wpcalibrate-exec-progress-fill" class="wpcalibrate-progress-fill" style="width: 5%;"></div>
                        </div>
                        <p id="wpcalibrate-exec-status-text" class="wpcalibrate-progress-status">
                            <?php esc_html_e('Initializing import batches...', 'wpcalibrate-html-bridge'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- STEP 4: Completion Report -->
            <div id="wpcalibrate-step-4" class="wpcalibrate-step-panel hidden">
                <div class="wpcalibrate-card">
                    <div class="wpcalibrate-report-header">
                        <span class="dashicons dashicons-yes-alt wpcalibrate-success-icon"></span>
                        <h2><?php esc_html_e('Import Completed Successfully!', 'wpcalibrate-html-bridge'); ?></h2>
                    </div>

                    <div id="wpcalibrate-report-summary" class="wpcalibrate-report-details">
                        <!-- Populated by JS -->
                    </div>

                    <div class="wpcalibrate-form-actions" style="margin-top: 30px;">
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=page')); ?>" class="button button-primary button-hero">
                            <?php esc_html_e('View Imported Pages', 'wpcalibrate-html-bridge'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpcalibrate-html-bridge&wpcalibrate_tab=imports')); ?>" class="button button-secondary" style="margin-left: 10px;">
                            <?php esc_html_e('Back to Import History', 'wpcalibrate-html-bridge'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($resumeManifest && $resumeId > 0) : ?>
        <script>
            window.wpcalibrateResumeData = <?php echo wp_json_encode([
                'import_id' => $resumeId,
                'manifest'  => $resumeManifest->toArray(),
                'warnings'  => $resumeManifest->getWarnings(),
            ]); ?>;
        </script>
        <?php endif; ?>
        <?php
    }
}
