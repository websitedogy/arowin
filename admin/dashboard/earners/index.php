<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'earners';
$ADMIN_TITLE = 'Today earners';
$ADMIN_SUBTITLE = 'Members who received income payouts today';
$emptyMessage = 'No earners recorded today.';
$columns = ['User ID', 'Payouts', 'Total amount', 'Last payout'];
$rows = [];

foreach (arowin_admin_list_today_earners() as $row) {
    $rows[] = [
        '<strong class="admin-mono">' . e((string) $row['username']) . '</strong>',
        e((string) (int) $row['payouts']),
        e(arowin_admin_fmt_money((float) $row['total_amount'])),
        '<span class="admin-muted">' . e((string) ($row['last_at'] ?? '')) . '</span>',
    ];
}

require dirname(__DIR__, 2) . '/includes/report-page.php';
