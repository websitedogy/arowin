<?php
/**
 * Admin — Withdrawal Address Permissions Management
 * Shows users with disabled withdrawal address permission and allows admin to add addresses
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'withdrawal_permissions';
$ADMIN_TITLE = 'Withdrawal Address Permissions';
$ADMIN_SUBTITLE = 'Manage users who cannot add withdrawal addresses';
$ADMIN_BODY_CLASS = 'admin-withdrawal-permissions-page';

require_once dirname(__DIR__, 3) . '/includes/withdrawal-settings-repository.php';

$flashOk = null;
$flashErr = null;

if (!empty($_SESSION['arowin_admin_wd_perm_flash_ok'])) {
    $flashOk = (string) $_SESSION['arowin_admin_wd_perm_flash_ok'];
    unset($_SESSION['arowin_admin_wd_perm_flash_ok']);
}
if (!empty($_SESSION['arowin_admin_wd_perm_flash_err'])) {
    $flashErr = (string) $_SESSION['arowin_admin_wd_perm_flash_err'];
    unset($_SESSION['arowin_admin_wd_perm_flash_err']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
        $flashErr = 'Session expired. Refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'add_address') {
            $targetUsername = arowin_normalize_sponsor_id((string) ($_POST['target_username'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ''));
            $label = trim((string) ($_POST['label'] ?? ''));
            $currency = trim((string) ($_POST['currency'] ?? 'usdtbsc'));
            $isDefault = !empty($_POST['is_default']);
            
            $adminUser = (string) ($_SESSION['arowin_admin_uid'] ?? 'admin');
            $result = arowin_admin_add_withdrawal_address($adminUser, $targetUsername, $address, $label, $currency, $isDefault);
            
            if (!empty($result['ok'])) {
                $_SESSION['arowin_admin_wd_perm_flash_ok'] = $result['message'];
            } else {
                $flashErr = $result['message'] ?? 'Failed to add address.';
            }
        } elseif ($action === 'enable_permission') {
            $targetUsername = arowin_normalize_sponsor_id((string) ($_POST['target_username'] ?? ''));
            $result = arowin_set_withdrawal_address_permission($targetUsername, true);
            
            if (!empty($result['ok'])) {
                $_SESSION['arowin_admin_wd_perm_flash_ok'] = $result['message'];
            } else {
                $flashErr = $result['message'] ?? 'Failed to enable permission.';
            }
        }
    }
}

$disabledUsers = arowin_get_users_with_disabled_withdrawal_address();
$csrf = arowin_csrf_token();

require dirname(__DIR__, 2) . '/includes/layout-start.php';
?>

<?php if ($flashOk): ?><div class="admin-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="admin-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<section class="admin-section">
  <div class="section-header">
    <h2>Users with Disabled Withdrawal Address Permission</h2>
    <p>These users cannot add withdrawal addresses. You can add addresses on their behalf or enable their permission.</p>
  </div>

  <?php if ($disabledUsers === []): ?>
    <div class="admin-empty-state">
      <p>No users have disabled withdrawal address permission.</p>
    </div>
  <?php else: ?>
    <div class="admin-table-wrapper">
      <table class="admin-table">
        <thead>
          <tr>
            <th>User ID</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($disabledUsers as $user): ?>
            <tr>
              <td><strong><?= e($user['username']) ?></strong></td>
              <td><?= e($user['full_name'] ?? '—') ?></td>
              <td><?= e($user['email'] ?? '—') ?></td>
              <td><?= e($user['phone'] ?? '—') ?></td>
              <td><?= $user['created_at'] ? date('d M Y', strtotime($user['created_at'])) : '—' ?></td>
              <td>
                <button type="button" class="admin-btn admin-btn-sm" onclick="showAddAddressModal('<?= e($user['username']) ?>', '<?= e($user['full_name'] ?? $user['username']) ?>')">
                  Add Address
                </button>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="action" value="enable_permission">
                  <input type="hidden" name="target_username" value="<?= e($user['username']) ?>">
                  <button type="submit" class="admin-btn admin-btn-sm approve">
                    Enable Permission
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- Add Address Modal -->
<div class="admin-modal hidden" id="addAddressModal" aria-hidden="true">
  <div class="admin-modal-content" onclick="event.stopPropagation()">
    <div class="admin-modal-header">
      <h3>Add Withdrawal Address</h3>
      <button type="button" class="admin-modal-close" onclick="closeAddAddressModal()">&times;</button>
    </div>
    <form method="POST" class="admin-modal-body">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="action" value="add_address">
      <input type="hidden" name="target_username" id="modalTargetUsername" value="">
      
      <div class="form-group">
        <label>For User</label>
        <input type="text" id="modalTargetUserDisplay" readonly class="form-input readonly">
      </div>
      
      <div class="form-group">
        <label for="modalAddress">Withdrawal Address</label>
        <input type="text" id="modalAddress" name="address" required placeholder="0x..." class="form-input">
      </div>
      
      <div class="form-group">
        <label for="modalLabel">Label (optional)</label>
        <input type="text" id="modalLabel" name="label" placeholder="e.g., My Wallet" class="form-input">
      </div>
      
      <div class="form-group">
        <label for="modalCurrency">Currency</label>
        <select id="modalCurrency" name="currency" class="form-input">
          <option value="usdtbsc">USDT-BEP20 (BSC)</option>
          <option value="usdttrc20">USDT-TRC20 (TRON)</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>
          <input type="checkbox" name="is_default" value="1"> Set as default address
        </label>
      </div>
      
      <div class="admin-modal-footer">
        <button type="button" class="admin-btn" onclick="closeAddAddressModal()">Cancel</button>
        <button type="submit" class="admin-btn primary">Add Address</button>
      </div>
    </form>
  </div>
</div>

<script>
function showAddAddressModal(username, displayName) {
  document.getElementById('modalTargetUsername').value = username;
  document.getElementById('modalTargetUserDisplay').value = displayName + ' (' + username + ')';
  document.getElementById('addAddressModal').classList.remove('hidden');
  document.getElementById('addAddressModal').setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
}

function closeAddAddressModal() {
  document.getElementById('addAddressModal').classList.add('hidden');
  document.getElementById('addAddressModal').setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
}

document.getElementById('addAddressModal').addEventListener('click', function(e) {
  if (e.target === this) closeAddAddressModal();
});
</script>

<?php require dirname(__DIR__, 2) . '/includes/layout-end.php'; ?>
