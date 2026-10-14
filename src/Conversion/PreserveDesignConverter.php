<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Conversion;

use WPCalibrate\HtmlBridge\Security\Sanitizer;
use WPCalibrate\HtmlBridge\Settings\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PreserveDesignConverter
 *
 * Extracts and isolates body content, page-specific CSS, and scoped assets for faithful layout reproduction.
 */
final class PreserveDesignConverter
{
    /**
     * Convert static HTML page for Preserve Design mode.
     *
     * @param string $htmlContent Raw HTML string.
     * @param string $pagePath    Relative path of the page.
     * @return array{
     *     content: string,
     *     custom_css: string,
     *     stylesheets: array<string>,
     *     scripts: array<string>,
     *     body_class: string,
     *     warnings: array<string>
     * }
     */
    public function convert(string $htmlContent, string $pagePath): array
    {
        $warnings = [];
        $customCss = [];
        $stylesheets = [];
        $scripts = [];
        $bodyClass = '';

        if (trim($htmlContent) === '') {
            return [
                'content'     => '',
                'custom_css'  => '',
                'stylesheets' => [],
                'scripts'     => [],
                'body_class'  => '',
                'warnings'    => [],
            ];
        }

        $prevInternalErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $encoded = '<?xml encoding="utf-8" ?>' . $htmlContent;
        @$dom->loadHTML($encoded, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prevInternalErrors);

        $xpath = new \DOMXPath($dom);

        // 1. Extract <link rel="stylesheet">
        $linkNodes = $xpath->query('//link[contains(@rel, "stylesheet")]');
        if ($linkNodes) {
            foreach ($linkNodes as $link) {
                /** @var \DOMElement $link */
                $href = trim($link->getAttribute('href'));
                if ($href !== '') {
                    $stylesheets[] = $href;
                }
            }
        }

        // 2. Extract <style> blocks
        $styleNodes = $xpath->query('//style');
        if ($styleNodes) {
            foreach ($styleNodes as $style) {
                /** @var \DOMElement $style */
                $cssText = trim((string) $style->textContent);
                if ($cssText !== '') {
                    $customCss[] = $cssText;
                }
                // Remove from DOM so it doesn't duplicate in post_content
                $style->parentNode?->removeChild($style);
            }
        }

        // 3. Extract <script> elements if JavaScript preservation is permitted
        $preserveJs = (bool) Settings::get('preserve_frontend_js', true);
        $scriptNodes = $xpath->query('//script');
        if ($scriptNodes) {
            foreach ($scriptNodes as $script) {
                /** @var \DOMElement $script */
                $src = trim($script->getAttribute('src'));
                $inline = trim((string) $script->textContent);

                if ($preserveJs) {
                    if ($src !== '') {
                        $scripts[] = $src;
                    }
                } else {
                    $warnings[] = __('Inline and local JavaScript was stripped per the active JavaScript security policy.', 'wpcalibrate-html-bridge');
                }

                // Remove from DOM to keep post_content clean
                $script->parentNode?->removeChild($script);
            }
        }

        // 4. Extract body content and body attributes
        $bodyNodes = $dom->getElementsByTagName('body');
        $bodyContent = '';
        if ($bodyNodes->length > 0) {
            /** @var \DOMElement $bodyNode */
            $bodyNode = $bodyNodes->item(0);
            $bodyClass = trim($bodyNode->getAttribute('class'));

            foreach ($bodyNode->childNodes as $child) {
                $bodyContent .= $dom->saveHTML($child);
            }
        } else {
            $bodyContent = $dom->saveHTML() ?: '';
        }

        // Clean any residual unsafe event handlers
        $bodyContent = Sanitizer::cleanHtml($bodyContent);

        // Wrap inside an isolated container
        $wrapperClass = 'wpcalibrate-preserve-design-wrapper';
        if ($bodyClass !== '') {
            $wrapperClass .= ' ' . esc_attr($bodyClass);
        }

        $wrappedContent = sprintf(
            "<div class=\"%s\" data-wpcalibrate-source=\"%s\">\n%s\n</div>",
            $wrapperClass,
            esc_attr($pagePath),
            $bodyContent
        );

        return [
            'content'     => $wrappedContent,
            'custom_css'  => implode("\n\n", $customCss),
            'stylesheets' => array_unique($stylesheets),
            'scripts'     => array_unique($scripts),
            'body_class'  => $bodyClass,
            'warnings'    => $warnings,
        ];
    }
}
