<?php
/**
 * Dashboard stats helpers
 */
declare(strict_types=1);

function arowin_count_team_by_sponsor(string $sponsorUsername, ?string $leg = null): int
{
    $sponsorUsername = arowin_normalize_sponsor_id($sponsorUsername);
    if ($sponsorUsername === '') {
        return 0;
    }

    if ($leg !== null && in_array($leg, ['left', 'right'], true)) {
        $stmt = arowin_db()->prepare(
            'SELECT COUNT(*) FROM node_registrations
             WHERE UPPER(sponsor_id) = :s AND preferred_leg = :leg'
        );
        $stmt->execute([':s' => $sponsorUsername, ':leg' => $leg]);
    } else {
        $stmt = arowin_db()->prepare(
            'SELECT COUNT(*) FROM node_registrations WHERE UPPER(sponsor_id) = :s'
        );
        $stmt->execute([':s' => $sponsorUsername]);
    }

    return (int) $stmt->fetchColumn();
}

/**
 * @return array{
 *   initials:string,
 *   first_name:string,
 *   full_name:string,
 *   rank_label:string,
 *   status_label:string,
 *   status_active:bool,
 *   wallet:float,
 *   referral_income:float,
 *   matching_income:float,
 *   rank_bonus:float,
 *   daily_yield:float,
 *   left_team:int,
 *   right_team:int,
 *   matched_pairs:int,
 *   left_carry_team:int,
 *   right_carry_team:int,
 *   total_income:float,
 *   profile_photo:?string
 * }
 */
function arowin_dashboard_view_data(array $user): array
{
    $fullName = trim((string) ($user['full_name'] ?? 'Operator'));
    $parts = preg_split('/\s+/', $fullName) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    if ($initials === '') {
        $initials = 'AR';
    }

    $firstName = $parts[0] ?? $fullName;
    $status = strtolower((string) ($user['status'] ?? 'pending'));
    $statusActive = $status === 'active';

    $username = (string) ($user['username'] ?? '');
    $leftTeam = 0;
    $rightTeam = 0;
    $leftBusiness = 0.0;
    $rightBusiness = 0.0;
    if ($username !== '' && function_exists('arowin_binary_leg_stats')) {
        $leftStats = arowin_binary_leg_stats($username, 'left');
        $rightStats = arowin_binary_leg_stats($username, 'right');
        $leftTeam = (int) ($leftStats['count'] ?? 0);
        $rightTeam = (int) ($rightStats['count'] ?? 0);
        $leftBusiness = (float) ($leftStats['business'] ?? 0);
        $rightBusiness = (float) ($rightStats['business'] ?? 0);
    } elseif ($username !== '' && function_exists('arowin_count_binary_team')) {
        $leftTeam = arowin_count_binary_team($username, 'left');
        $rightTeam = arowin_count_binary_team($username, 'right');
    } else {
        $leftTeam = arowin_count_team_by_sponsor($username, 'left');
        $rightTeam = arowin_count_team_by_sponsor($username, 'right');
    }

    // Rank still uses starter/team counts.
    $matchedPairs = min($leftTeam, $rightTeam);
    $leftCarryTeam = max(0, $leftTeam - $matchedPairs);
    $rightCarryTeam = max(0, $rightTeam - $matchedPairs);

    // Carry forward business = unmatched package volume after 1:1.
    $matchedBusiness = min($leftBusiness, $rightBusiness);
    $leftCarryBusiness = max(0.0, round($leftBusiness - $matchedBusiness, 2));
    $rightCarryBusiness = max(0.0, round($rightBusiness - $matchedBusiness, 2));

    $canCatchup = $username !== ''
        && $statusActive
        && function_exists('arowin_income_page_catchup');
    if ($canCatchup) {
        try {
            arowin_income_page_catchup($username);
        } catch (Throwable $e) {
            // non-fatal — display still loads
        }
    }
    if ($username !== '' && function_exists('arowin_deposit_catchup_user')) {
        try {
            arowin_deposit_catchup_user($username);
        } catch (Throwable $e) {
            // non-fatal — IPN/status APIs still credit when they can
        }
    }
    $wallet = function_exists('arowin_wallet_balance')
        ? arowin_wallet_balance($username)
        : 0.0;
    $referral = function_exists('arowin_income_sum')
        ? arowin_income_sum($username, 'referral_income')
        : 0.0;
    $matching = function_exists('arowin_income_sum')
        ? arowin_income_sum($username, 'matching_income')
        : 0.0;
    $rankBonus = 0.0;
    $dailyYield = 0.0;
    if ($username !== '' && function_exists('arowin_daily_yield_process_user') && !function_exists('arowin_income_page_catchup')) {
        try {
            arowin_daily_yield_process_user($username);
        } catch (Throwable $e) {
            // non-fatal
        }
    }
    // Always unlock due weeks here. Catch-up may have timed out on matching
    // before rank bonus, and CLI cron used to miss the income stack.
    if ($username !== '' && function_exists('arowin_rank_bonus_process_user')) {
        try {
            arowin_rank_bonus_process_user($username);
        } catch (Throwable $e) {
            // non-fatal
        }
    }
    if ($username !== '' && function_exists('arowin_daily_yield_sum')) {
        $dailyYield = arowin_daily_yield_sum($username);
    }
    if ($username !== '' && function_exists('arowin_rank_bonus_sum')) {
        $rankBonus = arowin_rank_bonus_sum($username);
    }
    $earnings = $referral + $matching + $rankBonus + $dailyYield;

    $photoUrl = null;
    if (function_exists('arowin_user_profile_photo_url')) {
        $photoUrl = arowin_user_profile_photo_url($user);
    }

    $rankLabel = 'Unranked';
    if ($username !== '' && function_exists('arowin_user_rank_label')) {
        $rankLabel = arowin_user_rank_label($username);
    }

    return [
        'initials'         => $initials,
        'first_name'       => $firstName,
        'full_name'        => $fullName,
        'rank_label'       => $rankLabel,
        'status_label'     => $statusActive ? 'Active' : 'Pending',
        'status_active'    => $statusActive,
        'wallet'           => $wallet,
        'earnings'         => $earnings,
        'referral_income'  => $referral,
        'matching_income'  => $matching,
        'rank_bonus'       => $rankBonus,
        'daily_yield'      => $dailyYield,
        'left_team'        => $leftTeam,
        'right_team'       => $rightTeam,
        'matched_pairs'    => $matchedPairs,
        'left_carry_team'  => $leftCarryTeam,
        'right_carry_team' => $rightCarryTeam,
        'left_business'    => $leftBusiness,
        'right_business'   => $rightBusiness,
        'left_carry_business'  => $leftCarryBusiness,
        'right_carry_business' => $rightCarryBusiness,
        'total_income'     => $earnings,
        'profile_photo'    => $photoUrl,
    ];
}

function arowin_fmt_usdt(float $amount, int $decimals = 2): string
{
    return number_format($amount, $decimals) . ' USDT';
}

/**
 * Dashboard-friendly wallet type label.
 */
function arowin_dashboard_tx_label(string $type): string
{
    return match (strtolower(trim($type))) {
        'referral_income' => 'Direct Income',
        'matching_income' => 'Matching Income',
        'withdraw' => 'Withdrawal',
        'deposit' => 'Deposit',
        'deposit_reversal' => 'Deposit Reversal',
        'transfer_in' => 'Transfer In',
        'transfer_out' => 'Transfer Out',
        'package_buy' => 'Package Buy',
        default => ucwords(str_replace('_', ' ', $type)),
    };
}

/**
 * CSS class for Recent Transactions type column.
 */
function arowin_dashboard_tx_class(string $type): string
{
    return match (strtolower(trim($type))) {
        'referral_income' => 'tx-di',
        'matching_income' => 'tx-mi',
        'withdraw' => 'tx-wd',
        'deposit' => 'tx-dep',
        'deposit_reversal' => 'tx-wd',
        'transfer_in', 'transfer_out' => 'tx-tr',
        'package_buy' => 'tx-bn',
        default => 'tx-bn',
    };
}

function arowin_dashboard_tx_when(?string $createdAt): string
{
    if (function_exists('arowin_format_india_time')) {
        return arowin_format_india_time($createdAt, 'd M Y, h:i A');
    }
    $createdAt = trim((string) $createdAt);
    if ($createdAt === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($createdAt, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Asia/Kolkata'))
            ->format('d M Y, h:i A') . ' IST';
    } catch (Throwable $e) {
        return $createdAt;
    }
}

/**
 * Latest wallet rows for dashboard Recent Transactions.
 *
 * @return list<array{date:string,type:string,type_class:string,amount:string,status:string}>
 */
function arowin_dashboard_recent_transactions(string $username, int $limit = 6): array
{
    if (!function_exists('arowin_wallet_history')) {
        return [];
    }

    $limit = max(1, min(20, $limit));
    $rows = arowin_wallet_history($username, $limit);
    $out = [];

    foreach ($rows as $row) {
        $type = (string) ($row['type'] ?? '');
        $amount = (float) ($row['amount'] ?? 0);
        $decimals = abs($amount - round($amount)) < 0.001 ? 0 : 2;
        $out[] = [
            'date' => arowin_dashboard_tx_when(isset($row['created_at']) ? (string) $row['created_at'] : null),
            'type' => arowin_dashboard_tx_label($type),
            'type_class' => arowin_dashboard_tx_class($type),
            'amount' => arowin_fmt_usdt($amount, $decimals),
            'status' => 'Success',
        ];
    }

    return $out;
}
