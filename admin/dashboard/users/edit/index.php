<?php
/**
 * Admin — edit a user's identity details.
 */
declare(strict_types=1);

require dirname(__DIR__, 3) . '/includes/init.php';
require dirname(__DIR__, 3) . '/includes/auth-check.php';

$ADMIN_PAGE = 'users';
$ADMIN_TITLE = 'Edit User Profile';
$ADMIN_SUBTITLE = 'Administrator-only identity details';
$flashOk = null;
$flashErr = null;
$username = arowin_normalize_sponsor_id((string) ($_GET['username'] ?? $_POST['username'] ?? ''));
$user = $username !== '' ? arowin_get_operator_profile($username) : null;
if (!$user && $username !== '' && function_exists('arowin_get_user_by_username')) {
    $user = arowin_get_user_by_username($username);
}

if (!$user) {
    $flashErr = 'User not found.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
        $flashErr = 'Session expired. Refresh and try again.';
    } else {
        $result = arowin_admin_update_user_profile($username, [
            'full_name' => (string) ($_POST['full_name'] ?? ''),
            'email' => (string) ($_POST['email'] ?? ''),
            'phone' => (string) ($_POST['phone'] ?? ''),
            'usdt_wallet' => (string) ($_POST['usdt_wallet'] ?? ''),
            'country' => (string) ($_POST['country'] ?? ''),
        ]);
        if (!empty($result['ok'])) {
            $flashOk = (string) ($result['message'] ?? 'Profile updated.');
            $user = arowin_get_operator_profile($username) ?: $user;
        } else {
            $flashErr = (string) ($result['message'] ?? 'Profile update failed.');
        }
    }
}

require dirname(__DIR__, 3) . '/includes/layout-start.php';
?>
<?php if ($flashOk): ?><div class="admin-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="admin-flash err"><?= e($flashErr) ?></div><?php endif; ?>
<section class="admin-panel" style="max-width:720px">
  <div class="admin-panel-head">
    <h2><?= e($username !== '' ? $username : 'User') ?></h2>
    <a class="admin-btn ghost" href="<?= e(admin_url('/dashboard/users/')) ?>">Back to users</a>
  </div>
  <?php if ($user): ?>
    <form method="post" class="admin-form">
      <input type="hidden" name="csrf" value="<?= e(arowin_csrf_token()) ?>">
      <input type="hidden" name="username" value="<?= e($username) ?>">
      <label>Full name
        <input class="admin-input" name="full_name" type="text" required value="<?= e((string) ($user['full_name'] ?? '')) ?>">
      </label>
      <label>Email
        <input class="admin-input" name="email" type="email" value="<?= e((string) ($user['email'] ?? '')) ?>">
      </label>
      <label>Mobile
        <input class="admin-input" name="phone" type="tel" required value="<?= e((string) ($user['phone'] ?? '')) ?>">
      </label>
      <label>USDT wallet
        <input class="admin-input" name="usdt_wallet" type="text" value="<?= e((string) ($user['usdt_wallet'] ?? '')) ?>">
      </label>
      <label>Country
        <input class="admin-input" name="country" type="text" value="<?= e((string) ($user['country'] ?? '')) ?>">
      </label>
      <button class="admin-btn primary" type="submit">Save profile</button>
    </form>
  <?php endif; ?>
</section>
<?php require dirname(__DIR__, 3) . '/includes/layout-end.php'; ?>
