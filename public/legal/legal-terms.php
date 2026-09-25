<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$CURRENT_ROUTE = 'legal-terms';
arowin_prepare_page([
    'title'       => 'Legal Terms | Arowin Trading Company',
    'title_short' => 'Legal Terms',
    'description' => 'Legal terms and conditions for using Arowin Trading Company, USDT matching services, and operator node registration.',
    'canonical'   => '/legal/terms',
    'faqs'        => false,
]);
arowin_apply_seo_overrides('/legal/terms');

$crumbsHtml = arowin_render_breadcrumbs([
    ['name' => 'Home', 'url' => url('home')],
    ['name' => 'Legal Terms'],
]);

require dirname(__DIR__, 2) . '/includes/page-start.php';
?>
<main>
  <section class="page-hero" aria-labelledby="legal-heading">
    <div class="container">
      <?= $crumbsHtml ?>
      <p class="eyebrow">Legal</p>
      <h1 id="legal-heading">Legal Terms</h1>
      <p>These terms govern access to Arowin Trading Company, node registration, and USDT matching features. Last updated <?= e(date('F j, Y')) ?>.</p>
    </div>
  </section>

  <section class="page-content">
    <div class="container">
      <div class="prose">
        <h2>1. Acceptance</h2>
        <p>By accessing Arowin you agree to these Legal Terms and any policies referenced here, including Cookie Settings.</p>

        <h2>2. Services</h2>
        <p>Arowin provides binary network tooling, USDT matching simulations, and related operator interfaces. Features may change as the platform evolves.</p>

        <h2>3. Eligibility</h2>
        <p>You must be legally able to use digital-asset services in your jurisdiction. You are responsible for complying with local laws regarding USDT and network marketing activity.</p>

        <h2>4. Yield &amp; risk notice</h2>
        <p>Matching bonuses, pair caps, and estimator outputs are illustrative platform rules — not financial advice. Crypto and network marketing involve risk of loss.</p>

        <h2>5. Accounts &amp; nodes</h2>
        <p>You must provide accurate registration details and secure your operator credentials. You are responsible for activity under your node.</p>

        <h2>6. Prohibited use</h2>
        <p>Do not abuse matching engines, attempt unauthorized access, manipulate ledgers, or use Arowin for unlawful activity.</p>

        <h2>7. Limitation of liability</h2>
        <p>To the fullest extent permitted by law, Arowin is not liable for indirect, incidental, or consequential damages arising from platform use.</p>

        <h2>8. Contact</h2>
        <p>Legal questions: <a href="mailto:<?= e($APP['contact_email']) ?>"><?= e($APP['contact_email']) ?></a> or visit our <a href="<?= e(url('contact')) ?>">Contact</a> page.</p>
      </div>

      <div class="page-cta" role="group" aria-label="Legal page actions">
        <a class="btn btn-outline" href="<?= e(url('cookie-settings')) ?>">Cookie Settings</a>
        <a class="btn btn-outline" href="<?= e(url('contact')) ?>">Contact Support</a>
        <a class="btn btn-primary" href="<?= e(url('home')) ?>">Back to Home</a>
      </div>
    </div>
  </section>
</main>
<?php require dirname(__DIR__, 2) . '/includes/page-end.php'; ?>
