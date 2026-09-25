<?php
declare(strict_types=1);

require __DIR__ . '/includes/finance-bootstrap.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'withdrawal';

arowin_prepare_page([
    'title'       => 'Withdrawal — Arowin Trading Portal',
    'title_short' => 'Withdrawal',
    'description' => 'Withdraw USDT from your Arowin wallet.',
    'canonical'   => '/dashboard/withdrawal',
    'robots'      => 'noindex, nofollow',
    'faqs'        => false,
]);

$balance = arowin_wallet_balance((string) $user['username']);
$isPowerId = trim((string) ($user['power_owner'] ?? '')) !== '';
$history = arowin_withdrawal_list_for_user((string) $user['username'], 20);
$settingsUrl = url('operator-configuration');

ob_start();
?>
  <div class="finance-stats" style="margin-bottom:16px">
    <div class="finance-stat">
      <span>Master Wallet</span>
      <strong>$<?= e(arowin_fmt_money($balance)) ?> USDT</strong>
    </div>
  </div>

  <?php if ($isPowerId): ?>
    <div class="finance-empty">
      <h2>Main ID only</h2>
      <p>Power IDs cannot withdraw.</p>
    </div>
  <?php else: ?>
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px">
      <button type="button" class="finance-link" style="border:0;cursor:pointer" onclick="openWithdrawalModal()">Withdraw now</button>
      <a class="finance-link" href="<?= e($settingsUrl) ?>">Withdrawal setup</a>
    </div>
    <p class="dep-hint" style="margin-bottom:20px">Use bottom bar <strong>Withdrawal</strong> or the button above. Set password &amp; addresses in Operator Configuration first.</p>
  <?php endif; ?>

  <?php if ($history !== []): ?>
    <div style="margin-top:12px">
      <h3 style="margin:0 0 12px;font-size:15px">Your withdrawal requests</h3>
      <div class="admin-table-wrap" style="overflow:auto">
        <table class="admin-table" style="width:100%;font-size:13px">
          <thead>
            <tr>
              <th>ID</th>
              <th>Amount</th>
              <th>Address</th>
              <th>Status</th>
              <th>Tx hash</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $w): ?>
              <tr>
                <td>#<?= (int) $w['id'] ?></td>
                <td>$<?= e(number_format((float) $w['amount'], 2)) ?></td>
                <td style="word-break:break-all;font-size:11px"><?= e((string) ($w['wallet_address'] ?: '—')) ?></td>
                <td><?= e(ucfirst((string) $w['status'])) ?></td>
                <td style="word-break:break-all;font-size:11px"><?= e((string) ($w['tx_hash'] ?: '—')) ?></td>
                <td><?= e(arowin_format_india_time((string) ($w['created_at'] ?? ''))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php else: ?>
    <div class="finance-empty">
      <h2>No withdrawals yet</h2>
      <p>Your withdrawal history will appear here.</p>
    </div>
  <?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/includes/finance-layout.php';
