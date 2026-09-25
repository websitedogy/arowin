<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$CURRENT_ROUTE = 'about';
arowin_prepare_page([
    'title'       => 'About Arowin Trading Company | Official AROWIN',
    'title_short' => 'About Arowin Trading Company',
    'description' => 'About Arowin Trading Company (AROWIN / Arowin) — institutional binary networks, USDT matching bonuses, and automated node validation for operators worldwide.',
    'keywords'    => 'About Arowin Trading Company, About Arowin, AROWIN company, Arowin official',
    'canonical'   => '/about',
    'faqs'        => false,
]);
arowin_apply_seo_overrides('/about');

$crumbsHtml = arowin_render_breadcrumbs([
    ['name' => 'Home', 'url' => url('home')],
    ['name' => 'About'],
]);

require dirname(__DIR__, 2) . '/includes/page-start.php';
?>
<main>
  <section class="page-hero" aria-labelledby="about-heading">
    <div class="container">
      <?= $crumbsHtml ?>
      <p class="eyebrow">Company</p>
      <h1 id="about-heading">About Arowin Trading Company</h1>
      <p>We build USDT-native binary infrastructure so operators can match legs, clear pairing rewards, and scale nodes with transparent daily caps.</p>
    </div>
  </section>

  <section class="page-content">
    <div class="container prose">
      <h2>Our mission</h2>
      <p>Arowin exists to make institutional-grade binary matching simple, auditable, and fast. Every 1:1 left/right pair is validated automatically and credited in USDT.</p>

      <h2>What we operate</h2>
      <ul>
        <li>Automated binary validation and lightning-fast matching nodes</li>
        <li>$5.00 USDT pairing bonuses with Starter caps of 50 pairs ($250/day)</li>
        <li>Direct referral (sponsor) commissions and rank expansion paths</li>
        <li>Operator terminal visibility for live left/right starter counts</li>
      </ul>

      <h2>Built for operators</h2>
      <p>From first-time starters to multi-node networks, Arowin keeps ledger sync, pair math, and daily ceilings clear — so you can focus on growing balanced legs.</p>

      <div class="page-cta">
        <a class="btn btn-primary" href="<?= e(url('register')) ?>">REGISTER NODE</a>
        <a class="btn btn-outline" href="<?= e(url('contact')) ?>">Contact Us</a>
      </div>
    </div>
  </section>
</main>
<?php require dirname(__DIR__, 2) . '/includes/page-end.php'; ?>
