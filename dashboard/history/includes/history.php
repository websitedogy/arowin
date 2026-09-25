<?php
/**
 * Transaction History helpers (PhonePe-style statement)
 */
declare(strict_types=1);

/**
 * Category menu labels (All dropdown).
 *
 * @return array<string, string>
 */
function arowin_history_type_labels(): array
{
    return [
        'all' => 'All',
        'earnings' => 'Income',
        'sent' => 'Send',
        'received' => 'Received',
        'deposit' => 'Deposit',
        'withdrawals' => 'Withdrawal',
        'matching_income' => 'Matching Income',
        'referral_income' => 'Direct Income',
        'rank_bonus' => 'Rank Bonus Income',
        'daily_yield' => 'Daily Yield',
        'packages' => 'Packages',
    ];
}

function arowin_history_type_label(string $type): string
{
    return match ($type) {
        'deposit' => 'Deposit',
        'deposit_reversal' => 'Deposit reversed',
        'transfer_in' => 'Transfer in',
        'transfer_out' => 'Transfer out',
        'withdraw' => 'Withdrawal',
        'package_buy' => 'Package purchase',
        'referral_income' => 'Direct Income',
        'matching_income' => 'Matching Income',
        'daily_yield' => 'Daily Yield',
        'rank_bonus' => 'Rank Bonus Income',
        default => ucfirst(str_replace('_', ' ', $type)),
    };
}

function arowin_history_is_credit(string $type): bool
{
    return in_array($type, ['deposit', 'transfer_in', 'referral_income', 'matching_income', 'daily_yield', 'rank_bonus'], true);
}

/**
 * @return array{label:string,class:string}|null
 */
function arowin_history_status_badge(string $type): ?array
{
    return match ($type) {
        'deposit' => ['label' => 'Credited', 'class' => 'is-credited'],
        'deposit_reversal' => ['label' => 'Reversed', 'class' => 'is-reversed'],
        default => null,
    };
}

/**
 * @return array{type:string,q:string,from:string,to:string,page:int,preset:string,year:int,month:int}
 */
function arowin_history_parse_filters(array $src): array
{
    $type = strtolower(trim((string) ($src['type'] ?? 'all')));
    if ($type === 'income') {
        $type = 'earnings';
    }
    if ($type === 'send' || $type === 'transfer_out') {
        $type = 'sent';
    }
    if ($type === 'transfer_in') {
        $type = 'received';
    }
    if ($type === 'withdraw') {
        $type = 'withdrawals';
    }
    if ($type === 'direct_income' || $type === 'direct') {
        $type = 'referral_income';
    }
    if ($type === 'transfer') {
        $type = 'sent';
    }
    if (!isset(arowin_history_type_labels()[$type])) {
        $type = 'all';
    }

    $q = trim((string) ($src['q'] ?? ''));
    if (mb_strlen($q) > 80) {
        $q = mb_substr($q, 0, 80);
    }

    $preset = strtolower(trim((string) ($src['preset'] ?? '')));
    if ($preset === 'month') {
        $preset = 'last_month';
    }
    $year = (int) ($src['year'] ?? 0);
    $month = (int) ($src['month'] ?? 0);
    $from = trim((string) ($src['from'] ?? ''));
    $to = trim((string) ($src['to'] ?? ''));
    $todayDt = new DateTimeImmutable('today');
    $today = $todayDt->format('Y-m-d');
    $maxYear = (int) $todayDt->format('Y');
    $minYear = $maxYear - 6;

    if ($preset === 'today') {
        $from = $today;
        $to = $today;
        $year = 0;
        $month = 0;
    } elseif ($preset === 'week') {
        $from = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
        $to = $today;
        $year = 0;
        $month = 0;
    } elseif ($preset === 'last_month') {
        $start = new DateTimeImmutable('first day of last month');
        $end = new DateTimeImmutable('last day of last month');
        $from = $start->format('Y-m-d');
        $to = $end->format('Y-m-d');
        $year = 0;
        $month = 0;
    } elseif ($preset === 'all') {
        $from = '';
        $to = '';
        $year = 0;
        $month = 0;
    } elseif ($year >= $minYear && $year <= $maxYear) {
        $preset = '';
        if ($month >= 1 && $month <= 12) {
            $start = DateTimeImmutable::createFromFormat('!Y-n-j', $year . '-' . $month . '-1');
            if ($start instanceof DateTimeImmutable) {
                $from = $start->format('Y-m-d');
                $to = $start->modify('last day of this month')->format('Y-m-d');
                if ($to > $today) {
                    $to = $today;
                }
            } else {
                $year = 0;
                $month = 0;
            }
        } else {
            $month = 0;
            $from = sprintf('%04d-01-01', $year);
            $to = sprintf('%04d-12-31', $year);
            if ($to > $today) {
                $to = $today;
            }
        }
    } else {
        $year = 0;
        $month = 0;
        if (!in_array($preset, ['today', 'week', 'last_month', 'all'], true)) {
            $preset = '';
        }
    }

    if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = '';
    }
    if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = '';
    }

    $page = max(1, (int) ($src['page'] ?? 1));

    return [
        'type' => $type,
        'q' => $q,
        'from' => $from,
        'to' => $to,
        'page' => $page,
        'preset' => $preset,
        'year' => $year,
        'month' => $month,
    ];
}

function arowin_history_format_when(?string $createdAt): string
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
 * Build query string preserving filters + multi-tab ?as=.
 *
 * @param array<string, scalar|null> $overrides
 */
function arowin_history_qs(array $filters, array $overrides = []): string
{
    $data = array_merge($filters, $overrides);
    $out = [];
    foreach (['type', 'q', 'from', 'to', 'preset', 'year', 'month', 'page'] as $key) {
        $val = trim((string) ($data[$key] ?? ''));
        if ($val === '' || $val === '0'
            || ($key === 'type' && $val === 'all')
            || ($key === 'page' && $val === '1')
        ) {
            continue;
        }
        // Year/month filters use from/to; keep year+month in URL for UI state.
        if (in_array($key, ['from', 'to'], true) && ((int) ($data['year'] ?? 0) > 0 || in_array((string) ($data['preset'] ?? ''), ['today', 'week', 'last_month'], true))) {
            continue;
        }
        $out[$key] = $val;
    }

    // Keep tab account context so filters actually apply to the same ID.
    $as = arowin_normalize_sponsor_id((string) ($_GET['as'] ?? $_SESSION['arowin_uid'] ?? ''));
    if ($as !== '') {
        $out['as'] = $as;
    }

    return $out === [] ? '' : ('?' . http_build_query($out));
}
