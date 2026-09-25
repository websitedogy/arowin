<?php
/**
 * Site search — SearchAction target
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/cms-repository.php';

$q = trim((string) ($_GET['q'] ?? ''));
$results = $q !== '' ? arowin_cms_search($q, 40) : [];

$CURRENT_ROUTE = 'search';
arowin_prepare_page([
    'title' => $q !== ''
        ? arowin_auto_title('Search results for “' . $q . '”')
        : arowin_auto_title('Search Arowin Trading Company'),
    'title_short' => 'Search Arowin',
    'description' => 'Search official Arowin Trading Company pages, products, blog, news, and resources.',
    'keywords' => 'Arowin search, Arowin Trading Company, AROWIN',
    'canonical' => '/search' . ($q !== '' ? '?q=' . rawurlencode($q) : ''),
    'robots' => $q !== '' ? 'noindex, follow' : 'index, follow',
    'faqs' => false,
]);
arowin_apply_seo_overrides('/search');

$crumbsHtml = arowin_render_breadcrumbs([
    ['name' => 'Home', 'url' => url('home')],
    ['name' => 'Search'],
]);

require dirname(__DIR__, 2) . '/includes/page-start.php';
?>
<main>
  <section class="page-hero" aria-labelledby="search-heading">
    <div class="container">
      <?= $crumbsHtml ?>
      <p class="eyebrow">Arowin Trading Company</p>
      <h1 id="search-heading">Search</h1>
      <p>Find official Arowin pages, plans, products, and articles.</p>
      <form class="cms-search-form" action="<?= e(url('search')) ?>" method="get" role="search">
        <label class="sr-only" for="site-search-q">Search query</label>
        <input id="site-search-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search Arowin…" required minlength="2" autocomplete="off">
        <button class="btn btn-primary" type="submit">Search</button>
      </form>
    </div>
  </section>

  <section class="page-content">
    <div class="container">
      <?php if ($q === ''): ?>
        <p class="section-lead">Enter a term such as <strong>Arowin</strong>, binary plan, or USDT matching.</p>
      <?php elseif ($results === []): ?>
        <p class="section-lead">No results for “<?= e($q) ?>”. Try <a href="<?= e(url('faqs')) ?>">FAQs</a> or <a href="<?= e(url('about')) ?>">About Arowin</a>.</p>
      <?php else: ?>
        <p class="section-lead"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for “<?= e($q) ?>”.</p>
        <ul class="cms-search-results">
          <?php foreach ($results as $r): ?>
            <li>
              <a href="<?= e((string) $r['url']) ?>"><strong><?= e((string) $r['title']) ?></strong></a>
              <span class="cms-result-type"><?= e($r['type'] === 'page' ? 'Page' : arowin_cms_type_label((string) $r['type'])) ?></span>
              <?php if (!empty($r['excerpt'])): ?>
                <p><?= e(arowin_auto_description((string) $r['excerpt'], 140)) ?></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php require dirname(__DIR__, 2) . '/includes/page-end.php'; ?>
