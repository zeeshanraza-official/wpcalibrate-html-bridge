<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Notices
 *
 * Manages admin flash notices cleanly.
 */
final class Notices
{
    /**
     * Add a flash notice to transients.
     */
    public static function add(string $message, string $type = 'success'): void
    {
        $userId = get_current_user_id();
        $notices = get_transient('wpcalibrate_html_bridge_notices_' . $userId) ?: [];
        $notices[] = ['message' => $message, 'type' => in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info'];
        set_transient('wpcalibrate_html_bridge_notices_' . $userId, $notices, 60);
    }

    /**
     * Render and clear pending flash notices.
     */
    public static function render(): void
    {
        $userId = get_current_user_id();
        $notices = get_transient('wpcalibrate_html_bridge_notices_' . $userId);
        if (is_array($notices)) {
            delete_transient('wpcalibrate_html_bridge_notices_' . $userId);
            foreach ($notices as $notice) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr($notice['type']),
                    esc_html($notice['message'])
                );
            }
        }
    }
}
