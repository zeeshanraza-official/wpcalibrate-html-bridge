<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Theme;

use WPCalibrate\HtmlBridge\Archive\FilePolicy;
use WPCalibrate\HtmlBridge\Import\ImportManifest;
use WPCalibrate\HtmlBridge\Scanner\HtmlParser;
use WPCalibrate\HtmlBridge\Support\Filesystem;
use WPCalibrate\HtmlBridge\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ThemeGenerator
 *
 * Generates a clean, production-ready classic WordPress theme from an analyzed static website.
 */
final class ThemeGenerator
{
    /**
     * Generate standalone WordPress theme files in a staging directory.
     *
     * @param string         $extractedDir Extracted static site directory.
     * @param ImportManifest $manifest     Scanned site manifest.
     * @param string         $stagingDir   Destination staging directory.
     * @param string         $themeSlug    Unique slug for the theme.
     * @param string         $themeName    Display name for the theme.
     * @return array{
     *     success: bool,
     *     theme_dir: string,
     *     theme_slug: string,
     *     files: array<string>,
     *     warnings: array<string>,
     *     errors: array<string>
     * }
     */
    public function generate(
        string $extractedDir,
        ImportManifest $manifest,
        string $stagingDir,
        string $themeSlug = '',
        string $themeName = ''
    ): array {
        $extractedDir = trailingslashit(str_replace('\\', '/', $extractedDir));
        $stagingDir = trailingslashit(str_replace('\\', '/', $stagingDir));

        if ($themeSlug === '') {
            $base = pathinfo($manifest->toArray()['original_filename'] ?? 'site', PATHINFO_FILENAME);
            $themeSlug = 'wpcalibrate-theme-' . sanitize_title($base);
        }

        if ($themeName === '') {
            $themeName = 'WPCalibrate ' . ucwords(str_replace(['-', '_'], ' ', $themeSlug));
        }

        $themeDir = $stagingDir . $themeSlug . '/';
        wp_mkdir_p($themeDir);
        wp_mkdir_p($themeDir . 'assets/css');
        wp_mkdir_p($themeDir . 'assets/js');
        wp_mkdir_p($themeDir . 'assets/images');
        wp_mkdir_p($themeDir . 'assets/fonts');

        $filesCreated = [];
        $warnings = [];
        $errors = [];

        // 1. Copy static assets (CSS, JS, images, fonts)
        $cssEnqueueList = [];
        $jsEnqueueList = [];

        foreach ($manifest->getCssFiles() as $cssFile) {
            $src = $extractedDir . $cssFile;
            if (file_exists($src)) {
                $destName = basename($cssFile);
                $dest = $themeDir . 'assets/css/' . $destName;
                copy($src, $dest);
                $cssEnqueueList[] = 'assets/css/' . $destName;
                $filesCreated[] = 'assets/css/' . $destName;
            }
        }

        foreach ($manifest->getJsFiles() as $jsFile) {
            $src = $extractedDir . $jsFile;
            if (file_exists($src)) {
                $destName = basename($jsFile);
                $dest = $themeDir . 'assets/js/' . $destName;
                copy($src, $dest);
                $jsEnqueueList[] = 'assets/js/' . $destName;
                $filesCreated[] = 'assets/js/' . $destName;
            }
        }

        foreach ($manifest->getImages() as $imgFile) {
            $src = $extractedDir . $imgFile;
            if (file_exists($src)) {
                $destName = basename($imgFile);
                $dest = $themeDir . 'assets/images/' . $destName;
                copy($src, $dest);
                $filesCreated[] = 'assets/images/' . $destName;
            }
        }

        foreach ($manifest->getFonts() as $fontFile) {
            $src = $extractedDir . $fontFile;
            if (file_exists($src)) {
                $destName = basename($fontFile);
                $dest = $themeDir . 'assets/fonts/' . $destName;
                copy($src, $dest);
                $filesCreated[] = 'assets/fonts/' . $destName;
            }
        }

        // 2. Generate style.css
        $styleContent = "/*\n"
            . "Theme Name: " . sanitize_text_field($themeName) . "\n"
            . "Theme URI: https://wpcalibrate.com/\n"
            . "Author: WPCalibrate\n"
            . "Author URI: https://wpcalibrate.com/\n"
            . "Description: Standalone WordPress theme generated cleanly by WPCalibrate HTML Bridge.\n"
            . "Version: 1.0.0\n"
            . "Requires at least: 6.5\n"
            . "Tested up to: 6.7\n"
            . "Requires PHP: 8.2\n"
            . "License: GPL-2.0-or-later\n"
            . "License URI: https://www.gnu.org/licenses/gpl-2.0.html\n"
            . "Text Domain: " . sanitize_key($themeSlug) . "\n"
            . "*/\n\n"
            . "/* Base generated layout styles */\n"
            . "body { margin: 0; padding: 0; box-sizing: border-box; }\n";

        file_put_contents($themeDir . 'style.css', $styleContent);
        $filesCreated[] = 'style.css';

        // 3. Generate functions.php
        $fnContent = "<?php\n"
            . "declare(strict_types=1);\n\n"
            . "if (!defined('ABSPATH')) {\n    exit;\n}\n\n"
            . "function " . str_replace('-', '_', $themeSlug) . "_setup(): void {\n"
            . "    add_theme_support('title-tag');\n"
            . "    add_theme_support('post-thumbnails');\n"
            . "    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);\n"
            . "    register_nav_menus(['primary' => __('Primary Navigation', '" . sanitize_key($themeSlug) . "')]);\n"
            . "}\n"
            . "add_action('after_setup_theme', '" . str_replace('-', '_', $themeSlug) . "_setup');\n\n"
            . "function " . str_replace('-', '_', $themeSlug) . "_scripts(): void {\n"
            . "    wp_enqueue_style('" . sanitize_key($themeSlug) . "-style', get_stylesheet_uri(), [], '1.0.0');\n";

        $i = 1;
        foreach ($cssEnqueueList as $cssRel) {
            $handle = sanitize_key($themeSlug) . '-css-' . $i++;
            $fnContent .= "    wp_enqueue_style('{$handle}', get_template_directory_uri() . '/{$cssRel}', [], '1.0.0');\n";
        }

        $j = 1;
        foreach ($jsEnqueueList as $jsRel) {
            $handle = sanitize_key($themeSlug) . '-js-' . $j++;
            $fnContent .= "    wp_enqueue_script('{$handle}', get_template_directory_uri() . '/{$jsRel}', ['jquery'], '1.0.0', true);\n";
        }

        $fnContent .= "}\nadd_action('wp_enqueue_scripts', '" . str_replace('-', '_', $themeSlug) . "_scripts');\n";

        file_put_contents($themeDir . 'functions.php', $fnContent);
        $filesCreated[] = 'functions.php';

        // 4. Generate header.php
        $headerContent = "<!DOCTYPE html>\n"
            . "<html <?php language_attributes(); ?>>\n"
            . "<head>\n"
            . "    <meta charset=\"<?php bloginfo('charset'); ?>\">\n"
            . "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n"
            . "    <?php wp_head(); ?>\n"
            . "</head>\n"
            . "<body <?php body_class(); ?>>\n"
            . "<?php wp_body_open(); ?>\n"
            . "<header id=\"site-header\" class=\"site-header\">\n"
            . "    <div class=\"site-branding\">\n"
            . "        <a href=\"<?php echo esc_url(home_url('/')); ?>\"><?php bloginfo('name'); ?></a>\n"
            . "    </div>\n"
            . "    <nav class=\"site-navigation\">\n"
            . "        <?php wp_nav_menu(['theme_location' => 'primary', 'fallback_cb' => false]); ?>\n"
            . "    </nav>\n"
            . "</header>\n";

        file_put_contents($themeDir . 'header.php', $headerContent);
        $filesCreated[] = 'header.php';

        // 5. Generate footer.php
        $footerContent = "<footer id=\"site-footer\" class=\"site-footer\">\n"
            . "    <div class=\"site-info\">\n"
            . "        &copy; <?php echo esc_html(gmdate('Y')); ?> <?php bloginfo('name'); ?>\n"
            . "    </div>\n"
            . "</footer>\n"
            . "<?php wp_footer(); ?>\n"
            . "</body>\n</html>\n";

        file_put_contents($themeDir . 'footer.php', $footerContent);
        $filesCreated[] = 'footer.php';

        // 6. Generate page.php
        $pageContent = "<?php\n"
            . "get_header();\n"
            . "?>\n"
            . "<main id=\"primary\" class=\"site-main\">\n"
            . "<?php\n"
            . "while (have_posts()) : the_post();\n"
            . "    the_title('<h1 class=\"entry-title\">', '</h1>');\n"
            . "    the_content();\n"
            . "endwhile;\n"
            . "?>\n"
            . "</main>\n"
            . "<?php\n"
            . "get_footer();\n";

        file_put_contents($themeDir . 'page.php', $pageContent);
        $filesCreated[] = 'page.php';

        // 7. Generate index.php
        $indexContent = "<?php\n"
            . "get_header();\n"
            . "?>\n"
            . "<main id=\"primary\" class=\"site-main\">\n"
            . "<?php\n"
            . "if (have_posts()) :\n"
            . "    while (have_posts()) : the_post();\n"
            . "        the_title('<h2><a href=\"' . esc_url(get_permalink()) . '\">', '</a></h2>');\n"
            . "        the_excerpt();\n"
            . "    endwhile;\n"
            . "else :\n"
            . "    echo '<p>' . esc_html__('No content found.', '" . sanitize_key($themeSlug) . "') . '</p>';\n"
            . "endif;\n"
            . "?>\n"
            . "</main>\n"
            . "<?php\n"
            . "get_footer();\n";

        file_put_contents($themeDir . 'index.php', $indexContent);
        $filesCreated[] = 'index.php';

        // 8. Screenshot if available
        $menuIcon = WPCALIBRATE_HTML_BRIDGE_PATH . 'branding/icon-dark.png';
        if (file_exists($menuIcon)) {
            copy($menuIcon, $themeDir . 'screenshot.png');
            $filesCreated[] = 'screenshot.png';
        }

        return [
            'success'    => true,
            'theme_dir'  => $themeDir,
            'theme_slug' => $themeSlug,
            'files'      => $filesCreated,
            'warnings'   => $warnings,
            'errors'     => $errors,
        ];
    }
}
