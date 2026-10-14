<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge;

use WPCalibrate\HtmlBridge\Admin\AjaxHandler;
use WPCalibrate\HtmlBridge\Admin\Assets;
use WPCalibrate\HtmlBridge\Admin\Menu;
use WPCalibrate\HtmlBridge\Conversion\PreserveDesignRenderer;
use WPCalibrate\HtmlBridge\Database\Migrator;
use WPCalibrate\HtmlBridge\Import\ImportManager;
use WPCalibrate\HtmlBridge\Support\Filesystem;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Plugin
 *
 * Primary application orchestrator and service container singleton.
 */
final class Plugin
{
    private static ?self $instance = null;

    private ImportManager $importManager;
    private Menu $menu;
    private Assets $assets;
    private AjaxHandler $ajaxHandler;
    private PreserveDesignRenderer $preserveRenderer;

    private function __construct()
    {
        $this->importManager = new ImportManager();
        $this->menu = new Menu();
        $this->assets = new Assets();
        $this->ajaxHandler = new AjaxHandler($this->importManager);
        $this->preserveRenderer = new PreserveDesignRenderer();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize plugin components and hooks.
     */
    public function init(): void
    {
        // 1. Run migrations if database schema needs update
        Migrator::maybeMigrate();

        // 2. Load translations
        add_action('init', [$this, 'loadTextdomain']);

        // 3. Register daily temporary files cleanup cron hook
        add_action('wpcalibrate_html_bridge_daily_cleanup', static function (): void {
            Filesystem::cleanExpiredTemp(24);
        });

        // 4. Register admin components
        if (is_admin()) {
            $this->menu->register();
            $this->assets->register();
            $this->ajaxHandler->register();
        }

        // 5. Register frontend rendering layer for preserved designs
        $this->preserveRenderer->register();
    }

    /**
     * Load plugin text domain.
     */
    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            WPCALIBRATE_HTML_BRIDGE_TEXT_DOMAIN,
            false,
            dirname(WPCALIBRATE_HTML_BRIDGE_BASENAME) . '/languages/'
        );
    }

    public function getImportManager(): ImportManager
    {
        return $this->importManager;
    }
}
