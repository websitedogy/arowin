<?php
/**
 * Admin — CMS content list
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';
require_once dirname(__DIR__, 3) . '/includes/cms-repository.php';

$ADMIN_PAGE = 'cms';
$ADMIN_TITLE = 'CMS content';
$ADMIN_SUBTITLE = 'Blog, news, products, events, gallery, videos, downloads';

$type = (string) ($_GET['type'] ?? 'blog');
if (!in_array($type, arowin_cms_types(), true)) {
    $type = 'blog';
}

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    arowin_require_post_csrf();
    if (($_POST['action'] ?? '') === 'delete') {
        arowin_cms_delete((int) ($_POST['id'] ?? 0));
        $flash = 'Entry deleted. Sitemap refreshed.';
    }
}

$items = arowin_cms_list($type, ['admin' => true, 'status' => 'all', 'limit' => 100]);
$csrf = arowin_csrf_token();
$pager = arowin_admin_paginate($items);
$items = $pager['rows'];

require dirname(__DIR__, 2) . '/includes/layout-start.php';
?>

<?php if ($flash): ?><div class="admin-flash ok"><?= e($flash) ?></div><?php endif; ?>

<section class="admin-panel">
  <div class="admin-panel-head">
    <h2><?= e(arowin_cms_type_label($type)) ?></h2>
    <a class="admin-btn primary" href="<?= e(admin_url('/dashboard/cms/edit') . '?type=' . urlencode($type)) ?>">Add new</a>
  </div>

  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
    <?php foreach (arowin_cms_types() as $t): ?>
      <a class="admin-btn<?= $t === $type ? ' primary' : '' ?>" href="?type=<?= e($t) ?>"><?= e(arowin_cms_type_label($t)) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Title</th><th>Slug</th><th>Status</th><th>Published</th><th></th></tr>
      </thead>
      <tbody>
        <?php if ($pager['total'] < 1): ?>
          <tr><td colspan="5" class="admin-muted">No entries. Seed sample content or add a new item.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
          <tr>
            <td><?= e((string) $item['title']) ?></td>
            <td><code><?= e((string) $item['slug']) ?></code></td>
            <td><?= e((string) $item['status']) ?></td>
            <td><?= e((string) ($item['published_at'] ?? '—')) ?></td>
            <td style="white-space:nowrap">
              <a href="<?= e(admin_url('/dashboard/cms/edit') . '?id=' . (int) $item['id']) ?>">Edit</a>
              <?php if (($item['status'] ?? '') === 'published'): ?>
                · <a href="<?= e(arowin_cms_url($type, (string) $item['slug'])) ?>" target="_blank" rel="noopener">View</a>
              <?php endif; ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this entry?')">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                <button type="submit" class="admin-btn" style="padding:4px 8px;margin-left:6px">Delete</button>
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
.admin-btn{display:inline-flex;padding:8px 12px;border-radius:8px;border:1px solid #333;background:#1a1a1a;color:#fff;text-decoration:none;cursor:pointer;font:inherit}
.admin-btn.primary{background:#ff8c1a;border-color:#ff8c1a;color:#000;font-weight:700}
.admin-flash{padding:12px;border-radius:8px;margin-bottom:12px;background:rgba(34,197,94,.12);color:#86efac}
</style>

<?php require dirname(__DIR__, 2) . '/includes/layout-end.php'; ?>
