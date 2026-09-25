<?php
/**
 * Shared admin report table renderer.
 *
 * Expected vars before require:
 * - string $ADMIN_PAGE
 * - string $ADMIN_TITLE
 * - string $ADMIN_SUBTITLE
 * - string $emptyMessage
 * - list<string> $columns  (header labels)
 * - list<list<string>> $rows (pre-escaped HTML cells)
 * - string|null $backHint
 */
declare(strict_types=1);

require_once __DIR__ . '/pagination.php';

$allRows = is_array($rows ?? null) ? $rows : [];
$pager = arowin_admin_paginate($allRows);
$rows = $pager['rows'];

require __DIR__ . '/layout-start.php';
?>

<section class="admin-page-intro">
  <h2><?= e($ADMIN_TITLE) ?></h2>
  <p><?= e($ADMIN_SUBTITLE) ?></p>
</section>

<p class="admin-back-link">
  <a class="admin-btn link" href="<?= e(admin_url('/dashboard')) ?>">← Back to dashboard</a>
</p>

<section class="admin-panel">
  <?php if ($pager['total'] < 1): ?>
    <div class="admin-empty"><?= e($emptyMessage ?? 'No records found.') ?></div>
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

<?php require __DIR__ . '/layout-end.php'; ?>
