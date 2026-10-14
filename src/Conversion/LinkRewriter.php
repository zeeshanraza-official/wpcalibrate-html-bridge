<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Conversion;

use WPCalibrate\HtmlBridge\Scanner\SiteScanner;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LinkRewriter
 *
 * Rewrites internal page hyperlinks to target WordPress URLs while preserving protocols, anchors, and queries.
 */
final class LinkRewriter
{
    /**
     * Map of source relative page paths to WordPress permalinks.
     * e.g. ['about.html' => 'https://example.com/about/', 'services/web.html' => 'https://example.com/services/web/']
     *
     * @var array<string, string>
     */
    private array $urlMap = [];

    /**
     * Optional source domain to match absolute URLs (e.g. 'https://oldsite.com').
     */
    private string $sourceDomain = '';

    /**
     * Constructor.
     *
     * @param array<string, string> $urlMap
     * @param string                $sourceDomain
     */
    public function __construct(array $urlMap = [], string $sourceDomain = '')
    {
        $this->urlMap = $urlMap;
        $this->sourceDomain = rtrim($sourceDomain, '/');
    }

    /**
     * Rewrite internal links within an HTML fragment or page.
     *
     * @param string $htmlContent
     * @param string $currentPagePath Relative path of the page containing the links.
     * @return string Rewritten HTML.
     */
    public function rewriteHtmlLinks(string $htmlContent, string $currentPagePath): string
    {
        if (trim($htmlContent) === '' || empty($this->urlMap)) {
            return $htmlContent;
        }

        // Match <a ... href="...">
        return (string) preg_replace_callback(
            '/(<a\b[^>]*\bhref\s*=\s*)(["\'])(.*?)\2/is',
            function (array $matches) use ($currentPagePath): string {
                $prefix = $matches[1];
                $quote = $matches[2];
                $rawHref = $matches[3];

                $rewritten = $this->rewriteHref($rawHref, $currentPagePath);
                return $prefix . $quote . esc_url($rewritten) . $quote;
            },
            $htmlContent
        );
    }

    /**
     * Rewrite an individual href value.
     *
     * @param string $href
     * @param string $currentPagePath
     * @return string
     */
    public function rewriteHref(string $href, string $currentPagePath): string
    {
        $trimmed = trim($href);

        // Keep empty, javascript, mailto, tel, sms, whatsapp unchanged
        if ($trimmed === '' || preg_match('/^(mailto:|tel:|sms:|javascript:|data:|#)/i', $trimmed)) {
            return $trimmed;
        }

        // If source domain is configured and href matches it, strip domain
        if ($this->sourceDomain !== '' && str_starts_with($trimmed, $this->sourceDomain)) {
            $trimmed = substr($trimmed, strlen($this->sourceDomain));
            if ($trimmed === '') {
                $trimmed = '/';
            }
        }

        // External domain check
        if (preg_match('/^(https?:)?\/\//i', $trimmed)) {
            return $trimmed;
        }

        // Separate query string and fragment
        $parts = parse_url($trimmed);
        $pathOnly = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        // If only anchor or query, preserve
        if ($pathOnly === '') {
            return $query . $fragment;
        }

        // Resolve path relative to current page
        $resolved = SiteScanner::resolveRelativeAsset($pathOnly, $currentPagePath);

        // Check if exact match in urlMap
        if (isset($this->urlMap[$resolved])) {
            return rtrim($this->urlMap[$resolved], '/') . '/' . $query . $fragment;
        }

        // Check if resolved without index.html or with index.html matches
        if (str_ends_with($resolved, '/index.html') && isset($this->urlMap[substr($resolved, 0, -11)])) {
            return rtrim($this->urlMap[substr($resolved, 0, -11)], '/') . '/' . $query . $fragment;
        }

        if (isset($this->urlMap[$resolved . '/index.html'])) {
            return rtrim($this->urlMap[$resolved . '/index.html'], '/') . '/' . $query . $fragment;
        }

        // Check index.htm variations
        if (str_ends_with($resolved, '/index.htm') && isset($this->urlMap[substr($resolved, 0, -10)])) {
            return rtrim($this->urlMap[substr($resolved, 0, -10)], '/') . '/' . $query . $fragment;
        }

        return $href;
    }
}
