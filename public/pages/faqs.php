<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$CURRENT_ROUTE = 'faqs';
arowin_prepare_page([
    'title'       => 'Arowin FAQs | Arowin Trading Company Help',
    'title_short' => 'Arowin FAQs',
    'description' => 'Arowin Trading Company FAQs — USDT matching, 1:1 binary pairs, daily caps, sponsor commissions, and node registration answers.',
    'keywords'    => 'Arowin FAQ, Arowin Trading Company help, AROWIN questions, Arowin matching FAQ',
    'canonical'   => '/faqs',
    'faqs'        => true,
]);
arowin_apply_seo_overrides('/faqs');

$crumbsHtml = arowin_render_breadcrumbs([
    ['name' => 'Home', 'url' => url('home')],
    ['name' => 'FAQs'],
]);

require dirname(__DIR__, 2) . '/includes/page-start.php';
?>
<main>
  <section class="page-hero" aria-labelledby="faqs-heading">
    <div class="container">
      <?= $crumbsHtml ?>
      <p class="eyebrow">Support</p>
      <h1 id="faqs-heading">Arowin FAQs</h1>
      <p>Clear answers on AROWIN binary pairing, USDT matching bonuses, daily caps, and how Arowin nodes work.</p>
    </div>
  </section>

  <section class="page-content">
    <div class="container">
      <div class="faq-list" style="margin:0 auto;max-width:860px;">
        <?php foreach (($SEO['faqs'] ?? []) as $i => $faq): ?>
        <details class="faq-item"<?= $i === 0 ? ' open' : '' ?>>
          <summary><?= e($faq['q']) ?></summary>
          <div class="faq-answer"><?= e($faq['a']) ?></div>
        </details>
        <?php endforeach; ?>
      </div>
      <div class="page-cta" style="justify-content:center;">
        <a class="btn btn-primary" href="<?= e(url('contact')) ?>">Still need help?</a>
      </div>
    </div>
  </section>
</main>
<?php require dirname(__DIR__, 2) . '/includes/page-end.php'; ?>
