<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'distribute';
$ADMIN_TITLE = 'Total distribute amount';
$ADMIN_SUBTITLE = 'All referral and matching income paid to members';
$emptyMessage = 'No income distributed yet.';
$columns = ['ID', 'User ID', 'Type', 'Amount', 'Source', 'Note', 'Time'];
$rows = [];

foreach (arowin_admin_list_income_payouts() as $row) {
    $rows[] = [
        '#' . (int) $row['id'],
        '<strong class="admin-mono">' . e((string) $row['username']) . '</strong>',
        e((string) $row['type']),
        e(arowin_admin_fmt_money((float) $row['amount'])),
        '<span class="admin-mono">' . e((string) ($row['source_username'] ?? '—')) . '</span>',
        e((string) ($row['note'] ?? '—')),
        '<span class="admin-muted">' . e((string) ($row['created_at'] ?? '')) . '</span>',
    ];
}

require dirname(__DIR__, 2) . '/includes/report-page.php';
