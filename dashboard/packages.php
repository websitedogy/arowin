<?php
/**
 * Packages — buy once from wallet (eligible tiers in green)
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'packages';

arowin_prepare_page([
    'title'       => 'Packages — Arowin Trading Portal',
    'title_short' => 'Packages',
    'description' => 'Buy one lifetime package from your wallet balance.',
    'canonical'   => '/dashboard/packages',
    'robots'      => 'noindex, nofollow',
    'faqs'        => false,
]);

$sessionUid = (string) ($_SESSION['arowin_uid'] ?? '');
if ($sessionUid === '' || !arowin_is_logged_in()) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('login'), true, 303);
    exit;
}

$user = arowin_get_user_by_username($sessionUid);
if (!$user || (int) ($user['id'] ?? 0) < 1) {
    arowin_logout_user();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('login'), true, 303);
    exit;
}

$view = arowin_dashboard_view_data($user);
$packageCatalog = arowin_package_catalog();
$nodePackage = arowin_normalize_package((string) ($user['node_package'] ?? ''));
$purchasedPackage = arowin_normalize_package((string) ($user['purchased_package'] ?? ''));
if ($purchasedPackage === '' && $nodePackage !== '' && $nodePackage !== '50') {
    $purchasedPackage = $nodePackage;
}
$purchasedInfo = arowin_package_info($purchasedPackage);
$lifetimeLocked = arowin_user_has_lifetime_package((string) $user['username']);
$isPowerId = trim((string) ($user['power_owner'] ?? '')) !== '';
$walletBalance = arowin_wallet_balance((string) $user['username']);
$history = arowin_user_purchase_history((string) $user['username'], 8);
$csrf = arowin_csrf_token();

$flashOk = null;
$flashErr = null;
$packagesAction = arowin_abs($BASE, '/dashboard/packages');

if (!empty($_SESSION['arowin_pkg_flash_ok'])) {
    $flashOk = (string) $_SESSION['arowin_pkg_flash_ok'];
    unset($_SESSION['arowin_pkg_flash_ok']);
}
if (!empty($_SESSION['arowin_pkg_flash_err'])) {
    $flashErr = (string) $_SESSION['arowin_pkg_flash_err'];
    unset($_SESSION['arowin_pkg_flash_err']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
        $_SESSION['arowin_pkg_flash_err'] = 'Session expired. Refresh and try again.';
    } elseif ($action === 'buy_with_wallet') {
        $result = arowin_buy_package_with_wallet(
            (string) $user['username'],
            (string) ($_POST['package_code'] ?? '')
        );
        if (!empty($result['ok'])) {
            $_SESSION['arowin_pkg_flash_ok'] = (string) ($result['message'] ?? 'Package purchased successfully.');
        } else {
            $_SESSION['arowin_pkg_flash_err'] = (string) ($result['message'] ?? 'Purchase failed.');
        }
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $packagesAction, true, 303);
    exit;
}

$cssHref = arowin_abs($BASE, '/dashboard/assets/css/dashboard.css');
$jsHref  = arowin_abs($BASE, '/dashboard/assets/js/dashboard.js');
$cssFile = dirname(__DIR__) . '/dashboard/assets/css/dashboard.css';
$jsFile  = dirname(__DIR__) . '/dashboard/assets/js/dashboard.js';
if (is_file($cssFile)) {
    $cssHref .= '?v=' . filemtime($cssFile);
}
if (is_file($jsFile)) {
    $jsHref .= '?v=' . filemtime($jsFile);
}
?>
<!doctype html>
<html lang="<?= e($APP['language'] ?? 'en') ?>">
  <head>
    <?php require dirname(__DIR__) . '/includes/dashboard-seo-head.php'; ?>
    <link rel="preload" href="<?= e($cssHref) ?>" as="style" />
    <link rel="stylesheet" href="<?= e($cssHref) ?>" />
  </head>
  <body>
    <?php arowin_flush_response(); ?>
    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="main" id="main">
      <?php require __DIR__ . '/navbar.php'; ?>

      <div class="content">
        <div class="pkg-head">
          <div>
            <p class="pkg-eyebrow">Packages</p>
            <h1 class="pkg-title">Buy a node package</h1>
            <p class="pkg-sub">Pay from wallet. Lifetime: one package only. Green cards = you can afford them now.</p>
          </div>
        </div>

        <?php if ($flashOk): ?>
          <div class="pkg-flash ok" role="status"><?= e($flashOk) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
          <div class="pkg-flash err" role="alert"><?= e($flashErr) ?></div>
        <?php endif; ?>
        <?php if ($isPowerId): ?>
          <div class="pkg-flash err" role="status">This is a power ID. Package purchases are only allowed on your main ID.</div>
        <?php endif; ?>

        <div class="pkg-summary pkg-summary-packages">
          <div class="pkg-summary-card is-rank" style="--rank-color: var(--accent)">
            <span class="pkg-summary-label">Wallet balance</span>
            <strong class="pkg-summary-value">$<?= e(arowin_fmt_money($walletBalance)) ?></strong>
            <span class="pkg-summary-meta"><a href="<?= e(url('deposit')) ?>">Deposit more</a></span>
          </div>
          <div class="pkg-summary-card">
            <span class="pkg-summary-label">Purchased package</span>
            <strong class="pkg-summary-value"><?= e(arowin_package_amount_label($purchasedPackage)) ?></strong>
            <span class="pkg-summary-meta"><?= $purchasedInfo ? e((string) $purchasedInfo['label']) : 'Lifetime once' ?></span>
          </div>
        </div>

        <?php if ($lifetimeLocked): ?>
          <div class="pkg-pending-box">
            <h2>Lifetime package locked</h2>
            <p>You already purchased <strong><?= e(arowin_package_label($purchasedPackage)) ?></strong>. Another package cannot be bought on this ID.</p>
          </div>
        <?php endif; ?>

        <h2 class="pkg-section-title">All packages</h2>
        <p class="pkg-section-note">
          <?= $lifetimeLocked
            ? 'Your lifetime package is already active.'
            : 'Green = eligible with current wallet balance. Deposit if you need more funds.' ?>
        </p>

        <div class="pkg-nodes pkg-nodes-8">
          <?php foreach ($packageCatalog as $code => $info): ?>
            <?php
              $codeStr = (string) $code;
              $plan = arowin_package_unit_plan($codeStr);
              $amt = (int) $info['amount'];
              $isPurchased = $purchasedPackage === $codeStr;
              $eligible = !$lifetimeLocked && !$isPowerId && $walletBalance >= $amt;
              $splitBlurb = $plan
                  ? ('Self $50 · L ' . $plan['left'] . ' · R ' . $plan['right'])
                  : $info['blurb'];
            ?>
            <div class="pkg-node-card<?= $isPurchased ? ' is-active' : '' ?><?= $eligible ? ' is-eligible' : '' ?>">
              <div class="pkg-node-amt">$<?= e(number_format($amt)) ?></div>
              <div class="pkg-node-name"><?= e($info['label']) ?></div>
              <div class="pkg-node-blurb"><?= e($splitBlurb) ?></div>
              <?php if ($isPurchased): ?>
                <span class="pkg-node-badge">Purchased</span>
              <?php elseif ($eligible): ?>
                <span class="pkg-node-badge eligible">Eligible</span>
              <?php endif; ?>

              <?php if ($eligible): ?>
                <form
                  method="post"
                  action="<?= e($packagesAction) ?>"
                  class="pkg-buy-inline"
                  autocomplete="off"
                  onsubmit="return confirm('Pay $<?= e(number_format($amt)) ?> USDT from Master Wallet for <?= e($info['label']) ?>? Wallet will be cut by $<?= e(number_format($amt)) ?>. Lifetime one-time purchase.');"
                >
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="action" value="buy_with_wallet">
                  <input type="hidden" name="package_code" value="<?= e($codeStr) ?>">
                  <button type="submit" class="pkg-buy-link">Buy</button>
                </form>
              <?php elseif ($isPurchased): ?>
                <span class="pkg-buy-disabled">Lifetime package</span>
              <?php elseif ($lifetimeLocked): ?>
                <span class="pkg-buy-disabled">Locked</span>
              <?php elseif ($isPowerId): ?>
                <span class="pkg-buy-disabled">Main ID only</span>
              <?php else: ?>
                <span class="pkg-buy-disabled">Need $<?= e(number_format($amt - (int) floor($walletBalance))) ?> more</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($history !== []): ?>
          <h2 class="pkg-section-title">Purchase history</h2>
          <div class="pkg-history">
            <?php foreach ($history as $row): ?>
              <div class="pkg-history-row">
                <div>
                  <strong>$<?= e(number_format((int) $row['amount_usdt'])) ?></strong>
                  <span><?= e(arowin_package_label((string) $row['package_code'])) ?></span>
                </div>
                <code><?= e((string) $row['tx_hash']) ?></code>
                <span class="pkg-hist-status <?= e((string) $row['status']) ?>"><?= e(ucfirst((string) $row['status'])) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>
    <script src="<?= e($jsHref) ?>" defer></script>
    <?php require dirname(__DIR__) . '/includes/pwa-register.php'; ?>
  </body>
</html>
