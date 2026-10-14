<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Admin\Pages;

use WPCalibrate\HtmlBridge\Security\Capabilities;
use WPCalibrate\HtmlBridge\Security\Nonces;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DemoTemplatesPage
 *
 * Renders the Demo HTML Templates catalog with interactive live preview modal,
 * instant one-click imports, and direct ZIP downloads.
 */
final class DemoTemplatesPage
{
    /**
     * @return array<string, array{
     *     id: string,
     *     title: string,
     *     tag: string,
     *     tag_class: string,
     *     preview_domain: string,
     *     gradient_class: string,
     *     description: string,
     *     file: string,
     *     features: array<string>,
     *     recommended_mode: string
     * }>
     */
    public static function getTemplates(): array
    {
        return [
            'saas-showcase' => [
                'id'               => 'saas-showcase',
                'title'            => __('NexaTech - AI Cloud Platform', 'wpcalibrate-html-bridge'),
                'tag'              => __('SaaS / AI Cloud', 'wpcalibrate-html-bridge'),
                'tag_class'        => 'wpcalibrate-badge-warning',
                'preview_domain'   => 'demo.nexatech.cloud',
                'gradient_class'   => 'gradient-saas',
                'description'      => __('Modern high-impact dark SaaS product site with animated gradient hero, real-time metrics ticker, 6 interactive feature pillars, 3-tier pricing tables, FAQ accordion, and multi-column footer.', 'wpcalibrate-html-bridge'),
                'file'             => 'saas-showcase.zip',
                'features'         => [
                    __('Dark-Mode Hero with Glow Accents', 'wpcalibrate-html-bridge'),
                    __('Live SLA & Latency Stats Banner', 'wpcalibrate-html-bridge'),
                    __('6 Cloud Infrastructure Feature Cards', 'wpcalibrate-html-bridge'),
                    __('3-Tier Pricing Comparison Matrix', 'wpcalibrate-html-bridge'),
                    __('Interactive Native FAQ Accordion', 'wpcalibrate-html-bridge'),
                ],
                'recommended_mode' => __('Preserve Design Mode', 'wpcalibrate-html-bridge'),
            ],
            'agency-portfolio' => [
                'id'               => 'agency-portfolio',
                'title'            => __('StudioVibe - Digital Experience Studio', 'wpcalibrate-html-bridge'),
                'tag'              => __('Creative Agency / Portfolio', 'wpcalibrate-html-bridge'),
                'tag_class'        => 'wpcalibrate-badge-success',
                'preview_domain'   => 'studiovibe.design',
                'gradient_class'   => 'gradient-agency',
                'description'      => __('Ultra-clean editorial studio layout with bold typography, 4 showcase case study cards with metrics, impact stats bar, 4-column capabilities, and interactive consultation form.', 'wpcalibrate-html-bridge'),
                'file'             => 'agency-portfolio.zip',
                'features'         => [
                    __('Editorial Layout & Typography', 'wpcalibrate-html-bridge'),
                    __('4 Detailed Project Case Studies', 'wpcalibrate-html-bridge'),
                    __('120+ Product Impact Numbers Bar', 'wpcalibrate-html-bridge'),
                    __('4-Column Practice Capabilities', 'wpcalibrate-html-bridge'),
                    __('Project Inquiry Form with Budget Picker', 'wpcalibrate-html-bridge'),
                ],
                'recommended_mode' => __('Preserve Design or Theme Mode', 'wpcalibrate-html-bridge'),
            ],
            'business-landing' => [
                'id'               => 'business-landing',
                'title'            => __('ApexConsult - Enterprise Advisory', 'wpcalibrate-html-bridge'),
                'tag'              => __('Corporate / Enterprise', 'wpcalibrate-html-bridge'),
                'tag_class'        => 'wpcalibrate-badge-info',
                'preview_domain'   => 'apexconsult.global',
                'gradient_class'   => 'gradient-corp',
                'description'      => __('Executive enterprise advisory website featuring trust badges, $3.8B+ transaction spotlight card, 4 core practice areas, 4-stage advisory lifecycle roadmap, and consultation booking form.', 'wpcalibrate-html-bridge'),
                'file'             => 'business-landing.zip',
                'features'         => [
                    __('Executive Navy & Slate Palette', 'wpcalibrate-html-bridge'),
                    __('Fortune 500 Trust Endorsement', 'wpcalibrate-html-bridge'),
                    __('M&A & Operational Excellence Pillars', 'wpcalibrate-html-bridge'),
                    __('4-Stage Lifecycle Process Cards', 'wpcalibrate-html-bridge'),
                    __('Confidential Executive Booking Form', 'wpcalibrate-html-bridge'),
                ],
                'recommended_mode' => __('Preserve Design Mode', 'wpcalibrate-html-bridge'),
            ],
            'minimal-blog' => [
                'id'               => 'minimal-blog',
                'title'            => __('Lumina Journal - Tech & Culture', 'wpcalibrate-html-bridge'),
                'tag'              => __('Editorial / Magazine', 'wpcalibrate-html-bridge'),
                'tag_class'        => 'wpcalibrate-badge-info',
                'preview_domain'   => 'luminajournal.org',
                'gradient_class'   => 'gradient-blog',
                'description'      => __('Refined editorial magazine layout highlighting semantic HTML5 architecture: lead headline, styled blockquotes, benchmark comparison data table, code snippet styling, callout box, and author bio.', 'wpcalibrate-html-bridge'),
                'file'             => 'minimal-blog.zip',
                'features'         => [
                    __('Classic Editorial Serif Typography', 'wpcalibrate-html-bridge'),
                    __('Semantic HTML5 Elements & Headings', 'wpcalibrate-html-bridge'),
                    __('Styled Blockquote & Code Snippets', 'wpcalibrate-html-bridge'),
                    __('Data Comparison Matrix Table', 'wpcalibrate-html-bridge'),
                    __('Author Bio Box & Newsletter Card', 'wpcalibrate-html-bridge'),
                ],
                'recommended_mode' => __('Gutenberg Conversion Mode', 'wpcalibrate-html-bridge'),
            ],
        ];
    }

    public function render(): void
    {
        if (!Capabilities::canManage()) {
            wp_die(esc_html__('Unauthorized.', 'wpcalibrate-html-bridge'));
        }

        $templates = self::getTemplates();
        $demosDirUrl = WPCALIBRATE_HTML_BRIDGE_URL . 'assets/demo-templates/';
        $demosDirPath = WPCALIBRATE_HTML_BRIDGE_PATH . 'assets/demo-templates/';
        ?>
        <div class="wpcalibrate-demo-templates-container">
            <!-- Hero Header -->
            <div class="wpcalibrate-card wpcalibrate-demo-hero">
                <div class="wpcalibrate-hero-badge"><?php esc_html_e('Demo Template Gallery', 'wpcalibrate-html-bridge'); ?></div>
                <h2 class="wpcalibrate-hero-title"><?php esc_html_e('Explore & Test Pre-Packaged Static HTML Sites', 'wpcalibrate-html-bridge'); ?></h2>
                <p class="wpcalibrate-hero-subtitle">
                    <?php esc_html_e('Test drive WPCalibrate HTML Bridge with complete, production-quality modern static websites. Click "Live Preview" to inspect any site inside a responsive device sandbox, download the source archive, or trigger an instant One-Click Import.', 'wpcalibrate-html-bridge'); ?>
                </p>
            </div>

            <!-- Templates Grid -->
            <div class="wpcalibrate-demo-grid">
                <?php foreach ($templates as $tmpl) : ?>
                    <?php
                    $zipFilePath = $demosDirPath . $tmpl['file'];
                    $zipFileUrl = $demosDirUrl . $tmpl['file'];
                    $previewUrl = $demosDirUrl . 'previews/' . $tmpl['id'] . '/index.html';
                    $fileExists = file_exists($zipFilePath);
                    $fileSizeKb = $fileExists ? (int) ceil(filesize($zipFilePath) / 1024) : 0;
                    ?>
                    <div class="wpcalibrate-card wpcalibrate-demo-card" data-template-id="<?php echo esc_attr($tmpl['id']); ?>">
                        <!-- Visual Preview Thumbnail Banner -->
                        <div class="demo-card-browser-mockup <?php echo esc_attr($tmpl['gradient_class']); ?>">
                            <div class="mockup-chrome">
                                <span class="chrome-dot dot-red"></span>
                                <span class="chrome-dot dot-yellow"></span>
                                <span class="chrome-dot dot-green"></span>
                                <span class="mockup-url"><?php echo esc_html($tmpl['preview_domain']); ?></span>
                            </div>
                            <div class="mockup-inner-preview">
                                <span class="mockup-badge"><?php echo esc_html($tmpl['tag']); ?></span>
                                <div class="mockup-title"><?php echo esc_html($tmpl['title']); ?></div>
                                <button type="button"
                                        class="mockup-overlay-btn wpcalibrate-btn-demo-preview"
                                        data-template-id="<?php echo esc_attr($tmpl['id']); ?>"
                                        data-preview-url="<?php echo esc_url($previewUrl); ?>"
                                        data-title="<?php echo esc_attr($tmpl['title']); ?>"
                                        data-tag="<?php echo esc_attr($tmpl['tag']); ?>"
                                        data-zip-url="<?php echo esc_url($zipFileUrl); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php esc_html_e('Live Interactive Preview', 'wpcalibrate-html-bridge'); ?>
                                </button>
                            </div>
                        </div>

                        <div class="demo-card-header">
                            <span class="wpcalibrate-badge <?php echo esc_attr($tmpl['tag_class']); ?>">
                                <?php echo esc_html($tmpl['tag']); ?>
                            </span>
                            <?php if ($fileExists) : ?>
                                <span class="demo-size-badge">
                                    <span class="dashicons dashicons-media-archive"></span>
                                    <?php echo esc_html(sprintf(__('%d KB', 'wpcalibrate-html-bridge'), $fileSizeKb)); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <h3 class="demo-card-title"><?php echo esc_html($tmpl['title']); ?></h3>
                        <p class="demo-card-desc"><?php echo esc_html($tmpl['description']); ?></p>

                        <div class="demo-card-highlights">
                            <div class="demo-highlights-label"><?php esc_html_e('Included Components & Architecture:', 'wpcalibrate-html-bridge'); ?></div>
                            <ul class="demo-features-list">
                                <?php foreach ($tmpl['features'] as $feat) : ?>
                                    <li>
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php echo esc_html($feat); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="demo-card-recommendation">
                            <strong><?php esc_html_e('Optimal Conversion:', 'wpcalibrate-html-bridge'); ?></strong>
                            <span><?php echo esc_html($tmpl['recommended_mode']); ?></span>
                        </div>

                        <div class="demo-card-actions">
                            <div class="demo-actions-row">
                                <button type="button"
                                        class="button button-primary wpcalibrate-btn-demo-preview"
                                        data-template-id="<?php echo esc_attr($tmpl['id']); ?>"
                                        data-preview-url="<?php echo esc_url($previewUrl); ?>"
                                        data-title="<?php echo esc_attr($tmpl['title']); ?>"
                                        data-tag="<?php echo esc_attr($tmpl['tag']); ?>"
                                        data-zip-url="<?php echo esc_url($zipFileUrl); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php esc_html_e('Live Preview', 'wpcalibrate-html-bridge'); ?>
                                </button>

                                <button type="button"
                                        class="button button-secondary wpcalibrate-btn-demo-import"
                                        data-template-id="<?php echo esc_attr($tmpl['id']); ?>"
                                        data-loading-text="<?php esc_attr_e('Staging...', 'wpcalibrate-html-bridge'); ?>"
                                        <?php disabled(!$fileExists); ?>>
                                    <span class="dashicons dashicons-upload"></span>
                                    <?php esc_html_e('One-Click Import', 'wpcalibrate-html-bridge'); ?>
                                </button>
                            </div>

                            <?php if ($fileExists) : ?>
                                <a href="<?php echo esc_url($zipFileUrl); ?>"
                                   class="button button-secondary wpcalibrate-btn-demo-download"
                                   download="<?php echo esc_attr($tmpl['file']); ?>"
                                   title="<?php esc_attr_e('Download static ZIP package to inspect locally', 'wpcalibrate-html-bridge'); ?>">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php echo esc_html(sprintf(__('Download Static Package (%d KB)', 'wpcalibrate-html-bridge'), $fileSizeKb)); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Fullscreen Live Preview Modal -->
            <div id="wpcalibrate-preview-modal" class="wpcalibrate-preview-modal hidden" role="dialog" aria-modal="true" aria-labelledby="preview-modal-title">
                <div class="wpcalibrate-preview-backdrop"></div>
                <div class="wpcalibrate-preview-dialog">
                    <!-- Preview Topbar -->
                    <div class="wpcalibrate-preview-topbar">
                        <div class="preview-topbar-left">
                            <span class="preview-app-icon dashicons dashicons-desktop"></span>
                            <div>
                                <h3 id="preview-modal-title" class="preview-title">Template Preview</h3>
                                <span id="preview-modal-tag" class="wpcalibrate-badge wpcalibrate-badge-info">Category</span>
                            </div>
                        </div>

                        <!-- Device Viewport Switcher -->
                        <div class="preview-viewport-switcher" role="group" aria-label="<?php esc_attr_e('Device Viewport Switcher', 'wpcalibrate-html-bridge'); ?>">
                            <button type="button" class="wpcalibrate-viewport-btn active" data-viewport="desktop" title="<?php esc_attr_e('Desktop View (100% width)', 'wpcalibrate-html-bridge'); ?>">
                                <span class="dashicons dashicons-desktop"></span>
                                <span><?php esc_html_e('Desktop', 'wpcalibrate-html-bridge'); ?></span>
                            </button>
                            <button type="button" class="wpcalibrate-viewport-btn" data-viewport="tablet" title="<?php esc_attr_e('Tablet View (768px width)', 'wpcalibrate-html-bridge'); ?>">
                                <span class="dashicons dashicons-tablet"></span>
                                <span><?php esc_html_e('Tablet', 'wpcalibrate-html-bridge'); ?></span>
                            </button>
                            <button type="button" class="wpcalibrate-viewport-btn" data-viewport="mobile" title="<?php esc_attr_e('Mobile View (375px width)', 'wpcalibrate-html-bridge'); ?>">
                                <span class="dashicons dashicons-smartphone"></span>
                                <span><?php esc_html_e('Mobile', 'wpcalibrate-html-bridge'); ?></span>
                            </button>
                        </div>

                        <!-- Action Buttons & Close -->
                        <div class="preview-topbar-right">
                            <a href="#" target="_blank" rel="noopener noreferrer" class="button button-secondary wpcalibrate-preview-modal-external" id="preview-btn-external" title="<?php esc_attr_e('Open template in a new browser tab', 'wpcalibrate-html-bridge'); ?>">
                                <span class="dashicons dashicons-external"></span>
                                <?php esc_html_e('Open in Tab', 'wpcalibrate-html-bridge'); ?>
                            </a>
                            <button type="button" class="button button-primary wpcalibrate-preview-modal-import" id="preview-btn-import" data-loading-text="<?php esc_attr_e('Staging Demo...', 'wpcalibrate-html-bridge'); ?>">
                                <span class="dashicons dashicons-upload"></span>
                                <?php esc_html_e('Import This Template', 'wpcalibrate-html-bridge'); ?>
                            </button>
                            <a href="#" class="button button-secondary wpcalibrate-preview-modal-download" id="preview-btn-download" download>
                                <span class="dashicons dashicons-download"></span>
                                <?php esc_html_e('Download ZIP', 'wpcalibrate-html-bridge'); ?>
                            </a>
                            <button type="button" class="wpcalibrate-preview-close" id="preview-modal-close" aria-label="<?php esc_attr_e('Close Preview', 'wpcalibrate-html-bridge'); ?>">&times;</button>
                        </div>
                    </div>

                    <!-- Sandbox Frame Canvas -->
                    <div class="wpcalibrate-preview-stage">
                        <div id="wpcalibrate-preview-device-wrapper" class="preview-device-desktop">
                            <div class="preview-loader" id="preview-loader">
                                <span class="spinner is-active"></span>
                                <span><?php esc_html_e('Loading full design live preview...', 'wpcalibrate-html-bridge'); ?></span>
                            </div>
                            <iframe id="wpcalibrate-preview-frame" sandbox="allow-scripts allow-same-origin allow-forms allow-popups" src="about:blank" title="<?php esc_attr_e('Live Template Preview', 'wpcalibrate-html-bridge'); ?>"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
