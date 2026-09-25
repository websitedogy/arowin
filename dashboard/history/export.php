<?php
/**
 * Download filtered wallet history as CSV (permanent ledger export)
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/history.php';

$sessionUid = (string) ($_SESSION['arowin_uid'] ?? '');
if ($sessionUid === '' || !arowin_is_logged_in()) {
    header('Location: ' . url('login'), true, 303);
    exit;
}

$user = arowin_get_user_by_username($sessionUid);
if (!$user) {
    header('Location: ' . url('login'), true, 303);
    exit;
}

$filters = arowin_history_parse_filters($_GET);
$result = arowin_wallet_history_query((string) $user['username'], [
    'type' => $filters['type'],
    'q' => $filters['q'],
    'from' => $filters['from'],
    'to' => $filters['to'],
    'limit' => 5000,
    'offset' => 0,
]);

$filename = 'arowin-history-' . strtolower((string) $user['username']) . '-' . date('Ymd-His') . '.csv';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit;
}

// UTF-8 BOM for Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Txn ID',
    'Date',
    'Type',
    'Direction',
    'Amount (USDT)',
    'Balance after (USDT)',
    'Counterparty',
    'Note',
]);

foreach ($result['rows'] as $row) {
    $type = (string) ($row['type'] ?? '');
    $credit = arowin_history_is_credit($type);
    fputcsv($out, [
        (string) ($row['id'] ?? ''),
        (string) ($row['created_at'] ?? ''),
        arowin_history_type_label($type),
        $credit ? 'Credit' : 'Debit',
        number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
        number_format((float) ($row['balance_after'] ?? 0), 2, '.', ''),
        (string) ($row['counterparty'] ?? ''),
        (string) ($row['note'] ?? ''),
    ]);
}

fclose($out);
exit;
