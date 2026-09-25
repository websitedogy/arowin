<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$CURRENT_ROUTE = 'usdt-matching';
arowin_prepare_page([
    'title'       => 'Arowin USDT Matching | AROWIN $5 Pair Bonus',
    'title_short' => 'Arowin USDT Matching | AROWIN',
    'description' => 'Arowin (AROWIN) USDT matching — $5.00 per 1:1 pair (10% of $50 unit), 50-pair daily cap, and $250 max matching income on Starter Protocol.',
    'keywords'    => 'Arowin USDT matching, AROWIN matching income, Aro Win pair bonus, Arowin $5 matching',
    'canonical'   => '/usdt-matching',
]);

require dirname(__DIR__, 2) . '/includes/page-start.php';
?>
<main>
  <section class="page-hero">
    <div class="container">
      <p class="eyebrow">USDT Products</p>
      <h1>Arowin USDT Matching</h1>
      <p>Secure AROWIN $5.00 matching bonuses on balanced left/right starter pairs — paid in USDT with clear daily ceilings.</p>
    </div>
  </section>

  <section class="page-content">
    <div class="container">
      <div class="page-grid">
        <article class="page-card">
          <h3>$5.00 / pair</h3>
          <p>Each validated 1:1 starter pair credits $5.00 USDT to your matching ledger.</p>
        </article>
        <article class="page-card">
          <h3>50 pairs / day</h3>
          <p>Starter Protocol caps matching at 50 pairs daily for predictable yield math.</p>
        </article>
        <article class="page-card">
          <h3>$250 daily max</h3>
          <p>Maximum Starter matching income is $250.00 USDT per day before rank expansion.</p>
        </article>
      </div>
      <div class="page-cta">
        <a class="btn btn-primary" href="<?= e(url('register')) ?>">REGISTER NODE</a>
        <a class="btn btn-outline" href="<?= e(url('yield-estimator')) ?>">Open Yield Estimator</a>
      </div>
    </div>
  </section>
</main>
<?php require dirname(__DIR__, 2) . '/includes/page-end.php'; ?>
