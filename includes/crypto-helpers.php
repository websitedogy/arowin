<?php
/**
 * Crypto address + amount validation helpers
 */
declare(strict_types=1);

/**
 * Validate TRON (TRC20) base58 address.
 */
function arowin_is_valid_tron_address(string $address): bool
{
    $address = trim($address);
    if (!preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address)) {
        return false;
    }
    return true;
}

/**
 * Validate Ethereum / ERC20 / BEP20 hex address.
 */
function arowin_is_valid_eth_address(string $address): bool
{
    return (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', trim($address));
}

function arowin_normalize_payout_currency(string $currency, string $fallback = 'usdtbsc'): string
{
    $currency = strtolower(trim($currency));
    $fallback = strtolower(trim($fallback));
    if ($fallback === '') {
        $fallback = 'usdtbsc';
    }

    return match ($currency) {
        'usdtbep20', 'usdt-bep20', 'usdt_bep20' => 'usdtbsc',
        'usdttrc', 'usdt-trc20', 'usdt_trc20' => 'usdttrc20',
        default => $currency !== '' ? $currency : $fallback,
    };
}

function arowin_payout_currency_for_address(string $address, string $fallback = 'usdtbsc'): string
{
    $address = trim($address);
    $fallback = arowin_normalize_payout_currency($fallback);
    if (arowin_is_valid_eth_address($address)) {
        return 'usdtbsc';
    }
    if (arowin_is_valid_tron_address($address)) {
        return 'usdttrc20';
    }

    return $fallback;
}

/**
 * Validate payout address for a NOWPayments currency code.
 */
function arowin_validate_crypto_address(string $address, string $currency = 'usdttrc20'): array
{
    $address = trim($address);
    $currency = strtolower(trim($currency));

    if ($address === '') {
        return ['ok' => false, 'message' => 'Wallet address is required.'];
    }
    if (strlen($address) < 20 || strlen($address) > 128) {
        return ['ok' => false, 'message' => 'Wallet address length is invalid.'];
    }
    if (preg_match('/\s/', $address)) {
        return ['ok' => false, 'message' => 'Wallet address must not contain spaces.'];
    }

    $tokenContracts = [
        '0x55d398326f99059ff775485246999027b3197955', // USDT on BSC
        '0xdac17f958d2ee523a2206206994597c13d831ec7', // USDT on Ethereum
        '0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d', // USDC on BSC
        '0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48', // USDC on Ethereum
    ];
    if (in_array(strtolower($address), $tokenContracts, true)) {
        return [
            'ok' => false,
            'message' => 'That is a USDT/USDC token contract, not your wallet. Open MetaMask and copy your Account address (0x…).',
        ];
    }

    $tronCoins = ['usdttrc20', 'trx', 'usdctrc20', 'tusdtrc20'];
    $ethCoins = ['usdterc20', 'eth', 'usdc', 'usdcerc20', 'dai', 'busd'];
    $bscCoins = ['usdtbsc', 'bnbbsc', 'busdbsc'];

    if (in_array($currency, $tronCoins, true) || str_ends_with($currency, 'trc20')) {
        if (!arowin_is_valid_tron_address($address)) {
            return ['ok' => false, 'message' => 'Enter a valid TRC20 (TRON) address starting with T.'];
        }
        return ['ok' => true, 'message' => 'OK', 'network' => 'TRC20'];
    }

    if (in_array($currency, $ethCoins, true) || str_ends_with($currency, 'erc20')) {
        if (!arowin_is_valid_eth_address($address)) {
            return ['ok' => false, 'message' => 'Enter a valid ERC20 address (0x…).'];
        }
        return ['ok' => true, 'message' => 'OK', 'network' => 'ERC20'];
    }

    if (in_array($currency, $bscCoins, true) || str_contains($currency, 'bsc')) {
        if (!arowin_is_valid_eth_address($address)) {
            return ['ok' => false, 'message' => 'Enter a valid BEP20 address (0x…).'];
        }
        return ['ok' => true, 'message' => 'OK', 'network' => 'BEP20'];
    }

    // Unknown currency — accept non-empty alphanumeric-ish addresses
    if (!preg_match('/^[A-Za-z0-9:_-]+$/', $address)) {
        return ['ok' => false, 'message' => 'Wallet address contains invalid characters.'];
    }
    return ['ok' => true, 'message' => 'OK', 'network' => 'OTHER'];
}

function arowin_money_ok(float $amount, float $min, float $max, string $label = 'Amount'): array
{
    $amount = round($amount, 2);
    if ($amount < $min) {
        return ['ok' => false, 'message' => $label . ' minimum is ' . number_format($min, 2) . ' USDT.'];
    }
    if ($amount > $max) {
        return ['ok' => false, 'message' => $label . ' maximum is ' . number_format($max, 2) . ' USDT.'];
    }
    return ['ok' => true, 'message' => 'OK', 'amount' => $amount];
}
