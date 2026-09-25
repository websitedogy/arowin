<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$allowedTopics = [
    'node', 'matching', 'payouts', 'technical', 'partnership', 'compliance', 'other',
];
$formError = null;
$formOld = [
    'first_name'   => '',
    'last_name'    => '',
    'email'        => '',
    'phone'        => '',
    'organization' => '',
    'topic'        => '',
    'subject'      => '',
    'message'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formOld = [
        'first_name'   => trim((string) ($_POST['first_name'] ?? '')),
        'last_name'    => trim((string) ($_POST['last_name'] ?? '')),
        'email'        => trim((string) ($_POST['email'] ?? '')),
        'phone'        => trim((string) ($_POST['phone'] ?? '')),
        'organization' => trim((string) ($_POST['organization'] ?? '')),
        'topic'        => trim((string) ($_POST['topic'] ?? '')),
        'subject'      => trim((string) ($_POST['subject'] ?? '')),
        'message'      => trim((string) ($_POST['message'] ?? '')),
    ];
    $consent = isset($_POST['consent']);

    if ($formOld['first_name'] === '' || mb_strlen($formOld['first_name']) > 100) {
        $formError = 'Please enter a valid first name.';
    } elseif ($formOld['last_name'] === '' || mb_strlen($formOld['last_name']) > 100) {
        $formError = 'Please enter a valid last name.';
    } elseif (!filter_var($formOld['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($formOld['email']) > 190) {
        $formError = 'Please enter a valid work email address.';
    } elseif ($formOld['phone'] !== '' && mb_strlen($formOld['phone']) > 40) {
        $formError = 'Phone number is too long.';
    } elseif ($formOld['organization'] !== '' && mb_strlen($formOld['organization']) > 190) {
        $formError = 'Organization / Node ID is too long.';
    } elseif (!in_array($formOld['topic'], $allowedTopics, true)) {
        $formError = 'Please select a valid inquiry type.';
    } elseif ($formOld['subject'] === '' || mb_strlen($formOld['subject']) > 190) {
        $formError = 'Please enter a subject (max 190 characters).';
    } elseif ($formOld['message'] === '' || mb_strlen($formOld['message']) > 4000) {
        $formError = 'Please enter a message (max 4000 characters).';
    } elseif (!$consent) {
        $formError = 'You must agree to the Legal Terms to submit an inquiry.';
    } else {
        try {
            arowin_save_contact_inquiry([
                'first_name'   => $formOld['first_name'],
                'last_name'    => $formOld['last_name'],
                'email'        => $formOld['email'],
                'phone'        => $formOld['phone'],
                'organization' => $formOld['organization'],
                'topic'        => $formOld['topic'],
                'subject'      => $formOld['subject'],
                'message'      => $formOld['message'],
                'consent'      => true,
                'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'   => isset($_SERVER['HTTP_USER_AGENT'])
                    ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255)
                    : null,
            ]);

            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Location: ' . url('contact') . '?sent=1', true, 303);
            exit;
        } catch (Throwable $e) {
            $formError = env('APP_DEBUG', false)
                ? ('Unable to save inquiry: ' . $e->getMessage())
                : 'Unable to save your inquiry right now. Please try again shortly.';
        }
    }
}

$CURRENT_ROUTE = 'contact';
arowin_prepare_page([
    'title'       => 'Contact Arowin | AROWIN Official Support',
    'title_short' => 'Contact Arowin | AROWIN Support',
    'description' => 'Official Arowin (AROWIN / Aro Win) contact — operator support, USDT matching help, partnerships, and legal inquiries. Typical reply within 1 business day.',
    'keywords'    => 'Contact Arowin, AROWIN support, Aro Win help, Arowin Trading Portal contact',
    'canonical'   => '/contact',
    'faqs'        => false,
]);

require dirname(__DIR__, 2) . '/includes/page-start.php';
$sent = isset($_GET['sent']) && $formError === null;
?>
<main class="contact-page">
  <section class="page-hero contact-hero">
    <div class="container">
      <p class="eyebrow">Official Channels</p>
      <h1>Contact Arowin</h1>
      <p>
        Institutional operator support for USDT matching, node registration, and network operations.
        Choose the right desk below — our team responds during business hours.
      </p>
    </div>
  </section>

  <section class="page-content contact-body">
    <div class="container">
      <?php if ($sent): ?>
        <div class="contact-alert" role="status">
          <span class="contact-alert-icon" aria-hidden="true">✓</span>
          <div>
            <strong>Message received</strong>
            <p>Thank you. An Arowin specialist will reply to your email shortly. <?= e($APP['contact_response']) ?></p>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($formError !== null): ?>
        <div class="contact-alert contact-alert-error" role="alert">
          <span class="contact-alert-icon" aria-hidden="true">!</span>
          <div>
            <strong>Submission failed</strong>
            <p><?= e($formError) ?></p>
          </div>
        </div>
      <?php endif; ?>

      <div class="contact-layout">
        <aside class="contact-aside" aria-label="Contact directories">
          <div class="contact-aside-card">
            <h2>Support desks</h2>
            <ul class="contact-channels">
              <li>
                <span class="channel-label">Operator Support</span>
                <a href="mailto:<?= e($APP['contact_email']) ?>"><?= e($APP['contact_email']) ?></a>
                <span class="channel-note">Node activation, matching, payouts</span>
              </li>
              <li>
                <span class="channel-label">Partnerships</span>
                <a href="mailto:<?= e($APP['contact_partners']) ?>"><?= e($APP['contact_partners']) ?></a>
                <span class="channel-note">Integrations &amp; network growth</span>
              </li>
              <li>
                <span class="channel-label">Legal &amp; Compliance</span>
                <a href="mailto:<?= e($APP['contact_legal']) ?>"><?= e($APP['contact_legal']) ?></a>
                <span class="channel-note">Terms, notices, formal requests</span>
              </li>
            </ul>
          </div>

          <div class="contact-aside-card contact-meta">
            <div class="meta-row">
              <span class="meta-label">Business hours</span>
              <span class="meta-value"><?= e($APP['contact_hours']) ?></span>
            </div>
            <div class="meta-row">
              <span class="meta-label">Response time</span>
              <span class="meta-value"><?= e($APP['contact_response']) ?></span>
            </div>
            <div class="meta-row">
              <span class="meta-label">Policies</span>
              <span class="meta-value">
                <a href="<?= e(url('legal-terms')) ?>">Legal Terms</a>
                ·
                <a href="<?= e(url('cookie-settings')) ?>">Cookies</a>
              </span>
            </div>
          </div>

          <div class="contact-aside-card contact-quick">
            <h3>Quick links</h3>
            <a href="<?= e(url('faqs')) ?>">FAQs</a>
            <a href="<?= e(url('usdt-matching')) ?>">USDT Matching</a>
            <a href="<?= e(url('register')) ?>">Register Node</a>
          </div>
        </aside>

        <div class="contact-main">
          <div class="contact-form-panel">
            <div class="contact-form-head">
              <h2>Send an official inquiry</h2>
              <p>Provide accurate details so we can route your request to the correct desk. Submissions are stored securely in our operator CRM.</p>
            </div>

            <form class="contact-form contact-form-pro" method="post" action="<?= e(url('contact')) ?>">
              <div class="form-row">
                <label>
                  First name <span class="req">*</span>
                  <input type="text" name="first_name" required autocomplete="given-name" placeholder="Jane" value="<?= e($formOld['first_name']) ?>">
                </label>
                <label>
                  Last name <span class="req">*</span>
                  <input type="text" name="last_name" required autocomplete="family-name" placeholder="Operator" value="<?= e($formOld['last_name']) ?>">
                </label>
              </div>

              <div class="form-row">
                <label>
                  Work email <span class="req">*</span>
                  <input type="email" name="email" required autocomplete="email" placeholder="you@company.com" value="<?= e($formOld['email']) ?>">
                </label>
                <label>
                  Phone <span class="opt">(optional)</span>
                  <input type="tel" name="phone" autocomplete="tel" placeholder="+1 555 000 0000" value="<?= e($formOld['phone']) ?>">
                </label>
              </div>

              <label>
                Organization / Node ID <span class="opt">(optional)</span>
                <input type="text" name="organization" autocomplete="organization" placeholder="AROWIN-001 or company name" value="<?= e($formOld['organization']) ?>">
              </label>

              <label>
                Inquiry type <span class="req">*</span>
                <select name="topic" required>
                  <option value="" disabled<?= $formOld['topic'] === '' ? ' selected' : '' ?>>Select a department</option>
                  <?php
                  $topicLabels = [
                      'node' => 'Node registration & activation',
                      'matching' => 'USDT matching & pairing',
                      'payouts' => 'Payouts, caps & ledger',
                      'technical' => 'Technical / Operator Terminal',
                      'partnership' => 'Partnership & integrations',
                      'compliance' => 'Legal & compliance',
                      'other' => 'Other',
                  ];
                  foreach ($topicLabels as $value => $label):
                  ?>
                    <option value="<?= e($value) ?>"<?= $formOld['topic'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>

              <label>
                Subject <span class="req">*</span>
                <input type="text" name="subject" required maxlength="120" placeholder="Brief summary of your request" value="<?= e($formOld['subject']) ?>">
              </label>

              <label>
                Message <span class="req">*</span>
                <textarea name="message" required maxlength="4000" placeholder="Include node ID, transaction references, or screenshots details where relevant."><?= e($formOld['message']) ?></textarea>
              </label>

              <label class="form-check">
                <input type="checkbox" name="consent" value="1" required>
                <span>I agree to the <a href="<?= e(url('legal-terms')) ?>">Legal Terms</a> and understand Arowin may contact me regarding this inquiry.</span>
              </label>

              <div class="form-actions">
                <button class="btn btn-primary btn-lg" type="submit">Submit inquiry</button>
                <p class="form-footnote">Encrypted transport via HTTPS. Do not include seed phrases or private keys. Your inquiry is saved to our secure database.</p>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'ContactPage',
    'name' => 'Contact Arowin Trading Portal',
    'url' => url('contact'),
    'isPartOf' => ['@type' => 'WebSite', 'name' => $APP['name'], 'url' => url('home')],
    'mainEntity' => [
        '@type' => 'Organization',
        'name' => $APP['name'],
        'email' => $APP['contact_email'],
        'contactPoint' => [
            [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'email' => $APP['contact_email'],
                'availableLanguage' => ['English'],
                'hoursAvailable' => $APP['contact_hours'],
            ],
            [
                '@type' => 'ContactPoint',
                'contactType' => 'sales',
                'email' => $APP['contact_partners'],
                'availableLanguage' => ['English'],
            ],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php require dirname(__DIR__, 2) . '/includes/page-end.php'; ?>
