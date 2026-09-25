<?php
/**
 * Identify Profile — operator identity card + editable details
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/genealogy-helper.php';
require_once dirname(__DIR__) . '/includes/team-repository.php';
require_once dirname(__DIR__) . '/includes/ranks-repository.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'profile';

arowin_prepare_page([
    'title'       => 'Identify Profile — Arowin Trading Portal',
    'title_short' => 'Identify Profile',
    'description' => 'View and update your Arowin operator identity profile.',
    'canonical'   => '/dashboard/profile',
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

// Refresh full profile fields
$profile = arowin_get_operator_profile((string) $user['username']) ?? $user;
$user = array_merge($user, $profile);

$view = arowin_dashboard_view_data($user);
$rank = arowin_rank_progress((string) $user['username']);
$view['rank_label'] = $rank['rank_label'];

$flashOk = null;
$flashErr = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save_profile') {
    $flashErr = 'Identity details can only be changed by an administrator.';
    }
}

$photoUrl = $view['profile_photo'] ?? null;
$joined = '';
if (!empty($user['created_at'])) {
    try {
        $joined = function_exists('arowin_format_india_time')
            ? arowin_format_india_time((string) $user['created_at'], 'd M Y, H:i')
            : ((new DateTimeImmutable((string) $user['created_at'], new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('Asia/Kolkata'))
                ->format('d M Y, H:i') . ' IST');
    } catch (Throwable $e) {
        $joined = (string) $user['created_at'];
    }
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

$leg = (string) ($user['preferred_leg'] ?? '');
$status = strtolower((string) ($user['status'] ?? 'pending'));
$statusLabel = $status === 'active' ? 'Active' : 'Pending';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= e($SEO['title']) ?></title>
    <meta name="robots" content="<?= e($SEO['robots']) ?>">
    <link rel="canonical" href="<?= e($CANONICAL) ?>">
    <link rel="icon" href="<?= e(arowin_abs($BASE, '/favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/svg+xml" href="<?= e(img('favicon.svg')) ?>">
    <?php require dirname(__DIR__) . '/includes/pwa-meta.php'; ?>
    <link
      href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="<?= e($cssHref) ?>" />
  </head>
  <body>
    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="main" id="main">
      <?php require __DIR__ . '/navbar.php'; ?>

      <div class="content">
        <div class="id-head">
          <div>
            <p class="id-eyebrow">Operator identity</p>
            <h1 class="id-title">Identify Profile</h1>
            <p class="id-sub">Your node credentials, contact details, and network position.</p>
          </div>
          <div class="id-head-actions">
            <button type="button" class="id-btn" onclick="openAccountModal('photo')">Upload photo</button>
            <button type="button" class="id-btn" onclick="openAccountModal('password')">Update password</button>
          </div>
        </div>

        <?php if ($flashOk): ?>
          <div class="id-flash is-ok" role="status"><?= e($flashOk) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
          <div class="id-flash is-err" role="alert"><?= e($flashErr) ?></div>
        <?php endif; ?>

        <div class="id-layout">
          <section class="id-card">
            <div class="id-hero">
              <div class="id-avatar" id="profilePageAvatar">
                <?php if (!empty($photoUrl)): ?>
                  <img src="<?= e((string) $photoUrl) ?>" alt="" class="avatar-img" id="profilePageAvatarImg">
                <?php else: ?>
                  <span><?= e($view['initials']) ?></span>
                <?php endif; ?>
              </div>
              <div>
                <h2 class="id-name"><?= e($view['full_name']) ?></h2>
                <p class="id-uid"><?= e((string) $user['username']) ?></p>
                <div class="id-chips">
                  <span class="id-chip"><?= e($view['rank_label']) ?></span>
                  <span class="id-chip<?= $status === 'active' ? ' ok' : ' warn' ?>"><?= e($statusLabel) ?></span>
                  <span class="id-chip"><?= e(arowin_package_amount_label((string) ($user['purchased_package'] ?? $user['node_package'] ?? ''))) ?> purchased</span>
                  <span class="id-chip">Unit <?= e(arowin_package_amount_label((string) ($user['node_package'] ?? ''))) ?></span>
                </div>
              </div>
            </div>

            <dl class="id-facts">
              <div>
                <dt>Sponsor ID</dt>
                <dd><?= e((string) ($user['sponsor_id'] ?? '—') ?: '—') ?></dd>
              </div>
              <div>
                <dt>Preferred leg</dt>
                <dd><?= e($leg !== '' ? ucfirst($leg) : '—') ?></dd>
              </div>
              <div>
                <dt>Left team</dt>
                <dd><a href="<?= e(url('left-team')) ?>"><?= (int) $rank['left'] ?> starters</a></dd>
              </div>
              <div>
                <dt>Right team</dt>
                <dd><a href="<?= e(url('right-team')) ?>"><?= (int) $rank['right'] ?> starters</a></dd>
              </div>
              <div>
                <dt>Matched pairs</dt>
                <dd><?= (int) $rank['pairs'] ?></dd>
              </div>
              <div>
                <dt>Joined</dt>
                <dd><?= e($joined !== '' ? $joined : '—') ?></dd>
              </div>
              <div>
                <dt>Email</dt>
                <dd><?= e((string) ($user['email'] ?? '') !== '' ? (string) $user['email'] : '—') ?></dd>
              </div>
              <div>
                <dt>Mobile</dt>
                <dd><?= e((string) ($user['phone'] ?? '') !== '' ? (string) $user['phone'] : '—') ?></dd>
              </div>
              <div>
                <dt>Country</dt>
                <dd><?= e((string) ($user['country'] ?? '') !== '' ? (string) $user['country'] : '—') ?></dd>
              </div>
              <div>
                <dt>USDT wallet</dt>
                <dd class="mono"><?= e((string) ($user['usdt_wallet'] ?? '') !== '' ? (string) $user['usdt_wallet'] : '—') ?></dd>
              </div>
            </dl>

            <div class="id-links">
              <a href="<?= e(url('packages')) ?>">Packages</a>
              <a href="<?= e(url('genealogy')) ?>">Genealogy Tree</a>
              <button type="button" onclick="openAccountModal('history')">Login history</button>
            </div>
          </section>

          <section class="id-edit">
            <h2>Identity details</h2>
            <p class="id-edit-sub">These details are read-only. Contact an administrator for corrections.</p>
            <dl class="id-facts">
              <div><dt>Full name</dt><dd><?= e((string) ($user['full_name'] ?? '—')) ?></dd></div>
              <div><dt>Email</dt><dd><?= e((string) ($user['email'] ?? '—')) ?></dd></div>
              <div><dt>Mobile</dt><dd><?= e((string) ($user['phone'] ?? '—')) ?></dd></div>
              <div><dt>Country</dt><dd><?= e((string) ($user['country'] ?? '—')) ?></dd></div>
              <div><dt>USDT wallet</dt><dd class="mono"><?= e((string) ($user['usdt_wallet'] ?? '—')) ?></dd></div>
              <div><dt>User ID</dt><dd class="mono"><?= e((string) $user['username']) ?></dd></div>
            </dl>
          </section>
        </div>
      </div>
    </div>

    <?php require __DIR__ . '/includes/bottom-nav.php'; ?>
    <script src="<?= e($jsHref) ?>"></script>
    <script>
      (function () {
        const original = window.applyAvatarUrl;
        if (typeof original !== 'function') return;
        window.applyAvatarUrl = function (url) {
          original(url);
          const box = document.getElementById('profilePageAvatar');
          if (!box || !url) return;
          let img = document.getElementById('profilePageAvatarImg');
          if (!img) {
            box.innerHTML = '';
            img = document.createElement('img');
            img.id = 'profilePageAvatarImg';
            img.className = 'avatar-img';
            img.alt = '';
            box.appendChild(img);
          }
          img.src = url + (url.includes('?') ? '&' : '?') + 't=' + Date.now();
        };
      })();
    </script>
    <?php require dirname(__DIR__) . '/includes/pwa-register.php'; ?>
  </body>
</html>
