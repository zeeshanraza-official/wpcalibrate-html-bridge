<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Scanner;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HtmlParser
 *
 * Robust DOM-based HTML parser that safely handles malformed markup and extracts semantic metadata.
 */
final class HtmlParser
{
    /**
     * Parse an HTML document and extract structural and semantic elements.
     *
     * @param string $htmlContent Raw HTML content.
     * @param string $filePath    Relative file path of the document.
     * @return array{
     *     title: string,
     *     meta_description: string,
     *     canonical_url: string,
     *     og_title: string,
     *     og_description: string,
     *     og_image: string,
     *     favicon: string,
     *     stylesheets: array<string>,
     *     scripts: array{
     *         local: array<string>,
     *         external: array<string>,
     *         inline_count: int
     *     },
     *     images: array<array{src: string, alt: string}>,
     *     links: array<array{href: string, text: string, is_external: bool}>,
     *     forms: array<array{action: string, method: string, type: string}>,
     *     iframes: array<string>,
     *     nav_candidates: array<array{tag: string, links: array<string>}>,
     *     has_body: bool
     * }
     */
    public static function parse(string $htmlContent, string $filePath = ''): array
    {
        $result = [
            'title'            => '',
            'meta_description' => '',
            'canonical_url'    => '',
            'og_title'         => '',
            'og_description'   => '',
            'og_image'         => '',
            'favicon'          => '',
            'stylesheets'      => [],
            'scripts'          => [
                'local'        => [],
                'external'     => [],
                'inline_count' => 0,
            ],
            'images'           => [],
            'links'            => [],
            'forms'            => [],
            'iframes'          => [],
            'nav_candidates'   => [],
            'has_body'         => false,
        ];

        if (trim($htmlContent) === '') {
            return $result;
        }

        // Save and suppress libxml errors
        $prevInternalErrors = libxml_use_internal_errors(true);
        $prevEntityLoader = true;
        if (\PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader')) {
            // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
            $prevEntityLoader = libxml_disable_entity_loader(true);
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        // Ensure UTF-8 decoding handling without deprecated HTML-ENTITIES
        $encodedHtml = '<?xml encoding="utf-8" ?>' . $htmlContent;
        $loaded = @$dom->loadHTML($encodedHtml, LIBXML_NOERROR | LIBXML_NOWARNING);

        // Clear libxml errors and restore state
        libxml_clear_errors();
        libxml_use_internal_errors($prevInternalErrors);
        if (\PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader')) {
            // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
            libxml_disable_entity_loader($prevEntityLoader);
        }

        if (!$loaded) {
            return $result;
        }

        $xpath = new \DOMXPath($dom);

        // 1. Title
        $titleNodes = $xpath->query('//title');
        if ($titleNodes && $titleNodes->length > 0) {
            $result['title'] = trim((string) $titleNodes->item(0)->textContent);
        }

        // 2. Meta tags
        $metaNodes = $xpath->query('//meta');
        if ($metaNodes) {
            foreach ($metaNodes as $meta) {
                /** @var \DOMElement $meta */
                $name = strtolower($meta->getAttribute('name'));
                $property = strtolower($meta->getAttribute('property'));
                $content = trim($meta->getAttribute('content'));

                if ($name === 'description') {
                    $result['meta_description'] = $content;
                } elseif ($property === 'og:title') {
                    $result['og_title'] = $content;
                } elseif ($property === 'og:description') {
                    $result['og_description'] = $content;
                } elseif ($property === 'og:image') {
                    $result['og_image'] = $content;
                }
            }
        }

        // 3. Link tags (canonical, stylesheets, favicon)
        $linkNodes = $xpath->query('//link');
        if ($linkNodes) {
            foreach ($linkNodes as $link) {
                /** @var \DOMElement $link */
                $rel = strtolower(trim($link->getAttribute('rel')));
                $href = trim($link->getAttribute('href'));

                if ($rel === 'canonical') {
                    $result['canonical_url'] = $href;
                } elseif (str_contains($rel, 'stylesheet')) {
                    if ($href !== '') {
                        $result['stylesheets'][] = $href;
                    }
                } elseif (str_contains($rel, 'icon')) {
                    if ($href !== '' && empty($result['favicon'])) {
                        $result['favicon'] = $href;
                    }
                }
            }
        }

        // 4. Scripts
        $scriptNodes = $xpath->query('//script');
        if ($scriptNodes) {
            foreach ($scriptNodes as $script) {
                /** @var \DOMElement $script */
                $src = trim($script->getAttribute('src'));
                if ($src === '') {
                    $result['scripts']['inline_count']++;
                } else {
                    if (preg_match('/^(https?:)?\/\//i', $src)) {
                        $result['scripts']['external'][] = $src;
                    } else {
                        $result['scripts']['local'][] = $src;
                    }
                }
            }
        }

        // 5. Images
        $imgNodes = $xpath->query('//img');
        if ($imgNodes) {
            foreach ($imgNodes as $img) {
                /** @var \DOMElement $img */
                $src = trim($img->getAttribute('src'));
                $alt = trim($img->getAttribute('alt'));
                if ($src !== '') {
                    $result['images'][] = [
                        'src' => $src,
                        'alt' => $alt,
                    ];
                }
            }
        }

        // 6. Hyperlinks
        $aNodes = $xpath->query('//a');
        if ($aNodes) {
            foreach ($aNodes as $a) {
                /** @var \DOMElement $a */
                $href = trim($a->getAttribute('href'));
                $text = trim((string) $a->textContent);
                if ($href !== '') {
                    $isExternal = (bool) preg_match('/^(https?:)?\/\//i', $href);
                    $result['links'][] = [
                        'href'        => $href,
                        'text'        => $text,
                        'is_external' => $isExternal,
                    ];
                }
            }
        }

        // 7. Forms
        $formNodes = $xpath->query('//form');
        if ($formNodes) {
            foreach ($formNodes as $form) {
                /** @var \DOMElement $form */
                $action = trim($form->getAttribute('action'));
                $method = strtoupper(trim($form->getAttribute('method')) ?: 'GET');

                $type = 'local_static';
                if (str_starts_with($action, 'mailto:')) {
                    $type = 'mailto';
                } elseif (preg_match('/^(https?:)?\/\//i', $action)) {
                    $type = 'external_service';
                } elseif ($action === '' || $action === '#' || str_starts_with($action, 'javascript:')) {
                    $type = 'script_controlled';
                }

                $result['forms'][] = [
                    'action' => $action,
                    'method' => $method,
                    'type'   => $type,
                ];
            }
        }

        // 8. Iframes
        $iframeNodes = $xpath->query('//iframe');
        if ($iframeNodes) {
            foreach ($iframeNodes as $iframe) {
                /** @var \DOMElement $iframe */
                $src = trim($iframe->getAttribute('src'));
                if ($src !== '') {
                    $result['iframes'][] = $src;
                }
            }
        }

        // 9. Navigation Candidates (<nav> elements and lists inside headers)
        $navNodes = $xpath->query('//nav | //header//ul');
        if ($navNodes) {
            foreach ($navNodes as $nav) {
                /** @var \DOMElement $nav */
                $navLinks = [];
                $subLinks = $xpath->query('.//a', $nav);
                if ($subLinks && $subLinks->length >= 2) {
                    foreach ($subLinks as $subLink) {
                        /** @var \DOMElement $subLink */
                        $navLinks[] = [
                            'href' => trim($subLink->getAttribute('href')),
                            'text' => trim((string) $subLink->textContent),
                        ];
                    }
                    $result['nav_candidates'][] = [
                        'tag'   => $nav->nodeName,
                        'links' => $navLinks,
                    ];
                }
            }
        }

        // 10. Check if body element exists
        $bodyNodes = $xpath->query('//body');
        $result['has_body'] = ($bodyNodes && $bodyNodes->length > 0);

        return $result;
    }
}
