<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Admin\Pages;

use WPCalibrate\HtmlBridge\Database\Schema;
use WPCalibrate\HtmlBridge\Import\ImportRepository;
use WPCalibrate\HtmlBridge\Security\Nonces;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ImportsPage
 *
 * Renders the import history table, status indicators, and rollback/re-import actions.
 */
final class ImportsPage
{
    private ImportRepository $repository;

    public function __construct(?ImportRepository $repository = null)
    {
        $this->repository = $repository ?? new ImportRepository();
    }

    public function render(): void
    {
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $limit = 20;
        $offset = ($paged - 1) * $limit;

        $imports = $this->repository->listImports($limit, $offset);
        $totalImports = $this->repository->countImports();
        $totalPages = (int) ceil($totalImports / $limit);
        ?>
        <div class="wpcalibrate-card">
            <div class="wpcalibrate-card-header">
                <h2><?php esc_html_e('Import History', 'wpcalibrate-html-bridge'); ?></h2>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wpcalibrate-html-bridge&wpcalibrate_tab=new_import')); ?>"
                   class="button button-primary">
                    <?php esc_html_e('+ New Import', 'wpcalibrate-html-bridge'); ?>
                </a>
            </div>

            <?php if (empty($imports)) : ?>
                <div class="wpcalibrate-empty-state">
                    <p class="wpcalibrate-empty-title"><?php esc_html_e('No imports yet', 'wpcalibrate-html-bridge'); ?></p>
                    <p class="wpcalibrate-empty-desc">
                        <?php esc_html_e('Upload a static HTML website archive to convert it into native WordPress Pages or a standalone theme.', 'wpcalibrate-html-bridge'); ?>
                    </p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpcalibrate-html-bridge&wpcalibrate_tab=new_import')); ?>"
                       class="button button-primary button-hero">
                        <?php esc_html_e('Start Your First Import', 'wpcalibrate-html-bridge'); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 50px;"><?php esc_html_e('ID', 'wpcalibrate-html-bridge'); ?></th>
                                <th scope="col"><?php esc_html_e('Archive File', 'wpcalibrate-html-bridge'); ?></th>
                                <th scope="col"><?php esc_html_e('Mode', 'wpcalibrate-html-bridge'); ?></th>
                                <th scope="col"><?php esc_html_e('Status', 'wpcalibrate-html-bridge'); ?></th>
                                <th scope="col"><?php esc_html_e('Date', 'wpcalibrate-html-bridge'); ?></th>
                                <th scope="col" style="text-align: right;"><?php esc_html_e('Actions', 'wpcalibrate-html-bridge'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($imports as $import) : ?>
                                <?php
                                $status = (string) $import['status'];
                                $statusBadgeClass = match ($status) {
                                    'completed'               => 'wpcalibrate-badge-success',
                                    'completed_with_warnings' => 'wpcalibrate-badge-warning',
                                    'importing', 'scanning'   => 'wpcalibrate-badge-info',
                                    'failed'                  => 'wpcalibrate-badge-danger',
                                    'rolled_back'             => 'wpcalibrate-badge-secondary',
                                    default                   => 'wpcalibrate-badge-default',
                                };
                                ?>
                                <tr data-import-id="<?php echo esc_attr((string) $import['id']); ?>">
                                    <td>#<?php echo esc_html((string) $import['id']); ?></td>
                                    <td>
                                        <strong><?php echo esc_html((string) $import['source_filename']); ?></strong>
                                        <div class="row-actions">
                                            <span class="uuid-text code"><?php echo esc_html(substr((string) $import['uuid'], 0, 8)); ?>...</span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo esc_html(ucfirst((string) $import['import_mode'])); ?>
                                        <span class="description">(<?php echo esc_html((string) $import['conversion_mode']); ?>)</span>
                                    </td>
                                    <td>
                                        <span class="wpcalibrate-badge <?php echo esc_attr($statusBadgeClass); ?>">
                                            <?php echo esc_html(str_replace('_', ' ', $status)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html((string) $import['created_at']); ?></td>
                                    <td style="text-align: right;">
                                        <?php if (in_array($status, ['completed', 'completed_with_warnings'], true)) : ?>
                                            <button type="button" class="button button-small button-link-delete wpcalibrate-btn-rollback"
                                                    data-import-id="<?php echo esc_attr((string) $import['id']); ?>">
                                                <?php esc_html_e('Rollback', 'wpcalibrate-html-bridge'); ?>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($status === 'preview_ready') : ?>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpcalibrate-html-bridge&wpcalibrate_tab=new_import&resume_import=' . (int) $import['id'])); ?>"
                                               class="button button-small button-primary">
                                                <?php esc_html_e('Continue', 'wpcalibrate-html-bridge'); ?>
                                            </a>
                                        <?php endif; ?>

                                        <button type="button" class="button button-small wpcalibrate-btn-delete"
                                                data-import-id="<?php echo esc_attr((string) $import['id']); ?>">
                                            <?php esc_html_e('Delete', 'wpcalibrate-html-bridge'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1) : ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links([
                                'base'      => add_query_arg('paged', '%#%'),
                                'format'    => '',
                                'prev_text' => __('&laquo;', 'wpcalibrate-html-bridge'),
                                'next_text' => __('&raquo;', 'wpcalibrate-html-bridge'),
                                'total'     => $totalPages,
                                'current'   => $paged,
                            ]);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
