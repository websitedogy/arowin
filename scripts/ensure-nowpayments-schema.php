<?php
/**
 * Ensure NOWPayments deposit/withdrawal tables exist.
 * Usage: php scripts/ensure-nowpayments-schema.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

arowin_ensure_deposits_schema();
arowin_ensure_withdrawals_schema();

echo "OK: wallet_deposits, wallet_withdrawals, nowpayments_webhook_logs ready.\n";
