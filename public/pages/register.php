<?php
/**
 * Arowin — Free node registration (BBM-style UI, Arowin backend)
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$AROWIN_BOOT_LIGHT = true;
require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$CURRENT_ROUTE = 'register';
arowin_prepare_page([
    'title'       => 'Arowin Register | AROWIN Node Registration — Trading Portal',
    'title_short' => 'Arowin Register | AROWIN Node Signup',
    'description' => 'Official Arowin (AROWIN / Aro Win) registration. Create your free Trading Portal node, enter sponsor ID, choose binary placement, and get your User ID instantly.',
    'keywords'    => 'Arowin register, AROWIN register, Aro Win signup, Arowin node registration, Arowin Trading Portal register',
    'canonical'   => '/register',
    'faqs'        => false,
]);

$supportEmail = (string) ($APP['contact_email'] ?? 'support@arowin.com');
$supportPhone = (string) env('AROWIN_SUPPORT_PHONE', '');
$supportWhatsapp = (string) env('AROWIN_WHATSAPP', '');
$whatsappUrl = $supportWhatsapp !== ''
    ? 'https://wa.me/' . preg_replace('/\D/', '', $supportWhatsapp)
    : '';
$loginUrl = url('login');
$termsUrl = url('legal-terms');
$registerUrl = url('register');
arowin_ensure_shared_contact_schema();

// Share / copy links must stay on the register form even if a member is already
// logged in on this browser. Bare /register (no invite) still goes to dashboard.
$inviteSponsor = arowin_normalize_sponsor_id((string) ($_POST['share_sponsor'] ?? $_GET['sponsor_id'] ?? ''));
$allowWhileLoggedIn = isset($_GET['check_sponsor'])
    || isset($_GET['for_guest'])
    || isset($_POST['for_guest'])
    || $inviteSponsor !== ''
    || isset($_POST['share_sponsor'])
    || isset($_POST['register'])
    || (isset($_GET['sponsor_id']) && (isset($_GET['full_name']) || isset($_GET['mobile']) || isset($_GET['phone'])));

if (arowin_is_logged_in() && !$allowWhileLoggedIn) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('dashboard'), true, 303);
    exit;
}

/* ── Sponsor AJAX check ─────────────────────────────────────────── */
if (isset($_GET['check_sponsor'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');

    $sid = trim((string) ($_GET['sponsor_id'] ?? ''));
    if ($sid === '') {
        echo json_encode(['status' => 'invalid', 'error' => 'Sponsor ID required']);
        exit;
    }

    try {
        $sponsor = arowin_get_user_by_username($sid);
        if ($sponsor) {
            echo json_encode([
                'status' => 'valid',
                'name'   => (string) $sponsor['full_name'],
            ]);
        } else {
            echo json_encode(['status' => 'invalid', 'error' => 'Sponsor not found']);
        }
    } catch (Throwable $e) {
        error_log('Sponsor validation error: ' . $e->getMessage());
        echo json_encode([
            'status' => 'invalid',
            'error'  => env('APP_DEBUG', false) ? $e->getMessage() : 'Database error',
        ]);
    }
    exit;
}

$errors = [];

$lockSponsor = $inviteSponsor !== ''
    ? $inviteSponsor
    : arowin_normalize_sponsor_id((string) ($_POST['share_sponsor'] ?? $_GET['sponsor_id'] ?? ''));
$lockLeg = function_exists('arowin_normalize_placement_leg')
    ? arowin_normalize_placement_leg((string) ($_POST['share_leg'] ?? $_GET['placement'] ?? ''))
    : strtolower(trim((string) ($_POST['share_leg'] ?? $_GET['placement'] ?? '')));
if ($lockLeg !== 'left' && $lockLeg !== 'right') {
    $lockLeg = '';
}
$sponsorLocked = $lockSponsor !== '';
$placementLocked = $sponsorLocked && $lockLeg !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['register']) || isset($_POST['sponsor_id']))) {
    $sponsor_id = arowin_normalize_sponsor_id((string) ($_POST['sponsor_id'] ?? ''));
    $placement  = trim((string) ($_POST['placement'] ?? ''));
    if ($sponsorLocked) {
        $sponsor_id = $lockSponsor;
    }
    if ($placementLocked) {
        $placement = $lockLeg;
    }
    $full_name  = trim((string) ($_POST['full_name'] ?? ''));
    $mobile     = trim((string) ($_POST['mobile'] ?? ''));
    $email      = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password   = (string) ($_POST['password'] ?? '');
    $confirm_pw = (string) ($_POST['confirm_password'] ?? '');
    $terms      = isset($_POST['terms']);

    if ($sponsor_id === '') {
        $errors[] = 'Sponsor ID is required.';
    }
    if (!in_array($placement, ['left', 'right'], true)) {
        $errors[] = 'Please select a binary placement leg (Left or Right).';
    }
    if ($full_name === '' || mb_strlen($full_name) < 3) {
        $errors[] = 'Full name must be at least 3 characters.';
    }
    if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        $errors[] = 'Enter a valid 10-digit mobile number.';
    }
    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (mb_strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_pw) {
        $errors[] = 'Passwords do not match.';
    }
    if (!$terms) {
        $errors[] = 'You must agree to Terms & Conditions.';
    }

    if ($errors === []) {
        try {
            $sponsorRow = arowin_get_user_by_username($sponsor_id);
            if (!$sponsorRow) {
                $errors[] = 'Invalid Sponsor ID. Use ARWDOGY for company root, or an existing User ID.';
            }

            if ($errors === []) {
                arowin_ensure_shared_contact_schema();
                $newUid = arowin_generate_user_id();
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $canonicalSponsorId = (string) ($sponsorRow['username'] ?? $sponsor_id);

                $newUser = arowin_create_registered_user([
                    'user_id'    => $newUid,
                    'sponsor_id' => $canonicalSponsorId,
                    'placement'  => $placement,
                    'full_name'  => $full_name,
                    'mobile'     => $mobile,
                    'email'      => $email,
                    'password'   => $hashed,
                ]);

                if ($newUser) {
                    arowin_place_in_binary_tree($canonicalSponsorId, $placement, $newUid);

                    $sponsorName = (string) ($sponsorRow['full_name'] ?? $canonicalSponsorId);
                    try {
                        require_once dirname(__DIR__, 2) . '/includes/welcome-login-repository.php';
                        $tokenResult = arowin_issue_welcome_login_token($newUid);
                        if (!empty($tokenResult['ok']) && !empty($tokenResult['url'])) {
                            $mailResult = arowin_send_registration_welcome_email(
                                $email,
                                $full_name,
                                $newUid,
                                $canonicalSponsorId,
                                $sponsorName,
                                (string) $tokenResult['url'],
                                $mobile,
                                $placement
                            );
                            if (empty($mailResult['ok'])) {
                                error_log('Welcome email failed for ' . $newUid . ': ' . ($mailResult['message'] ?? ''));
                            }
                        }
                    } catch (Throwable $mailEx) {
                        error_log('Welcome email exception for ' . $newUid . ': ' . $mailEx->getMessage());
                    }

                    arowin_login_user($newUser);

                    $legLabel = $placement === 'left' ? 'Left Leg (L)' : 'Right Leg (R)';
                    $_SESSION['arowin_flash'] =
                        'Welcome, ' . $full_name . '! Your User ID is ' . $newUid
                        . '. Sponsor: ' . $sponsorName
                        . ' · Placement: ' . $legLabel
                        . '. Account details were sent to ' . $email . '.';

                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    header('Location: ' . url('dashboard'), true, 303);
                    exit;
                }

                $errors[] = 'Registration failed. Please try again.';
            }
        } catch (Throwable $e) {
            error_log('Registration error: ' . $e->getMessage());
            $errors[] = env('APP_DEBUG', false)
                ? ('Database error: ' . $e->getMessage())
                : 'Database error. Please try again.';
        }
    }
}

$default_sponsor_id = $sponsorLocked
    ? $lockSponsor
    : (string) ($_GET['sponsor_id'] ?? '');
$default_placement = $placementLocked
    ? $lockLeg
    : (string) ($_GET['placement'] ?? 'left');
$posted_placement = (string) ($_POST['placement'] ?? $default_placement);
if ($placementLocked) {
    $posted_placement = $lockLeg;
}
if (!in_array($posted_placement, ['left', 'right'], true)) {
    $posted_placement = 'left';
}
$default_full_name = trim((string) ($_GET['full_name'] ?? $_GET['name'] ?? ''));
$default_mobile = preg_replace('/\D+/', '', (string) ($_GET['mobile'] ?? $_GET['phone'] ?? '')) ?? '';
if (strlen($default_mobile) > 10) {
    $default_mobile = substr($default_mobile, -10);
}

$cssRegister = asset_v('css/register-ui.css');
$cssIcons = asset_v('css/auth-icons.css');
if (function_exists('arowin_perf_header')) {
    arowin_perf_header();
}
?>
<!DOCTYPE html>
<html lang="<?= e($APP['language'] ?? 'en') ?>">
<head>
<?php
$SEO_META = ['json_ld' => false, 'preconnect' => false];
require dirname(__DIR__, 2) . '/includes/seo-meta.php';
?>
<link rel="preload" href="<?= e($CSS_HREF) ?>" as="style">
<link rel="stylesheet" href="<?= e($CSS_HREF) ?>">
<link rel="stylesheet" href="<?= e($CSS_NAVBAR_HREF) ?>">
<link rel="stylesheet" href="<?= e($CSS_FOOTER_HREF) ?>">
<link rel="stylesheet" href="<?= e($cssRegister) ?>">
<link rel="stylesheet" href="<?= e($cssIcons) ?>">
</head>
<body class="register-page">
<?php arowin_flush_response(); ?>

<?php require dirname(__DIR__, 2) . '/includes/navbar.php'; ?>

<div class="register-layout">

  <aside class="register-visual" aria-label="Node onboarding">
    <div class="register-visual-bg" aria-hidden="true"></div>
    <div class="register-visual-inner">
      <p class="section-tag">Node Onboarding</p>
      <h2>Initialize your <span>core node</span></h2>
      <p class="register-visual-desc">
        Join Arowin’s institutional binary network. Secure placement under your sponsor,
        receive a permanent User ID, and operate within the USDT matching ledger.
      </p>

      <div class="register-metrics" aria-label="Network highlights">
        <div class="register-metric">
          <strong>$5.00</strong>
          <span>Match bonus</span>
        </div>
        <div class="register-metric">
          <strong>50</strong>
          <span>Pairs / day</span>
        </div>
        <div class="register-metric">
          <strong>1:1</strong>
          <span>Leg pairing</span>
        </div>
      </div>

      <div class="register-steps">
        <div class="register-step">
          <span class="register-step-num">1</span>
          <div>
            <strong>Verify sponsor</strong>
            Confirm your upline with a live sponsor ID check before account creation.
          </div>
        </div>
        <div class="register-step">
          <span class="register-step-num">2</span>
          <div>
            <strong>Lock binary placement</strong>
            Choose Left or Right leg — permanent position for matching volume.
          </div>
        </div>
        <div class="register-step">
          <span class="register-step-num">3</span>
          <div>
            <strong>Receive User ID</strong>
            Instant ARW credentials issued and linked to your selected sponsor.
          </div>
        </div>
      </div>

      <div class="register-assurance">
        <span><i class="auth-ico auth-ico-shield" aria-hidden="true"></i> Encrypted credentials</span>
        <span><i class="auth-ico auth-ico-bolt" aria-hidden="true"></i> Instant ID issuance</span>
        <span><i class="auth-ico auth-ico-tree" aria-hidden="true"></i> Permanent placement</span>
      </div>
    </div>
  </aside>

  <section class="register-form-panel">
    <div class="register-card">
      <div class="register-card-header">
        <h1>Create Account</h1>
        <p>Already a member? <a href="<?= e($loginUrl) ?>">Login here</a></p>
      </div>

      <?php if ($errors !== []): ?>
      <div class="alert alert-error" role="alert">
        <strong>Please fix the following:</strong>
        <ul>
          <?php foreach ($errors as $err): ?>
          <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php
        $registerFormAction = $registerUrl;
        if ($sponsorLocked && function_exists('arowin_join_share_url')) {
            $registerFormAction = arowin_join_share_url($lockSponsor, $lockLeg);
        } elseif ($sponsorLocked) {
            $registerFormAction = $registerUrl . (str_contains($registerUrl, '?') ? '&' : '?') . http_build_query(array_filter([
                'sponsor_id' => $lockSponsor,
                'placement'  => $lockLeg !== '' ? $lockLeg : null,
                'for_guest'  => '1',
            ]));
        }
      ?>
      <form method="POST" action="<?= e($registerFormAction) ?>" id="registerForm" class="form-grid" novalidate>
        <input type="hidden" name="register" value="1">
        <?php if ($sponsorLocked): ?>
          <input type="hidden" name="share_sponsor" value="<?= e($lockSponsor) ?>">
          <input type="hidden" name="for_guest" value="1">
        <?php endif; ?>
        <?php if ($placementLocked): ?>
          <input type="hidden" name="share_leg" value="<?= e($lockLeg) ?>">
          <input type="hidden" name="placement" value="<?= e($lockLeg) ?>">
        <?php endif; ?>
        <?php if (!$sponsorLocked && (!empty($_GET['for_guest']) || !empty($_POST['for_guest']))): ?>
          <input type="hidden" name="for_guest" value="1">
        <?php endif; ?>
        <div class="field full">
          <label class="field-label" for="sponsorId">Sponsor ID <span class="req">*</span></label>
          <input type="text" id="sponsorId" name="sponsor_id" placeholder="e.g. ARWDOGY or ARW48291736"
            value="<?= e((string) ($_POST['sponsor_id'] ?? $default_sponsor_id)) ?>" required autocomplete="off"<?= $sponsorLocked ? ' readonly' : '' ?>>
          <?php if ($placementLocked): ?>
            <p class="field-hint" style="margin-top:6px;">Invited under <strong><?= e($lockSponsor) ?></strong> — <?= $lockLeg === 'right' ? 'Right' : 'Left' ?> leg is locked from the share link.</p>
          <?php elseif ($sponsorLocked): ?>
            <p class="field-hint" style="margin-top:6px;">Invited under <strong><?= e($lockSponsor) ?></strong> — Sponsor ID is filled from the share link.</p>
          <?php else: ?>
            <p class="field-hint" style="margin-top:6px;">First registration: use <strong>ARWDOGY</strong>. Later use your sponsor’s User ID.</p>
          <?php endif; ?>
          <div id="sponsorStatus" class="sponsor-status"></div>
        </div>

        <div class="field full placement-block<?= $placementLocked ? ' is-locked' : '' ?>">
          <label class="field-label">Binary Placement Leg <span class="req">*</span></label>
          <?php if ($placementLocked): ?>
            <p class="field-hint">This invite is for the <strong><?= $lockLeg === 'right' ? 'Right' : 'Left' ?> leg</strong>. Placement cannot be changed.</p>
          <?php else: ?>
            <p class="field-hint">Select the leg under your sponsor where your account will be placed in the genealogy tree.</p>
          <?php endif; ?>

          <div class="placement-leg-grid" role="radiogroup" aria-label="Binary placement leg">
            <label class="placement-leg <?= $posted_placement === 'left' ? 'is-selected' : '' ?><?= $placementLocked && $lockLeg !== 'left' ? ' is-locked-out' : '' ?>" data-leg="left">
              <input type="radio" name="placement" value="left" <?= $posted_placement === 'left' ? 'checked' : '' ?><?= $placementLocked ? ' disabled' : '' ?> required>
              <div class="placement-leg-head">
                <span class="placement-leg-icon" aria-hidden="true"><i class="auth-ico auth-ico-left"></i></span>
                <span class="placement-leg-badge">LEG — L</span>
              </div>
              <div class="placement-leg-title">Left Leg</div>
              <div class="placement-leg-desc">Placed at the extreme left depth of your sponsor's left downline.</div>
            </label>

            <label class="placement-leg <?= $posted_placement === 'right' ? 'is-selected' : '' ?><?= $placementLocked && $lockLeg !== 'right' ? ' is-locked-out' : '' ?>" data-leg="right">
              <input type="radio" name="placement" value="right" <?= $posted_placement === 'right' ? 'checked' : '' ?><?= $placementLocked ? ' disabled' : '' ?>>
              <div class="placement-leg-head">
                <span class="placement-leg-icon" aria-hidden="true"><i class="auth-ico auth-ico-right"></i></span>
                <span class="placement-leg-badge">LEG — R</span>
              </div>
              <div class="placement-leg-title">Right Leg</div>
              <div class="placement-leg-desc">Placed at the extreme right depth of your sponsor's right downline.</div>
            </label>
          </div>

          <p class="placement-tree-note">
            <i class="auth-ico auth-ico-tree" aria-hidden="true"></i>
            Placement is permanent and determines your position for matching volume and team structure under the selected sponsor.
          </p>
        </div>

        <div class="field full">
          <label class="field-label" for="fullName">Full Name <span class="req">*</span></label>
          <input type="text" id="fullName" name="full_name" placeholder="As per official records"
            value="<?= e((string) ($_POST['full_name'] ?? $default_full_name)) ?>" required>
        </div>

        <div class="field">
          <label class="field-label" for="mobile">Mobile Number <span class="req">*</span></label>
          <input type="tel" id="mobile" name="mobile" placeholder="10-digit mobile" maxlength="10" inputmode="numeric"
            value="<?= e((string) ($_POST['mobile'] ?? $default_mobile)) ?>" required>
          <p class="field-hint">Same mobile / email tho unlimited Arowin IDs register avvachu.</p>
        </div>

        <div class="field">
          <label class="field-label" for="email">Email Address <span class="req">*</span></label>
          <input type="email" id="email" name="email" placeholder="your@email.com"
            value="<?= e((string) ($_POST['email'] ?? '')) ?>" required autocomplete="email">
          <p class="field-hint">Same email multiple IDs OK. Welcome mail this address ki pampadam.</p>
        </div>

        <div class="field">
          <label class="field-label" for="password">Password <span class="req">*</span></label>
          <div class="password-wrap">
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
            <button type="button" class="password-toggle" onclick="togglePassword('password', this)" aria-label="Show password">
              <svg class="icon-show" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="icon-hide" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
        </div>

        <div class="field">
          <label class="field-label" for="confirmPassword">Confirm Password <span class="req">*</span></label>
          <div class="password-wrap">
            <input type="password" id="confirmPassword" name="confirm_password" placeholder="Re-enter password" required>
            <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)" aria-label="Show password">
              <svg class="icon-show" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="icon-hide" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
        </div>

        <div class="terms">
          <input type="checkbox" id="terms" name="terms" <?= isset($_POST['terms']) ? 'checked' : '' ?> required>
          <label for="terms">I agree to the <a href="<?= e($termsUrl) ?>">Terms &amp; Conditions</a> and Privacy Policy of Arowin Trading Portal.</label>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <i class="auth-ico auth-ico-plus" aria-hidden="true"></i> Create Account
        </button>
      </form>
    </div>
  </section>
</div>

<footer class="page-footer">
  © <?= (int) date('Y') ?> Arowin Trading Portal ·
  <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>
  <?php if ($supportPhone !== ''): ?>
   · <a href="tel:<?= e(preg_replace('/\s+/', '', $supportPhone) ?? $supportPhone) ?>"><?= e($supportPhone) ?></a>
  <?php endif; ?>
  <?php if ($whatsappUrl !== ''): ?>
   · <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener">WhatsApp</a>
  <?php endif; ?>
</footer>

<script>
function togglePassword(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input || !btn) return;
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btn.classList.toggle('is-visible', show);
  btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
}

document.querySelectorAll('.placement-leg').forEach(card => {
  card.addEventListener('click', function() {
    if (this.classList.contains('is-locked-out')) return;
    if (this.closest('.placement-block.is-locked')) return;
    document.querySelectorAll('.placement-leg').forEach(c => c.classList.remove('is-selected'));
    this.classList.add('is-selected');
    const radio = this.querySelector('input[type=radio]');
    if (radio && !radio.disabled) radio.checked = true;
  });
});

const registerCheckUrl = <?= json_encode($registerUrl, JSON_UNESCAPED_SLASHES) ?>;
let sponsorTimer;
const sponsorInput = document.getElementById('sponsorId');
const sponsorStatus = document.getElementById('sponsorStatus');

if (sponsorInput && sponsorStatus) {
  sponsorInput.addEventListener('input', function() {
    const pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);

    clearTimeout(sponsorTimer);
    const val = this.value.trim();
    if (val.length < 3) {
      sponsorStatus.textContent = '';
      sponsorStatus.className = 'sponsor-status';
      return;
    }
    sponsorStatus.className = 'sponsor-status';
    sponsorStatus.innerHTML = '<i class="auth-ico auth-ico-spin" aria-hidden="true"></i> Verifying sponsor...';
    sponsorTimer = setTimeout(() => {
      fetch(registerCheckUrl + '?check_sponsor=1&sponsor_id=' + encodeURIComponent(val))
        .then(r => r.json())
        .then(data => {
          if (data.status === 'valid') {
            sponsorStatus.className = 'sponsor-status valid';
            sponsorStatus.innerHTML = '<i class="auth-ico auth-ico-check" aria-hidden="true"></i> Verified: ' + data.name;
          } else {
            sponsorStatus.className = 'sponsor-status invalid';
            sponsorStatus.innerHTML = '<i class="auth-ico auth-ico-x" aria-hidden="true"></i> ' + (data.error || 'Invalid Sponsor ID');
          }
        })
        .catch(() => {
          sponsorStatus.className = 'sponsor-status invalid';
          sponsorStatus.innerHTML = '<i class="auth-ico auth-ico-x" aria-hidden="true"></i> Unable to verify sponsor. Try again.';
        });
    }, 280);
  });
  if (sponsorInput.value.trim().length >= 3) {
    sponsorInput.dispatchEvent(new Event('input'));
  }
}

const registerForm = document.getElementById('registerForm');
if (registerForm) {
  registerForm.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    if (!btn) return;
    btn.innerHTML = '<i class="auth-ico auth-ico-spin" aria-hidden="true"></i> Creating account...';
    btn.setAttribute('aria-busy', 'true');
    requestAnimationFrame(function() { btn.disabled = true; });
  });
}
</script>
<script src="<?= e($JS_NAVBAR_HREF) ?>" defer></script>
<?php require dirname(__DIR__, 2) . '/includes/pwa-register.php'; ?>
</body>
</html>
