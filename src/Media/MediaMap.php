<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Media;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MediaMap
 *
 * Tracks mappings between original static asset relative paths and imported WordPress attachment IDs / URLs.
 */
final class MediaMap
{
    /**
     * @var array<string, array{id: int, url: string, hash: string}>
     */
    private array $mappings = [];

    /**
     * Add a mapping for a source asset.
     *
     * @param string $sourcePath
     * @param int    $attachmentId
     * @param string $attachmentUrl
     * @param string $sourceHash
     */
    public function add(string $sourcePath, int $attachmentId, string $attachmentUrl, string $sourceHash = ''): void
    {
        $normalized = ltrim(str_replace('\\', '/', $sourcePath), '/');
        $this->mappings[$normalized] = [
            'id'   => $attachmentId,
            'url'  => $attachmentUrl,
            'hash' => $sourceHash,
        ];
    }

    /**
     * Check if a source path has been imported.
     */
    public function has(string $sourcePath): bool
    {
        $normalized = ltrim(str_replace('\\', '/', $sourcePath), '/');
        return isset($this->mappings[$normalized]);
    }

    /**
     * Get attachment info for a source path.
     *
     * @param string $sourcePath
     * @return array{id: int, url: string, hash: string}|null
     */
    public function get(string $sourcePath): ?array
    {
        $normalized = ltrim(str_replace('\\', '/', $sourcePath), '/');
        return $this->mappings[$normalized] ?? null;
    }

    /**
     * Get attachment URL for a source path.
     *
     * @param string $sourcePath
     * @return string|null
     */
    public function getUrl(string $sourcePath): ?string
    {
        $entry = $this->get($sourcePath);
        return $entry ? $entry['url'] : null;
    }

    /**
     * Export all mappings.
     *
     * @return array<string, array{id: int, url: string, hash: string}>
     */
    public function getAll(): array
    {
        return $this->mappings;
    }

    /**
     * Populate from existing array.
     *
     * @param array<string, array{id: int, url: string, hash: string}> $entries
     */
    public function load(array $entries): void
    {
        $this->mappings = $entries;
    }
}
