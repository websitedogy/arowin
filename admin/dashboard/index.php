<?php
/**
 * Admin dashboard home — /admin/dashboard
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/auth-check.php';

$ADMIN_PAGE = 'dashboard';
$ADMIN_TITLE = 'Dashboard';
$ADMIN_SUBTITLE = 'Operations overview';

$metrics = [
    'users_all' => 0,
    'users_pending' => 0,
    'users_active' => 0,
    'users_blocked' => 0,
    'today_earners' => 0,
    'today_earners_amount' => 0.0,
    'today_withdrawals' => 0.0,
    'today_withdrawals_count' => 0,
    'today_deposits' => 0.0,
    'today_deposits_count' => 0,
    'today_transfers' => 0.0,
    'today_transfers_count' => 0,
    'today_turnover' => 0.0,
    'total_distribute' => 0.0,
    'total_admin' => 0.0,
    'total_tds' => 0.0,
    'company_surplus' => 0.0,
    'today_failed_payments' => 0,
    'support_open' => 0,
    'guests_all' => 0,
    'today_rank_achievers' => 0,
    'queued_withdrawals' => 0,
    'queued_withdrawals_amount' => 0.0,
    'queued_withdrawals_users' => 0,
];
$purchaseCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];
$recentPurchases = [];
$queuedWd = ['count' => 0, 'amount' => 0.0, 'users' => 0];

try {
    $metrics = arowin_admin_dashboard_metrics();
    $purchaseCounts = arowin_purchase_status_counts();
    $recentPurchases = arowin_list_purchases('approved', 5);
    require_once dirname(__DIR__, 2) . '/includes/withdrawal-repository.php';
    $queuedWd = arowin_withdrawal_queued_summary();
    $metrics['queued_withdrawals'] = (int) ($queuedWd['count'] ?? 0);
    $metrics['queued_withdrawals_amount'] = (float) ($queuedWd['amount'] ?? 0);
    $metrics['queued_withdrawals_users'] = (int) ($queuedWd['users'] ?? 0);
} catch (Throwable $e) {
    error_log('Admin dashboard load error: ' . $e->getMessage());
}

$usersBase = admin_url('/dashboard/users');
$purchasesBase = admin_url('/dashboard/purchases');

$boxes = [
    [
        'label' => 'All users',
        'value' => (string) (int) $metrics['users_all'],
        'hint' => 'Click to view all',
        'href' => $usersBase . '?status=all',
        'class' => '',
        'value_class' => '',
        'icon' => 'users',
    ],
    [
        'label' => 'Pending users',
        'value' => (string) (int) $metrics['users_pending'],
        'hint' => 'Awaiting package',
        'href' => $usersBase . '?status=pending',
        'class' => 'pending',
        'value_class' => 'accent',
        'icon' => 'clock',
    ],
    [
        'label' => 'Active users',
        'value' => (string) (int) $metrics['users_active'],
        'hint' => 'Package purchased',
        'href' => $usersBase . '?status=active',
        'class' => 'approved',
        'value_class' => 'green',
        'icon' => 'check',
    ],
    [
        'label' => 'Blocked users',
        'value' => (string) (int) $metrics['users_blocked'],
        'hint' => 'Inactive accounts',
        'href' => $usersBase . '?status=inactive',
        'class' => 'rejected',
        'value_class' => 'red',
        'icon' => 'block',
    ],
    [
        'label' => 'Today earners',
        'value' => (string) (int) $metrics['today_earners'],
        'hint' => arowin_admin_fmt_money((float) $metrics['today_earners_amount']) . ' paid today',
        'href' => admin_url('/dashboard/earners'),
        'class' => 'approved',
        'value_class' => 'green',
        'icon' => 'earn',
    ],
    [
        'label' => 'Today withdrawals',
        'value' => arowin_admin_fmt_money((float) $metrics['today_withdrawals']),
        'hint' => (int) $metrics['today_withdrawals_count'] . ' transactions',
        'href' => admin_url('/dashboard/withdrawals'),
        'class' => 'pending',
        'value_class' => 'accent',
        'icon' => 'withdraw',
    ],
    [
        'label' => 'Queued withdrawals',
        'value' => (string) (int) ($metrics['queued_withdrawals'] ?? 0),
        'hint' => arowin_admin_fmt_money((float) ($metrics['queued_withdrawals_amount'] ?? 0))
            . ' · ' . (int) ($metrics['queued_withdrawals_users'] ?? 0) . ' users (retry after NP top-up)',
        'href' => admin_url('/dashboard/withdrawals?status=queued'),
        'class' => 'pending',
        'value_class' => 'accent',
        'icon' => 'withdraw',
    ],
    [
        'label' => 'Today deposit',
        'value' => arowin_admin_fmt_money((float) $metrics['today_deposits']),
        'hint' => (int) $metrics['today_deposits_count'] . ' transactions',
        'href' => admin_url('/dashboard/deposits'),
        'class' => 'approved',
        'value_class' => 'green',
        'icon' => 'deposit',
    ],
    [
        'label' => 'Today transfers',
        'value' => arowin_admin_fmt_money((float) $metrics['today_transfers']),
        'hint' => (int) $metrics['today_transfers_count'] . ' transfers out',
        'href' => admin_url('/dashboard/transfers'),
        'class' => '',
        'value_class' => '',
        'icon' => 'transfer',
    ],
    [
        'label' => 'Today company turnover',
        'value' => arowin_admin_fmt_money((float) $metrics['today_turnover']),
        'hint' => 'Approved packages today',
        'href' => admin_url('/dashboard/turnover'),
        'class' => 'pending',
        'value_class' => 'accent',
        'icon' => 'chart',
    ],
    [
        'label' => 'Total distribute amount',
        'value' => arowin_admin_fmt_money((float) $metrics['total_distribute']),
        'hint' => 'All income paid out',
        'href' => admin_url('/dashboard/distribute'),
        'class' => 'approved',
        'value_class' => 'green',
        'icon' => 'distribute',
    ],
    [
        'label' => 'Total admin amount',
        'value' => arowin_admin_fmt_money((float) $metrics['total_admin']),
        'hint' => 'From COMPANY_ADMIN_PERCENT',
        'href' => admin_url('/dashboard/admin-amount'),
        'class' => '',
        'value_class' => '',
        'icon' => 'admin',
    ],
    [
        'label' => 'Total TDS amount',
        'value' => arowin_admin_fmt_money((float) $metrics['total_tds']),
        'hint' => 'From COMPANY_TDS_PERCENT',
        'href' => admin_url('/dashboard/tds'),
        'class' => 'pending',
        'value_class' => 'accent',
        'icon' => 'tds',
    ],
    [
        'label' => 'Company surplus amount',
        'value' => arowin_admin_fmt_money((float) $metrics['company_surplus']),
        'hint' => 'Turnover − distributed',
        'href' => admin_url('/dashboard/surplus'),
        'class' => 'approved',
        'value_class' => 'green',
        'icon' => 'surplus',
    ],
    [
        'label' => 'Today failed payments',
        'value' => (string) (int) $metrics['today_failed_payments'],
        'hint' => 'Rejected purchases today',
        'href' => admin_url('/dashboard/failed-payments'),
        'class' => 'rejected',
        'value_class' => 'red',
        'icon' => 'fail',
    ],
    [
        'label' => 'PPT upload',
        'value' => 'Open',
        'hint' => 'Upload business presentation',
        'href' => admin_url('/dashboard/ppt'),
        'class' => '',
        'value_class' => '',
        'icon' => 'ppt',
    ],
    [
        'label' => 'Support',
        'value' => (string) (int) $metrics['support_open'],
        'hint' => 'All inquiries',
        'href' => admin_url('/dashboard/support'),
        'class' => 'pending',
        'value_class' => 'accent',
        'icon' => 'support',
    ],
    [
        'label' => 'All guest list',
        'value' => (string) (int) $metrics['guests_all'],
        'hint' => 'Guests across members',
        'href' => admin_url('/dashboard/guests'),
        'class' => '',
        'value_class' => '',
        'icon' => 'guest',
    ],
    [
        'label' => 'Today rank achievers',
        'value' => (string) (int) $metrics['today_rank_achievers'],
        'hint' => 'New ranks unlocked today',
        'href' => admin_url('/dashboard/rank-achievers'),
        'class' => 'approved',
        'value_class' => 'green',
        'icon' => 'rank',
    ],
];

// Keep separate from sidebar $icons (layout-start overwrites that name).
$statIcons = [
    'users' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'check' => '<path d="M20 6L9 17l-5-5"/>',
    'block' => '<path d="M18 6L6 18M6 6l12 12"/>',
    'earn' => '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>',
    'withdraw' => '<path d="M12 5v14M5 12l7 7 7-7"/>',
    'deposit' => '<path d="M12 19V5M5 12l7-7 7 7"/>',
    'transfer' => '<path d="M7 7h10M17 7l-3-3M17 7l-3 3M17 17H7M7 17l3-3M7 17l3 3"/>',
    'chart' => '<path d="M4 19V5M4 19h16M8 15l3-4 3 2 4-6"/>',
    'distribute' => '<path d="M12 2v6M12 16v6M4.93 4.93l4.24 4.24M14.83 14.83l4.24 4.24M2 12h6M16 12h6M4.93 19.07l4.24-4.24M14.83 9.17l4.24-4.24"/>',
    'admin' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    'tds' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h6M7 16h8"/>',
    'surplus' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
    'fail' => '<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>',
    'ppt' => '<path d="M4 4h12l4 4v12H4z"/><path d="M14 4v4h4M8 13h8M8 17h5"/>',
    'support' => '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>',
    'guest' => '<path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87"/>',
    'rank' => '<path d="M8 21h8M12 17v4M7 4h10l-1 8H8L7 4zM9 4V2h6v2"/>',
];
$statIconFallback = $statIcons['chart'];

require dirname(__DIR__) . '/includes/layout-start.php';
?>

<section class="admin-page-intro">
  <h2>Welcome back, <?= e(arowin_admin_username()) ?></h2>
  <p>Monitor users, wallets, turnover, support, and daily operations from one control desk.</p>
</section>

<?php if ((int) ($queuedWd['count'] ?? 0) > 0): ?>
  <div class="admin-flash err" style="border-color:#f59e0b;background:rgba(245,158,11,.12);color:#fde68a">
    <strong>Action needed:</strong>
    <?= (int) $queuedWd['count'] ?> withdrawal(s) waiting — NOWPayments funds low (retry payout after top-up)
    (<?= (int) $queuedWd['users'] ?> users · <?= e(arowin_admin_fmt_money((float) $queuedWd['amount'])) ?>).
    <a href="<?= e(admin_url('/dashboard/withdrawals?status=queued')) ?>" style="color:#fff;text-decoration:underline;margin-left:6px">View who withdrew →</a>
  </div>
<?php endif; ?>

<section class="admin-section-label">Quick metrics</section>
<section class="admin-stats admin-stats-dashboard" aria-label="Dashboard metrics">
  <?php foreach ($boxes as $box): ?>
    <?php
      $iconKey = (string) ($box['icon'] ?? 'chart');
      $iconPath = $statIcons[$iconKey] ?? $statIconFallback;
      $valueClass = trim((string) ($box['value_class'] ?? ''));
    ?>
    <a class="admin-stat <?= e((string) $box['class']) ?>" href="<?= e((string) $box['href']) ?>">
      <div class="stat-top">
        <span class="label"><?= e((string) $box['label']) ?></span>
        <span class="stat-icon" aria-hidden="true">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><?= $iconPath ?></svg>
        </span>
      </div>
      <span class="value<?= $valueClass !== '' ? ' ' . e($valueClass) : '' ?>"><?= e((string) $box['value']) ?></span>
      <span class="hint"><?= e((string) $box['hint']) ?></span>
    </a>
  <?php endforeach; ?>
</section>

<section class="admin-section-label">Package purchases (instant wallet — history)</section>
<section class="admin-stats" aria-label="Purchase overview">
  <a class="admin-stat approved" href="<?= e($purchasesBase . '?status=approved') ?>">
    <div class="stat-top">
      <span class="label">Activated</span>
      <span class="stat-icon" aria-hidden="true">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
      </span>
    </div>
    <span class="value green"><?= (int) $purchaseCounts['approved'] ?></span>
    <span class="hint">Auto-activated from wallet</span>
  </a>
  <a class="admin-stat" href="<?= e($purchasesBase . '?status=all') ?>">
    <div class="stat-top">
      <span class="label">Total</span>
      <span class="stat-icon" aria-hidden="true">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19V5M4 19h16M8 15l3-4 3 2 4-6"/></svg>
      </span>
    </div>
    <span class="value"><?= (int) $purchaseCounts['all'] ?></span>
    <span class="hint">All purchase records</span>
  </a>
  <?php if ((int) ($purchaseCounts['pending'] ?? 0) > 0): ?>
  <a class="admin-stat pending" href="<?= e($purchasesBase . '?status=pending') ?>">
    <div class="stat-top">
      <span class="label">Legacy pending</span>
      <span class="stat-icon" aria-hidden="true">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
      </span>
    </div>
    <span class="value accent"><?= (int) $purchaseCounts['pending'] ?></span>
    <span class="hint">Old records only</span>
  </a>
  <?php endif; ?>
  <?php if ((int) ($purchaseCounts['rejected'] ?? 0) > 0): ?>
  <a class="admin-stat rejected" href="<?= e($purchasesBase . '?status=rejected') ?>">
    <div class="stat-top">
      <span class="label">Rejected</span>
      <span class="stat-icon" aria-hidden="true">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </span>
    </div>
    <span class="value red"><?= (int) $purchaseCounts['rejected'] ?></span>
    <span class="hint">Declined (legacy)</span>
  </a>
  <?php endif; ?>
</section>

<div class="admin-dash-grid">
  <section class="admin-panel">
    <div class="admin-panel-head">
      <h2>Recent purchases</h2>
      <a class="admin-btn link" href="<?= e($purchasesBase . '?status=approved') ?>">View all</a>
    </div>
    <?php if ($recentPurchases === []): ?>
      <div class="admin-empty">No package purchases yet.</div>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Package</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentPurchases as $row): ?>
              <tr>
                <td>#<?= (int) $row['id'] ?></td>
                <td>
                  <strong class="admin-mono"><?= e((string) $row['username']) ?></strong><br>
                  <span class="admin-muted"><?= e((string) ($row['full_name'] ?? '')) ?></span>
                </td>
                <td>$<?= e(number_format((int) $row['amount_usdt'])) ?></td>
                <td class="admin-muted"><?= e((string) ($row['created_at'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>
