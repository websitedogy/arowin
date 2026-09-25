<?php
/**
 * Guest List — history first, add form in modal
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/guests.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'guest_list';

arowin_prepare_page([
    'title'       => 'Guest List — Arowin Trading Portal',
    'title_short' => 'Guest List',
    'description' => 'Add friends to your guest list — name, contact, profession, and location.',
    'canonical'   => '/dashboard/guest-list',
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
$csrf = arowin_csrf_token();
$flashOk = null;
$flashErr = null;
$openModal = false;
$openJoinModal = false;
$joinSuccess = null;
$joinForm = [
    'guest_id' => 0,
    'full_name' => '',
    'mobile' => '',
    'email' => '',
    'placement' => 'left',
];
$tab = strtolower(trim((string) ($_GET['tab'] ?? 'history')));
if ($tab === 'all') {
    $tab = 'history';
}
if (!in_array($tab, ['history', 'joined', 'dropped'], true)) {
    $tab = 'history';
}

$pageBase = arowin_abs($BASE, '/dashboard/guest-list/');
$asParam = arowin_normalize_sponsor_id((string) ($_GET['as'] ?? $_SESSION['arowin_uid'] ?? ''));
$redirectTab = static function (string $tab, bool $openModal = false) use ($pageBase, $asParam): void {
    if ($tab === 'all') {
        $tab = 'history';
    }
    $q = ['tab' => $tab];
    if ($asParam !== '') {
        $q['as'] = $asParam;
    }
    if ($openModal) {
        $q['add'] = '1';
    }
    $url = $pageBase . '?' . http_build_query($q);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $url, true, 303);
    exit;
};

if (!empty($_SESSION['arowin_guest_flash_ok'])) {
    $flashOk = (string) $_SESSION['arowin_guest_flash_ok'];
    unset($_SESSION['arowin_guest_flash_ok']);
}
if (!empty($_SESSION['arowin_guest_flash_err'])) {
    $flashErr = (string) $_SESSION['arowin_guest_flash_err'];
    unset($_SESSION['arowin_guest_flash_err']);
}
if (!empty($_SESSION['arowin_guest_open_modal'])) {
    $openModal = true;
    unset($_SESSION['arowin_guest_open_modal']);
}
if (!empty($_SESSION['arowin_guest_open_join'])) {
    $openJoinModal = true;
    unset($_SESSION['arowin_guest_open_join']);
}
if (!empty($_SESSION['arowin_guest_join_success']) && is_array($_SESSION['arowin_guest_join_success'])) {
    $joinSuccess = $_SESSION['arowin_guest_join_success'];
    unset($_SESSION['arowin_guest_join_success']);
}
if (!empty($_SESSION['arowin_guest_join_form']) && is_array($_SESSION['arowin_guest_join_form'])) {
    $joinForm = array_merge($joinForm, $_SESSION['arowin_guest_join_form']);
    unset($_SESSION['arowin_guest_join_form']);
    $openJoinModal = true;
}

$form = [
    'full_name' => '',
    'phone' => '',
    'profession' => '',
    'relation_type' => '',
    'state' => '',
    'district' => '',
];
if (!empty($_SESSION['arowin_guest_form']) && is_array($_SESSION['arowin_guest_form'])) {
    $form = array_merge($form, $_SESSION['arowin_guest_form']);
    unset($_SESSION['arowin_guest_form']);
    $openModal = true;
}
if (isset($_GET['add']) && (string) $_GET['add'] === '1') {
    $openModal = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
        $_SESSION['arowin_guest_flash_err'] = 'Session expired. Refresh and try again.';
        $redirectTab($tab);
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'add_guest') {
        $form = [
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'profession' => trim((string) ($_POST['profession'] ?? '')),
            'relation_type' => trim((string) ($_POST['relation_type'] ?? '')),
            'state' => trim((string) ($_POST['state'] ?? '')),
            'district' => trim((string) ($_POST['district'] ?? '')),
        ];
        $result = arowin_guest_add((string) $user['username'], $form);
        if (!empty($result['ok'])) {
            $_SESSION['arowin_guest_flash_ok'] = (string) $result['message'];
            $redirectTab($tab);
        }
        $_SESSION['arowin_guest_flash_err'] = (string) ($result['message'] ?? 'Could not add friend.');
        $_SESSION['arowin_guest_form'] = $form;
        $_SESSION['arowin_guest_open_modal'] = 1;
        $redirectTab($tab, true);
    }

    if ($action === 'register_guest') {
        $guestId = (int) ($_POST['guest_id'] ?? 0);
        $joinForm = [
            'guest_id' => $guestId,
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'mobile' => trim((string) ($_POST['mobile'] ?? '')),
            'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'placement' => trim((string) ($_POST['placement'] ?? 'left')),
        ];
        $password = (string) ($_POST['password'] ?? '');
        $confirmPw = (string) ($_POST['confirm_password'] ?? '');
        $terms = isset($_POST['terms']);
        $errors = [];

        $guest = arowin_guest_get((string) $user['username'], $guestId);
        if ($guest === null) {
            $errors[] = 'Guest not found.';
        }
        if (!in_array($joinForm['placement'], ['left', 'right'], true)) {
            $errors[] = 'Select Left or Right placement.';
        }
        if ($joinForm['full_name'] === '' || mb_strlen($joinForm['full_name']) < 3) {
            $errors[] = 'Full name must be at least 3 characters.';
        }
        if (!preg_match('/^[6-9]\d{9}$/', $joinForm['mobile'])) {
            $errors[] = 'Enter a valid 10-digit mobile number.';
        }
        if ($joinForm['email'] === '' || !filter_var($joinForm['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($password === '' || mb_strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirmPw) {
            $errors[] = 'Passwords do not match.';
        }
        if (!$terms) {
            $errors[] = 'Agree to Terms & Conditions.';
        }

        if ($errors === []) {
            try {
                if (!function_exists('arowin_create_registered_user')) {
                    require_once dirname(__DIR__, 2) . '/includes/register-repository.php';
                }
                arowin_ensure_shared_contact_schema();
                $sponsorId = (string) $user['username'];
                $newUid = arowin_generate_user_id();
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $newUser = arowin_create_registered_user([
                    'user_id' => $newUid,
                    'sponsor_id' => $sponsorId,
                    'placement' => $joinForm['placement'],
                    'full_name' => $joinForm['full_name'],
                    'mobile' => $joinForm['mobile'],
                    'email' => $joinForm['email'],
                    'password' => $hashed,
                ]);
                if ($newUser) {
                    arowin_place_in_binary_tree($sponsorId, $joinForm['placement'], $newUid);
                    arowin_guest_set_status($sponsorId, $guestId, 'joined');
                    try {
                        require_once dirname(__DIR__, 2) . '/includes/welcome-login-repository.php';
                        $tokenResult = arowin_issue_welcome_login_token($newUid);
                        if (!empty($tokenResult['ok']) && !empty($tokenResult['url'])) {
                            arowin_send_registration_welcome_email(
                                $joinForm['email'],
                                $joinForm['full_name'],
                                $newUid,
                                $sponsorId,
                                (string) ($user['full_name'] ?? $sponsorId),
                                (string) $tokenResult['url']
                            );
                        }
                    } catch (Throwable $mailEx) {
                        error_log('Guest join welcome mail: ' . $mailEx->getMessage());
                    }
                    $dateLabel = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('d/m/Y h:i A');
                    $_SESSION['arowin_guest_join_success'] = [
                        'user_id' => $newUid,
                        'password' => $password,
                        'sponsor_id' => $sponsorId,
                        'sponsor_name' => (string) ($user['full_name'] ?? $sponsorId),
                        'date' => $dateLabel,
                        'full_name' => $joinForm['full_name'],
                    ];
                    $redirectTab('joined');
                }
                $errors[] = 'Registration failed. Please try again.';
            } catch (Throwable $e) {
                error_log('Guest join register error: ' . $e->getMessage());
                $errors[] = 'Registration failed. Please try again.';
            }
        }

        $_SESSION['arowin_guest_flash_err'] = implode(' ', $errors);
        $_SESSION['arowin_guest_join_form'] = $joinForm;
        $_SESSION['arowin_guest_open_join'] = 1;
        $redirectTab('history');
    }

    if ($action === 'set_status') {
        $newStatus = strtolower(trim((string) ($_POST['status'] ?? '')));
        $guestId = (int) ($_POST['guest_id'] ?? 0);

        $result = arowin_guest_set_status(
            (string) $user['username'],
            $guestId,
            $newStatus
        );
        if (!empty($result['ok'])) {
            $_SESSION['arowin_guest_flash_ok'] = (string) $result['message'];
            $redirectTab($newStatus === 'dropped' ? 'dropped' : 'history');
        }
        $_SESSION['arowin_guest_flash_err'] = (string) ($result['message'] ?? 'Update failed.');
        $redirectTab($tab);
    }

    $redirectTab($tab);
}

$historyGuests = arowin_guest_list_rows((string) $user['username'], 'history');
$droppedGuests = arowin_guest_list_rows((string) $user['username'], 'dropped');
$joiners = arowin_guest_joiners((string) $user['username']);
$historyCount = arowin_guest_list_count((string) $user['username'], 'history');
$droppedCount = arowin_guest_list_count((string) $user['username'], 'dropped');
$joinedCount = count($joiners);
$allCount = $historyCount + $joinedCount + $droppedCount;
$professions = arowin_guest_professions();
$relations = arowin_guest_relations();
$statesMap = arowin_guest_states_districts();
$states = array_keys($statesMap);

$baseCss = arowin_abs($BASE, '/dashboard/assets/css/dashboard.css');
$featCss = arowin_abs($BASE, '/dashboard/guest-list/assets/guest-list.css');
$jsHref  = arowin_abs($BASE, '/dashboard/assets/js/dashboard.js');
$featJs  = arowin_abs($BASE, '/dashboard/guest-list/assets/guest-list.js');
$baseCssFile = dirname(__DIR__, 2) . '/dashboard/assets/css/dashboard.css';
$featCssFile = __DIR__ . '/assets/guest-list.css';
$jsFile = dirname(__DIR__, 2) . '/dashboard/assets/js/dashboard.js';
$featJsFile = __DIR__ . '/assets/guest-list.js';
if (is_file($baseCssFile)) {
    $baseCss .= '?v=' . filemtime($baseCssFile);
}
if (is_file($featCssFile)) {
    $featCss .= '?v=' . filemtime($featCssFile);
}
if (is_file($jsFile)) {
    $jsHref .= '?v=' . filemtime($jsFile);
}
if (is_file($featJsFile)) {
    $featJs .= '?v=' . filemtime($featJsFile);
}

$tabQs = static function (string $tab) use ($asParam): string {
    $q = ['tab' => $tab];
    if ($asParam !== '') {
        $q['as'] = $asParam;
    }
    return '?' . http_build_query($q);
};

$registerBase = function_exists('arowin_join_share_url')
    ? arowin_join_share_url((string) $user['username'])
    : (url('register') . '?sponsor_id=' . rawurlencode((string) $user['username']) . '&for_guest=1');
$loginUrl = url('login');
$supportEmail = (string) ($APP['contact_email'] ?? 'support@arowin.com');
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
    <?php require dirname(__DIR__, 2) . '/includes/pwa-meta.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= e($baseCss) ?>" />
    <link rel="stylesheet" href="<?= e($featCss) ?>" />
  </head>
  <body<?= $openModal ? ' data-gl-open-modal="1"' : '' ?><?= $openJoinModal ? ' data-gl-open-join="1"' : '' ?><?= $joinSuccess ? ' data-gl-join-success="1"' : '' ?>>
    <?php require dirname(__DIR__) . '/sidebar.php'; ?>
    <div class="main" id="main">
      <?php require dirname(__DIR__) . '/navbar.php'; ?>
      <div class="content">
        <div class="gl-head">
          <div>
            <h1 class="gl-title">Guest List</h1>
            <p class="gl-sub">Your saved friends history. Tap + to add a new guest.</p>
          </div>
          <div class="gl-head-actions">
            <div class="gl-stats">
              <span><strong><?= (int) $allCount ?></strong> total</span>
              <span><?= (int) $historyCount ?> history</span>
              <span><?= (int) $joinedCount ?> joined</span>
              <span><?= (int) $droppedCount ?> dropped</span>
            </div>
          </div>
        </div>

        <?php if ($flashOk): ?><div class="gl-flash ok"><?= e($flashOk) ?></div><?php endif; ?>
        <?php if ($flashErr && !$openJoinModal): ?><div class="gl-flash err"><?= e($flashErr) ?></div><?php endif; ?>

        <div class="gl-share">
          <span>Your invite link</span>
          <code id="glInviteLink"><?= e($registerBase) ?></code>
          <button type="button" class="gl-copy" onclick="navigator.clipboard.writeText(document.getElementById('glInviteLink').textContent||'')">Copy</button>
        </div>

        <div class="gl-tabs">
          <a class="gl-tab<?= $tab === 'history' ? ' is-active' : '' ?>" href="<?= e($pageBase . $tabQs('history')) ?>">
            History <em><?= (int) $historyCount ?></em>
          </a>
          <a class="gl-tab<?= $tab === 'joined' ? ' is-active' : '' ?>" href="<?= e($pageBase . $tabQs('joined')) ?>">
            Joined <em><?= (int) $joinedCount ?></em>
          </a>
          <a class="gl-tab<?= $tab === 'dropped' ? ' is-active' : '' ?>" href="<?= e($pageBase . $tabQs('dropped')) ?>">
            Dropped <em><?= (int) $droppedCount ?></em>
          </a>
        </div>

        <?php if ($tab === 'joined'): ?>
          <div class="gl-table-wrap">
            <?php if ($joiners === []): ?>
              <div class="gl-empty">No one has joined under your sponsor ID yet. From History, pick <strong>Join</strong> to open the register form.</div>
            <?php else: ?>
              <table class="gl-table gl-table--joined">
                <thead>
                  <tr>
                    <th>S.No</th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Status</th>
                    <th>Joined</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($joiners as $i => $row): ?>
                    <tr>
                      <td><?= $i + 1 ?></td>
                      <td class="mono"><?= e((string) $row['username']) ?></td>
                      <td><?= e((string) $row['full_name']) ?></td>
                      <td><?= e((string) ($row['phone'] ?: '—')) ?></td>
                      <td><span class="gl-badge is-joined"><?= e(ucfirst((string) ($row['status'] ?: 'pending'))) ?></span></td>
                      <td><?= e(arowin_guest_format_date(isset($row['created_at']) ? (string) $row['created_at'] : null)) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>

        <?php elseif ($tab === 'dropped'): ?>
          <div class="gl-table-wrap">
            <?php if ($droppedGuests === []): ?>
              <div class="gl-empty">No dropped contacts yet.</div>
            <?php else: ?>
              <table class="gl-table">
                <thead>
                  <tr>
                    <th>S.No</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Profession</th>
                    <th>Relation</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($droppedGuests as $i => $g): ?>
                    <?php
                      $locParts = array_filter([
                          trim((string) ($g['district'] ?? '')),
                          trim((string) ($g['state'] ?? '')),
                      ]);
                      $loc = $locParts !== [] ? implode(', ', $locParts) : '—';
                    ?>
                    <tr>
                      <td><?= $i + 1 ?></td>
                      <td><?= e((string) $g['full_name']) ?></td>
                      <td><?= e((string) ($g['phone'] ?: '—')) ?></td>
                      <td><?= e((string) ($g['profession'] ?: '—')) ?></td>
                      <td><?= e((string) ($g['relation_type'] ?: '—')) ?></td>
                      <td><?= e($loc) ?></td>
                      <td><span class="gl-badge is-dropped">Dropped</span></td>
                      <td>
                        <select
                          class="gl-action-select"
                          data-guest-id="<?= (int) $g['id'] ?>"
                          data-guest-name="<?= e((string) $g['full_name']) ?>"
                          data-guest-phone="<?= e((string) ($g['phone'] ?? '')) ?>"
                          onchange="window.arowinGuestAction && window.arowinGuestAction(this)"
                        >
                          <option value="" selected disabled>Action</option>
                          <option value="join">Join</option>
                        </select>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>

        <?php else: ?>
          <div class="gl-table-wrap">
            <?php if ($historyGuests === []): ?>
              <div class="gl-empty">No guests in history yet. Tap the <strong>+</strong> button to add one.</div>
            <?php else: ?>
              <table class="gl-table">
                <thead>
                  <tr>
                    <th>S.No</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Profession</th>
                    <th>Relation</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($historyGuests as $i => $g): ?>
                    <?php
                      $locParts = array_filter([
                          trim((string) ($g['district'] ?? '')),
                          trim((string) ($g['state'] ?? '')),
                      ]);
                      $loc = $locParts !== [] ? implode(', ', $locParts) : '—';
                    ?>
                    <tr>
                      <td><?= $i + 1 ?></td>
                      <td><?= e((string) $g['full_name']) ?></td>
                      <td><?= e((string) ($g['phone'] ?: '—')) ?></td>
                      <td><?= e((string) ($g['profession'] ?: '—')) ?></td>
                      <td><?= e((string) ($g['relation_type'] ?: '—')) ?></td>
                      <td><?= e($loc) ?></td>
                      <td><span class="gl-badge is-<?= e((string) $g['status']) ?>"><?= e(ucfirst((string) $g['status'])) ?></span></td>
                      <td>
                        <form method="post" class="gl-status-form" action="<?= e($pageBase . $tabQs('history')) ?>">
                          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                          <input type="hidden" name="action" value="set_status">
                          <input type="hidden" name="guest_id" value="<?= (int) $g['id'] ?>">
                          <select
                            name="status"
                            class="gl-action-select"
                            data-guest-id="<?= (int) $g['id'] ?>"
                            data-guest-name="<?= e((string) $g['full_name']) ?>"
                            data-guest-phone="<?= e((string) ($g['phone'] ?? '')) ?>"
                            onchange="window.arowinGuestAction && window.arowinGuestAction(this)"
                          >
                            <option value="" selected disabled>Action</option>
                            <option value="join">Join</option>
                            <option value="dropped">Dropped</option>
                          </select>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <button type="button" class="gl-plus-btn" id="glOpenModal" aria-label="Add guest">+</button>

    <div class="gl-overlay<?= $openModal ? '' : ' hidden' ?>" id="glOverlay" aria-hidden="<?= $openModal ? 'false' : 'true' ?>">
      <div class="gl-sheet" role="dialog" aria-modal="true" aria-labelledby="glModalTitle" onclick="event.stopPropagation()">
        <div class="gl-sheet-head">
          <div>
            <h2 id="glModalTitle">Add guest</h2>
            <p>Enter friend details and save to your guest list.</p>
          </div>
          <button type="button" class="gl-sheet-close" id="glCloseModal" aria-label="Close">×</button>
        </div>

        <form class="gl-form" method="post" action="<?= e($pageBase . $tabQs('history')) ?>" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="add_guest">

          <label class="gl-field">
            <span>Name</span>
            <input type="text" name="full_name" required maxlength="120" placeholder="Friend full name" value="<?= e($form['full_name']) ?>">
          </label>

          <label class="gl-field">
            <span>Mobile no.</span>
            <input type="tel" name="phone" required maxlength="10" inputmode="numeric" pattern="[6-9][0-9]{9}" placeholder="10-digit mobile" value="<?= e($form['phone']) ?>">
          </label>

          <div class="gl-grid-2">
            <label class="gl-field">
              <span>Profession</span>
              <select name="profession" required>
                <option value="">Select profession</option>
                <?php foreach ($professions as $p): ?>
                  <option value="<?= e($p) ?>" <?= $form['profession'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="gl-field">
              <span>Relation type</span>
              <select name="relation_type" required>
                <option value="">Select relation</option>
                <?php foreach ($relations as $r): ?>
                  <option value="<?= e($r) ?>" <?= $form['relation_type'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <div class="gl-grid-2">
            <label class="gl-field">
              <span>State</span>
              <select name="state" id="glState" required>
                <option value="">Select state</option>
                <?php foreach ($states as $st): ?>
                  <option value="<?= e($st) ?>" <?= $form['state'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="gl-field">
              <span>District</span>
              <select name="district" id="glDistrict" required data-selected="<?= e($form['district']) ?>">
                <option value=""><?= $form['state'] !== '' ? 'Select district' : 'Select state first' ?></option>
              </select>
            </label>
          </div>

          <div class="gl-form-actions">
            <button type="button" class="gl-cancel-btn" id="glCancelModal">Cancel</button>
            <button type="submit" class="gl-add-btn">Save guest</button>
          </div>
        </form>
      </div>
    </div>

    <div class="gl-overlay<?= $openJoinModal ? '' : ' hidden' ?>" id="glJoinOverlay" aria-hidden="<?= $openJoinModal ? 'false' : 'true' ?>">
      <div class="gl-sheet gl-sheet--join" role="dialog" aria-modal="true" aria-labelledby="glJoinTitle" onclick="event.stopPropagation()">
        <div class="gl-sheet-head">
          <div>
            <h2 id="glJoinTitle">Register guest</h2>
            <p>Fill details and create their Arowin ID under your sponsor.</p>
          </div>
          <button type="button" class="gl-sheet-close" id="glJoinClose" aria-label="Close">×</button>
        </div>

        <form class="gl-form" method="post" action="<?= e($pageBase . $tabQs('history')) ?>" autocomplete="off" id="glJoinForm">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="register_guest">
          <input type="hidden" name="guest_id" id="glJoinGuestId" value="<?= (int) $joinForm['guest_id'] ?>">

          <div class="gl-join-errors<?= ($openJoinModal && $flashErr) ? '' : ' hidden' ?>" id="glJoinErrors" role="alert"<?= ($openJoinModal && $flashErr) ? '' : ' hidden' ?>>
            <?= $openJoinModal && $flashErr ? e((string) $flashErr) : '' ?>
          </div>

          <label class="gl-field">
            <span>Sponsor ID</span>
            <input type="text" value="<?= e((string) $user['username']) ?>" readonly>
          </label>

          <div class="gl-field">
            <span>Binary placement</span>
            <div class="gl-leg-row">
              <label class="gl-leg<?= $joinForm['placement'] === 'left' ? ' is-on' : '' ?>">
                <input type="radio" name="placement" value="left" <?= $joinForm['placement'] === 'left' ? 'checked' : '' ?> required>
                Left
              </label>
              <label class="gl-leg<?= $joinForm['placement'] === 'right' ? ' is-on' : '' ?>">
                <input type="radio" name="placement" value="right" <?= $joinForm['placement'] === 'right' ? 'checked' : '' ?>>
                Right
              </label>
            </div>
          </div>

          <label class="gl-field">
            <span>Full name</span>
            <input type="text" name="full_name" id="glJoinName" required maxlength="120" value="<?= e((string) $joinForm['full_name']) ?>">
          </label>

          <label class="gl-field">
            <span>Mobile</span>
            <input type="tel" name="mobile" id="glJoinMobile" required maxlength="10" inputmode="numeric" pattern="[6-9][0-9]{9}" value="<?= e((string) $joinForm['mobile']) ?>">
          </label>

          <label class="gl-field">
            <span>Email</span>
            <input type="email" name="email" id="glJoinEmail" required value="<?= e((string) $joinForm['email']) ?>" placeholder="guest@email.com">
          </label>

          <div class="gl-grid-2">
            <label class="gl-field">
              <span>Password</span>
              <div class="gl-pass-wrap">
                <input type="password" name="password" id="glJoinPassword" required minlength="6" placeholder="Min 6 characters" autocomplete="new-password">
                <button type="button" class="gl-pass-toggle" data-gl-pass="glJoinPassword" aria-label="Show password">
                  <svg class="icon-show" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg class="icon-hide" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
            </label>
            <label class="gl-field">
              <span>Confirm password</span>
              <div class="gl-pass-wrap">
                <input type="password" name="confirm_password" id="glJoinPassword2" required minlength="6" placeholder="Re-enter" autocomplete="new-password">
                <button type="button" class="gl-pass-toggle" data-gl-pass="glJoinPassword2" aria-label="Show password">
                  <svg class="icon-show" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg class="icon-hide" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
              </div>
            </label>
          </div>
          <p class="gl-pass-hint" id="glPassHint" hidden></p>

          <label class="gl-check">
            <input type="checkbox" name="terms" value="1" required>
            <span>I agree to <a href="<?= e(url('legal-terms')) ?>" target="_blank" rel="noopener">Terms &amp; Conditions</a></span>
          </label>

          <div class="gl-form-actions">
            <button type="button" class="gl-cancel-btn" id="glJoinCancel">Cancel</button>
            <button type="submit" class="gl-add-btn">Create User ID</button>
          </div>
        </form>
      </div>
    </div>

    <?php if (is_array($joinSuccess)): ?>
    <div class="gl-cred-overlay" id="glCredOverlay" role="dialog" aria-modal="true" aria-labelledby="glCredTitle">
      <div
        class="gl-cred-card"
        id="glCredCard"
        onclick="event.stopPropagation()"
        data-user-id="<?= e((string) ($joinSuccess['user_id'] ?? '')) ?>"
        data-password="<?= e((string) ($joinSuccess['password'] ?? '')) ?>"
        data-sponsor-id="<?= e((string) ($joinSuccess['sponsor_id'] ?? '')) ?>"
        data-sponsor-name="<?= e((string) ($joinSuccess['sponsor_name'] ?? '')) ?>"
        data-date="<?= e((string) ($joinSuccess['date'] ?? '')) ?>"
        data-login="<?= e($loginUrl) ?>"
        data-support="<?= e($supportEmail) ?>"
      >
        <h3 id="glCredTitle">Registration successful</h3>
        <p class="gl-cred-sub">Save / share these login details.</p>
        <div class="gl-cred-welcome">
          <strong>Welcome to Arowin company!</strong><br>
          Bright future ahead — let's grow together with Arowin.
        </div>
        <div class="gl-cred-row">
          <div>
            <span>User ID</span>
            <strong><?= e((string) ($joinSuccess['user_id'] ?? '')) ?></strong>
          </div>
        </div>
        <div class="gl-cred-row">
          <div class="gl-cred-pass-block">
            <span>Password</span>
            <div class="gl-cred-pass-line">
              <strong id="glCredPassword" data-pass="<?= e((string) ($joinSuccess['password'] ?? '')) ?>">••••••</strong>
              <button type="button" class="gl-cred-pass-toggle no-capture" id="glCredPassToggle" aria-label="Show password">
                <svg class="icon-show" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon-hide" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>
        </div>
        <div class="gl-cred-row">
          <div>
            <span>Sponsor ID</span>
            <strong><?= e((string) ($joinSuccess['sponsor_id'] ?? '')) ?></strong>
          </div>
        </div>
        <div class="gl-cred-row">
          <div>
            <span>Sponsor name</span>
            <strong><?= e((string) ($joinSuccess['sponsor_name'] ?? '')) ?></strong>
          </div>
        </div>
        <div class="gl-cred-row">
          <div>
            <span>Date</span>
            <strong><?= e((string) ($joinSuccess['date'] ?? '')) ?></strong>
          </div>
        </div>
        <p class="gl-cred-footer">
          Need help? Email us at
          <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a><br>
          Sign in:
          <a class="gl-cred-login" href="<?= e($loginUrl) ?>"><?= e($loginUrl) ?></a>
        </p>
        <div class="gl-cred-actions no-capture">
          <button type="button" class="gl-cred-wa" id="glCredWaBtn" title="Share on WhatsApp" aria-label="Share on WhatsApp">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          </button>
          <button type="button" class="gl-cred-close" id="glCredClose">OK, close</button>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php require dirname(__DIR__) . '/includes/bottom-nav.php'; ?>
    <script src="<?= e($jsHref) ?>"></script>
    <script>
      window.AROWIN_GUEST_DISTRICTS = <?= json_encode($statesMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="<?= e($featJs) ?>"></script>
    <?php require dirname(__DIR__, 2) . '/includes/pwa-register.php'; ?>
  </body>
</html>
