<?php
/**
 * Arowin — Operator login (password + optional 2FA PIN / biometric)
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$AROWIN_BOOT_LIGHT = true;
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/security-repository.php';

$CURRENT_ROUTE = 'sign-in';
arowin_prepare_page([
    'title'       => 'Arowin Login | AROWIN Operator Sign In — Trading Portal',
    'title_short' => 'Arowin Login | AROWIN Sign In',
    'description' => 'Official Arowin (AROWIN / Aro Win) login. Sign in to your Trading Portal operator node with User ID and password.',
    'keywords'    => 'Arowin login, AROWIN login, Aro Win login, Arowin sign in, Arowin Trading Portal login',
    'canonical'   => '/login',
    'faqs'        => false,
]);

$loginUrl = url('login');
$registerUrl = url('register');
$dashboardUrl = url('dashboard');

$errors = [];
$userIdValue = '';
$step = 'password'; // password | pin
$pwaUnlock = false;
$forcePassword = isset($_GET['password']) && (string) $_GET['password'] === '1';
$allowAddAccount = isset($_GET['add']) && (string) $_GET['add'] === '1';
$pendingUser = arowin_security_pending_2fa_username();
if ($pendingUser !== '') {
    $step = 'pin';
    $userIdValue = $pendingUser;
    $pwaUnlock = arowin_security_pending_is_pwa_unlock();
}
$rememberedPwaUid = arowin_security_remembered_pwa_uid();
$alreadySignedIn = arowin_is_logged_in();
$signedInUid = $alreadySignedIn
    ? arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? ''))
    : '';
$continueDashUrl = $alreadySignedIn ? url('dashboard') : '';

// Already signed in → go to dashboard (normal). Use /login?add=1 only to open a second ID.
if (
    $alreadySignedIn
    && !$allowAddAccount
    && $step === 'password'
    && $_SERVER['REQUEST_METHOD'] !== 'POST'
    && !isset($_GET['ajax'])
) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $dashboardUrl, true, 303);
    exit;
}

// 2FA remembered device: skip password form — go straight to security PIN
// Skip when adding a second ID, or when already signed in.
if (
    !$forcePassword
    && !$alreadySignedIn
    && $step === 'password'
    && $_SERVER['REQUEST_METHOD'] !== 'POST'
    && $rememberedPwaUid !== ''
    && !isset($_GET['ajax'])
) {
    $started = arowin_security_begin_pin_challenge($rememberedPwaUid);
    if (!empty($started['ok'])) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . $loginUrl . '?step=pin&pwa=1', true, 303);
        exit;
    }
}
// JSON API for biometric (same page)
if (isset($_GET['ajax']) && (string) $_GET['ajax'] === '1') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    $ajaxAction = (string) ($_POST['action'] ?? $_GET['action'] ?? '');
    try {
        if ($ajaxAction === 'biometric_options') {
            $uid = arowin_normalize_sponsor_id((string) ($_POST['user_id'] ?? ''));
            echo json_encode(arowin_security_biometric_login_options($uid), JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($ajaxAction === 'biometric_login') {
            $uid = arowin_normalize_sponsor_id((string) ($_POST['user_id'] ?? ''));
            $result = arowin_security_complete_biometric_login(
                $uid,
                (string) ($_POST['credential_id'] ?? ''),
                (string) ($_POST['challenge'] ?? '')
            );
            if (!empty($result['ok']) && empty($result['needs_2fa'])) {
                $_SESSION['arowin_flash'] = 'Welcome back, ' . (string) ($result['user']['full_name'] ?? '') . '.';
                $result['redirect'] = url('dashboard');
            }
            echo json_encode($result, JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($ajaxAction === 'pwa_2fa_check') {
            $uid = arowin_normalize_sponsor_id((string) ($_POST['user_id'] ?? ''));
            $status = arowin_security_status($uid);
            echo json_encode([
                'ok' => true,
                'enabled' => !empty($status['two_fa_enabled']),
                'username' => $uid,
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($ajaxAction === 'pwa_pin_start') {
            $uid = arowin_normalize_sponsor_id((string) ($_POST['user_id'] ?? ''));
            $result = arowin_security_begin_pin_challenge($uid);
            if (!empty($result['ok'])) {
                $result['redirect'] = $loginUrl . '?step=pin&pwa=1';
            }
            echo json_encode($result, JSON_UNESCAPED_SLASHES);
            exit;
        }
        echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'message' => 'Biometric request failed.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'password');

    if ($action === 'verify_2fa') {
        $pin = (string) ($_POST['pin'] ?? '');
        $result = arowin_security_complete_2fa($pin);
        if (!empty($result['ok'])) {
            $_SESSION['arowin_flash'] = 'Welcome back.';
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Location: ' . url('dashboard'), true, 303);
            exit;
        }
        $errors[] = (string) ($result['message'] ?? 'Incorrect PIN.');
        $step = 'pin';
        $userIdValue = arowin_security_pending_2fa_username();
    } elseif ($action === 'cancel_2fa') {
        unset($_SESSION['arowin_2fa_pending']);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . $loginUrl . '?password=1', true, 303);
        exit;
    } else {
        $userIdValue = arowin_normalize_sponsor_id((string) ($_POST['user_id'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($userIdValue === '') {
            $errors[] = 'User ID is required.';
        }
        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        if ($errors === []) {
            try {
                $user = arowin_authenticate($userIdValue, $password);
                if (!$user) {
                    $errors[] = 'Invalid User ID or password.';
                } else {
                    $gate = arowin_user_can_sign_in($user);
                    if (empty($gate['ok'])) {
                        $errors[] = (string) $gate['message'];
                    } else {
                    $gateLogin = arowin_security_after_password($user);
                    if (empty($gateLogin['ok'])) {
                        $errors[] = (string) ($gateLogin['message'] ?? 'Unable to sign in.');
                    } elseif (!empty($gateLogin['needs_2fa'])) {
                        while (ob_get_level() > 0) {
                            ob_end_clean();
                        }
                        header('Location: ' . $loginUrl . '?step=pin', true, 303);
                        exit;
                    } else {
                    $_SESSION['arowin_flash'] = 'Welcome back, ' . $user['full_name'] . '.';
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    header('Location: ' . url('dashboard'), true, 303);
                    exit;
                    }
                    }
                }
            } catch (Throwable $e) {
                error_log('Login error: ' . $e->getMessage());
                $errors[] = env('APP_DEBUG', false)
                    ? ('Login failed: ' . $e->getMessage())
                    : 'Unable to sign in right now. Please try again.';
            }
        }
    }
}

if (isset($_GET['step']) && (string) $_GET['step'] === 'pin' && arowin_security_pending_2fa_username() !== '') {
    $step = 'pin';
    $userIdValue = arowin_security_pending_2fa_username();
    $pwaUnlock = arowin_security_pending_is_pwa_unlock() || (isset($_GET['pwa']) && (string) $_GET['pwa'] === '1');
}

$flash = '';
if (!empty($_SESSION['arowin_flash']) && $step === 'password') {
    $flash = (string) $_SESSION['arowin_flash'];
    unset($_SESSION['arowin_flash']);
}
if ($flash === '' && isset($_GET['logged_out']) && (string) $_GET['logged_out'] === '1' && $step === 'password') {
    $flash = 'You have been signed out.';
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
      <p>Operator terminal access for your AROWIN node</p>
    </div>

    <section class="login-panel" aria-labelledby="login-heading">
      <div class="login-panel-head">
        <h2 id="login-heading"><?= $step === 'pin' ? ($pwaUnlock ? 'Two-factor authentication' : 'Security PIN') : 'Sign in' ?></h2>
        <p>
          <?php if ($step === 'pin'): ?>
            Enter your 6-digit code for <?= e($userIdValue) ?>
          <?php else: ?>
            New here? <a href="<?= e($registerUrl) ?>">Create an account</a>
            <?php if ($allowAddAccount && $alreadySignedIn && $continueDashUrl !== ''): ?>
              · <a href="<?= e($continueDashUrl) ?>">Open dashboard</a>
            <?php endif; ?>
          <?php endif; ?>
        </p>
      </div>

      <?php if ($allowAddAccount && $alreadySignedIn && $step === 'password'): ?>
        <div class="login-alert login-alert--info" role="status">
          <strong>Add another ID</strong>
          <ul>
            <li>
              Signed in as <strong><?= e($signedInUid !== '' ? $signedInUid : 'another account') ?></strong>.
              Sign in below to open a second dashboard in this tab — other tabs keep their ID.
            </li>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($flash !== ''): ?>
        <?php
        $flashIsEmail = (bool) preg_match('/email|inbox|credentials sent|check your mail/i', $flash);
        $flashIsLogout = (bool) preg_match('/signed out|logged out/i', $flash);
        $flashTitle = $flashIsEmail ? 'Email sent' : ($flashIsLogout ? 'Signed out' : 'Success');
        $flashClass = $flashIsLogout ? 'login-alert--info login-alert--compact' : 'login-alert--ok';
        ?>
        <div class="login-alert <?= e($flashClass) ?>" role="status">
          <strong><?= e($flashTitle) ?></strong>
          <?php if (!$flashIsLogout): ?>
            <ul><li><?= e($flash) ?></li></ul>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($errors !== []): ?>
        <div class="login-alert" role="alert">
          <strong>Unable to sign in</strong>
          <ul>
            <?php foreach ($errors as $err): ?>
              <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($step === 'pin'): ?>
        <form method="POST" action="<?= e($loginUrl) ?>" class="login-form" novalidate>
          <input type="hidden" name="action" value="verify_2fa">
          <div class="login-field">
            <label for="pin">6-digit PIN <span class="req">*</span></label>
            <input
              type="password"
              id="pin"
              name="pin"
              inputmode="numeric"
              pattern="[0-9]{6}"
              maxlength="6"
              placeholder="••••••"
              required
              autocomplete="one-time-code"
              autofocus
            >
          </div>
          <div class="login-actions">
            <button type="submit" class="login-submit">Verify &amp; continue</button>
          </div>
        </form>
        <form method="POST" action="<?= e($loginUrl) ?>" class="login-actions">
          <input type="hidden" name="action" value="cancel_2fa">
          <button type="submit" class="login-submit login-submit--ghost"><?= $pwaUnlock ? 'Use password instead' : 'Back to password' ?></button>
        </form>
      <?php else: ?>
        <form method="POST" action="<?= e($loginUrl) ?>" id="loginForm" class="login-form" novalidate>
          <input type="hidden" name="action" value="password">
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
            <div class="login-field-head">
              <label for="password">Password <span class="req">*</span></label>
              <a href="<?= e(url('forgot-password')) ?>">Forgot password?</a>
            </div>
            <div class="login-pass">
              <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                required
                autocomplete="current-password"
              >
              <button type="button" class="login-pass-toggle" onclick="togglePassword('password', this)" aria-label="Show password">
                <svg class="icon-show" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon-hide" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>

          <div class="login-actions">
            <button type="submit" class="login-submit" id="submitBtn">Sign In</button>
            <button type="button" class="login-submit login-submit--bio" id="bioLoginBtn" hidden>Use Fingerprint</button>
          </div>
        </form>
      <?php endif; ?>

      <div class="login-meta">
        <span>Secure hashed session</span>
        <a href="<?= e(url('contact')) ?>">Need help?</a>
      </div>
    </section>

    <div class="login-trust" aria-hidden="true">
      <span><i class="auth-ico auth-ico-lock"></i> Encrypted</span>
      <span><i class="auth-ico auth-ico-shield"></i> Private</span>
      <span><i class="auth-ico auth-ico-bolt"></i> Instant</span>
    </div>
  </div>
</main>


<script>
function togglePassword(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input || !btn) return;
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btn.classList.toggle('is-visible', show);
  btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
}

function isArowinPwa() {
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    window.matchMedia('(display-mode: fullscreen)').matches ||
    window.navigator.standalone === true
  );
}

/** Biometric: phone browser or mobile installed web app only */
function isArowinMobileBioContext() {
  const ua = navigator.userAgent || '';
  if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|CriOS|FxiOS/i.test(ua)) {
    return true;
  }
  // iPadOS desktop-mode Safari
  if (navigator.platform === 'MacIntel' && (navigator.maxTouchPoints || 0) > 1) {
    return true;
  }
  return false;
}

const loginAjaxUrl = <?= json_encode($loginUrl . '?ajax=1', JSON_UNESCAPED_SLASHES) ?>;
const rememberedPwaUid = <?= json_encode($rememberedPwaUid, JSON_UNESCAPED_SLASHES) ?>;
const currentLoginStep = <?= json_encode($step, JSON_UNESCAPED_SLASHES) ?>;
const alreadySignedIn = <?= $alreadySignedIn ? 'true' : 'false' ?>;
const allowAddAccount = <?= $allowAddAccount ? 'true' : 'false' ?>;

const userIdInput = document.getElementById('userId');
if (userIdInput) {
  userIdInput.addEventListener('input', function() {
    const pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
    if (typeof maybeShowBio === 'function') maybeShowBio();
  });
}

const loginForm = document.getElementById('loginForm');
if (loginForm) {
  loginForm.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    if (!btn) return;
    btn.innerHTML = '<i class="auth-ico auth-ico-spin" aria-hidden="true"></i> Signing in…';
    btn.setAttribute('aria-busy', 'true');
    requestAnimationFrame(function() { btn.disabled = true; });
  });
}

(async function bootPwaPinUnlock() {
  if (currentLoginStep === 'pin') return;
  // Don't steal focus when adding a second account, or when already signed in.
  if (alreadySignedIn || allowAddAccount) return;
  // Server already handled cookie-remembered users; also catch localStorage (any browser / PWA)
  const params = new URLSearchParams(location.search);
  if (params.get('password') === '1' || params.get('logged_out') === '1') {
    try {
      localStorage.removeItem('arowin_pwa_uid');
      localStorage.removeItem('arowin_pwa_2fa');
    } catch (_) {}
    return;
  }

  let uid = rememberedPwaUid || '';
  try {
    const ls2fa = localStorage.getItem('arowin_pwa_2fa') === '1';
    const lsUid = (localStorage.getItem('arowin_pwa_uid') || '').trim().toUpperCase();
    if (ls2fa && lsUid) {
      uid = uid || lsUid;
    }
  } catch (_) {}
  uid = String(uid || '').trim().toUpperCase();
  if (uid.length < 5) return;

  try {
    const checkBody = new FormData();
    checkBody.append('action', 'pwa_2fa_check');
    checkBody.append('user_id', uid);
    const checkRes = await fetch(loginAjaxUrl, { method: 'POST', body: checkBody, credentials: 'same-origin' });
    const check = await checkRes.json();
    if (!check || !check.enabled) return;

    const startBody = new FormData();
    startBody.append('action', 'pwa_pin_start');
    startBody.append('user_id', uid);
    const startRes = await fetch(loginAjaxUrl, { method: 'POST', body: startBody, credentials: 'same-origin' });
    const start = await startRes.json();
    if (start && start.ok && start.redirect) {
      try {
        localStorage.setItem('arowin_pwa_uid', uid);
        localStorage.setItem('arowin_pwa_2fa', '1');
      } catch (_) {}
      location.replace(start.redirect);
    }
  } catch (_) {}
})();

(function () {
  const bioBtn = document.getElementById('bioLoginBtn');
  if (!bioBtn || !window.PublicKeyCredential) return;
  if (!isArowinMobileBioContext()) {
    bioBtn.hidden = true;
    return;
  }

  function b64url(buf) {
    const bytes = new Uint8Array(buf);
    let str = '';
    bytes.forEach((b) => { str += String.fromCharCode(b); });
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
  }
  function fromB64url(str) {
    const pad = '='.repeat((4 - (str.length % 4)) % 4);
    const b64 = (str + pad).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(b64);
    const out = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out.buffer;
  }

  window.maybeShowBio = async function () {
    if (!isArowinMobileBioContext()) {
      bioBtn.hidden = true;
      return;
    }
    const uid = (userIdInput?.value || '').trim().toUpperCase();
    if (uid.length < 5) {
      bioBtn.hidden = true;
      return;
    }
    try {
      const body = new FormData();
      body.append('action', 'biometric_options');
      body.append('user_id', uid);
      const res = await fetch(loginAjaxUrl, { method: 'POST', body, credentials: 'same-origin' });
      const data = await res.json();
      bioBtn.hidden = !(data && data.enabled);
      bioBtn._opts = data;
    } catch (_) {
      bioBtn.hidden = true;
    }
  };

  bioBtn.addEventListener('click', async () => {
    if (!isArowinMobileBioContext()) {
      alert('Fingerprint login works only on mobile browser or the mobile web app.');
      return;
    }
    const uid = (userIdInput?.value || '').trim().toUpperCase();
    if (!uid) return;
    try {
      const body = new FormData();
      body.append('action', 'biometric_options');
      body.append('user_id', uid);
      const optRes = await fetch(loginAjaxUrl, { method: 'POST', body, credentials: 'same-origin' });
      const opts = await optRes.json();
      if (!opts || !opts.enabled) {
        alert('Fingerprint is not enabled for this User ID on a registered device.');
        return;
      }
      const assertion = await navigator.credentials.get({
        publicKey: {
          challenge: fromB64url(opts.challenge),
          allowCredentials: [{ type: 'public-key', id: fromB64url(opts.credential_id) }],
          userVerification: 'required',
          timeout: 60000,
        },
      });
      if (!assertion || !assertion.rawId) {
        alert('Fingerprint cancelled.');
        return;
      }
      const done = new FormData();
      done.append('action', 'biometric_login');
      done.append('user_id', uid);
      done.append('credential_id', b64url(assertion.rawId));
      done.append('challenge', opts.challenge);
      const loginRes = await fetch(loginAjaxUrl, { method: 'POST', body: done, credentials: 'same-origin' });
      const result = await loginRes.json();
      if (!result.ok) {
        alert(result.message || 'Fingerprint login failed.');
        return;
      }
      if (result.needs_2fa) {
        location.href = <?= json_encode($loginUrl . '?step=pin', JSON_UNESCAPED_SLASHES) ?>;
        return;
      }
      location.href = result.redirect || <?= json_encode($dashboardUrl, JSON_UNESCAPED_SLASHES) ?>;
    } catch (err) {
      alert(err && err.name === 'NotAllowedError' ? 'Fingerprint cancelled.' : 'Fingerprint login failed on this device.');
    }
  });

  maybeShowBio();
})();
</script>
<script src="<?= e($JS_NAVBAR_HREF) ?>" defer></script>
<?php require dirname(__DIR__, 2) . '/includes/pwa-register.php'; ?>
</body>
</html>
