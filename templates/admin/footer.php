<?php
/**
 * Admin Dashboard Footer Template.
 *
 * @package WPCalibrate\HtmlBridge
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
    </main>

    <footer class="wpcalibrate-admin-footer">
        <p>
            <?php
            printf(
                /* translators: 1: WPCalibrate link, 2: Marketplace link */
                esc_html__('Developed with precision by %1$s &bull; Explore more tools on the %2$s', 'wpcalibrate-html-bridge'),
                '<a href="https://wpcalibrate.com/" target="_blank" rel="noopener noreferrer">WPCalibrate</a>',
                '<a href="https://marketplace.wpcalibrate.com/" target="_blank" rel="noopener noreferrer">WPCalibrate Marketplace</a>'
            );
            ?>
        </p>
    </footer>
</div>
