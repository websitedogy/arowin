<?php
/**
 * Admin login — /admin
 */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';

if (arowin_is_admin_logged_in()) {
    admin_redirect('/dashboard');
}

$error = '';
$usernameValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameValue = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
        $error = 'Session expired. Please try again.';
    } elseif (!arowin_admin_credentials_configured()) {
        $error = 'Admin credentials are not configured. Set ADMIN_USER and ADMIN_PASSWORD in .env.';
    } elseif ($usernameValue === '' || $password === '') {
        $error = 'Username and password are required.';
    } elseif (arowin_admin_authenticate($usernameValue, $password)) {
        arowin_admin_login($usernameValue);
        admin_redirect('/dashboard');
    } else {
        $error = 'Invalid username or password.';
    }
}

$csrf = arowin_csrf_token();
$brand = (string) ($APP['short_name'] ?? 'AROWIN');
$appName = (string) ($APP['name'] ?? 'Arowin Trading Portal');
$year = (int) date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Sign In — <?= e($appName) ?></title>
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="theme-color" content="#0a0a0a">
  <link rel="icon" href="<?= e(arowin_abs($BASE, '/favicon.ico')) ?>" sizes="any">
  <link rel="icon" type="image/svg+xml" href="<?= e(img('favicon.svg')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"></noscript>
  <link rel="stylesheet" href="<?= e(admin_asset('css/admin-login.css')) ?>">
</head>
<body class="admin-login">
  <div class="admin-login-shell">
    <aside class="admin-brand" aria-label="Arowin admin">
      <div class="admin-brand-bg" aria-hidden="true"></div>

      <a class="admin-brand-mark" href="<?= e(admin_url()) ?>">
        <span class="word"><?= e($brand) ?></span>
        <span class="dot" aria-hidden="true"></span>
      </a>

      <div class="admin-brand-copy">
        <p class="admin-eyebrow">Admin access</p>
        <h1>Sign in to the <span>control desk</span></h1>
        <p>Review package purchases and verify USDT settlements for the trading portal.</p>
      </div>

      <p class="admin-brand-foot">Authorized personnel only</p>
    </aside>

    <main class="admin-form-pane">
      <div class="admin-form-panel">
        <header class="admin-form-header">
          <h2>Admin sign in</h2>
          <p>Use your admin username and password to continue.</p>
        </header>

        <?php if ($error !== ''): ?>
          <div class="admin-alert" role="alert">
            <i class="fa fa-circle-exclamation" aria-hidden="true"></i>
            <span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <!-- action="" posts to the current URL (keeps trailing slash; preserves POST) -->
        <form method="post" action="" id="adminLoginForm" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

          <div class="admin-field">
            <label for="username">Username</label>
            <input
              id="username"
              name="username"
              type="text"
              value="<?= e($usernameValue) ?>"
              placeholder="Enter username"
              required
              autocomplete="username"
              autofocus
            >
          </div>

          <div class="admin-field">
            <label for="password">Password</label>
            <div class="admin-password">
              <input
                id="password"
                name="password"
                type="password"
                placeholder="Enter password"
                required
                autocomplete="current-password"
              >
              <button type="button" class="admin-password-toggle" data-password-toggle aria-label="Show password">
                <svg class="icon-show" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon-hide" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>

          <button type="submit" class="admin-submit" id="adminSubmitBtn">
            Sign in
          </button>
        </form>

        <p class="admin-form-footer">
          © <?= $year ?> <?= e($appName) ?>
          · <a href="<?= e(arowin_abs($BASE, '/')) ?>">Back to site</a>
        </p>
      </div>
    </main>
  </div>

  <script src="<?= e(admin_asset('js/admin-login.js')) ?>" defer></script>
</body>
</html>
