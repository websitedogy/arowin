<?php
/**
 * Admin — CMS create / edit
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';
require_once dirname(__DIR__, 3) . '/includes/cms-repository.php';

$ADMIN_PAGE = 'cms';
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$entry = $id > 0 ? arowin_cms_find_by_id($id) : null;
$type = (string) ($entry['type'] ?? $_GET['type'] ?? 'blog');
if (!in_array($type, arowin_cms_types(), true)) {
    $type = 'blog';
}

$ADMIN_TITLE = $entry ? 'Edit CMS entry' : 'New ' . arowin_cms_type_label($type);
$ADMIN_SUBTITLE = 'Auto slug + meta with manual overrides';

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    arowin_require_post_csrf();
    try {
        $savedId = arowin_cms_save($id > 0 ? $id : null, [
            'type' => $_POST['type'] ?? $type,
            'title' => $_POST['title'] ?? '',
            'slug' => $_POST['slug'] ?? '',
            'excerpt' => $_POST['excerpt'] ?? '',
            'body' => $_POST['body'] ?? '',
            'featured_image' => $_POST['featured_image'] ?? '',
            'gallery_json' => $_POST['gallery_json'] ?? '',
            'video_url' => $_POST['video_url'] ?? '',
            'video_embed' => $_POST['video_embed'] ?? '',
            'download_url' => $_POST['download_url'] ?? '',
            'download_mime' => $_POST['download_mime'] ?? '',
            'download_size' => $_POST['download_size'] ?? '',
            'event_start' => $_POST['event_start'] ?? '',
            'event_end' => $_POST['event_end'] ?? '',
            'event_location' => $_POST['event_location'] ?? '',
            'price' => $_POST['price'] ?? '',
            'currency' => $_POST['currency'] ?? 'USD',
            'availability' => $_POST['availability'] ?? 'InStock',
            'rating_value' => $_POST['rating_value'] ?? '',
            'rating_count' => $_POST['rating_count'] ?? 0,
            'sku' => $_POST['sku'] ?? '',
            'meta_title' => $_POST['meta_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'meta_keywords' => $_POST['meta_keywords'] ?? '',
            'canonical_url' => $_POST['canonical_url'] ?? '',
            'robots' => $_POST['robots'] ?? '',
            'og_title' => $_POST['og_title'] ?? '',
            'og_description' => $_POST['og_description'] ?? '',
            'og_image' => $_POST['og_image'] ?? '',
            'status' => $_POST['status'] ?? 'draft',
            'published_at' => $_POST['published_at'] ?? '',
            'author_name' => $_POST['author_name'] ?? 'Arowin Trading Company',
        ]);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . admin_url('/dashboard/cms/edit') . '?id=' . $savedId . '&saved=1', true, 303);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $entry = array_merge($entry ?? [], $_POST);
        $entry['type'] = $_POST['type'] ?? $type;
    }
}

if (isset($_GET['saved'])) {
    $flash = 'Saved. Sitemap regenerated on next crawl.';
    $entry = arowin_cms_find_by_id($id) ?: $entry;
}

$e = static function (string $key, string $default = '') use ($entry): string {
    return e((string) ($entry[$key] ?? $default));
};
$csrf = arowin_csrf_token();

require dirname(__DIR__, 2) . '/includes/layout-start.php';
?>

<p class="admin-muted"><a href="<?= e(admin_url('/dashboard/cms') . '?type=' . urlencode($type)) ?>">← Back to list</a></p>
<?php if ($flash): ?><div class="admin-flash ok"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-flash err"><?= e($error) ?></div><?php endif; ?>

<section class="admin-panel">
  <form method="post" class="admin-form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="admin-form-grid">
      <label>Type
        <select name="type">
          <?php foreach (arowin_cms_types() as $t): ?>
            <option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(arowin_cms_type_label($t)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Status
        <select name="status">
          <?php foreach (['draft','published','archived'] as $st): ?>
            <option value="<?= $st ?>" <?= (($entry['status'] ?? 'draft') === $st) ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Author <input name="author_name" value="<?= $e('author_name', 'Arowin Trading Company') ?>"></label>
      <label>Published at (UTC) <input name="published_at" placeholder="YYYY-mm-dd HH:ii:ss" value="<?= $e('published_at') ?>"></label>
    </div>

    <label>Title <input name="title" required value="<?= $e('title') ?>"></label>
    <label>Slug (blank = auto) <input name="slug" value="<?= $e('slug') ?>" placeholder="auto-generated-from-title"></label>
    <label>Excerpt <textarea name="excerpt" rows="2"><?= $e('excerpt') ?></textarea></label>
    <label>Body (HTML allowed) <textarea name="body" rows="12"><?= $e('body') ?></textarea></label>
    <label>Featured image path <input name="featured_image" value="<?= $e('featured_image') ?>" placeholder="/uploads/cms/photo.webp"></label>

    <h3 class="admin-subhead">Type-specific</h3>
    <div class="admin-form-grid">
      <label>Video URL <input name="video_url" value="<?= $e('video_url') ?>"></label>
      <label>Download URL <input name="download_url" value="<?= $e('download_url') ?>"></label>
      <label>Download MIME <input name="download_mime" value="<?= $e('download_mime') ?>"></label>
      <label>Download size (bytes) <input name="download_size" value="<?= $e('download_size') ?>"></label>
      <label>Event start <input name="event_start" value="<?= $e('event_start') ?>"></label>
      <label>Event end <input name="event_end" value="<?= $e('event_end') ?>"></label>
      <label>Event location <input name="event_location" value="<?= $e('event_location') ?>"></label>
      <label>Price <input name="price" value="<?= $e('price') ?>"></label>
      <label>Currency <input name="currency" value="<?= $e('currency', 'USD') ?>"></label>
      <label>Availability <input name="availability" value="<?= $e('availability', 'InStock') ?>"></label>
      <label>SKU <input name="sku" value="<?= $e('sku') ?>"></label>
      <label>Rating value <input name="rating_value" value="<?= $e('rating_value') ?>"></label>
      <label>Rating count <input name="rating_count" value="<?= $e('rating_count', '0') ?>"></label>
    </div>
    <label>Gallery JSON <textarea name="gallery_json" rows="3" placeholder='[{"src":"/uploads/a.webp","alt":"Arowin"}]'><?= $e('gallery_json') ?></textarea></label>
    <label>Video embed HTML <textarea name="video_embed" rows="3"><?= $e('video_embed') ?></textarea></label>

    <h3 class="admin-subhead">SEO overrides (optional — auto-filled if blank)</h3>
    <div class="admin-form-grid">
      <label>Meta title <input name="meta_title" value="<?= $e('meta_title') ?>"></label>
      <label>Keywords <input name="meta_keywords" value="<?= $e('meta_keywords') ?>"></label>
      <label>Canonical <input name="canonical_url" value="<?= $e('canonical_url') ?>"></label>
      <label>Robots <input name="robots" value="<?= $e('robots') ?>"></label>
      <label>OG title <input name="og_title" value="<?= $e('og_title') ?>"></label>
      <label>OG image <input name="og_image" value="<?= $e('og_image') ?>"></label>
    </div>
    <label>Meta description <textarea name="meta_description" rows="2"><?= $e('meta_description') ?></textarea></label>
    <label>OG description <textarea name="og_description" rows="2"><?= $e('og_description') ?></textarea></label>

    <div class="admin-form-actions" style="margin-top:16px">
      <button class="admin-btn primary" type="submit">Save entry</button>
    </div>
  </form>
</section>

<style>
.admin-form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin:12px 0}
.admin-form label{display:flex;flex-direction:column;gap:6px;font-size:12px;color:#9ca3af;margin-bottom:10px}
.admin-form input,.admin-form textarea,.admin-form select{background:#111;border:1px solid #2a2a2a;border-radius:8px;padding:10px 12px;color:#fff;font:inherit;width:100%}
.admin-subhead{margin:24px 0 8px;font-size:15px}
.admin-btn{display:inline-flex;padding:10px 14px;border-radius:8px;border:1px solid #333;background:#1a1a1a;color:#fff;cursor:pointer}
.admin-btn.primary{background:#ff8c1a;border-color:#ff8c1a;color:#000;font-weight:700}
.admin-flash{padding:12px;border-radius:8px;margin-bottom:12px}
.admin-flash.ok{background:rgba(34,197,94,.12);color:#86efac}
.admin-flash.err{background:rgba(239,68,68,.12);color:#fca5a5}
</style>

<?php require dirname(__DIR__, 2) . '/includes/layout-end.php'; ?>
