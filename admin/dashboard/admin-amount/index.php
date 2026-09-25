<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'admin-amount';
$ADMIN_TITLE = 'Total admin amount';
$metrics = arowin_admin_dashboard_metrics();
$pct = max(0.0, (float) env('COMPANY_ADMIN_PERCENT', '0'));
$ADMIN_SUBTITLE = $pct > 0
    ? 'Estimated admin fee (' . rtrim(rtrim(number_format($pct, 2), '0'), '.') . '% of all withdrawals)'
    : 'Set COMPANY_ADMIN_PERCENT in .env to estimate admin fees from withdrawals';
$emptyMessage = 'No admin amount configured yet. Add COMPANY_ADMIN_PERCENT to .env.';
$columns = ['Metric', 'Value'];
$rows = [
    [
        'Admin percent',
        e(number_format($pct, 2) . '%'),
    ],
    [
        'Estimated total admin amount',
        '<strong class="value green">' . e(arowin_admin_fmt_money((float) $metrics['total_admin'])) . '</strong>',
    ],
];

require dirname(__DIR__, 2) . '/includes/report-page.php';
