<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'tds';
$ADMIN_TITLE = 'Total TDS amount';
$metrics = arowin_admin_dashboard_metrics();
$pct = max(0.0, (float) env('COMPANY_TDS_PERCENT', '0'));
$ADMIN_SUBTITLE = $pct > 0
    ? 'Estimated TDS (' . rtrim(rtrim(number_format($pct, 2), '0'), '.') . '% of all withdrawals)'
    : 'Set COMPANY_TDS_PERCENT in .env to estimate TDS from withdrawals';
$emptyMessage = 'No TDS amount configured yet. Add COMPANY_TDS_PERCENT to .env.';
$columns = ['Metric', 'Value'];
$rows = [
    [
        'TDS percent',
        e(number_format($pct, 2) . '%'),
    ],
    [
        'Estimated total TDS amount',
        '<strong class="value accent">' . e(arowin_admin_fmt_money((float) $metrics['total_tds'])) . '</strong>',
    ],
];

require dirname(__DIR__, 2) . '/includes/report-page.php';
