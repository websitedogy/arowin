<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$CURRENT_ROUTE = 'yield-estimator';
arowin_prepare_page([
    'title'       => 'Yield Estimator | Arowin Trading Portal',
    'description' => 'Simulate Arowin matching yields: choose node cost, set left/right starters, and estimate USDT pairing profit under the 50-pair daily cap.',
    'canonical'   => '/yield-estimator',
]);

require dirname(__DIR__, 2) . '/includes/page-start.php';
?>
<main>
  <section class="page-hero">
    <div class="container">
      <p class="eyebrow">Tools</p>
      <h1>Yield Estimator</h1>
      <p>Model matching and sponsor commissions on customized left and right downline starter configurations.</p>
    </div>
  </section>

  <section class="page-content">
    <div class="container prose">
      <h2>What you can simulate</h2>
      <ul>
        <li>Base node costs: $150, $350, $750, $1550 USDT</li>
        <li>Left and right starter counts (up to 100)</li>
        <li>Pairs matched = min(left, right, 50)</li>
        <li>Estimated pairing profit at $5.00 USDT per pair</li>
      </ul>
      <p>Use the interactive estimator on the homepage to adjust sliders live.</p>
      <div class="page-cta">
        <a class="btn btn-primary" href="<?= e(url('home')) ?>#binary-plan">Launch Estimator</a>
        <a class="btn btn-outline" href="<?= e(url('register')) ?>">REGISTER NODE</a>
      </div>
    </div>
  </section>
</main>
<?php require dirname(__DIR__, 2) . '/includes/page-end.php'; ?>
