<?php
/**
 * Income box history API — earnings / referral / matching / daily / rank
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$AROWIN_API_MODE = true;
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/wallet-repository.php';
require_once dirname(__DIR__, 2) . '/includes/ranks-repository.php';
require_once dirname(__DIR__, 2) . '/includes/admin-metrics-repository.php';
require_once dirname(__DIR__, 2) . '/includes/rank-bonus-repository.php';
require_once dirname(__DIR__, 2) . '/includes/daily-yield-repository.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function arowin_income_hist_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!arowin_is_logged_in()) {
    arowin_income_hist_json(['ok' => false, 'message' => 'Not signed in.', 'rows' => []], 401);
}

$username = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? ''));
if ($username === '') {
    arowin_income_hist_json(['ok' => false, 'message' => 'Not signed in.', 'rows' => []], 401);
}

// Credit any missing L1:R1 / matching / rank / yield into the ledger before listing.
if (function_exists('arowin_income_page_catchup')) {
    try {
        arowin_income_page_catchup($username);
    } catch (Throwable $e) {
        error_log('Income history catch-up: ' . $e->getMessage());
    }
}

$kind = strtolower(trim((string) ($_GET['kind'] ?? 'earnings')));
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 25)));

$titles = [
    'earnings' => 'Earnings History',
    'referral' => 'Direct Income History',
    'matching' => 'Matching Income History',
    'rank' => 'Rank Bonus History',
    'daily' => 'Daily Yield History',
];

$typeMap = [
    'earnings' => 'earnings',
    'referral' => 'referral_income',
    'matching' => 'matching_income',
    'daily' => 'daily_yield',
    'rank' => 'rank_bonus',
];

if (!isset($typeMap[$kind])) {
    $kind = 'earnings';
}

$labels = [
    'referral_income' => 'Direct Income',
    'matching_income' => 'Matching Income',
    'daily_yield' => 'Daily Yield',
    'rank_bonus' => 'Rank Bonus',
];

// Rank Bonus: week schedule + next release date (separate from Daily Yield).
if ($kind === 'rank') {
    $emptyMsg = 'No history available. Achieve a rank (1 Left + 1 Right starters) to start Rank Bonus.';
    $hist = [
        'enrollments' => [],
        'weeks' => [],
        'next_release' => null,
        'next_release_fmt' => '',
        'active_rank' => '',
        'total' => 0.0,
    ];
    try {
        $hist = arowin_rank_bonus_history($username);
    } catch (Throwable $e) {
        error_log('Rank bonus history API: ' . $e->getMessage());
    }

    $weeks = $hist['weeks'] ?? [];
    $rows = [];
    foreach ($weeks as $w) {
        $status = (string) ($w['status'] ?? '');
        $rows[] = [
            'id' => (int) (($w['enrollment_id'] ?? 0) * 100 + (int) ($w['week'] ?? 0)),
            'type' => 'rank_bonus',
            'label' => (string) ($w['label'] ?? 'Week'),
            'amount' => round((float) ($w['amount'] ?? 0), 2),
            'amount_fmt' => (string) ($w['amount_fmt'] ?? ''),
            'note' => (string) ($w['note'] ?? ''),
            'when' => (string) ($w['when'] ?? ''),
            'status' => $status,
            'week' => (int) ($w['week'] ?? 0),
            'rank_label' => (string) ($w['rank_label'] ?? ''),
        ];
    }

    // Unlocked weeks already sorted to top by arowin_rank_bonus_history().
    // Do not reverse — keep Week 1 / unlocked at top.

    $subParts = [];
    if (!empty($hist['active_rank'])) {
        $subParts[] = 'Active: ' . $hist['active_rank'];
    }
    if (!empty($hist['next_release_fmt'])) {
        $subParts[] = 'Next payment release: ' . $hist['next_release_fmt'];
    }
    $total = round((float) ($hist['total'] ?? 0), 2);

    arowin_income_hist_json([
        'ok' => true,
        'kind' => 'rank',
        'title' => $titles['rank'],
        'total' => $total,
        'total_fmt' => number_format($total, 2) . ' USDT',
        'next_release' => $hist['next_release'] ?? null,
        'next_release_fmt' => (string) ($hist['next_release_fmt'] ?? ''),
        'active_rank' => (string) ($hist['active_rank'] ?? ''),
        'subtitle' => $subParts !== [] ? implode(' · ', $subParts) : '',
        'view' => 'weeks',
        'rows' => $rows,
        'message' => $rows === [] ? $emptyMsg : '',
    ]);
}

// Daily Yield: credits + stop reason (rank achievement / upgrade / completed).
if ($kind === 'daily') {
    $emptyMsg = 'No Daily Yield yet. Activate a pure $50 package to start $0.25/day.';
    $hist = [
        'rows' => [],
        'total' => 0.0,
        'status' => '',
        'stop_reason' => '',
        'stop_notice' => '',
        'stopped_at' => '',
        'subtitle' => '',
    ];
    try {
        $hist = arowin_daily_yield_history($username);
    } catch (Throwable $e) {
        error_log('Daily yield history API: ' . $e->getMessage());
    }

    $rows = [];
    foreach ($hist['rows'] ?? [] as $i => $row) {
        $row['id'] = (int) ($row['id'] ?? 0) ?: (1000 + $i);
        $rows[] = $row;
    }
    $total = round((float) ($hist['total'] ?? 0), 2);
    $notice = trim((string) ($hist['stop_notice'] ?? ''));

    arowin_income_hist_json([
        'ok' => true,
        'kind' => 'daily',
        'title' => $titles['daily'],
        'total' => $total,
        'total_fmt' => number_format($total, 2) . ' USDT',
        'subtitle' => (string) ($hist['subtitle'] ?? ''),
        'stop_notice' => $notice,
        'stop_reason' => (string) ($hist['stop_reason'] ?? ''),
        'status' => (string) ($hist['status'] ?? ''),
        'view' => 'daily',
        'rows' => $rows,
        'message' => $rows === []
            ? ($notice !== '' ? $notice : $emptyMsg)
            : '',
    ]);
}

$result = arowin_wallet_history_query($username, [
    'type' => $typeMap[$kind],
    'limit' => $limit,
    'offset' => 0,
]);

$rows = [];
$sum = 0.0;
foreach ($result['rows'] as $row) {
    $type = (string) ($row['type'] ?? '');
    $amount = round((float) ($row['amount'] ?? 0), 2);
    $sum = round($sum + $amount, 2);
    $when = function_exists('arowin_format_india_time')
        ? arowin_format_india_time(isset($row['created_at']) ? (string) $row['created_at'] : null, 'd M Y, h:i A')
        : (string) ($row['created_at'] ?? '');
    $sign = $amount < 0 ? '' : '+';
    $rows[] = [
        'id' => (int) ($row['id'] ?? 0),
        'type' => $type,
        'label' => $labels[$type] ?? ucwords(str_replace('_', ' ', $type)),
        'amount' => $amount,
        'amount_fmt' => $sign . number_format($amount, 2) . ' USDT',
        'note' => trim((string) ($row['note'] ?? '')),
        'when' => $when,
    ];
}

arowin_income_hist_json([
    'ok' => true,
    'kind' => $kind,
    'title' => $titles[$kind],
    'total' => $sum,
    'total_fmt' => number_format($sum, 2) . ' USDT',
    'rows' => $rows,
    'message' => $rows === [] ? 'No credits yet for this income type.' : '',
]);
