<?php
/**
 * Admin — PPT upload + change history
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';
require_once dirname(__DIR__, 3) . '/includes/ppt-storage.php';

$ADMIN_PAGE = 'ppt';
$ADMIN_TITLE = 'PPT upload';
$ADMIN_SUBTITLE = 'Latest PDF is live for members';

$flashOk = null;
$flashErr = null;

if (!empty($_SESSION['arowin_admin_ppt_flash_ok'])) {
    $flashOk = (string) $_SESSION['arowin_admin_ppt_flash_ok'];
    unset($_SESSION['arowin_admin_ppt_flash_ok']);
}
if (!empty($_SESSION['arowin_admin_ppt_flash_err'])) {
    $flashErr = (string) $_SESSION['arowin_admin_ppt_flash_err'];
    unset($_SESSION['arowin_admin_ppt_flash_err']);
}

arowin_ppt_ensure_dir();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
        $flashErr = 'Session expired. Refresh and try again.';
    } else {
        $postAction = (string) ($_POST['action'] ?? 'upload');
        if ($postAction === 'edit') {
            $result = arowin_ppt_history_edit(
                (string) ($_POST['id'] ?? ''),
                (string) ($_POST['original_name'] ?? '')
            );
            if (!empty($result['ok'])) {
                $_SESSION['arowin_admin_ppt_flash_ok'] = 'History item updated.';
            } else {
                $_SESSION['arowin_admin_ppt_flash_err'] = (string) ($result['error'] ?? 'Could not update.');
            }
            header('Location: ' . admin_url('/dashboard/ppt/'), true, 303);
            exit;
        }
        if ($postAction === 'delete') {
            $result = arowin_ppt_history_delete((string) ($_POST['id'] ?? ''));
            if (!empty($result['ok'])) {
                $_SESSION['arowin_admin_ppt_flash_ok'] = 'History item deleted.';
            } else {
                $_SESSION['arowin_admin_ppt_flash_err'] = (string) ($result['error'] ?? 'Could not delete.');
            }
            header('Location: ' . admin_url('/dashboard/ppt/'), true, 303);
            exit;
        }

        $result = arowin_ppt_save_upload($_FILES['ppt_file'] ?? [], arowin_admin_username());
        if (!empty($result['ok'])) {
            $flashOk = 'PDF uploaded. Members now see this latest file.';
        } else {
            $flashErr = (string) ($result['error'] ?? 'Upload failed.');
        }
    }
}

$current = arowin_ppt_current();
$history = arowin_ppt_history();
$editId = (string) ($_GET['edit'] ?? '');
$csrf = arowin_csrf_token();
$historyPager = arowin_admin_paginate($history);
$historyPage = $historyPager['rows'];

$editRow = null;
if ($editId !== '') {
    foreach ($history as $row) {
        if ((string) ($row['id'] ?? '') === $editId) {
            $editRow = $row;
            break;
        }
    }
}

require dirname(__DIR__, 2) . '/includes/layout-start.php';
?>

<?php if ($flashOk): ?><div class="admin-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="admin-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<section class="admin-panel">
  <div class="admin-panel-head">
    <h2>Upload PDF</h2>
  </div>
  <form method="post" enctype="multipart/form-data" class="admin-form" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="upload">
    <label class="admin-field" style="flex:1 1 220px;margin:0">
      <span>PDF file</span>
      <input type="file" name="ppt_file" accept=".pdf,application/pdf" required>
    </label>
    <button type="submit" class="admin-btn primary">Upload</button>
  </form>
</section>

<?php if ($editRow !== null): ?>
<section class="admin-panel" style="margin-top:16px">
  <div class="admin-panel-head">
    <h2>Edit history item</h2>
  </div>
  <form method="post" class="admin-form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="id" value="<?= e((string) ($editRow['id'] ?? '')) ?>">
    <label class="admin-field">
      <span>Display file name</span>
      <input type="text" name="original_name" value="<?= e((string) ($editRow['original_name'] ?? '')) ?>" required maxlength="180">
    </label>
    <div class="admin-user-actions" style="margin-top:12px">
      <button type="submit" class="admin-btn primary">Save</button>
      <a class="admin-btn link" href="<?= e(admin_url('/dashboard/ppt/')) ?>">Cancel</a>
    </div>
  </form>
</section>
<?php endif; ?>

<section class="admin-panel" style="margin-top:16px">
  <div class="admin-panel-head">
    <h2>Change history</h2>
  </div>
  <?php if ($historyPager['total'] < 1): ?>
    <div class="admin-empty">No upload history yet.</div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table admin-table-compact">
        <thead>
          <tr>
            <th>#</th>
            <th>When</th>
            <th>Action</th>
            <th>PDF</th>
            <th>By</th>
            <th>Size</th>
            <th>Status</th>
            <th>Manage</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($historyPage as $i => $row): ?>
            <?php
              $rowId = (string) ($row['id'] ?? '');
              $action = (string) ($row['action'] ?? 'uploaded');
              $orig = (string) ($row['original_name'] ?? '—');
              $by = (string) ($row['uploaded_by'] ?? '—');
              $when = (string) ($row['uploaded_at'] ?? '');
              $size = (int) ($row['size'] ?? 0);
              $stored = (string) ($row['stored_name'] ?? '');
              $fileExists = $stored !== '' && is_file(arowin_ppt_dir() . '/' . basename($stored));
              $viewUrl = ($rowId !== '' && $fileExists)
                  ? (admin_url('/dashboard/ppt/view') . '?id=' . rawurlencode($rowId))
                  : '';
              $isLive = $current !== null
                  && $action !== 'removed'
                  && $stored !== ''
                  && $stored === $current['stored_name'];
              $badge = match (true) {
                  $isLive => ['Live', 'active'],
                  $action === 'removed' => ['Removed', 'rejected'],
                  $action === 'replaced' => ['Replaced', 'pending'],
                  default => ['Past', 'pending'],
              };
              $actionLabel = match ($action) {
                  'removed' => 'Removed',
                  'replaced' => 'Replaced previous',
                  default => 'Uploaded',
              };
            ?>
            <tr>
              <td><?= (int) ($i + 1) ?></td>
              <td class="admin-muted"><?= e($when) ?></td>
              <td><?= e($actionLabel) ?></td>
              <td>
                <?php if ($viewUrl !== ''): ?>
                  <a href="<?= e($viewUrl) ?>" target="_blank" rel="noopener" style="color:var(--admin-accent,#ff8c1a);font-weight:700;text-decoration:underline">
                    <?= e($orig) ?>
                  </a>
                <?php else: ?>
                  <?= e($orig) ?>
                <?php endif; ?>
              </td>
              <td><?= e($by !== '' ? $by : '—') ?></td>
              <td><?= $size > 0 ? e(number_format($size / 1024, 1) . ' KB') : '—' ?></td>
              <td><span class="admin-badge <?= e($badge[1]) ?>"><?= e($badge[0]) ?></span></td>
              <td>
                <div class="admin-user-actions">
                  <?php if ($viewUrl !== ''): ?>
                    <a class="admin-btn" href="<?= e($viewUrl) ?>" target="_blank" rel="noopener">View PDF</a>
                  <?php endif; ?>
                  <?php if ($rowId !== ''): ?>
                    <a class="admin-btn" href="<?= e(admin_url('/dashboard/ppt/?edit=' . rawurlencode($rowId))) ?>">Edit</a>
                    <form method="post" class="admin-inline-form" onsubmit="return confirm(<?= $isLive ? "'Delete this LIVE file? Members will no longer see a presentation.'" : "'Delete this history row?'" ?>);">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= e($rowId) ?>">
                      <button type="submit" class="admin-btn danger">Delete</button>
                    </form>
                  <?php else: ?>
                    <span class="admin-muted">—</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= arowin_admin_pagination_html($historyPager) ?>
  <?php endif; ?>
</section>

<?php require dirname(__DIR__, 2) . '/includes/layout-end.php'; ?>
