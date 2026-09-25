<?php
/**
 * Transaction History — PhonePe-style permanent wallet statement
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/history.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'history';

arowin_prepare_page([
    'title'       => 'History — Arowin Trading Portal',
    'title_short' => 'History',
    'description' => 'Permanent wallet statement: deposits, transfers, withdrawals, packages.',
    'canonical'   => '/dashboard/history',
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
$filters = arowin_history_parse_filters($_GET);
$perPage = 30;
$offset = ($filters['page'] - 1) * $perPage;

$balance = arowin_wallet_balance((string) $user['username']);
$result = arowin_wallet_history_query((string) $user['username'], [
    'type' => $filters['type'],
    'q' => $filters['q'],
    'from' => $filters['from'],
    'to' => $filters['to'],
    'limit' => $perPage,
    'offset' => $offset,
]);

$rows = $result['rows'];
$total = (int) $result['total'];
$credits = (float) $result['credits'];
$debits = (float) $result['debits'];
$totalPages = max(1, (int) ceil($total / $perPage));
if ($filters['page'] > $totalPages) {
    $filters['page'] = $totalPages;
}

$baseCss = arowin_abs($BASE, '/dashboard/assets/css/dashboard.css');
$featCss = arowin_abs($BASE, '/dashboard/history/assets/history.css');
$jsHref  = arowin_abs($BASE, '/dashboard/assets/js/dashboard.js');
$baseCssFile = dirname(__DIR__, 2) . '/dashboard/assets/css/dashboard.css';
$featCssFile = __DIR__ . '/assets/history.css';
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

$historyBase = arowin_abs($BASE, '/dashboard/history/');
$exportUrl = arowin_abs($BASE, '/dashboard/history/export.php') . arowin_history_qs($filters, ['page' => null]);
$typeLabels = arowin_history_type_labels();
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
  <body class="history-page">
    <?php require dirname(__DIR__) . '/sidebar.php'; ?>

    <div class="main" id="main">
      <?php require dirname(__DIR__) . '/navbar.php'; ?>

      <div class="content">
        <div class="hist-top">
          <div class="hist-head">
            <div class="hist-head-text">
              <h1 class="hist-title"><?= $filters['type'] === 'earnings' ? 'Income History' : 'History' ?></h1>
              <?php if ($filters['type'] === 'earnings'): ?>
                <p class="hist-sub">Referral, Matching, Rank Bonus &amp; Daily Yield — amounts credited as income.</p>
              <?php elseif ($filters['type'] === 'sent'): ?>
                <p class="hist-sub">Money you sent to other Arowin IDs.</p>
              <?php endif; ?>
            </div>
            <a class="hist-download" href="<?= e($exportUrl) ?>">CSV</a>
          </div>

          <?php
            $presets = [
              'today' => 'Today',
              'week' => 'This week',
              'last_month' => 'Last month',
            ];
            $monthNames = [
              1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
              5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
              9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
            ];
            $nowY = (int) date('Y');
            $yearOptions = range($nowY, $nowY - 5);
            $selYear = (int) ($filters['year'] ?? 0);
            $selMonth = (int) ($filters['month'] ?? 0);
            $monthLabel = 'Year';
            if ($filters['preset'] === 'today') {
                $monthLabel = 'Today';
            } elseif ($filters['preset'] === 'week') {
                $monthLabel = 'This week';
            } elseif ($filters['preset'] === 'last_month') {
                $monthLabel = 'Last month';
            } elseif ($selYear > 0 && $selMonth > 0) {
                $monthLabel = ($monthNames[$selMonth] ?? '') . ' ' . $selYear;
            } elseif ($selYear > 0) {
                $monthLabel = (string) $selYear;
            }
            $catLabel = $typeLabels[$filters['type']] ?? 'All';
            $periodOn = ($filters['preset'] !== '' && $filters['preset'] !== 'all')
              || $selYear > 0
              || $filters['from'] !== '';
            $pickYear = $selYear > 0 ? $selYear : $nowY;
          ?>

          <div class="hist-menu-bar" id="histMenuBar">
            <div class="hist-menu" data-hist-menu="month">
              <button type="button" class="hist-menu-btn<?= $periodOn ? ' is-on' : '' ?>" aria-expanded="false" aria-haspopup="true">
                <span><?= e($monthLabel) ?></span>
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
              </button>
              <div class="hist-menu-panel hist-menu-panel--period" hidden>
                <?php foreach ($presets as $pkey => $plabel): ?>
                  <a class="hist-menu-item<?= $filters['preset'] === $pkey ? ' is-active' : '' ?>" href="<?= e($historyBase . arowin_history_qs($filters, ['preset' => $pkey, 'year' => '', 'month' => '', 'from' => '', 'to' => '', 'page' => 1])) ?>">
                    <?= e($plabel) ?>
                  </a>
                <?php endforeach; ?>

                <div class="hist-period-block">
                  <div class="hist-period-label">Select year</div>
                  <div class="hist-chip-row">
                    <?php foreach ($yearOptions as $y): ?>
                      <a class="hist-chip<?= $selYear === (int) $y ? ' is-active' : '' ?>" href="<?= e($historyBase . arowin_history_qs($filters, ['preset' => '', 'year' => $y, 'month' => '', 'from' => '', 'to' => '', 'page' => 1])) ?>">
                        <?= (int) $y ?>
                      </a>
                    <?php endforeach; ?>
                  </div>
                </div>

                <div class="hist-period-block">
                  <div class="hist-period-label">Select month<?= $selYear > 0 ? ' · ' . (int) $selYear : '' ?></div>
                  <div class="hist-chip-row hist-chip-row--months">
                    <?php foreach ($monthNames as $mNum => $mName):
                      $monthHref = $historyBase . arowin_history_qs($filters, [
                        'preset' => '',
                        'year' => $pickYear,
                        'month' => $mNum,
                        'from' => '',
                        'to' => '',
                        'page' => 1,
                      ]);
                      $monthActive = $selYear === $pickYear && $selMonth === (int) $mNum;
                    ?>
                      <a class="hist-chip<?= $monthActive ? ' is-active' : '' ?>" href="<?= e($monthHref) ?>">
                        <?= e($mName) ?>
                      </a>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="hist-menu" data-hist-menu="categories">
              <button type="button" class="hist-menu-btn<?= $filters['type'] !== 'all' ? ' is-on' : '' ?>" aria-expanded="false" aria-haspopup="true">
                <span><?= e($catLabel) ?></span>
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
              </button>
              <div class="hist-menu-panel" hidden>
                <?php foreach ($typeLabels as $key => $label): ?>
                  <a class="hist-menu-item<?= $filters['type'] === $key ? ' is-active' : '' ?>" href="<?= e($historyBase . arowin_history_qs($filters, ['type' => $key, 'page' => 1])) ?>">
                    <?= e($label) ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="hist-menu" data-hist-menu="balance">
              <button type="button" class="hist-menu-btn is-on" aria-expanded="false" aria-haspopup="true">
                <span>Balance</span>
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
              </button>
              <div class="hist-menu-panel hist-menu-panel--balance" hidden>
                <div class="hist-bal-row is-in">
                  <span>Income (+)</span>
                  <strong>+$<?= e(arowin_fmt_money($credits)) ?></strong>
                </div>
                <div class="hist-bal-row is-out">
                  <span>Out (−)</span>
                  <strong>−$<?= e(arowin_fmt_money($debits)) ?></strong>
                </div>
                <div class="hist-bal-row is-rem">
                  <span>Remaining balance</span>
                  <strong>$<?= e(arowin_fmt_money($balance)) ?></strong>
                </div>
                <button type="button" class="hist-bal-close" data-hist-close>Close</button>
              </div>
            </div>
          </div>
        </div>

        <div class="hist-list-wrap">
          <?php if ($rows === []): ?>
            <div class="hist-empty">
              <h2>No transactions found</h2>
              <p>Try another filter, or add funds / transfer / buy a package to see history here.</p>
            </div>
          <?php else: ?>
            <ul class="hist-list">
              <?php foreach ($rows as $row): ?>
                <?php
                  $type = (string) ($row['type'] ?? '');
                  $amt = (float) ($row['amount'] ?? 0);
                  $credit = arowin_history_is_credit($type);
                  if ($amt < 0) {
                      $credit = !$credit;
                  }
                  $absAmt = abs($amt);
                  $statusBadge = arowin_history_status_badge($type);
                  $note = trim((string) ($row['note'] ?? ''));
                  $party = trim((string) ($row['counterparty'] ?? ''));
                  $balAfter = (float) ($row['balance_after'] ?? 0);
                  $meta = [];
                  if ($party !== '') {
                      $meta[] = ($credit ? 'From ' : 'To ') . $party;
                  }
                  if ($note !== '') {
                      $meta[] = $note;
                  }
                  $meta[] = 'Txn #' . (string) ($row['id'] ?? '');
                ?>
                <li class="hist-item">
                  <div class="hist-item-icon <?= $credit ? 'is-credit' : 'is-debit' ?>">
                    <?= $credit ? '+' : '−' ?>
                  </div>
                  <div class="hist-item-main">
                    <div class="hist-item-title">
                      <?= e(arowin_history_type_label($type)) ?>
                      <?php if ($statusBadge !== null): ?>
                        <span class="hist-status-badge <?= e($statusBadge['class']) ?>"><?= e($statusBadge['label']) ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="hist-item-meta"><?= e(implode(' · ', $meta)) ?></div>
                    <div class="hist-item-when"><?= e(arowin_history_format_when(isset($row['created_at']) ? (string) $row['created_at'] : null)) ?></div>
                  </div>
                  <div class="hist-item-amt <?= $credit ? 'is-credit' : 'is-debit' ?>">
                    <strong><?= $credit ? '+' : '−' ?>$<?= e(arowin_fmt_money($absAmt)) ?></strong>
                    <span>After $<?= e(arowin_fmt_money($balAfter)) ?></span>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>

            <?php if ($totalPages > 1): ?>
              <div class="hist-pager">
                <?php if ($filters['page'] > 1): ?>
                  <a href="<?= e($historyBase . arowin_history_qs($filters, ['page' => $filters['page'] - 1])) ?>">← Prev</a>
                <?php endif; ?>
                <span>Page <?= (int) $filters['page'] ?> of <?= (int) $totalPages ?></span>
                <?php if ($filters['page'] < $totalPages): ?>
                  <a href="<?= e($historyBase . arowin_history_qs($filters, ['page' => $filters['page'] + 1])) ?>">Next →</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php require dirname(__DIR__) . '/includes/bottom-nav.php'; ?>
    <script src="<?= e($jsHref) ?>"></script>
    <script>
    (function () {
      var bar = document.getElementById('histMenuBar');
      if (!bar) return;

      function closeAll() {
        bar.querySelectorAll('.hist-menu').forEach(function (menu) {
          menu.classList.remove('is-open');
          var btn = menu.querySelector('.hist-menu-btn');
          var panel = menu.querySelector('.hist-menu-panel');
          if (btn) btn.setAttribute('aria-expanded', 'false');
          if (panel) {
            panel.hidden = true;
            panel.setAttribute('hidden', 'hidden');
          }
        });
      }

      function openMenu(menu) {
        if (!menu) return;
        closeAll();
        var btn = menu.querySelector('.hist-menu-btn');
        var panel = menu.querySelector('.hist-menu-panel');
        menu.classList.add('is-open');
        if (btn) btn.setAttribute('aria-expanded', 'true');
        if (panel) {
          panel.hidden = false;
          panel.removeAttribute('hidden');
        }
      }

      bar.addEventListener('click', function (e) {
        var closeBtn = e.target.closest('[data-hist-close]');
        if (closeBtn) {
          e.preventDefault();
          e.stopPropagation();
          closeAll();
          return;
        }

        var btn = e.target.closest('.hist-menu-btn');
        if (!btn || !bar.contains(btn)) return;
        e.preventDefault();
        e.stopPropagation();
        var menu = btn.closest('.hist-menu');
        if (menu && menu.classList.contains('is-open')) {
          closeAll();
        } else {
          openMenu(menu);
        }
      });

      document.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('#histMenuBar')) return;
        closeAll();
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAll();
      });
    })();
    </script>
    <?php require dirname(__DIR__, 2) . '/includes/pwa-register.php'; ?>
  </body>
</html>
