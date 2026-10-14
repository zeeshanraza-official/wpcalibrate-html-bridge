<?php
/**
 * Standalone Canvas Template for WPCalibrate HTML Bridge Preserve Design mode.
 *
 * @package WPCalibrate\HtmlBridge
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class('wpcalibrate-canvas-body'); ?>>
<?php wp_body_open(); ?>

<main id="wpcalibrate-canvas-main" class="wpcalibrate-canvas-container">
    <?php
    while (have_posts()) :
        the_post();
        the_content();
    endwhile;
    ?>
</main>

<?php wp_footer(); ?>
</body>
</html>
