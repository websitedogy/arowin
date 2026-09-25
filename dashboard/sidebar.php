<?php
declare(strict_types=1);
/** @var string $DASHBOARD_PAGE */
?>
<!-- SIDEBAR OVERLAY -->
    <div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-logo">
        <div class="logo-ring"><span>ARW</span></div>
      </div>

      <nav class="sidebar-nav">
        <a class="nav-item<?= ($DASHBOARD_PAGE ?? 'dashboard') === 'dashboard' ? ' active' : '' ?>" href="<?= e(url('dashboard')) ?>" onclick="closeSidebar()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="3" width="7" height="7" rx="1" />
            <rect x="14" y="3" width="7" height="7" rx="1" />
            <rect x="14" y="14" width="7" height="7" rx="1" />
            <rect x="3" y="14" width="7" height="7" rx="1" />
          </svg>
          Dashboard
        </a>
        <a class="nav-item<?= ($DASHBOARD_PAGE ?? '') === 'profile' ? ' active' : '' ?>" href="<?= e(url('profile')) ?>" onclick="closeSidebar()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="4" />
            <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
          </svg>
          Identify Profile
        </a>
        <a class="nav-item<?= ($DASHBOARD_PAGE ?? '') === 'packages' ? ' active' : '' ?>" href="<?= e(url('packages')) ?>" onclick="closeSidebar()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
            <line x1="3" y1="6" x2="21" y2="6" />
            <path d="M16 10a4 4 0 01-8 0" />
          </svg>
          Packages
        </a>
        <a class="nav-item<?= ($DASHBOARD_PAGE ?? '') === 'genealogy_tree' ? ' active' : '' ?>" href="<?= e(url('genealogy')) ?>" onclick="closeSidebar()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="5" r="2" />
            <circle cx="5" cy="19" r="2" />
            <circle cx="19" cy="19" r="2" />
            <path d="M12 7v4M9 17l3-6 3 6M5.5 18l6.5-7 6.5 7" />
          </svg>
          Genealogy Tree
        </a>
        <a class="nav-item<?= ($DASHBOARD_PAGE ?? '') === 'left_team' ? ' active' : '' ?>" href="<?= e(url('left-team')) ?>" onclick="closeSidebar()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
          </svg>
          Left Team
        </a>
        <a class="nav-item<?= ($DASHBOARD_PAGE ?? '') === 'right_team' ? ' active' : '' ?>" href="<?= e(url('right-team')) ?>" onclick="closeSidebar()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
          </svg>
          Right Team
        </a>
        <a class="nav-item<?= ($DASHBOARD_PAGE ?? '') === 'direct_referrals' ? ' active' : '' ?>" href="<?= e(url('direct-referrals')) ?>" onclick="closeSidebar()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
            <polyline points="7 10 12 15 17 10" />
            <line x1="12" y1="15" x2="12" y2="3" />
          </svg>
          Direct Referrals
        </a>
        <?php
          if (!isset($user) || !is_array($user) || empty($user['username'])) {
              $navUid = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? ''));
              if ($navUid !== '' && function_exists('arowin_get_user_by_username')) {
                  $user = arowin_get_user_by_username($navUid) ?: ($user ?? null);
              }
          }
          $showPowerIdsNav = is_array($user ?? null)
              && function_exists('arowin_user_can_view_power_ids')
              && arowin_user_can_view_power_ids($user);
        ?>
        <?php if ($showPowerIdsNav): ?>
        <a class="nav-item<?= ($DASHBOARD_PAGE ?? '') === 'team_collaborations' ? ' active' : '' ?>" href="<?= e(url('team-collaborations')) ?>" onclick="closeSidebar()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
          </svg>
          Team Collaborations
          <?php
            $powerIdCount = 0;
            $powerNavUid = arowin_normalize_sponsor_id((string) ($user['username'] ?? ''));
            if ($powerNavUid !== '' && function_exists('arowin_count_owned_power_ids')) {
                $now = time();
                $cacheKey = 'arowin_power_id_count_' . $powerNavUid;
                $cached = $_SESSION[$cacheKey] ?? null;
                if (is_array($cached) && (int) ($cached['exp'] ?? 0) > $now) {
                    $powerIdCount = (int) ($cached['n'] ?? 0);
                } else {
                    $powerIdCount = arowin_count_owned_power_ids($powerNavUid);
                    $_SESSION[$cacheKey] = ['n' => $powerIdCount, 'exp' => $now + 30];
                }
            }
            if ($powerIdCount > 0):
          ?>
            <span class="nav-badge" aria-label="<?= (int) $powerIdCount ?> Team Collaborations"><?= (int) $powerIdCount ?></span>
          <?php endif; ?>
        </a>
        <?php endif; ?>
        <a class="nav-item<?= ($DASHBOARD_PAGE ?? '') === 'ranks_rewards' ? ' active' : '' ?>" href="<?= e(url('ranks-rewards')) ?>" onclick="closeSidebar()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="6" />
            <path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-3.72 4.35-8.94 5.66-16.88 5.85m19.5 1.9c-3.5-.93-6.63-.82-8.94 0-2.58.92-5.01 2.86-7.44 6.32" />
          </svg>
          Ranks &amp; Achievers
        </a>
        <a class="nav-item<?= ($DASHBOARD_PAGE ?? '') === 'guest_list' ? ' active' : '' ?>" href="<?= e(url('guest-list')) ?>" onclick="closeSidebar()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
          </svg>
          Guest List
        </a>
        <a class="nav-item<?= ($DASHBOARD_PAGE ?? '') === 'ppt_view' ? ' active' : '' ?>" href="<?= e(url('ppt-view')) ?>" onclick="closeSidebar()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="2" y="3" width="20" height="14" rx="2" />
            <path d="M8 21h8M12 17v4" />
          </svg>
          PPT View
        </a>
        <div class="nav-divider"></div>
        <a class="nav-item<?= ($DASHBOARD_PAGE ?? '') === 'support' ? ' active' : '' ?>" href="<?= e(url('support')) ?>" onclick="closeSidebar()">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" />
            <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>
          Support
          <?php
            $supUnread = 0;
            $supUid = '';
            if (!empty($user['username'])) {
                $supUid = (string) $user['username'];
            } elseif (!empty($_SESSION['arowin_uid'])) {
                $supUid = (string) $_SESSION['arowin_uid'];
            }
            if ($supUid !== '') {
                $now = time();
                $cacheKey = 'arowin_member_support_unread_' . strtoupper($supUid);
                $cached = $_SESSION[$cacheKey] ?? null;
                if (is_array($cached) && (int) ($cached['exp'] ?? 0) > $now) {
                    $supUnread = (int) ($cached['n'] ?? 0);
                } else {
                    $lib = __DIR__ . '/support/includes/support.php';
                    if (is_file($lib)) {
                        require_once $lib;
                        if (function_exists('arowin_support_member_unread_count')) {
                            $supUnread = arowin_support_member_unread_count($supUid);
                        }
                    }
                    $_SESSION[$cacheKey] = ['n' => $supUnread, 'exp' => $now + 15];
                }
            }
            if ($supUnread > 0):
          ?>
            <span class="nav-badge" aria-label="<?= (int) $supUnread ?> new replies"><?= (int) $supUnread ?></span>
          <?php endif; ?>
        </a>
      </nav>

      <div class="sidebar-bottom">
        <div class="keep-growing">
          <div class="trophy">🏆</div>
          <h4>Keep Growing!</h4>
          <p>Achieve higher ranks and unlock rewards</p>
          <button class="btn-rewards" type="button" onclick="location.href='<?= e(url('ranks-rewards')) ?>'">View Achievers</button>
        </div>
        <a class="nav-logout" href="<?= e(url('logout')) ?>">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
          </svg>
          Logout
        </a>
      </div>
    </aside>

    