=== WPCalibrate HTML Bridge ===
Contributors: wpcalibrate
Donate link: https://wpcalibrate.com/
Tags: html import, static site, static to wordpress, theme generator, site migration
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 1.0.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safely import static HTML websites into WordPress Pages or convert them into standalone classic WordPress themes.

== Description ==

**WPCalibrate HTML Bridge** is an enterprise-grade migration solution that enables WordPress administrators to upload static HTML/CSS/JavaScript websites and transform them into native WordPress content or standalone WordPress themes.

### Key Features

* **Two Primary Import Modes**:
  1. **WordPress Pages**: Convert static HTML files into native hierarchical WordPress Pages.
  2. **Standalone WordPress Theme**: Generate a complete, independent classic WordPress theme from your uploaded static website design.
* **Two Content Conversion Modes**:
  * **Preserve Design**: Faithfully maintains your original HTML/CSS structure, typography, and visual styling with isolated frontend CSS scoping.
  * **Gutenberg Conversion**: Safely maps semantic HTML elements (headings, paragraphs, lists, quotes, tables, images, code blocks) into native WordPress blocks, with complex markup gracefully preserved as Custom HTML blocks.
* **Rigorous Security Architecture**:
  * Multi-layer archive validation preventing directory traversal (`../`), null-byte attacks, absolute paths, and symbolic links.
  * Strict static file allowlists and automatic quarantine of dangerous server-side executables (`.php`, `.phtml`, `.cgi`, `.pl`, `.py`, `.sh`, `.bash`, server config files).
  * ZIP bomb mitigation with compression ratio inspection and strict resource limits.
* **Asset & Image Migration**:
  * Imports local images into the WordPress Media Library with attachment metadata and alt text preservation.
  * Automatic rewriting of `src`, `srcset`, `<picture>`, and CSS `url()` references.
* **Deterministic URL & Link Rewriting**:
  * Rewrites internal hyperlinks to point to newly created WordPress permalinks while preserving URL fragments, query strings, and protocols like `mailto:`, `tel:`, and `WhatsApp`.
* **Automatic Navigation Detection**:
  * Discovers semantic `<nav>` and menu candidates to construct native WordPress navigation menus.
* **Resumable Batch Processing**:
  * Chunked batch execution with distributed locking to prevent timeouts or duplicate worker execution on large migrations.
* **Safe Re-import & Rollback**:
  * Complete rollback support that cleanly trashes only pages and menus owned by that specific import without touching unrelated site content.

== Installation ==

1. Upload the `wpcalibrate-html-bridge.zip` file through the WordPress admin panel (**Plugins > Add New > Upload Plugin**) or extract it directly into your `wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **WPCalibrate > HTML Bridge** in the WordPress admin menu to start your first import.

== Usage Workflow ==

1. **New Import**: Navigate to **WPCalibrate > HTML Bridge > New Import**.
2. **Upload ZIP**: Select or drop your static site ZIP archive.
3. **Security Scan**: The plugin validates file boundaries, quarantines unsafe files, and checks resource limits.
4. **Analysis & Preview**: Inspect detected pages, assets, forms, external scripts, and warnings.
5. **Configure**: Select your Import Mode (Pages vs. Theme), Conversion Mode (Preserve Design vs. Gutenberg), page titles, slugs, and homepage assignment.
6. **Run Import**: Watch the live, resumable batch progress bar.
7. **Verify & Review**: View links to created WordPress Pages or generated themes with a complete migration report.

== Frequently Asked Questions ==

= Are static HTML forms automatically functional in WordPress? =
No. While visual form presentation is safely preserved, static forms require backend integration with WordPress form handlers or services to process form submissions.

= Can arbitrary HTML always be converted perfectly into Gutenberg blocks? =
No. Gutenberg conversion maps reliable semantic structures (headings, paragraphs, lists, quotes, tables, images, separators, code). Complex nested layouts, script-dependent markup, or ambiguous components fall back cleanly to Custom HTML blocks to prevent layout loss.

= Will generated themes activate automatically? =
No. Generated themes are safely staged and installed into your `wp-content/themes/` directory, but activating them is a separate, deliberate administrator action in **Appearance > Themes**.

= Does this plugin affect other pages or themes on my site? =
In Preserve Design mode, imported styles and scripts are scoped and enqueued strictly on pages associated with that specific import. Unrelated site pages and wp-admin remain completely unaffected.

= How does Rollback work? =
Rollback identifies objects created during that specific import run. It safely moves imported pages to Trash, removes generated menus, and deletes generated themes if they are not active. It never deletes unrelated content.

== Security & Limitations ==

* **Backups**: We recommend creating a WordPress backup before performing large site migrations.
* **JavaScript**: Frontend scripts can be preserved for isolated rendering, but third-party scripts remain the administrator's responsibility.
* **Licensing**: Remote license activation requires provider endpoint configuration. No arbitrary external calls are made.

== Changelog ==

= 1.0.2 =
* Fix: Direct local stylesheet inlining into postmeta for Preserve Design and Gutenberg modes to guarantee CSS rendering across all themes.
* Fix: Add direct wp_head styling fallback in PreserveDesignRenderer.
* Fix: Ergonomic 2-tier card action layout and balanced 2x2 grid in Demo Templates catalog.
* Fix: Refined form detection notice into a positive integration advisory with form handler instructions.
* Tweak: Add GitHub Plugin URI and Primary Branch headers for dashboard updates.

= 1.0.1 =
* Fix: Live preview modal dynamic asset cache-busting using file modification timestamps.
* Fix: Strict modal visibility rules and browser mockup styling.
* Enhancement: Added dedicated "Open in Tab" button for full-screen template viewing.
* Enhancement: Expanded iframe sandbox flags to support interactive forms and script execution.

= 1.0.0 =
* Initial production release.
* Added Pages Import Mode with Preserve Design and Gutenberg Conversion.
* Added Standalone Theme Generator.
* Added multi-tier ZIP security validator and isolated extraction.
* Added Media Library asset importer and link rewriter.
* Added resumable batch processor and import rollback manager.
* Added Overview tab and 4 pre-packaged interactive demo website templates.
