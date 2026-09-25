<?php
/**
 * Admin — package purchases history (wallet buys activate instantly — no admin approve)
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'purchases';
$ADMIN_TITLE = 'Purchases';
$ADMIN_SUBTITLE = 'Wallet package buys activate instantly — history only (no approval needed)';

$flashErr = null;
$filter = strtolower((string) ($_GET['status'] ?? 'approved'));
if (!in_array($filter, ['approved', 'rejected', 'pending', 'all'], true)) {
    $filter = 'approved';
}

$rows = [];
try {
    $rows = arowin_list_purchases($filter === 'all' ? 'all' : $filter, 100);
} catch (Throwable $e) {
    error_log('Admin purchases load error: ' . $e->getMessage());
    $flashErr = 'Unable to load purchases right now.';
}

$pager = arowin_admin_paginate($rows);
$rows = $pager['rows'];

require dirname(__DIR__, 2) . '/includes/layout-start.php';
?>

<?php if ($flashErr): ?><div class="admin-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<p class="admin-muted" style="margin:0 0 14px;font-size:13px">
  Members pay from Master Wallet and the package activates automatically.
  This screen is a record log — not an approval queue.
</p>

<div class="admin-tabs">
  <?php foreach (['approved' => 'Activated', 'rejected' => 'Rejected', 'pending' => 'Legacy pending', 'all' => 'All'] as $key => $label): ?>
    <a class="<?= $filter === $key ? 'active' : '' ?>" href="?status=<?= e($key) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<section class="admin-panel">
  <?php if ($pager['total'] < 1): ?>
    <div class="admin-empty">No <?= e($filter === 'approved' ? 'activated' : $filter) ?> purchases.</div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>User</th>
            <th>Package</th>
            <th>TX / ref</th>
            <th>Status</th>
            <th>Source</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php
              $status = strtolower((string) ($row['status'] ?? 'pending'));
              $network = strtoupper((string) ($row['network'] ?? ''));
              $source = $network === 'WALLET' ? 'Wallet (instant)' : ($network !== '' ? $network : '—');
            ?>
            <tr>
              <td>#<?= (int) $row['id'] ?></td>
              <td>
                <strong><?= e((string) $row['username']) ?></strong><br>
                <span class="admin-muted"><?= e((string) ($row['full_name'] ?? '')) ?></span>
              </td>
              <td>
                $<?= e(number_format((int) $row['amount_usdt'])) ?><br>
                <span class="admin-muted"><?= e((string) ($row['created_at'] ?? '')) ?></span>
              </td>
              <td><code><?= e((string) $row['tx_hash']) ?></code></td>
              <td><span class="admin-badge <?= e($status) ?>"><?= e(ucfirst($status === 'approved' ? 'activated' : $status)) ?></span></td>
              <td class="admin-muted"><?= e($source) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="admin-mobile-list" aria-label="Purchase list">
      <?php foreach ($rows as $row): ?>
        <?php
          $status = strtolower((string) ($row['status'] ?? 'pending'));
          $network = strtoupper((string) ($row['network'] ?? ''));
          $source = $network === 'WALLET' ? 'Wallet (instant)' : ($network !== '' ? $network : '—');
        ?>
        <article class="admin-mobile-card">
          <div class="row">
            <span class="k">ID</span>
            <span class="v">#<?= (int) $row['id'] ?></span>
          </div>
          <div class="row">
            <span class="k">User</span>
            <span class="v">
              <strong><?= e((string) $row['username']) ?></strong><br>
              <span class="admin-muted"><?= e((string) ($row['full_name'] ?? '')) ?></span>
            </span>
          </div>
          <div class="row">
            <span class="k">Package</span>
            <span class="v">$<?= e(number_format((int) $row['amount_usdt'])) ?></span>
          </div>
          <div class="row">
            <span class="k">TX / ref</span>
            <span class="v"><code><?= e((string) $row['tx_hash']) ?></code></span>
          </div>
          <div class="row">
            <span class="k">Status</span>
            <span class="v"><span class="admin-badge <?= e($status) ?>"><?= e(ucfirst($status === 'approved' ? 'activated' : $status)) ?></span></span>
          </div>
          <div class="row">
            <span class="k">Source</span>
            <span class="v admin-muted"><?= e($source) ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?= arowin_admin_pagination_html($pager) ?>
  <?php endif; ?>
</section>

<?php require dirname(__DIR__, 2) . '/includes/layout-end.php'; ?>
