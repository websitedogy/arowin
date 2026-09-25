<?php
/**
 * Cron / CLI: credit pure-$50 daily yield ($0.25 × up to 400 days).
 *
 * Windows Task Scheduler example (daily ~00:05):
 *   php C:\xampp\htdocs\arowin\scripts\run-daily-yield.php
 *
 * Optional: php scripts/run-daily-yield.php --backfill-enroll
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (!function_exists('arowin_daily_yield_run_all')) {
    require_once dirname(__DIR__) . '/includes/wallet-repository.php';
    require_once dirname(__DIR__) . '/includes/ranks-repository.php';
    require_once dirname(__DIR__) . '/includes/admin-metrics-repository.php';
    require_once dirname(__DIR__) . '/includes/income-repository.php';
    require_once dirname(__DIR__) . '/includes/daily-yield-repository.php';
}

$backfill = in_array('--backfill-enroll', $argv ?? [], true);
if ($backfill) {
    $r = arowin_daily_yield_backfill_enrollments();
    echo ($r['message'] ?? 'done') . PHP_EOL;
}

$result = arowin_daily_yield_run_all();
echo ($result['message'] ?? 'done') . PHP_EOL;
