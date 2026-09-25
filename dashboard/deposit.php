<?php
declare(strict_types=1);

require __DIR__ . '/includes/finance-bootstrap.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'deposit';

arowin_prepare_page([
    'title'       => 'Deposit — Arowin Trading Portal',
    'title_short' => 'Deposit',
    'description' => 'Deposit USDT to your Arowin wallet.',
    'canonical'   => '/dashboard/deposit',
    'robots'      => 'noindex, nofollow',
    'faqs'        => false,
]);

$balance = arowin_wallet_balance((string) $user['username']);
$mode = strtolower(trim((string) env('DEPOSIT_MODE', 'test')));

ob_start();
?>
  <div class="finance-stats" style="margin-bottom:16px">
    <div class="finance-stat">
      <span>Wallet balance</span>
      <strong>$<?= e(arowin_fmt_money($balance)) ?> USDT</strong>
    </div>
  </div>
  <div class="finance-empty">
    <h2>Quick deposit</h2>
    <p>
      <?= $mode === 'nowpayments'
        ? 'Choose a network, enter an amount, and get a unique deposit address with QR. Wallet credits after blockchain confirmation.'
        : 'Testing mode: open the popup, enter amount, and it credits your wallet instantly.' ?>
    </p>
    <button type="button" class="finance-link" style="border:0;cursor:pointer" onclick="openDepositModal()">Enter amount</button>
  </div>
<?php
$FINANCE_EYEBROW = 'Funds';
$FINANCE_TITLE = 'Deposit';
$FINANCE_SUB = 'Add USDT to your spendable wallet.';
$FINANCE_BODY = ob_get_clean();

require __DIR__ . '/includes/finance-page.php';
