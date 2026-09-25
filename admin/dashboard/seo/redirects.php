<?php
/**
 * Admin — SEO redirects
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';
require_once dirname(__DIR__, 3) . '/includes/seo-settings-repository.php';

$ADMIN_PAGE = 'seo';
$ADMIN_TITLE = 'SEO redirects';
$ADMIN_SUBTITLE = '301/302 URL redirects';

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    arowin_require_post_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'delete') {
            arowin_seo_delete_redirect((int) ($_POST['id'] ?? 0));
            $flash = 'Redirect deleted.';
        } elseif ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            arowin_seo_save_redirect($id > 0 ? $id : null, [
                'from_path' => $_POST['from_path'] ?? '',
                'to_path' => $_POST['to_path'] ?? '',
                'status_code' => (int) ($_POST['status_code'] ?? 301),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ]);
            $flash = 'Redirect saved.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rows = arowin_seo_list_redirects();
$csrf = arowin_csrf_token();
$pager = arowin_admin_paginate($rows);
$rows = $pager['rows'];

require dirname(__DIR__, 2) . '/includes/layout-start.php';
?>

<p class="admin-muted" style="margin-bottom:12px">
  <a href="<?= e(admin_url('/dashboard/seo')) ?>">← SEO settings</a> ·
  <a href="<?= e(admin_url('/dashboard/seo/pages')) ?>">Page meta</a>
</p>

<?php if ($flash): ?><div class="admin-flash ok"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-flash err"><?= e($error) ?></div><?php endif; ?>

<section class="admin-panel">
  <div class="admin-panel-head"><h2>Add redirect</h2></div>
  <form method="post" class="admin-form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="0">
    <div class="admin-form-grid">
      <label>From path <input name="from_path" required placeholder="/old-page"></label>
      <label>To path / URL <input name="to_path" required placeholder="/new-page"></label>
      <label>Status
        <select name="status_code">
          <option value="301">301 Permanent</option>
          <option value="302">302 Temporary</option>
        </select>
      </label>
      <label class="admin-check" style="flex-direction:row;align-items:center;margin-top:22px">
        <input type="checkbox" name="is_active" value="1" checked> Active
      </label>
    </div>
    <button class="admin-btn primary" type="submit">Save redirect</button>
  </form>
</section>

<section class="admin-panel" style="margin-top:20px">
  <div class="admin-panel-head"><h2>Redirects</h2></div>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>From</th><th>To</th><th>Code</th><th>Hits</th><th>Active</th><th></th></tr></thead>
      <tbody>
        <?php if ($pager['total'] < 1): ?>
          <tr><td colspan="6" class="admin-muted">No redirects configured.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><code><?= e((string) $r['from_path']) ?></code></td>
            <td><code><?= e((string) $r['to_path']) ?></code></td>
            <td><?= (int) $r['status_code'] ?></td>
            <td><?= (int) $r['hits'] ?></td>
            <td><?= !empty($r['is_active']) ? 'Yes' : 'No' ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Delete redirect?')">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button type="submit" class="admin-btn">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= arowin_admin_pagination_html($pager) ?>
</section>

<style>
.admin-form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin:12px 0}
.admin-form label{display:flex;flex-direction:column;gap:6px;font-size:12px;color:#9ca3af}
.admin-form input,.admin-form select{background:#111;border:1px solid #2a2a2a;border-radius:8px;padding:10px 12px;color:#fff;font:inherit}
.admin-btn{display:inline-flex;padding:8px 12px;border-radius:8px;border:1px solid #333;background:#1a1a1a;color:#fff;cursor:pointer}
.admin-btn.primary{background:#ff8c1a;border-color:#ff8c1a;color:#000;font-weight:700}
.admin-flash{padding:12px;border-radius:8px;margin-bottom:12px}
.admin-flash.ok{background:rgba(34,197,94,.12);color:#86efac}
.admin-flash.err{background:rgba(239,68,68,.12);color:#fca5a5}
</style>

<?php require dirname(__DIR__, 2) . '/includes/layout-end.php'; ?>
