<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'deposits';
$ADMIN_TITLE = 'Today deposits';
$ADMIN_SUBTITLE = 'Wallet deposit ledger for today';
$emptyMessage = 'No deposits today.';
$columns = ['ID', 'User ID', 'Amount', 'Balance after', 'Note', 'Time'];
$rows = [];

foreach (arowin_admin_list_wallet_tx(['deposit'], true) as $row) {
    $rows[] = [
        '#' . (int) $row['id'],
        '<strong class="admin-mono">' . e((string) $row['username']) . '</strong>',
        e(arowin_admin_fmt_money((float) $row['amount'])),
        e(arowin_admin_fmt_money((float) $row['balance_after'])),
        e((string) ($row['note'] ?? '—')),
        '<span class="admin-muted">' . e((string) ($row['created_at'] ?? '')) . '</span>',
    ];
}

require dirname(__DIR__, 2) . '/includes/report-page.php';
