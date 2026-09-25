<?php
/**
 * Operator Configuration — biometric + 2FA security
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/security-repository.php';
require_once dirname(__DIR__, 2) . '/includes/withdrawal-settings-repository.php';
require_once dirname(__DIR__, 2) . '/includes/nowpayments-service.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'operator_configuration';

arowin_prepare_page([
    'title'       => 'Operator Configuration — Arowin Trading Portal',
    'title_short' => 'Operator Configuration',
    'description' => 'Configure biometric access and two-factor authentication.',
    'canonical'   => '/dashboard/operator-configuration',
    'robots'      => 'noindex, nofollow',
    'faqs'        => false,
]);

$sessionUid = (string) ($_SESSION['arowin_uid'] ?? '');
if ($sessionUid === '' || !arowin_is_logged_in()) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('login'), true, 303);
    exit;
}

$user = arowin_get_user_by_username($sessionUid);
if (!$user || (int) ($user['id'] ?? 0) < 1) {
    arowin_logout_user();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('login'), true, 303);
    exit;
}

$view = arowin_dashboard_view_data($user);
$security = arowin_security_status((string) $user['username']);
$accountApi = arowin_abs($BASE, '/dashboard/api/account/');

$baseCss = arowin_abs($BASE, '/dashboard/assets/css/dashboard.css');
$jsHref  = arowin_abs($BASE, '/dashboard/assets/js/dashboard.js');
$baseCssFile = dirname(__DIR__, 2) . '/dashboard/assets/css/dashboard.css';
$jsFile = dirname(__DIR__, 2) . '/dashboard/assets/js/dashboard.js';
if (is_file($baseCssFile)) {
    $baseCss .= '?v=' . filemtime($baseCssFile);
}
if (is_file($jsFile)) {
    $jsHref .= '?v=' . filemtime($jsFile);
}
$secJsFile = dirname(__DIR__, 2) . '/dashboard/assets/js/profile-security.js';
$secJs = arowin_abs($BASE, '/dashboard/assets/js/profile-security.js');
if (is_file($secJsFile)) {
    $secJs .= '?v=' . filemtime($secJsFile);
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= e($SEO['title']) ?></title>
    <meta name="robots" content="<?= e($SEO['robots']) ?>">
    <link rel="canonical" href="<?= e($CANONICAL) ?>">
    <link rel="icon" href="<?= e(arowin_abs($BASE, '/favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/svg+xml" href="<?= e(img('favicon.svg')) ?>">
    <?php require dirname(__DIR__, 2) . '/includes/pwa-meta.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= e($baseCss) ?>" />
  </head>
  <body>
    <?php require dirname(__DIR__) . '/sidebar.php'; ?>
    <div class="main" id="main">
      <?php require dirname(__DIR__) . '/navbar.php'; ?>
      <div class="content">
        <div class="id-head">
          <div>
            <p class="id-eyebrow">Security</p>
            <h1 class="id-title">Operator Configuration</h1>
            <p class="id-sub">Choose one login mode only — Sign In, Fingerprint, or 2FA.</p>
          </div>
          <div class="id-head-actions">
            <a class="id-btn" href="<?= e(url('profile')) ?>">Identify Profile</a>
          </div>
        </div>

        <?php
          $loginMode = (string) ($security['login_mode'] ?? 'signin');
          $signinOn = $loginMode === 'signin';
          $bioOn = $loginMode === 'biometric';
          $twoFaOn = $loginMode === '2fa';
        ?>

        <section class="id-security" aria-label="Account security">
          <p class="id-security-sub">Only one option can be enabled at a time. Enabling one turns the others off.</p>

          <div class="id-sec-card" id="signinCard">
            <div class="id-sec-icon is-signin" aria-hidden="true">
              <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
            </div>
            <div class="id-sec-copy">
              <div class="id-sec-title-row">
                <h3>Sign In</h3>
                <span class="id-sec-badge<?= $signinOn ? ' is-on' : '' ?>" id="signinBadge"><?= $signinOn ? 'Enabled' : 'Disabled' ?></span>
              </div>
              <p>Standard password login only. No PIN and no fingerprint.</p>
            </div>
            <label class="id-toggle">
              <input type="checkbox" id="signinToggle" <?= $signinOn ? 'checked' : '' ?> aria-label="Toggle Sign In mode">
              <span class="id-toggle-ui"></span>
            </label>
          </div>

          <div class="id-sec-card" id="bioCard">
            <div class="id-sec-icon is-bio" aria-hidden="true">
              <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 11c0-1.5.8-2.5 2-3"/><path d="M7 11a5 5 0 0110 0"/><path d="M5 11a7 7 0 0114 0"/><path d="M9 21v-4a3 3 0 016 0v4"/><path d="M12 11v4"/></svg>
            </div>
            <div class="id-sec-copy">
              <div class="id-sec-title-row">
                <h3>Fingerprint</h3>
                <span class="id-sec-badge<?= $bioOn ? ' is-on' : '' ?>" id="bioBadge"><?= $bioOn ? 'Enabled' : 'Disabled' ?></span>
              </div>
              <p>Fingerprint login — mobile browser &amp; mobile web app only. Disables Sign In and 2FA while on.</p>
            </div>
            <label class="id-toggle">
              <input type="checkbox" id="bioToggle" <?= $bioOn ? 'checked' : '' ?> aria-label="Toggle fingerprint access">
              <span class="id-toggle-ui"></span>
            </label>
          </div>

          <div class="id-sec-card" id="twoFaCard">
            <div class="id-sec-icon is-2fa" aria-hidden="true">
              <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3l8 4v5c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V7l8-4z"/><path d="M9 12l2 2 4-4"/></svg>
            </div>
            <div class="id-sec-copy">
              <div class="id-sec-title-row">
                <h3>Two-factor authentication (2FA)</h3>
                <span class="id-sec-badge<?= $twoFaOn ? ' is-on' : '' ?>" id="twoFaBadge"><?= $twoFaOn ? 'Enabled' : 'Disabled' ?></span>
              </div>
              <p>Password + 6-digit PIN. Disables Sign In and Fingerprint while on.</p>
            </div>
            <label class="id-toggle">
              <input type="checkbox" id="twoFaToggle" <?= $twoFaOn ? 'checked' : '' ?> aria-label="Toggle 2FA">
              <span class="id-toggle-ui"></span>
            </label>
          </div>
        </section>

        <?php
          $wdSettings = arowin_withdrawal_settings((string) $user['username']);
          $wdAddresses = $wdSettings['addresses'];
          $wdHasPass = !empty($wdSettings['has_password']);
          $wdCanAddAddr = arowin_can_add_withdrawal_address((string) $user['username']);
          $wdApi = arowin_abs($BASE, '/dashboard/api/withdrawal/');
          $wdCsrf = arowin_csrf_token();
          $payoutCurrency = (string) (arowin_nowpayments_config()['payout_currency'] ?? 'usdttrc20');
        ?>

        <section class="id-security wd-settings" aria-label="Withdrawal settings" style="margin-top:28px">
          <div class="id-head" style="margin-bottom:12px">
            <div>
              <p class="id-eyebrow">Funds</p>
              <h2 class="id-title" style="font-size:20px">Withdrawal setup</h2>
              <p class="id-sub">Create a withdrawal password and save multiple payout addresses.</p>
            </div>
          </div>

          <div class="id-sec-card" id="wdPassCard">
            <div class="id-sec-icon is-2fa" aria-hidden="true">
              <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            </div>
            <div class="id-sec-copy">
              <div class="id-sec-title-row">
                <h3>Withdrawal password</h3>
                <span class="id-sec-badge<?= $wdHasPass ? ' is-on' : '' ?>" id="wdPassBadge"><?= $wdHasPass ? 'Set' : 'Not set' ?></span>
              </div>
              <p>Required every time you withdraw from Master Wallet.</p>
            </div>
            <button type="button" class="id-btn primary" id="wdPassOpen">
              <?= $wdHasPass ? 'Update password' : '+ Set withdrawal password' ?>
            </button>
          </div>

          <div class="id-sec-card" id="wdAddrCard" style="align-items:flex-start;flex-wrap:wrap">
            <div class="id-sec-icon is-signin" aria-hidden="true">
              <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12V7H5a2 2 0 010-4h14v4"/><path d="M3 5v14a2 2 0 002 2h16v-5"/><path d="M18 12a2 2 0 100 4h4v-4z"/></svg>
            </div>
            <div class="id-sec-copy" style="flex:1;min-width:180px">
              <div class="id-sec-title-row">
                <h3>Withdrawal addresses</h3>
                <span class="id-sec-badge is-on" id="wdAddrCount"><?= count($wdAddresses) ?> saved</span>
              </div>
              <p>Save multiple USDT-BEP20 addresses. Pick one when withdrawing.</p>
            </div>
            <button type="button" class="id-btn primary" id="wdAddrOpen" data-can-add="<?= $wdCanAddAddr ? '1' : '0' ?>">+ Add withdrawal address</button>

            <p class="id-sec-msg" id="wdAddrMsg" hidden></p>

            <ul class="wd-addr-list" id="wdAddrList">
              <?php if ($wdAddresses === []): ?>
                <li class="wd-addr-empty">No addresses yet.</li>
              <?php else: ?>
                <?php foreach ($wdAddresses as $a): ?>
                  <li class="wd-addr-item" data-id="<?= (int) $a['id'] ?>">
                    <div class="wd-addr-meta">
                      <strong><?= e((string) ($a['label'] ?: 'Wallet')) ?><?= !empty($a['is_default']) ? ' · Default' : '' ?></strong>
                      <span class="wd-addr-hex"><?= e((string) $a['address']) ?></span>
                    </div>
                    <?php if ($wdCanAddAddr): ?>
                      <button type="button" class="id-btn wd-addr-del" data-id="<?= (int) $a['id'] ?>">Delete</button>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              <?php endif; ?>
            </ul>
          </div>
        </section>
      </div>
    </div>

    <div class="id-sec-overlay hidden" id="secOverlay" aria-hidden="true">
      <div class="id-sec-sheet" role="dialog" aria-modal="true" aria-labelledby="secSheetTitle">
        <div class="id-sec-sheet-head">
          <h3 id="secSheetTitle">Enable 2FA</h3>
          <button type="button" class="id-sec-close" id="secClose" aria-label="Close">×</button>
        </div>
        <p class="id-sec-sheet-sub" id="secSheetSub">Create a 6-digit PIN used after password at login.</p>
        <form id="secPinForm" class="id-sec-form" autocomplete="off">
          <label for="secPin">Security PIN</label>
          <input id="secPin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="••••••" required>
          <label for="secPin2" id="secPin2Label">Confirm PIN</label>
          <input id="secPin2" name="confirm_pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="••••••">
          <div class="id-form-actions">
            <button type="button" class="id-btn" id="secCancel">Cancel</button>
            <button type="submit" class="id-btn primary" id="secSubmit">Save</button>
          </div>
          <p class="id-sec-msg" id="secMsg" hidden></p>
        </form>
      </div>
    </div>

    <div class="id-sec-overlay hidden" id="wdPassOverlay" aria-hidden="true">
      <div class="id-sec-sheet" role="dialog" aria-modal="true" aria-labelledby="wdPassTitle" onclick="event.stopPropagation()">
        <div class="id-sec-sheet-head">
          <h3 id="wdPassTitle"><?= $wdHasPass ? 'Update withdrawal password' : 'Set withdrawal password' ?></h3>
          <button type="button" class="id-sec-close" id="wdPassClose" aria-label="Close">×</button>
        </div>
        <p class="id-sec-sheet-sub">Used to confirm every Master Wallet withdrawal.</p>
        <form id="wdPassForm" class="id-sec-form" autocomplete="off" data-has-pass="<?= $wdHasPass ? '1' : '0' ?>">
          <div id="wdPassOldWrap"<?= $wdHasPass ? '' : ' hidden' ?>>
            <label for="wdPassOld">Current password</label>
            <div class="id-pass-wrap">
              <input id="wdPassOld" type="password" minlength="6" placeholder="Current password" autocomplete="current-password"<?= $wdHasPass ? ' required' : '' ?>>
              <button type="button" class="id-pass-toggle" data-pass-target="wdPassOld" aria-label="Show password">
                <svg class="icon-show" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon-hide" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>
          <label for="wdPass">New password</label>
          <div class="id-pass-wrap">
            <input id="wdPass" type="password" minlength="6" required placeholder="Min 6 characters" autocomplete="new-password">
            <button type="button" class="id-pass-toggle" data-pass-target="wdPass" aria-label="Show password">
              <svg class="icon-show" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="icon-hide" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <label for="wdPass2">Confirm password</label>
          <div class="id-pass-wrap">
            <input id="wdPass2" type="password" minlength="6" required placeholder="Re-enter" autocomplete="new-password">
            <button type="button" class="id-pass-toggle" data-pass-target="wdPass2" aria-label="Show password">
              <svg class="icon-show" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="icon-hide" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <div class="id-form-actions">
            <button type="button" class="id-btn" id="wdPassCancel">Cancel</button>
            <button type="submit" class="id-btn primary" id="wdPassSubmit"><?= $wdHasPass ? 'Update password' : 'Save password' ?></button>
          </div>
          <p class="id-sec-msg" id="wdPassMsg" hidden></p>
        </form>
      </div>
    </div>

    <div class="id-sec-overlay hidden" id="wdAddrOverlay" aria-hidden="true">
      <div class="id-sec-sheet" role="dialog" aria-modal="true" aria-labelledby="wdAddrTitle" onclick="event.stopPropagation()">
        <div class="id-sec-sheet-head">
          <h3 id="wdAddrTitle">Add withdrawal address</h3>
          <button type="button" class="id-sec-close" id="wdAddrClose" aria-label="Close">×</button>
        </div>
        <p class="id-sec-sheet-sub">Save MetaMask/BEP20 or TRC20 wallet addresses.</p>
        <form id="wdAddrForm" class="id-sec-form" autocomplete="off">
          <label for="wdAddrLabel">Label</label>
          <input id="wdAddrLabel" type="text" maxlength="80" placeholder="e.g. MetaMask BEP20">
          <label for="wdAddrCurrency">Network</label>
          <select id="wdAddrCurrency">
            <option value="usdtbsc">USDT-BEP20 (MetaMask)</option>
            <option value="usdttrc20">USDT-TRC20</option>
          </select>
          <label for="wdAddrValue">Wallet address</label>
          <input id="wdAddrValue" type="text" required maxlength="128" placeholder="0x…">
          <div class="id-form-actions">
            <button type="button" class="id-btn" id="wdAddrCancel">Cancel</button>
            <button type="submit" class="id-btn primary" id="wdAddrSubmit">Save address</button>
          </div>
          <p class="id-sec-msg" id="wdAddrMsg" hidden></p>
        </form>
      </div>
    </div>

    <?php require dirname(__DIR__) . '/includes/bottom-nav.php'; ?>
    <script src="<?= e($jsHref) ?>"></script>
    <script>
      window.AROWIN_ACCOUNT_API = <?= json_encode($accountApi, JSON_UNESCAPED_SLASHES) ?>;
      window.AROWIN_SECURITY = <?= json_encode([
          'two_fa_enabled' => $twoFaOn,
          'biometric_enabled' => $bioOn,
          'login_mode' => $loginMode,
          'username' => (string) $user['username'],
      ], JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="<?= e($secJs) ?>"></script>
    <script>
      (function () {
        var api = <?= json_encode($wdApi, JSON_UNESCAPED_SLASHES) ?>;
        var csrf = <?= json_encode($wdCsrf, JSON_UNESCAPED_SLASHES) ?>;
        window.AROWIN_WITHDRAWAL_ADDRESSES = <?= json_encode($wdAddresses, JSON_UNESCAPED_SLASHES) ?>;

        function msg(el, text, ok) {
          if (!el) return;
          el.hidden = !text;
          el.textContent = text || '';
          el.style.color = ok ? '#86efac' : '#fecaca';
        }

        function openOverlay(id, focusId) {
          var el = document.getElementById(id);
          if (!el) return;
          el.classList.remove('hidden');
          el.setAttribute('aria-hidden', 'false');
          document.body.style.overflow = 'hidden';
          if (focusId) {
            var f = document.getElementById(focusId);
            if (f) setTimeout(function () { f.focus(); }, 50);
          }
        }

        function closeOverlay(id) {
          var el = document.getElementById(id);
          if (!el) return;
          el.classList.add('hidden');
          el.setAttribute('aria-hidden', 'true');
          document.body.style.overflow = '';
        }

        function post(body) {
          return fetch(api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ csrf: csrf }, body)),
          }).then(function (r) { return r.json(); });
        }

        function renderAddresses(list) {
          var ul = document.getElementById('wdAddrList');
          var count = document.getElementById('wdAddrCount');
          if (!ul) return;
          list = list || [];
          window.AROWIN_WITHDRAWAL_ADDRESSES = list;
          if (count) count.textContent = list.length + ' saved';
          if (!list.length) {
            ul.innerHTML = '<li class="wd-addr-empty">No addresses yet.</li>';
            return;
          }
          ul.innerHTML = list.map(function (a) {
            var label = (a.label || 'Wallet') + (Number(a.is_default) ? ' · Default' : '');
            var currency = String(a.currency || '').toLowerCase();
            var network = currency.indexOf('trc') !== -1 ? 'TRC20' : 'BEP20';
            return (
              '<li class="wd-addr-item" data-id="' + a.id + '">' +
              '<div class="wd-addr-meta"><strong>' + String(label).replace(/</g, '&lt;') + ' · ' + network + '</strong>' +
              '<span class="wd-addr-hex">' + String(a.address || '').replace(/</g, '&lt;') + '</span></div>' +
              '<div><button type="button" class="id-btn wd-addr-edit" data-id="' + a.id + '">Edit</button>' +
              '<button type="button" class="id-btn wd-addr-del" data-id="' + a.id + '">Delete</button></div></li>'
            );
          }).join('');
        }

        document.getElementById('wdPassOpen')?.addEventListener('click', function () {
          msg(document.getElementById('wdPassMsg'), '', true);
          var form = document.getElementById('wdPassForm');
          var hasPass = form && form.getAttribute('data-has-pass') === '1';
          var focusId = hasPass ? 'wdPassOld' : 'wdPass';
          openOverlay('wdPassOverlay', focusId);
        });
        document.getElementById('wdPassClose')?.addEventListener('click', function () {
          closeOverlay('wdPassOverlay');
        });
        document.getElementById('wdPassCancel')?.addEventListener('click', function () {
          closeOverlay('wdPassOverlay');
        });
        document.getElementById('wdPassOverlay')?.addEventListener('click', function (e) {
          if (e.target === e.currentTarget) closeOverlay('wdPassOverlay');
        });

        document.getElementById('wdPassForm')?.addEventListener('click', function (e) {
          var toggle = e.target.closest('.id-pass-toggle');
          if (!toggle) return;
          var id = toggle.getAttribute('data-pass-target');
          var input = id ? document.getElementById(id) : null;
          if (!input) return;
          var show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          toggle.classList.toggle('is-visible', show);
          toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });

        document.getElementById('wdAddrOpen')?.addEventListener('click', function () {
          var canAdd = this.getAttribute('data-can-add') === '1';
          if (!canAdd) {
            // Show popup for disabled users
            alert('Please contact admin to add withdrawal addresses.');
            return;
          }
          msg(document.getElementById('wdAddrMsg'), '', true);
          var addForm = document.getElementById('wdAddrForm');
          if (addForm) addForm.removeAttribute('data-edit-id');
          var addLabel = document.getElementById('wdAddrLabel');
          var addCurrency = document.getElementById('wdAddrCurrency');
          var addAddress = document.getElementById('wdAddrValue');
          var addSubmit = document.getElementById('wdAddrSubmit');
          if (addLabel) addLabel.value = '';
          if (addCurrency) addCurrency.value = 'usdtbsc';
          if (addAddress) addAddress.value = '';
          if (addSubmit) addSubmit.textContent = 'Save address';
          openOverlay('wdAddrOverlay', 'wdAddrLabel');
        });

        document.getElementById('wdAddrList')?.addEventListener('click', function (e) {
          var edit = e.target.closest('.wd-addr-edit');
          if (!edit) return;
          var item = edit.closest('.wd-addr-item');
          var address = item ? item.querySelector('.wd-addr-hex') : null;
          var id = Number(edit.getAttribute('data-id') || 0);
          var current = (window.AROWIN_WITHDRAWAL_ADDRESSES || []).find(function (a) { return Number(a.id) === id; });
          if (!current || !address) return;
          document.getElementById('wdAddrForm').setAttribute('data-edit-id', String(id));
          document.getElementById('wdAddrLabel').value = current.label || '';
          document.getElementById('wdAddrCurrency').value = current.currency || 'usdtbsc';
          document.getElementById('wdAddrValue').value = current.address || '';
          document.getElementById('wdAddrSubmit').textContent = 'Update address';
          openOverlay('wdAddrOverlay', 'wdAddrValue');
        });

        document.getElementById('wdAddrClose')?.addEventListener('click', function () {
          closeOverlay('wdAddrOverlay');
        });
        document.getElementById('wdAddrCancel')?.addEventListener('click', function () {
          var form = document.getElementById('wdAddrForm');
          if (form) form.removeAttribute('data-edit-id');
          closeOverlay('wdAddrOverlay');
        });
        document.getElementById('wdAddrOverlay')?.addEventListener('click', function (e) {
          if (e.target === e.currentTarget) closeOverlay('wdAddrOverlay');
        });

        document.addEventListener('keydown', function (e) {
          if (e.key !== 'Escape') return;
          closeOverlay('wdPassOverlay');
          closeOverlay('wdAddrOverlay');
        });

        document.getElementById('wdPassForm')?.addEventListener('submit', function (e) {
          e.preventDefault();
          var form = document.getElementById('wdPassForm');
          var hasPass = form && form.getAttribute('data-has-pass') === '1';
          var oldEl = document.getElementById('wdPassOld');
          var p1 = document.getElementById('wdPass');
          var p2 = document.getElementById('wdPass2');
          var out = document.getElementById('wdPassMsg');
          var btn = document.getElementById('wdPassSubmit');
          if (btn) btn.disabled = true;
          post({
            action: 'set_password',
            old_password: hasPass && oldEl ? oldEl.value : '',
            password: p1 ? p1.value : '',
            confirm_password: p2 ? p2.value : '',
          }).then(function (data) {
            msg(out, data.message || (data.ok ? 'Saved.' : 'Failed.'), !!data.ok);
            if (data.ok) {
              var badge = document.getElementById('wdPassBadge');
              if (badge) {
                badge.textContent = 'Set';
                badge.classList.add('is-on');
              }
              var openBtn = document.getElementById('wdPassOpen');
              if (openBtn) openBtn.textContent = 'Update password';
              var title = document.getElementById('wdPassTitle');
              if (title) title.textContent = 'Update withdrawal password';
              if (btn) btn.textContent = 'Update password';
              if (form) form.setAttribute('data-has-pass', '1');
              var oldWrap = document.getElementById('wdPassOldWrap');
              if (oldWrap) oldWrap.hidden = false;
              if (oldEl) oldEl.required = true;
              if (oldEl) oldEl.value = '';
              if (p1) p1.value = '';
              if (p2) p2.value = '';
              document.querySelectorAll('#wdPassForm .id-pass-toggle').forEach(function (t) {
                t.classList.remove('is-visible');
                t.setAttribute('aria-label', 'Show password');
                var tid = t.getAttribute('data-pass-target');
                var inp = tid ? document.getElementById(tid) : null;
                if (inp) inp.type = 'password';
              });
              setTimeout(function () { closeOverlay('wdPassOverlay'); }, 600);
            }
          }).catch(function () {
            msg(out, 'Network error.', false);
          }).finally(function () {
            if (btn) btn.disabled = false;
          });
        });

        document.getElementById('wdAddrForm')?.addEventListener('submit', function (e) {
          e.preventDefault();
          var label = document.getElementById('wdAddrLabel');
          var currency = document.getElementById('wdAddrCurrency');
          var addr = document.getElementById('wdAddrValue');
          var out = document.getElementById('wdAddrMsg');
          var btn = document.getElementById('wdAddrSubmit');
          var form = document.getElementById('wdAddrForm');
          var editId = Number(form ? form.getAttribute('data-edit-id') || 0 : 0);
          if (btn) btn.disabled = true;
          post({
            action: editId ? 'update_address' : 'add_address',
            id: editId,
            label: label ? label.value : '',
            address: addr ? addr.value : '',
            currency: currency ? currency.value : 'usdtbsc',
            is_default: true,
          }).then(function (data) {
            msg(out, data.message || (data.ok ? 'Saved.' : 'Failed.'), !!data.ok);
            if (data.ok) {
              if (label) label.value = '';
              if (addr) addr.value = '';
              if (form) form.removeAttribute('data-edit-id');
              if (btn) btn.textContent = 'Save address';
              window.AROWIN_WITHDRAWAL_ADDRESSES = data.addresses || [];
              renderAddresses(data.addresses || []);
              setTimeout(function () { closeOverlay('wdAddrOverlay'); }, 600);
            }
          }).catch(function () {
            msg(out, 'Network error.', false);
          }).finally(function () {
            if (btn) btn.disabled = false;
          });
        });

        document.getElementById('wdAddrList')?.addEventListener('click', function (e) {
          var btn = e.target.closest('.wd-addr-del');
          if (!btn) return;
          var id = Number(btn.getAttribute('data-id') || 0);
          if (!id || !confirm('Delete this address?')) return;
          post({ action: 'delete_address', id: id }).then(function (data) {
            if (data.ok) renderAddresses(data.addresses || []);
            else alert(data.message || 'Delete failed');
          });
        });
      })();
    </script>
    <?php require dirname(__DIR__, 2) . '/includes/pwa-register.php'; ?>
  </body>
</html>
