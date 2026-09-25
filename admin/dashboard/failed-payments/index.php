<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'failed-payments';
$ADMIN_TITLE = 'Today failed payments';
$ADMIN_SUBTITLE = 'Rejected package purchase submissions for today';
$emptyMessage = 'No failed payments today.';
$columns = ['ID', 'User ID', 'Package', 'Amount', 'Note', 'Time'];
$rows = [];

foreach (arowin_admin_list_failed_payments(true) as $row) {
    $rows[] = [
        '#' . (int) $row['id'],
        '<strong class="admin-mono">' . e((string) $row['username']) . '</strong>',
        '$' . e((string) $row['package_code']),
        e(arowin_admin_fmt_money((float) $row['amount_usdt'])),
        e((string) ($row['admin_note'] ?? '—')),
        '<span class="admin-muted">' . e((string) ($row['reviewed_at'] ?? $row['created_at'] ?? '')) . '</span>',
    ];
}

require dirname(__DIR__, 2) . '/includes/report-page.php';
