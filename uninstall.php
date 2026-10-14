<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WPCalibrate\HtmlBridge
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Ensure the PSR-4 autoloader is available.
spl_autoload_register(static function (string $class): void {
    $prefix = 'WPCalibrate\\HtmlBridge\\';
    $baseDir = plugin_dir_path(__FILE__) . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

\WPCalibrate\HtmlBridge\Lifecycle\Uninstaller::uninstall();
