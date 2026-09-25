<?php
/**
 * CMS listing — blog, news, products, events, gallery, videos, downloads
 * Set $CMS_TYPE before require, or ?type=
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/cms-repository.php';

$type = (string) ($CMS_TYPE ?? ($_GET['type'] ?? 'blog'));
if (!in_array($type, arowin_cms_types(), true)) {
    http_response_code(404);
    require dirname(__DIR__, 2) . '/public/pages/404.php';
    exit;
}

$label = arowin_cms_type_label($type);
$basePath = arowin_cms_public_base($type);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;
$total = arowin_cms_count($type);
$items = arowin_cms_list($type, ['limit' => $perPage, 'offset' => $offset]);
$pages = max(1, (int) ceil($total / $perPage));

$CURRENT_ROUTE = $type;
arowin_prepare_page([
    'title' => arowin_auto_title($label . ' — Arowin Trading Company'),
    'title_short' => 'Arowin ' . $label,
    'description' => 'Official Arowin Trading Company ' . strtolower($label) . ' — insights, updates, and resources from AROWIN institutional binary networks.',
    'keywords' => 'Arowin, Arowin Trading Company, AROWIN, ' . $label,
    'canonical' => $basePath . ($page > 1 ? '?page=' . $page : ''),
    'faqs' => false,
]);
arowin_apply_seo_overrides($basePath);

$crumbsHtml = arowin_render_breadcrumbs([
    ['name' => 'Home', 'url' => url('home')],
    ['name' => $label, 'url' => arowin_cms_url($type)],
]);

require dirname(__DIR__, 2) . '/includes/page-start.php';
?>
<main>
  <section class="page-hero" aria-labelledby="cms-list-heading">
    <div class="container">
      <?= $crumbsHtml ?>
      <p class="eyebrow">Arowin Trading Company</p>
      <h1 id="cms-list-heading"><?= e($label) ?></h1>
      <p>Browse official Arowin <?= e(strtolower($label)) ?> — published for operators and partners seeking clear, trustworthy information.</p>
    </div>
  </section>

  <section class="page-content">
    <div class="container">
      <?php if ($items === []): ?>
        <p class="section-lead">No published <?= e(strtolower($label)) ?> yet. Check back soon, or explore the <a href="<?= e(url('binary-plan')) ?>">Binary Plan</a> and <a href="<?= e(url('faqs')) ?>">FAQs</a>.</p>
      <?php else: ?>
        <div class="cms-grid" role="list">
          <?php foreach ($items as $item):
              $slug = (string) ($item['slug'] ?? '');
              $title = (string) ($item['title'] ?? '');
              if ($slug === '' || $title === '') {
                  continue;
              }
              $href = arowin_cms_url($type, $slug);
              $img = (string) ($item['featured_image'] ?? '');
              $excerpt = (string) ($item['excerpt'] ?? '');
          ?>
          <article class="cms-card" role="listitem">
            <?php if ($img !== ''): ?>
              <a class="cms-card-media" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true">
                <?= arowin_img($img, $title, ['class' => 'cms-card-img', 'width' => 640, 'height' => 360]) ?>
              </a>
            <?php endif; ?>
            <div class="cms-card-body">
              <h2 class="cms-card-title"><a href="<?= e($href) ?>"><?= e($title) ?></a></h2>
              <?php if ($excerpt !== ''): ?>
                <p><?= e($excerpt) ?></p>
              <?php endif; ?>
              <a class="cms-card-more" href="<?= e($href) ?>">Read more<span class="sr-only">: <?= e($title) ?></span></a>
            </div>
          </article>
          <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
        <nav class="cms-pagination" aria-label="Pagination">
          <?php if ($page > 1): ?>
            <a href="<?= e(arowin_cms_url($type) . '?page=' . ($page - 1)) ?>" rel="prev">Previous</a>
          <?php endif; ?>
          <span aria-current="page">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
          <?php if ($page < $pages): ?>
            <a href="<?= e(arowin_cms_url($type) . '?page=' . ($page + 1)) ?>" rel="next">Next</a>
          <?php endif; ?>
        </nav>
        <?php endif; ?>
      <?php endif; ?>

      <?= arowin_render_internal_links([
          ['title' => 'Binary Plan', 'url' => url('binary-plan')],
          ['title' => 'USDT Matching', 'url' => url('usdt-matching')],
          ['title' => 'FAQs', 'url' => url('faqs')],
          ['title' => 'About Arowin', 'url' => url('about')],
          ['title' => 'Contact', 'url' => url('contact')],
          ['title' => 'Search', 'url' => url('search')],
      ], 'Explore Arowin') ?>
    </div>
  </section>
</main>
<?php require dirname(__DIR__, 2) . '/includes/page-end.php'; ?>
