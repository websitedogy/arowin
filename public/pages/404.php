<?php
/**
 * Custom 404 — brand-safe, crawlable links, no soft-404 cloaking
 */
declare(strict_types=1);

if (!isset($APP)) {
    require dirname(__DIR__, 2) . '/includes/bootstrap.php';
}

http_response_code(404);

$CURRENT_ROUTE = '404';
arowin_prepare_page([
    'title' => 'Page not found | Arowin Trading Company',
    'title_short' => 'Page not found',
    'description' => 'The page you requested was not found on the official Arowin Trading Company website.',
    'canonical' => '/404',
    'robots' => 'noindex, follow',
    'faqs' => false,
]);

// Avoid nesting if already in page-start
if (!headers_sent() || true) {
    require dirname(__DIR__, 2) . '/includes/page-start.php';
}
?>
<main>
  <section class="page-hero" aria-labelledby="error-heading">
    <div class="container">
      <p class="eyebrow">Error 404</p>
      <h1 id="error-heading">This page is not available</h1>
      <p>The URL may be mistyped or the content may have moved. Use the links below to continue on the official Arowin Trading Company site.</p>
      <div class="page-cta" style="margin-top:24px;">
        <a class="btn btn-primary" href="<?= e(url('home')) ?>">Go to homepage</a>
        <a class="btn btn-outline" href="<?= e(url('search')) ?>">Search Arowin</a>
      </div>
    </div>
  </section>
  <section class="page-content">
    <div class="container">
      <?= arowin_render_internal_links([
          ['title' => 'About Arowin Trading Company', 'url' => url('about')],
          ['title' => 'Binary Plan', 'url' => url('binary-plan')],
          ['title' => 'USDT Matching', 'url' => url('usdt-matching')],
          ['title' => 'FAQs', 'url' => url('faqs')],
          ['title' => 'Contact', 'url' => url('contact')],
          ['title' => 'Register Node', 'url' => url('register')],
      ], 'Helpful links') ?>
    </div>
  </section>
</main>
<?php require dirname(__DIR__, 2) . '/includes/page-end.php'; ?>
