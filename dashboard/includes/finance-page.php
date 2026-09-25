<?php
/**
 * Shared shell for Wallet / Deposit / Withdrawal / History pages
 *
 * Expected vars:
 * - $FINANCE_TITLE, $FINANCE_EYEBROW, $FINANCE_SUB, $FINANCE_BODY (HTML string)
 * - $DASHBOARD_PAGE, $user, $view, $SEO, $CANONICAL, $BASE, $cssHref, $jsHref
 */
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= e($SEO['title']) ?></title>
    <meta name="robots" content="<?= e($SEO['robots']) ?>">
    <link rel="canonical" href="<?= e($CANONICAL) ?>">
    <link rel="icon" href="<?= e(arowin_abs($BASE, '/favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/svg+xml" href="<?= e(img('favicon.svg')) ?>">
    <?php require dirname(__DIR__, 2) . '/includes/pwa-meta.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php $finFont = 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap'; ?>
    <link rel="preload" as="style" href="<?= e($finFont) ?>" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="<?= e($finFont) ?>"></noscript>
    <link rel="preload" href="<?= e($cssHref) ?>" as="style">
    <link rel="stylesheet" href="<?= e($cssHref) ?>" />
  </head>
  <body>
    <?php require dirname(__DIR__) . '/sidebar.php'; ?>

    <div class="main" id="main">
      <?php require dirname(__DIR__) . '/navbar.php'; ?>

      <div class="content">
        <div class="pkg-head">
          <div>
            <p class="pkg-eyebrow"><?= e($FINANCE_EYEBROW) ?></p>
            <h1 class="pkg-title"><?= e($FINANCE_TITLE) ?></h1>
            <p class="pkg-sub"><?= e($FINANCE_SUB) ?></p>
          </div>
        </div>

        <div class="finance-panel">
          <?= $FINANCE_BODY ?>
        </div>
      </div>
    </div>

    <?php require __DIR__ . '/bottom-nav.php'; ?>
    <script src="<?= e($jsHref) ?>"></script>
    <?php require dirname(__DIR__, 2) . '/includes/pwa-register.php'; ?>
  </body>
</html>
