<?php
/**
 * Left / Right binary team list
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/genealogy-helper.php';
require_once dirname(__DIR__) . '/includes/team-repository.php';

$legParam = strtolower((string) ($_GET['leg'] ?? 'left'));
$leg = $legParam === 'right' ? 'right' : 'left';
$legLabel = $leg === 'right' ? 'Right Team' : 'Left Team';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = $leg === 'right' ? 'right_team' : 'left_team';

arowin_prepare_page([
    'title'       => $legLabel . ' — Arowin Trading Portal',
    'title_short' => $legLabel,
    'description' => 'Binary ' . strtolower($legLabel) . ' members under your node.',
    'canonical'   => $leg === 'right' ? '/dashboard/team/right' : '/dashboard/team/left',
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
$team = arowin_team_page_data((string) $user['username'], $leg);

$q = trim((string) ($_GET['q'] ?? ''));
$members = $team['members'];
if ($q !== '') {
    $qLower = mb_strtolower($q);
    $members = array_values(array_filter($members, static function (array $m) use ($qLower): bool {
        $hay = mb_strtolower(
            ($m['username'] ?? '') . ' ' .
            ($m['full_name'] ?? '') . ' ' .
            ($m['phone'] ?? '') . ' ' .
            ($m['sponsor_id'] ?? '')
        );
        return str_contains($hay, $qLower);
    }));
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

$otherLegUrl = $leg === 'right' ? url('left-team') : url('right-team');
$otherLegLabel = $leg === 'right' ? 'Left Team' : 'Right Team';
$genealogyUrl = url('genealogy');
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
    <?php require dirname(__DIR__) . '/includes/pwa-meta.php'; ?>
    <link
      href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="<?= e($cssHref) ?>" />
  </head>
  <body>
    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="main" id="main">
      <?php require __DIR__ . '/navbar.php'; ?>

      <div class="content">
        <div class="team-page-head">
          <div>
            <p class="team-eyebrow"><?= $leg === 'right' ? 'Right leg' : 'Left leg' ?> · Binary downline</p>
            <h1 class="team-title"><?= e($legLabel) ?></h1>
            <p class="team-sub">Members placed under your <?= e(strtolower($legLabel)) ?> in the binary tree.</p>
          </div>
          <div class="team-head-actions">
            <a class="team-link-btn" href="<?= e($otherLegUrl) ?>"><?= e($otherLegLabel) ?></a>
            <a class="team-link-btn primary" href="<?= e($genealogyUrl) ?>">Genealogy</a>
          </div>
        </div>

        <div class="team-stats">
          <div class="team-stat">
            <span class="team-stat-label">Total</span>
            <strong class="team-stat-value"><?= (int) $team['total'] ?></strong>
          </div>
          <div class="team-stat">
            <span class="team-stat-label">Active</span>
            <strong class="team-stat-value is-ok"><?= (int) $team['active'] ?></strong>
          </div>
          <div class="team-stat">
            <span class="team-stat-label">Pending</span>
            <strong class="team-stat-value is-warn"><?= (int) $team['pending'] ?></strong>
          </div>
          <div class="team-stat">
            <span class="team-stat-label">Showing</span>
            <strong class="team-stat-value"><?= count($members) ?></strong>
          </div>
        </div>

        <form class="team-search" method="get" action="">
          <input type="hidden" name="leg" value="<?= e($leg) ?>">
          <input
            type="search"
            name="q"
            value="<?= e($q) ?>"
            placeholder="Search ID, name, mobile, sponsor…"
            autocomplete="off"
          >
          <button type="submit">Search</button>
          <?php if ($q !== ''): ?>
            <a class="team-clear" href="<?= e($leg === 'right' ? url('right-team') : url('left-team')) ?>">Clear</a>
          <?php endif; ?>
        </form>

        <div class="team-table-wrap">
          <?php if ($members === []): ?>
            <div class="team-empty">
              <p><?= $q !== '' ? 'No members match your search.' : 'No members on this leg yet.' ?></p>
              <a href="<?= e($genealogyUrl) ?>">Open genealogy tree</a>
            </div>
          <?php else: ?>
            <table class="team-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Member</th>
                  <th>Level</th>
                  <th>Sponsor</th>
                  <th>Parent</th>
                  <th>Package</th>
                  <th>Status</th>
                  <th>Joined</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($members as $i => $m): ?>
                  <?php
                    $name = (string) $m['full_name'];
                    $parts = preg_split('/\s+/', trim($name)) ?: [];
                    $ini = '';
                    foreach (array_slice($parts, 0, 2) as $p) {
                        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
                    }
                    if ($ini === '') {
                        $ini = 'AR';
                    }
                    $photo = arowin_profile_photo_url(
                        isset($m['profile_photo']) && $m['profile_photo'] !== null
                            ? (string) $m['profile_photo']
                            : null
                    );
                    $joined = '';
                    if (!empty($m['created_at'])) {
                        try {
                            $joined = (new DateTimeImmutable((string) $m['created_at']))->format('d M Y');
                        } catch (Throwable $e) {
                            $joined = (string) $m['created_at'];
                        }
                    }
                    $place = (string) ($m['binary_placement'] ?? '');
                  ?>
                  <tr>
                    <td class="muted"><?= $i + 1 ?></td>
                    <td>
                      <div class="team-member">
                        <div class="team-avatar">
                          <?php if ($photo): ?>
                            <img src="<?= e($photo) ?>" alt="">
                          <?php else: ?>
                            <?= e($ini) ?>
                          <?php endif; ?>
                        </div>
                        <div>
                          <div class="team-name"><?= e($name) ?></div>
                          <div class="team-id"><?= e((string) $m['username']) ?></div>
                          <?php if (!empty($m['phone'])): ?>
                            <div class="team-phone"><?= e((string) $m['phone']) ?></div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                    <td><span class="team-pill"><?= (int) $m['level'] ?></span></td>
                    <td class="mono"><?= e((string) ($m['sponsor_id'] ?: '—')) ?></td>
                    <td class="mono">
                      <?= e((string) ($m['binary_parent_id'] ?: '—')) ?>
                      <?php if ($place !== ''): ?>
                        <span class="team-leg-tag"><?= e(ucfirst($place)) ?></span>
                      <?php endif; ?>
                    </td>
                    <td><?= e($m['node_package'] !== '' ? '$' . $m['node_package'] : '—') ?></td>
                    <td>
                      <span class="team-status<?= ($m['status'] ?? '') === 'active' ? ' is-active' : ' is-pending' ?>">
                        <?= ($m['status'] ?? '') === 'active' ? 'Active' : 'Pending' ?>
                      </span>
                    </td>
                    <td class="muted"><?= e($joined !== '' ? $joined : '—') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>
    <script src="<?= e($jsHref) ?>"></script>
    <?php require dirname(__DIR__) . '/includes/pwa-register.php'; ?>
  </body>
</html>
