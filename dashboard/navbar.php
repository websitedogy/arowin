<?php
declare(strict_types=1);
/** @var array $user */
/** @var array $view */

$accountApiUrl = url('account-api');
$photoUrl = $view['profile_photo'] ?? null;

$collabHomeUid = '';
$collabViewing = false;
$collabHelpers = __DIR__ . '/team-collaborations/includes/collaborations.php';
if (is_file($collabHelpers)) {
    require_once $collabHelpers;
    if (function_exists('arowin_collab_is_viewing_member') && arowin_collab_is_viewing_member()) {
        $collabViewing = true;
        $collabHomeUid = arowin_collab_home_uid();
    }
}
$collabRestoreUrl = arowin_url_with_account(
    arowin_abs($BASE, '/dashboard/team-collaborations/restore.php?to=collaborations')
);

$adminImpersonating = false;
$adminExitUrl = '';
if (
    function_exists('arowin_is_admin_logged_in')
    && arowin_is_admin_logged_in()
    && !empty($_SESSION['arowin_admin_impersonating'])
    && (string) ($_SESSION['arowin_admin_impersonating'] ?? '') === (string) ($user['username'] ?? '')
) {
    $adminImpersonating = true;
    $adminExitUrl = arowin_abs($BASE, '/admin/dashboard/users/exit-impersonation/');
}
?>
<?php if ($adminImpersonating): ?>
      <div class="collab-view-banner" role="status">
        <span>Admin viewing <strong><?= e((string) ($user['username'] ?? '')) ?></strong> dashboard</span>
        <a class="collab-view-back" href="<?= e($adminExitUrl) ?>">← Back to admin users</a>
      </div>
<?php elseif ($collabViewing): ?>
      <div class="collab-view-banner" role="status">
        <span>Viewing member <strong><?= e((string) ($user['username'] ?? '')) ?></strong> dashboard</span>
        <a class="collab-view-back" href="<?= e($collabRestoreUrl) ?>">← Back to my ID<?= $collabHomeUid !== '' ? ' (' . e($collabHomeUid) . ')' : '' ?></a>
      </div>
<?php endif; ?>
<!-- HEADER -->
      <header class="header">
        <button class="hamburger" onclick="toggleSidebar()" type="button" aria-label="Open menu">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <line x1="3" y1="6" x2="21" y2="6" />
            <line x1="3" y1="12" x2="21" y2="12" />
            <line x1="3" y1="18" x2="21" y2="18" />
          </svg>
        </button>
        <div class="header-spacer"></div>
        <div class="header-chips">
          <div class="header-chip header-chip-wallet">
            <svg width="15" height="15" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
              <rect x="2" y="5" width="20" height="14" rx="2" />
              <line x1="2" y1="10" x2="22" y2="10" />
            </svg>
            <div>
              <span class="chip-label">Master Wallet</span><span class="chip-value" id="navWalletValue"><?= e('$' . number_format((float) ($view['wallet'] ?? 0), 2)) ?></span>
            </div>
          </div>
          <div class="header-chip header-chip-rank">
            <svg width="15" height="15" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="12" cy="8" r="6" />
              <path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11" />
            </svg>
            <div>
              <span class="chip-label">Rank</span><span class="chip-value"><?= e($view['rank_label']) ?></span>
            </div>
          </div>
          <div class="header-chip header-chip-status">
            <div class="status-dot<?= empty($view['status_active']) ? ' is-pending' : '' ?>"></div>
            <div>
              <span class="chip-label">Status</span><span class="chip-value"><?= e($view['status_label']) ?></span>
            </div>
          </div>
        </div>
        <button type="button" class="header-avatar" id="headerAvatarBtn" onclick="toggleDropdown(event)" aria-label="Open profile menu">
          <?php if (!empty($photoUrl)): ?>
            <img src="<?= e((string) $photoUrl) ?>" alt="" class="avatar-img" id="headerAvatarImg">
          <?php else: ?>
            <span id="headerAvatarInitials"><?= e($view['initials']) ?></span>
          <?php endif; ?>
        </button>
      </header>

      <!-- PROFILE DROPDOWN -->
      <div class="profile-dropdown" id="profileDropdown" data-account-api="<?= e($accountApiUrl) ?>">
        <div class="dd-user">
          <div class="dd-avatar" id="ddAvatar">
            <?php if (!empty($photoUrl)): ?>
              <img src="<?= e((string) $photoUrl) ?>" alt="" class="avatar-img" id="ddAvatarImg">
            <?php else: ?>
              <span id="ddAvatarInitials"><?= e($view['initials']) ?></span>
            <?php endif; ?>
          </div>
          <div class="dd-info">
            <h4 id="ddHello">Hello, <?= e($view['full_name']) ?> 👋</h4>
            <p>User ID : <?= e((string) $user['username']) ?></p>
            <p>Rank : <?= e($view['rank_label']) ?></p>
          </div>
        </div>

        <a class="dd-item" href="<?= e(url('profile')) ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
            <circle cx="12" cy="7" r="4" />
          </svg>
          Identify Profile
        </a>
        <button type="button" class="dd-item" onclick="openAccountModal('photo')">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z" />
            <circle cx="12" cy="13" r="4" />
          </svg>
          Upload Profile Photo
        </button>
        <button type="button" class="dd-item" onclick="openAccountModal('password')">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="11" width="18" height="11" rx="2" />
            <path d="M7 11V7a5 5 0 0110 0v4" />
          </svg>
          Update Password
        </button>
        <a class="dd-item" href="<?= e(url('operator-configuration')) ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>
          Operator Configuration
        </a>
        <button type="button" class="dd-item" onclick="openAccountModal('history')">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
          </svg>
          Last Login History
        </button>
        <div class="dd-divider"></div>
        <a class="dd-signout" href="<?= e(url('logout')) ?>">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
          </svg>
          Sign Out
        </a>
      </div>

      <!-- Account modals -->
      <div class="acct-overlay hidden" id="acctOverlay" onclick="closeAccountModal(event)">
        <div class="acct-sheet" onclick="event.stopPropagation()" role="dialog" aria-modal="true">
          <button type="button" class="acct-close" onclick="closeAccountModal()" aria-label="Close">&times;</button>

          <!-- Photo -->
          <div class="acct-panel" id="acctPanelPhoto" hidden>
            <h3 class="acct-title">Upload Profile Photo</h3>
            <p class="acct-sub">JPG, PNG, WEBP or GIF — max 2 MB</p>
            <form id="acctPhotoForm" class="acct-form" enctype="multipart/form-data">
              <div class="acct-photo-preview" id="acctPhotoPreview">
                <?php if (!empty($photoUrl)): ?>
                  <img src="<?= e((string) $photoUrl) ?>" alt="Current photo">
                <?php else: ?>
                  <span><?= e($view['initials']) ?></span>
                <?php endif; ?>
              </div>
              <label class="acct-file-btn">
                Choose photo
                <input type="file" name="photo" id="acctPhotoInput" accept="image/jpeg,image/png,image/webp,image/gif" required>
              </label>
              <p class="acct-msg" id="acctPhotoMsg" hidden></p>
              <div class="acct-actions">
                <button type="button" class="acct-btn secondary" onclick="closeAccountModal()">Cancel</button>
                <button type="submit" class="acct-btn primary" id="acctPhotoSubmit">Upload</button>
              </div>
            </form>
          </div>

          <!-- Password -->
          <div class="acct-panel" id="acctPanelPassword" hidden>
            <h3 class="acct-title">Update Password</h3>
            <p class="acct-sub">Use at least 6 characters</p>
            <form id="acctPasswordForm" class="acct-form">
              <label class="acct-label" for="acctCurrentPw">Current password</label>
              <input class="acct-input" type="password" id="acctCurrentPw" name="current_password" required autocomplete="current-password">
              <label class="acct-label" for="acctNewPw">New password</label>
              <input class="acct-input" type="password" id="acctNewPw" name="new_password" required minlength="6" autocomplete="new-password">
              <label class="acct-label" for="acctConfirmPw">Confirm new password</label>
              <input class="acct-input" type="password" id="acctConfirmPw" name="confirm_password" required minlength="6" autocomplete="new-password">
              <p class="acct-msg" id="acctPasswordMsg" hidden></p>
              <div class="acct-actions">
                <button type="button" class="acct-btn secondary" onclick="closeAccountModal()">Cancel</button>
                <button type="submit" class="acct-btn primary" id="acctPasswordSubmit">Save password</button>
              </div>
            </form>
          </div>

          <!-- Login history -->
          <div class="acct-panel" id="acctPanelHistory" hidden>
            <h3 class="acct-title">Last Login History</h3>
            <p class="acct-sub">Latest 10 sign-ins for <strong id="acctHistoryUser">this ID</strong> · India time (IST)</p>
            <div class="acct-history" id="acctHistoryList">
              <p class="acct-history-empty">Loading…</p>
            </div>
            <div class="acct-actions">
              <button type="button" class="acct-btn secondary" onclick="closeAccountModal()" style="grid-column:1/-1">Close</button>
            </div>
          </div>
        </div>
      </div>
