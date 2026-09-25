<?php
/**
 * Admin — Change Password
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'change_password';
$ADMIN_TITLE = 'Change Password';
$ADMIN_SUBTITLE = 'Update your admin password';
$ADMIN_BODY_CLASS = 'admin-change-password-page';

$flashOk = null;
$flashErr = null;

if (!empty($_SESSION['arowin_admin_change_pw_flash_ok'])) {
    $flashOk = (string) $_SESSION['arowin_admin_change_pw_flash_ok'];
    unset($_SESSION['arowin_admin_change_pw_flash_ok']);
}
if (!empty($_SESSION['arowin_admin_change_pw_flash_err'])) {
    $flashErr = (string) $_SESSION['arowin_admin_change_pw_flash_err'];
    unset($_SESSION['arowin_admin_change_pw_flash_err']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
        $flashErr = 'Session expired. Refresh and try again.';
    } else {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        
        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $flashErr = 'All fields are required.';
        } elseif (strlen($newPassword) < 6) {
            $flashErr = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $flashErr = 'New password and confirm password do not match.';
        } else {
          // Admin credentials are stored in .env, not in a database table.
          $adminUser = arowin_admin_username();
          $envPath = dirname(__DIR__, 3) . '/.env';
          $envContents = is_readable($envPath) ? file_get_contents($envPath) : false;

          if (!arowin_admin_authenticate($adminUser, $currentPassword)) {
                $flashErr = 'Current password is incorrect.';
          } elseif (!is_string($envContents)) {
            $flashErr = 'Unable to read admin configuration. Please try again.';
            } else {
            $updatedEnv = preg_replace(
              '/^ADMIN_PASSWORD=.*$/m',
              'ADMIN_PASSWORD=' . str_replace(["\\", "\n", "\r"], '', $newPassword),
              $envContents,
              1,
              $replacements
            );

            if (!is_string($updatedEnv) || $replacements !== 1
              || file_put_contents($envPath, $updatedEnv, LOCK_EX) === false) {
              $flashErr = 'Failed to update admin configuration. Please try again.';
            } else {
                    $_SESSION['arowin_admin_change_pw_flash_ok'] = 'Password updated successfully.';
                    admin_redirect('/dashboard/change-password');
                }
            }
        }
    }
}

$csrf = arowin_csrf_token();

require dirname(__DIR__, 2) . '/includes/layout-start.php';
?>

<?php if ($flashOk): ?><div class="admin-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="admin-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<section class="admin-section">
  <div class="section-header">
    <h2>Change Admin Password</h2>
    <p>Update your admin account password for security.</p>
  </div>

  <div class="admin-panel">
    <form method="POST" class="admin-form">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      
      <div class="form-group">
        <label for="current_password">Current Password</label>
        <input 
          type="password" 
          id="current_password" 
          name="current_password" 
          required 
          class="form-input"
          placeholder="Enter your current password"
        >
      </div>
      
      <div class="form-group">
        <label for="new_password">New Password</label>
        <input 
          type="password" 
          id="new_password" 
          name="new_password" 
          required 
          minlength="6"
          class="form-input"
          placeholder="Enter new password (min 6 characters)"
        >
      </div>
      
      <div class="form-group">
        <label for="confirm_password">Confirm New Password</label>
        <input 
          type="password" 
          id="confirm_password" 
          name="confirm_password" 
          required 
          minlength="6"
          class="form-input"
          placeholder="Confirm new password"
        >
      </div>
      
      <div class="form-actions">
        <button type="submit" class="admin-btn primary">Update Password</button>
      </div>
    </form>
  </div>
</section>

<?php require dirname(__DIR__, 2) . '/includes/layout-end.php'; ?>
