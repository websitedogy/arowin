<?php
/**
 * Direct Referrals — members who joined under your sponsor ID
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/referrals.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'direct_referrals';

arowin_prepare_page([
    'title'       => 'Direct Referrals — Arowin Trading Portal',
    'title_short' => 'Direct Referrals',
    'description' => 'People who registered with you as their sponsor.',
    'canonical'   => '/dashboard/direct-referrals',
    'robots'      => 'noindex, nofollow',
    'faqs'        => false,
]);

if (!arowin_is_logged_in()) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('login'), true, 303);
    exit;
}

$sessionUid = (string) ($_SESSION['arowin_uid'] ?? '');
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
$q = trim((string) ($_GET['q'] ?? ''));
$rows = arowin_list_direct_referrals((string) $user['username']);

if ($q !== '') {
    $needle = mb_strtolower($q);
    $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
        $hay = mb_strtolower(implode(' ', [
            (string) ($row['username'] ?? ''),
            (string) ($row['full_name'] ?? ''),
            (string) ($row['phone'] ?? ''),
            (string) ($row['email'] ?? ''),
        ]));
        return str_contains($hay, $needle);
    }));
}

$stats = arowin_direct_referral_stats($rows);
$registerLink = function_exists('arowin_join_share_url')
    ? arowin_join_share_url((string) $user['username'])
    : (url('register') . '?sponsor_id=' . rawurlencode((string) $user['username']) . '&for_guest=1');

$baseCss = arowin_abs($BASE, '/dashboard/assets/css/dashboard.css');
$featCss = arowin_abs($BASE, '/dashboard/direct-referrals/assets/referrals.css');
$jsHref  = arowin_abs($BASE, '/dashboard/assets/js/dashboard.js');
$baseCssFile = dirname(__DIR__, 2) . '/dashboard/assets/css/dashboard.css';
$featCssFile = __DIR__ . '/assets/referrals.css';
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

$pageBase = arowin_abs($BASE, '/dashboard/direct-referrals/');
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
        <div class="dr-head">
          <div>
            <p class="dr-eyebrow">Network</p>
            <h1 class="dr-title">Direct Referrals</h1>
            <p class="dr-sub">People who registered with your User ID as sponsor (Left/Right share link). Placement under another ID does not make them that ID’s direct referral.</p>
          </div>
          <div class="dr-count"><?= (int) $stats['total'] ?> referral<?= (int) $stats['total'] === 1 ? '' : 's' ?></div>
        </div>

        <div class="dr-stats">
          <div class="dr-stat"><span>Total</span><strong><?= (int) $stats['total'] ?></strong></div>
          <div class="dr-stat"><span>Left</span><strong><?= (int) ($stats['left'] ?? 0) ?></strong></div>
          <div class="dr-stat"><span>Right</span><strong><?= (int) ($stats['right'] ?? 0) ?></strong></div>
          <div class="dr-stat"><span>Active</span><strong class="is-ok"><?= (int) $stats['active'] ?></strong></div>
          <div class="dr-stat"><span>Pending</span><strong class="is-warn"><?= (int) $stats['pending'] ?></strong></div>
          <div class="dr-stat"><span>Blocked</span><strong class="is-bad"><?= (int) $stats['blocked'] ?></strong></div>
        </div>

        <form class="dr-search" method="get" action="<?= e($pageBase) ?>">
          <input
            type="search"
            name="q"
            value="<?= e($q) ?>"
            placeholder="Search ID, name, phone, or email"
            autocomplete="off"
          />
          <button type="submit">Search</button>
          <?php if ($q !== ''): ?>
            <a class="dr-clear" href="<?= e($pageBase) ?>">Clear</a>
          <?php endif; ?>
        </form>

        <div class="dr-table-wrap">
          <?php if ($rows === []): ?>
            <div class="dr-empty">
              <?php if ($q !== ''): ?>
                <p>No referrals match “<?= e($q) ?>”.</p>
                <a href="<?= e($pageBase) ?>">Clear search</a>
              <?php else: ?>
                <p>No direct referrals yet. Share your invite link to grow your team.</p>
                <a href="<?= e($registerLink) ?>">Open invite link</a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <table class="dr-table">
              <thead>
                <tr>
                  <th>S.No</th>
                  <th>Member ID</th>
                  <th>Name</th>
                  <th>Package</th>
                  <th>Status</th>
                  <th>Joined</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $i => $row): ?>
                  <?php
                    $memberId = (string) ($row['username'] ?? '');
                    $name = trim((string) ($row['full_name'] ?? ''));
                    $phone = trim((string) ($row['phone'] ?? ''));
                    $pkg = trim((string) ($row['purchased_package'] ?? ''));
                    if ($pkg === '') {
                        $pkg = trim((string) ($row['node_package'] ?? ''));
                    }
                    $status = (string) ($row['status'] ?? 'pending');
                    $leg = strtolower((string) ($row['preferred_leg'] ?? $row['binary_placement'] ?? ''));
                    if ($leg !== 'left' && $leg !== 'right') {
                        $leg = '';
                    }
                    $isPower = !empty($row['is_power_id']);
                    $joined = arowin_direct_referral_format_date(isset($row['created_at']) ? (string) $row['created_at'] : null);
                  ?>
                  <tr>
                    <td class="muted"><?= $i + 1 ?></td>
                    <td class="mono">
                      <?= e($memberId) ?>
                      <?php if ($leg !== ''): ?>
                        <span class="dr-leg is-<?= e($leg) ?>"><?= e(ucfirst($leg)) ?></span>
                      <?php endif; ?>
                      <?php if ($isPower): ?>
                        <span class="dr-leg is-power">Power ID</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="dr-member">
                        <span class="name"><?= e($name !== '' ? $name : '—') ?></span>
                        <?php if ($phone !== ''): ?>
                          <span class="phone"><?= e($phone) ?></span>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td class="muted"><?= e($pkg !== '' ? $pkg : '—') ?></td>
                    <td>
                      <span class="dr-status is-<?= e($status) ?>"><?= e(ucfirst($status)) ?></span>
                    </td>
                    <td class="muted"><?= e($joined) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php require dirname(__DIR__) . '/includes/bottom-nav.php'; ?>
    <script src="<?= e($jsHref) ?>"></script>
    <?php require dirname(__DIR__, 2) . '/includes/pwa-register.php'; ?>
  </body>
</html>
