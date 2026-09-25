<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$CURRENT_ROUTE = 'binary-plan';
arowin_prepare_page([
    'title'       => 'Arowin Binary Plan | AROWIN Left Right Matching',
    'title_short' => 'Arowin Binary Plan | AROWIN',
    'description' => 'Arowin (AROWIN) binary plan — left/right leg balancing, 1:1 matching, carry-forward volume, and USDT pair payouts on the Trading Portal.',
    'keywords'    => 'Arowin binary plan, AROWIN binary, Aro Win binary, Arowin left right matching',
    'canonical'   => '/binary-plan',
]);

require dirname(__DIR__, 2) . '/includes/page-start.php';
?>
<main>
  <section class="page-hero">
    <div class="container">
      <p class="eyebrow">Network Design</p>
      <h1>Arowin Binary Plan</h1>
      <p>AROWIN payouts follow the absolute minimum starters between left and right structures — updated continuously as your downline grows.</p>
    </div>
  </section>

  <section class="page-content">
    <div class="container prose">
      <h2>How pairing works</h2>
      <ul>
        <li>Place starters on left and right node legs</li>
        <li>System matches 1:1 using the weaker-leg count</li>
        <li>Unmatched stronger-leg starters carry forward</li>
        <li>Each match routes USDT to your pairing reward ledger</li>
      </ul>
      <h2>Rank expansion</h2>
      <p>Ascend into Elite, Bronze, Silver, and Gold ranks to increase pair income and daily capping limits beyond Starter Protocol.</p>
      <div class="page-cta">
        <a class="btn btn-primary" href="<?= e(url('usdt-matching')) ?>">View USDT Matching</a>
        <a class="btn btn-outline" href="<?= e(url('faqs')) ?>">Read FAQs</a>
      </div>
    </div>
  </section>
</main>
<?php require dirname(__DIR__, 2) . '/includes/page-end.php'; ?>
