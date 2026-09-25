<?php
/**
 * Arowin — Genealogy Tree (binary)
 */
declare(strict_types=1);

set_time_limit(120);
ini_set('max_execution_time', '120');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/genealogy-helper.php';

requireAuth();

$user = getCurrentUser();
if (!$user) {
    header('Location: ' . bbm_login_url());
    exit;
}

arowin_backfill_binary_tree();

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'genealogy_tree';
$view = arowin_dashboard_view_data([
    'full_name' => $user['full_name'],
    'username'  => $user['user_id'],
    'status'    => ($user['status'] ?? '') === 'active' ? 'active' : 'pending',
    'phone'     => $user['mobile'] ?? '',
    'email'     => $user['email'] ?? '',
    'sponsor_id'=> $user['sponsor_id'] ?? '',
    'preferred_leg' => $user['preferred_leg'] ?? 'auto',
    'node_package' => (string) ($user['node_package'] ?? ''),
]);
// Keep $user for genealogy scripts; also expose mapped $user for navbar
$opProfile = arowin_get_operator_profile((string) $user['user_id']);
$dashUser = [
    'id' => (int) $user['id'],
    'username' => (string) $user['user_id'],
    'full_name' => (string) $user['full_name'],
    'email' => $user['email'] ?? null,
    'phone' => (string) ($user['mobile'] ?? ''),
    'sponsor_id' => (string) ($user['sponsor_id'] ?? ''),
    'preferred_leg' => (string) ($user['preferred_leg'] ?? 'auto'),
    'node_package' => (string) ($user['node_package'] ?? ''),
    'status' => ($user['status'] ?? '') === 'active' ? 'active' : 'pending',
    'usdt_wallet' => (string) ($opProfile['usdt_wallet'] ?? ''),
    'country' => (string) ($opProfile['country'] ?? ''),
    'profile_photo' => $opProfile['profile_photo'] ?? null,
];
$view['profile_photo'] = arowin_profile_photo_url(
    isset($opProfile['profile_photo']) && $opProfile['profile_photo'] !== null
        ? (string) $opProfile['profile_photo']
        : null
);

$cssDash = arowin_abs($BASE, '/dashboard/assets/css/dashboard.css');
$jsDash  = arowin_abs($BASE, '/dashboard/assets/js/dashboard.js');
$cssDashFile = dirname(__DIR__, 2) . '/dashboard/assets/css/dashboard.css';
$jsDashFile  = dirname(__DIR__, 2) . '/dashboard/assets/js/dashboard.js';
if (is_file($cssDashFile)) { $cssDash .= '?v=' . filemtime($cssDashFile); }
if (is_file($jsDashFile)) { $jsDash .= '?v=' . filemtime($jsDashFile); }
$genealogyUrl = url('genealogy');

function genealogy_enrich_tree_referral_names(array &$treeData): void
{
    if ($treeData === []) {
        return;
    }

    $nameById = [];
    foreach ($treeData as $uid => $node) {
        $nameById[(string)$uid] = (string)($node['name'] ?? '');
    }

    foreach ($treeData as &$node) {
        $ref = trim((string)($node['referral'] ?? ''));
        if ($ref === '') {
            $node['referralName'] = '';
            continue;
        }
        if (isset($nameById[$ref])) {
            $node['referralName'] = $nameById[$ref];
                continue;
        }
        $sponsor = getUserByUserId($ref);
        $node['referralName'] = $sponsor ? (string)($sponsor['full_name'] ?? '') : '';
        if ($node['referralName'] !== '') {
            $nameById[$ref] = $node['referralName'];
        }
    }
    unset($node);
}

/**
 * @return array{tree_data: array<string, array<string, mixed>>, team_stats: array<string, array<string, int>>}
 */
function genealogy_refresh_tree_payload(string $search_user_id): array
{
    $search_user_id = trim($search_user_id);
    $tree_user      = getUserByUserId($search_user_id);
    $tree_data      = buildGenealogyTreeData($search_user_id);

    if ($tree_data === [] && $tree_user) {
        $tree_data[(string)$tree_user['user_id']] = [
            'id'         => $tree_user['user_id'],
            'name'       => $tree_user['full_name'],
            'status'     => $tree_user['status'] ?? 'pending',
            'joinDt'     => date('d/m/Y', strtotime($tree_user['created_at'] ?? 'now')),
            'referral'   => $tree_user['sponsor_id'] ?? '',
            'mobile'     => $tree_user['mobile'] ?? '',
                'rank'       => 'Unranked',
                'leftChild'  => null,
                'rightChild' => null,
            ];
        }

    enrichGenealogyTreeWithSelfBv($tree_data);
    enrichGenealogyTreeWithPackageColors($tree_data);
    genealogy_enrich_tree_referral_names($tree_data);

    return [
        'tree_data'  => $tree_data,
        'team_stats' => getBinaryGenealogyTeamStats($search_user_id),
    ];
}

function genealogy_sponsor_leg_for_slot(string $sponsor_id, string $parent_id, string $leg): string
{
    if ($parent_id === $sponsor_id) {
        return $leg === 'right' ? 'right' : 'left';
    }

    $rel = getReferralLegRelativeToSponsor($sponsor_id, $parent_id);

    return $rel === 'right' ? 'right' : 'left';
}

/** Sponsor must be on upline path from tree root to the placement parent (top-leg members). */
function genealogy_is_allowed_tree_sponsor(string $tree_root, string $parent_id, string $sponsor_id, array $visible_tree, string $logged_in_id = ''): bool
{
    $tree_root  = trim($tree_root);
    $parent_id  = trim($parent_id);
    $sponsor_id = trim($sponsor_id);
    $logged_in_id = trim($logged_in_id);

    if ($sponsor_id === '') {
        return false;
    }
    if ($logged_in_id !== '' && $sponsor_id === $logged_in_id) {
        return true;
    }
    if (!isset($visible_tree[$sponsor_id]) || !isset($visible_tree[$parent_id])) {
        return false;
    }

    $current = $parent_id;
    for ($guard = 0; $guard < 64; $guard++) {
        if ($current === $sponsor_id) {
            return true;
        }
        if ($current === $tree_root) {
            return $sponsor_id === $tree_root;
        }

        $parent_node = null;
        foreach ($visible_tree as $uid => $node) {
            $left  = (string)($node['leftChild'] ?? '');
            $right = (string)($node['rightChild'] ?? '');
            if ($left === $current || $right === $current) {
                $parent_node = (string)$uid;
                break;
            }
        }

        if ($parent_node === null || $parent_node === '') {
            break;
        }
        $current = $parent_node;
    }

    return false;
}

if (isset($_GET['check_sponsor'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $sid = trim((string)($_GET['sponsor_id'] ?? ''));

    if ($sid === '') {
        echo json_encode(['status' => 'invalid', 'error' => 'Sponsor ID required'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $sponsor = getUserByUserId($sid);
        if ($sponsor) {
            echo json_encode([
                'status' => 'valid',
                'id'     => (string)$sponsor['user_id'],
                'name'   => (string)($sponsor['full_name'] ?? ''),
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['status' => 'invalid', 'error' => 'Sponsor not found'], JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
        error_log('Genealogy sponsor validation error: ' . $e->getMessage());
        echo json_encode(['status' => 'invalid', 'error' => 'Database error'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'tree_register') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $errors     = [];
    $parent_id  = arowin_normalize_sponsor_id((string) ($_POST['binary_parent_id'] ?? ''));
    $leg        = strtolower(trim((string) ($_POST['binary_leg'] ?? '')));
    $tree_root  = arowin_normalize_sponsor_id((string) ($_POST['tree_root'] ?? ''));
    $full_name  = trim((string) ($_POST['full_name'] ?? ''));
    $mobile     = trim((string) ($_POST['mobile'] ?? ''));
    $email      = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password   = (string) ($_POST['password'] ?? '');
    $confirm_pw = (string) ($_POST['confirm_password'] ?? '');
    $terms      = isset($_POST['terms']);
    $sponsor_id = arowin_normalize_sponsor_id((string) ($_POST['sponsor_id'] ?? ''));
    if ($sponsor_id === '') {
        $sponsor_id = $parent_id;
    }

    if ($tree_root === '') {
        $tree_root = (string) ($user['user_id'] ?? '');
    }
    if ($parent_id === '') {
        $errors[] = 'Invalid tree position — parent is missing.';
    }
    if (!in_array($leg, ['left', 'right'], true)) {
        $errors[] = 'Invalid tree position — choose Left or Right.';
    }
    if ($full_name === '' || mb_strlen($full_name) < 3) {
        $errors[] = 'Full name must be at least 3 characters.';
    }
    if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        $errors[] = 'Enter a valid 10-digit mobile number.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (mb_strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_pw) {
        $errors[] = 'Passwords do not match.';
    }
    if (!$terms) {
        $errors[] = 'You must agree to Terms & Conditions.';
    }

    $visible_tree = [];
    if ($errors === []) {
        $parent_row = getUserByUserId($parent_id);
        if (!$parent_row) {
            $errors[] = 'Parent member not found. This position is invalid.';
        } else {
            $visible_tree = buildGenealogyTreeData($tree_root);
            if (!isset($visible_tree[$parent_id])) {
                $errors[] = 'This position is not available in the current tree view.';
            } else {
                $occupied = getBinaryTreeChild($parent_id, $leg);
                if ($occupied) {
                    $errors[] = 'This position is no longer empty. Please select another slot.';
                }
            }
        }
    }

    if ($errors === []) {
        arowin_ensure_shared_contact_schema();
    }

    if ($errors === []) {
        $sponsor_row = getUserByUserId($sponsor_id);
        if (!$sponsor_row) {
            $errors[] = 'Invalid Sponsor ID.';
        } else {
            $sponsor_id = (string) ($sponsor_row['user_id'] ?? $sponsor_id);
            $loggedIn = (string) ($user['user_id'] ?? '');
            if (!genealogy_is_allowed_tree_sponsor($tree_root, $parent_id, $sponsor_id, $visible_tree, $loggedIn)
                && strcasecmp($sponsor_id, $parent_id) !== 0) {
                $errors[] = 'Sponsor must be the placement parent or an upline member on this path.';
            }
        }
    }

    if ($errors !== []) {
        echo json_encode(['ok' => false, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $new_uid = generateUserId();
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sponsor_leg = genealogy_sponsor_leg_for_slot($sponsor_id, $parent_id, $leg);

        $created = createUser([
            'user_id'    => $new_uid,
            'sponsor_id' => $sponsor_id,
            'placement'  => $sponsor_leg,
            'full_name'  => $full_name,
            'mobile'     => $mobile,
            'email'      => $email,
            'password'   => $hashed,
        ]);

        if (!$created) {
            echo json_encode(['ok' => false, 'errors' => ['Registration failed. Please try again.']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $placed = arowin_set_binary_placement($new_uid, $parent_id, $leg);
        if (!$placed) {
            echo json_encode(['ok' => false, 'errors' => ['Could not place member at the selected position.']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        invalidateBinaryTreeIndex();
        $payload = genealogy_refresh_tree_payload($tree_root);

        echo json_encode([
            'ok'            => true,
            'user_id'       => $new_uid,
            'password'      => $password,
            'sponsor_id'    => $sponsor_id,
            'sponsor_name'  => (string) ($sponsor_row['full_name'] ?? $sponsor_row['name'] ?? ''),
            'registered_at' => date('d/m/Y h:i A'),
            'message'       => 'Member ' . $new_uid . ' registered at ' . $parent_id . ' (' . strtoupper($leg) . ').',
            'tree_data'     => $payload['tree_data'],
            'team_stats'    => $payload['team_stats'],
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('Genealogy tree registration error: ' . $e->getMessage());
        $msg = env('APP_DEBUG', false)
            ? ('Database error: ' . $e->getMessage())
            : 'Database error. Please try again.';
        echo json_encode(['ok' => false, 'errors' => [$msg]], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$search_user_id = arowin_normalize_sponsor_id((string) ($_GET['search'] ?? $user['user_id']));
$tree_user      = getUserByUserId($search_user_id);
$search_error   = '';
$logged_in_id   = arowin_normalize_sponsor_id((string) $user['user_id']);

if (!$tree_user) {
    $search_error   = 'Member ID not found — showing your tree.';
    $tree_user      = $user;
    $search_user_id = $logged_in_id;
} elseif ($search_user_id !== $logged_in_id && !arowin_binary_is_under($logged_in_id, $search_user_id)) {
    $search_error   = 'That ID is not in your binary tree — showing your tree.';
    $tree_user      = $user;
    $search_user_id = $logged_in_id;
}

$tree_data = buildGenealogyTreeData($search_user_id);

// Fallback: at least show root node
if ($tree_data === [] && $tree_user) {
    $tree_data[(string)$tree_user['user_id']] = [
        'id'         => $tree_user['user_id'],
        'name'       => $tree_user['full_name'],
        'status'     => $tree_user['status'] ?? 'pending',
        'joinDt'     => date('d/m/Y', strtotime($tree_user['created_at'] ?? 'now')),
        'referral'   => $tree_user['sponsor_id'] ?? '',
        'mobile'     => $tree_user['mobile'] ?? '',
        'rank'       => 'Unranked',
        'leftChild'  => null,
        'rightChild' => null,
    ];
}

enrichGenealogyTreeWithSelfBv($tree_data);
enrichGenealogyTreeWithPackageColors($tree_data);
genealogy_enrich_tree_referral_names($tree_data);

$team_stats = getBinaryGenealogyTeamStats($search_user_id);

$root_id = (string) $search_user_id;

ensureUsersProfilePhotoColumn();
$profile_photo = getProfilePhotoWebPath($user);

$initials = '';
foreach (explode(' ', (string)$user['full_name']) as $n) {
    $initials .= strtoupper(substr(trim($n), 0, 1));
}
$initials = substr($initials, 0, 2) ?: '?';

$teamLegs             = getDeduplicatedTeamLegCounts((string)$user['user_id']);
$team_count_left      = (int)($teamLegs['left']  ?? 0);
$team_count_right     = (int)($teamLegs['right'] ?? 0);
$genealogy_tree_total = $team_count_left + $team_count_right;
$direct_referral_count = (int)getTeamCount((string)$user['user_id']);

applyMemberHeaderRankVars($user);
$current_nav        = 'genealogy_tree';
$header_back_url    = 'dashboard.php';
$header_back_label  = 'Back to Dashboard';
$header_layout      = 'simple';
$header_photo_redirect = 'genealogy_tree.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Genealogy Tree — Arowin</title>
    <?php require dirname(__DIR__, 2) . '/includes/pwa-meta.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($cssDash) ?>">
    <link rel="icon" href="<?= e(arowin_abs($BASE, '/favicon.ico')) ?>" sizes="any">
    
    
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #0a0a0a; --surface: #141414; --surface-2: #1a1a1a;
            --border: rgba(255,255,255,0.08); --border-md: rgba(255,255,255,0.14);
            --text-1: #f1f1f1; --text-2: #a3a3a3; --text-3: #6b6b6b;
            --blue: #ff8c1a; --blue-bg: rgba(255,140,26,0.14); --blue-text: #ff9f3a;
            --green: #22c55e; --green-bg: rgba(34,197,94,0.14); --green-text: #86efac;
            --red: #ef4444; --red-bg: rgba(239,68,68,0.14); --red-text: #fca5a5;
            --amber: #f59e0b; --amber-bg: rgba(245,158,11,0.14); --amber-text: #fcd34d;
            --radius-sm: 6px; --radius: 12px; --radius-lg: 14px;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.35);
            --shadow: 0 4px 16px rgba(0,0,0,0.45);
            --shadow-lg: 0 12px 32px rgba(0,0,0,0.55);
            --font: "Plus Jakarta Sans", "Segoe UI", sans-serif;
            --mono: "DM Mono", ui-monospace, monospace;
        }
        body { font-family: var(--font); background: #0a0a0a; color: var(--text-1); -webkit-font-smoothing: antialiased; }
        .main .content { background: var(--bg); color: var(--text-1); min-height: calc(100vh - 60px); }
        .main .content, .main .content * { font-family: var(--font); }
        .page-title { color: #fff !important; letter-spacing: -0.02em; }
        .page-sub { color: var(--text-2) !important; }
        .tree-legend { color: var(--text-3) !important; }

        /* ── Compact top toolbar ── */
        .tree-top {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 10px;
        }
        .tree-top-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px 16px;
            flex-wrap: wrap;
        }
        .tree-top-title-block { min-width: 0; flex: 1 1 180px; }
        .tree-top-title-block .page-title {
            font-size: 18px !important;
            font-weight: 800;
            margin: 0 0 2px !important;
            line-height: 1.15;
        }
        .tree-top-title-block .page-sub {
            font-size: 11px !important;
            margin: 0 !important;
            line-height: 1.35;
            color: var(--text-3) !important;
        }
        .tree-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 10px;
            align-items: center;
            font-size: 11px !important;
            margin: 0 !important;
            max-width: 100%;
        }
        .tree-legend span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        .tree-legend i {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* ── Page wrapper ── */
        .tree-page { padding: 0; max-width: 100%; }

        /* ── Alert ── */
        .tree-alert { background: var(--red-bg); border: 1px solid rgba(239,68,68,0.35); color: var(--red-text); padding: 8px 12px; border-radius: var(--radius-sm); font-size: 12px; margin-bottom: 8px; }

        /* ── Search bar (top form) ── */
        .tree-search-bar { display: flex; gap: 6px; margin: 0; flex-wrap: nowrap; align-items: stretch; }
        .tree-search-bar input  { flex: 1; min-width: 0; padding: 8px 10px; border: 1px solid var(--border-md); border-radius: var(--radius-sm); font-family: var(--font); font-size: 12px; outline: none; background: var(--surface); color: var(--text-1); }
        .tree-search-bar input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(255,140,26,.15); }
        .tree-search-bar button { padding: 8px 12px; background: var(--blue); color: #000; border: none; border-radius: var(--radius-sm); font-weight: 700; cursor: pointer; font-family: var(--font); font-size: 12px; white-space: nowrap; flex-shrink: 0; }
        .tree-search-bar button:hover { background: var(--blue-text); }

        /* ── Empty state ── */
        .tree-empty { text-align: center; padding: 48px 16px; color: var(--text-3); font-size: 14px; }

        /* ── Stat header (compact) ── */
        .stat-header { display: grid; grid-template-columns: 1fr auto 1fr; gap: 6px; align-items: stretch; margin-bottom: 8px; }
        .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 8px 10px; box-shadow: none; display: flex; flex-direction: column; gap: 3px; min-width: 0; }
        .stat-card.right-card { text-align: right; }
        .stat-card-label { font-size: 9px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--text-3); }
        .stat-card-total { font-size: 18px; font-weight: 700; color: var(--text-1); line-height: 1; }
        .stat-pills { display: flex; gap: 3px; flex-wrap: wrap; }
        .stat-card.right-card .stat-pills { justify-content: flex-end; }
        .stat-pill { display: inline-flex; align-items: center; gap: 3px; font-size: 9px; font-weight: 600; padding: 1px 6px; border-radius: 999px; }
        .stat-pill.green { background: var(--green-bg); color: var(--green-text); }
        .stat-pill.red   { background: var(--red-bg);   color: var(--red-text); }
        .stat-pill.amber { background: rgba(255, 140, 26, 0.16); color: #ff8c1a; }
        .stat-pill::before { content: ''; width: 4px; height: 4px; border-radius: 50%; background: currentColor; opacity: .7; }
        .stat-pill .pill-full { display: inline; }
        .stat-pill .pill-short { display: none; }

        /* ── Centre search ── */
        .ctrl-area { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; position: relative; }
        .search-btn { width: 32px; height: 32px; border-radius: 50%; border: 1px solid var(--border-md); background: var(--surface); display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: none; transition: box-shadow .15s, background .15s, border-color .15s; flex-shrink: 0; }
        .search-btn:hover { background: var(--surface-2); border-color: var(--blue); box-shadow: var(--shadow-sm); }
        .search-btn svg { width: 14px; height: 14px; stroke: var(--text-2); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .search-flyout { position: absolute; top: 38px; left: 50%; transform: translateX(-50%) translateY(-4px); z-index: 30; width: min(200px, 70vw); opacity: 0; pointer-events: none; transition: opacity .2s, transform .2s; }
        .search-flyout.open { opacity: 1; pointer-events: all; transform: translateX(-50%) translateY(0); }
        .search-flyout input { width: 100%; padding: 7px 10px; font-size: 12px; font-family: var(--font); color: var(--text-1); background: var(--surface); border: 1px solid var(--border-md); border-radius: var(--radius-sm); outline: none; box-shadow: var(--shadow); }
        .search-flyout input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(255,140,26,.15), var(--shadow); }
        .search-result { position: absolute; top: 74px; left: 50%; transform: translateX(-50%); z-index: 30; white-space: nowrap; font-size: 11px; font-family: var(--font); background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 4px 10px; box-shadow: var(--shadow); display: none; }
        .search-result.show   { display: block; }
        .search-result.found  { color: var(--green-text); cursor: pointer; }
        .search-result.notfound { color: var(--red-text); }

        /* ── Breadcrumb ── */
        .breadcrumb { display: flex; align-items: center; gap: 2px; flex-wrap: wrap; margin-bottom: 8px; padding: 0 2px; min-height: 22px; }
        .bc-item { font-size: 12px; font-family: var(--mono); color: var(--blue-text); cursor: pointer; padding: 3px 6px; border-radius: var(--radius-sm); transition: background .12s; }
        .bc-item:hover { background: var(--blue-bg); }
        .bc-sep { font-size: 11px; color: var(--text-3); padding: 0 1px; }
        .bc-cur { font-size: 12px; font-family: var(--mono); color: var(--text-2); padding: 3px 6px; background: var(--surface-2); border-radius: var(--radius-sm); }

        /* ── SVG tree wrapper ── */
        .tree-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch; padding: 8px 0 4px; }
        svg.tv { display: block; min-width: 360px; background: var(--surface); height: auto; }
        @media (max-width: 520px) {
            .tree-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; padding: 4px 0 2px; }
            svg.tv { min-width: 640px; }
        }
        svg.tv .tree-node-id { font-family: var(--mono, 'Courier New', monospace); font-weight: 700; fill: #ffffff; }
        svg.tv .tree-node-name { font-family: var(--font); font-weight: 500; fill: #d4d4d4; }
        svg.tv .tree-node-referral { font-family: var(--font); font-weight: 400; fill: #8a8a8a; }
        svg.tv .tree-label-bg { fill: #141414; stroke: #2a2a2a; stroke-width: 0.9; }
        svg.tv .tree-label-bg.is-root { fill: #1a140e; stroke: rgba(255,140,26,0.5); stroke-width: 1.15; }
        svg.tv .tree-label-bg.is-empty { fill: #121212; stroke: #3a3a3a; stroke-dasharray: 4 3; }
        svg.tv .tree-label-bg.is-empty-hot { fill: rgba(255,140,26,0.06); stroke: rgba(255,140,26,0.45); stroke-dasharray: none; }
        svg.tv .tree-lr-bg { fill: #121212; }
        svg.tv .tree-empty-node:hover .tree-empty-icon { fill: #ff8c1a; }
        svg.tv .tree-empty-node .tree-reg-hint { fill: #ff8c1a; font-weight: 600; }
        svg.tv .tree-empty-node:hover .tree-reg-hint { fill: #ff9f3a; }
        svg.tv .tree-empty-node:hover .tree-label-bg.is-empty-hot { fill: rgba(255,140,26,0.12); stroke: #ff8c1a; }
        svg.tv .tree-connector { stroke: rgba(255,140,26,0.28); }
        svg.tv .tree-avatar-ring { fill: none; stroke: rgba(255,140,26,0.35); }
        svg.tv .tree-avatar-disc { fill: #1a1a1a; }

        /* ── Modal / bottom-sheet ── */
        .modal-overlay { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: flex-end; justify-content: center; background: rgba(0,0,0,.65); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); transition: opacity .2s; }
        .modal-overlay.hidden { display: none; }
        @media (min-width: 520px) { .modal-overlay { align-items: center; } }
        .modal-sheet { background: var(--surface); border: 1px solid var(--border); border-radius: 20px 20px 0 0; padding: 0 0 env(safe-area-inset-bottom); width: 100%; max-width: 440px; box-shadow: var(--shadow-lg); animation: slideUp .22s cubic-bezier(.32,.72,0,1); max-height: 92vh; overflow-y: auto; color: var(--text-1); }
        @media (min-width: 520px) { .modal-sheet { border-radius: var(--radius-lg); animation: popIn .18s cubic-bezier(.34,1.56,.64,1); } }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        @keyframes popIn   { from { transform: scale(.94); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .sheet-handle { width: 36px; height: 4px; background: var(--border-md); border-radius: 2px; margin: 10px auto 0; }
        @media (min-width: 520px) { .sheet-handle { display: none; } }
        .modal-header { display: flex; align-items: center; gap: 12px; padding: 16px 20px 14px; border-bottom: 1px solid var(--border); }
        .modal-avatar { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #000; }
        .modal-avatar svg { display: block; }
        .modal-name { font-size: 15px; font-weight: 600; color: var(--text-1); }
        .modal-id   { font-size: 12px; font-family: var(--mono); color: var(--text-3); margin-top: 2px; }
        .modal-body { padding: 14px 20px; }
        .info-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 9px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
        .info-row:last-of-type { border-bottom: none; }
        .info-key { color: var(--text-2); flex-shrink: 0; }
        .info-val { font-weight: 500; color: var(--text-1); text-align: right; max-width: 62%; word-break: break-word; }
        .info-mono { font-family: var(--mono); font-weight: 600; }
        .status-badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; display: inline-block; }
        .status-badge.a { background: var(--green-bg); color: var(--green-text); }
        .status-badge.i { background: var(--red-bg);   color: var(--red-text); }
        .status-badge.p { background: rgba(255, 140, 26, 0.16); color: #ff8c1a; }
        .team-section-title { font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--text-3); margin: 14px 0 8px; }
        .team-stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 14px; }
        .tg-cell { background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius); padding: 10px 12px; text-align: center; }
        .tg-cell-label { font-size: 10px; color: var(--text-3); margin-bottom: 4px; font-weight: 500; }
        .tg-cell-val   { font-size: 20px; font-weight: 700; color: var(--text-1); line-height: 1; }
        .tg-cell-val.green { color: var(--green); }
        .tg-cell-val.red   { color: var(--red); }
        .tg-cell-val.amber { color: #ff8c1a; }
        .modal-footer { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 0 20px 20px; }
        .modal-btn { padding: 10px; border-radius: var(--radius); border: 1px solid var(--border-md); font-size: 13px; font-family: var(--font); font-weight: 500; cursor: pointer; transition: background .12s; text-align: center; }
        .modal-btn.secondary { background: var(--surface-2); color: var(--text-2); }
        .modal-btn.secondary:hover { background: var(--bg); }
        .modal-btn.primary { background: var(--blue); color: #000; border-color: var(--blue); font-weight: 700; }
        .modal-btn.primary:hover { background: var(--blue-text); }
        .modal-btn:disabled { opacity: .6; cursor: not-allowed; }

        /* ── Tree registration modal ── */
        .reg-modal-sheet { max-width: 480px; }
        .reg-modal-mini-title {
            display: block;
            padding: 14px 20px 0;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text-3);
            line-height: 1;
        }
        .reg-slot-banner {
            margin: 14px 20px 0;
            padding: 10px 12px;
            background: var(--blue-bg);
            border: 1px solid rgba(255,140,26,0.35);
            border-radius: var(--radius);
            font-size: 12px;
            color: var(--blue-text);
            line-height: 1.45;
        }
        .reg-slot-banner strong { font-family: var(--mono); }
        .reg-form { padding: 14px 20px 0; display: flex; flex-direction: column; gap: 10px; }
        .reg-field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-2); margin-bottom: 4px; }
        .reg-field-hint { font-size: 11px; color: var(--text-3); margin: 4px 0 0; line-height: 1.4; }
        .reg-field input[type="text"],
        .reg-field input[type="email"],
        .reg-field input[type="tel"] {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border-md);
            border-radius: var(--radius); font-family: var(--font); font-size: 13px; outline: none;
            background: var(--surface-2); color: var(--text-1);
        }
        .reg-field .pwd-input-wrap {
            position: relative;
            width: 100%;
        }
        .reg-field .pwd-input-wrap input[type="password"],
        .reg-field .pwd-input-wrap input[type="text"] {
            width: 100%; padding: 10px 42px 10px 12px; border: 1px solid var(--border-md);
            border-radius: var(--radius); font-family: var(--font); font-size: 13px; outline: none;
            background: var(--surface-2); color: var(--text-1);
        }
        .reg-field .pwd-eye-btn {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            border: none;
            background: transparent;
            color: var(--text-3);
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }
        .reg-field .pwd-eye-btn:hover {
            color: var(--blue);
            background: rgba(255,140,26,0.1);
        }
        .reg-field .pwd-eye-btn .icon-hide { display: none; }
        .reg-field .pwd-eye-btn.is-visible .icon-show { display: none; }
        .reg-field .pwd-eye-btn.is-visible .icon-hide { display: block; }
        .reg-field .pwd-input-wrap input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(255,140,26,.15); }
        .reg-field input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(255,140,26,.15); }
        .reg-field input[readonly] { background: var(--surface-2); color: var(--text-2); }
        .reg-terms { display: flex; align-items: flex-start; gap: 8px; font-size: 12px; color: var(--text-2); }
        .reg-terms input { margin-top: 2px; }
        .reg-errors {
            margin: 0 20px; padding: 10px 12px; background: var(--red-bg); border: 1px solid rgba(239,68,68,0.35);
            border-radius: var(--radius); color: var(--red-text); font-size: 12px; display: none;
        }
        .reg-errors.show { display: block; }
        .reg-errors ul { margin: 6px 0 0 16px; }
        .reg-success {
            margin: 0 20px; padding: 10px 12px; background: var(--green-bg); border: 1px solid rgba(34,197,94,0.35);
            border-radius: var(--radius); color: var(--green-text); font-size: 12px; display: none;
        }
        .reg-success.show { display: block; }
        .reg-cred-overlay {
            position: fixed; inset: 0; z-index: 10050;
            background: rgba(0,0,0,.78);
            display: flex; align-items: center; justify-content: center;
            padding: 20px 16px;
        }
        .reg-cred-overlay.hidden { display: none !important; }
        .reg-cred-card {
            width: min(360px, 100%);
            background: #141414;
            border: 1px solid rgba(255,140,26,.35);
            border-radius: 16px;
            padding: 22px 20px 18px;
            box-shadow: 0 20px 50px rgba(0,0,0,.55);
            text-align: center;
        }
        .reg-cred-card h3 {
            margin: 0 0 6px;
            font-size: 18px;
            color: #fff;
        }
        .reg-cred-card > p.reg-cred-sub {
            margin: 0 0 12px;
            font-size: 13px;
            color: #9ca3af;
        }
        .reg-cred-welcome {
            margin: 0 0 14px;
            padding: 12px 12px;
            border-radius: 10px;
            background: rgba(255, 140, 26, 0.08);
            border: 1px solid rgba(255, 140, 26, 0.22);
            text-align: left;
            font-size: 12.5px;
            line-height: 1.45;
            color: #e8e8e8;
        }
        .reg-cred-welcome strong {
            color: #ffd7a8;
            font-weight: 700;
        }
        .reg-cred-footer-note {
            margin: 10px 0 0;
            font-size: 11.5px;
            line-height: 1.45;
            color: #9ca3af;
            text-align: left;
        }
        .reg-cred-footer-note a {
            color: #25d366;
            word-break: break-all;
        }
        .reg-cred-footer-note a.reg-cred-login {
            color: #ffb86b;
        }
        .reg-cred-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            margin-bottom: 8px;
            border-radius: 10px;
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            text-align: left;
        }
        .reg-cred-row span {
            font-size: 11px;
            color: #9ca3af;
            display: block;
            margin-bottom: 2px;
        }
        .reg-cred-row strong {
            font-size: 15px;
            color: #ffd7a8;
            font-family: var(--mono, ui-monospace, monospace);
            word-break: break-all;
        }
        .reg-cred-actions {
            display: flex;
            gap: 8px;
            margin-top: 14px;
        }
        .reg-cred-wa {
            flex: 0 0 48px;
            border: 1px solid #25d366;
            border-radius: 10px;
            background: rgba(37, 211, 102, 0.12);
            color: #25d366;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
        }
        .reg-cred-wa:disabled { opacity: 0.55; cursor: wait; }
        .reg-cred-close {
            flex: 1;
            border: 0;
            border-radius: 10px;
            padding: 12px 14px;
            background: #ff8c1a;
            color: #0a0a0a;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
        }
        .reg-sponsor-row {
            display: flex;
            align-items: stretch;
            gap: 6px;
        }
        .reg-sponsor-row input {
            flex: 1;
            min-width: 0;
            padding: 10px 12px;
            border: 1px solid var(--border-md);
            border-radius: var(--radius);
            font-family: var(--mono);
            font-size: 13px;
            background: var(--surface-2);
            color: var(--text-1);
        }
        .reg-sponsor-edit {
            width: 38px;
            flex-shrink: 0;
            border: 1px solid var(--border-md);
            border-radius: var(--radius);
            background: var(--surface);
            color: var(--text-2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .reg-sponsor-edit:hover { border-color: var(--blue); color: var(--blue); background: var(--blue-bg); }
        .reg-sponsor-pop {
            margin-top: 8px;
            padding: 12px;
            border: 1px solid var(--border-md);
            border-radius: var(--radius);
            background: var(--surface-2);
            box-shadow: var(--shadow-sm);
        }
        .reg-sponsor-pop.hidden { display: none; }
        .reg-sponsor-pop-title { font-size: 12px; font-weight: 700; color: var(--text-1); margin-bottom: 4px; }
        .reg-sponsor-pop-hint { font-size: 11px; color: var(--text-3); margin-bottom: 8px; line-height: 1.4; }
        .reg-sponsor-manual {
            width: 100%;
            padding: 9px 11px;
            border: 1px solid var(--border-md);
            border-radius: var(--radius);
            font-family: var(--mono);
            font-size: 12px;
            margin-bottom: 8px;
            background: var(--surface-2);
            color: var(--text-1);
        }
        .reg-sponsor-manual:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(255,140,26,.15); }
        .reg-sponsor-status { font-size: 11px; min-height: 16px; margin-bottom: 8px; }
        .reg-sponsor-status.ok { color: var(--green-text); }
        .reg-sponsor-status.bad { color: var(--red-text); }
        .reg-sponsor-pop-actions { display: flex; gap: 6px; }
        .reg-sponsor-pop-actions button {
            flex: 1;
            padding: 8px 10px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-family: var(--font);
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--border-md);
        }
        .reg-sponsor-pop-actions .apply { background: var(--blue); color: #000; border-color: var(--blue); font-weight: 700; }
        .reg-sponsor-pop-actions .apply:hover { background: var(--blue-text); }
        .reg-sponsor-pop-actions .cancel { background: var(--surface); color: var(--text-2); }
        svg.tv .tree-empty-node { cursor: pointer; }
        svg.tv .tree-empty-node:hover .tree-empty-icon { fill: #ff8c1a; }
        svg.tv .tree-empty-node .tree-reg-hint { fill: #ff8c1a; }
        svg.tv .tree-empty-node:hover .tree-reg-hint { fill: #ff9f3a; }
        svg.tv .tree-drill-plus circle { transition: fill .12s, stroke .12s; }
        svg.tv .tree-drill-plus:hover circle { fill: rgba(255,140,26,0.14); stroke: #ff8c1a; }
        svg.tv .tree-drill-plus:hover text { fill: #ff8c1a; }

        /* ── Responsive tweaks ── */
        @media (max-width: 900px) {
            .tree-top-row { gap: 8px; }
            .tree-legend { width: 100%; }
        }
        @media (max-width: 640px) {
            .stat-header { gap: 5px; }
            .stat-card { padding: 7px 8px; }
            .stat-card-total { font-size: 16px; }
            .stat-pill .pill-full { display: none; }
            .stat-pill .pill-short { display: inline; }
            .tree-top-title-block .page-title { font-size: 16px !important; }
            .tree-search-bar button { padding: 8px 10px; }
        }
        @media (max-width: 420px) {
            .stat-header { grid-template-columns: 1fr 1fr; gap: 6px; }
            .ctrl-area { grid-column: 1 / -1; order: -1; flex-direction: row; justify-content: flex-end; min-height: 0; margin-bottom: -2px; }
            .search-flyout { left: auto; right: 0; transform: translateY(-4px); }
            .search-flyout.open { transform: translateY(0); }
            .search-result { left: auto; right: 0; transform: none; }
            .stat-card.right-card { text-align: left; }
            .stat-card.right-card .stat-pills { justify-content: flex-start; }
            .tree-legend { gap: 3px 8px; font-size: 10px !important; }
        }
    </style>
</head>
<body class="has-mobile-bottom-nav">

<?php $user = $dashUser; require dirname(__DIR__) . '/sidebar.php'; $user = getCurrentUser(); ?>

<div class="main">
  <?php $user = $dashUser; require dirname(__DIR__) . '/navbar.php'; $user = getCurrentUser(); ?>

  <div class="content" style="padding:14px 16px 20px">
    <div class="tree-top">
      <div class="tree-top-row">
        <div class="tree-top-title-block">
          <h1 class="page-title">Genealogy Tree</h1>
          <p class="page-sub">Tap member for details · <strong>Register here</strong> to add</p>
        </div>
        <div class="tree-legend" aria-label="Status legend">
          <span><i style="background:#ff8c1a"></i>Pending</span>
          <span><i style="background:#22c55e"></i>Active</span>
          <span><i style="background:#ef4444"></i>Blocked</span>
        </div>
      </div>

      <?php if ($search_error !== ''): ?>
      <div class="tree-alert"><?= genealogy_h($search_error) ?></div>
      <?php endif; ?>

      <form class="tree-search-bar" method="get" action="<?= e($genealogyUrl) ?>">
        <input type="text" name="search" value="<?= genealogy_h($search_user_id) ?>" placeholder="Member ID…" autocomplete="off">
        <button type="submit">View tree</button>
      </form>
    </div>

    <div class="tree-page">

      <!-- ── Stat header ── -->
      <div class="stat-header">
        <div class="stat-card" id="leftCard">
          <div class="stat-card-label">Left team</div>
          <div class="stat-card-total" id="leftTotal"><?= $team_stats['left']['total'] ?></div>
          <div class="stat-pills">
            <span class="stat-pill green" id="leftActive"><span class="pill-full"><?= $team_stats['left']['active'] ?> active</span><span class="pill-short"><?= $team_stats['left']['active'] ?> A</span></span>
            <span class="stat-pill amber" id="leftInactive"><span class="pill-full"><?= $team_stats['left']['inactive'] ?> pending</span><span class="pill-short"><?= $team_stats['left']['inactive'] ?> P</span></span>
            <span class="stat-pill red" id="leftBlocked"><span class="pill-full"><?= $team_stats['left']['blocked'] ?> blocked</span><span class="pill-short"><?= $team_stats['left']['blocked'] ?> B</span></span>
          </div>
        </div>

        <div class="ctrl-area" id="sArea">
          <button class="search-btn" onclick="toggleSearch()" aria-label="Search member">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="22" y2="22"/></svg>
          </button>
          <div class="search-flyout" id="sExpand">
            <input type="text" id="sInput" placeholder="Search ID or name…" oninput="doSearch(this.value)" autocomplete="off">
          </div>
          <div class="search-result" id="sMsg"></div>
        </div>

        <div class="stat-card right-card" id="rightCard">
          <div class="stat-card-label">Right team</div>
          <div class="stat-card-total" id="rightTotal"><?= $team_stats['right']['total'] ?></div>
          <div class="stat-pills">
            <span class="stat-pill green" id="rightActive"><span class="pill-full"><?= $team_stats['right']['active'] ?> active</span><span class="pill-short"><?= $team_stats['right']['active'] ?> A</span></span>
            <span class="stat-pill amber" id="rightInactive"><span class="pill-full"><?= $team_stats['right']['inactive'] ?> pending</span><span class="pill-short"><?= $team_stats['right']['inactive'] ?> P</span></span>
            <span class="stat-pill red" id="rightBlocked"><span class="pill-full"><?= $team_stats['right']['blocked'] ?> blocked</span><span class="pill-short"><?= $team_stats['right']['blocked'] ?> B</span></span>
          </div>
        </div>
      </div>
      <!-- end stat-header -->

      <div class="breadcrumb" id="breadcrumb"></div>

      <div class="tree-wrap">
        <svg class="tv" id="tsvg"></svg>
      </div>

    </div><!-- .tree-page -->
  </div><!-- .content -->
</div><!-- .main -->

<!-- ── Tree registration modal ── -->
<div class="modal-overlay hidden" id="regOverlay" onclick="closeRegModal(event)">
  <div class="modal-sheet reg-modal-sheet" onclick="event.stopPropagation()">
    <div class="sheet-handle"></div>
    <span class="reg-modal-mini-title">Register here</span>
    <div class="modal-header">
      <div class="modal-avatar" style="background:#ff8c1a;color:#000">
        <svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3.5a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9zm-7 15.2c0-2.85 3.13-5.2 7-5.2s7 2.35 7 5.2V20H5v-1.3z"/></svg>
      </div>
      <div>
        <div class="modal-id" id="regSlotLabel">Selected position</div>
      </div>
    </div>
    <div class="reg-slot-banner" id="regSlotBanner"></div>
    <div class="reg-errors" id="regErrors"><strong>Please fix:</strong><ul id="regErrorsList"></ul></div>
    <div class="reg-success" id="regSuccess"></div>
    <form class="reg-form" id="regForm" novalidate>
      <input type="hidden" name="ajax_action" value="tree_register">
      <input type="hidden" name="binary_parent_id" id="regParentId" value="">
      <input type="hidden" name="binary_leg" id="regLeg" value="">
      <input type="hidden" name="tree_root" id="regTreeRoot" value="">
      <div class="reg-field reg-sponsor-field">
        <label for="regSponsorDisplay">Sponsor ID</label>
        <div class="reg-sponsor-row">
          <input type="text" id="regSponsorDisplay" value="">
          <button type="button" class="reg-sponsor-edit" onclick="toggleSponsorEdit()" aria-label="Edit sponsor">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
        </div>
        <input type="hidden" name="sponsor_id" id="regSponsorIdHidden" value="">
        <div class="reg-sponsor-pop hidden" id="regSponsorPop">
          <div class="reg-sponsor-pop-title">Change Sponsor</div>
          <div class="reg-sponsor-pop-hint">Select a sponsor from dropdown or enter manually:</div>
          <select class="reg-sponsor-manual" id="regSponsorDropdown" style="margin-bottom: 8px;">
            <option value="">-- Select from upline --</option>
          </select>
          <input type="text" class="reg-sponsor-manual" id="regSponsorManual" placeholder="Or enter sponsor ID manually">
          <div class="reg-sponsor-status" id="regSponsorStatus"></div>
          <div class="reg-sponsor-pop-actions">
            <button type="button" class="cancel" onclick="toggleSponsorEdit()">Cancel</button>
            <button type="button" class="apply" onclick="applySponsorChange()">Apply</button>
          </div>
        </div>
        <p class="reg-field-hint">Default: your ID. Can be changed to any upline member.</p>
      </div>
      <div class="reg-field">
        <label for="regFullName">Full name <span style="color:#dc2626">*</span></label>
        <input type="text" id="regFullName" name="full_name" required minlength="3" autocomplete="name">
      </div>
      <div class="reg-field">
        <label for="regMobile">Mobile <span style="color:#dc2626">*</span></label>
        <input type="tel" id="regMobile" name="mobile" required pattern="[6-9][0-9]{9}" maxlength="10" autocomplete="tel">
        <p class="reg-field-hint">Same mobile / email — unlimited Arowin IDs.</p>
      </div>
      <div class="reg-field">
        <label for="regEmail">Email <span style="font-weight:400;color:#9ca3af">(optional)</span></label>
        <input type="email" id="regEmail" name="email" autocomplete="email" placeholder="Optional">
      </div>
      <div class="reg-field">
        <label for="regPassword">Password <span style="color:#dc2626">*</span></label>
        <div class="pwd-input-wrap">
          <input type="password" id="regPassword" name="password" required autocomplete="new-password">
          <button type="button" class="pwd-eye-btn" onclick="togglePwdVisibility('regPassword', this)" aria-label="Show password">
            <svg class="icon-show" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="icon-hide" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>
      <div class="reg-field">
        <label for="regConfirmPw">Confirm password <span style="color:#dc2626">*</span></label>
        <div class="pwd-input-wrap">
          <input type="password" id="regConfirmPw" name="confirm_password" required autocomplete="new-password">
          <button type="button" class="pwd-eye-btn" onclick="togglePwdVisibility('regConfirmPw', this)" aria-label="Show password">
            <svg class="icon-show" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="icon-hide" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>
      <label class="reg-terms">
        <input type="checkbox" name="terms" id="regTerms" value="1">
        <span>I agree to the Terms &amp; Conditions</span>
      </label>
    </form>
    <div class="modal-footer" style="padding-top:14px">
      <button type="button" class="modal-btn secondary" onclick="closeRegModal()">Cancel</button>
      <button type="button" class="modal-btn primary" id="regSubmitBtn">Register here</button>
    </div>
  </div>
</div>

<!-- Credentials popup after successful register -->
<div class="reg-cred-overlay hidden" id="regCredOverlay" role="dialog" aria-modal="true" aria-labelledby="regCredTitle">
  <div class="reg-cred-card" id="regCredCard" onclick="event.stopPropagation()">
    <h3 id="regCredTitle">Registration successful</h3>
    <p class="reg-cred-sub">Save / share these login details.</p>
    <div class="reg-cred-welcome">
      <strong>Welcome to Arowin!</strong><br>
      Wishing you a bright and successful future with us.
    </div>
    <div class="reg-cred-row">
      <div>
        <span>User ID</span>
        <strong id="regCredUserId">—</strong>
      </div>
    </div>
    <div class="reg-cred-row">
      <div>
        <span>Password</span>
        <strong id="regCredPassword">—</strong>
      </div>
    </div>
    <div class="reg-cred-row">
      <div>
        <span>Sponsor ID</span>
        <strong id="regCredSponsorId">—</strong>
      </div>
    </div>
    <div class="reg-cred-row">
      <div>
        <span>Sponsor name</span>
        <strong id="regCredSponsorName">—</strong>
      </div>
    </div>
    <div class="reg-cred-row">
      <div>
        <span>Date</span>
        <strong id="regCredDate">—</strong>
      </div>
    </div>
    <p class="reg-cred-footer-note">
      Need help? Email us at
      <a href="mailto:support@arowin.com">support@arowin.com</a><br>
      Sign in:
      <a class="reg-cred-login" id="regCredLoginLink" href="<?= e(url('login')) ?>"><?= e(url('login')) ?></a>
    </p>
    <div class="reg-cred-actions no-capture">
      <button type="button" class="reg-cred-wa" id="regCredWaBtn" title="Share on WhatsApp" aria-label="Share on WhatsApp">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
      </button>
      <button type="button" class="reg-cred-close" id="regCredCloseBtn">OK, close</button>
    </div>
  </div>
</div>

<!-- ── Member detail modal ── -->
<div class="modal-overlay hidden" id="pOverlay" onclick="closePop(event)">
  <div class="modal-sheet" onclick="event.stopPropagation()">
    <div class="sheet-handle"></div>
    <div class="modal-header">
      <div class="modal-avatar" id="pAv"></div>
      <div>
        <div class="modal-name" id="pNm"></div>
        <div class="modal-id"   id="pId2"></div>
      </div>
    </div>
    <div class="modal-body" id="pRows"></div>
    <div class="modal-footer">
      <button class="modal-btn secondary" onclick="document.getElementById('pOverlay').classList.add('hidden')">Close</button>
      <button class="modal-btn primary"   id="drillBtn">Drill in →</button>
    </div>
  </div>
</div>

<?php require dirname(__DIR__) . '/includes/bottom-nav.php'; ?>

<script>
function togglePwdVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input || !btn) return;
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btn.classList.toggle('is-visible', show);
  btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
}

// ── Data from PHP ──────────────────────────────────────────────
const DB           = <?= json_encode($tree_data, JSON_UNESCAPED_UNICODE) ?>;
const ROOT_ID      = <?= json_encode($root_id) ?>;
const SEARCH_ROOT  = <?= json_encode($search_user_id) ?>;
const PHP_STATS    = <?= json_encode($team_stats) ?>;
const LOGGED_IN_ID = <?= json_encode((string)$user['user_id']) ?>;
const NS           = "http://www.w3.org/2000/svg";
const TREE_POST_URL = <?= json_encode($genealogyUrl, JSON_UNESCAPED_SLASHES) ?>;

let rootView   = <?= json_encode($search_user_id) ?>;
let breadcrumb = [];
let searchOpen = false;

// ── Tree layout (desktop defaults; mobile bumped in refreshTreeLayout) ──
const PERSON_PATH = 'M12 3.5a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9zm-7 15.2c0-2.85 3.13-5.2 7-5.2s7 2.35 7 5.2V20H5v-1.3z';
const COL_POS = [
    [350],
    [183, 517],
    [91, 274, 426, 609],
    [46, 137, 228, 319, 381, 472, 563, 654]
];
const TR = {
    W: 700,
    SVG_H: 480,
    PERSON_HALF: 16,
    ICON_SCALE: 1.45,
    LABEL_GAP: 6,
    LR_PILL_H: 12,
    ROW_Y: [52, 160, 268, 376],
    MOBILE_W: 640,
};

function isMobileTree() {
    return window.matchMedia('(max-width: 520px)').matches;
}

function refreshTreeLayout() {
    const mob = isMobileTree();
    if (mob) {
        // Compact professional nodes (was oversized at scale 3.0)
        TR.SVG_H = 500;
        TR.PERSON_HALF = 15;
        TR.ICON_SCALE = 1.35;
        TR.LABEL_GAP = 5;
        TR.LR_PILL_H = 11;
        TR.ROW_Y = [48, 152, 256, 360];
    } else {
        TR.SVG_H = 480;
        TR.PERSON_HALF = 16;
        TR.ICON_SCALE = 1.45;
        TR.LABEL_GAP = 6;
        TR.LR_PILL_H = 12;
        TR.ROW_Y = [52, 160, 268, 376];
    }
}

function treeIdFontSize(depth) {
    const base = depth < 2 ? 8.5 : 7;
    return base + (isMobileTree() ? 0.5 : 0);
}

function treeNameFontSize(depth) {
    const base = depth < 2 ? 7.5 : 6.5;
    return base + (isMobileTree() ? 0.5 : 0);
}

function treeNameOffset(depth) {
    return (depth < 2 ? 10 : 9) + (isMobileTree() ? 1 : 0);
}

function treeReferralFontSize(depth) {
    return (depth < 2 ? 5.5 : 5) + (isMobileTree() ? 0.5 : 0);
}

function formatReferralLine(m, depth) {
    const rid = String(m.referral || '').trim();
    if (!rid) return '';
    const rnm = String(m.referralName || '').trim();
    const maxName = depth < 2 ? (isMobileTree() ? 18 : 14) : (isMobileTree() ? 12 : 10);
    return rnm ? rid + ', ' + shortName(rnm, maxName) : rid;
}

/** Sponsor / referral ID + full name for member detail modal. */
function referralModalFields(m) {
    const rid = String(m.referral || '').trim();
    let rnm = String(m.referralName || '').trim();
    if (!rnm && rid && DB[rid]) {
        rnm = String(DB[rid].name || '').trim();
    }
    return {
        id: rid || '—',
        name: rnm || '—',
    };
}

function treeLrFontSize() {
    return isMobileTree() ? 7.5 : 7;
}

// ── Helpers ────────────────────────────────────────────────────
function getAllDesc(id, vis = new Set()) {
    if (!id || vis.has(id)) return [];
    vis.add(id);
    const m = DB[id];
    if (!m) return [];
    const r = [id];
    if (m.leftChild)  r.push(...getAllDesc(m.leftChild,  vis));
    if (m.rightChild) r.push(...getAllDesc(m.rightChild, vis));
    return r;
}

function teamStats(rid) {
    const m = DB[rid];
    if (!m) return { left:{total:0,active:0,inactive:0,blocked:0}, right:{total:0,active:0,inactive:0,blocked:0} };
    const cnt = ids => ({
        total:    ids.length,
        active:   ids.filter(i => DB[i]?.status === 'active').length,
        inactive: ids.filter(i => {
            const st = String(DB[i]?.status || 'pending').toLowerCase();
            return st === 'pending' || st === '';
        }).length,
        blocked:  ids.filter(i => {
            const st = String(DB[i]?.status || '').toLowerCase();
            return st === 'blocked' || st === 'rejected' || st === 'inactive';
        }).length,
    });
    return {
        left:  cnt(m.leftChild  ? getAllDesc(m.leftChild)  : []),
        right: cnt(m.rightChild ? getAllDesc(m.rightChild) : []),
    };
}

function activeCount(id) {
    if (!id) return 0;
    return getAllDesc(id).filter(i => DB[i]?.status === 'active').length;
}

/** Sum completed package BV for all members under a binary child leg. */
function legPackageBv(childId) {
    if (!childId) return 0;
    return getAllDesc(childId).reduce((sum, uid) => sum + (Number(DB[uid]?.selfBv) || 0), 0);
}

const STATUS_COLOR = {
    pending: '#ff8c1a',
    active: '#22c55e',
    blocked: '#ef4444',
};

function getColor(m) {
    if (!m) return STATUS_COLOR.pending;
    const st = String(m.status || 'pending').toLowerCase();
    if (st === 'active') return STATUS_COLOR.active;
    if (st === 'blocked' || st === 'rejected' || st === 'inactive') return STATUS_COLOR.blocked;
    return STATUS_COLOR.pending;
}

function shortName(name, max) {
    const n = String(name || '').trim();
    if (!n) return '—';
    if (n.length <= max) return n;
    return n.slice(0, Math.max(1, max - 1)) + '…';
}

/** Y where vertical connector may start (below ID + name block). */
function nodeConnectorStartY(y, depth) {
    const labelY = y + TR.PERSON_HALF + TR.LABEL_GAP;
    const nameDy = treeNameOffset(depth);
    const nameFs = treeNameFontSize(depth);
    const refExtra = treeReferralFontSize(depth) + 4;
    return labelY + refExtra + nameDy + Math.ceil(nameFs * 1.1) + 6;
}

/** Y where child connector ends (above icon + L:R pill). */
function nodeConnectorEndY(y, depth) {
    const lrPad = depth < 3 ? TR.LR_PILL_H + (isMobileTree() ? 12 : 10) : 6;
    return y - TR.PERSON_HALF - lrPad;
}

function labelBlockWidth(idText, nameText, idFs, nameFs) {
    const est = (s, fs) => Math.max(36, String(s).length * fs * 0.58);
    return Math.max(est(idText, idFs), est(nameText, nameFs)) + 8;
}

/** L:R counts — polished pill */
function appendLrPill(parent, x, y, lc, rc, hasL, hasR) {
    if (!hasL && !hasR) return;
    const pillY = y - TR.PERSON_HALF - (isMobileTree() ? 14 : 12);
    const fs = treeLrFontSize();
    const mono = 'var(--mono,monospace)';
    const fillN = '#737373';
    const fillY = '#22c55e';
    const charPx = 4.6;

    if (hasL && hasR) {
        const lLabel = `L:${lc}`;
        const rLabel = `R:${rc}`;
        const lW = lLabel.length * charPx + 6;
        const rW = rLabel.length * charPx + 6;
        const midGap = 5;
        const pillW = lW + rW + midGap;
        const pillH = TR.LR_PILL_H;
        const x0 = x - pillW / 2;
        parent.appendChild(el('rect', {
            x: x0, y: pillY - pillH / 2, width: pillW, height: pillH,
            rx: 6, class: 'tree-lr-bg', fill: '#121212', stroke: 'rgba(255,140,26,0.22)', 'stroke-width': '0.8',
        }));
        const divX = x0 + lW + midGap / 2;
        parent.appendChild(st(lLabel, {
            x: x0 + lW / 2, y: pillY, 'text-anchor': 'middle', 'dominant-baseline': 'central',
            'font-size': fs, fill: lc > 0 ? fillY : fillN, 'font-weight': '700', 'font-family': mono,
        }));
        parent.appendChild(el('line', {
            x1: divX, y1: pillY - 3, x2: divX, y2: pillY + 3,
            stroke: 'rgba(255,255,255,0.12)', 'stroke-width': '0.8',
        }));
        parent.appendChild(st(rLabel, {
            x: x0 + lW + midGap + rW / 2, y: pillY, 'text-anchor': 'middle', 'dominant-baseline': 'central',
            'font-size': fs, fill: rc > 0 ? fillY : fillN, 'font-weight': '700', 'font-family': mono,
        }));
    } else if (hasL) {
        const label = `L:${lc}`;
        const pillW = label.length * charPx + 10;
        parent.appendChild(el('rect', {
            x: x - pillW / 2, y: pillY - TR.LR_PILL_H / 2, width: pillW, height: TR.LR_PILL_H,
            rx: 6, class: 'tree-lr-bg', fill: '#121212', stroke: 'rgba(255,140,26,0.22)', 'stroke-width': '0.8',
        }));
        parent.appendChild(st(label, {
            x, y: pillY, 'text-anchor': 'middle', 'dominant-baseline': 'central',
            'font-size': fs, fill: lc > 0 ? fillY : fillN, 'font-weight': '700', 'font-family': mono,
        }));
    } else if (hasR) {
        const label = `R:${rc}`;
        const pillW = label.length * charPx + 10;
        parent.appendChild(el('rect', {
            x: x - pillW / 2, y: pillY - TR.LR_PILL_H / 2, width: pillW, height: TR.LR_PILL_H,
            rx: 6, class: 'tree-lr-bg', fill: '#121212', stroke: 'rgba(255,140,26,0.22)', 'stroke-width': '0.8',
        }));
        parent.appendChild(st(label, {
            x, y: pillY, 'text-anchor': 'middle', 'dominant-baseline': 'central',
            'font-size': fs, fill: rc > 0 ? fillY : fillN, 'font-weight': '700', 'font-family': mono,
        }));
    }
}

/** Tiny + between head & body, right edge — drills into that member's downline. */
function appendDrillPlusBtn(parent, x, y, nodeId) {
    const m = DB[nodeId];
    if (!m || (!m.leftChild && !m.rightChild)) return;

    const sc  = TR.ICON_SCALE;
    const mob = isMobileTree();
    const r   = mob ? 3.6 : 3.4;
    const bx  = x + 4.2 * sc + (mob ? 1 : 0.4);
    const by  = y - 1.4 * sc;

    const btn = el('g', { class: 'tree-drill-plus' });
    btn.appendChild(el('circle', {
        cx: bx, cy: by, r: mob ? 8 : 7,
        fill: 'transparent', stroke: 'none',
    }));
    btn.appendChild(el('circle', {
        cx: bx, cy: by, r: r,
        fill: '#1a1a1a', stroke: '#ff8c1a', 'stroke-width': '0.7',
    }));
    btn.appendChild(st('+', {
        x: bx, y: by + 0.15, 'text-anchor': 'middle', 'dominant-baseline': 'central',
        'font-size': mob ? '6' : '5.5', fill: '#ff8c1a', 'font-weight': '700',
        'font-family': 'inherit',
    }));
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        drillDown(nodeId);
    });
    parent.appendChild(btn);
}

/** Person silhouette in avatar disc + ring */
function appendPersonIcon(parent, x, y, color, outlined) {
    const r = TR.PERSON_HALF * 0.9;
    parent.appendChild(el('circle', {
        cx: x, cy: y, r: r + 1.6,
        class: 'tree-avatar-ring',
        stroke: outlined ? 'rgba(255,255,255,0.1)' : (color + '80'),
        'stroke-width': outlined ? '0.9' : '1.1',
        fill: 'none',
    }));
    parent.appendChild(el('circle', {
        cx: x, cy: y, r: r,
        class: 'tree-avatar-disc',
        fill: outlined ? '#141414' : '#1a1a1a',
        stroke: outlined ? '#3a3a3a' : 'rgba(255,255,255,0.05)',
        'stroke-width': '0.8',
    }));
    const pg = el('g', {
        transform: `translate(${x},${y}) scale(${TR.ICON_SCALE * 0.88}) translate(-12,-13)`,
        class: outlined ? 'tree-empty-icon' : '',
    });
    pg.appendChild(el('path', {
        d: PERSON_PATH,
        fill: outlined ? '#525252' : color,
        stroke: 'none',
    }));
    parent.appendChild(pg);
}

function el(tag, attrs) {
    const e = document.createElementNS(NS, tag);
    if (attrs) Object.entries(attrs).forEach(([k, v]) => e.setAttribute(k, v));
    return e;
}
function st(text, attrs) { const e = el('text', attrs); e.textContent = text; return e; }

// ── Build level map ────────────────────────────────────────────
function buildLevels(rid) {
    const lv = [[rid]];
    for (let d = 0; d < 3; d++) {
        const nx = [];
        lv[d].forEach(id => {
            if (!id) { nx.push(null, null); return; }
            const m = DB[id];
            nx.push(m ? m.leftChild  || null : null);
            nx.push(m ? m.rightChild || null : null);
        });
        lv.push(nx);
    }
    return lv;
}

// ── Update stat cards ──────────────────────────────────────────
function updateTopStats() {
    const s = (rootView === SEARCH_ROOT) ? PHP_STATS : teamStats(rootView);
    document.getElementById('leftTotal').textContent = s.left.total;
    document.getElementById('rightTotal').textContent = s.right.total;
    const setPill = (id, n, fullWord, shortWord) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = '<span class="pill-full">' + n + ' ' + fullWord + '</span><span class="pill-short">' + n + ' ' + shortWord + '</span>';
    };
    setPill('leftActive', s.left.active, 'active', 'A');
    setPill('leftInactive', s.left.inactive, 'pending', 'P');
    setPill('leftBlocked', s.left.blocked, 'blocked', 'B');
    setPill('rightActive', s.right.active, 'active', 'A');
    setPill('rightInactive', s.right.inactive, 'pending', 'P');
    setPill('rightBlocked', s.right.blocked, 'blocked', 'B');
}

// ── Draw SVG tree ──────────────────────────────────────────────
function draw() {
    refreshTreeLayout();
    const wrap = document.querySelector('.tree-wrap');

    if (!DB[rootView]) {
        wrap.innerHTML = '<div class="tree-empty">No tree data available for this member.</div>';
        return;
    }
    if (!wrap.querySelector('svg')) {
        wrap.innerHTML = '<svg class="tv" id="tsvg"></svg>';
    }

    const svg = document.getElementById('tsvg');
    while (svg.firstChild) svg.removeChild(svg.firstChild);
    svg.setAttribute('viewBox', `0 0 ${TR.W} ${TR.SVG_H}`);
    if (isMobileTree()) {
        svg.setAttribute('width', String(TR.MOBILE_W));
    } else {
    svg.setAttribute('width', '100%');
    }

    const lv = buildLevels(rootView);

    // ── Connector lines ──
    for (let d = 0; d < 3; d++) {
        lv[d].forEach((_, pi) => {
            const px = COL_POS[d][pi], py = TR.ROW_Y[d];
            const cy = TR.ROW_Y[d + 1];
            [COL_POS[d + 1][pi * 2], COL_POS[d + 1][pi * 2 + 1]].forEach(cx => {
                const y1 = nodeConnectorStartY(py, d);
                const y2 = nodeConnectorEndY(cy, d + 1);
                if (y2 <= y1) return;
                const my = (y1 + y2) / 2;
                svg.appendChild(el('path', {
                    d: `M${px} ${y1} L${px} ${my} L${cx} ${my} L${cx} ${y2}`,
                    fill: 'none', class: 'tree-connector',
                    stroke: 'rgba(255,140,26,0.28)', 'stroke-width': '1.15',
                    'stroke-linecap': 'round', 'stroke-linejoin': 'round',
                }));
                svg.appendChild(el('circle', {
                    cx: cx, cy: y2, r: 1.6,
                    fill: 'rgba(255,140,26,0.5)',
                }));
            });
        });
    }

    // ── Nodes ──
    for (let d = 0; d < 4; d++) {
        lv[d].forEach((id, i) => {
            const x = COL_POS[d][i], y = TR.ROW_Y[d];
            const m = id ? DB[id] : null;
            const g = el('g');
            const parentId = d > 0 ? lv[d - 1][Math.floor(i / 2)] : null;
            const slotLeg  = (i % 2 === 0) ? 'left' : 'right';
            const canRegister = !m && d > 0 && parentId && DB[parentId];

            g.style.cursor = m || canRegister ? 'pointer' : 'default';
            if (canRegister) g.setAttribute('class', 'tree-empty-node');

            if (!m) {
                appendPersonIcon(g, x, y, '#3a3a3a', true);
                const emptyY = y + TR.PERSON_HALF + TR.LABEL_GAP + 1;
                const emptyLabel = canRegister ? 'Register' : 'Empty';
                const emptyFs = canRegister
                    ? (isMobileTree() ? 7.5 : 7)
                    : (isMobileTree() ? 7 : 6.5);
                const emptyW = canRegister
                    ? (isMobileTree() ? 58 : 52)
                    : (isMobileTree() ? 40 : 36);
                const emptyH = emptyFs + 8;
                g.appendChild(el('rect', {
                    x: x - emptyW / 2, y: emptyY - emptyFs * 0.3 - 3,
                    width: emptyW, height: emptyH,
                    rx: 6,
                    class: canRegister ? 'tree-label-bg is-empty-hot' : 'tree-label-bg is-empty',
                }));
                g.appendChild(st(emptyLabel, {
                    x, y: emptyY + 3, 'text-anchor': 'middle', 'font-size': String(emptyFs),
                    fill: canRegister ? '#ff8c1a' : '#6b6b6b',
                    class: canRegister ? 'tree-reg-hint' : '',
                    'font-family': 'inherit', 'font-weight': canRegister ? '700' : '500',
                }));
                if (canRegister) {
                    g.addEventListener('click', () => openRegisterModal(parentId, slotLeg));
                }
            } else {
                const color = getColor(m);
                const isRootNode = id === rootView;
                appendPersonIcon(g, x, y, color, false);
                appendDrillPlusBtn(g, x, y, id);

                const idFs    = treeIdFontSize(d);
                const nameFs  = treeNameFontSize(d);
                const refFs   = treeReferralFontSize(d);
                const disp    = id.length > 11 && d >= 2 ? id.slice(0, 9) + '…' : id;
                const refLine = formatReferralLine(m, d);
                const referralY = y + TR.PERSON_HALF + 4;
                const labelY  = referralY + (refLine ? refFs + 3.5 : TR.LABEL_GAP - 1);
                const nameDy  = treeNameOffset(d);
                const nameStr = shortName(m.name, d < 2 ? (isMobileTree() ? 14 : 13) : (isMobileTree() ? 11 : 10));
                const padX = 8;
                const blockW  = Math.max(
                    labelBlockWidth(disp, nameStr, idFs, nameFs),
                    refLine ? labelBlockWidth(refLine, '', refFs, refFs) : 0
                ) + padX;
                const blockTop = (refLine ? referralY - refFs * 0.8 : labelY - idFs * 0.7) - 3;
                const blockH   = (labelY - blockTop) + nameDy + nameFs + 6;

                g.appendChild(el('rect', {
                    x: x - blockW / 2, y: blockTop,
                    width: blockW, height: blockH,
                    rx: 7,
                    class: isRootNode ? 'tree-label-bg is-root' : 'tree-label-bg',
                }));
                if (refLine) {
                    g.appendChild(st(refLine, {
                        x, y: referralY, 'text-anchor': 'middle', 'font-size': refFs,
                        class: 'tree-node-referral',
                    }));
                }
                g.appendChild(st(disp, {
                    x, y: labelY, 'text-anchor': 'middle', 'font-size': idFs,
                    fill: '#ffffff', class: 'tree-node-id',
                }));
                g.appendChild(st(nameStr, {
                    x, y: labelY + nameDy, 'text-anchor': 'middle', 'font-size': nameFs,
                    fill: '#d4d4d4', class: 'tree-node-name',
                }));

                if (d < 3) {
                    const lc   = activeCount(m.leftChild);
                    const rc   = activeCount(m.rightChild);
                    const hasL = m.leftChild !== null;
                    const hasR = m.rightChild !== null;
                    appendLrPill(g, x, y, lc, rc, hasL, hasR);
                }

                g.addEventListener('click', () => {
                    if (d === 3 && (m.leftChild || m.rightChild)) drillDown(id);
                    else showPop(m);
                });
            }

            svg.appendChild(g);
        });
    }

    // "Drill deeper" hint
    if (lv[3].some(id => id && (DB[id]?.leftChild || DB[id]?.rightChild))) {
        svg.appendChild(st('↓ Tap bottom row to drill deeper', {
            x: TR.W / 2, y: TR.SVG_H - 12, 'text-anchor': 'middle',
            'font-size': isMobileTree() ? '11' : '10', fill: '#6b6b6b', 'font-family': 'inherit',
        }));
    }

    updateTopStats();
    renderBreadcrumb();
}

// ── Navigation ─────────────────────────────────────────────────
function drillDown(id) {
    if (!DB[id]) return;
    breadcrumb.push({ id: rootView });
    rootView = id;
    draw();
}

function goTo(id) {
    const idx = breadcrumb.findIndex(b => b.id === id);
    breadcrumb = idx >= 0 ? breadcrumb.slice(0, idx) : [];
    rootView = id;
    draw();
}

// ── Breadcrumb ─────────────────────────────────────────────────
function renderBreadcrumb() {
    const bc = document.getElementById('breadcrumb');
    bc.innerHTML = '';

    const mkItem = (label, id) => {
        const s = document.createElement('span');
        s.className = 'bc-item';
        s.textContent = label;
        s.onclick = () => goTo(id);
        return s;
    };
    const mkSep = () => { const s = document.createElement('span'); s.className = 'bc-sep'; s.textContent = '›'; return s; };

    bc.appendChild(mkItem('⌂ ' + ROOT_ID, ROOT_ID));
    breadcrumb.forEach(b => { bc.appendChild(mkSep()); bc.appendChild(mkItem(b.id, b.id)); });

    if (rootView !== ROOT_ID) {
        bc.appendChild(mkSep());
        const cur = document.createElement('span');
        cur.className = 'bc-cur';
        cur.textContent = rootView;
        bc.appendChild(cur);
    }
}

// ── In-tree search ─────────────────────────────────────────────
function toggleSearch() {
    searchOpen = !searchOpen;
    document.getElementById('sExpand').classList.toggle('open', searchOpen);
    if (searchOpen) setTimeout(() => document.getElementById('sInput').focus(), 30);
    else { document.getElementById('sInput').value = ''; document.getElementById('sMsg').classList.remove('show'); }
}

document.addEventListener('click', e => {
    if (searchOpen && !e.target.closest('#sArea')) {
        searchOpen = false;
        document.getElementById('sExpand').classList.remove('open');
        document.getElementById('sInput').value = '';
        document.getElementById('sMsg').classList.remove('show');
    }
});

function doSearch(val) {
    const msg = document.getElementById('sMsg');
    if (!val.trim()) { msg.classList.remove('show'); return; }
    const q = val.toLowerCase();
    const f = Object.values(DB).find(m =>
        m.id.toLowerCase().includes(q) || m.name.toLowerCase().includes(q)
    );
    if (f) {
        msg.className = 'search-result show found';
        msg.textContent = '✓ ' + f.id + ' — tap to go';
        msg.onclick = () => { goTo(f.id); toggleSearch(); };
    } else {
        msg.className = 'search-result show notfound';
        msg.textContent = 'Not found in loaded tree';
        msg.onclick = null;
    }
}

// ── Member popup ───────────────────────────────────────────────
function showPop(m) {
    const s = teamStats(m.id);

    const pAv = document.getElementById('pAv');
    pAv.style.background = getColor(m);
    pAv.style.color = '#000';
    pAv.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="' + PERSON_PATH + '"/></svg>';
    pAv.textContent = '';
    document.getElementById('pNm').textContent      = m.name;
    document.getElementById('pId2').textContent     = 'ID: ' + m.id;

    const statusKey = String(m.status || 'pending').toLowerCase();
    const sb = {
        active:   '<span class="status-badge a">Active</span>',
        pending:  '<span class="status-badge p">Pending</span>',
        blocked:  '<span class="status-badge i">Blocked</span>',
        rejected: '<span class="status-badge i">Blocked</span>',
        inactive: '<span class="status-badge i">Blocked</span>',
    }[statusKey] || '<span class="status-badge p">Pending</span>';

    const pkgAmt = Number(DB[m.id]?.packageAmount) || 0;
    const leftBiz = Number(DB[m.id]?.leftBusiness);
    const rightBiz = Number(DB[m.id]?.rightBusiness);
    const leftBusiness = Number.isFinite(leftBiz) ? leftBiz : legPackageBv(m.leftChild);
    const rightBusiness = Number.isFinite(rightBiz) ? rightBiz : legPackageBv(m.rightChild);
    const fmtBiz = (n) => '$' + Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 0 });
    const pkgLine = pkgAmt > 0
        ? `<div class="info-row"><span class="info-key">Package</span><span class="info-val"><strong>$${pkgAmt.toLocaleString('en-US')}</strong></span></div>`
        : '';

    const tg = `
        <div class="team-section-title">Team breakdown</div>
        <div class="team-stats-grid">
            <div class="tg-cell"><div class="tg-cell-label">Left total</div><div class="tg-cell-val">${s.left.total}</div></div>
            <div class="tg-cell"><div class="tg-cell-label">Right total</div><div class="tg-cell-val">${s.right.total}</div></div>
            <div class="tg-cell"><div class="tg-cell-label">Left active</div><div class="tg-cell-val green">${s.left.active}</div></div>
            <div class="tg-cell"><div class="tg-cell-label">Right active</div><div class="tg-cell-val green">${s.right.active}</div></div>
            <div class="tg-cell"><div class="tg-cell-label">Left pending</div><div class="tg-cell-val amber">${s.left.inactive}</div></div>
            <div class="tg-cell"><div class="tg-cell-label">Right pending</div><div class="tg-cell-val amber">${s.right.inactive}</div></div>
            <div class="tg-cell"><div class="tg-cell-label">Left blocked</div><div class="tg-cell-val red">${s.left.blocked}</div></div>
            <div class="tg-cell"><div class="tg-cell-label">Right blocked</div><div class="tg-cell-val red">${s.right.blocked}</div></div>
        </div>`;

    const ref = referralModalFields(m);
    const rankLabel = String(m.rank || '').trim() || 'Unranked';
    document.getElementById('pRows').innerHTML =
        [
            ['Rank', `<strong>${rankLabel}</strong>`],
            ['Join date', m.joinDt],
            ['Referral ID', `<span class="info-mono">${ref.id}</span>`],
            ['Referral Name', ref.name],
            ['Mobile', m.mobile || '—'],
            ['Status', sb],
            ['Type', m.isPowerId ? '<span class="status-badge p">Power ID</span>' : 'Live ID'],
            ['Left Business', `<strong>${fmtBiz(leftBusiness)}</strong>`],
            ['Right Business', `<strong>${fmtBiz(rightBusiness)}</strong>`],
        ]
        .map(([k, v]) => `<div class="info-row"><span class="info-key">${k}</span><span class="info-val">${v}</span></div>`)
        .join('') + pkgLine + tg;

    document.getElementById('drillBtn').onclick = () => goToFromPopup(m.id);
    document.getElementById('pOverlay').classList.remove('hidden');
}

function goToFromPopup(id) {
    document.getElementById('pOverlay').classList.add('hidden');
    goTo(id);
}
function closePop(e) {
    if (e && e.target !== e.currentTarget) return;
    document.getElementById('pOverlay').classList.add('hidden');
}

// ── Tree registration modal ────────────────────────────────────
function applyTreePayload(payload) {
    if (!payload) return;
    if (payload.tree_data) {
        Object.keys(DB).forEach(k => { delete DB[k]; });
        Object.assign(DB, payload.tree_data);
    }
    if (payload.team_stats && rootView === SEARCH_ROOT) {
        Object.assign(PHP_STATS, payload.team_stats);
    }
    draw();
}

function resetRegModalFeedback() {
    const errBox = document.getElementById('regErrors');
    const okBox  = document.getElementById('regSuccess');
    errBox.classList.remove('show');
    okBox.classList.remove('show');
    document.getElementById('regErrorsList').innerHTML = '';
    okBox.textContent = '';
}

function formatSponsorDisplay(id, name) {
    const n = String(name || '').trim();
    return n ? id + ' — ' + n : id;
}

function setRegSponsor(id, name) {
    document.getElementById('regSponsorIdHidden').value = id;
    document.getElementById('regSponsorDisplay').value = formatSponsorDisplay(id, name);
}

function openRegisterModal(parentId, leg) {
    const parent = DB[parentId];
    if (!parent) return;

    resetRegModalFeedback();
    document.getElementById('regParentId').value = parentId;
    document.getElementById('regLeg').value        = leg;
    document.getElementById('regTreeRoot').value = rootView;

    // Default sponsor is parent ID (upline)
    const defaultSponsorId = parentId;
    const defaultSponsor = parent;
    setRegSponsor(defaultSponsorId, defaultSponsor ? defaultSponsor.name : '');

    const legLabel = leg === 'right' ? 'Right (R)' : 'Left (L)';
    document.getElementById('regSlotLabel').textContent = 'Under ' + parentId + ' · ' + legLabel;
    document.getElementById('regSlotBanner').innerHTML =
        'Placement position: <strong>' + parentId + '</strong> (' + (parent.name || '—') + ') → <strong>' + legLabel + '</strong> child. '
        + 'New member will be placed at this slot.';

    document.getElementById('regForm').reset();
    document.querySelectorAll('#regOverlay .pwd-eye-btn').forEach(function (btn) {
        btn.classList.remove('is-visible');
        btn.setAttribute('aria-label', 'Show password');
    });
    ['regPassword', 'regConfirmPw'].forEach(function (id) {
        const input = document.getElementById(id);
        if (input) input.type = 'password';
    });
    document.getElementById('regParentId').value = parentId;
    document.getElementById('regLeg').value        = leg;
    document.getElementById('regTreeRoot').value = rootView;
    setRegSponsor(defaultSponsorId, defaultSponsor ? defaultSponsor.name : '');

    // Populate sponsor quick select with upline members
    populateSponsorQuickSelect(parentId);

    const btn = document.getElementById('regSubmitBtn');
    btn.disabled = false;
    btn.textContent = 'Register here';

    document.getElementById('regOverlay').classList.remove('hidden');
    setTimeout(() => document.getElementById('regFullName').focus(), 50);
}

function closeRegModal(e) {
    if (e && e.target !== e.currentTarget) return;
    document.getElementById('regOverlay').classList.add('hidden');
}

function showRegCredentialsPopup(info) {
    info = info || {};
    document.getElementById('regCredUserId').textContent = String(info.userId || '—');
    document.getElementById('regCredPassword').textContent = String(info.password || '—');
    document.getElementById('regCredSponsorId').textContent = String(info.sponsorId || '—');
    document.getElementById('regCredSponsorName').textContent = String(info.sponsorName || '—');
    document.getElementById('regCredDate').textContent = String(info.date || '—');
    // Register sheet behind — close it so credentials popup is visible immediately
    document.getElementById('regOverlay').classList.add('hidden');
    document.getElementById('regCredOverlay').classList.remove('hidden');
}

function closeRegCredentialsPopup() {
    document.getElementById('regCredOverlay').classList.add('hidden');
    document.getElementById('regOverlay').classList.add('hidden');
    const btn = document.getElementById('regSubmitBtn');
    if (btn) {
        btn.disabled = false;
        btn.textContent = 'Register here';
    }
}

function loadHtml2Canvas() {
    return new Promise(function (resolve, reject) {
        if (window.html2canvas) {
            resolve(window.html2canvas);
            return;
        }
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
        s.onload = function () { resolve(window.html2canvas); };
        s.onerror = function () { reject(new Error('html2canvas failed')); };
        document.head.appendChild(s);
    });
}

async function shareRegCredentialsWhatsApp() {
    const btn = document.getElementById('regCredWaBtn');
    const card = document.getElementById('regCredCard');
    if (!card) return;

    const uid = document.getElementById('regCredUserId').textContent || '';
    const pw = document.getElementById('regCredPassword').textContent || '';
    const sid = document.getElementById('regCredSponsorId').textContent || '';
    const sname = document.getElementById('regCredSponsorName').textContent || '';
    const dt = document.getElementById('regCredDate').textContent || '';
    const loginLink = (document.getElementById('regCredLoginLink') && document.getElementById('regCredLoginLink').href) || '';
    const text =
        'Welcome to Arowin!\n' +
        'Wishing you a bright and successful future with us.\n\n' +
        'Arowin Registration\n' +
        'User ID: ' + uid + '\n' +
        'Password: ' + pw + '\n' +
        'Sponsor ID: ' + sid + '\n' +
        'Sponsor name: ' + sname + '\n' +
        'Date: ' + dt + '\n\n' +
        'Need help? support@arowin.com\n' +
        'Sign in: ' + loginLink;

    if (btn) btn.disabled = true;
    try {
        const html2canvas = await loadHtml2Canvas();
        const canvas = await html2canvas(card, {
            backgroundColor: '#141414',
            scale: 2,
            useCORS: true,
            ignoreElements: function (el) {
                return el.classList && el.classList.contains('no-capture');
            },
        });
        const blob = await new Promise(function (resolve) {
            canvas.toBlob(function (b) { resolve(b); }, 'image/png');
        });
        if (!blob) throw new Error('No image');

        const file = new File([blob], 'arowin-registration.png', { type: 'image/png' });
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            await navigator.share({
                files: [file],
                title: 'Arowin Registration',
                text: text,
            });
            return;
        }

        // Fallback: download image + open WhatsApp with text
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'arowin-registration-' + uid + '.png';
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 2000);
        window.open('https://wa.me/?text=' + encodeURIComponent(text + '\n\n(Image downloaded — attach in WhatsApp)'), '_blank');
    } catch (err) {
        window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
    } finally {
        if (btn) btn.disabled = false;
    }
}

document.getElementById('regCredCloseBtn').addEventListener('click', closeRegCredentialsPopup);
document.getElementById('regCredWaBtn').addEventListener('click', shareRegCredentialsWhatsApp);
document.getElementById('regCredOverlay').addEventListener('click', function (e) {
    if (e.target === e.currentTarget) closeRegCredentialsPopup();
});

// ── Sponsor edit functionality ─────────────────────────────────────
function toggleSponsorEdit() {
    const pop = document.getElementById('regSponsorPop');
    pop.classList.toggle('hidden');
    if (!pop.classList.contains('hidden')) {
        document.getElementById('regSponsorManual').focus();
    }
}

function populateSponsorQuickSelect(parentId) {
    const dropdown = document.getElementById('regSponsorDropdown');
    dropdown.innerHTML = '<option value="">-- Select from upline --</option>';

    // Get upline path from root to parent
    const upline = getUplinePath(parentId);
    if (upline.length === 0) return;

    // Add logged-in user as first option if not already in upline
    if (!upline.find(u => u.id === LOGGED_IN_ID) && DB[LOGGED_IN_ID]) {
        const user = DB[LOGGED_IN_ID];
        const option = document.createElement('option');
        option.value = LOGGED_IN_ID;
        option.textContent = user.id + ' — ' + (user.name || 'You');
        if (LOGGED_IN_ID === document.getElementById('regSponsorIdHidden').value) {
            option.selected = true;
        }
        dropdown.appendChild(option);
    }

    // Add upline members
    upline.forEach(u => {
        const option = document.createElement('option');
        option.value = u.id;
        option.textContent = u.id + ' — ' + (u.name || '—');
        if (u.id === document.getElementById('regSponsorIdHidden').value) {
            option.selected = true;
        }
        dropdown.appendChild(option);
    });

    // Add change event listener to dropdown
    dropdown.onchange = function() {
        if (this.value) {
            document.getElementById('regSponsorManual').value = this.value;
            validateSponsorInput(this.value);
        }
    };
}

function getUplinePath(targetId) {
    const path = [];
    let current = targetId;
    const visited = new Set();

    for (let i = 0; i < 64 && current && !visited.has(current); i++) {
        visited.add(current);
        const node = DB[current];
        if (!node) break;

        path.unshift({ id: current, name: node.name || '' });

        // Find parent of current node
        let foundParent = null;
        for (const uid in DB) {
            const n = DB[uid];
            if (n.leftChild === current || n.rightChild === current) {
                foundParent = uid;
                break;
            }
        }
        current = foundParent;
    }

    return path;
}


function validateSponsorInput(id) {
    const status = document.getElementById('regSponsorStatus');
    id = String(id || '').trim();

    if (!id) {
        status.textContent = '';
        status.className = 'reg-sponsor-status';
        return false;
    }

    const sponsor = DB[id];
    if (sponsor) {
        status.textContent = '✓ ' + id + ' — ' + (sponsor.name || 'Valid');
        status.className = 'reg-sponsor-status ok';
        return true;
    } else {
        status.textContent = '✗ Sponsor ID not found';
        status.className = 'reg-sponsor-status bad';
        return false;
    }
}

function applySponsorChange() {
    const manualId = document.getElementById('regSponsorManual').value.trim();
    if (!manualId || !validateSponsorInput(manualId)) {
        return;
    }

    const sponsor = DB[manualId];
    setRegSponsor(manualId, sponsor ? sponsor.name : '');
    toggleSponsorEdit();
}

// Add input validation for manual sponsor entry
document.addEventListener('DOMContentLoaded', () => {
    const manualInput = document.getElementById('regSponsorManual');
    if (manualInput) {
        manualInput.addEventListener('input', (e) => {
            validateSponsorInput(e.target.value);
        });
    }
});

async function submitTreeRegistration() {
    const btn  = document.getElementById('regSubmitBtn');
    const form = document.getElementById('regForm');
    resetRegModalFeedback();
    btn.disabled = true;
    btn.textContent = 'Registering…';

    try {
        const res = await fetch(TREE_POST_URL, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const raw = await res.text();
        let data;
        try {
            data = JSON.parse(raw);
        } catch (parseErr) {
            document.getElementById('regErrorsList').innerHTML = '<li>Server returned an invalid response. Please try again.</li>';
            document.getElementById('regErrors').classList.add('show');
            btn.disabled = false;
            btn.textContent = 'Register here';
            return;
        }

        if (!data.ok) {
            const list = document.getElementById('regErrorsList');
            list.innerHTML = (data.errors || ['Registration failed.']).map(msg => '<li>' + msg + '</li>').join('');
            document.getElementById('regErrors').classList.add('show');
            btn.disabled = false;
            btn.textContent = 'Register here';
            return;
        }

        document.getElementById('regSuccess').textContent = data.message || 'Member registered.';
        document.getElementById('regSuccess').classList.add('show');
        applyTreePayload(data);

        const plainPw = (document.getElementById('regPassword') && document.getElementById('regPassword').value)
            || data.password
            || '';
        const sponsorId = data.sponsor_id
            || (document.getElementById('regSponsorIdHidden') && document.getElementById('regSponsorIdHidden').value)
            || '';
        let sponsorName = data.sponsor_name || '';
        if (!sponsorName && sponsorId && DB[sponsorId]) {
            sponsorName = DB[sponsorId].name || '';
        }
        showRegCredentialsPopup({
            userId: data.user_id || '',
            password: plainPw,
            sponsorId: sponsorId,
            sponsorName: sponsorName,
            date: data.registered_at || new Date().toLocaleString(),
        });
        btn.disabled = false;
        btn.textContent = 'Register here';
    } catch (err) {
        document.getElementById('regErrorsList').innerHTML = '<li>Network error. Please try again.</li>';
        document.getElementById('regErrors').classList.add('show');
        btn.disabled = false;
        btn.textContent = 'Register here';
    }
}

document.getElementById('regSubmitBtn').addEventListener('click', submitTreeRegistration);

// ── Init ───────────────────────────────────────────────────────
let resizeTreeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTreeTimer);
    resizeTreeTimer = setTimeout(draw, 150);
});
draw();
</script>
<script src="<?= e($jsDash) ?>"></script>
<?php require dirname(__DIR__, 2) . '/includes/pwa-register.php'; ?>
</body>
</html>