<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Conversion;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PreserveDesignRenderer
 *
 * Selectively enqueues isolated styles and scripts only on frontend pages created by an import.
 */
final class PreserveDesignRenderer
{
    /**
     * Register hooks.
     */
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueuePreservedAssets'], 20);
        add_action('wp_head', [$this, 'printPreservedCustomCss'], 99);
        add_filter('template_include', [$this, 'filterTemplate'], 99);
    }

    /**
     * Enqueue page-specific imported assets.
     */
    public function enqueuePreservedAssets(): void
    {
        if (!is_singular('page')) {
            return;
        }

        $pageId = get_the_ID();
        if (!$pageId) {
            return;
        }

        $importId = (int) get_post_meta($pageId, '_wpcalibrate_html_bridge_import_id', true);
        if ($importId <= 0) {
            return;
        }

        // Enqueue default scoping stylesheet for preserve design wrapper
        wp_enqueue_style(
            'wpcalibrate-html-bridge-preserved-base',
            WPCALIBRATE_HTML_BRIDGE_URL . 'assets/frontend/css/preserved-base.css',
            [],
            WPCALIBRATE_HTML_BRIDGE_VERSION
        );

        // Enqueue registered external stylesheets
        $stylesheets = get_post_meta($pageId, '_wpcalibrate_html_bridge_stylesheets', true);
        if (is_array($stylesheets)) {
            $index = 1;
            foreach ($stylesheets as $sheetUrl) {
                if (is_string($sheetUrl) && $sheetUrl !== '') {
                    $handle = sprintf('wpcalibrate-page-%d-css-%d', $pageId, $index++);
                    wp_enqueue_style($handle, esc_url($sheetUrl), [], WPCALIBRATE_HTML_BRIDGE_VERSION);
                }
            }
        }

        // Inject page-specific custom inline CSS
        $customCss = (string) get_post_meta($pageId, '_wpcalibrate_html_bridge_custom_css', true);
        if (trim($customCss) !== '') {
            wp_add_inline_style('wpcalibrate-html-bridge-preserved-base', $customCss);
        }

        // Enqueue scripts if preserved
        $scripts = get_post_meta($pageId, '_wpcalibrate_html_bridge_scripts', true);
        if (is_array($scripts)) {
            $scriptIndex = 1;
            foreach ($scripts as $scriptUrl) {
                if (is_string($scriptUrl) && $scriptUrl !== '') {
                    $scriptHandle = sprintf('wpcalibrate-page-%d-js-%d', $pageId, $scriptIndex++);
                    wp_enqueue_script($scriptHandle, esc_url($scriptUrl), ['jquery'], WPCALIBRATE_HTML_BRIDGE_VERSION, true);
                }
            }
        }
    }

    /**
     * Direct injection of custom CSS in wp_head ensuring styling renders even in themes
     * that do not fully support wp_add_inline_style or that bypass standard style printing.
     */
    public function printPreservedCustomCss(): void
    {
        if (!is_singular('page')) {
            return;
        }

        $pageId = get_the_ID();
        if (!$pageId) {
            return;
        }

        $importId = (int) get_post_meta($pageId, '_wpcalibrate_html_bridge_import_id', true);
        if ($importId <= 0) {
            return;
        }

        $customCss = (string) get_post_meta($pageId, '_wpcalibrate_html_bridge_custom_css', true);
        if (trim($customCss) !== '') {
            echo "\n<!-- WPCalibrate HTML Bridge Preserved Page Styles -->\n";
            echo "<style id=\"wpcalibrate-page-" . (int) $pageId . "-preserved-css\">\n" . wp_strip_all_tags($customCss) . "\n</style>\n";
        }
    }

    /**
     * Provide a clean canvas template if the page specifies a standalone static template.
     *
     * @param string $template
     * @return string
     */
    public function filterTemplate(string $template): string
    {
        if (!is_singular('page')) {
            return $template;
        }

        $pageId = get_the_ID();
        if (!$pageId) {
            return $template;
        }

        $useCanvas = (bool) get_post_meta($pageId, '_wpcalibrate_html_bridge_canvas_template', true);
        if ($useCanvas) {
            $canvasTemplate = WPCALIBRATE_HTML_BRIDGE_PATH . 'templates/frontend/canvas.php';
            if (file_exists($canvasTemplate)) {
                return $canvasTemplate;
            }
        }

        return $template;
    }
}
