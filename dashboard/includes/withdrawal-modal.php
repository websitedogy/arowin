<?php
declare(strict_types=1);

$wdModalCsrf = arowin_csrf_token();
$wdModalBasePath = (string) (parse_url($BASE, PHP_URL_PATH) ?: '');
$wdModalApi = rtrim($wdModalBasePath, '/') . '/dashboard/api/withdrawal.php';
$wdModalBalance = function_exists('arowin_wallet_balance') && !empty($user['username'] ?? null)
    ? arowin_wallet_balance((string) $user['username'])
    : (float) ($view['wallet'] ?? 0);
$wdModalIsPower = !empty($user) && trim((string) ($user['power_owner'] ?? '')) !== '';
$wdModalSettingsUrl = function_exists('url') ? url('operator-configuration') : '#';

if (!function_exists('arowin_withdrawal_settings')) {
    require_once dirname(__DIR__, 2) . '/includes/withdrawal-settings-repository.php';
}
$wdModalSettings = !empty($user['username'] ?? null)
    ? arowin_withdrawal_settings((string) $user['username'])
    : ['has_password' => false, 'addresses' => []];
$wdModalAddresses = $wdModalSettings['addresses'] ?? [];
$wdModalPhone = preg_replace('/\D+/', '', (string) ($user['phone'] ?? ''));
?>
<div class="dep-overlay hidden" id="withdrawalOverlay" onclick="closeWithdrawalModal(event)" aria-hidden="true">
  <div class="dep-sheet" onclick="event.stopPropagation()" role="dialog" aria-modal="true" aria-labelledby="withdrawalTitle">
    <div class="dep-head">
      <div>
        <p class="dep-eyebrow">Funds</p>
        <h2 id="withdrawalTitle">Withdraw USDT</h2>
      </div>
      <button type="button" class="dep-close" onclick="closeWithdrawalModal()" aria-label="Close">&times;</button>
    </div>

    <div class="dep-balance">
      <span>Master Wallet</span>
      <strong id="withdrawalBalanceLabel">$<?= e(number_format($wdModalBalance, 2)) ?> USDT</strong>
    </div>

    <?php if ($wdModalIsPower): ?>
      <div class="finance-empty" style="padding:18px 8px">
        <h2 style="font-size:16px;margin:0 0 6px">Main ID only</h2>
        <p style="margin:0;color:var(--text-muted);font-size:13px">Power IDs cannot withdraw. Switch to your main ID.</p>
      </div>
    <?php elseif ($wdModalAddresses === []): ?>
      <div class="finance-empty" style="padding:18px 8px">
        <h2 style="font-size:16px;margin:0 0 6px">Setup required</h2>
        <p style="margin:0 0 14px;color:var(--text-muted);font-size:13px">
          Save at least one withdrawal address in Operator Configuration.
        </p>
        <a class="dep-submit" style="display:inline-block;text-align:center;text-decoration:none" href="<?= e($wdModalSettingsUrl) ?>">Open Operator Configuration</a>
      </div>
    <?php else: ?>
      <form id="withdrawalForm" class="dep-form" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e($wdModalCsrf) ?>">
        <?= function_exists('arowin_account_hidden_field') ? arowin_account_hidden_field() : '' ?>

        <label for="withdrawalAddress">Withdrawal address</label>
        <select id="withdrawalAddress" name="address_id" required>
          <?php foreach ($wdModalAddresses as $a): ?>
            <option value="<?= (int) $a['id'] ?>"<?= !empty($a['is_default']) ? ' selected' : '' ?>>
              <?= e((string) ($a['label'] ?: 'Wallet')) ?> · <?= e(str_contains(strtolower((string) ($a['currency'] ?? '')), 'trc') ? 'TRC20' : 'BEP20') ?> — <?= e(mb_substr((string) $a['address'], 0, 8) . '…' . mb_substr((string) $a['address'], -6)) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label for="withdrawalAmount">Amount (USDT)</label>
        <div class="dep-amount-wrap">
          <span>$</span>
          <input id="withdrawalAmount" name="amount" type="number" min="10" step="0.01" required placeholder="10.00" inputmode="decimal">
          <em class="dep-amount-unit">USDT</em>
        </div>
        <div class="dep-quick">
          <button type="button" data-wd-all>Max</button>
        </div>
        <p class="dep-fee-line" id="withdrawalFeeLine">Min $10. 10% fee — you receive 90% on-chain.</p>

        <div id="withdrawalOtpStep" hidden>
          <label for="withdrawalOtp">SMS verification code</label>
          <input id="withdrawalOtp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="Enter 6-digit OTP">
          <p class="dep-hint">Enter the OTP sent to your registered mobile number.</p>
        </div>

        <p class="dep-hint">Master Wallet is cut for the full amount you enter. After a 10% fee, the remaining 90% is sent to the selected address. No admin approval.</p>
        <p class="dep-msg" id="withdrawalMsg" hidden></p>
        <div id="withdrawalRecaptcha" aria-hidden="true"></div>
        <button type="submit" class="dep-submit" id="withdrawalSubmit">Send OTP</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<script>
  window.AROWIN_WITHDRAWAL_API = <?= json_encode($wdModalApi, JSON_UNESCAPED_SLASHES) ?>;
  window.AROWIN_WITHDRAWAL_IS_POWER = <?= $wdModalIsPower ? 'true' : 'false' ?>;
  window.AROWIN_WITHDRAWAL_CSRF = <?= json_encode($wdModalCsrf, JSON_UNESCAPED_SLASHES) ?>;
  window.AROWIN_WITHDRAWAL_PHONE = <?= json_encode($wdModalPhone, JSON_UNESCAPED_SLASHES) ?>;
  window.AROWIN_FIREBASE_CONFIG = <?= json_encode([
      'apiKey' => (string) env('FIREBASE_API_KEY', ''),
      'authDomain' => (string) env('FIREBASE_AUTH_DOMAIN', ''),
      'databaseURL' => (string) env('FIREBASE_DATABASE_URL', ''),
      'projectId' => (string) env('FIREBASE_PROJECT_ID', ''),
      'storageBucket' => (string) env('FIREBASE_STORAGE_BUCKET', ''),
      'messagingSenderId' => (string) env('FIREBASE_MESSAGING_SENDER_ID', ''),
      'appId' => (string) env('FIREBASE_APP_ID', ''),
      'measurementId' => (string) env('FIREBASE_MEASUREMENT_ID', ''),
  ], JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-auth-compat.js"></script>
