<?php
/**
 * Admin — withdrawal history + operational recovery
 * Normal withdrawals auto-payout via NOWPayments (no admin approve).
 * Queued / 2FA only need ops action when NP balance or gateway 2FA blocks payout.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'withdrawals';
$ADMIN_TITLE = 'Withdrawals';
$ADMIN_SUBTITLE = 'Instant user payouts — history & recovery when NOWPayments funds/2FA block send';

$flashOk = null;
$flashErr = null;
$counts = arowin_withdrawal_status_counts();
$queuedSummary = arowin_withdrawal_queued_summary();

$filter = strtolower((string) ($_GET['status'] ?? ''));
$allowed = ['queued', 'awaiting_2fa', 'processing', 'finished', 'failed', 'rejected', 'refunded', 'pending', 'all'];
if ($filter === '' || !in_array($filter, $allowed, true)) {
    // Prefer Queued when NP low-fund recovery is needed; otherwise finished history
    $filter = ((int) ($queuedSummary['count'] ?? 0) > 0) ? 'queued' : 'finished';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
        $flashErr = 'Session expired. Refresh and try again.';
    } else {
        $id = (int) ($_POST['withdrawal_id'] ?? 0);
        $action = strtolower(trim((string) ($_POST['decision'] ?? '')));
        $note = trim((string) ($_POST['admin_note'] ?? ''));
        $code = trim((string) ($_POST['verification_code'] ?? ''));
        $admin = arowin_admin_username();

        if ($action === 'rejected') {
            $result = arowin_withdrawal_reject($id, $admin, $note);
        } elseif ($action === 'cancel_queued') {
            $row = arowin_withdrawal_find($id);
            if (!$row || strtolower((string) ($row['status'] ?? '')) !== 'queued') {
                $result = ['ok' => false, 'message' => 'Only queued withdrawals can be cancelled.'];
            } else {
                arowin_db()->prepare(
                    "UPDATE wallet_withdrawals
                     SET status = 'rejected',
                         reviewed_by = :by,
                         reviewed_at = UTC_TIMESTAMP(),
                         admin_note = :n
                     WHERE id = :id AND status = 'queued' LIMIT 1"
                )->execute([
                    ':by' => mb_substr($admin, 0, 80),
                    ':n' => $note !== '' ? mb_substr($note, 0, 255) : 'Cancelled queued withdrawal',
                    ':id' => $id,
                ]);
                $result = ['ok' => true, 'message' => 'Queued withdrawal cancelled. Master Wallet was never deducted.'];
            }
        } elseif ($action === 'verify_2fa') {
            $result = arowin_withdrawal_verify_2fa($id, $code, $admin);
        } else {
            $result = arowin_withdrawal_approve($id, $admin, $note, $code);
        }

        if (!empty($result['ok'])) {
            $flashOk = $result['message'];
            if (!empty($result['needs_2fa'])) {
                $flashOk .= ' (2FA required — enter code below on awaiting_2fa tab)';
            }
        } else {
            $flashErr = $result['message'] ?? 'Action failed.';
        }

        $counts = arowin_withdrawal_status_counts();
        $queuedSummary = arowin_withdrawal_queued_summary();
    }
}

$rows = [];
try {
    $rows = arowin_withdrawal_list($filter === 'all' ? 'all' : $filter, 200);
} catch (Throwable $e) {
    error_log('Admin withdrawals load: ' . $e->getMessage());
    $flashErr = $flashErr ?? 'Unable to load withdrawals.';
}

$csrf = arowin_csrf_token();
$pager = arowin_admin_paginate($rows);
$rows = $pager['rows'];
$payoutReady = arowin_nowpayments()->hasPayoutCredentials();
$npBal = 0.0;
$npCurrency = strtolower((string) (arowin_nowpayments_config()['payout_currency'] ?? 'usdtbsc'));
if ($payoutReady) {
    try {
        $npBal = arowin_nowpayments()->currencyBalance($npCurrency);
    } catch (Throwable $e) {
        $npBal = 0.0;
    }
}

require dirname(__DIR__, 2) . '/includes/layout-start.php';
?>

<?php if ($flashOk): ?><div class="admin-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="admin-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<?php if (!$payoutReady): ?>
  <div class="admin-flash err">
    Payout credentials incomplete. Set <code>NOWPAYMENTS_API_KEY</code>, <code>NOWPAYMENTS_EMAIL</code>, and <code>NOWPAYMENTS_PASSWORD</code> in <code>.env</code>.
  </div>
<?php endif; ?>

<?php if ((int) ($queuedSummary['count'] ?? 0) > 0): ?>
  <div class="admin-flash err" style="border-color:#f59e0b;background:rgba(245,158,11,.12);color:#fde68a">
    <strong>NOWPayments funds low — <?= (int) $queuedSummary['count'] ?> withdrawal(s) queued</strong><br>
    <?= (int) $queuedSummary['users'] ?> user(s) · total
    <?= e(arowin_admin_fmt_money((float) $queuedSummary['amount'])) ?> USDT
    waiting after NOWPayments top-up (retry payout — not a business approval).
    Current NP <?= e(strtoupper($npCurrency)) ?> balance:
    <strong><?= e(number_format($npBal, 4)) ?></strong>.
    <a href="?status=queued" style="color:#fff;text-decoration:underline;margin-left:6px">Open Queued list</a>
  </div>
<?php endif; ?>

<p class="admin-muted" style="margin:0 0 14px;font-size:13px">
  Member withdrawals send automatically. You only act here if NOWPayments balance is low (Queued)
  or the gateway asks for a 2FA code — not to “approve” each withdraw.
</p>

<div class="admin-tabs">
  <?php
  $tabs = [
      'finished' => 'Finished',
      'queued' => 'Queued (NP low)',
      'awaiting_2fa' => 'Awaiting 2FA',
      'processing' => 'Processing',
      'failed' => 'Failed',
      'rejected' => 'Rejected',
      'refunded' => 'Refunded',
      'all' => 'All',
  ];
  // Only show legacy Pending if any stuck rows exist
  if ((int) ($counts['pending'] ?? 0) > 0) {
      $tabs = ['pending' => 'Stuck pending'] + $tabs;
  }
  foreach ($tabs as $key => $label):
      $n = (int) ($counts[$key] ?? 0);
  ?>
    <a class="<?= $filter === $key ? 'active' : '' ?>" href="?status=<?= e($key) ?>">
      <?= e($label) ?><?= $n > 0 ? ' (' . $n . ')' : '' ?>
    </a>
  <?php endforeach; ?>
</div>

<section class="admin-panel">
  <?php if ($filter === 'queued'): ?>
    <p class="admin-muted" style="margin:0 0 14px;font-size:13px">
      These users withdrew while NOWPayments custody had insufficient balance.
      Master Wallet was <strong>not</strong> deducted. Top up NOWPayments, then <strong>Retry payout</strong> on each row.
    </p>
  <?php elseif ($filter === 'pending'): ?>
    <p class="admin-muted" style="margin:0 0 14px;font-size:13px">
      Rare stuck rows from a failed instant send. Retry payout or refund — this is recovery, not approval.
    </p>
  <?php endif; ?>

  <?php if ($pager['total'] < 1): ?>
    <div class="admin-empty">No <?= e($filter) ?> withdrawals.</div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>User</th>
            <th>Contact</th>
            <th>Amount</th>
            <th>Address</th>
            <th>Time</th>
            <th>Status / Reason</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php
              $status = strtolower((string) ($row['status'] ?? 'pending'));
              $badge = $status;
              $fullName = trim((string) ($row['user_full_name'] ?? ''));
              $email = trim((string) ($row['user_email'] ?? ''));
              $phone = trim((string) ($row['user_phone'] ?? ''));
              $reason = trim((string) ($row['error_log'] ?? $row['admin_note'] ?? ''));
            ?>
            <tr>
              <td>#<?= (int) $row['id'] ?></td>
              <td>
                <strong class="admin-mono"><?= e((string) $row['username']) ?></strong>
                <?php if ($fullName !== ''): ?>
                  <br><span class="admin-muted"><?= e($fullName) ?></span>
                <?php endif; ?>
                <?php if ($status === 'queued' && isset($row['user_wallet_balance'])): ?>
                  <br><span class="admin-muted">Wallet $<?= e(number_format((float) $row['user_wallet_balance'], 2)) ?></span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px">
                <?= $email !== '' ? e($email) : '<span class="admin-muted">—</span>' ?>
                <?php if ($phone !== ''): ?>
                  <br><?= e($phone) ?>
                <?php endif; ?>
              </td>
              <td>
                <?= e(arowin_admin_fmt_money((float) $row['amount'])) ?><br>
                <span class="admin-muted"><?= e(strtoupper((string) $row['currency'])) ?></span>
              </td>
              <td><code style="font-size:11px;word-break:break-all"><?= e((string) $row['wallet_address']) ?></code></td>
              <td style="font-size:12px;white-space:nowrap">
                <?= e(function_exists('arowin_format_india_time')
                  ? arowin_format_india_time((string) ($row['created_at'] ?? ''))
                  : (string) ($row['created_at'] ?? '—')) ?>
              </td>
              <td>
                <span class="admin-badge <?= e($badge) ?>"><?= e(ucfirst(str_replace('_', ' ', $status))) ?></span>
                <?php if ($reason !== ''): ?>
                  <br><span class="admin-muted" style="font-size:11px"><?= e(mb_substr($reason, 0, 160)) ?></span>
                <?php endif; ?>
                <?php if (!empty($row['nowpayments_batch_id'])): ?>
                  <br><span class="admin-muted" style="font-size:11px">Batch: <?= e((string) $row['nowpayments_batch_id']) ?></span>
                <?php endif; ?>
                <?php if (!empty($row['tx_hash'])): ?>
                  <br><span class="admin-muted" style="font-size:11px">Tx: <?= e((string) $row['tx_hash']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($status === 'queued' || $status === 'pending'): ?>
                  <form method="post" class="admin-actions">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="withdrawal_id" value="<?= (int) $row['id'] ?>">
                    <input class="admin-input" type="text" name="admin_note" placeholder="Optional note">
                    <input class="admin-input" type="text" name="verification_code" placeholder="2FA code (if required)" autocomplete="one-time-code">
                    <button class="admin-btn approve" name="decision" value="approved" type="submit">Retry payout</button>
                    <?php if ($status === 'pending'): ?>
                      <button class="admin-btn reject" name="decision" value="rejected" type="submit">Refund wallet</button>
                    <?php else: ?>
                      <button class="admin-btn reject" name="decision" value="cancel_queued" type="submit">Cancel queue</button>
                    <?php endif; ?>
                  </form>
                <?php elseif ($status === 'awaiting_2fa'): ?>
                  <form method="post" class="admin-actions">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="withdrawal_id" value="<?= (int) $row['id'] ?>">
                    <input class="admin-input" type="text" name="verification_code" required placeholder="Email 2FA code" autocomplete="one-time-code">
                    <button class="admin-btn approve" name="decision" value="verify_2fa" type="submit">Enter NP 2FA</button>
                  </form>
                <?php else: ?>
                  <span class="admin-muted"><?= e((string) ($row['reviewed_by'] ?? '—')) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= arowin_admin_pagination_html($pager) ?>
  <?php endif; ?>
</section>

<?php require dirname(__DIR__, 2) . '/includes/layout-end.php'; ?>
