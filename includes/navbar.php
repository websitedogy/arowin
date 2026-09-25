<?php
declare(strict_types=1);
/** @var array $NAV */
/** @var string $CURRENT_ROUTE */
$primary = $NAV['primary'] ?? [];
$sidebarExtra = $NAV['sidebar_extra'] ?? [];
?>
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>
<aside class="sidebar" id="sidebar" aria-label="Site menu" aria-hidden="true">
  <div class="sidebar-head">
    <a class="brand" href="<?= e(url('home')) ?>" aria-label="<?= e($APP['name']) ?> home">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2.5L21.5 12L12 21.5L2.5 12L12 2.5Z" fill="#ff8c1a"/>
          <path d="M12 7L17 12L12 17L7 12L12 7Z" fill="#000"/>
        </svg>
      </span>
      <span class="brand-text">
        <span class="brand-name"><?= e($APP['short_name']) ?></span>
        <span class="brand-tag">Trading Portal</span>
      </span>
    </a>
    <button class="sidebar-close" type="button" id="sidebarClose" aria-label="Close menu">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
        <path d="M6 6l12 12M18 6L6 18"/>
      </svg>
    </button>
  </div>
  <nav class="sidebar-nav" aria-label="Sidebar">
    <?php
    $sidebarHome = $NAV['sidebar_home'] ?? null;
    $sidebarItems = array_merge(
        $sidebarHome ? [$sidebarHome] : [],
        $primary,
        $sidebarExtra
    );
    foreach ($sidebarItems as $item):
      $isHome = ($item['icon'] ?? '') === 'home';
    ?>
      <a href="<?= e(url($item['route'])) ?>"<?= (($CURRENT_ROUTE ?? '') === $item['route']) ? ' aria-current="page"' : '' ?>>
        <?php if ($isHome): ?>
          <span class="nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 10.5L12 3l9 7.5"/>
              <path d="M5 9.5V20h14V9.5"/>
              <path d="M9 20v-6h6v6"/>
            </svg>
          </span>
        <?php else: ?>
          <span class="nav-dot" aria-hidden="true"></span>
        <?php endif; ?>
        <?= e($item['label']) ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <a class="btn btn-primary btn-pill" href="<?= e(url('register')) ?>">REGISTER NODE</a>
    <a class="btn btn-outline" href="<?= e(url('sign-in')) ?>">Sign In</a>
  </div>
</aside>

<header class="site-header">
  <div class="container nav-bar">
    <div class="nav-left">
      <button class="menu-btn" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="sidebar" id="menuToggle">
        <span></span><span></span><span></span>
      </button>
      <a class="brand" href="<?= e(url('home')) ?>" aria-label="<?= e($APP['name']) ?> home">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2.5L21.5 12L12 21.5L2.5 12L12 2.5Z" fill="#ff8c1a"/>
            <path d="M12 7L17 12L12 17L7 12L12 7Z" fill="#000"/>
          </svg>
        </span>
        <span class="brand-text">
          <span class="brand-name"><?= e($APP['short_name']) ?></span>
          <span class="brand-tag">Trading Portal</span>
        </span>
      </a>
    </div>

    <div class="nav-right">
      <nav class="nav-links" aria-label="Primary">
        <a
          class="nav-home"
          href="<?= e(url('home')) ?>"
          aria-label="Home"
          title="Home"
          <?= (($CURRENT_ROUTE ?? '') === 'home') ? ' aria-current="page"' : '' ?>
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 10.5L12 3l9 7.5"/>
            <path d="M5 9.5V20h14V9.5"/>
            <path d="M9 20v-6h6v6"/>
          </svg>
        </a>
        <?php foreach ($primary as $item): ?>
          <a href="<?= e(url($item['route'])) ?>"<?= (($CURRENT_ROUTE ?? '') === $item['route']) ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
        <?php endforeach; ?>
      </nav>

      <div class="nav-actions">
        <span class="nav-divider" aria-hidden="true"></span>
        <a class="signin" href="<?= e(url('sign-in')) ?>">Sign In</a>
        <a class="btn btn-primary btn-pill" href="<?= e(url('register')) ?>">REGISTER NODE</a>
      </div>
    </div>
  </div>
</header>
