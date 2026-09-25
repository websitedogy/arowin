<?php
/**
 * Arowin Crypto Dashboard — entry
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'dashboard';

arowin_prepare_page([
    'title'       => 'Dashboard — Arowin Trading Portal',
    'title_short' => 'Dashboard',
    'description' => 'Arowin operator crypto dashboard.',
    'canonical'   => '/dashboard',
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

$user = null;
try {
    $user = arowin_get_user_by_username($sessionUid);
} catch (Throwable $e) {
    error_log('Dashboard load error: ' . $e->getMessage());
}

if (!$user || (int) ($user['id'] ?? 0) < 1) {
    arowin_logout_user();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('login'), true, 303);
    exit;
}

$view = arowin_dashboard_view_data($user);
$recentTransactions = arowin_dashboard_recent_transactions((string) ($user['username'] ?? ''), 6);

$flash = null;
if (!empty($_SESSION['arowin_flash'])) {
    $flash = (string) $_SESSION['arowin_flash'];
    unset($_SESSION['arowin_flash']);
}

$cssHref = arowin_abs($BASE, '/dashboard/assets/css/dashboard.css');
$jsHref  = arowin_abs($BASE, '/dashboard/assets/js/dashboard.js');
$cssFile = dirname(__DIR__) . '/dashboard/assets/css/dashboard.css';
$jsFile  = dirname(__DIR__) . '/dashboard/assets/js/dashboard.js';
if (is_file($cssFile)) {
    $cssHref .= '?v=' . filemtime($cssFile);
}
if (is_file($jsFile)) {
    $jsHref .= '?v=' . filemtime($jsFile);
}
?>
<?php
$offlineSnapshot = [
    'username'         => (string) ($user['username'] ?? ''),
    'full_name'        => (string) ($view['full_name'] ?? ''),
    'rank_label'       => (string) ($view['rank_label'] ?? ''),
    'status_label'     => (string) ($view['status_label'] ?? ''),
    'wallet'           => (float) ($view['wallet'] ?? 0),
    'referral_income'  => (float) ($view['referral_income'] ?? 0),
    'matching_income'  => (float) ($view['matching_income'] ?? 0),
    'left_team'        => (int) ($view['left_team'] ?? 0),
    'right_team'       => (int) ($view['right_team'] ?? 0),
    'saved_at'         => gmdate('c'),
];
?>
<!doctype html>
<html lang="<?= e($APP['language'] ?? 'en') ?>">
  <head>
    <?php require dirname(__DIR__) . '/includes/dashboard-seo-head.php'; ?>
    <link rel="preload" href="<?= e($cssHref) ?>" as="style" />
    <link rel="stylesheet" href="<?= e($cssHref) ?>" />
  </head>
  <body>
    <?php if (function_exists('arowin_flush_response')) { arowin_flush_response(); } ?>
    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="main" id="main">
      <?php require __DIR__ . '/navbar.php'; ?>
      <?php if (!empty($flash)): ?>
      <div class="content" style="padding-bottom:0">
        <div class="dashboard-flash" role="status" style="margin:16px 20px 0;padding:12px 16px;border-radius:10px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.35);color:#86efac;font-size:13px;line-height:1.5;">
          <?= e($flash) ?>
        </div>
      </div>
      <?php endif; ?>
      <?php require __DIR__ . '/includes/home-content.php'; ?>
    </div>

    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>

    <script type="application/json" id="arowin-offline-snapshot"><?= json_encode(
        $offlineSnapshot,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
    ) ?></script>
    <script>
      window.AROWIN_INCOME_HISTORY_API = <?= json_encode(
          arowin_abs($BASE, '/dashboard/api/income-history.php'),
          JSON_UNESCAPED_SLASHES
      ) ?>;
    </script>
    <script src="<?= e($jsHref) ?>"></script>
    <?php require dirname(__DIR__) . '/includes/pwa-register.php'; ?>
  </body>
</html>
