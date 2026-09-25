<?php
/**
 * Admin layout open
 *
 * Expected vars:
 * - string $ADMIN_PAGE
 * - string $ADMIN_TITLE
 * - string|null $ADMIN_SUBTITLE
 */
declare(strict_types=1);

$ADMIN_TITLE = (string) ($ADMIN_TITLE ?? 'Admin');
$ADMIN_SUBTITLE = (string) ($ADMIN_SUBTITLE ?? ('Signed in as ' . arowin_admin_username()));
$adminUser = arowin_admin_username();
if (function_exists('arowin_perf_header')) {
    arowin_perf_header();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= e($ADMIN_TITLE) ?> — Admin</title>
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="theme-color" content="#090909">
  <link rel="icon" href="<?= e(arowin_abs($BASE, '/favicon.ico')) ?>" sizes="any">
  <link rel="icon" type="image/svg+xml" href="<?= e(img('favicon.svg')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <?php
    $adminCss = admin_asset('css/admin.css');
    $adminFonts = 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap';
  ?>
  <link rel="preload" href="<?= e($adminCss) ?>" as="style">
  <link rel="stylesheet" href="<?= e($adminCss) ?>">
  <link rel="preload" as="style" href="<?= e($adminFonts) ?>" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="<?= e($adminFonts) ?>"></noscript>
</head>
<body class="admin-app<?= !empty($ADMIN_BODY_CLASS) ? ' ' . e((string) $ADMIN_BODY_CLASS) : '' ?>">
  <?php if (function_exists('arowin_flush_response')) { arowin_flush_response(); } ?>
  <div class="admin-shell">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="admin-main">
      <header class="admin-topbar">
        <div class="admin-topbar-left">
          <button type="button" class="admin-menu-btn" id="adminMenuBtn" aria-label="Open menu" aria-controls="adminSidebar" aria-expanded="false">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
          </button>
          <div>
            <h1><?= e($ADMIN_TITLE) ?></h1>
            <p class="admin-topbar-sub"><?= e($ADMIN_SUBTITLE) ?></p>
          </div>
        </div>
        <div class="admin-topbar-right">
          <div class="admin-profile-wrapper" style="position: relative;">
            <button type="button" class="admin-profile-toggle" id="adminProfileToggle" title="Profile menu">
              <span class="live" aria-hidden="true"></span>
              <span class="label-text">Online ·</span>
              <?= e($adminUser) ?>
              <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" style="margin-left: 6px;"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="admin-profile-dropdown hidden" id="adminProfileDropdown">
              <a href="<?= e(admin_url('/dashboard/change-password')) ?>">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                Update Password
              </a>
              <div class="admin-dropdown-divider"></div>
              <a class="admin-logout-link" href="<?= e(admin_url('/logout')) ?>">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
              </a>
            </div>
          </div>
        </div>
      </header>
      <main class="admin-content">
<script>
document.getElementById('adminProfileToggle')?.addEventListener('click', function(e) {
  e.stopPropagation();
  const dropdown = document.getElementById('adminProfileDropdown');
  if (dropdown) dropdown.classList.toggle('hidden');
});

document.addEventListener('click', function() {
  const dropdown = document.getElementById('adminProfileDropdown');
  if (dropdown) dropdown.classList.add('hidden');
});
</script>
