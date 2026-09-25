<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$CURRENT_ROUTE = 'careers';
arowin_prepare_page([
    'title'       => 'Careers | Arowin Trading Portal',
    'description' => 'Join Arowin Trading Portal. Explore open roles across binary engineering, USDT operations, support, and growth.',
    'canonical'   => '/careers',
]);

require dirname(__DIR__, 2) . '/includes/page-start.php';
?>
<main>
  <section class="page-hero">
    <div class="container">
      <p class="eyebrow">Company</p>
      <h1>Careers at Arowin</h1>
      <p>Help build the next generation of USDT binary infrastructure — matching engines, operator tools, and trust-first product experiences.</p>
    </div>
  </section>

  <section class="page-content">
    <div class="container">
      <div class="prose">
        <h2>Open focus areas</h2>
        <p>We hire builders who care about clarity, security, and operator outcomes.</p>
      </div>
      <div class="page-grid">
        <article class="page-card">
          <h3>Binary / Backend</h3>
          <p>Matching engines, ledger sync, pair validation, and high-availability USDT settlement flows.</p>
        </article>
        <article class="page-card">
          <h3>Product &amp; Design</h3>
          <p>Operator terminal UX, yield estimators, and institutional-grade dashboard experiences.</p>
        </article>
        <article class="page-card">
          <h3>Support &amp; Operations</h3>
          <p>Node onboarding, payout education, and real-time operator success across global networks.</p>
        </article>
      </div>
      <div class="page-cta">
        <a class="btn btn-primary" href="<?= e(url('contact')) ?>">Send your profile</a>
        <a class="btn btn-outline" href="<?= e(url('about')) ?>">About Arowin</a>
      </div>
    </div>
  </section>
</main>
<?php require dirname(__DIR__, 2) . '/includes/page-end.php'; ?>
