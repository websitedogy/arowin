<?php
/**
 * Shared PWA meta tags (marketing + dashboard)
 */
declare(strict_types=1);

$AROWIN_BASE_PATH = (string) (parse_url($BASE, PHP_URL_PATH) ?: '');
?>
  <meta name="theme-color" content="<?= e($APP['theme_color'] ?? '#000000') ?>">
  <meta name="arowin-base" content="<?= e($AROWIN_BASE_PATH) ?>">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="<?= e($APP['short_name'] ?? 'AROWIN') ?>">
  <meta name="application-name" content="<?= e($APP['short_name'] ?? 'AROWIN') ?>">
  <meta name="format-detection" content="telephone=no">
  <link rel="manifest" href="<?= e(arowin_abs($BASE, '/manifest.json')) ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= e(img('apple-touch-icon.png')) ?>">
  <link rel="icon" type="image/png" sizes="192x192" href="<?= e(img('favicon-192x192.png')) ?>">
  <link rel="icon" type="image/png" sizes="512x512" href="<?= e(img('favicon-512x512.png')) ?>">
  <link rel="apple-touch-startup-image" href="<?= e(img('favicon-512x512.png')) ?>">
