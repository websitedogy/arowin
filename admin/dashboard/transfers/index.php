<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'transfers';
$ADMIN_TITLE = 'Today transfers';
$ADMIN_SUBTITLE = 'Member-to-member wallet transfers for today';
$emptyMessage = 'No transfers today.';
$columns = ['ID', 'User ID', 'Type', 'Amount', 'Counterparty', 'Time'];
$rows = [];

foreach (arowin_admin_list_wallet_tx(['transfer_out', 'transfer_in'], true) as $row) {
    $rows[] = [
        '#' . (int) $row['id'],
        '<strong class="admin-mono">' . e((string) $row['username']) . '</strong>',
        e((string) $row['type']),
        e(arowin_admin_fmt_money((float) $row['amount'])),
        '<span class="admin-mono">' . e((string) ($row['counterparty'] ?? '—')) . '</span>',
        '<span class="admin-muted">' . e((string) ($row['created_at'] ?? '')) . '</span>',
    ];
}

require dirname(__DIR__, 2) . '/includes/report-page.php';
