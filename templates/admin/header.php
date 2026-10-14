<?php
/**
 * Admin Dashboard Header Template.
 *
 * @package WPCalibrate\HtmlBridge
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$activeTab = (new \WPCalibrate\HtmlBridge\Admin\Menu())->getActiveTab();
$tabs = [
    'overview'       => __('Overview', 'wpcalibrate-html-bridge'),
    'new_import'     => __('New Import', 'wpcalibrate-html-bridge'),
    'demo_templates' => __('Demo Templates', 'wpcalibrate-html-bridge'),
    'imports'        => __('Imports History', 'wpcalibrate-html-bridge'),
    'settings'       => __('Settings', 'wpcalibrate-html-bridge'),
    'license'        => __('License', 'wpcalibrate-html-bridge'),
    'support'        => __('Support', 'wpcalibrate-html-bridge'),
];
?>
<div class="wrap wpcalibrate-admin-wrap">
    <header class="wpcalibrate-admin-header">
        <div class="wpcalibrate-header-brand">
            <img src="<?php echo esc_url(WPCALIBRATE_HTML_BRIDGE_URL . 'assets/admin/images/icon-48-dark.png'); ?>"
                 alt="<?php esc_attr_e('WPCalibrate Logo', 'wpcalibrate-html-bridge'); ?>"
                 width="40" height="40" class="wpcalibrate-logo" />
            <div class="wpcalibrate-title-group">
                <h1 class="wpcalibrate-plugin-title"><?php esc_html_e('WPCalibrate HTML Bridge', 'wpcalibrate-html-bridge'); ?></h1>
                <span class="wpcalibrate-version-badge">v<?php echo esc_html(WPCALIBRATE_HTML_BRIDGE_VERSION); ?></span>
            </div>
        </div>
        <div class="wpcalibrate-header-links">
            <a href="https://wpcalibrate.com/" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                <?php esc_html_e('Documentation', 'wpcalibrate-html-bridge'); ?>
            </a>
        </div>
    </header>

    <nav class="nav-tab-wrapper wpcalibrate-nav-tabs" aria-label="<?php esc_attr_e('WPCalibrate HTML Bridge Tabs', 'wpcalibrate-html-bridge'); ?>">
        <?php foreach ($tabs as $tabKey => $tabLabel) : ?>
            <?php
            $tabUrl = admin_url('admin.php?page=wpcalibrate-html-bridge&wpcalibrate_tab=' . $tabKey);
            $isActive = ($activeTab === $tabKey);
            ?>
            <a href="<?php echo esc_url($tabUrl); ?>"
               class="nav-tab <?php echo $isActive ? 'nav-tab-active' : ''; ?>"
               <?php echo $isActive ? 'aria-current="page"' : ''; ?>>
                <?php echo esc_html($tabLabel); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <main class="wpcalibrate-tab-content">
        <?php \WPCalibrate\HtmlBridge\Admin\Notices::render(); ?>
