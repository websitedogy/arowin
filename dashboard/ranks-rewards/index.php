<?php
/**
 * Ranks & Rewards — binary rank ladder (L/R starters)
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/ranks-repository.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'ranks_rewards';

arowin_prepare_page([
    'title'       => 'Ranks & Achievers — Arowin Trading Portal',
    'title_short' => 'Ranks & Achievers',
    'description' => 'Binary rank ladder based on left and right starters.',
    'canonical'   => '/dashboard/ranks-rewards',
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
$rank = arowin_rank_progress((string) $user['username']);
$view['rank_label'] = $rank['rank_label'];

$bonusRows = [];
$currentRankKey = (string) ($rank['current']['key'] ?? '');
foreach (arowin_weekly_rank_bonus_table() as $bonus) {
    $bonus['current'] = ($currentRankKey !== '' && $currentRankKey === $bonus['key']);
    $bonusRows[] = $bonus;
}

$cssHref = arowin_abs($BASE, '/dashboard/assets/css/dashboard.css');
$jsHref  = arowin_abs($BASE, '/dashboard/assets/js/dashboard.js');
$cssFile = dirname(__DIR__, 2) . '/dashboard/assets/css/dashboard.css';
$jsFile  = dirname(__DIR__, 2) . '/dashboard/assets/js/dashboard.js';
if (is_file($cssFile)) {
    $cssHref .= '?v=' . filemtime($cssFile);
}
if (is_file($jsFile)) {
    $jsHref .= '?v=' . filemtime($jsFile);
}

$currentName = $rank['rank_label'] !== '' ? $rank['rank_label'] : ($rank['current']['name'] ?? 'Unranked');
$currentColor = $rank['current']['color'] ?? '#64748b';
$nextName = $rank['next']['name'] ?? null;
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
    <link rel="stylesheet" href="<?= e($cssHref) ?>" />
  </head>
  <body>
    <?php require dirname(__DIR__) . '/sidebar.php'; ?>

    <div class="main" id="main">
      <?php require dirname(__DIR__) . '/navbar.php'; ?>

      <div class="content">
        <div class="pkg-head">
          <div>
            <p class="pkg-eyebrow">Ranks &amp; Achievers</p>
            <h1 class="pkg-title">Rank ladder</h1>
            <p class="pkg-sub">First rank: <strong>1 active $50 pack Left : 1 active $50 pack Right</strong> → Starter. After that, only IDs that already have <strong>Starter rank</strong> count (Power IDs and live IDs use the same rule). Raw ID count does not unlock Gold/Silver.</p>
          </div>
        </div>

        <div class="pkg-summary pkg-summary--3">
          <div class="pkg-summary-card is-rank" style="--rank-color: <?= e($currentColor) ?>">
            <span class="pkg-summary-label">Current rank</span>
            <strong class="pkg-summary-value"><?= e($currentName) ?></strong>
            <?php if ($nextName): ?>
              <span class="pkg-summary-meta">Next: <?= e($nextName) ?></span>
            <?php else: ?>
              <span class="pkg-summary-meta">Top rank achieved</span>
            <?php endif; ?>
          </div>
          <div class="pkg-summary-card">
            <span class="pkg-summary-label"><?= ($rank['count_kind'] ?? 'starters') === 'packs' ? 'Left active packs' : 'Left starters' ?></span>
            <strong class="pkg-summary-value"><?= (int) $rank['left'] ?></strong>
            <span class="pkg-summary-meta"><a href="<?= e(url('left-team')) ?>">View left team</a></span>
          </div>
          <div class="pkg-summary-card">
            <span class="pkg-summary-label"><?= ($rank['count_kind'] ?? 'starters') === 'packs' ? 'Right active packs' : 'Right starters' ?></span>
            <strong class="pkg-summary-value"><?= (int) $rank['right'] ?></strong>
            <span class="pkg-summary-meta"><a href="<?= e(url('right-team')) ?>">View right team</a></span>
          </div>
        </div>

        <?php if ($rank['next'] !== null): ?>
        <div class="pkg-next">
          <div class="pkg-next-top">
            <div>
              <h2>Progress to <?= e((string) $rank['next']['name']) ?></h2>
              <p>
                Need
                <strong><?= e(arowin_rank_requirement_label((string) $rank['next']['key'], (int) $rank['next']['left'], (int) $rank['next']['right'])) ?></strong>.
                <?php if ($rank['left_needed'] > 0 || $rank['right_needed'] > 0): ?>
                  Remaining:
                  <?php if ($rank['left_needed'] > 0): ?><?= (int) $rank['left_needed'] ?> left<?php endif; ?>
                  <?php if ($rank['left_needed'] > 0 && $rank['right_needed'] > 0): ?>, <?php endif; ?>
                  <?php if ($rank['right_needed'] > 0): ?><?= (int) $rank['right_needed'] ?> right<?php endif; ?>.
                <?php else: ?>
                  Requirement met — rank will show on refresh.
                <?php endif; ?>
              </p>
            </div>
            <div class="pkg-next-pct"><?= e((string) $rank['progress_pct']) ?>%</div>
          </div>
          <div class="pkg-progress-track" aria-hidden="true">
            <div class="pkg-progress-fill" style="width: <?= min(100, (float) $rank['progress_pct']) ?>%"></div>
          </div>
          <div class="pkg-progress-legs">
            <span>L <?= (int) $rank['left'] ?> / <?= (int) $rank['next']['left'] ?></span>
            <span>R <?= (int) $rank['right'] ?> / <?= (int) $rank['next']['right'] ?></span>
          </div>
        </div>
        <?php endif; ?>

        <h2 class="pkg-section-title">Rank packages</h2>
        <p class="pkg-section-note">Starter = <strong>1 active $50 pack L : 1 active $50 pack R</strong>. Next ranks count those Starter IDs only. Example: $1550 fill can make 7 starters L – 7 starters R → Silver, not Gold.</p>

        <div class="pkg-ladder">
          <?php foreach ($rank['ladder'] as $row): ?>
            <article
              class="pkg-rank-card<?= $row['current'] ? ' is-current' : '' ?><?= $row['achieved'] ? ' is-achieved' : '' ?><?= $row['locked'] ? ' is-locked' : '' ?>"
              style="--rank-color: <?= e($row['color']) ?>"
            >
              <div class="pkg-rank-left">
                <div class="pkg-rank-dot" aria-hidden="true"></div>
                <div>
                  <h3><?= e($row['name']) ?></h3>
                  <p><?= e((string) ($row['need_label'] ?? ((int) $row['left'] . ' starters L – ' . (int) $row['right'] . ' starters R'))) ?></p>
                </div>
              </div>
              <div class="pkg-rank-mid">
                <div class="pkg-rank-bars">
                  <div class="pkg-bar">
                    <span>L</span>
                    <div class="pkg-bar-track"><div class="pkg-bar-fill<?= $row['left_ok'] ? ' ok' : '' ?>" style="width:<?= min(100, $row['left'] > 0 ? (((int) ($row['have_left'] ?? 0)) / $row['left']) * 100 : 0) ?>%"></div></div>
                    <em><?= (int) ($row['have_left'] ?? 0) ?>/<?= (int) $row['left'] ?></em>
                  </div>
                  <div class="pkg-bar">
                    <span>R</span>
                    <div class="pkg-bar-track"><div class="pkg-bar-fill<?= $row['right_ok'] ? ' ok' : '' ?>" style="width:<?= min(100, $row['right'] > 0 ? (((int) ($row['have_right'] ?? 0)) / $row['right']) * 100 : 0) ?>%"></div></div>
                    <em><?= (int) ($row['have_right'] ?? 0) ?>/<?= (int) $row['right'] ?></em>
                  </div>
                </div>
              </div>
              <div class="pkg-rank-status">
                <?php if ($row['current']): ?>
                  <span class="pkg-badge current">Current</span>
                <?php elseif ($row['achieved']): ?>
                  <span class="pkg-badge done">Achieved</span>
                <?php elseif (($rank['current'] === null && $row['key'] === 'starter') || ($rank['next']['key'] ?? '') === $row['key']): ?>
                  <span class="pkg-badge next">Next</span>
                <?php else: ?>
                  <span class="pkg-badge locked">Locked</span>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <h2 class="pkg-section-title">Weekly Rank Bonus Income</h2>
        <p class="pkg-section-note">Weekly Rank Bonus over <strong>52 weeks</strong>. Starts on rank achievement. Each week unlocks to wallet (not daily). Total = weekly × 52. Separate from Daily Yield.</p>

        <div class="wrb-table-wrap">
          <table class="wrb-table">
            <thead>
              <tr>
                <th>Rank</th>
                <th class="is-num">Weekly Income ($)</th>
                <th>Duration (Weeks)</th>
                <th class="is-num">Total Income ($)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bonusRows as $bonus): ?>
                <tr class="<?= !empty($bonus['current']) ? 'is-current' : '' ?>">
                  <td>
                    <span class="wrb-rank-dot" style="background:<?= e((string) $bonus['color']) ?>"></span>
                    <strong class="wrb-rank-name"><?= e((string) $bonus['name']) ?></strong>
                    <?php if (!empty($bonus['current'])): ?>
                      <em class="wrb-you">You</em>
                    <?php endif; ?>
                  </td>
                  <td class="is-num">$<?= e(number_format((float) $bonus['weekly'], 0)) ?></td>
                  <td><?= (int) $bonus['weeks'] ?> Weeks</td>
                  <td class="is-num wrb-total">$<?= e(number_format((float) $bonus['total'], 0)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php require dirname(__DIR__) . '/includes/bottom-nav.php'; ?>
    <script src="<?= e($jsHref) ?>"></script>
    <?php require dirname(__DIR__, 2) . '/includes/pwa-register.php'; ?>
  </body>
</html>
