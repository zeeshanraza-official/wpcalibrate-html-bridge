<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Admin\Pages;

use WPCalibrate\HtmlBridge\Security\Capabilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SupportPage
 *
 * Displays official support contacts, documentation links, and essential FAQ guides.
 */
final class SupportPage
{
    public function render(): void
    {
        if (!Capabilities::canManage()) {
            wp_die(esc_html__('Unauthorized.', 'wpcalibrate-html-bridge'));
        }

        $email = 'support@wpcalibrate.com';
        $website = 'https://wpcalibrate.com';
        $marketplace = 'https://marketplace.wpcalibrate.com/';
        $phone = '+447474795976';
        $whatsappUrl = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $phone);
        ?>
        <div class="wpcalibrate-card">
            <h2><?php esc_html_e('Help & Direct Support', 'wpcalibrate-html-bridge'); ?></h2>
            <p class="description">
                <?php esc_html_e('Need assistance with a complex HTML migration or custom theme conversion? Our engineering team is here to assist.', 'wpcalibrate-html-bridge'); ?>
            </p>

            <div class="wpcalibrate-contact-cards-grid">
                <div class="wpcalibrate-contact-card">
                    <span class="dashicons dashicons-email-alt contact-icon"></span>
                    <h3><?php esc_html_e('Email Support', 'wpcalibrate-html-bridge'); ?></h3>
                    <p><a href="<?php echo esc_url('mailto:' . $email); ?>"><?php echo esc_html($email); ?></a></p>
                </div>

                <div class="wpcalibrate-contact-card">
                    <span class="dashicons dashicons-admin-site contact-icon"></span>
                    <h3><?php esc_html_e('Official Website', 'wpcalibrate-html-bridge'); ?></h3>
                    <p><a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($website); ?></a></p>
                </div>

                <div class="wpcalibrate-contact-card">
                    <span class="dashicons dashicons-cart contact-icon"></span>
                    <h3><?php esc_html_e('WPCalibrate Marketplace', 'wpcalibrate-html-bridge'); ?></h3>
                    <p><a href="<?php echo esc_url($marketplace); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($marketplace); ?></a></p>
                </div>

                <div class="wpcalibrate-contact-card">
                    <span class="dashicons dashicons-phone contact-icon"></span>
                    <h3><?php esc_html_e('Phone & WhatsApp', 'wpcalibrate-html-bridge'); ?></h3>
                    <p>
                        <a href="<?php echo esc_url('tel:' . $phone); ?>"><?php echo esc_html($phone); ?></a>
                        &bull;
                        <a href="<?php echo esc_url($whatsappUrl); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Chat on WhatsApp', 'wpcalibrate-html-bridge'); ?></a>
                    </p>
                </div>
            </div>

            <hr style="margin: 30px 0; border: 0; border-top: 1px solid #dcdcde;" />

            <h3><?php esc_html_e('Frequently Asked Questions & Migration Limitations', 'wpcalibrate-html-bridge'); ?></h3>
            <div class="wpcalibrate-faq-section">
                <details>
                    <summary><strong><?php esc_html_e('What is the difference between Preserve Design and Gutenberg Conversion modes?', 'wpcalibrate-html-bridge'); ?></strong></summary>
                    <p><?php esc_html_e('Preserve Design mode maintains your original CSS, body structure, and styling isolation to render full-page static layouts faithfully. Gutenberg Conversion parses semantic elements (headings, paragraphs, lists, quotes, tables, images) into native editable WordPress blocks, with complex layouts gracefully preserved as Custom HTML blocks.', 'wpcalibrate-html-bridge'); ?></p>
                </details>
                <details>
                    <summary><strong><?php esc_html_e('Are static HTML forms automatically functional in WordPress?', 'wpcalibrate-html-bridge'); ?></strong></summary>
                    <p><?php esc_html_e('No. Static HTML forms preserve visual markup, but backend form processing requires manual integration with WordPress form handlers or services.', 'wpcalibrate-html-bridge'); ?></p>
                </details>
                <details>
                    <summary><strong><?php esc_html_e('How does rollback work?', 'wpcalibrate-html-bridge'); ?></strong></summary>
                    <p><?php esc_html_e('Rollback safely trashes only the WordPress Pages created during that specific import run, deletes importer-created nav menus, and removes generated theme files if the theme is not active. Unrelated site pages and media are never modified.', 'wpcalibrate-html-bridge'); ?></p>
                </details>
            </div>
        </div>
        <?php
    }
}
