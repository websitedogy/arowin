<?php
/**
 * Admin — per-page SEO meta overrides
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';
require_once dirname(__DIR__, 3) . '/includes/seo-settings-repository.php';

$ADMIN_PAGE = 'seo';
$ADMIN_TITLE = 'SEO page meta';
$ADMIN_SUBTITLE = 'Manual overrides for titles, descriptions, canonicals, OG, schema';

$flash = '';
$error = '';
$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    arowin_require_post_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete') {
        arowin_seo_delete_page_meta((int) ($_POST['id'] ?? 0));
        $flash = 'Page meta deleted.';
    } elseif ($action === 'save') {
        try {
            arowin_seo_save_page_meta((string) ($_POST['path'] ?? ''), [
                'meta_title' => $_POST['meta_title'] ?? '',
                'meta_description' => $_POST['meta_description'] ?? '',
                'meta_keywords' => $_POST['meta_keywords'] ?? '',
                'canonical_url' => $_POST['canonical_url'] ?? '',
                'robots' => $_POST['robots'] ?? '',
                'og_title' => $_POST['og_title'] ?? '',
                'og_description' => $_POST['og_description'] ?? '',
                'og_image' => $_POST['og_image'] ?? '',
                'og_type' => $_POST['og_type'] ?? '',
                'twitter_title' => $_POST['twitter_title'] ?? '',
                'twitter_description' => $_POST['twitter_description'] ?? '',
                'twitter_image' => $_POST['twitter_image'] ?? '',
                'schema_json' => $_POST['schema_json'] ?? '',
            ]);
            $flash = 'Page meta saved.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

if (isset($_GET['edit'])) {
    $edit = arowin_seo_get_page_meta('/' . ltrim((string) $_GET['edit'], '/'));
    if ($edit === null && (string) $_GET['edit'] !== '') {
        $edit = ['path' => arowin_seo_normalize_path((string) $_GET['edit'])];
    }
}

$rows = arowin_seo_list_page_meta();
$csrf = arowin_csrf_token();
$pager = arowin_admin_paginate($rows);
$rows = $pager['rows'];

require dirname(__DIR__, 2) . '/includes/layout-start.php';
?>

<p class="admin-muted" style="margin-bottom:12px">
  <a href="<?= e(admin_url('/dashboard/seo')) ?>">← SEO settings</a> ·
  <a href="<?= e(admin_url('/dashboard/seo/redirects')) ?>">Redirects</a>
</p>

<?php if ($flash): ?><div class="admin-flash ok"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-flash err"><?= e($error) ?></div><?php endif; ?>

<section class="admin-panel">
  <div class="admin-panel-head">
    <h2><?= $edit ? 'Edit page meta' : 'Add page meta override' ?></h2>
  </div>
  <form method="post" class="admin-form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="save">
    <div class="admin-form-grid">
      <label>Path (e.g. /about)
        <input name="path" required value="<?= e((string) ($edit['path'] ?? '')) ?>">
      </label>
      <label>Meta title
        <input name="meta_title" value="<?= e((string) ($edit['meta_title'] ?? '')) ?>">
      </label>
      <label>Robots
        <input name="robots" placeholder="index, follow" value="<?= e((string) ($edit['robots'] ?? '')) ?>">
      </label>
      <label>Canonical URL / path
        <input name="canonical_url" value="<?= e((string) ($edit['canonical_url'] ?? '')) ?>">
      </label>
      <label>OG type
        <input name="og_type" placeholder="website" value="<?= e((string) ($edit['og_type'] ?? '')) ?>">
      </label>
      <label>OG image
        <input name="og_image" value="<?= e((string) ($edit['og_image'] ?? '')) ?>">
      </label>
    </div>
    <label>Meta description
      <textarea name="meta_description" rows="2"><?= e((string) ($edit['meta_description'] ?? '')) ?></textarea>
    </label>
    <label>Keywords
      <input name="meta_keywords" value="<?= e((string) ($edit['meta_keywords'] ?? '')) ?>">
    </label>
    <div class="admin-form-grid">
      <label>OG title <input name="og_title" value="<?= e((string) ($edit['og_title'] ?? '')) ?>"></label>
      <label>Twitter title <input name="twitter_title" value="<?= e((string) ($edit['twitter_title'] ?? '')) ?>"></label>
      <label>Twitter image <input name="twitter_image" value="<?= e((string) ($edit['twitter_image'] ?? '')) ?>"></label>
    </div>
    <label>OG description <textarea name="og_description" rows="2"><?= e((string) ($edit['og_description'] ?? '')) ?></textarea></label>
    <label>Twitter description <textarea name="twitter_description" rows="2"><?= e((string) ($edit['twitter_description'] ?? '')) ?></textarea></label>
    <label>Extra schema JSON (object or @graph)
      <textarea name="schema_json" rows="5" placeholder='{"@type":"FAQPage",...}'><?= e((string) ($edit['schema_json'] ?? '')) ?></textarea>
    </label>
    <div class="admin-form-actions">
      <button class="admin-btn primary" type="submit">Save</button>
    </div>
  </form>
</section>

<section class="admin-panel" style="margin-top:20px">
  <div class="admin-panel-head"><h2>Overrides</h2></div>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Path</th><th>Title</th><th>Robots</th><th></th></tr></thead>
      <tbody>
        <?php if ($pager['total'] < 1): ?>
          <tr><td colspan="4" class="admin-muted">No overrides yet — pages use auto meta from content.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><code><?= e((string) $r['path']) ?></code></td>
            <td><?= e((string) ($r['meta_title'] ?? '—')) ?></td>
            <td><?= e((string) ($r['robots'] ?? '—')) ?></td>
            <td style="white-space:nowrap">
              <a href="?edit=<?= e(ltrim((string) $r['path'], '/')) ?>">Edit</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete override?')">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button type="submit" class="admin-btn" style="padding:4px 8px">Delete</button>
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
.admin-form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin:12px 0}
.admin-form label{display:flex;flex-direction:column;gap:6px;font-size:12px;color:#9ca3af;margin-bottom:10px}
.admin-form input,.admin-form textarea{background:#111;border:1px solid #2a2a2a;border-radius:8px;padding:10px 12px;color:#fff;font:inherit;width:100%}
.admin-btn{display:inline-flex;padding:10px 14px;border-radius:8px;border:1px solid #333;background:#1a1a1a;color:#fff;cursor:pointer}
.admin-btn.primary{background:#ff8c1a;border-color:#ff8c1a;color:#000;font-weight:700}
.admin-flash{padding:12px;border-radius:8px;margin-bottom:12px}
.admin-flash.ok{background:rgba(34,197,94,.12);color:#86efac}
.admin-flash.err{background:rgba(239,68,68,.12);color:#fca5a5}
</style>

<?php require dirname(__DIR__, 2) . '/includes/layout-end.php'; ?>
