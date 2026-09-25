<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$ADMIN_PAGE = 'surplus';
$ADMIN_TITLE = 'Company surplus amount';
$ADMIN_SUBTITLE = 'Approved package turnover minus total distributed income';
$metrics = arowin_admin_dashboard_metrics();
$emptyMessage = 'No surplus calculated yet.';
$columns = ['Metric', 'Value'];

$totalTurnover = 0.0;
try {
    if (function_exists('arowin_ensure_purchases_schema')) {
        arowin_ensure_purchases_schema();
    }
    $totalTurnover = (float) arowin_db()->query(
        "SELECT COALESCE(SUM(amount_usdt), 0) FROM package_purchases WHERE status = 'approved'"
    )->fetchColumn();
} catch (Throwable $e) {
    $totalTurnover = 0.0;
}

$rows = [
    ['Total approved turnover', e(arowin_admin_fmt_money($totalTurnover))],
    ['Total distributed income', e(arowin_admin_fmt_money((float) $metrics['total_distribute']))],
    [
        'Company surplus',
        '<strong class="value green">' . e(arowin_admin_fmt_money((float) $metrics['company_surplus'])) . '</strong>',
    ],
];

require dirname(__DIR__, 2) . '/includes/report-page.php';
