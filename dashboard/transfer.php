<?php
declare(strict_types=1);

require __DIR__ . '/includes/finance-bootstrap.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'transfer';

arowin_prepare_page([
    'title'       => 'Transfer — Arowin Trading Portal',
    'title_short' => 'Transfer',
    'description' => 'Transfer USDT between Arowin wallets.',
    'canonical'   => '/dashboard/transfer',
    'robots'      => 'noindex, nofollow',
    'faqs'        => false,
]);

$csrf = arowin_csrf_token();
$flashOk = null;
$flashErr = null;
$balance = arowin_wallet_balance((string) $user['username']);
$isPowerId = trim((string) ($user['power_owner'] ?? '')) !== '';
$toValue = '';
$toName = '';

$basePath = (string) (parse_url($BASE, PHP_URL_PATH) ?: '');
$transferApi = rtrim($basePath, '/') . '/dashboard/api/transfer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
        $flashErr = 'Session expired. Refresh and try again.';
    } else {
        $toValue = arowin_normalize_sponsor_id((string) ($_POST['to_user'] ?? ''));
        $amount = (float) ($_POST['amount'] ?? 0);
        $note = trim((string) ($_POST['note'] ?? ''));
        $result = arowin_wallet_transfer((string) $user['username'], $toValue, $amount, $note);
        if (!empty($result['ok'])) {
            $flashOk = $result['message'];
            $balance = (float) ($result['balance'] ?? arowin_wallet_balance((string) $user['username']));
            $toValue = '';
            $toName = '';
        } else {
            $flashErr = $result['message'] ?? 'Transfer failed.';
            $toUser = arowin_get_user_by_username($toValue);
            $toName = $toUser ? (string) ($toUser['full_name'] ?? '') : '';
        }
    }
}

ob_start();
?>
  <?php if ($flashOk): ?><div class="pkg-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
  <?php if ($flashErr): ?><div class="pkg-flash err"><?= e($flashErr) ?></div><?php endif; ?>

  <div class="finance-stats" style="margin-bottom:16px">
    <div class="finance-stat">
      <span>Available to transfer</span>
      <strong>$<?= e(arowin_fmt_money($balance)) ?> USDT</strong>
    </div>
  </div>

  <?php if ($isPowerId): ?>
    <div class="finance-empty">
      <h2>Main ID only</h2>
      <p>Power IDs cannot transfer funds.</p>
    </div>
  <?php else: ?>
    <form method="post" class="finance-form" id="transferPageForm" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <label for="to_user">Recipient User ID</label>
      <input id="to_user" name="to_user" type="text" required value="<?= e($toValue) ?>" placeholder="e.g. ARW48291736" style="text-transform:uppercase" autocomplete="off" spellcheck="false">
      <div class="xfer-preview<?= $toName === '' ? ' hidden' : '' ?>" id="transferPagePreview" aria-live="polite">
        <div class="xfer-preview-avatar" id="transferPageInitial"><?= e($toName !== '' ? mb_strtoupper(mb_substr($toName, 0, 1)) : '?') ?></div>
        <div class="xfer-preview-meta">
          <strong id="transferPageName"><?= e($toName !== '' ? $toName : '—') ?></strong>
          <span id="transferPageId"><?= e($toValue !== '' ? $toValue : '—') ?></span>
        </div>
        <span class="xfer-preview-badge">Selected</span>
      </div>
      <p class="xfer-lookup-msg" id="transferPageLookupMsg" hidden></p>
      <label for="amount">Amount (USDT)</label>
      <input id="amount" name="amount" type="number" min="1" step="0.01" required placeholder="e.g. 50">
      <label for="note">Note (optional)</label>
      <input id="note" name="note" type="text" maxlength="120" placeholder="Optional message">
      <button type="submit" class="finance-link" id="transferPageSubmit" style="border:0;cursor:pointer"<?= $toName === '' && $toValue === '' ? ' disabled' : '' ?>>Send transfer</button>
    </form>
    <script>
      (function () {
        const api = <?= json_encode($transferApi, JSON_UNESCAPED_SLASHES) ?>;
        const input = document.getElementById('to_user');
        const preview = document.getElementById('transferPagePreview');
        const nameEl = document.getElementById('transferPageName');
        const idEl = document.getElementById('transferPageId');
        const initialEl = document.getElementById('transferPageInitial');
        const msg = document.getElementById('transferPageLookupMsg');
        const submit = document.getElementById('transferPageSubmit');
        let timer = null;
        let selected = <?= json_encode($toValue !== '' && $toName !== '' ? $toValue : '') ?>;

        function show(ok, id, name, errText) {
          if (msg) {
            msg.hidden = !errText;
            msg.textContent = errText || '';
            msg.className = 'xfer-lookup-msg' + (errText ? ' err' : '');
          }
          if (!ok) {
            selected = '';
            if (preview) preview.classList.add('hidden');
            if (submit) submit.disabled = true;
            return;
          }
          selected = String(id || '').toUpperCase();
          if (preview) preview.classList.remove('hidden');
          if (nameEl) nameEl.textContent = name || 'Member';
          if (idEl) idEl.textContent = selected;
          if (initialEl) initialEl.textContent = (name || id || '?').trim().charAt(0).toUpperCase();
          if (submit) submit.disabled = false;
        }

        async function lookup(val) {
          const id = String(val || '').trim().toUpperCase().replace(/\s+/g, '');
          if (id.length < 4) return show(false);
          if (msg) { msg.hidden = false; msg.textContent = 'Looking up…'; msg.className = 'xfer-lookup-msg'; }
          try {
            const res = await fetch(api + '?lookup=1&user_id=' + encodeURIComponent(id), {
              headers: { Accept: 'application/json' },
              credentials: 'same-origin'
            });
            const data = await res.json();
            if (!data.ok || data.status !== 'valid') {
              return show(false, '', '', data.message || 'User ID not found.');
            }
            show(true, data.id, data.name);
          } catch (_) {
            show(false, '', '', 'Lookup failed. Try again.');
          }
        }

        if (!input) return;
        input.addEventListener('input', function () {
          input.value = input.value.trim().toUpperCase();
          if (submit) submit.disabled = true;
          if (preview) preview.classList.add('hidden');
          clearTimeout(timer);
          timer = setTimeout(function () { lookup(input.value); }, 350);
        });
        input.addEventListener('blur', function () { lookup(input.value); });
        document.getElementById('transferPageForm').addEventListener('submit', function (e) {
          if (!selected) {
            e.preventDefault();
            lookup(input.value);
          }
        });
      })();
    </script>
  <?php endif; ?>
<?php
$FINANCE_EYEBROW = 'Funds';
$FINANCE_TITLE = 'Transfer';
$FINANCE_SUB = 'Send wallet balance to another Arowin ID.';
$FINANCE_BODY = ob_get_clean();

require __DIR__ . '/includes/finance-page.php';
