<?php
/**
 * Admin sidebar
 *
 * @var string $ADMIN_PAGE
 */
declare(strict_types=1);

$adminNav = require __DIR__ . '/nav.php';
$icons = [
    'grid' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>',
    'package' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>',
    'users' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
    'settings' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>',
    'lock' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>',
    'support' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>',
    'ppt' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h12l4 4v12H4z"/><path d="M14 4v4h4M8 13h8M8 17h5"/></svg>',
    'guest' => '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87"/></svg>',
];
$brand = (string) ($APP['short_name'] ?? 'AROWIN');
$adminUser = arowin_admin_username();
$adminInitial = strtoupper(substr($adminUser !== '' ? $adminUser : 'A', 0, 1));
?>
<aside class="admin-sidebar" id="adminSidebar" aria-label="Admin navigation">
  <div class="admin-sidebar-brand">
    <a class="admin-sidebar-brand-link" href="<?= e(admin_url('/dashboard')) ?>">
      <span><?= e($brand) ?></span>
      <span class="dot" aria-hidden="true"></span>
    </a>
    <button type="button" class="admin-sidebar-close" id="adminSidebarClose" aria-label="Close menu">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>

  <p class="admin-sidebar-label">Control desk</p>
  <nav class="admin-sidebar-nav">
    <?php
      $supportWaiting = 0;
      $now = time();
      $badgeCache = $_SESSION['arowin_admin_support_badge'] ?? null;
      if (is_array($badgeCache) && (int) ($badgeCache['exp'] ?? 0) > $now) {
          $supportWaiting = (int) ($badgeCache['n'] ?? 0);
      } else {
          $supportLib = dirname(__DIR__, 2) . '/dashboard/support/includes/support.php';
          if (is_file($supportLib)) {
              require_once $supportLib;
              try {
                  $supportWaiting = (int) (arowin_support_admin_counts()['waiting'] ?? 0);
              } catch (Throwable $e) {
                  $supportWaiting = 0;
              }
          }
          $_SESSION['arowin_admin_support_badge'] = [
              'n' => $supportWaiting,
              'exp' => $now + 20,
          ];
      }
    ?>
    <?php foreach ($adminNav as $item): ?>
      <?php
        $id = (string) $item['id'];
        $active = ($ADMIN_PAGE ?? '') === $id ? ' active' : '';
        $icon = $icons[$item['icon']] ?? $icons['grid'];
      ?>
      <a class="admin-nav-item<?= $active ?>" href="<?= e((string) $item['href']) ?>">
        <?= $icon ?>
        <span><?= e((string) $item['label']) ?></span>
        <?php if ($id === 'support' && $supportWaiting > 0): ?>
          <span class="admin-nav-badge" id="asNavBadge"><?= (int) $supportWaiting ?></span>
        <?php elseif ($id === 'support'): ?>
          <span class="admin-nav-badge" id="asNavBadge" hidden></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="admin-sidebar-foot">
    <div class="admin-user-chip">
      <span class="admin-user-avatar" aria-hidden="true"><?= e($adminInitial) ?></span>
      <span class="meta">
        <span class="name"><?= e($adminUser) ?></span>
        <span class="role">Administrator</span>
      </span>
    </div>
    <a class="admin-logout-btn" href="<?= e(admin_url('/logout')) ?>">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Logout
    </a>
  </div>
</aside>
<div class="admin-sidebar-overlay" id="adminSidebarOverlay"></div>
