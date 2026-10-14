<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Conversion;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BlockMapper
 *
 * Generates serialized Gutenberg block markup for standard semantic HTML nodes.
 */
final class BlockMapper
{
    /**
     * Create core/heading block.
     */
    public static function heading(string $content, int $level = 2, string $className = ''): string
    {
        $level = max(1, min(6, $level));
        $attrs = ['level' => $level];
        if ($className !== '') {
            $attrs['className'] = $className;
        }

        $jsonAttrs = !empty($attrs) ? ' ' . wp_json_encode($attrs, JSON_UNESCAPED_SLASHES) : '';
        $classAttr = $className !== '' ? sprintf(' class="%s"', esc_attr($className)) : '';

        return sprintf(
            "<!-- wp:heading%s -->\n<h%d%s>%s</h%d>\n<!-- /wp:heading -->\n\n",
            $jsonAttrs,
            $level,
            $classAttr,
            $content,
            $level
        );
    }

    /**
     * Create core/paragraph block.
     */
    public static function paragraph(string $content, string $className = ''): string
    {
        $attrs = [];
        if ($className !== '') {
            $attrs['className'] = $className;
        }
        $jsonAttrs = !empty($attrs) ? ' ' . wp_json_encode($attrs, JSON_UNESCAPED_SLASHES) : '';
        $classAttr = $className !== '' ? sprintf(' class="%s"', esc_attr($className)) : '';

        return sprintf(
            "<!-- wp:paragraph%s -->\n<p%s>%s</p>\n<!-- /wp:paragraph -->\n\n",
            $jsonAttrs,
            $classAttr,
            $content
        );
    }

    /**
     * Create core/list block.
     */
    public static function listBlock(string $innerHtml, bool $ordered = false, string $className = ''): string
    {
        $attrs = [];
        if ($ordered) {
            $attrs['ordered'] = true;
        }
        if ($className !== '') {
            $attrs['className'] = $className;
        }
        $jsonAttrs = !empty($attrs) ? ' ' . wp_json_encode($attrs, JSON_UNESCAPED_SLASHES) : '';
        $tag = $ordered ? 'ol' : 'ul';
        $classAttr = $className !== '' ? sprintf(' class="%s"', esc_attr($className)) : '';

        return sprintf(
            "<!-- wp:list%s -->\n<%s%s>\n%s\n</%s>\n<!-- /wp:list -->\n\n",
            $jsonAttrs,
            $tag,
            $classAttr,
            trim($innerHtml),
            $tag
        );
    }

    /**
     * Create core/quote block.
     */
    public static function quote(string $innerContent, string $citation = ''): string
    {
        $citeHtml = $citation !== '' ? sprintf("<cite>%s</cite>", $citation) : '';
        return sprintf(
            "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">\n%s\n%s\n</blockquote>\n<!-- /wp:quote -->\n\n",
            trim($innerContent),
            $citeHtml
        );
    }

    /**
     * Create core/separator block.
     */
    public static function separator(): string
    {
        return "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->\n\n";
    }

    /**
     * Create core/image block.
     */
    public static function image(string $src, string $alt = '', int $id = 0, string $caption = ''): string
    {
        $attrs = [];
        if ($id > 0) {
            $attrs['id'] = $id;
            $attrs['sizeSlug'] = 'full';
            $attrs['linkDestination'] = 'none';
        }

        $jsonAttrs = !empty($attrs) ? ' ' . wp_json_encode($attrs, JSON_UNESCAPED_SLASHES) : '';
        $captionHtml = $caption !== '' ? sprintf("<figcaption class=\"wp-element-caption\">%s</figcaption>", esc_html($caption)) : '';
        $classId = $id > 0 ? sprintf(' wp-image-%d', $id) : '';

        return sprintf(
            "<!-- wp:image%s -->\n<figure class=\"wp-block-image\"><img src=\"%s\" alt=\"%s\" class=\"%s\"/>%s</figure>\n<!-- /wp:image -->\n\n",
            $jsonAttrs,
            esc_url($src),
            esc_attr($alt),
            trim('wp-block-image' . $classId),
            $captionHtml
        );
    }

    /**
     * Create core/table block.
     */
    public static function table(string $tableHtml): string
    {
        return sprintf(
            "<!-- wp:table -->\n<figure class=\"wp-block-table\">%s</figure>\n<!-- /wp:table -->\n\n",
            trim($tableHtml)
        );
    }

    /**
     * Create core/code block.
     */
    public static function code(string $codeContent): string
    {
        return sprintf(
            "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>%s</code></pre>\n<!-- /wp:code -->\n\n",
            esc_html($codeContent)
        );
    }

    /**
     * Create core/html fallback block for unmapped, fragile, or complex elements.
     */
    public static function customHtml(string $rawHtml): string
    {
        return sprintf(
            "<!-- wp:html -->\n%s\n<!-- /wp:html -->\n\n",
            trim($rawHtml)
        );
    }
}
