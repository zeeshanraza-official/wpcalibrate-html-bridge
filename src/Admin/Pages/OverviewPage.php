<?php

declare(strict_types=1);

namespace WPCalibrate\HtmlBridge\Admin\Pages;

use WPCalibrate\HtmlBridge\Security\Capabilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OverviewPage
 *
 * Renders the comprehensive Overview tab explaining the plugin's purpose, architecture, workflow, and methods.
 */
final class OverviewPage
{
    public function render(): void
    {
        if (!Capabilities::canManage()) {
            wp_die(esc_html__('Unauthorized.', 'wpcalibrate-html-bridge'));
        }

        $newImportUrl = admin_url('admin.php?page=wpcalibrate-html-bridge&wpcalibrate_tab=new_import');
        $demosUrl = admin_url('admin.php?page=wpcalibrate-html-bridge&wpcalibrate_tab=demo_templates');
        $historyUrl = admin_url('admin.php?page=wpcalibrate-html-bridge&wpcalibrate_tab=imports');
        ?>
        <div class="wpcalibrate-overview-container">
            <!-- Hero Welcome Card -->
            <div class="wpcalibrate-card wpcalibrate-overview-hero">
                <div class="wpcalibrate-hero-badge"><?php esc_html_e('Enterprise Static-to-WordPress Bridge', 'wpcalibrate-html-bridge'); ?></div>
                <h2 class="wpcalibrate-hero-title"><?php esc_html_e('Transform Static HTML Websites into Dynamic WordPress Content', 'wpcalibrate-html-bridge'); ?></h2>
                <p class="wpcalibrate-hero-subtitle">
                    <?php esc_html_e('WPCalibrate HTML Bridge safely inspects, sanitizes, and converts arbitrary static HTML/CSS/JavaScript websites into native WordPress Pages or standalone classic WordPress themes with zero external dependencies.', 'wpcalibrate-html-bridge'); ?>
                </p>
                <div class="wpcalibrate-hero-actions">
                    <a href="<?php echo esc_url($newImportUrl); ?>" class="button button-primary button-hero">
                        <?php esc_html_e('Start New Import', 'wpcalibrate-html-bridge'); ?> &rarr;
                    </a>
                    <a href="<?php echo esc_url($demosUrl); ?>" class="button button-secondary button-hero" style="margin-left: 10px;">
                        <?php esc_html_e('Explore Demo Templates', 'wpcalibrate-html-bridge'); ?>
                    </a>
                    <a href="<?php echo esc_url($historyUrl); ?>" class="button button-secondary" style="margin-left: 10px;">
                        <?php esc_html_e('View Import History', 'wpcalibrate-html-bridge'); ?>
                    </a>
                </div>
            </div>

            <!-- Migration Workflow Steps -->
            <div class="wpcalibrate-card">
                <h3 class="wpcalibrate-section-heading">
                    <span class="dashicons dashicons-randomize"></span>
                    <?php esc_html_e('The 6-Step Migration Workflow', 'wpcalibrate-html-bridge'); ?>
                </h3>
                <p class="description">
                    <?php esc_html_e('Every import follows a rigorous, non-destructive pipeline designed to keep you in complete control before publishing.', 'wpcalibrate-html-bridge'); ?>
                </p>

                <div class="wpcalibrate-workflow-grid">
                    <div class="wpcalibrate-workflow-step">
                        <div class="workflow-step-badge">1</div>
                        <h4><?php esc_html_e('Upload ZIP Archive', 'wpcalibrate-html-bridge'); ?></h4>
                        <p><?php esc_html_e('Upload a ZIP containing static HTML, CSS, JavaScript, web fonts, and images. Nested folders and root parent wrappers are supported.', 'wpcalibrate-html-bridge'); ?></p>
                    </div>

                    <div class="wpcalibrate-workflow-step">
                        <div class="workflow-step-badge">2</div>
                        <h4><?php esc_html_e('Security Scan & Quarantine', 'wpcalibrate-html-bridge'); ?></h4>
                        <p><?php esc_html_e('Archive signatures, file limits, traversal sequences, and compression ratios are verified. Executable server files (.php, .cgi, .py, .htaccess) are quarantined.', 'wpcalibrate-html-bridge'); ?></p>
                    </div>

                    <div class="wpcalibrate-workflow-step">
                        <div class="workflow-step-badge">3</div>
                        <h4><?php esc_html_e('Structured Site Analysis', 'wpcalibrate-html-bridge'); ?></h4>
                        <p><?php esc_html_e('DOMDocument parses titles, meta descriptions, canonical URLs, stylesheets, scripts, forms, and internal links without executing any client code.', 'wpcalibrate-html-bridge'); ?></p>
                    </div>

                    <div class="wpcalibrate-workflow-step">
                        <div class="workflow-step-badge">4</div>
                        <h4><?php esc_html_e('Interactive Preview & Config', 'wpcalibrate-html-bridge'); ?></h4>
                        <p><?php esc_html_e('Review detected pages, customize WordPress titles and permalink slugs, select or exclude individual pages, and choose your conversion mode.', 'wpcalibrate-html-bridge'); ?></p>
                    </div>

                    <div class="wpcalibrate-workflow-step">
                        <div class="workflow-step-badge">5</div>
                        <h4><?php esc_html_e('Resumable Batch Import', 'wpcalibrate-html-bridge'); ?></h4>
                        <p><?php esc_html_e('Chunked execution engine imports media attachments, builds hierarchical pages, rewrites relative URLs, and generates menus under atomic lock safety.', 'wpcalibrate-html-bridge'); ?></p>
                    </div>

                    <div class="wpcalibrate-workflow-step">
                        <div class="workflow-step-badge">6</div>
                        <h4><?php esc_html_e('Verify & Rollback Support', 'wpcalibrate-html-bridge'); ?></h4>
                        <p><?php esc_html_e('Inspect generated content via the detailed report. Easily roll back an import to trash only pages and menus owned by that specific import run.', 'wpcalibrate-html-bridge'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Conversion Methods Comparison -->
            <div class="wpcalibrate-card">
                <h3 class="wpcalibrate-section-heading">
                    <span class="dashicons dashicons-layout"></span>
                    <?php esc_html_e('Primary Import & Conversion Methods', 'wpcalibrate-html-bridge'); ?>
                </h3>
                <p class="description">
                    <?php esc_html_e('Choose the technical strategy that best fits your design and editing requirements.', 'wpcalibrate-html-bridge'); ?>
                </p>

                <div class="wpcalibrate-methods-grid">
                    <!-- Method 1: Preserve Design -->
                    <div class="wpcalibrate-method-card">
                        <div class="method-header">
                            <span class="dashicons dashicons-art"></span>
                            <h4><?php esc_html_e('Preserve Design Mode', 'wpcalibrate-html-bridge'); ?></h4>
                        </div>
                        <span class="wpcalibrate-badge wpcalibrate-badge-info"><?php esc_html_e('High Visual Fidelity', 'wpcalibrate-html-bridge'); ?></span>
                        <p class="method-desc">
                            <?php esc_html_e('Preserves original HTML structure and styling as closely as technically practical while keeping styles isolated.', 'wpcalibrate-html-bridge'); ?>
                        </p>
                        <ul class="method-bullets">
                            <li><strong><?php esc_html_e('Scoped CSS Isolation:', 'wpcalibrate-html-bridge'); ?></strong> <?php esc_html_e('Wraps content in an isolated container; styles never bleed into wp-admin or unrelated pages.', 'wpcalibrate-html-bridge'); ?></li>
                            <li><strong><?php esc_html_e('Selective Enqueueing:', 'wpcalibrate-html-bridge'); ?></strong> <?php esc_html_e('Page-specific stylesheets and custom CSS are enqueued only on the matching frontend page.', 'wpcalibrate-html-bridge'); ?></li>
                            <li><strong><?php esc_html_e('Canvas Template:', 'wpcalibrate-html-bridge'); ?></strong> <?php esc_html_e('Includes a clean standalone canvas template to render edge-to-edge layouts without theme header/footer clashes.', 'wpcalibrate-html-bridge'); ?></li>
                            <li><strong><?php esc_html_e('Ideal For:', 'wpcalibrate-html-bridge'); ?></strong> <?php esc_html_e('Bespoke landing pages, pixel-perfect agency sites, and static portfolios.', 'wpcalibrate-html-bridge'); ?></li>
                        </ul>
                    </div>

                    <!-- Method 2: Gutenberg Conversion -->
                    <div class="wpcalibrate-method-card">
                        <div class="method-header">
                            <span class="dashicons dashicons-block-default"></span>
                            <h4><?php esc_html_e('Gutenberg Conversion Mode', 'wpcalibrate-html-bridge'); ?></h4>
                        </div>
                        <span class="wpcalibrate-badge wpcalibrate-badge-success"><?php esc_html_e('Native Block Editing', 'wpcalibrate-html-bridge'); ?></span>
                        <p class="method-desc">
                            <?php esc_html_e('Translates semantic HTML tags into standard native WordPress Gutenberg blocks for seamless content editing.', 'wpcalibrate-html-bridge'); ?>
                        </p>
                        <ul class="method-bullets">
                            <li><strong><?php esc_html_e('Supported Blocks:', 'wpcalibrate-html-bridge'); ?></strong> <?php esc_html_e('Headings, Paragraphs, Ordered/Unordered Lists, Quotes, Tables, Code/Pre, Separators, and Images.', 'wpcalibrate-html-bridge'); ?></li>
                            <li><strong><?php esc_html_e('Custom HTML Fallback:', 'wpcalibrate-html-bridge'); ?></strong> <?php esc_html_e('Complex, script-dependent, or ambiguous elements fall back gracefully to Custom HTML blocks.', 'wpcalibrate-html-bridge'); ?></li>
                            <li><strong><?php esc_html_e('Theme Integration:', 'wpcalibrate-html-bridge'); ?></strong> <?php esc_html_e('Content automatically inherits your active theme typography and styling tokens.', 'wpcalibrate-html-bridge'); ?></li>
                            <li><strong><?php esc_html_e('Ideal For:', 'wpcalibrate-html-bridge'); ?></strong> <?php esc_html_e('Content-heavy sites, documentation, blogs, and articles where non-technical users need to edit text.', 'wpcalibrate-html-bridge'); ?></li>
                        </ul>
                    </div>

                    <!-- Method 3: Theme Generation -->
                    <div class="wpcalibrate-method-card">
                        <div class="method-header">
                            <span class="dashicons dashicons-desktop"></span>
                            <h4><?php esc_html_e('Standalone Theme Generation', 'wpcalibrate-html-bridge'); ?></h4>
                        </div>
                        <span class="wpcalibrate-badge wpcalibrate-badge-warning"><?php esc_html_e('Theme Conversion', 'wpcalibrate-html-bridge'); ?></span>
                        <p class="method-desc">
                            <?php esc_html_e('Converts the static site into a complete, standalone classic WordPress theme ready for installation.', 'wpcalibrate-html-bridge'); ?>
                        </p>
                        <ul class="method-bullets">
                            <li><strong><?php esc_html_e('Standard Templates:', 'wpcalibrate-html-bridge'); ?></strong> <?php esc_html_e('Generates style.css, functions.php, header.php, footer.php, page.php, and index.php.', 'wpcalibrate-html-bridge'); ?></li>
                            <li><strong><?php esc_html_e('Asset Bundling:', 'wpcalibrate-html-bridge'); ?></strong> <?php esc_html_e('Copies CSS, JS, images, and fonts into theme asset directories with dynamic URI functions.', 'wpcalibrate-html-bridge'); ?></li>
                            <li><strong><?php esc_html_e('Manual Activation:', 'wpcalibrate-html-bridge'); ?></strong> <?php esc_html_e('Themes are installed safely into wp-content/themes/ without automatic activation.', 'wpcalibrate-html-bridge'); ?></li>
                            <li><strong><?php esc_html_e('Ideal For:', 'wpcalibrate-html-bridge'); ?></strong> <?php esc_html_e('Converting an entire static HTML website template into a permanent WordPress theme.', 'wpcalibrate-html-bridge'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Security & Safety Architecture -->
            <div class="wpcalibrate-card">
                <h3 class="wpcalibrate-section-heading">
                    <span class="dashicons dashicons-shield"></span>
                    <?php esc_html_e('Security & Safety Guarantees', 'wpcalibrate-html-bridge'); ?>
                </h3>
                <div class="wpcalibrate-security-grid">
                    <div class="security-feature-item">
                        <strong><?php esc_html_e('Zero Untrusted Execution', 'wpcalibrate-html-bridge'); ?></strong>
                        <p><?php esc_html_e('Static archives are never executed on the server. Server scripts (.php, .phtml, .cgi, .pl, .py, .sh, .bash) and config files (.htaccess, web.config) are strictly blocked.', 'wpcalibrate-html-bridge'); ?></p>
                    </div>
                    <div class="security-feature-item">
                        <strong><?php esc_html_e('Path Traversal & Boundary Guards', 'wpcalibrate-html-bridge'); ?></strong>
                        <p><?php esc_html_e('Paths containing directory traversal (../), null-byte sequences, or absolute references are rejected prior to extraction.', 'wpcalibrate-html-bridge'); ?></p>
                    </div>
                    <div class="security-feature-item">
                        <strong><?php esc_html_e('Isolated Working Storage', 'wpcalibrate-html-bridge'); ?></strong>
                        <p><?php esc_html_e('Temporary extraction directories are sandboxed under wp-content/uploads/wpcalibrate-html-bridge/ with protective index.php and .htaccess execution locks.', 'wpcalibrate-html-bridge'); ?></p>
                    </div>
                    <div class="security-feature-item">
                        <strong><?php esc_html_e('Distributed Heartbeat Locks', 'wpcalibrate-html-bridge'); ?></strong>
                        <p><?php esc_html_e('Resumable batches utilize atomic distributed locks with automatic TTL expiration to prevent simultaneous worker conflicts.', 'wpcalibrate-html-bridge'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
