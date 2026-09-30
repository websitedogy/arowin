<?php
/**
 * Admin — users — /admin/dashboard/users
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'users';
$ADMIN_TITLE = 'Users';
$ADMIN_SUBTITLE = 'Registered operators — package purchase activates; Block stops sign-in';
$ADMIN_BODY_CLASS = 'admin-users-page';

$flashOk = null;
$flashErr = null;
$filter = strtolower((string) ($_GET['status'] ?? 'all'));
if (!in_array($filter, ['pending', 'active', 'inactive', 'all'], true)) {
    $filter = 'all';
}
$q = trim((string) ($_GET['q'] ?? ''));

if (!empty($_SESSION['arowin_admin_users_flash_ok'])) {
    $flashOk = (string) $_SESSION['arowin_admin_users_flash_ok'];
    unset($_SESSION['arowin_admin_users_flash_ok']);
}
if (!empty($_SESSION['arowin_admin_users_flash_err'])) {
    $flashErr = (string) $_SESSION['arowin_admin_users_flash_err'];
    unset($_SESSION['arowin_admin_users_flash_err']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
        $flashErr = 'Session expired. Refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $username = arowin_normalize_sponsor_id((string) ($_POST['username'] ?? ''));
        if ($action === 'set_status') {
            $newStatus = strtolower(trim((string) ($_POST['new_status'] ?? '')));
            // Active is set only by package purchase — admin cannot activate manually
            if ($newStatus === 'active') {
                $flashErr = 'Members become active automatically after package purchase. Admin cannot activate manually.';
            } else {
                $result = arowin_admin_set_user_status($username, $newStatus);
                if (!empty($result['ok'])) {
                    $flashOk = $result['message'];
                } else {
                    $flashErr = $result['message'] ?? 'Status update failed.';
                }
            }
        } elseif ($action === 'reset_password') {
            require_once dirname(__DIR__, 3) . '/includes/forgot-password-repository.php';
            $result = arowin_admin_reset_user_password($username);
            if (!empty($result['ok'])) {
                $flashOk = $result['message'];
            } else {
                $flashErr = $result['message'] ?? 'Password reset failed.';
            }
        }
    }
}

$counts = ['pending' => 0, 'active' => 0, 'inactive' => 0, 'all' => 0];
$rows = [];
try {
    $counts = arowin_admin_user_status_counts();
    $rows = arowin_admin_list_users($filter, $q, 300);
} catch (Throwable $e) {
    error_log('Admin users load error: ' . $e->getMessage());
    $flashErr = $flashErr ?? 'Unable to load users right now.';
}

$csrf = arowin_csrf_token();
$loginAsAction = admin_url('/dashboard/users/login-as');
$pageBase = admin_url('/dashboard/users');
$pager = arowin_admin_paginate($rows);
$rows = $pager['rows'];

$tabUrl = static function (string $tab) use ($pageBase, $q): string {
    $url = $pageBase . '?status=' . rawurlencode($tab);
    if ($q !== '') {
        $url .= '&q=' . rawurlencode($q);
    }
    return $url;
};

$formatDate = static function (?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($raw))->format('d M Y, H:i');
    } catch (Throwable $e) {
        return $raw;
    }
};

require dirname(__DIR__, 2) . '/includes/layout-start.php';
?>

<?php if ($flashOk): ?><div class="admin-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="admin-flash err"><?= e($flashErr) ?></div><?php endif; ?>

<section class="admin-stats admin-stats-users" aria-label="User overview">
  <a class="admin-stat<?= $filter === 'all' ? ' is-selected' : '' ?>" href="<?= e($tabUrl('all')) ?>">
    <div class="stat-top">
      <span class="label">Total users</span>
      <span class="stat-icon" aria-hidden="true">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      </span>
    </div>
    <span class="value"><?= (int) $counts['all'] ?></span>
  </a>
  <a class="admin-stat pending<?= $filter === 'pending' ? ' is-selected' : '' ?>" href="<?= e($tabUrl('pending')) ?>">
    <div class="stat-top">
      <span class="label">Pending</span>
      <span class="stat-icon" aria-hidden="true">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
      </span>
    </div>
    <span class="value accent"><?= (int) $counts['pending'] ?></span>
  </a>
  <a class="admin-stat approved<?= $filter === 'active' ? ' is-selected' : '' ?>" href="<?= e($tabUrl('active')) ?>">
    <div class="stat-top">
      <span class="label">Active</span>
      <span class="stat-icon" aria-hidden="true">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
      </span>
    </div>
    <span class="value green"><?= (int) $counts['active'] ?></span>
  </a>
  <a class="admin-stat rejected<?= $filter === 'inactive' ? ' is-selected' : '' ?>" href="<?= e($tabUrl('inactive')) ?>">
    <div class="stat-top">
      <span class="label">Blocked</span>
      <span class="stat-icon" aria-hidden="true">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M4.9 4.9l14.2 14.2"/></svg>
      </span>
    </div>
    <span class="value red"><?= (int) $counts['inactive'] ?></span>
  </a>
</section>

<div class="admin-toolbar">
  <div class="admin-tabs">
    <?php foreach ([
        'all' => 'All',
        'pending' => 'Pending',
        'active' => 'Active',
        'inactive' => 'Blocked',
    ] as $key => $label): ?>
      <a class="<?= $filter === $key ? 'active' : '' ?>" href="<?= e($tabUrl($key)) ?>">
        <?= e($label) ?>
        <span class="tab-count"><?= (int) ($counts[$key] ?? 0) ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <form class="admin-search" method="get" action="<?= e($pageBase) ?>">
    <input type="hidden" name="status" value="<?= e($filter) ?>">
    <input
      class="admin-input"
      type="search"
      name="q"
      value="<?= e($q) ?>"
      placeholder="Search ID, name, phone, email…"
      autocomplete="off"
    >
    <button class="admin-btn link" type="submit">Search</button>
    <?php if ($q !== ''): ?>
      <a class="admin-btn ghost" href="<?= e($tabUrl($filter)) ?>">Clear</a>
    <?php endif; ?>
  </form>
</div>

<section class="admin-panel admin-panel-compact">
  <div class="admin-panel-head">
    <h2><?= e(ucfirst($filter === 'all' ? 'All users' : $filter . ' users')) ?></h2>
    <span class="admin-muted"><?= (int) $pager['total'] ?> total</span>
  </div>

  <?php if ($pager['total'] < 1): ?>
    <div class="admin-empty">
      <?php if ($q !== ''): ?>
        No users match “<?= e($q) ?>”.
      <?php else: ?>
        No <?= e($filter === 'all' ? '' : $filter . ' ') ?>users found.
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table admin-table-compact">
        <thead>
          <tr>
            <th>#</th>
            <th>User ID</th>
            <th>Name</th>
            <th>Contact</th>
            <th>Sponsor</th>
            <th>Pkg</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $i => $user): ?>
            <?php
              $username = (string) ($user['username'] ?? '');
              $status = strtolower((string) ($user['status'] ?? 'pending'));
              $badge = arowin_admin_user_status_badge_class($status);
              $label = arowin_admin_user_status_label($status);
              $pkg = trim((string) ($user['purchased_package'] ?? ''));
              if ($pkg === '') {
                  $pkg = trim((string) ($user['node_package'] ?? ''));
              }
              $email = trim((string) ($user['email'] ?? ''));
              $phone = trim((string) ($user['phone'] ?? ''));
              $contact = trim(($phone !== '' ? $phone : '') . ($phone !== '' && $email !== '' ? ' · ' : '') . ($email !== '' ? $email : ''));
              if ($contact === '') {
                  $contact = '—';
              }
            ?>
            <tr>
              <td class="admin-muted"><?= $i + 1 ?></td>
              <td><strong class="admin-mono"><?= e($username) ?></strong></td>
              <td><?= e((string) ($user['full_name'] ?? '—')) ?></td>
              <td class="admin-contact admin-muted" title="<?= e($contact) ?>"><?= e($contact) ?></td>
              <td class="admin-mono"><?= e((string) ($user['sponsor_id'] ?? '—')) ?></td>
              <td class="admin-muted"><?= e($pkg !== '' ? $pkg : '—') ?></td>
              <td><span class="admin-badge <?= e($badge) ?>"><?= e($label) ?></span></td>
              <td class="admin-muted"><?= e($formatDate(isset($user['created_at']) ? (string) $user['created_at'] : null)) ?></td>
              <td>
                <div class="admin-user-actions">
                  <?php if (arowin_is_mother_id($username)): ?>
                    <span class="admin-badge active" title="Permanent root account">Mother ID · protected</span>
                  <?php else: ?>
                  <form method="post" action="<?= e($loginAsAction) ?>" target="_blank" rel="noopener">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="username" value="<?= e($username) ?>">
                    <input type="hidden" name="status" value="<?= e($filter) ?>">
                    <button class="admin-btn link" type="submit" title="Open member dashboard">Login as</button>
                  </form>
                  <a class="admin-btn ghost" href="<?= e(admin_url('/dashboard/users/edit') . '?username=' . rawurlencode($username)) ?>">Edit profile</a>
                  <?php if ($status !== 'rejected'): ?>
                    <form method="post" data-confirm="<?= e('Are you sure you want to block ' . $username . '? They will not be able to sign in with this ID.') ?>">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="action" value="set_status">
                      <input type="hidden" name="username" value="<?= e($username) ?>">
                      <input type="hidden" name="new_status" value="inactive">
                      <button class="admin-btn reject" type="submit">Block</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($status === 'rejected'): ?>
                    <form method="post" data-confirm="<?= e('Unblock ' . $username . '? They will be able to sign in again.') ?>">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="action" value="set_status">
                      <input type="hidden" name="username" value="<?= e($username) ?>">
                      <input type="hidden" name="new_status" value="pending">
                      <button class="admin-btn ghost" type="submit">Unblock</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" data-confirm="<?= e('Reset password for ' . $username . '? A new password will be emailed to their registered email address.') ?>">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="username" value="<?= e($username) ?>">
                    <button class="admin-btn ghost" type="submit">Reset Password</button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="admin-mobile-list" aria-label="User list">
      <?php foreach ($rows as $i => $user): ?>
        <?php
          $username = (string) ($user['username'] ?? '');
          $status = strtolower((string) ($user['status'] ?? 'pending'));
          $badge = arowin_admin_user_status_badge_class($status);
          $label = arowin_admin_user_status_label($status);
          $pkg = trim((string) ($user['purchased_package'] ?? ''));
          if ($pkg === '') {
              $pkg = trim((string) ($user['node_package'] ?? ''));
          }
        ?>
        <article class="admin-mobile-card">
          <div class="row">
            <span class="k">User</span>
            <span class="v">
              <strong class="admin-mono"><?= e($username) ?></strong><br>
              <?= e((string) ($user['full_name'] ?? '—')) ?>
            </span>
          </div>
          <div class="row">
            <span class="k">Contact</span>
            <span class="v admin-muted">
              <?= e((string) ($user['phone'] ?? '—')) ?><br>
              <?= e((string) ($user['email'] ?? '—')) ?>
            </span>
          </div>
          <div class="row">
            <span class="k">Sponsor</span>
            <span class="v admin-mono"><?= e((string) ($user['sponsor_id'] ?? '—')) ?></span>
          </div>
          <div class="row">
            <span class="k">Status</span>
            <span class="v"><span class="admin-badge <?= e($badge) ?>"><?= e($label) ?></span></span>
          </div>
          <div class="row">
            <span class="k">Joined</span>
            <span class="v admin-muted"><?= e($formatDate(isset($user['created_at']) ? (string) $user['created_at'] : null)) ?></span>
          </div>
          <div class="admin-user-actions" style="margin-top:12px;">
            <?php if (arowin_is_mother_id($username)): ?>
              <span class="admin-badge active">Mother ID · protected</span>
            <?php else: ?>
            <form method="post" action="<?= e($loginAsAction) ?>" target="_blank" rel="noopener">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="username" value="<?= e($username) ?>">
              <input type="hidden" name="status" value="<?= e($filter) ?>">
              <button class="admin-btn link" type="submit">Login as</button>
            </form>
            <a class="admin-btn ghost" href="<?= e(admin_url('/dashboard/users/edit') . '?username=' . rawurlencode($username)) ?>">Edit profile</a>
            <?php if ($status !== 'rejected'): ?>
              <form method="post" data-confirm="<?= e('Are you sure you want to block ' . $username . '? They will not be able to sign in with this ID.') ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="username" value="<?= e($username) ?>">
                <input type="hidden" name="new_status" value="inactive">
                <button class="admin-btn reject" type="submit">Block</button>
              </form>
            <?php endif; ?>
            <?php if ($status === 'rejected'): ?>
              <form method="post" data-confirm="<?= e('Unblock ' . $username . '? They will be able to sign in again.') ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="username" value="<?= e($username) ?>">
                <input type="hidden" name="new_status" value="pending">
                <button class="admin-btn ghost" type="submit">Unblock</button>
              </form>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?= arowin_admin_pagination_html($pager) ?>
  <?php endif; ?>
</section>

<?php require dirname(__DIR__, 2) . '/includes/layout-end.php'; ?>
