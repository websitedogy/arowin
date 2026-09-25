<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'turnover';
$ADMIN_TITLE = 'Today company turnover';
$ADMIN_SUBTITLE = 'Approved package purchases counted as company turnover today';
$emptyMessage = 'No approved turnover today.';
$columns = ['ID', 'User ID', 'Package', 'Amount', 'TX', 'Reviewed', 'Time'];
$rows = [];

foreach (arowin_admin_list_turnover(true) as $row) {
    $rows[] = [
        '#' . (int) $row['id'],
        '<strong class="admin-mono">' . e((string) $row['username']) . '</strong>',
        '$' . e((string) $row['package_code']),
        e(arowin_admin_fmt_money((float) $row['amount_usdt'])),
        '<span class="admin-mono">' . e(mb_substr((string) ($row['tx_hash'] ?? ''), 0, 18)) . '</span>',
        e((string) ($row['reviewed_by'] ?? '—')),
        '<span class="admin-muted">' . e((string) ($row['reviewed_at'] ?? $row['created_at'] ?? '')) . '</span>',
    ];
}

require dirname(__DIR__, 2) . '/includes/report-page.php';
