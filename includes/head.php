<?php
/**
 * Dynamic SEO head partial — expects $APP, $SEO, $BASE, $CANONICAL, $OG_IMAGE
 */
declare(strict_types=1);
if (function_exists('arowin_perf_header')) {
    arowin_perf_header();
}
?>
<!DOCTYPE html>
<html lang="<?= e($APP['language'] ?? 'en') ?>">
<head>
  <?php
  $SEO_META = ['json_ld' => true, 'preconnect' => true];
  require __DIR__ . '/seo-meta.php';
  ?>

  <link rel="preload" href="<?= e($CSS_HREF) ?>" as="style">
  <link rel="stylesheet" href="<?= e($CSS_HREF) ?>">
  <link rel="stylesheet" href="<?= e($CSS_NAVBAR_HREF) ?>">
  <link rel="stylesheet" href="<?= e($CSS_FOOTER_HREF) ?>">
  <link rel="stylesheet" href="<?= e($CSS_PAGES_HREF) ?>">
</head>
