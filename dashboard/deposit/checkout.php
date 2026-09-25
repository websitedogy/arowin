<?php
/**
 * Deposit checkout — shows /v1/payment pay address when hosted invoice_url is unavailable.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/finance-bootstrap.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'deposit';

arowin_prepare_page([
    'title'       => 'Complete deposit — Arowin Trading Portal',
    'title_short' => 'Pay deposit',
    'description' => 'Send crypto to complete your wallet deposit.',
    'canonical'   => '/dashboard/deposit/checkout',
    'robots'      => 'noindex, nofollow',
    'faqs'        => false,
]);

$orderId = trim((string) ($_GET['order_id'] ?? ''));
$row = $orderId !== '' ? arowin_deposit_find_by_order($orderId) : null;

if (!$row || strcasecmp((string) $row['username'], (string) $user['username']) !== 0) {
    $_SESSION['arowin_flash'] = 'Deposit order not found.';
    header('Location: ' . url('deposit'), true, 303);
    exit;
}

$status = strtolower((string) ($row['status'] ?? 'pending'));
$finished = !empty($row['credited_at']);
$payAddress = trim((string) ($row['pay_address'] ?? ''));
$payAmount = $row['pay_amount'] !== null ? (string) $row['pay_amount'] : '';
$payCurrency = strtoupper((string) ($row['pay_currency'] ?? 'USDTTRC20'));
$amountUsd = round((float) $row['amount_usd'], 2);

// Refresh from NOWPayments and credit as soon as payment is detected
if (!$finished && !empty($row['nowpayments_payment_id'])) {
    $np = arowin_nowpayments();
    $st = $np->getPaymentStatus((string) $row['nowpayments_payment_id']);
    if (!empty($st['ok']) && !empty($st['data']) && is_array($st['data'])) {
        $d = $st['data'];
        if (empty($d['order_id'])) {
            $d['order_id'] = $orderId;
        }
        arowin_deposit_handle_ipn($d);
        $row = arowin_deposit_find_by_order($orderId) ?: $row;
        $status = strtolower((string) ($row['status'] ?? $status));
        $finished = !empty($row['credited_at']);
        $payAddress = trim((string) ($row['pay_address'] ?? $payAddress));
        if ($row['pay_amount'] !== null) {
            $payAmount = (string) $row['pay_amount'];
        }
        if (!empty($row['pay_currency'])) {
            $payCurrency = strtoupper((string) $row['pay_currency']);
        }
    }
}

ob_start();
?>
  <?php if ($finished): ?>
    <div class="pkg-flash ok">Payment received. Your wallet has been credited $<?= e(number_format($amountUsd, 2)) ?> USDT.</div>
    <a class="finance-link" href="<?= e(url('dashboard')) ?>">Back to dashboard</a>
  <?php else: ?>
    <div class="finance-stats" style="margin-bottom:16px">
      <div class="finance-stat">
        <span>Order</span>
        <strong style="font-size:13px"><?= e($orderId) ?></strong>
      </div>
      <div class="finance-stat">
        <span>Amount (USD)</span>
        <strong>$<?= e(number_format($amountUsd, 2)) ?></strong>
      </div>
      <div class="finance-stat">
        <span>Status</span>
        <strong><?= e(ucfirst($status)) ?></strong>
      </div>
    </div>

    <?php if ($payAddress === ''): ?>
      <div class="finance-empty">
        <h2>Preparing payment</h2>
        <p>Preparing your payment address. Refresh this page in a few seconds.</p>
        <a class="finance-link" href="?order_id=<?= e(rawurlencode($orderId)) ?>">Refresh</a>
      </div>
    <?php else: ?>
      <div class="finance-form">
        <label>Send</label>
        <p style="font-size:22px;font-weight:700;margin:0 0 12px">
          $<?= e(number_format($amountUsd, 2)) ?>
          <span style="font-size:14px;font-weight:600">USDT</span>
        </p>
        <label>To this address</label>
        <input type="text" readonly value="<?= e($payAddress) ?>" onclick="this.select()" style="font-family:monospace;font-size:13px">
        <p class="dep-hint" style="margin-top:12px">
          Send only <?= e($payCurrency) ?> to this address. Your wallet credits as soon as the payment is detected.
        </p>
        <a class="finance-link" href="?order_id=<?= e(rawurlencode($orderId)) ?>">Check status</a>
      </div>
    <?php endif; ?>
  <?php endif; ?>
<?php
$FINANCE_EYEBROW = 'Deposit';
$FINANCE_TITLE = 'Complete payment';
$FINANCE_SUB = 'Secure payment checkout';
$FINANCE_BODY = ob_get_clean();

require dirname(__DIR__) . '/includes/finance-page.php';
