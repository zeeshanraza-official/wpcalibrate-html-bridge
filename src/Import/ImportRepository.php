<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Import;

use WPCalibrate\HtmlBridge\Database\Schema;
use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ImportRepository
 *
 * Provides safe, prepared, relational database operations for imports and items.
 */
class ImportRepository
{
    /**
     * Create a new import record.
     *
     * @param string              $uuid
     * @param int                 $userId
     * @param string              $sourceFilename
     * @param string              $importMode
     * @param string              $conversionMode
     * @param ImportManifest|null $manifest
     * @param array<string, mixed>$settings
     * @return int Inserted ID or 0 on failure.
     */
    public function createImport(
        string $uuid,
        int $userId,
        string $sourceFilename,
        string $importMode,
        string $conversionMode,
        ?ImportManifest $manifest = null,
        array $settings = []
    ): int {
        global $wpdb;

        $table = Schema::getImportsTable();
        $now = gmdate('Y-m-d H:i:s');

        $data = [
            'uuid'            => $uuid,
            'user_id'         => $userId,
            'source_filename' => sanitize_file_name($sourceFilename),
            'source_root'     => '',
            'import_mode'     => sanitize_key($importMode),
            'conversion_mode' => sanitize_key($conversionMode),
            'status'          => ImportState::UPLOADED,
            'manifest'        => $manifest ? $manifest->toJson() : null,
            'settings'        => !empty($settings) ? wp_json_encode($settings) : null,
            'error_summary'   => null,
            'created_at'      => $now,
            'updated_at'      => $now,
        ];

        $format = ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

        $inserted = $wpdb->insert($table, $data, $format);
        if ($inserted === false) {
            Logger::error('Failed to insert import record', ['uuid' => $uuid, 'error' => $wpdb->last_error]);
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Retrieve an import by its UUID.
     *
     * @param string $uuid
     * @return array<string, mixed>|null
     */
    public function getImportByUuid(string $uuid): ?array
    {
        global $wpdb;

        $table = Schema::getImportsTable();
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE uuid = %s LIMIT 1", $uuid);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row($sql, ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /**
     * Retrieve an import by its database ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getImportById(int $id): ?array
    {
        global $wpdb;

        $table = Schema::getImportsTable();
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row($sql, ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /**
     * Update import status with state transition validation.
     *
     * @param int         $id
     * @param string      $newStatus
     * @param string|null $errorSummary
     * @return bool
     */
    public function updateStatus(int $id, string $newStatus, ?string $errorSummary = null): bool
    {
        global $wpdb;

        $current = $this->getImportById($id);
        if (!$current) {
            return false;
        }

        $currentState = (string) $current['status'];
        if (!ImportState::canTransition($currentState, $newStatus)) {
            Logger::warning('Rejected invalid state transition', [
                'import_id' => $id,
                'from'      => $currentState,
                'to'        => $newStatus,
            ]);
            return false;
        }

        $table = Schema::getImportsTable();
        $now = gmdate('Y-m-d H:i:s');

        $data = [
            'status'     => $newStatus,
            'updated_at' => $now,
        ];
        $format = ['%s', '%s'];

        if ($errorSummary !== null) {
            $data['error_summary'] = $errorSummary;
            $format[] = '%s';
        }

        if (in_array($newStatus, [ImportState::COMPLETED, ImportState::COMPLETED_WITH_WARNINGS, ImportState::ROLLED_BACK], true)) {
            $data['completed_at'] = $now;
            $format[] = '%s';
        }

        $result = $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
        return $result !== false;
    }

    /**
     * Update import manifest and settings.
     *
     * @param int            $id
     * @param ImportManifest $manifest
     * @param array<string, mixed>|null $settings
     * @return bool
     */
    public function updateManifest(int $id, ImportManifest $manifest, ?array $settings = null): bool
    {
        global $wpdb;

        $table = Schema::getImportsTable();
        $data = [
            'manifest'   => $manifest->toJson(),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
        $format = ['%s', '%s'];

        if ($settings !== null) {
            $data['settings'] = wp_json_encode($settings);
            $format[] = '%s';
        }

        $result = $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
        return $result !== false;
    }

    /**
     * List imports with safe pagination and ordering.
     *
     * @param int    $limit
     * @param int    $offset
     * @param string $orderBy
     * @param string $order
     * @return array<array<string, mixed>>
     */
    public function listImports(int $limit = 20, int $offset = 0, string $orderBy = 'created_at', string $order = 'DESC'): array
    {
        global $wpdb;

        $table = Schema::getImportsTable();

        // Strict allowlists for sorting
        $allowedColumns = ['id', 'created_at', 'updated_at', 'status', 'source_filename'];
        if (!in_array($orderBy, $allowedColumns, true)) {
            $orderBy = 'created_at';
        }

        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY {$orderBy} {$order} LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($sql, ARRAY_A);
        return is_array($results) ? $results : [];
    }

    /**
     * Count total imports.
     *
     * @return int
     */
    public function countImports(): int
    {
        global $wpdb;
        $table = Schema::getImportsTable();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Record an imported item (Page, Attachment, Theme asset, etc.).
     *
     * @param int                  $importId
     * @param string               $sourcePath
     * @param string               $sourceHash
     * @param string               $itemType        'page', 'attachment', 'theme_file', 'menu'
     * @param string|null          $destinationType 'post', 'attachment', 'theme', 'nav_menu'
     * @param int|null             $destinationId   Created WordPress ID.
     * @param string|null          $destinationUrl  Created WordPress URL.
     * @param string               $status          'completed', 'failed', 'skipped'
     * @param array<string, mixed> $details
     * @return int Inserted ID.
     */
    public function recordItem(
        int $importId,
        string $sourcePath,
        string $sourceHash,
        string $itemType,
        ?string $destinationType = null,
        ?int $destinationId = null,
        ?string $destinationUrl = null,
        string $status = 'completed',
        array $details = []
    ): int {
        global $wpdb;

        $table = Schema::getItemsTable();
        $now = gmdate('Y-m-d H:i:s');

        $data = [
            'import_id'        => $importId,
            'source_path'      => $sourcePath,
            'source_hash'      => $sourceHash,
            'item_type'        => sanitize_key($itemType),
            'destination_type' => $destinationType ? sanitize_key($destinationType) : null,
            'destination_id'   => $destinationId,
            'destination_url'  => $destinationUrl,
            'status'           => sanitize_key($status),
            'details'          => !empty($details) ? wp_json_encode($details) : null,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];

        $format = ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'];

        $inserted = $wpdb->insert($table, $data, $format);
        return $inserted !== false ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Get all items belonging to an import, optionally filtered by item_type.
     *
     * @param int         $importId
     * @param string|null $itemType
     * @return array<array<string, mixed>>
     */
    public function getItems(int $importId, ?string $itemType = null): array
    {
        global $wpdb;

        $table = Schema::getItemsTable();

        if ($itemType !== null) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE import_id = %d AND item_type = %s ORDER BY id ASC",
                $importId,
                $itemType
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE import_id = %d ORDER BY id ASC",
                $importId
            );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($sql, ARRAY_A);
        return is_array($results) ? $results : [];
    }

    /**
     * Delete an import and its associated item records from the database.
     *
     * @param int $id
     * @return bool
     */
    public function deleteImport(int $id): bool
    {
        global $wpdb;

        $importsTable = Schema::getImportsTable();
        $itemsTable = Schema::getItemsTable();

        $wpdb->delete($itemsTable, ['import_id' => $id], ['%d']);
        $result = $wpdb->delete($importsTable, ['id' => $id], ['%d']);

        return $result !== false;
    }
}
