<?php
/**
 * CMS single entry view
 * Set $CMS_TYPE + $CMS_SLUG, or query params.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/cms-repository.php';

$type = (string) ($CMS_TYPE ?? ($_GET['type'] ?? ''));
$slug = (string) ($CMS_SLUG ?? ($_GET['slug'] ?? ''));
$slug = trim($slug, '/');

if (!in_array($type, arowin_cms_types(), true) || $slug === '') {
    http_response_code(404);
    require dirname(__DIR__, 2) . '/public/pages/404.php';
    exit;
}

$entry = arowin_cms_find($type, $slug, true);
if ($entry === null) {
    http_response_code(404);
    require dirname(__DIR__, 2) . '/public/pages/404.php';
    exit;
}

arowin_cms_bump_views((int) $entry['id']);
arowin_cms_prepare_entry_seo($entry);

$label = arowin_cms_type_label($type);
$crumbsHtml = arowin_render_breadcrumbs([
    ['name' => 'Home', 'url' => url('home')],
    ['name' => $label, 'url' => arowin_cms_url($type)],
    ['name' => (string) $entry['title']],
]);

$img = (string) ($entry['featured_image'] ?? '');
$body = (string) ($entry['body'] ?? '');
$related = arowin_internal_links($type, $slug, 6);

require dirname(__DIR__, 2) . '/includes/page-start.php';
?>
<main>
  <article class="cms-article" itemscope itemtype="https://schema.org/<?= $type === 'news' ? 'NewsArticle' : ($type === 'product' ? 'Product' : 'Article') ?>">
    <header class="page-hero" aria-labelledby="cms-entry-heading">
      <div class="container">
        <?= $crumbsHtml ?>
        <p class="eyebrow"><?= e($label) ?></p>
        <h1 id="cms-entry-heading" itemprop="headline name"><?= e((string) $entry['title']) ?></h1>
        <?php if (!empty($entry['excerpt'])): ?>
          <p itemprop="description"><?= e((string) $entry['excerpt']) ?></p>
        <?php endif; ?>
        <?php if (!empty($entry['published_at'])): ?>
          <p class="cms-meta"><time datetime="<?= e(date('c', strtotime((string) $entry['published_at'] . ' UTC'))) ?>" itemprop="datePublished"><?= e(arowin_format_india_time((string) $entry['published_at'], 'd M Y')) ?></time>
            <?php if (!empty($entry['author_name'])): ?>
              · <span itemprop="author"><?= e((string) $entry['author_name']) ?></span>
            <?php endif; ?>
          </p>
        <?php endif; ?>
      </div>
    </header>

    <div class="page-content">
      <div class="container">
        <?php if ($img !== ''): ?>
          <figure class="cms-featured">
            <?= arowin_img($img, (string) $entry['title'], ['class' => 'cms-featured-img', 'eager' => true, 'width' => 1200, 'height' => 630]) ?>
          </figure>
        <?php endif; ?>

        <?php if ($type === 'product' && isset($entry['price'])): ?>
          <div class="cms-product-offer" aria-label="Offer">
            <p class="cms-price"><span itemprop="offers" itemscope itemtype="https://schema.org/Offer">
              <meta itemprop="priceCurrency" content="<?= e((string) ($entry['currency'] ?: 'USD')) ?>">
              <strong itemprop="price"><?= e(number_format((float) $entry['price'], 2)) ?></strong> <?= e((string) ($entry['currency'] ?: 'USD')) ?>
            </span></p>
            <?php if (!empty($entry['rating_value']) && (int) ($entry['rating_count'] ?? 0) > 0): ?>
              <p class="cms-rating">Rated <?= e((string) $entry['rating_value']) ?> / 5 (<?= (int) $entry['rating_count'] ?> reviews)</p>
            <?php endif; ?>
            <p><a class="btn btn-primary" href="<?= e(url('register')) ?>">Register Node</a></p>
          </div>
        <?php endif; ?>

        <?php if ($type === 'video' && !empty($entry['video_url'])): ?>
          <div class="cms-video-wrap">
            <div class="cms-video-frame">
              <iframe src="<?= e((string) $entry['video_url']) ?>" title="<?= e((string) $entry['title']) ?>" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
            </div>
          </div>
        <?php elseif ($type === 'video' && !empty($entry['video_embed'])): ?>
          <div class="cms-video-wrap"><?= $entry['video_embed'] /* trusted admin HTML */ ?></div>
        <?php endif; ?>

        <?php if ($type === 'download' && !empty($entry['download_url'])): ?>
          <p class="page-cta"><a class="btn btn-primary" href="<?= e((string) $entry['download_url']) ?>" download>Download file</a></p>
        <?php endif; ?>

        <?php if ($type === 'event'): ?>
          <ul class="cms-event-meta">
            <?php if (!empty($entry['event_start'])): ?>
              <li><strong>Starts:</strong> <?= e(arowin_format_india_time((string) $entry['event_start'])) ?></li>
            <?php endif; ?>
            <?php if (!empty($entry['event_end'])): ?>
              <li><strong>Ends:</strong> <?= e(arowin_format_india_time((string) $entry['event_end'])) ?></li>
            <?php endif; ?>
            <?php if (!empty($entry['event_location'])): ?>
              <li><strong>Location:</strong> <?= e((string) $entry['event_location']) ?></li>
            <?php endif; ?>
          </ul>
        <?php endif; ?>

        <?php if ($type === 'gallery' && !empty($entry['gallery_json'])):
            $gallery = json_decode((string) $entry['gallery_json'], true);
            if (is_array($gallery) && $gallery !== []):
        ?>
          <div class="cms-gallery-grid" role="list">
            <?php foreach ($gallery as $g):
                $gsrc = is_array($g) ? (string) ($g['src'] ?? '') : (string) $g;
                $galt = is_array($g) ? (string) ($g['alt'] ?? $entry['title']) : (string) $entry['title'];
                if ($gsrc === '') {
                    continue;
                }
            ?>
              <figure class="cms-gallery-item" role="listitem">
                <?= arowin_img($gsrc, $galt, ['width' => 800, 'height' => 600]) ?>
              </figure>
            <?php endforeach; ?>
          </div>
        <?php endif; endif; ?>

        <?php if ($body !== ''): ?>
          <div class="prose" itemprop="articleBody"><?= $body ?></div>
        <?php endif; ?>

        <?= arowin_render_internal_links($related, 'Related on Arowin') ?>
      </div>
    </div>
  </article>
</main>
<?php require dirname(__DIR__, 2) . '/includes/page-end.php'; ?>
