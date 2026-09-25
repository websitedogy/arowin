<?php
/**
 * Arowin node package catalog (single source of truth)
 */
declare(strict_types=1);

/**
 * @return array<string, array{label:string, blurb:string, amount:int}>
 */
function arowin_package_catalog(): array
{
    return [
        '50'    => ['amount' => 50,    'label' => 'Starter $50',     'blurb' => 'Entry starter package'],
        '150'   => ['amount' => 150,   'label' => 'Node $150',       'blurb' => 'Entry binary node'],
        '350'   => ['amount' => 350,   'label' => 'Node $350',       'blurb' => 'Growth binary node'],
        '750'   => ['amount' => 750,   'label' => 'Node $750',       'blurb' => 'Standard operator node'],
        '1550'  => ['amount' => 1550,  'label' => 'Node $1550',      'blurb' => 'Advanced operator node'],
        '3150'  => ['amount' => 3150,  'label' => 'Node $3150',      'blurb' => 'Pro operator node'],
        '6350'  => ['amount' => 6350,  'label' => 'Node $6350',      'blurb' => 'Elite operator node'],
        '12750' => ['amount' => 12750, 'label' => 'Node $12750',     'blurb' => 'Premier operator node'],
    ];
}

/**
 * @return list<string>
 */
function arowin_package_codes(): array
{
    return array_keys(arowin_package_catalog());
}

function arowin_is_valid_package(string $code): bool
{
    return isset(arowin_package_catalog()[$code]);
}

/**
 * Normalize DB / form package code. Empty = no package purchased yet.
 */
function arowin_normalize_package(?string $code): string
{
    $code = trim((string) $code);
    return arowin_is_valid_package($code) ? $code : '';
}

/**
 * @return array{label:string, blurb:string, amount:int}|null
 */
function arowin_package_info(?string $code): ?array
{
    $code = arowin_normalize_package($code);
    if ($code === '') {
        return null;
    }
    return arowin_package_catalog()[$code];
}

function arowin_package_label(?string $code): string
{
    $info = arowin_package_info($code);
    return $info ? $info['label'] : 'No package';
}

function arowin_package_amount_label(?string $code): string
{
    $info = arowin_package_info($code);
    if (!$info) {
        return 'No package';
    }
    return '$' . number_format((int) $info['amount']);
}
