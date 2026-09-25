<?php
/**
 * NOWPayments gateway configuration
 */
declare(strict_types=1);

return [
    'api_key' => (string) env('NOWPAYMENTS_API_KEY', ''),
    'ipn_secret' => (string) env('NOWPAYMENTS_IPN_SECRET', ''),
    'email' => (string) env('NOWPAYMENTS_EMAIL', ''),
    'password' => (string) env('NOWPAYMENTS_PASSWORD', ''),
    /** Google Authenticator secret for auto-confirming payout 2FA (optional). */
    'totp_secret' => trim((string) env('NOWPAYMENTS_2FA_SECRET', '')),
    'sandbox' => filter_var(env('NOWPAYMENTS_SANDBOX', false), FILTER_VALIDATE_BOOLEAN),
    'timeout' => max(15, min(60, (int) env('NOWPAYMENTS_TIMEOUT', 45))),
    /**
     * Deposit checkout:
     * - payment = POST /v1/payment (pay_currency required) → app/hosted pay page
     * - invoice = POST /v1/invoice → NOWPayments hosted invoice_url (recommended UX)
     */
    'checkout_mode' => strtolower(trim((string) env('NOWPAYMENTS_CHECKOUT_MODE', 'payment'))),
    'pay_currency' => strtolower(trim((string) env('NOWPAYMENTS_PAY_CURRENCY', 'usdtbsc'))),
    'payout_currency' => strtolower(trim((string) env('NOWPAYMENTS_PAYOUT_CURRENCY', 'usdtbsc'))),
    'min_deposit' => max(1.0, (float) env('NOWPAYMENTS_MIN_DEPOSIT', 1)),
    'max_deposit' => max(1.0, (float) env('NOWPAYMENTS_MAX_DEPOSIT', 100000)),
    'min_withdraw' => max(10.0, (float) env('NOWPAYMENTS_MIN_WITHDRAW', 10)),
    'max_withdraw' => max(1.0, (float) env('NOWPAYMENTS_MAX_WITHDRAW', 50000)),
    'ipn_url' => trim((string) env('NOWPAYMENTS_IPN_URL', '')),
];
