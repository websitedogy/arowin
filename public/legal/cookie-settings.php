<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$CURRENT_ROUTE = 'cookie-settings';
arowin_prepare_page([
    'title'       => 'Cookie Settings | Arowin Trading Portal',
    'description' => 'Manage how Arowin Trading Portal uses cookies and similar technologies for performance, security, and preferences.',
    'canonical'   => '/legal/cookies',
]);

require dirname(__DIR__, 2) . '/includes/page-start.php';
?>
<main>
  <section class="page-hero">
    <div class="container">
      <p class="eyebrow">Legal</p>
      <h1>Cookie Settings</h1>
      <p>We use cookies to keep the portal fast, secure, and functional. You can review categories below.</p>
    </div>
  </section>

  <section class="page-content">
    <div class="container">
      <div class="page-grid">
        <article class="page-card">
          <h3>Essential</h3>
          <p>Required for security, session continuity, and core navigation. Always active.</p>
        </article>
        <article class="page-card">
          <h3>Performance</h3>
          <p>Helps us understand page speed and reliability so USDT tools load quickly.</p>
        </article>
        <article class="page-card">
          <h3>Preferences</h3>
          <p>Remembers UI choices such as estimator defaults where available.</p>
        </article>
      </div>

      <div class="prose" style="margin-top:36px;">
        <h2>Your choices</h2>
        <p>Essential cookies cannot be disabled. For marketing or analytics cookies (when enabled), contact <a href="mailto:<?= e($APP['contact_email']) ?>"><?= e($APP['contact_email']) ?></a> or use browser controls to clear stored data.</p>
        <p>See also our <a href="<?= e(url('legal-terms')) ?>">Legal Terms</a>.</p>
      </div>

      <div class="page-cta">
        <a class="btn btn-primary" href="<?= e(url('home')) ?>">Accept &amp; continue</a>
        <a class="btn btn-outline" href="<?= e(url('contact')) ?>">Ask a question</a>
      </div>
    </div>
  </section>
</main>
<?php require dirname(__DIR__, 2) . '/includes/page-end.php'; ?>
