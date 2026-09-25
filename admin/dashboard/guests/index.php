<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'guests';
$ADMIN_TITLE = 'All guest list';
$ADMIN_SUBTITLE = 'Guest contacts collected by all members';
$emptyMessage = 'No guests found.';
$columns = ['ID', 'Owner', 'Guest', 'Phone', 'Status', 'Added'];
$rows = [];

foreach (arowin_admin_list_guests() as $row) {
    $rows[] = [
        '#' . (int) $row['id'],
        '<strong class="admin-mono">' . e((string) $row['owner_username']) . '</strong>',
        e((string) ($row['full_name'] ?? '—')),
        e((string) ($row['phone'] ?? '—')),
        '<span class="admin-badge">' . e((string) ($row['status'] ?? '')) . '</span>',
        '<span class="admin-muted">' . e((string) ($row['created_at'] ?? '')) . '</span>',
    ];
}

require dirname(__DIR__, 2) . '/includes/report-page.php';
