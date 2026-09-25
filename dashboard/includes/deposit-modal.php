<?php
declare(strict_types=1);

$depositCsrf = arowin_csrf_token();
$depositBasePath = (string) (parse_url($BASE, PHP_URL_PATH) ?: '');
$depositApi = rtrim($depositBasePath, '/') . '/dashboard/api/deposit.php';
$depositStatusApi = rtrim($depositBasePath, '/') . '/dashboard/api/deposit-status.php';
$depositBalance = function_exists('arowin_wallet_balance') && !empty($user['username'] ?? null)
    ? arowin_wallet_balance((string) $user['username'])
    : (float) ($view['wallet'] ?? 0);
$depositMode = strtolower(trim((string) env('DEPOSIT_MODE', 'test')));
$isNowPayments = $depositMode === 'nowpayments';
$depositCurrencies = function_exists('arowin_deposit_pay_currencies')
    ? arowin_deposit_pay_currencies()
    : [['code' => 'usdtbsc', 'label' => 'USDT-BEP20']];
$defaultCurrency = 'usdtbsc';
?>
<div class="dep-overlay hidden" id="depositOverlay" onclick="closeDepositModal(event)" aria-hidden="true">
  <div class="dep-sheet" onclick="event.stopPropagation()" role="dialog" aria-modal="true" aria-labelledby="depositTitle">
    <div class="dep-head">
      <div>
        <p class="dep-eyebrow" id="depositEyebrow"><?= $isNowPayments ? 'Inbound deposit' : 'Wallet' ?></p>
        <h2 id="depositTitle"><?= $isNowPayments ? 'Generate address' : 'Deposit USDT' ?></h2>
      </div>
      <button type="button" class="dep-close" onclick="closeDepositModal()" aria-label="Close">&times;</button>
    </div>

    <div class="dep-balance">
      <span>Current balance</span>
      <strong id="depositBalanceLabel">$<?= e(number_format($depositBalance, 2)) ?> USDT</strong>
    </div>

    <!-- Step 1: amount + network -->
    <div id="depositStepForm">
      <form id="depositForm" class="dep-form" autocomplete="off" method="post" action="<?= e($depositApi) ?>">
        <input type="hidden" name="csrf" value="<?= e($depositCsrf) ?>">
        <?= function_exists('arowin_account_hidden_field') ? arowin_account_hidden_field() : '' ?>

        <?php if ($isNowPayments): ?>
          <label>Network / coin</label>
          <div class="dep-networks" id="depositNetworks" role="radiogroup" aria-label="Deposit network">
            <?php foreach ($depositCurrencies as $i => $coin): ?>
              <button
                type="button"
                class="dep-net<?= $coin['code'] === $defaultCurrency ? ' active' : '' ?>"
                data-pay-currency="<?= e($coin['code']) ?>"
                aria-pressed="<?= $coin['code'] === $defaultCurrency ? 'true' : 'false' ?>"
              ><?= e($coin['label']) ?></button>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="pay_currency" id="depositPayCurrency" value="<?= e($defaultCurrency) ?>">
        <?php endif; ?>

        <label for="depositAmount">Enter amount in USD</label>
        <div class="dep-amount-wrap">
          <span>$</span>
          <input id="depositAmount" name="amount" type="number" min="1" step="0.01" required placeholder="0.00" inputmode="decimal">
          <em class="dep-amount-unit">USD</em>
        </div>
        <div class="dep-quick">
          <button type="button" data-dep-quick="50">$50</button>
          <button type="button" data-dep-quick="150">$150</button>
          <button type="button" data-dep-quick="350">$350</button>
          <button type="button" data-dep-quick="750">$750</button>
        </div>

        <label for="depositNote">Note (optional)</label>
        <input id="depositNote" name="note" type="text" maxlength="120" placeholder="Wallet deposit">

        <p class="dep-hint" id="depositHint">
          <?= $isNowPayments
            ? 'A unique deposit address and QR code will be generated for this payment.'
            : 'Testing mode: amount is added to wallet instantly.' ?>
        </p>
        <p class="dep-msg" id="depositMsg" hidden></p>
        <button type="submit" class="dep-submit" id="depositSubmit">
          <?= $isNowPayments ? 'Generate deposit address' : 'Add to wallet' ?>
        </button>
      </form>
    </div>

    <!-- Step 2: QR + address -->
    <div id="depositStepPay" class="dep-pay" hidden>
      <p class="dep-pay-status" id="depositPayStatus">Payment initialized</p>
      <p class="dep-pay-sub">Send this amount to the address below</p>
      <p class="dep-pay-amount" id="depositPayAmount">—</p>
      <div class="dep-qr-wrap">
        <img id="depositQrImg" class="dep-qr" alt="Deposit QR code" width="220" height="220">
      </div>
      <label>Deposit address</label>
      <div class="dep-addr-row">
        <input id="depositPayAddress" type="text" readonly value="">
        <button type="button" class="dep-copy" id="depositCopyBtn" title="Copy address">Copy</button>
      </div>
      <p class="dep-info">
        Wallet credits as soon as your payment is detected — usually within seconds to a couple of minutes. You can close this screen; balance updates automatically.
      </p>
      <p class="dep-msg" id="depositPayMsg" hidden></p>
      <button type="button" class="dep-submit" id="depositCheckBtn">Check payment status</button>
      <button type="button" class="dep-cancel" id="depositCancelBtn">Cancel &amp; close</button>
    </div>
  </div>
</div>
<script>
  window.AROWIN_DEPOSIT_API = <?= json_encode($depositApi, JSON_UNESCAPED_SLASHES) ?>;
  window.AROWIN_DEPOSIT_STATUS_API = <?= json_encode($depositStatusApi, JSON_UNESCAPED_SLASHES) ?>;
  window.AROWIN_DEPOSIT_MODE = <?= json_encode($depositMode, JSON_UNESCAPED_SLASHES) ?>;
</script>
