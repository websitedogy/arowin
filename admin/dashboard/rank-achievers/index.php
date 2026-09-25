<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'rank-achievers';
$filter = strtolower((string) ($_GET['scope'] ?? 'today'));
$todayOnly = $filter !== 'all';

$ADMIN_TITLE = $todayOnly ? 'Today rank achievers' : 'All rank achievers';
$ADMIN_SUBTITLE = 'Members who unlocked binary ranks' . ($todayOnly ? ' today' : '');
$emptyMessage = $todayOnly ? 'No new rank achievers today.' : 'No rank achievements recorded yet.';
$columns = ['ID', 'User ID', 'Rank', 'Left', 'Right', 'Achieved'];
$rows = [];

foreach (arowin_admin_list_rank_achievers($todayOnly) as $row) {
    $rows[] = [
        '#' . (int) $row['id'],
        '<strong class="admin-mono">' . e((string) $row['username']) . '</strong>',
        e((string) ($row['rank_label'] ?? '')),
        e((string) (int) ($row['left_count'] ?? 0)),
        e((string) (int) ($row['right_count'] ?? 0)),
        '<span class="admin-muted">' . e((string) ($row['achieved_at'] ?? '')) . '</span>',
    ];
}

$pager = arowin_admin_paginate($rows);
$rows = $pager['rows'];

require dirname(__DIR__, 2) . '/includes/layout-start.php';
?>

<section class="admin-page-intro">
  <h2><?= e($ADMIN_TITLE) ?></h2>
  <p><?= e($ADMIN_SUBTITLE) ?></p>
</section>

<p class="admin-back-link">
  <a class="admin-btn link" href="<?= e(admin_url('/dashboard')) ?>">← Back to dashboard</a>
</p>

<div class="admin-tabs">
  <a class="<?= $todayOnly ? 'active' : '' ?>" href="?scope=today">Today</a>
  <a class="<?= !$todayOnly ? 'active' : '' ?>" href="?scope=all">All</a>
</div>

<section class="admin-panel">
  <?php if ($pager['total'] < 1): ?>
    <div class="admin-empty"><?= e($emptyMessage) ?></div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <?php foreach ($columns as $col): ?>
              <th><?= e($col) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $cells): ?>
            <tr>
              <?php foreach ($cells as $cell): ?>
                <td><?= $cell ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= arowin_admin_pagination_html($pager) ?>
  <?php endif; ?>
</section>

<?php require dirname(__DIR__, 2) . '/includes/layout-end.php'; ?>
