<?php
declare(strict_types=1);

$transferCsrf = arowin_csrf_token();
$transferBasePath = (string) (parse_url($BASE, PHP_URL_PATH) ?: '');
$transferApi = rtrim($transferBasePath, '/') . '/dashboard/api/transfer.php';
$transferBalance = function_exists('arowin_wallet_balance') && !empty($user['username'] ?? null)
    ? arowin_wallet_balance((string) $user['username'])
    : (float) ($view['wallet'] ?? 0);
$transferIsPower = !empty($user) && trim((string) ($user['power_owner'] ?? '')) !== '';
?>
<div class="dep-overlay hidden" id="transferOverlay" onclick="closeTransferModal(event)" aria-hidden="true">
  <div class="dep-sheet" onclick="event.stopPropagation()" role="dialog" aria-modal="true" aria-labelledby="transferTitle">
    <div class="dep-head">
      <div>
        <p class="dep-eyebrow">Funds</p>
        <h2 id="transferTitle">Transfer USDT</h2>
      </div>
      <button type="button" class="dep-close" onclick="closeTransferModal()" aria-label="Close">&times;</button>
    </div>

    <div class="dep-balance">
      <span>Available to transfer</span>
      <strong id="transferBalanceLabel">$<?= e(number_format($transferBalance, 2)) ?> USDT</strong>
    </div>

    <?php if ($transferIsPower): ?>
      <div class="finance-empty" style="padding:18px 8px">
        <h2 style="font-size:16px;margin:0 0 6px">Main ID only</h2>
        <p style="margin:0;color:var(--text-muted);font-size:13px">Power IDs cannot transfer funds.</p>
      </div>
    <?php else: ?>
      <form id="transferForm" class="dep-form" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e($transferCsrf) ?>">
        <?= function_exists('arowin_account_hidden_field') ? arowin_account_hidden_field() : '' ?>

        <label for="transferToUser">Recipient User ID</label>
        <input
          id="transferToUser"
          name="to_user"
          type="text"
          required
          placeholder="e.g. ARW48291736"
          style="text-transform:uppercase"
          autocomplete="off"
          spellcheck="false"
        >

        <div class="xfer-preview hidden" id="transferPreview" aria-live="polite">
          <div class="xfer-preview-avatar" id="transferPreviewInitial">?</div>
          <div class="xfer-preview-meta">
            <strong id="transferPreviewName">—</strong>
            <span id="transferPreviewId">—</span>
          </div>
          <span class="xfer-preview-badge" id="transferPreviewBadge">Select</span>
        </div>
        <p class="xfer-lookup-msg" id="transferLookupMsg" hidden></p>

        <label for="transferAmount">Amount (USDT)</label>
        <div class="dep-amount-wrap">
          <span>$</span>
          <input id="transferAmount" name="amount" type="number" min="1" step="0.01" required placeholder="0.00" inputmode="decimal">
          <em class="dep-amount-unit">USDT</em>
        </div>

        <label for="transferNote">Note (optional)</label>
        <input id="transferNote" name="note" type="text" maxlength="120" placeholder="Optional message">

        <p class="dep-hint">Amount is deducted from your Master Wallet and credited to the recipient’s Master Wallet.</p>
        <p class="dep-msg" id="transferMsg" hidden></p>
        <button type="submit" class="dep-submit" id="transferSubmit" disabled>Send transfer</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<script>
  window.AROWIN_TRANSFER_API = <?= json_encode($transferApi, JSON_UNESCAPED_SLASHES) ?>;
  window.AROWIN_TRANSFER_IS_POWER = <?= $transferIsPower ? 'true' : 'false' ?>;
</script>
