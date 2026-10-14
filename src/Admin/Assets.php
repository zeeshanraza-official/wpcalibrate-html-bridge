<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Assets
 *
 * Enqueues CSS and JS strictly on WPCalibrate HTML Bridge administrative screens.
 */
final class Assets
{
    /**
     * Register hook.
     */
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    /**
     * Enqueue assets if currently on this plugin's admin page.
     *
     * @param string $hookSuffix
     */
    public function enqueue(string $hookSuffix): void
    {
        // Strictly isolate assets to this plugin's submenu screen
        if (!str_contains($hookSuffix, 'wpcalibrate-html-bridge')) {
            return;
        }

        // Dynamic cache-busting versions based on file modification timestamp
        $cssFile = WPCALIBRATE_HTML_BRIDGE_PATH . 'assets/admin/css/admin.css';
        $jsFile  = WPCALIBRATE_HTML_BRIDGE_PATH . 'assets/admin/js/admin.js';

        $cssVer  = file_exists($cssFile) ? WPCALIBRATE_HTML_BRIDGE_VERSION . '.' . filemtime($cssFile) : WPCALIBRATE_HTML_BRIDGE_VERSION;
        $jsVer   = file_exists($jsFile) ? WPCALIBRATE_HTML_BRIDGE_VERSION . '.' . filemtime($jsFile) : WPCALIBRATE_HTML_BRIDGE_VERSION;

        // Admin CSS
        wp_enqueue_style(
            'wpcalibrate-html-bridge-admin',
            WPCALIBRATE_HTML_BRIDGE_URL . 'assets/admin/css/admin.css',
            [],
            $cssVer
        );

        // Admin JS
        wp_enqueue_script(
            'wpcalibrate-html-bridge-admin',
            WPCALIBRATE_HTML_BRIDGE_URL . 'assets/admin/js/admin.js',
            ['jquery'],
            $jsVer,
            true
        );

        // Localize script data with security nonce, endpoints, translations
        wp_localize_script('wpcalibrate-html-bridge-admin', 'wpcalibrateHtmlBridge', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'adminUrl'  => admin_url('admin.php'),
            'restUrl'   => esc_url_raw(rest_url('wpcalibrate-html-bridge/v1/')),
            'nonce'     => wp_create_nonce('wpcalibrate_html_bridge_admin_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'i18n'      => [
                'uploading'        => __('Uploading and validating archive...', 'wpcalibrate-html-bridge'),
                'scanning'         => __('Scanning static website package...', 'wpcalibrate-html-bridge'),
                'importing'        => __('Importing content...', 'wpcalibrate-html-bridge'),
                'generatingTheme'  => __('Generating WordPress theme...', 'wpcalibrate-html-bridge'),
                'rollingBack'      => __('Rolling back import...', 'wpcalibrate-html-bridge'),
                'confirmRollback'  => __('Are you sure you want to rollback this import? Trashing created pages and restoring previous settings cannot be undone automatically.', 'wpcalibrate-html-bridge'),
                'confirmReimport'  => __('Are you sure you want to re-import this site? Existing pages may be updated.', 'wpcalibrate-html-bridge'),
                'genericError'     => __('An unexpected error occurred. Please try again.', 'wpcalibrate-html-bridge'),
                'success'          => __('Operation completed successfully.', 'wpcalibrate-html-bridge'),
            ],
        ]);
    }
}
