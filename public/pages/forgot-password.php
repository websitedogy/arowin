<?php
/**
 * Forgot password — email new login credentials
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$AROWIN_BOOT_LIGHT = true;
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/forgot-password-repository.php';

$CURRENT_ROUTE = 'forgot-password';
arowin_prepare_page([
    'title'       => 'Forgot Password | Arowin Trading Portal',
    'title_short' => 'Forgot Password | Arowin',
    'description' => 'Recover your Arowin User ID login. We email new credentials to your registered email.',
    'keywords'    => 'Arowin forgot password, AROWIN reset password, Arowin login recovery',
    'canonical'   => '/forgot-password',
    'faqs'        => false,
]);

$loginUrl = url('login');
$forgotUrl = url('forgot-password');
$registerUrl = url('register');

if (arowin_is_logged_in()) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('dashboard'), true, 303);
    exit;
}

$errors = [];
$success = '';
$userIdValue = '';
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userIdValue = arowin_normalize_sponsor_id((string) ($_POST['user_id'] ?? ''));
    $emailValue = strtolower(trim((string) ($_POST['email'] ?? '')));

    if ($userIdValue === '') {
        $errors[] = 'User ID is required.';
    }
    if ($emailValue === '' || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter the email registered on this account.';
    }

    if ($errors === []) {
        try {
            $result = arowin_forgot_password_reset($userIdValue, $emailValue);
            if (empty($result['ok'])) {
                $errors[] = (string) ($result['message'] ?? 'Could not process request.');
            } else {
                $_SESSION['arowin_flash'] = (string) ($result['message'] ?? 'Check your email for login credentials.');
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                header('Location: ' . $loginUrl, true, 303);
                exit;
            }
        } catch (Throwable $e) {
            error_log('Forgot password error: ' . $e->getMessage());
            $errors[] = env('APP_DEBUG', false)
                ? ('Error: ' . $e->getMessage())
                : 'Unable to process right now. Please try again.';
        }
    }
}

$flash = '';
if (!empty($_SESSION['arowin_flash'])) {
    $flash = (string) $_SESSION['arowin_flash'];
    unset($_SESSION['arowin_flash']);
}

$cssLogin = asset_v('css/login-ui.css');
$cssIcons = asset_v('css/auth-icons.css');
if (function_exists('arowin_perf_header')) {
    arowin_perf_header();
}
?>
<!DOCTYPE html>
<html lang="<?= e($APP['language'] ?? 'en') ?>">
<head>
<?php
$SEO_META = ['json_ld' => false, 'preconnect' => true];
require dirname(__DIR__, 2) . '/includes/seo-meta.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800&family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="preload" href="<?= e($cssLogin) ?>" as="style">
<link rel="stylesheet" href="<?= e($CSS_HREF) ?>">
<link rel="stylesheet" href="<?= e($CSS_NAVBAR_HREF) ?>">
<link rel="stylesheet" href="<?= e($CSS_FOOTER_HREF) ?>">
<link rel="stylesheet" href="<?= e($cssLogin) ?>">
<link rel="stylesheet" href="<?= e($cssIcons) ?>">
</head>
<body class="login-page">
<?php arowin_flush_response(); ?>

<?php require dirname(__DIR__, 2) . '/includes/navbar.php'; ?>

<main class="login-stage">
  <div class="login-shell">
    <div class="login-brand">
      <div class="login-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2.5L21.5 12L12 21.5L2.5 12L12 2.5Z" fill="#ff8c1a"/>
          <path d="M12 7L17 12L12 17L7 12L12 7Z" fill="#000"/>
        </svg>
      </div>
      <h1>Aro<span>win</span></h1>
      <p>Recover operator access with your registered email</p>
    </div>

    <section class="login-panel" aria-labelledby="forgot-heading">
      <div class="login-panel-head">
        <h2 id="forgot-heading">Forgot password</h2>
        <p>Remembered it? <a href="<?= e($loginUrl) ?>">Back to login</a></p>
      </div>

      <?php if ($flash !== ''): ?>
        <div class="login-alert" role="status" style="border-color:rgba(16,185,129,.35);background:rgba(16,185,129,.08)">
          <strong>Check your inbox</strong>
          <ul><li><?= e($flash) ?></li></ul>
        </div>
      <?php endif; ?>

      <?php if ($errors !== []): ?>
        <div class="login-alert" role="alert">
          <strong>Unable to continue</strong>
          <ul>
            <?php foreach ($errors as $err): ?>
              <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= e($forgotUrl) ?>" id="forgotForm" class="login-form" novalidate>
        <div class="login-field">
          <label for="userId">User ID <span class="req">*</span></label>
          <input
            type="text"
            id="userId"
            name="user_id"
            placeholder="ARW48291736"
            value="<?= e($userIdValue) ?>"
            required
            autocomplete="username"
            autofocus
            spellcheck="false"
          >
        </div>

        <div class="login-field">
          <label for="email">Registered email <span class="req">*</span></label>
          <input
            type="email"
            id="email"
            name="email"
            placeholder="your@email.com"
            value="<?= e($emailValue) ?>"
            required
            autocomplete="email"
          >
          <p class="login-hint">Must match the email on this User ID. We will email a new password.</p>
        </div>

        <button type="submit" class="login-submit" id="submitBtn">Email my credentials</button>
      </form>

      <div class="login-meta">
        <a href="<?= e($registerUrl) ?>">Create account</a>
        <a href="<?= e(url('contact')) ?>">Need help?</a>
      </div>
    </section>
  </div>
</main>

<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>

<script>
const forgotForm = document.getElementById('forgotForm');
if (forgotForm) {
  forgotForm.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    if (!btn) return;
    btn.textContent = 'Sending…';
    btn.setAttribute('aria-busy', 'true');
    requestAnimationFrame(function() { btn.disabled = true; });
  });
  const uid = document.getElementById('userId');
  if (uid) {
    uid.addEventListener('input', function() {
      const pos = this.selectionStart;
      this.value = this.value.toUpperCase();
      this.setSelectionRange(pos, pos);
    });
  }
}
</script>
<script src="<?= e($JS_NAVBAR_HREF) ?>" defer></script>
<?php require dirname(__DIR__, 2) . '/includes/pwa-register.php'; ?>
</body>
</html>
