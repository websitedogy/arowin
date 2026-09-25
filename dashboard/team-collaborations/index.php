<?php
/**
 * Team Collaborations — All / Left / Right team table + login-as
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/collaborations.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'team_collaborations';

$tab = arowin_collab_normalize_tab((string) ($_GET['tab'] ?? 'all'));

arowin_prepare_page([
    'title'       => 'Power IDs — Arowin Trading Portal',
    'title_short' => 'Power IDs',
              'description' => 'View Power IDs created by your $150+ package and open their dashboards.',
    'canonical'   => '/dashboard/team-collaborations',
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

// Back from member tab → always show YOUR team on this page
arowin_collab_ensure_home_session();

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

if (!function_exists('arowin_user_can_view_power_ids') || !arowin_user_can_view_power_ids($user)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('direct-referrals'), true, 303);
    exit;
}

$view = arowin_dashboard_view_data($user);
$membersAll = arowin_collab_list_members((string) $user['username'], $tab);
$packCode = function_exists('arowin_normalize_package')
    ? arowin_normalize_package((string) ($user['purchased_package'] ?? $user['node_package'] ?? ''))
    : trim((string) ($user['purchased_package'] ?? $user['node_package'] ?? ''));
$unitPlan = ($packCode !== '' && function_exists('arowin_package_unit_plan'))
    ? arowin_package_unit_plan($packCode)
    : null;
$expectedPowerIds = is_array($unitPlan)
    ? ((int) ($unitPlan['left'] ?? 0) + (int) ($unitPlan['right'] ?? 0))
    : 0;
$csrf = arowin_csrf_token();
$flashErr = (string) ($_SESSION['arowin_collab_flash_err'] ?? '');
unset($_SESSION['arowin_collab_flash_err']);
$flashOk = (string) ($_SESSION['arowin_collab_flash_ok'] ?? '');
unset($_SESSION['arowin_collab_flash_ok']);
$powerTotals = arowin_collab_power_totals((string) $user['username']);
$sweepWallet = round((float) ($powerTotals['wallet'] ?? 0), 2);
$sweepBtnLabel = $sweepWallet >= 0.01
    ? '$' . number_format($sweepWallet, 2)
    : '';

$perPage = 15;
$totalMembers = count($membersAll);
$totalPages = max(1, (int) ceil($totalMembers / $perPage));
$page = max(1, (int) ($_GET['page'] ?? 1));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$members = array_slice($membersAll, $offset, $perPage);
$members = arowin_collab_attach_member_stats($members);
$from = $totalMembers === 0 ? 0 : $offset + 1;
$to = $offset + count($members);

$baseCss = arowin_abs($BASE, '/dashboard/assets/css/dashboard.css');
$featCss = arowin_abs($BASE, '/dashboard/team-collaborations/assets/collaborations.css');
$jsHref  = arowin_abs($BASE, '/dashboard/assets/js/dashboard.js');
$baseCssFile = dirname(__DIR__, 2) . '/dashboard/assets/css/dashboard.css';
$featCssFile = __DIR__ . '/assets/collaborations.css';
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

$loginAction = arowin_url_with_account(arowin_abs($BASE, '/dashboard/team-collaborations/login.php'));
$sweepAction = arowin_url_with_account(arowin_abs($BASE, '/dashboard/team-collaborations/sweep.php'));
$collabUrl = static function (string $t, int $p = 1) use ($BASE): string {
    $q = [];
    if ($t !== 'all') {
        $q['tab'] = $t;
    }
    if ($p > 1) {
        $q['page'] = (string) $p;
    }
    $path = '/dashboard/team-collaborations';
    if ($q !== []) {
        $path .= '?' . http_build_query($q);
    }
    return arowin_url_with_account(arowin_abs($BASE, $path));
};
$tabUrl = static function (string $t) use ($collabUrl): string {
    return $collabUrl($t, 1);
};
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
    <link
      href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="<?= e($baseCss) ?>" />
    <link rel="stylesheet" href="<?= e($featCss) ?>" />
  </head>
  <body>
    <?php require dirname(__DIR__) . '/sidebar.php'; ?>

    <div class="main" id="main">
      <?php require dirname(__DIR__) . '/navbar.php'; ?>

      <div class="content">
        <div class="collab-head">
          <div>
            <p class="collab-eyebrow">$150+ package</p>
            <h1 class="collab-title">Power IDs</h1>
            <p class="collab-sub">Only auto Power IDs from your $150+ pack (Left / Right). Not the full team, not live referrals, not a $50 pack. Login opens that Power ID — use “Back to my ID” to return.</p>
          </div>
          <div class="collab-head-side">
            <div class="collab-total-box">
              <div class="collab-total-k">Team <?= (int) ($powerTotals['count'] ?? 0) ?></div>
              <div class="collab-total-v">$<?= e(number_format((float) ($powerTotals['earnings'] ?? 0), 2)) ?></div>
              <div class="collab-total-s">Remaining · all IDs</div>
            </div>
            <form
              method="post"
              action="<?= e($sweepAction) ?>"
              class="collab-sweep-form"
              onsubmit="return confirm('Are you sure? Send all Power ID amount to the master wallet?');"
            >
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="tab" value="<?= e($tab) ?>">
              <button
                type="submit"
                class="collab-sweep-btn"
                <?= $sweepWallet < 0.01 ? 'disabled' : '' ?>
              >
                <span>Transfer all</span>
                <?php if ($sweepBtnLabel !== ''): ?>
                  <strong><?= e($sweepBtnLabel) ?></strong>
                <?php endif; ?>
              </button>
            </form>
          </div>
        </div>

        <?php if ($flashOk !== ''): ?>
          <div class="collab-flash ok" role="status"><?= e($flashOk) ?></div>
        <?php endif; ?>
        <?php if ($flashErr !== ''): ?>
          <div class="collab-flash err" role="alert"><?= e($flashErr) ?></div>
        <?php endif; ?>

        <div class="collab-tabs" role="tablist" aria-label="Team leg filter">
          <a
            class="collab-tab<?= $tab === 'all' ? ' is-active' : '' ?>"
            href="<?= e($tabUrl('all')) ?>"
            role="tab"
            aria-selected="<?= $tab === 'all' ? 'true' : 'false' ?>"
          >All</a>
          <a
            class="collab-tab collab-tab-left<?= $tab === 'left' ? ' is-active' : '' ?>"
            href="<?= e($tabUrl('left')) ?>"
            role="tab"
            aria-selected="<?= $tab === 'left' ? 'true' : 'false' ?>"
          >Left</a>
          <a
            class="collab-tab collab-tab-right<?= $tab === 'right' ? ' is-active' : '' ?>"
            href="<?= e($tabUrl('right')) ?>"
            role="tab"
            aria-selected="<?= $tab === 'right' ? 'true' : 'false' ?>"
          >Right</a>
        </div>

        <div class="collab-table-wrap">
          <?php if ($members === []): ?>
            <div class="collab-empty">
              <?php if ($expectedPowerIds < 1): ?>
                <p>No Power IDs for <?= e((string) $user['username']) ?>. This ID’s pack is $<?= e($packCode !== '' ? $packCode : '0') ?> — a $50 pack does not auto-create Power IDs. Left/Right Team may still show live referrals; those are not added here. Buy $150+ to get auto Left/Right Power IDs in this list.</p>
              <?php else: ?>
                <p>No Power IDs on this <?= $tab === 'all' ? 'list' : $tab . ' leg' ?> yet. Your $<?= e($packCode) ?> pack should create <?= (int) $expectedPowerIds ?> auto Power IDs (Left <?= (int) ($unitPlan['left'] ?? 0) ?> / Right <?= (int) ($unitPlan['right'] ?? 0) ?>).</p>
              <?php endif; ?>
              <a href="<?= e(url('packages')) ?>">View packages</a>
            </div>
          <?php else: ?>
            <table class="collab-table">
              <thead>
                <tr>
                  <th>S.No</th>
                  <th>Sponsor</th>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Created Date</th>
                  <th>Rank</th>
                  <th>Incomes</th>
                  <th>Login</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($members as $i => $m): ?>
                  <?php
                    $memberId = (string) ($m['username'] ?? '');
                    $sponsorId = (string) ($m['sponsor_id'] ?? '');
                    $sponsorName = trim((string) ($m['sponsor_name'] ?? ''));
                    $name = (string) ($m['full_name'] ?? '');
                    $leg = strtolower((string) ($m['leg'] ?? ''));
                    $created = arowin_collab_format_date(isset($m['created_at']) ? (string) $m['created_at'] : null);
                    $serial = $offset + $i + 1;
                    $rankLabel = (string) ($m['rank_label'] ?? 'Unranked');
                    $referral = (float) ($m['referral_income'] ?? 0);
                    $matching = (float) ($m['matching_income'] ?? 0);
                    $rankBonus = (float) ($m['rank_bonus'] ?? 0);
                    $dailyYield = (float) ($m['daily_yield'] ?? 0);
                    $totalIncome = (float) ($m['total_income'] ?? ($referral + $matching + $rankBonus + $dailyYield));
                  ?>
                  <tr>
                    <td class="muted"><?= (int) $serial ?></td>
                    <td>
                      <?php if ($sponsorId !== ''): ?>
                        <div class="collab-sponsor">
                          <span class="mono"><?= e($sponsorId) ?></span>
                          <?php if ($sponsorName !== ''): ?>
                            <span class="collab-sponsor-name"><?= e($sponsorName) ?></span>
                          <?php endif; ?>
                        </div>
                      <?php else: ?>
                        <span class="muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="mono">
                      <span class="collab-id-wrap">
                        <?= e($memberId) ?>
                        <?php if ($leg === 'left' || $leg === 'right'): ?>
                          <span class="collab-leg is-<?= e($leg) ?>"><?= e(ucfirst($leg)) ?></span>
                        <?php endif; ?>
                      </span>
                    </td>
                    <td><?= e($name !== '' ? $name : '—') ?></td>
                    <td class="muted"><?= e($created) ?></td>
                    <td>
                      <span class="collab-rank"><?= e($rankLabel) ?></span>
                    </td>
                    <td>
                      <div class="collab-incomes" title="Member income breakdown">
                        <div><span>Referral</span><strong><?= e(arowin_fmt_usdt($referral)) ?></strong></div>
                        <div><span>Matching</span><strong><?= e(arowin_fmt_usdt($matching)) ?></strong></div>
                        <div><span>Rank bonus</span><strong><?= e(arowin_fmt_usdt($rankBonus)) ?></strong></div>
                        <div><span>Daily yield</span><strong><?= e(arowin_fmt_usdt($dailyYield)) ?></strong></div>
                        <div class="is-total"><span>Total</span><strong><?= e(arowin_fmt_usdt($totalIncome)) ?></strong></div>
                      </div>
                    </td>
                    <td>
                      <form
                        method="post"
                        action="<?= e($loginAction) ?>"
                        class="collab-login-form"
                      >
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="target_id" value="<?= e($memberId) ?>">
                        <input type="hidden" name="tab" value="<?= e($tab) ?>">
                        <button type="submit" class="collab-login-btn" title="Open this member’s dashboard">Login</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
              <nav class="collab-pager" aria-label="Power IDs pages">
                <p class="collab-pager-meta">
                  Showing <?= (int) $from ?>–<?= (int) $to ?> of <?= (int) $totalMembers ?>
                </p>
                <div class="collab-pager-links">
                  <?php if ($page > 1): ?>
                    <a class="collab-page-btn" href="<?= e($collabUrl($tab, $page - 1)) ?>">Prev</a>
                  <?php else: ?>
                    <span class="collab-page-btn is-disabled">Prev</span>
                  <?php endif; ?>

                  <?php
                    $window = 2;
                    $start = max(1, $page - $window);
                    $end = min($totalPages, $page + $window);
                    if ($start > 1):
                  ?>
                    <a class="collab-page-num<?= $page === 1 ? ' is-active' : '' ?>" href="<?= e($collabUrl($tab, 1)) ?>">1</a>
                    <?php if ($start > 2): ?><span class="collab-page-ellipsis">…</span><?php endif; ?>
                  <?php endif; ?>

                  <?php for ($p = $start; $p <= $end; $p++): ?>
                    <?php if ($p === $page): ?>
                      <span class="collab-page-num is-active" aria-current="page"><?= (int) $p ?></span>
                    <?php else: ?>
                      <a class="collab-page-num" href="<?= e($collabUrl($tab, $p)) ?>"><?= (int) $p ?></a>
                    <?php endif; ?>
                  <?php endfor; ?>

                  <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?><span class="collab-page-ellipsis">…</span><?php endif; ?>
                    <a class="collab-page-num<?= $page === $totalPages ? ' is-active' : '' ?>" href="<?= e($collabUrl($tab, $totalPages)) ?>"><?= (int) $totalPages ?></a>
                  <?php endif; ?>

                  <?php if ($page < $totalPages): ?>
                    <a class="collab-page-btn" href="<?= e($collabUrl($tab, $page + 1)) ?>">Next</a>
                  <?php else: ?>
                    <span class="collab-page-btn is-disabled">Next</span>
                  <?php endif; ?>
                </div>
              </nav>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php require dirname(__DIR__) . '/includes/bottom-nav.php'; ?>
    <script src="<?= e($jsHref) ?>"></script>
    <?php require dirname(__DIR__, 2) . '/includes/pwa-register.php'; ?>
  </body>
</html>
