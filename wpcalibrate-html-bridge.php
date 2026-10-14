<?php
/**
 * Plugin Name:       WPCalibrate HTML Bridge
 * Plugin URI:        https://wpcalibrate.com/
 * Description:       Safely import static HTML websites into WordPress as Pages with Preserve Design or Gutenberg Conversion modes, or convert them into standalone WordPress themes.
 * Version:           1.0.2
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            WPCalibrate
 * Author URI:        https://wpcalibrate.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcalibrate-html-bridge
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/zeeshanraza-official/wpcalibrate-html-bridge
 * Primary Branch:    main
 *
 * @package WPCalibrate\HtmlBridge
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Minimum PHP requirement check.
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'WPCalibrate HTML Bridge requires PHP 8.2 or higher. Please upgrade your PHP installation.',
                'wpcalibrate-html-bridge'
            )
        );
    });
    return;
}

// Define core plugin constants.
if (!defined('WPCALIBRATE_HTML_BRIDGE_VERSION')) {
    define('WPCALIBRATE_HTML_BRIDGE_VERSION', '1.0.2');
}
if (!defined('WPCALIBRATE_HTML_BRIDGE_DB_VERSION')) {
    define('WPCALIBRATE_HTML_BRIDGE_DB_VERSION', '1.0.0');
}
if (!defined('WPCALIBRATE_HTML_BRIDGE_FILE')) {
    define('WPCALIBRATE_HTML_BRIDGE_FILE', __FILE__);
}
if (!defined('WPCALIBRATE_HTML_BRIDGE_PATH')) {
    define('WPCALIBRATE_HTML_BRIDGE_PATH', plugin_dir_path(__FILE__));
}
if (!defined('WPCALIBRATE_HTML_BRIDGE_URL')) {
    define('WPCALIBRATE_HTML_BRIDGE_URL', plugin_dir_url(__FILE__));
}
if (!defined('WPCALIBRATE_HTML_BRIDGE_BASENAME')) {
    define('WPCALIBRATE_HTML_BRIDGE_BASENAME', plugin_basename(__FILE__));
}
if (!defined('WPCALIBRATE_HTML_BRIDGE_TEXT_DOMAIN')) {
    define('WPCALIBRATE_HTML_BRIDGE_TEXT_DOMAIN', 'wpcalibrate-html-bridge');
}

// PSR-4 Autoloader for WPCalibrate\HtmlBridge\ namespace.
spl_autoload_register(static function (string $class): void {
    $prefix = 'WPCalibrate\\HtmlBridge\\';
    $baseDir = WPCALIBRATE_HTML_BRIDGE_PATH . 'src/';

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

// Register activation and deactivation hooks.
register_activation_hook(__FILE__, static function (bool $networkWide = false): void {
    \WPCalibrate\HtmlBridge\Lifecycle\Activator::activate($networkWide);
});

register_deactivation_hook(__FILE__, static function (bool $networkWide = false): void {
    \WPCalibrate\HtmlBridge\Lifecycle\Deactivator::deactivate($networkWide);
});

// Bootstrap the plugin.
add_action('plugins_loaded', static function (): void {
    \WPCalibrate\HtmlBridge\Plugin::getInstance()->init();
});
