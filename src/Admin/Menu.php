<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Admin;

use WPCalibrate\HtmlBridge\Security\Capabilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Menu
 *
 * Registers the shared WPCalibrate parent menu and HTML Bridge submenu safely.
 */
final class Menu
{
    public const PARENT_SLUG = 'wpcalibrate';
    public const MENU_SLUG   = 'wpcalibrate-html-bridge';

    /**
     * Register admin hooks.
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenus'], 9);
    }

    /**
     * Register WPCalibrate parent menu (if not already present) and HTML Bridge submenu.
     */
    public function registerMenus(): void
    {
        if (!Capabilities::canManage()) {
            return;
        }

        global $admin_page_hooks;

        // Check if another WPCalibrate plugin has already created the top-level menu
        $parentExists = isset($admin_page_hooks[self::PARENT_SLUG]);

        $menuIcon = WPCALIBRATE_HTML_BRIDGE_URL . 'assets/admin/images/icon-menu.png';

        if (!$parentExists) {
            add_menu_page(
                __('WPCalibrate', 'wpcalibrate-html-bridge'),
                __('WPCalibrate', 'wpcalibrate-html-bridge'),
                Capabilities::REQUIRED_CAPABILITY,
                self::PARENT_SLUG,
                [$this, 'renderMainRedirect'],
                $menuIcon,
                30
            );
        }

        // Add HTML Bridge submenu
        add_submenu_page(
            self::PARENT_SLUG,
            __('HTML Bridge', 'wpcalibrate-html-bridge'),
            __('HTML Bridge', 'wpcalibrate-html-bridge'),
            Capabilities::REQUIRED_CAPABILITY,
            self::MENU_SLUG,
            [$this, 'renderDashboard']
        );

        // If this plugin created the parent menu, fix the duplicate parent submenu item name
        if (!$parentExists) {
            // Remove the default duplicate submenu entry created by WordPress
            remove_submenu_page(self::PARENT_SLUG, self::PARENT_SLUG);
        }
    }

    /**
     * Fallback callback for top-level menu if accessed directly.
     */
    public function renderMainRedirect(): void
    {
        if (!Capabilities::canManage()) {
            wp_die(esc_html__('Unauthorized access.', 'wpcalibrate-html-bridge'));
        }

        // Redirect to HTML Bridge dashboard
        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG));
        exit;
    }

    /**
     * Render the HTML Bridge admin dashboard and tabs.
     */
    public function renderDashboard(): void
    {
        if (!Capabilities::canManage()) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wpcalibrate-html-bridge'));
        }

        $activeTab = $this->getActiveTab();

        // Render dashboard header with branding
        require WPCALIBRATE_HTML_BRIDGE_PATH . 'templates/admin/header.php';

        // Render appropriate tab content
        switch ($activeTab) {
            case 'new_import':
                (new Pages\NewImportPage())->render();
                break;
            case 'demo_templates':
                (new Pages\DemoTemplatesPage())->render();
                break;
            case 'imports':
                (new Pages\ImportsPage())->render();
                break;
            case 'settings':
                (new Pages\SettingsPage())->render();
                break;
            case 'license':
                (new Pages\LicensePage())->render();
                break;
            case 'support':
                (new Pages\SupportPage())->render();
                break;
            case 'overview':
            default:
                (new Pages\OverviewPage())->render();
                break;
        }

        // Render dashboard footer
        require WPCALIBRATE_HTML_BRIDGE_PATH . 'templates/admin/footer.php';
    }

    /**
     * Determine active tab from query parameter safely.
     */
    public function getActiveTab(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab = isset($_GET['wpcalibrate_tab']) ? sanitize_key((string) $_GET['wpcalibrate_tab']) : 'overview';
        $allowedTabs = ['overview', 'new_import', 'demo_templates', 'imports', 'settings', 'license', 'support'];

        return in_array($tab, $allowedTabs, true) ? $tab : 'overview';
    }
}
