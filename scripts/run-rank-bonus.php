<?php
/**
 * Cron / CLI: unlock Rank Bonus Income weeks (weekly amount × up to 52 weeks).
 *
 * Windows Task Scheduler example (daily check ~00:10):
 *   php C:\xampp\htdocs\arowin\scripts\run-rank-bonus.php
 *
 * Optional: php scripts/run-rank-bonus.php --backfill-enroll
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (!function_exists('arowin_rank_bonus_run_all')) {
    require_once dirname(__DIR__) . '/includes/wallet-repository.php';
    require_once dirname(__DIR__) . '/includes/ranks-repository.php';
    require_once dirname(__DIR__) . '/includes/admin-metrics-repository.php';
    require_once dirname(__DIR__) . '/includes/income-repository.php';
    require_once dirname(__DIR__) . '/includes/rank-bonus-repository.php';
}

$backfill = in_array('--backfill-enroll', $argv ?? [], true);
if ($backfill) {
    $r = arowin_rank_bonus_backfill_enrollments();
    echo ($r['message'] ?? 'done') . PHP_EOL;
}

$result = arowin_rank_bonus_run_all();
echo ($result['message'] ?? 'done') . PHP_EOL;
