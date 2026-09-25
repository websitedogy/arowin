<?php
/**
 * PPT View — display admin-uploaded presentation (inline, no download)
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'ppt_view';

arowin_prepare_page([
    'title'       => 'PPT View — Arowin Trading Portal',
    'title_short' => 'PPT View',
    'description' => 'Arowin business presentation.',
    'canonical'   => '/dashboard/ppt-view',
    'robots'      => 'noindex, nofollow',
    'faqs'        => false,
]);

$sessionUid = (string) ($_SESSION['arowin_uid'] ?? '');
if ($sessionUid === '' || !arowin_is_logged_in()) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('login'), true, 303);
    exit;
}

$user = arowin_get_user_by_username($sessionUid);
if (!$user || (int) ($user['id'] ?? 0) < 1) {
    arowin_logout_user();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('login'), true, 303);
    exit;
}

$view = arowin_dashboard_view_data($user);

$pptMetaFile = dirname(__DIR__, 2) . '/storage/ppt/current.json';
$pptViewUrl = '';
$pptFileName = '';
$pptExt = '';
if (is_file($pptMetaFile)) {
    $pptMeta = json_decode((string) file_get_contents($pptMetaFile), true);
    if (is_array($pptMeta) && !empty($pptMeta['stored_name'])) {
        $storedName = basename((string) $pptMeta['stored_name']);
        $storedPath = dirname(__DIR__, 2) . '/storage/ppt/' . $storedName;
        if (is_file($storedPath)) {
            $pptViewUrl = arowin_abs($BASE, '/dashboard/ppt-view/download.php');
            $pptFileName = (string) ($pptMeta['original_name'] ?? 'Presentation');
            $pptExt = strtolower(pathinfo($storedName, PATHINFO_EXTENSION));
        }
    }
}

$canEmbed = $pptViewUrl !== '' && $pptExt === 'pdf';

$baseCss = arowin_abs($BASE, '/dashboard/assets/css/dashboard.css');
$featCss = arowin_abs($BASE, '/dashboard/ppt-view/assets/ppt-view.css');
$jsHref  = arowin_abs($BASE, '/dashboard/assets/js/dashboard.js');
$baseCssFile = dirname(__DIR__, 2) . '/dashboard/assets/css/dashboard.css';
$featCssFile = __DIR__ . '/assets/ppt-view.css';
$jsFile = dirname(__DIR__, 2) . '/dashboard/assets/js/dashboard.js';
if (is_file($baseCssFile)) {
    $baseCss .= '?v=' . filemtime($baseCssFile);
}
if (is_file($featCssFile)) {
    $featCss .= '?v=' . filemtime($featCssFile);
}
if (is_file($jsFile)) {
    $jsHref .= '?v=' . filemtime($jsFile);
}
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= e($baseCss) ?>" />
    <link rel="stylesheet" href="<?= e($featCss) ?>" />
  </head>
  <body>
    <?php require dirname(__DIR__) . '/sidebar.php'; ?>
    <div class="main" id="main">
      <?php require dirname(__DIR__) . '/navbar.php'; ?>
      <div class="content">
        <div class="ppt-toolbar">
          <div>
            <p class="ppt-eyebrow">Presentation</p>
            <h1 class="ppt-title"><?= $pptFileName !== '' ? e($pptFileName) : 'PPT View' ?></h1>
          </div>
          <?php if ($canEmbed): ?>
            <div class="ppt-actions">
              <a class="ppt-btn" href="<?= e($pptViewUrl) ?>?download=1" download="<?= e($pptFileName !== '' ? $pptFileName : 'presentation.pdf') ?>">Download</a>
              <button type="button" class="ppt-btn primary" id="pptFullscreen">Fullscreen</button>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($canEmbed): ?>
          <div class="ppt-stage ppt-stage--embed" id="pptStage" tabindex="0">
            <iframe
              class="ppt-frame"
              src="<?= e($pptViewUrl) ?>#toolbar=0&navpanes=0&scrollbar=1"
              title="<?= e($pptFileName !== '' ? $pptFileName : 'Presentation') ?>"
              loading="eager"
            ></iframe>
          </div>
        <?php elseif ($pptViewUrl !== ''): ?>
          <div class="ppt-empty">
            <p class="ppt-empty-title">This file can’t be shown in the browser</p>
            <p class="ppt-empty-body">Ask admin to upload a <strong>PDF</strong> version so you can view it here.</p>
          </div>
        <?php else: ?>
          <div class="ppt-empty">
            <p class="ppt-empty-title">No presentation yet</p>
            <p class="ppt-empty-body">When admin uploads a PDF, it will appear here.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php require dirname(__DIR__) . '/includes/bottom-nav.php'; ?>
    <script src="<?= e($jsHref) ?>"></script>
    <script src="<?= e(arowin_abs($BASE, '/dashboard/ppt-view/assets/ppt-view.js')) ?>?v=<?= is_file(__DIR__ . '/assets/ppt-view.js') ? filemtime(__DIR__ . '/assets/ppt-view.js') : time() ?>"></script>
    <?php require dirname(__DIR__, 2) . '/includes/pwa-register.php'; ?>
  </body>
</html>
