<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Conversion;

use WPCalibrate\HtmlBridge\Media\MediaMap;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GutenbergConverter
 *
 * Converts static HTML structure into editable native Gutenberg blocks with graceful Custom HTML fallback.
 */
final class GutenbergConverter
{
    private MediaMap $mediaMap;

    public function __construct(MediaMap $mediaMap)
    {
        $this->mediaMap = $mediaMap;
    }

    /**
     * Convert an HTML document to serialized Gutenberg blocks.
     *
     * @param string $htmlContent
     * @param string $pagePath
     * @return array{
     *     content: string,
     *     warnings: array<string>,
     *     blocks_count: int,
     *     fallback_count: int
     * }
     */
    public function convert(string $htmlContent, string $pagePath): array
    {
        $warnings = [];
        $blocksCount = 0;
        $fallbackCount = 0;
        $outputBlocks = [];

        if (trim($htmlContent) === '') {
            return [
                'content'        => '',
                'warnings'       => [],
                'blocks_count'   => 0,
                'fallback_count' => 0,
            ];
        }

        $prevInternalErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $encoded = '<?xml encoding="utf-8" ?>' . $htmlContent;
        @$dom->loadHTML($encoded, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prevInternalErrors);

        $bodyNodes = $dom->getElementsByTagName('body');
        $rootNode = ($bodyNodes->length > 0) ? $bodyNodes->item(0) : $dom->documentElement;

        if (!$rootNode) {
            return [
                'content'        => BlockMapper::customHtml($htmlContent),
                'warnings'       => ['Document could not be parsed; preserved as Custom HTML.'],
                'blocks_count'   => 1,
                'fallback_count' => 1,
            ];
        }

        foreach ($rootNode->childNodes as $node) {
            /** @var \DOMNode $node */
            if ($node->nodeType === XML_TEXT_NODE) {
                $text = trim((string) $node->textContent);
                if ($text !== '') {
                    $outputBlocks[] = BlockMapper::paragraph(esc_html($text));
                    $blocksCount++;
                }
                continue;
            }

            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            /** @var \DOMElement $node */
            $tag = strtolower($node->nodeName);
            $className = trim($node->getAttribute('class'));

            // 1. Headings (h1 - h6)
            if (preg_match('/^h([1-6])$/', $tag, $matches)) {
                $level = (int) $matches[1];
                $innerHtml = self::getInnerHtml($node);
                $outputBlocks[] = BlockMapper::heading($innerHtml, $level, $className);
                $blocksCount++;
                continue;
            }

            // 2. Paragraph (p)
            if ($tag === 'p') {
                $innerHtml = self::getInnerHtml($node);
                if (trim($innerHtml) !== '') {
                    $outputBlocks[] = BlockMapper::paragraph($innerHtml, $className);
                    $blocksCount++;
                }
                continue;
            }

            // 3. Lists (ul, ol)
            if ($tag === 'ul' || $tag === 'ol') {
                $innerHtml = self::getInnerHtml($node);
                $outputBlocks[] = BlockMapper::listBlock($innerHtml, $tag === 'ol', $className);
                $blocksCount++;
                continue;
            }

            // 4. Blockquote
            if ($tag === 'blockquote') {
                $innerHtml = self::getInnerHtml($node);
                $outputBlocks[] = BlockMapper::quote($innerHtml);
                $blocksCount++;
                continue;
            }

            // 5. Separator (hr)
            if ($tag === 'hr') {
                $outputBlocks[] = BlockMapper::separator();
                $blocksCount++;
                continue;
            }

            // 6. Pre / Code
            if ($tag === 'pre') {
                $textContent = (string) $node->textContent;
                $outputBlocks[] = BlockMapper::code($textContent);
                $blocksCount++;
                continue;
            }

            // 7. Table
            if ($tag === 'table') {
                $tableHtml = $dom->saveHTML($node) ?: '';
                $outputBlocks[] = BlockMapper::table($tableHtml);
                $blocksCount++;
                continue;
            }

            // 8. Image (img)
            if ($tag === 'img') {
                $src = $node->getAttribute('src');
                $alt = $node->getAttribute('alt');
                $outputBlocks[] = BlockMapper::image($src, $alt);
                $blocksCount++;
                continue;
            }

            // Fallback for containers, sections, articles, or complex structures
            $outerHtml = $dom->saveHTML($node) ?: '';
            if (trim($outerHtml) !== '') {
                $outputBlocks[] = BlockMapper::customHtml($outerHtml);
                $blocksCount++;
                $fallbackCount++;
                $warnings[] = sprintf(
                    /* translators: %s: HTML tag name */
                    __('Complex element <%s> was preserved as a Custom HTML block.', 'wpcalibrate-html-bridge'),
                    $tag
                );
            }
        }

        return [
            'content'        => implode('', $outputBlocks),
            'warnings'       => $warnings,
            'blocks_count'   => $blocksCount,
            'fallback_count' => $fallbackCount,
        ];
    }

    /**
     * Get inner HTML of a DOM element.
     */
    public static function getInnerHtml(\DOMNode $node): string
    {
        $doc = $node->ownerDocument;
        if (!$doc) {
            return '';
        }

        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $doc->saveHTML($child);
        }

        return trim($html);
    }
}
