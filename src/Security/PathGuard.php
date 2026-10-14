<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PathGuard
 *
 * Enforces strict filesystem path traversal defenses and filename sanitization.
 */
final class PathGuard
{
    /**
     * Check if a relative path contains directory traversal, null bytes, backslashes or absolute path markers.
     *
     * @param string $path Path to check.
     * @return bool True if safe, false if dangerous or suspicious.
     */
    public static function isPathSafe(string $path): bool
    {
        // Reject null bytes
        if (str_contains($path, "\0")) {
            return false;
        }

        // Normalize slashes for analysis
        $normalized = str_replace('\\', '/', $path);

        // Reject absolute paths on Unix or Windows (e.g. /etc/passwd or C:/windows)
        if (str_starts_with($normalized, '/') || preg_match('/^[a-zA-Z]:\//', $normalized)) {
            return false;
        }

        // Split into parts and check for traversal sequences
        $parts = explode('/', $normalized);
        $depth = 0;

        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed === '' || $trimmed === '.') {
                continue;
            }
            if ($trimmed === '..') {
                $depth--;
                if ($depth < 0) {
                    return false; // Traversed above root
                }
                continue;
            }
            $depth++;
        }

        return true;
    }

    /**
     * Ensure a target path is strictly contained within an allowed base directory.
     *
     * @param string $basePath Base directory that must contain the target.
     * @param string $subPath  Target path (relative or absolute).
     * @return string|null Resolved real safe path, or null if outside base directory.
     */
    public static function resolveSafePath(string $basePath, string $subPath): ?string
    {
        if (!self::isPathSafe($subPath)) {
            return null;
        }

        $baseReal = realpath($basePath);
        if ($baseReal === false) {
            return null;
        }

        $baseReal = rtrim(str_replace('\\', '/', $baseReal), '/') . '/';
        $fullPath = $baseReal . ltrim(str_replace('\\', '/', $subPath), '/');

        // Normalize path segments without relying strictly on realpath for non-existent files
        $parts = [];
        $segments = explode('/', $fullPath);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);
            } else {
                $parts[] = $segment;
            }
        }

        $resolved = (str_starts_with($fullPath, '/') ? '/' : '') . implode('/', $parts);

        // On Windows handle drive letter e.g. D:/
        if (preg_match('/^[a-zA-Z]:/', $fullPath) && !preg_match('/^[a-zA-Z]:/', $resolved)) {
            $prefix = substr($fullPath, 0, 2);
            $resolved = $prefix . '/' . ltrim($resolved, '/');
        }

        // Ensure resolved path starts strictly with base path
        if (!str_starts_with($resolved, rtrim($baseReal, '/'))) {
            return null;
        }

        return $resolved;
    }

    /**
     * Sanitize a relative file or directory path for safe internal referencing.
     *
     * @param string $path
     * @return string
     */
    public static function sanitizeRelativePath(string $path): string
    {
        $path = str_replace(["\0", '\\'], ['', '/'], $path);
        $segments = explode('/', $path);
        $cleanSegments = [];

        foreach ($segments as $segment) {
            $clean = preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', $segment);
            if ($clean === null || $clean === '' || $clean === '.' || $clean === '..') {
                continue;
            }
            $cleanSegments[] = $clean;
        }

        return implode('/', $cleanSegments);
    }
}
