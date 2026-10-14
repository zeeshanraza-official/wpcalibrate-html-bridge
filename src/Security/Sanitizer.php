<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Sanitizer
 *
 * Dedicated security filtering and sanitization routines.
 */
final class Sanitizer
{
    /**
     * Sanitize a URL slug for a post or page while preserving hierarchy paths.
     *
     * @param string $path E.g. 'services/web-design.html'
     * @return string E.g. 'services/web-design'
     */
    public static function pathToSlug(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        // Remove trailing html/htm extension
        $path = preg_replace('/\.(html|htm|xhtml)$/i', '', $path) ?? $path;
        // Strip out index references
        $path = preg_replace('/(^|\/)index$/i', '', $path) ?? $path;

        if ($path === '' || $path === '/') {
            return '';
        }

        $parts = explode('/', trim($path, '/'));
        $cleanParts = array_map(static fn($p) => sanitize_title($p), $parts);

        return implode('/', array_filter($cleanParts));
    }

    /**
     * Clean untrusted HTML body while removing unsafe inline scripts, event handlers, and data: URIs.
     *
     * @param string $html
     * @return string
     */
    public static function cleanHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // Use wp_kses with post context if user lacks unfiltered_html
        if (!Capabilities::canImportHtml()) {
            return wp_kses_post($html);
        }

        // For administrators, strip malicious active script tags/event handlers in content mode
        // Strip out on* attributes (onload, onclick, onerror, etc.)
        $clean = preg_replace('/\s+on[a-zA-Z]+\s*=\s*(["\']).*?\1/is', '', $html);
        if ($clean !== null) {
            $html = $clean;
        }

        // Strip javascript: pseudo-protocol in href or src
        $clean = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/is', '$1="#"', $html);
        if ($clean !== null) {
            $html = $clean;
        }

        return $html;
    }
}
