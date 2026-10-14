<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Conversion;

use WPCalibrate\HtmlBridge\Media\MediaMap;
use WPCalibrate\HtmlBridge\Scanner\SiteScanner;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AssetRewriter
 *
 * Rewrites image, CSS, font, and media references in HTML and CSS to their WordPress destinations.
 */
final class AssetRewriter
{
    private MediaMap $mediaMap;
    private string $staticAssetBaseUrl;

    /**
     * Constructor.
     *
     * @param MediaMap $mediaMap           Map of imported attachment URLs.
     * @param string   $staticAssetBaseUrl URL where non-media assets (CSS/JS/fonts) are served.
     */
    public function __construct(MediaMap $mediaMap, string $staticAssetBaseUrl = '')
    {
        $this->mediaMap = $mediaMap;
        $this->staticAssetBaseUrl = rtrim($staticAssetBaseUrl, '/');
    }

    /**
     * Rewrite image src, srcset, picture source, and background url() in HTML content.
     *
     * @param string $htmlContent
     * @param string $currentPagePath
     * @return string
     */
    public function rewriteHtmlAssets(string $htmlContent, string $currentPagePath): string
    {
        if (trim($htmlContent) === '') {
            return $htmlContent;
        }

        // 1. Rewrite img / script / audio / video / source src attributes
        $htmlContent = (string) preg_replace_callback(
            '/(<(?:img|source|audio|video|script|link)\b[^>]*\b(?:src|href)\s*=\s*)(["\'])(.*?)\2/is',
            function (array $matches) use ($currentPagePath): string {
                $prefix = $matches[1];
                $quote = $matches[2];
                $rawUrl = $matches[3];

                $rewritten = $this->resolveAssetUrl($rawUrl, $currentPagePath);
                return $prefix . $quote . esc_url($rewritten) . $quote;
            },
            $htmlContent
        );

        // 2. Rewrite srcset attributes (comma-separated list of url [descriptor])
        $htmlContent = (string) preg_replace_callback(
            '/(<(?:img|source)\b[^>]*\bsrcset\s*=\s*)(["\'])(.*?)\2/is',
            function (array $matches) use ($currentPagePath): string {
                $prefix = $matches[1];
                $quote = $matches[2];
                $rawSrcset = $matches[3];

                $rewrittenSrcset = $this->rewriteSrcset($rawSrcset, $currentPagePath);
                return $prefix . $quote . esc_attr($rewrittenSrcset) . $quote;
            },
            $htmlContent
        );

        // 3. Rewrite inline CSS url(...) references
        $htmlContent = $this->rewriteCssUrls($htmlContent, $currentPagePath);

        return $htmlContent;
    }

    /**
     * Rewrite CSS url() references inside a stylesheet or style block.
     *
     * @param string $cssContent
     * @param string $cssFilePath Relative path of the CSS file.
     * @return string
     */
    public function rewriteCssUrls(string $cssContent, string $cssFilePath): string
    {
        return (string) preg_replace_callback(
            '/url\(\s*(["\']?)([^)]*?)\1\s*\)/i',
            function (array $matches) use ($cssFilePath): string {
                $quote = $matches[1];
                $rawUrl = trim($matches[2]);

                if (str_starts_with($rawUrl, 'data:') || preg_match('/^(https?:)?\/\//i', $rawUrl)) {
                    return $matches[0];
                }

                $rewritten = $this->resolveAssetUrl($rawUrl, $cssFilePath);
                return 'url(' . $quote . esc_url($rewritten) . $quote . ')';
            },
            $cssContent
        );
    }

    /**
     * Resolve an individual asset reference to an attachment URL or static asset URL.
     *
     * @param string $assetRef
     * @param string $referrerPath
     * @return string
     */
    public function resolveAssetUrl(string $assetRef, string $referrerPath): string
    {
        $trimmed = trim($assetRef);

        // Don't touch external or inline data
        if ($trimmed === '' || preg_match('/^(https?:)?\/\//i', $trimmed) || str_starts_with($trimmed, 'data:') || str_starts_with($trimmed, '#')) {
            return $trimmed;
        }

        $resolvedRel = SiteScanner::resolveRelativeAsset($trimmed, $referrerPath);

        // Check if imported into Media Library
        $mediaUrl = $this->mediaMap->getUrl($resolvedRel);
        if ($mediaUrl !== null) {
            return $mediaUrl;
        }

        // If static asset base URL is available (e.g. for CSS, fonts, preserved static files)
        if ($this->staticAssetBaseUrl !== '') {
            return $this->staticAssetBaseUrl . '/' . ltrim($resolvedRel, '/');
        }

        return $assetRef;
    }

    /**
     * Rewrite comma-delimited srcset definitions.
     */
    private function rewriteSrcset(string $srcset, string $currentPagePath): string
    {
        $candidates = explode(',', $srcset);
        $rewrittenCandidates = [];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $parts = preg_split('/\s+/', $candidate, 2);
            if (!empty($parts)) {
                $url = $parts[0];
                $descriptor = $parts[1] ?? '';
                $rewrittenUrl = $this->resolveAssetUrl($url, $currentPagePath);
                $rewrittenCandidates[] = trim(esc_url($rewrittenUrl) . ' ' . $descriptor);
            }
        }

        return implode(', ', $rewrittenCandidates);
    }
}
